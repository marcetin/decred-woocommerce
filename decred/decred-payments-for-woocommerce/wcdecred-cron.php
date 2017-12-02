<?php
/*
Decred Payments for WooCommerce
https://marcetin.com/
*/

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
// Include everything
define('WCDECRED_MUST_LOAD_WP',  '1');
include (dirname(__FILE__) . '/wcdecred-include-all.php');

// Cpanel-scheduled cron job call
if (@$_REQUEST['hardcron']=='1')
  WCDECRED_cron_job_worker (true);

//===========================================================================
// '$hardcron' == true if job is ran by Cpanel's cron job.

function WCDECRED_cron_job_worker ($hardcron=false)
{
  global $wpdb;

  $wcdecred_settings ='';
  $wcdecred_settings = WCDECRED__get_settings ();

  // status = "unused", "assigned", "used"
  $decred_addresses_table_name     = $wpdb->prefix . 'wcdecred_addresses';
  $decred_uuid_table_name          = $wpdb->prefix . 'wcdecred_uuid';

  $funds_received_value_expires_in_secs = $wcdecred_settings['funds_received_value_expires_in_mins'] * 60;
  $assigned_address_expires_in_secs     = $wcdecred_settings['assigned_address_expires_in_mins'] * 60;

  //Get numbers of confirmations requiered
  $confirmations = @$wcdecred_settings ['gateway_settings']['decred_confirmations'];

  $clean_address = NULL;
  $current_time = time();

  // Search for completed orders (addresses that received full payments for their orders) ...

  // NULL == not found
  // Retrieve:
  //     'assigned'   - unexpired, with old balances (due for revalidation. Fresh balances and still 'assigned' means no [full] payment received yet)
  //     'revalidate' - all
  //        order results by most recently assigned
  $query =
    "SELECT * FROM `$decred_addresses_table_name`
      WHERE
      (
        (`status`='assigned' AND (('$current_time' - `assigned_at`) < '$assigned_address_expires_in_secs'))
        OR
        (`status`='revalidate')
      )
      AND (('$current_time' - `received_funds_checked_at`) > '$funds_received_value_expires_in_secs')
      ORDER BY `received_funds_checked_at` ASC;"; // Check the ones that haven't been checked for longest time
  $rows_for_balance_check = $wpdb->get_results ($query, ARRAY_A);


  if (is_array($rows_for_balance_check))
  	$count_rows_for_balance_check = count($rows_for_balance_check);
  else
  	$count_rows_for_balance_check = 0;

  if (is_array($rows_for_balance_check))
  {
  	$ran_cycles = 0;
  	foreach ($rows_for_balance_check as $row_for_balance_check)
  	{   
  		$ran_cycles++;	// To limit number of cycles per soft cron job.

		  // Prepare 'address_meta' for use.
		  $address_meta    = WCDECRED_unserialize_address_meta (@$row_for_balance_check['address_meta']);
		  $last_order_info = @$address_meta['orders'][0];

		  $row_id       = $row_for_balance_check['id'];


		  // Retrieve current balance at address.
		  $balance_info_array = WCDECRED__getreceivedbyaddress_info (false, $row_for_balance_check['decred_account'], $confirmations, $wcdecred_settings['blockchain_api_timeout_secs']);
		   
		  if ($balance_info_array['result'] == 'success')
		  {
        $current_time = time();
        $query =
          "UPDATE `$decred_addresses_table_name`
             SET
                `total_received_funds` = '{$balance_info_array['balance']}',
                `received_funds_checked_at`='$current_time'
            WHERE `id`='$row_id';";
        $ret_code = $wpdb->query ($query);

        if ($balance_info_array['balance'] > 0)
        {

          if ($row_for_balance_check['status'] == 'revalidate')
          {
            // Address with suddenly appeared balance. Check if that is matching to previously-placed [likely expired] order
            if (!$last_order_info || !@$last_order_info['order_id'] || !@$balance_info_array['balance'] || !@$last_order_info['order_total'])
            {
              // No proper metadata present. Mark this address as 'xused' (used by unknown entity outside of this application) and be done with it forever.
              $query =
                "UPDATE `$decred_addresses_table_name`
                   SET
                      `status` = 'xused'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);
              continue;
            }
            else
            {
              // Metadata for this address is present. Mark this address as 'assigned' and treat it like that further down...
              $query =
                "UPDATE `$decred_addresses_table_name`
                   SET
                      `status` = 'assigned'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);
            }
          }

          WCDECRED__log_event (__FILE__, __LINE__, "Cron job: NOTE: Detected non-zero balance at address: '{$row_for_balance_check['decred_address']}, order ID = '{$last_order_info['order_id']}'. Detected balance ='{$balance_info_array['balance']}'.");
          if ($balance_info_array['balance'] < $last_order_info['order_total'])
          {
            WCDECRED__log_event (__FILE__, __LINE__, "Cron job: NOTE: balance at address: '{$row_for_balance_check['decred_address']}' (DECRED '{$balance_info_array['balance']}') is not yet sufficient to complete it's order (order ID = '{$last_order_info['order_id']}'). Total required: '{$last_order_info['order_total']}'. Will wait for more funds to arrive...".$wcdecred_settings['funds_received_value_expires_in_mins']."a".$wcdecred_settings['assigned_address_expires_in_mins']);
          }
        }
        else
        {
			WCDECRED__log_event (__FILE__, __LINE__, "Cron job: NOTE: Detected balance zero at address: '{$row_for_balance_check['decred_address']}, order ID = '{$last_order_info['order_id']}'.");

        }

        // Note: to be perfectly safe against late-paid orders, we need to:
        //	Scan '$address_meta['orders']' for first UNPAID order that is exactly matching amount at address.

		    if ($balance_info_array['balance'] >= $last_order_info['order_total'])
		    {

	        // Last order was fully paid! Complete it...
	        WCDECRED__log_event (__FILE__, __LINE__, "Cron job: NOTE: Full payment for order ID '{$last_order_info['order_id']}' detected at address: '{$row_for_balance_check['decred_address']}' (DECRED '{$balance_info_array['balance']}'). Total was required for this order: '{$last_order_info['order_total']}'. Processing order ...");

	        // Update order' meta info
	        $address_meta['orders'][0]['paid'] = true;

	        // Process and complete the order within WooCommerce (send confirmation emails, etc...)
	        WCDECRED__process_payment_completed_for_order ($last_order_info['order_id'], $balance_info_array['balance']);

	        // Update address' record
	        $address_meta_serialized = WCDECRED_serialize_address_meta ($address_meta);

	        // Update DB - mark address as 'used'.
	        //
	        $current_time = time();

          // Note: `total_received_funds` and `received_funds_checked_at` are already updated above.
          //
	        $query =
	          "UPDATE `$decred_addresses_table_name`
	             SET
	                `status`='used',
	                `address_meta`='$address_meta_serialized'
	            WHERE `id`='$row_id';";
	        $ret_code = $wpdb->query ($query);
	        WCDECRED__log_event (__FILE__, __LINE__, "Cron job: SUCCESS: Order ID '{$last_order_info['order_id']}' successfully completed.");

		    }
		  }
		  else
		  {
		    WCDECRED__log_event (__FILE__, __LINE__, "Cron job: Warning: Cannot retrieve balance for address: '{$row_for_balance_check['decred_address']}: " . $balance_info_array['message']);
		  }
		  //..//
		}
	}
	
