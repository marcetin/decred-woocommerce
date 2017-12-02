<?php
/*
Decred Payments for WooCommerce
*/


//===========================================================================
/*
   Input:
   ------
      $order_info =
         array (
            'order_id'        => $order_id,
            'order_total'     => $order_total_in_decred,
            'order_datetime'  => date('Y-m-d H:i:s T'),
            'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
            );
*/
// Returns:
// --------
/*
    $ret_info_array = array (
       'result'                      => 'success', // OR 'error'
       'message'                     => '...',
       'host_reply_raw'              => '......',
       'generated_decred_address'   => '1H9uAP3x439YvQDoKNGgSYCg3FmrYRzpD2', // or false
       );
*/
//

error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'html_errors', 'On' );

function WCDECRED__get_decred_address_for_payment($order_info) 
{
   global $wpdb;

   // status = "unused", "assigned", "used"
   $decred_addresses_table_name     = $wpdb->prefix . 'wcdecred_addresses';
   
   $wcdecred_settings = WCDECRED__get_settings ();
   $clean_address = NULL;
   $current_time = time();

  //-------------------------------------------------------
  if (!$clean_address)
  {
    // Still could not find unused virgin address. Time to generate it from scratch.
    /*
    Returns:
       $ret_info_array = array (
          'result'                      => 'success', // 'error'
          'message'                     => '', // Failed to find/generate decred address',
          'host_reply_raw'              => '', // Error. No host reply availabe.',
          'generated_decred_address'   => '1FVai2j2FsFvCbdcr22ZbSMfUd3HLUHvKx', // false,
          );
    */
    $ret_addr_array = WCDECRED__generate_new_decred_address_wallet ($wcdecred_settings, $order_info);
    if ($ret_addr_array['result'] == 'success')
      $clean_address = $ret_addr_array['generated_decred_address'];
  }
  //-------------------------------------------------------

  //-------------------------------------------------------
   if ($clean_address)
   {
   /*
         $order_info =
         array (
            'order_id'     => $order_id,
            'order_total'  => $order_total_in_decred,
            'order_datetime'  => date('Y-m-d H:i:s T'),
            'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
            );

*/

      /*
      $address_meta =
         array (
            'orders' =>
               array (
                  // All orders placed on this address in reverse chronological order
                  array (
                     'order_id'     => $order_id,
                     'order_total'  => $order_total_in_decred,
                     'order_datetime'  => date('Y-m-d H:i:s T'),
                     'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
                  ),
                  array (
                     ...
                  ),
               ),
            'other_meta_info' => array (...)
         );
      */

      // Prepare `address_meta` field for this clean address.
      $address_meta = $wpdb->get_var ("SELECT `address_meta` FROM `$decred_addresses_table_name` WHERE `decred_address`='$clean_address'");
      $address_meta = WCDECRED_unserialize_address_meta ($address_meta);

      if (!isset($address_meta['orders']) || !is_array($address_meta['orders']))
         $address_meta['orders'] = array();

      array_unshift ($address_meta['orders'], $order_info);    // Prepend new order to array of orders
      if (count($address_meta['orders']) > 10)
         array_pop ($address_meta['orders']);   // Do not keep history of more than 10 unfullfilled orders per address.
      $address_meta_serialized = WCDECRED_serialize_address_meta ($address_meta);

      // Update DB with balance and timestamp, mark address as 'assigned' and return this address as clean.
      //
      $current_time = time();
      $remote_addr  = $order_info['requested_by_ip'];
      $query =
      "UPDATE `$decred_addresses_table_name`
         SET
            `total_received_funds` = '0',
            `received_funds_checked_at`='$current_time',
            `status`='assigned',
            `assigned_at`='$current_time',
            `last_assigned_to_ip`='$remote_addr',
            `address_meta`='$address_meta_serialized'
        WHERE `decred_address`='$clean_address';";
      $ret_code = $wpdb->query ($query);

      $ret_info_array = array (
         'result'                      => 'success',
         'message'                     => "",
         'host_reply_raw'              => "",
         'generated_decred_address'   => $clean_address,
         );

      return $ret_info_array;
  }
  //-------------------------------------------------------

   $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => 'Failed to find/generate decred address. ' . $ret_addr_array['message'],
      'host_reply_raw'              => $ret_addr_array['host_reply_raw'],
      'generated_decred_address'   => false,
      );
   return $ret_info_array;
}
//===========================================================================

//===========================================================================

//===========================================================================
/*
Returns:
   $ret_info_array = array (
      'result'                      => 'success', // 'error'
      'message'                     => '', // Failed to find/generate decred address',
      'host_reply_raw'              => '', // Error. No host reply availabe.',
      'generated_decred_address'   => '1FVai2j2FsFvCbdcr22ZbSMfUd3HLUHvKx', // false,
      );
*/
//
function WCDECRED__generate_new_decred_address_wallet ($wcdecred_settings=false, $order_info)
{
  global $wpdb;

  $decred_addresses_table_name = $wpdb->prefix . 'wcdecred_addresses';

  if (!$wcdecred_settings){
    $wcdecred_settings = WCDECRED__get_settings();
	}
	// Setting account for get new address
  $decred_prefix = @$wcdecred_settings ['gateway_settings']['decred_addprefix'];
  $decred_account = $decred_prefix."_".$order_info['order_id'];
  //Get numbers of confirmations requiered
  $confirmations = @$wcdecred_settings ['gateway_settings']['decred_confirmations'];
 /* if($decred_prefix){
  	  $decred_account = $decred_prefix."_".$order_info['order_id'];
  }else{
	  $decred_account =(string)$order_info['order_id'];
  }*/


		$decred =  new Decred($rpcuser,$rpcpass,$rpchost,$rpcport);
	if($ssl_true=='yes'){
        $decred->setSSL(null);
    }
    
     
  $clean_address = false;

  // Find next index to generate
  $next_key_index = $wpdb->get_var ("SELECT MAX(`index_in_wallet`) AS `max_index_in_wallet` FROM `$decred_addresses_table_name`;");
  if ($next_key_index === NULL)
    $next_key_index = $wcdecred_settings['starting_index_for_new_decred_addresses']; // Start generation of addresses from index #2 (skip two leading wallet's addresses)
  else
    $next_key_index = $next_key_index+1;  // Continue with next index

  $total_new_keys_generated = 0;
  $blockchains_api_failures = 0;
  do
  {
    $new_decred_address_result = WCDECRED__generate_decred_address_from_rpc ($wcdecred_settings, $decred_account);
	
	if($new_decred_address_result['result']=='error'){
	   $ret_info_array = array (
          'result'                      => 'error',
          'message'                     => $new_decred_address_result['message']!=''?$new_decred_address_result['message']:"Problem: Generated Decred Address",
          'host_reply_raw'              => "",
          'generated_decred_address'   => false,
          );
        return $ret_info_array;	
	}
	$new_decred_address = $new_decred_address_result['generated_decred_address'];	
    $ret_info_array  = WCDECRED__getreceivedbyaddress_info ($wcdecred_settings, $decred_account, $confirmations);
    $total_new_keys_generated ++;

    if ($ret_info_array['balance'] === false)
      $status = 'unknown';
    else if ($ret_info_array['balance'] == 0)
      $status = 'unused'; // Newly generated address with freshly checked zero balance is unused and will be assigned.
    else
      $status = 'used';   // Generated address that was already used to receive money.

    $funds_received                  = ($ret_info_array['balance'] === false)?-1:$ret_info_array['balance'];
    $received_funds_checked_at_time  = ($ret_info_array['balance'] === false)?0:time();

    // Insert newly generated address into DB
    $query =
      "INSERT INTO `$decred_addresses_table_name`
      (`decred_address`, `decred_account`, `index_in_wallet`, `total_received_funds`, `received_funds_checked_at`, `status`) VALUES
      ('$new_decred_address', '$decred_account', '$next_key_index', '$funds_received', '$received_funds_checked_at_time', '$status');";
    $ret_code = $wpdb->query ($query);

    $next_key_index++;

    if ($ret_info_array['balance'] === false)
    {
      $blockchains_api_failures ++;
      if ($blockchains_api_failures >= $wcdecred_settings['max_blockchains_api_failures'])
      {
        // Allow no more than 3 contigious blockchains API failures. After which return error reply.
        $ret_info_array = array (
          'result'                      => 'error',
          'message'                     => $ret_info_array['message'],
          'host_reply_raw'              => "",
          'generated_decred_address'   => false,
          );
        return $ret_info_array;
      }
    }
    else
    {
      if ($ret_info_array['balance'] == 0)
      {
        // Update DB with balance and timestamp, mark address as 'assigned' and return this address as clean.
        $clean_address    = $new_decred_address;
      }
    }

    if ($clean_address)
      break;

    if ($total_new_keys_generated >= $wcdecred_settings['max_unusable_generated_addresses'])
    {
      // Stop it after generating of 20 unproductive addresses.
      // Something is wrong. Possibly old merchant's wallet (with many used addresses) is used for new installation. - For this case 'starting_index_for_new_decred_addresses'
      //  needs to be proper set to high value.
      $ret_info_array = array (
        'result'                      => 'error',
        'message'                     => "Problem: Generated '$total_new_keys_generated' addresses and none were found to be unused. Possibly old merchant's wallet (with many used addresses) is used for new installation. If that is the case - 'starting_index_for_new_decred_addresses' needs to be proper set to high value",
        'host_reply_raw'              => '',
        'generated_decred_address'   => false,
        );
      return $ret_info_array;
    }

  } while (true);

  // Here only in case of clean address.
  $ret_info_array = array (
    'result'                      => 'success',
    'message'                     => '',
    'host_reply_raw'              => '',
    'generated_decred_address'   => $clean_address,
    );

  return $ret_info_array;
}