//============================================================================
//**************** Cancelled expired orders***************************************
 $query =
    "SELECT * FROM `$decred_addresses_table_name`
      WHERE
      (
        (`status`='assigned' AND (('$current_time' - `assigned_at`) > '$assigned_address_expires_in_secs'))
        OR
        (`status`='revalidate')
      )
      AND (('$current_time' - `received_funds_checked_at`) > '$funds_received_value_expires_in_secs')
      ORDER BY `received_funds_checked_at` ASC;"; // Check the ones that haven't been checked for longest time
  $rows_for_clean = $wpdb->get_results ($query, ARRAY_A);

  if (is_array($rows_for_clean))
  	$count_rows_for_clean = count($rows_for_clean);
  else
  	$count_rows_for_clean = 0;


  if (is_array($rows_for_clean))
  {
  	$ran_cycles = 0;
  	foreach ($rows_for_clean as $row_for_clean)
  	{
  		$ran_cycles++;	// To limit number of cycles per soft cron job.

		  // Prepare 'address_meta' for use.
		  $address_meta    = WCDECRED_unserialize_address_meta (@$row_for_clean['address_meta']);
		  $last_order_info = @$address_meta['orders'][0];

		  $row_id       = $row_for_clean['id'];


		  // Retrieve current balance at address.
		  $balance_info_array = WCDECRED__getreceivedbyaddress_info (false, $row_for_clean['decred_account'], $confirmations, $wcdecred_settings['blockchain_api_timeout_secs']);
		  if ($balance_info_array['result'] == 'success')
		  {
        // Refresh 'received_funds_checked_at' field
        $current_time = time();
        $query =
          "UPDATE `$decred_addresses_table_name`
             SET
                `total_received_funds` = '{$balance_info_array['balance']}',
                `received_funds_checked_at`='$current_time'
            WHERE `id`='$row_id';";
        $ret_code = $wpdb->query ($query);

        if ($balance_info_array['balance'] > 0)
        {
          if ($row_for_clean['status'] == 'revalidate')
          {
            // Address with suddenly appeared balance. Check if that is matching to previously-placed [likely expired] order
            if (!$last_order_info || !@$last_order_info['order_id'] || !@$balance_info_array['balance'] || !@$last_order_info['order_total'])
            {
              // No proper metadata present. Mark this address as 'xused' (used by unknown entity outside of this application) and be done with it forever.
              $query =
                "UPDATE `$decred_addresses_table_name`
                   SET
                      `status` = 'xused'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);
              continue;
            }
            else
            {
              // Metadata for this address is present. Mark this address as 'assigned' and treat it like that further down...
              $query =
                "UPDATE `$decred_addresses_table_name`
                   SET
                      `status` = 'assigned'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);
            }
          }

          WCDECRED__log_event (__FILE__, __LINE__, "Cron job: NOTE: Detected non-zero balance at address: '{$row_for_clean['decred_address']}, order ID = '{$last_order_info['order_id']}'. Detected balance ='{$balance_info_array['balance']}'.");

          if ($balance_info_array['balance'] < $last_order_info['order_total'])
          {
            WCDECRED__log_event (__FILE__, __LINE__, "Cron job Cancell: NOTE: balance at address: '{$row_for_clean['decred_address']}' (DECRED '{$balance_info_array['balance']}') is not yet sufficient to complete it's order (order ID = '{$last_order_info['order_id']}'). Total required: '{$last_order_info['order_total']}'.");
          }
        }
        else
        {
			// Process and cancelled the order within WooCommerce
	        WCDECRED__process_cancelled_for_order ($last_order_info['order_id']);

	        // Update address' record
	        $address_meta_serialized = WCDECRED_serialize_address_meta ($address_meta);
		  	
		  // Mark this addres as "cancelled" for cancell order
              $query =
                "UPDATE `$decred_addresses_table_name`
                   SET
                      `status` = 'cancelled'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);	
			

        }

        // Note: to be perfectly safe against late-paid orders, we need to:
        //	Scan '$address_meta['orders']' for first UNPAID order that is exactly matching amount at address.

		    if ($balance_info_array['balance'] >= $last_order_info['order_total'])
		    {

	        // Last order was fully paid! Complete it...
	        WCDECRED__log_event (__FILE__, __LINE__, "Cron job: NOTE: Full payment for order ID '{$last_order_info['order_id']}' detected at address: '{$row_for_clean['decred_address']}' (DECRED '{$balance_info_array['balance']}'). Total was required for this order: '{$last_order_info['order_total']}'. Processing order ...");

	        // Update order' meta info
	        $address_meta['orders'][0]['paid'] = true;

	        // Process and complete the order within WooCommerce (send confirmation emails, etc...)
	        WCDECRED__process_payment_completed_for_order ($last_order_info['order_id'], $balance_info_array['balance']);

	        // Update address' record
	        $address_meta_serialized = WCDECRED_serialize_address_meta ($address_meta);

	        // Update DB - mark address as 'used'.
	        //
	        $current_time = time();

          // Note: `total_received_funds` and `received_funds_checked_at` are already updated above.
          //
	        $query =
	          "UPDATE `$decred_addresses_table_name`
	             SET
	                `status`='used',
	                `address_meta`='$address_meta_serialized'
	            WHERE `id`='$row_id';";
	        $ret_code = $wpdb->query ($query);
	        WCDECRED__log_event (__FILE__, __LINE__, "Cron job: SUCCESS: Order ID '{$last_order_info['order_id']}' successfully completed.");

		    }
			else{ //cancell orders if balance not completed
			// Process and cancelled the order within WooCommerce
	        WCDECRED__process_cancelled_for_order ($last_order_info['order_id']);

	        // Update address' record
	        $address_meta_serialized = WCDECRED_serialize_address_meta ($address_meta);
		  	
		  // Mark this addres as "cancelled" for cancell order
              $query =
                "UPDATE `$decred_addresses_table_name`
                   SET
                      `status` = 'cancelled'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);	
				
			}	
		  }
		  else
		  {
		    WCDECRED__log_event (__FILE__, __LINE__, "Cron job: Warning: Cannot retrieve balance for address: '{$row_for_clean['decred_address']}: " . $balance_info_array['message']);
		  }
		  //..//
		}
	}