//===========================================================================

//===========================================================================
// Function makes sure that returned value is valid array
function WCDECRED_unserialize_address_meta ($flat_address_meta)
{
   $unserialized = @unserialize($flat_address_meta);
   if (is_array($unserialized))
      return $unserialized;
   return array();
}
//===========================================================================

//===========================================================================
// Function makes sure that value is ready to be stored in DB
function WCDECRED_serialize_address_meta ($address_meta_arr)
{
   return WCDECRED__safe_string_escape(serialize($address_meta_arr));
}
//===========================================================================

//===========================================================================
function WCDECRED__generate_decred_address_from_rpc ($wcdecred_settings=false, $decred_account)
{
	if (!$wcdecred_settings){
    $wcdecred_settings = WCDECRED__get_settings();
	}
	$ssl_true = @$wcdecred_settings ['gateway_settings']['decred_rpcssl_active'];
	$rpcuser = @$wcdecred_settings ['gateway_settings']['decred_rpcuser'];
	$rpcpass = @$wcdecred_settings ['gateway_settings']['decred_rpcpass'];
	$rpchost = @$wcdecred_settings ['gateway_settings']['decred_rpchostaddr'];
	$rpcport = @$wcdecred_settings ['gateway_settings']['decred_rpcport'];
	
	
	//Create rpc connection
	$decred =  new Decred($rpcuser,$rpcpass,$rpchost,$rpcport);
	if($ssl_true=='yes'){
        $decred->setSSL(null);
    }
    	$decred->createnewaccount($decred_account);  

    $response = $decred->getnewaddress($decred_account);  
	if($response['status']== 'error'){
		 $ret_info_array = array (
          'result'                      => 'error',
          'message'                     => $response['result'],
          'host_reply_raw'              => "",
          'generated_decred_address'   => false,
          );
        return $ret_info_array;	
		}
	if($response['status']== 'success'){
		$ret_info_array = array (
          'result'                      => 'success',
          'message'                     => "",
          'host_reply_raw'              => "",
          'generated_decred_address'   => $response['result'],
          );
        return $ret_info_array;	
		}
}
//===========================================================================



//===========================================================================
/*
$ret_info_array = array (
  'result'                      => 'success',
  'message'                     => "",
  'host_reply_raw'              => "",
  'balance'                     => false == error, else - balance
  );
*/

function WCDECRED__getreceivedbyaddress_info ($wcdecred_settings=false, $decred_account, $confirmations=1, $api_timeout=10)
{
	if (!$wcdecred_settings){
    $wcdecred_settings = WCDECRED__get_settings();
	}
	$ssl_true = @$wcdecred_settings ['gateway_settings']['decred_rpcssl_active'];
	$rpcuser = @$wcdecred_settings ['gateway_settings']['decred_rpcuser'];
	$rpcpass = @$wcdecred_settings ['gateway_settings']['decred_rpcpass'];
	$rpchost = @$wcdecred_settings ['gateway_settings']['decred_rpchostaddr'];
	$rpcport = @$wcdecred_settings ['gateway_settings']['decred_rpcport'];
	
	if ($decred_account == ''){
		$ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Invalid account",
      'host_reply_raw'              => "",
      'balance'                     => false,
      );
	
	return $ret_info_array;	
	}	
	

	
		$decred =  new Decred($rpcuser,$rpcpass,$rpchost,$rpcport);
	if($ssl_true=='yes'){
        $decred->setSSL(null);
    }
    

    $response = $decred->getreceivedbyaccount($decred_account,(int)$confirmations);  
	if($response['status']== 'error'){
		 $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Decred API failure. Erratic replies/Error: ".$response['result'],
      'host_reply_raw'              => "",
      'balance'                     => false,
      );
		}
	if($response['status']== 'success'){
		 if (is_numeric($response['result']))
  {
    $ret_info_array = array (
      'result'                      => 'success',
      'message'                     => "",
      'host_reply_raw'              => "",
      'balance'                     => $response['result'],
      );
  }
  else
  {
    $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Decred API failure. Erratic replies",
      'host_reply_raw'              => "",
      'balance'                     => false,
      );
  }
		}


  return $ret_info_array;
}

function WCDECRED__getbalancewallet_info ($wcdecred_settings=false, $api_timeout=10)
{
	if (!$wcdecred_settings){
    $wcdecred_settings = WCDECRED__get_settings();
	}
	$ssl_true = @$wcdecred_settings ['gateway_settings']['decred_rpcssl_active'];
	$rpcuser = @$wcdecred_settings ['gateway_settings']['decred_rpcuser'];
	$rpcpass = @$wcdecred_settings ['gateway_settings']['decred_rpcpass'];
	$rpchost = @$wcdecred_settings ['gateway_settings']['decred_rpchostaddr'];
	$rpcport = @$wcdecred_settings ['gateway_settings']['decred_rpcport'];
		
	
	$decred =  new Decred($rpcuser,$rpcpass,$rpchost,$rpcport);
	if($ssl_true=='yes'){
        $decred->setSSL(null);
    }
    
    $response = $decred->getbalance();  
	if($response['status']== 'error'){
		 $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Decred API failure. Erratic replies/Error: ".$response['result'],
      'host_reply_raw'              => "",
      'balance'                     => false,
      );
		}
	if($response['status']== 'success'){
		 if (is_numeric($response['result']))
  {
    $ret_info_array = array (
      'result'                      => 'success',
      'message'                     => "",
      'host_reply_raw'              => "",
      'balance'                     => $response['result'],
      );
  }
  else
  {
    $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Decred API failure. Erratic replies",
      'host_reply_raw'              => "",
      'balance'                     => false,
      );
  }
		}


  return $ret_info_array;
}

function WCDECRED__senttoaddres ($wcdecred_settings=false, $deposit_address, $quantity, $api_timeout=10)
{
	if (!$wcdecred_settings){
    $wcdecred_settings = WCDECRED__get_settings();
	}
	$ssl_true = @$wcdecred_settings ['gateway_settings']['decred_rpcssl_active'];
	$rpcuser = @$wcdecred_settings ['gateway_settings']['decred_rpcuser'];
	$rpcpass = @$wcdecred_settings ['gateway_settings']['decred_rpcpass'];
	$rpchost = @$wcdecred_settings ['gateway_settings']['decred_rpchostaddr'];
	$rpcport = @$wcdecred_settings ['gateway_settings']['decred_rpcport'];
		
	
	$decred =  new Decred($rpcuser,$rpcpass,$rpchost,$rpcport);
	if($ssl_true=='yes'){
        $decred->setSSL(null);
    }
    
    $response = $decred->sendtoaddress($deposit_address,$quantity);  
	if($response['status']== 'error'){
		 $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Error sent Decreds to address: ".$response['result'],
      'host_reply_raw'              => "",
      'txid'                     => false,
      );
		}
	if($response['status']== 'success'){
    $ret_info_array = array (
      'result'                      => 'success',
      'message'                     => "",
      'host_reply_raw'              => "",
      'txid'                     => $response['result'],
      );
  }



  return $ret_info_array;
}
//===========================================================================

//===========================================================================
// Returns:
//    success: number of currency units (dollars, etc...) would take to convert to 1 decred, ex: "15.32476".
//    failure: false
//
// $currency_code, one of: USD, AUD, CAD, CHF, CNY, DKK, EUR, GBP, HKD, JPY, NZD, PLN, RUB, SEK, SGD, THB
// $rate_retrieval_method
//		'getfirst' -- pick first successfully retireved rate
//		'getall'   -- retrieve from all possible exchange rate services and then pick the best rate.
//
// $rate_type:
//    'vwap'    	-- weighted average as per: http://en.wikipedia.org/wiki/VWAP
//    'realtime' 	-- Realtime exchange rate
//    'bestrate'  -- maximize number of decred to get for item priced in currency: == min (avg, vwap, sell)
//                 This is useful to ensure maximum decred gain for stores priced in other currencies.
//                 Note: This is the least favorable exchange rate for the store customer.
// $get_ticker_string - true - ticker string of all exchange types for the given currency.