//-----------------------------------------------------------------------------////
//--------------Auto create order selling on Bittrex--------------------------/////
if($wcdecred_settings ['gateway_settings']['decred_bittrex_active']=='yes'){
 // Retrieve current balance at wallet.
		  $balance_info = WCDECRED__getbalancewallet_info($wcdecred_settings, $wcdecred_settings['blockchain_api_timeout_secs']);
		   
		  if ($balance_info['result'] == 'success')
		  { if ($balance_info['balance'] >= $wcdecred_settings ['gateway_settings']['decred_amount_bittrex'] )
		    { 
		      $deposit_address = WCDECRED__bittrex_getdeposit_address(@$wcdecred_settings ['gateway_settings']['decred_apikey_bittrex']);
		       	if ($deposit_address['result'] == 'success')
		  		{ 
				  $txid = WCDECRED__senttoaddres ($wcdecred_settings, $deposit_address['deposit_address'], $balance_info['balance'] - 0.00001);
				  if ($txid['result'] == 'success')
		  			{ 
				 	 WCDECRED__log_event (__FILE__, __LINE__, "Cron job: Succes withdraw: Withdraw amount '{$balance_info['balance']}' to address: '{$deposit_address['deposit_address']}'. TxID: '{$txid['txid']}'");		
					}else{//senttoaddres  
					WCDECRED__log_event (__FILE__, __LINE__, "Cron job: Error withdraw: Error execute withdraw to address: '{$deposit_address['deposit_address']}': '{$txid['message']}'.{$balance_info['balance']}");	
				 	 }	//senttoaddres 
				}else{ //deposit_address 
				     WCDECRED__log_event (__FILE__, __LINE__, "Cron job: Error: Imposible get deposit address to bittrex:'{$deposit_address['message']}'.");	
				}//deposit_address 		
			}//balance is low  
			
	      }else{ //get balance
			WCDECRED__log_event (__FILE__, __LINE__, "Cron job: Warning: Cannot retrieve balance for wallet: '{$balance_info['message']}: ");  
			  
		}
		
//Verify for selling order

$balance_avail_bittrex = WCDECRED__bittrex_getbalance($wcdecred_settings ['gateway_settings']['decred_apikey_bittrex']);	
	if ($balance_avail_bittrex['result'] == 'success')
		  { 
		  if ($balance_avail_bittrex['balance_av'] >= $wcdecred_settings ['gateway_settings']['decred_amount_bittrex'] )
		    { 
			 $sell_order = WCDECRED__bittrex_sellmarket ($wcdecred_settings ['gateway_settings']['decred_apikey_bittrex'], $balance_avail_bittrex['balance_av']);
			 
			 if($sell_order['result'] == 'success'){
			  $decred_Uuid_table_name     = $wpdb->prefix . 'wcdecred_Uuid';
			  
			  $uuid = $sell_order['resultUuid'];
			  $amount = $balance_avail_bittrex['balance_av'];
			  
			  $query =
      "INSERT INTO `$decred_uuid_table_name`
      (`id`, `decred_uuid`, `decred_amount`, `decred_sell_at`) VALUES
      ('','$uuid', '$amount', NOW());";
				  
              $ret_code = $wpdb->query ($query);
				 
				WCDECRED__log_event (__FILE__, __LINE__, "Cron job: Succes SellMarket: resultUuid: '{$sell_order['resultUuid']}' , Amount: '{$balance_avail_bittrex['balance_av']}'. ".var_dump($wcdecred_settings)); 
			 }else{	 //sellorder
			   WCDECRED__log_event (__FILE__, __LINE__, "Cron job: Warning SellMarket: Cannot place sell order: '{$sell_order['message']}'.");  
			 }//sellorder
		}//balance low
	}	else{ //balance available
		
		WCDECRED__log_event (__FILE__, __LINE__, "Cron job: Warning: Cannot retrieve balance available: '{$balance_avail_bittrex['message']}: ");
	    }
			  	
}// if bittrex auto order is active		
	

}
//===========================================================================