function WCDECRED__get_exchange_rate_decred ($currency_code, $rate_retrieval_method = 'getfirst', $rate_type = 'vwap', $get_ticker_string=false)
{
   if ($currency_code == 'DCR')
      return "1.00";   // 1:1

	$wcdecred_settings = WCDECRED__get_settings ();

	$current_time  = time();
	$cache_hit     = false;
	$requested_cache_method_type = $rate_retrieval_method . '|' . $rate_type;
	$ticker_string = "<span style='color:darkgreen;'>Current Rates for 1 Decred (in {$currency_code})={{{EXCHANGE_RATE}}}</span>";
	$ticker_string_error = "<span style='color:red;background-color:#FFA'>WARNING: Cannot determine exchange rates (for '$currency_code')! {{{ERROR_MESSAGE}}} Make sure your PHP settings are configured properly and your server can (is allowed to) connect to external WEB services via PHP.</span>";


	$this_currency_info = @$wcdecred_settings['exchange_rates'][$currency_code][$requested_cache_method_type];
	if ($this_currency_info && isset($this_currency_info['time-last-checked']))
	{
	  $delta = $current_time - $this_currency_info['time-last-checked'];
	  if ($delta < (@$wcdecred_settings['cache_exchange_rates_for_minutes'] * 60))
	  {

	     // Exchange rates cache hit
	     // Use cached value as it is still fresh.
			if ($get_ticker_string)
	  		return str_replace('{{{EXCHANGE_RATE}}}', $this_currency_info['exchange_rate'], $ticker_string);
	  	else
	  		return $this_currency_info['exchange_rate'];
	  }
	}
   
   //Decred rate
     $decredrate = WCDECRED__get_decred_rate();
	 if(!$decredrate){
		return false;
      }
	  
	$rates = array();


	// bitcoinaverage covers both - vwap and realtime
	  $rates[] = WCDECRED__get_exchange_rate_from_bitcoinaverage($currency_code, $rate_type, $wcdecred_settings, $decredrate);  // Requested vwap, realtime or bestrate
	if ($rates[0])
	{

		// First call succeeded

		if ($rate_type == 'bestrate')
			$rates[] = WCDECRED__get_exchange_rate_from_bitpay ($currency_code, $rate_type, $wcdecred_settings, $decredrate);		   // Requested bestrate

		$rates = array_filter ($rates);

		if (count($rates) && $rates[0])
		{
			$exchange_rate = min($rates);
  		// Save new currency exchange rate info in cache
 			WCDECRED__update_exchange_rate_cache ($currency_code, $requested_cache_method_type, $exchange_rate);
 		}
 		else
 			$exchange_rate = false;
 	}
 	else
 	{

 		// First call failed
		if ($rate_type == 'vwap')
 			$rates[] = WCDECRED__get_exchange_rate_from_bitcoincharts ($currency_code, $rate_type, $wcdecred_settings, $decredrate);
 		else

			$rates[] = WCDECRED__get_exchange_rate_from_bitpay ($currency_code, $rate_type, $wcdecred_settings, $decredrate);
			
		//$rates = array_filter ($rates);
		if (count($rates) && $rates[0])
		{
			$exchange_rate = min($rates);
  		// Save new currency exchange rate info in cache
 			WCDECRED__update_exchange_rate_cache ($currency_code, $requested_cache_method_type, $exchange_rate);
 		}
 		else
 			$exchange_rate = false;

 	}


	if ($get_ticker_string)
	{
		if ($exchange_rate)
			return str_replace('{{{EXCHANGE_RATE}}}', $exchange_rate , $ticker_string);
		else
		{
			$extra_error_message = "";
			$fns = array ('file_get_contents', 'curl_init', 'curl_setopt', 'curl_setopt_array', 'curl_exec');
			$fns = array_filter ($fns, 'WCDECRED__function_not_exists');

			if (count($fns))
				$extra_error_message = "The following PHP functions are disabled on your server: " . implode (", ", $fns) . ".";

			return str_replace('{{{ERROR_MESSAGE}}}', $extra_error_message, $ticker_string_error);
		}
	}
	else
		return $exchange_rate;

}
//===========================================================================

//===========================================================================
function WCDECRED__function_not_exists ($fname) { return !function_exists($fname); }
//===========================================================================

//===========================================================================
function WCDECRED__update_exchange_rate_cache ($currency_code, $requested_cache_method_type, $exchange_rate)
{
  // Save new currency exchange rate info in cache
  $wcdecred_settings = WCDECRED__get_settings ();   // Re-get settings in case other piece updated something while we were pulling exchange rate API's...
  $wcdecred_settings['exchange_rates'][$currency_code][$requested_cache_method_type]['time-last-checked'] = time();
  $wcdecred_settings['exchange_rates'][$currency_code][$requested_cache_method_type]['exchange_rate'] = $exchange_rate;
  WCDECRED__update_settings ($wcdecred_settings);

}
//===========================================================================

//===========================================================================
function WCDECRED__get_decred_rate($wcdecred_settings=false, $source_url = 'https://bittrex.com/api/v1.1/public/getmarketsummary?market=btc-dcr',$rate='L')
{
		if (!$wcdecred_settings){
    $wcdecred_settings = WCDECRED__get_settings();
	}
	
//$source_urls = @$wcdecred_settings ['gateway_settings']['decred_exchnage'];
$source_url = 'https://bittrex.com/api/v1.1/public/getmarketsummary?market=btc-dcr';
 $src = file_get_contents($source_url);
 $obj = json_decode($src);
// if($obj->Success===true){
	 if($rate=='L'){
    return $btcdecred = $obj->result[0]->Bid;
	}
	else if($rate=='A'){
     return $btcdecred = $obj->result[0]->Last;
	}
 //}else{
	// return false;
//	} 

}
//===========================================================================


//===========================================================================
// $rate_type: 'vwap' | 'realtime' | 'bestrate'
function WCDECRED__get_exchange_rate_from_bitcoinaverage ($currency_code, $rate_type, $wcdecred_settings, $decred_rate)
{
	$source_url	=	"https://apiv2.bitcoinaverage.com/indices/global/ticker/BTC{$currency_code}";
	$result = @WCDECRED__get_contents  ($source_url, false, $wcdecred_settings['exchange_rate_api_timeout_secs']);

	$rate_obj = @json_decode(trim($result), true);
	if (!is_array($rate_obj))
		return false;




    
    
	if (@$rate_obj['averages']['day'])
		$rate_24h_avg = @$rate_obj['averages']['day'];
	else if (@$rate_obj['last'] && @$rate_obj['ask'] && @$rate_obj['bid'])
		$rate_24h_avg = ($rate_obj['last'] + $rate_obj['ask'] + $rate_obj['bid']) / 3;
	else
		$rate_24h_avg = @$rate_obj['last'];

	switch ($rate_type)
	{
		case 'vwap'	:				return $rate_24h_avg * $decred_rate;
		case 'realtime'	:		return @$rate_obj['last'] * $decred_rate;
		case 'bestrate'	:
		default:						return min ($rate_24h_avg * $decred_rate, @$rate_obj['last'] * $decred_rate);
	}
}
//===========================================================================

//===========================================================================
// $rate_type: 'vwap' | 'realtime' | 'bestrate'
function WCDECRED__get_exchange_rate_from_bitcoincharts ($currency_code, $rate_type, $wcdecred_settings, $decred_rate)
{
	$source_url	=	"http://api.bitcoincharts.com/v1/weighted_prices.json";
	$result = @WCDECRED__get_contents  ($source_url, false, $wcdecred_settings['exchange_rate_api_timeout_secs']);

	$rate_obj = @json_decode(trim($result), true);


	// Only vwap rate is available
	return @$rate_obj[$currency_code]['24h'] * $decred_rate;
}
//===========================================================================

//===========================================================================
// $rate_type: 'vwap' | 'realtime' | 'bestrate'
function WCDECRED__get_exchange_rate_from_bitpay ($currency_code, $rate_type, $wcdecred_settings, $decred_rate)
{
	$source_url	=	"https://bitpay.com/api/rates";
	$result = @WCDECRED__get_contents  ($source_url, false, $wcdecred_settings['exchange_rate_api_timeout_secs']);

	$rate_objs = @json_decode(trim($result), true);
	if (!is_array($rate_objs))
		return false;

	foreach ($rate_objs as $rate_obj)
	{
		if (@$rate_obj['code'] == $currency_code)
		{


			return @$rate_obj['rate'] * $decred_rate;	// Only realtime rate is available
		}
	}


	return false;
}
//===========================================================================
//===========================================================================
// Credits: http://www.php.net/manual/en/function.mysql-real-escape-string.php#100854
function WCDECRED__safe_string_escape ($str="")
{
   $len=strlen($str);
   $escapeCount=0;
   $targetString='';
   for ($offset=0; $offset<$len; $offset++)
   {
     switch($c=$str{$offset})
     {
         case "'":
         // Escapes this quote only if its not preceded by an unescaped backslash
                 if($escapeCount % 2 == 0) $targetString.="\\";
                 $escapeCount=0;
                 $targetString.=$c;
                 break;
         case '"':
         // Escapes this quote only if its not preceded by an unescaped backslash
                 if($escapeCount % 2 == 0) $targetString.="\\";
                 $escapeCount=0;
                 $targetString.=$c;
                 break;
         case '\\':
                 $escapeCount++;
                 $targetString.=$c;
                 break;
         default:
                 $escapeCount=0;
                 $targetString.=$c;
     }
   }
   return $targetString;
}
//===========================================================================

//===========================================================================
// Syntax:
//    WCDECRED__log_event (__FILE__, __LINE__, "Hi!");
//    WCDECRED__log_event (__FILE__, __LINE__, "Hi!", "/..");
//    WCDECRED__log_event (__FILE__, __LINE__, "Hi!", "", "another_log.php");
function WCDECRED__log_event ($filename, $linenum, $message, $prepend_path="", $log_file_name='__log.php')
{
   $log_filename   = dirname(__FILE__) . $prepend_path . '/' . $log_file_name;
   $logfile_header = "<?php exit(':-)'); ?>\n" . '/* =============== DecredGTW LOG file =============== */' . "\r\n";
   $logfile_tail   = "\r\nEND";

   // Delete too long logfiles.
   //if (@file_exists ($log_filename) && filesize($log_filename)>1000000)
   //   unlink ($log_filename);

   $filename = basename ($filename);

   if (@file_exists ($log_filename))
      {
      // 'r+' non destructive R/W mode.
      $fhandle = @fopen ($log_filename, 'r+');
      if ($fhandle)
         @fseek ($fhandle, -strlen($logfile_tail), SEEK_END);
      }
   else
      {
      $fhandle = @fopen ($log_filename, 'w');
      if ($fhandle)
         @fwrite ($fhandle, $logfile_header);
      }

   if ($fhandle)
      {
      @fwrite ($fhandle, "\r\n// " . $_SERVER['REMOTE_ADDR'] . '(' . $_SERVER['REMOTE_PORT'] . ')' . ' -> ' . date("Y-m-d, G:i:s T") . "|" . WCDECRED_VERSION . "/" . WCDECRED_EDITION . "|$filename($linenum)|: " . $message . $logfile_tail);
      @fclose ($fhandle);
      }
}
//===========================================================================

//===========================================================================

//===========================================================================

//===========================================================================
function WCDECRED__send_email ($email_to, $email_from, $subject, $plain_body)
{
   $message = "
   <html>
   <head>
   <title>$subject</title>
   </head>
   <body>" . $plain_body . "
   </body>
   </html>
   ";

   // To send HTML mail, the Content-type header must be set
   $headers  = 'MIME-Version: 1.0' . "\r\n";
   $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

   // Additional headers
   $headers .= "From: " . $email_from . "\r\n";    //"From: Birthday Reminder <birthday@example.com>" . "\r\n";

   // Mail it
   $ret_code = @mail ($email_to, $subject, $message, $headers);

   return $ret_code;
}
//===========================================================================
//===========================================================================
/*
  Get web page contents with the help of PHP cURL library
   Success => content
   Error   => if ($return_content_on_error == true) $content; else FALSE;
*/
function WCDECRED__get_contents ($url, $return_content_on_error=false, $timeout=60, $user_agent=FALSE)
{
   if (!function_exists('curl_init'))
      {
      return @file_get_contents ($url);
      }

   $ssl_verf=1;
   if ($_SERVER['HTTP_HOST']=='localhost')
    {
      $ssl_verf = 0;
      }

   $options = array(
      CURLOPT_URL            => $url,
      CURLOPT_RETURNTRANSFER => true,     // return web page
      CURLOPT_HEADER         => false,    // don't return headers
      CURLOPT_ENCODING       => "",       // handle compressed
	  CURLOPT_SSL_VERIFYPEER => $ssl_verf,
      CURLOPT_USERAGENT      => $user_agent?$user_agent:urlencode("Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.576.0 Safari/534.12"), // who am i

      CURLOPT_AUTOREFERER    => true,     // set referer on redirect
      CURLOPT_CONNECTTIMEOUT => $timeout,       // timeout on connect
      CURLOPT_TIMEOUT        => $timeout,       // timeout on response in seconds.
      CURLOPT_FOLLOWLOCATION => true,     // follow redirects
      CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
      );

   $ch      = curl_init   ();

   if (function_exists('curl_setopt_array'))
      {
      curl_setopt_array      ($ch, $options);
      }
   else
      {
      // To accomodate older PHP 5.0.x systems
      curl_setopt ($ch, CURLOPT_URL            , $url);
      curl_setopt ($ch, CURLOPT_RETURNTRANSFER , true);     // return web page
      curl_setopt ($ch, CURLOPT_HEADER         , false);    // don't return headers
      curl_setopt ($ch, CURLOPT_ENCODING       , "");       // handle compressed
	  curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER , $ssl_verf);
      curl_setopt ($ch, CURLOPT_USERAGENT      , $user_agent?$user_agent:urlencode("Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.576.0 Safari/534.12")); // who am i
      curl_setopt ($ch, CURLOPT_AUTOREFERER    , true);     // set referer on redirect
      curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT , $timeout);       // timeout on connect
      curl_setopt ($ch, CURLOPT_TIMEOUT        , $timeout);       // timeout on response in seconds.
      curl_setopt ($ch, CURLOPT_FOLLOWLOCATION , true);     // follow redirects
      curl_setopt ($ch, CURLOPT_MAXREDIRS      , 10);       // stop after 10 redirects
      }

   $content = curl_exec   ($ch);
   $err     = curl_errno  ($ch);
   $header  = curl_getinfo($ch);
   // $errmsg  = curl_error  ($ch);

   curl_close             ($ch);

   if (!$err && $header['http_code']==200)
      return trim($content);
   else
   {
      if ($return_content_on_error)
         return trim($content);
      else
         return FALSE;
   }
      
}

//===========================================================================
// Bittrex API
//    
/////// Get deposit address by currency   
 function WCDECRED__bittrex_getdeposit_address ($apikey, $currency="DCR")
{  
  $url_api = "https://bittrex.com/api/v1/account/getdepositaddress?apikey=".$apikey."&currency=".$currency;
    $src = file_get_contents($url_api);
    $obj = json_decode($src);
     if($obj->success===true){
		 
		 $ret_info_array = array (
         'result'                      => 'success',
         'message'                     => "",
         'deposit_address'   => $obj->result->Address,
         );
		 
      return $ret_info_array;
    }else{
		$ret_info_array = array (
         'result'                      => 'error',
         'message'                     => $obj->message,
         'deposit_address'   => "",
         );
	    return $ret_info_array;
	} 
   
  } 
//////Sell Market///////////////////////
 function WCDECRED__bittrex_sellmarket ($apikey, $quantity, $market="btc-dcr")
{  
$decredrate = WCDECRED__get_decred_rate('https://bittrex.com/api/v1.1/public/getmarketsummary?market=btc-dcr','A');
	 if($decredrate){
      
  $url_api = "https://bittrex.com/api/v1/market/selllimit?apikey=".$apikey."&market=".$market."&quantity=".$quantity."&rate=".$decredrate;
    $src = file_get_contents($url_api);
    $obj = json_decode($src);
     if($obj->success===true){
		 
		 $ret_info_array = array (
         'result'                      => 'success',
         'message'                     => "",
         'resultUuid'   => $obj->result->resultUuid,
         );
		 
      return $ret_info_array;
    }else{
		$ret_info_array = array (
         'result'                      => 'error',
         'message'                     => $obj->message,
         'resultUuid'   => "",
         );
	    return $ret_info_array;
	} 
  }else{
		$ret_info_array = array (
         'result'                      => 'error',
         'message'                     => "Error Getting rates for Decred".$decredrate,
         'resultUuid'   => "",
         );
	    return $ret_info_array;
	} 
  } 
// Get balance Available  
 function WCDECRED__bittrex_getbalance($apikey, $currency="DCR")
{  
  $url_api = "https://bittrex.com/api/v1/account/getbalance?apikey=".$apikey."&currency=".$currency;
    $src = file_get_contents($url_api);
    $obj = json_decode($src);
     if($obj->success===true){
		 
		 $ret_info_array = array (
         'result'                      => 'success',
         'message'                     => "",
         'balance_av'   => $obj->result->Available,
         );
		 
      return $ret_info_array;
    }else{
		$ret_info_array = array (
         'result'                      => 'error',
         'message'                     => $obj->message,
         'balance_av'   => "",
         );
	    return $ret_info_array;
	} 
   
  }
//===========================================================================
