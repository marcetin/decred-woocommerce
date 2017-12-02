<?php
/*
	
	
Decred Payments Gateway for WooCommerce
https://marcetin.com/
*/

//---------------------------------------------------------------------------
add_action('plugins_loaded', 'WCDCR_plg_load_decred_gtway', 0);
//---------------------------------------------------------------------------

//###########################################################################
// Hook payment gateway into WooCommerce

function WCDCR_plg_load_decred_gtway ()
{
    if (!class_exists('WC_Payment_Gateway'))
    	// Nothing happens here is WooCommerce is not loaded
    	return;

	//=======================================================================
	/**
	 * Decred Payment Gateway
	 * Provides a Decred Payment Gateway
	 * @class 		WCDECRED_Gateway
	 * @extends		WC_Payment_Gateway
	 * @version 	0.3
	 * @author 		marcetin
	 */
	class WCDECRED_Gateway extends WC_Payment_Gateway
	{
		//-------------------------------------------------------------------
	     /* Constructor for the gateway.
		  * Public
		  * Return true 
	     */
		public function __construct(){
      $this->id				= 'decred';
      $this->icon 			= plugins_url('/images/decred_buyitnow_32x.svg', __FILE__);	// 32 pixels high
      $this->has_fields 		= false;
      $this->method_title     = __( 'Decred', 'woocommerce' );

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->title = $this->settings['title'];	// The title which the user is shown on the checkout – retrieved from the settings which init_settings loads.
			$this->decred_rpchostaddr = $this->settings['decred_rpchostaddr'];
			$this->decred_rpcport = $this->settings['decred_rpcport'];			
			//$this->decred_rpccert = $this->settings['decred_rpccert'];
			//$this->decred_rpcssl = $this->settings['decred_rpcssl'];
			$this->decred_rpcssl_load = $this->settings['decred_rpcssl_load'];
			$this->decred_bittrex = $this->settings['decred_bittrex'];
			$this->decred_apikey_bittrex = $this->settings['decred_apikey_bittrex'];
			$this->decred_amount_bittrex = $this->settings['decred_amount_bittrex'];
			$this->decred_apisecret_bittrex = $this->settings['decred_apisecret_bittrex'];
			$this->decred_rpcuser = $this->settings['decred_rpcuser'];//$this->settings['decred_rpcuser'];			
			$this->decred_rpcpass = $this->settings['decred_rpcpass'];
			$this->decred_addprefix = $this->settings['decred_addprefix'];
			$this->decred_confirmations = $this->settings['decred_confirmations'];
			$this->exchange_rate_type = $this->settings['exchange_rate_type'];
			$this->exchange_multiplier = $this->settings['exchange_multiplier'];
			$this->description 	= $this->settings['description'];	// Short description about the gateway which is shown on checkout.
			$this->instructions = $this->settings['instructions'];	// Detailed payment instructions for the buyer.
			$this->instructions_multi_payment_str  = __('You may send payments from multiple accounts to reach the total required.', 'woocommerce');
			$this->instructions_single_payment_str = __('You must pay in a single payment in full.', 'woocommerce');
            
            //set SSL active/not active
		/*	if($this->decred_rpcssl=='yes'){
				$this->settings['decred_rpcssl_active'] = 'yes';
			}else{
				$this->settings['decred_rpcssl_active'] = 'no';
				}
		*/		
			if($this->decred_bittrex=='yes'){
				$this->settings['decred_bittrex_active'] = 'yes';
			}else{
				$this->settings['decred_bittrex_active'] = 'no';
				}
			// Load the form fields.
			$this->init_form_fields();

			// Actions
      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
      else
				add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options')); // hook into this action to save options in the backend

	    add_action('woocommerce_thankyou_' . $this->id, array(&$this, 'WCDECRED__thankyou_page')); // hooks into the thank you page after payment

	    	// Customer Emails
	    add_action('woocommerce_email_before_order_table', array(&$this, 'WCDECRED_instructions__email'), 10, 2); // hooks into the email template to show additional details

			// Hook IPN callback logic
			if (version_compare (WOOCOMMERCE_VERSION, '2.0', '<'))
				add_action('init', array(&$this, 'WCDECRED__maybe_decred_ipn_callback'));
			else
				add_action('woocommerce_api_' . strtolower(get_class($this)), array($this,'WCDECRED__maybe_decred_ipn_callback'));

			// Validate currently set currency for the store. Must be among supported ones.
			if (!$this->WCDECRED__is_gtway_valid__use()) $this->enabled = false;
	    }
		//-------------------------------------------------------------------
		///////////////////////////////////////////////////////////////////////////////////
	    /**
	     * Check if this gateway is enabled and available for the store's default currency
	     * Public
	     * Return bool
	     */
	    function WCDECRED__is_gtway_valid__use(&$ret_reason_message=NULL)
	    {
	    	$valid = true;

	    	//----------------------------------
	    	// Validate settings
	    	if (!$this->decred_rpchostaddr)
	    	{
	    		$reason_message = __("Your Decred RPC host address is not specified", 'woocommerce');
		    		$valid = false;
	    	}
			else if (!$this->decred_rpcport)
	    	{
	    		$reason_message = __("Your Decred RPC Port is not specified", 'woocommerce');
		    		$valid = false;
	    	}
			else if (!$this->decred_rpcuser)
	    	{
	    		$reason_message = __("Your Decred RPC Username is not specified", 'woocommerce');
		    		$valid = false;
	    	}
			else if (!$this->decred_rpcpass)
	    	{
	    		$reason_message = __("Your Decred RPC Password is not specified", 'woocommerce');
		    		$valid = false;
	    	}
			/*
			if ($this->decred_rpcssl=='yes')
	    	{  if ($this->decred_rpcssl_load=='')
	    	    {
	    		$reason_message = __($this->decred_rpcssl."Your Decred RPC Cert Path is not specified".$this->decred_rpcssl_load, 'woocommerce');
		    		$valid = false;
	    	    }
			}
			*/
			if ($this->decred_bittrex=='yes')
	    	{  if ($this->decred_apikey_bittrex=='')
	    	    {
	    		$reason_message = __($this->decred_apikey_bittrex."Your API Key is not specified".$this->decred_apikey_bittrex, 'woocommerce');
		    		$valid = false;
	    	    }
				
				if ($this->decred_amount_bittrex=='')
	    	    {
	    		$reason_message = __($this->decred_amount_bittrex."Amount to verify for order is not specified".$this->decred_amount_bittrex, 'woocommerce');
		    		$valid = false;
	    	    }
				
				if ($this->decred_apisecret_bittrex=='')
	    	    {
	    		$reason_message = __($this->decred_apisecret_bittrex."Your API Secret is not specified".$this->decred_apisecret_bittrex, 'woocommerce');
		    		$valid = false;
	    	    }
				
				
			}
	   
	    	if (!$valid)
	    	{
	    		if ($ret_reason_message !== NULL)
	    			$ret_reason_message = $reason_message;
	    		return false;
	    	}
	    	//----------------------------------

	    	//----------------------------------
	    	// Validate connection to exchange rate services

	   		$store_currency_code = get_woocommerce_currency();
	   		if ($store_currency_code != 'DCR')
	   		{
					$currency_rate = WCDECRED__get_exchange_rate_decred ($store_currency_code, 'getfirst', 'bestrate', false);
					if (!$currency_rate)
					{
						$valid = false;
						
						// error message.
						$error_msg = "ERROR: Cannot determine exchange rates (for '$store_currency_code')! {{{ERROR_MESSAGE}}} Make sure your PHP settings are configured properly and your server can (is allowed to) connect to external WEB services via PHP.";
						$extra_error_message = "";
						$fns = array ('file_get_contents', 'curl_init', 'curl_setopt', 'curl_setopt_array', 'curl_exec');
						$fns = array_filter ($fns, 'WCDECRED__function_not_exists');
						$extra_error_message = "";
						if (count($fns))
							$extra_error_message = "The following PHP functions are disabled on your server: " . implode (", ", $fns) . ".";

						$reason_message = str_replace('{{{ERROR_MESSAGE}}}', $extra_error_message, $error_msg);

		    		if ($ret_reason_message !== NULL)
		    			$ret_reason_message = $reason_message;
		    		return false;
					}
				}

	     	return true;
	    	//----------------------------------
	    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Initial Gateway Settings Form Fields
	     * Public
	     * Return void
	     */
	    function init_form_fields()
	    {
		    // This defines the settings we want to show in the admin area.
	    	//-----------------------------------
	    	// Assemble currency ticker.
	   		$store_currency_code = get_woocommerce_currency();
	   		if ($store_currency_code == 'DCR')
	   			$currency_code = 'USD';
	   		else
	   			$currency_code = $store_currency_code;

				$currency_ticker = WCDECRED__get_exchange_rate_decred ($currency_code, 'getfirst', 'bestrate', true);
	    	//-----------------------------------

	    	//-----------------------------------
	    	// Payment instructions
	    	$payment_instructions = '
<div id="decred_intr_pay" style="border:1px solid #e0e0e0">

<table class="wcdecred-payment-instructions-table" id="wcdecred-payment-instructions-table">
  <tr class="bpit-table-row">
    <td colspan="2">Please send your decred payment as follows:</td>
  </tr>
  <tr class="bpit-table-row">
    <td style="border:1px solid #FCCA09;vertical-align:middle" class="bpit-td-name bpit-td-name-amount">
      Amount (<strong>DECRED</strong>):
    </td>
    <td class="bpit-td-value bpit-td-value-amount">
      <div style="border:1px solid #FCCA09;padding:2px 6px;margin:2px;color:#CC0000;font-weight: bold;font-size: 120%">
      	{{{DECRED_AMOUNT}}}
      </div>
    </td>
  </tr>
  <tr class="bpit-table-row">
    <td style="border:1px solid #FCCA09;vertical-align:middle" class="bpit-td-name bpit-td-name-amount_currency">
      Total Amount with tax in(<strong>{{{CURRENCY}}}</strong>):
    </td>
    <td class="bpit-td-value bpit-td-value-amount">
      <div style="border:1px solid #FCCA09;padding:2px 6px;margin:2px;color:#CC0000;font-weight: bold;font-size: 120%">
      	{{{CURRENCY_AMOUNT}}}
      </div>
    </td>
  </tr>
  <tr class="bpit-table-row">
    <td style="border:1px solid #FCCA09;vertical-align:middle" class="bpit-td-name bpit-td-name-decredaddr">
      Address:
    </td>
    <td ID="copytext" class="bpit-td-value bpit-td-value-decredaddr">
      <div style="border:1px solid #FCCA09;padding:2px 6px;margin:2px;color:#555;font-weight: bold;font-size: 120%">
        {{{DECRED_ADDRESS}}}
      </div>
<TEXTAREA ID="holdtext" STYLE="display:none;">
</TEXTAREA>



    </td>
  </tr>
  
</table>

Please note:
<ol class="bpit-instructions">
    <li>You must make a payment within 2 hour, or your order will be cancelled</li>
    <li>As soon as your payment is received in full you will receive email confirmation with order delivery details.</li>
    <li>{{{EXTRA_INSTRUCTIONS}}}</li>
</ol>
</div>
';

/*<tr class="bpit-table-row">
    <td style="border:1px solid #FCCA09;vertical-align:middle" class="bpit-td-name bpit-td-name-qr">
	    QR Code:
    </td>
    <td class="bpit-td-value bpit-td-value-qr">
      <div style="border:1px solid #FCCA09;padding:5px;margin:2px;">
        <a href="//{{{DECRED_ADDRESS}}}?amount={{{DECRED_AMOUNT}}}"><img src="https://blockchain.info/qr?data=decred://{{{DECRED_ADDRESS}}}?amount={{{DECRED_AMOUNT}}}&amp;size=180" style="vertical-align:middle;border:1px solid #888" /></a>
      </div>
    </td>
  </tr>*/



				$payment_instructions = trim ($payment_instructions);

	    	$payment_instructions_description = '
						  <p class="description" style="width:50%;float:left;width:49%;">
					    	' . __( 'Specific instructions given to the customer to complete Decreds payment.<br />You may change it, but make sure these tags will be present: <b>{{{DECRED_AMOUNT}}}</b>, <b>{{{DECRED_ADDRESS}}}</b> and <b>{{{EXTRA_INSTRUCTIONS}}}</b> as these tags will be replaced with customer - specific payment details.', 'woocommerce' ) . '
						  </p>
						  <p class="description" style="width:50%;float:left;width:49%;">
					    	Payment Instructions, original template (for reference):<br />
					    	<textarea rows="2" onclick="this.focus();this.select()" readonly="readonly" style="width:100%;background-color:#f1f1f1;height:4em">' . $payment_instructions . '</textarea>
						  </p>
					';
				$payment_instructions_description = trim ($payment_instructions_description);
	    	//-----------------------------------


	    	$this->form_fields = array(
				'enabled' => array(
								'title' => __( 'Enable/Disable', 'woocommerce' ),
								'type' => 'checkbox',
								'label' => __( 'Enable Decred Payments', 'woocommerce' ),
								'default' => 'yes'
							),
				'title' => array(
								'title' => __( 'Title', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
								'default' => __( 'Decred Payment', 'woocommerce' )
							),
				'decred_rpchostaddr' => array(
								'title' => __('Decred RPC host address:', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Please enter your Decred RPC host address [Save changes].", 'woocommerce'),
							),
								
				'decred_rpcport' => array(
								'title' => __('Decred RPC Port', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Please enter your Decred RPC Port [Save changes].", 'woocommerce'),
							),		

				'decred_rpcssl' => array(
								'title' => __('Decred RPC SSL connect', 'woocommerce' ),
								'type' => 'checkbox',
								'default' => 'yes',
								'description' => __("Please select if your connect to Decred RPC is with SSL certificate [Save changes].", 'woocommerce'),
							),
							
				'decred_rpcssl_active' => array(
								'title' => '',
								'type' => 'hidden',
								'default' => 'no',
								'description' => '',
							),
								
				'decred_rpcssl_load' => array(
								'title' => __('Decred RPC SSL connect', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Path certificate server. Example: /siteroot/server.cert", 'woocommerce'),
							),		
							
				'decred_rpcuser' => array(
								'title' => __('Decred RPC username', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Please enter your Decred RPC username [Save changes].<br />", 'woocommerce'),
							),
				'decred_rpcpass' => array(
								'title' => __('Decred RPC password', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Please enter your Decred RPC password [Save changes].<br />", 'woocommerce'),
							),
				'decred_addprefix' => array(
								'title' => __('Decred address Prefix', 'woocommerce' ),
								'type' => 'text',
								'default' => 'decred',
								'description' => __('The prefix for the address labels.<span class="help">The account will be in the form</span>', 'woocommerce'),
							),
							
				'decred_confirmations' => array(
								'title' => __('Number of confirmations required before accepting payment', 'woocommerce' ),
								'type' => 'text',
								'default' => '6',
								'description' => __('', 'woocommerce'),
							),
							
				'decred_bittrex' => array(
								'title' => __('Autoselling on Bittrex', 'woocommerce' ),
								'type' => 'checkbox',
								'default' => 'yes',
								'description' => __("Please select if you would create automatic sell order on bittrex.", 'woocommerce'),
							),
							
				'decred_apikey_bittrex' => array(
								'title' => __('API Key Bittrex', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Please enter your API Key for bittrex.", 'woocommerce'),
							),
				'decred_apisecret_bittrex' => array(
								'title' => __('API Secret Bittrex', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Please enter your API Secret for bittrex.", 'woocommerce'),
							),					
				'decred_amount_bittrex' => array(
								'title' => __('Amount to verify for order (DECRED) ', 'woocommerce' ),
								'type' => 'text',
								'default' => '10',
								'description' => __("Please enter amount to verify.", 'woocommerce'),
							),		
							
				'decred_bittrex_active' => array(
								'title' => '',
								'type' => 'hidden',
								'default' => 'no',
								'description' => '',
							),

				'exchange_rate_type' => array(
								'title' => __('Exchange rate calculation type', 'woocommerce' ),
								'type' => 'select',
								'disabled' => $store_currency_code=='DCR'?true:false,
								'options' => array(
									'vwap' => __( 'Weighted Average', 'woocommerce' ),
									'realtime' => __( 'Real time', 'woocommerce' ),
									'bestrate' => __( 'Most profitable', 'woocommerce' ),
									),
								'default' => 'vwap',
								'description' => ($store_currency_code=='DCR'?__('<span style="color:red;"><b>Disabled</b>: Applies only for stores with non-decred default currency.</span><br />', 'woocommerce'):'') .
									__('<b>Weighted Average</b> (recommended): <a href="http://en.wikipedia.org/wiki/Volume-weighted_average_price" target="_blank">weighted average</a> rates polled from a number of exchange services<br />
										<b>Real time</b>: the most recent transaction rates polled from a number of exchange services.<br />
										<b>Most profitable</b>: pick better exchange rate of all indicators (most favorable for merchant). Calculated as: MIN (Weighted Average, Real time)') . '<br />' . $currency_ticker,
							),
				'exchange_multiplier' => array(
								'title' => __('Exchange rate multiplier', 'woocommerce' ),
								'type' => 'text',
								'disabled' => $store_currency_code=='DCR'?true:false,
								'description' => ($store_currency_code=='DCR'?__('<span style="color:red;"><b>Disabled</b>: Applies only for stores with non-decred default currency.</span><br />', 'woocommerce'):'') .
									__('Extra multiplier to apply to convert store default currency to decred price. <br />Example: <b>1.05</b> - will add extra 5% to the total price in decred. May be useful to compensate merchant\'s loss to fees when converting decred to local currency, or to encourage customer to use decred for purchases (by setting multiplier to < 1.00 values).', 'woocommerce' ),
								'default' => '1.00',
							),
				'description' => array(
								'title' => __( 'Customer Message', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'Initial instructions for the customer at checkout screen', 'woocommerce' ),
								'default' => __( 'Please proceed to the next screen to see necessary payment details.', 'woocommerce' )
							),
				'instructions' => array(
								'title' => __( 'Payment Instructions (HTML)', 'woocommerce' ),
								'type' => 'textarea',
								'description' => $payment_instructions_description,
								'default' => $payment_instructions,
							),
				);
	    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
		/**
		 * Admin Panel Options
		 * Public
		 * Return void
		 */
		public function admin_options()
		{
			$validation_msg = "";
			$store_valid    = $this->WCDECRED__is_gtway_valid__use ($validation_msg);

			// After defining the options, we need to display them too; thats where this next function comes into play:
	    	?>
	    	<h3><?php _e('Decred Payment', 'woocommerce'); ?></h3>
	    	<p>
	    		<?php _e('<p style="border:1px solid #890e4e;padding:5px 10px;color:#004400;background-color:#FFF;"><u>Please donate DECRED to</u>:&nbsp;&nbsp;<span style="color:#d21577;font-size:110%;font-weight:bold;">D8UWWqaUtZ8TtWkHvJtbF3jFJqCtMdAdKE</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size:95%;">(All supporters will be in marcetin.com)</span></p>
	    			',
	    				'woocommerce'); ?>
	    	</p>
	    	<?php
	    		echo $store_valid ? ('<p style="border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#004400;background-color:#CCFFCC;">' . __('Decred payment gateway is operational','woocommerce') . '</p>') : ('<p style="border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#EE0000;background-color:#FFFFAA;">' . __('Decred payment gateway is not operational: ','woocommerce') . $validation_msg . '</p>');
	    	?>
	    	<table class="form-table">
	    	<?php
	    		// Generate the HTML For the settings form.
	    		$this->generate_settings_html();
	    	?>
			</table><!--/.form-table-->
	    	<?php
	    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	  // Hook into admin options saving.
    public function process_admin_options()
    {
    	// Call parent
    	parent::process_admin_options();

    	if (isset($_POST) && is_array($_POST))
    	{
	  		$wcdecred_settings = WCDECRED__get_settings ();
	  		if (!isset($wcdecred_settings['gateway_settings']) || !is_array($wcdecred_settings['gateway_settings']))
	  			$wcdecred_settings['gateway_settings'] = array();

	    	$prefix        = 'woocommerce_decred_';
	    	$prefix_length = strlen($prefix);

	    	foreach ($_POST as $varname => $varvalue)
	    	{

	    		if (strpos($varname, 'woocommerce_decred_') === 0)
	    		{
	    			$trimmed_varname = substr($varname, $prefix_length);
	    			if ($trimmed_varname != 'description' && $trimmed_varname != 'instructions'){
					  if ($trimmed_varname == 'decred_bittrex_active'){
						  
						  if ($_POST['woocommerce_decred_decred_bittrex'] === '1'){
							$wcdecred_settings['gateway_settings'][$trimmed_varname] = 'yes';  
						  }else{
							$wcdecred_settings['gateway_settings'][$trimmed_varname] = 'no';  
						 }
					   }else if ($trimmed_varname == 'decred_rpcssl_active'){
						  if ($_POST['woocommerce_decred_decred_rpcssl'] === '1'){
							$wcdecred_settings['gateway_settings'][$trimmed_varname] = 'yes';  
						  }else{
							$wcdecred_settings['gateway_settings'][$trimmed_varname] = 'no';  
						 }	  
					  }else{
	    				$wcdecred_settings['gateway_settings'][$trimmed_varname] = $varvalue;
	    		   }
				  }
				}
	    	}

	  		// Update gateway settings within GOWC own settings for easier access.
			
	      WCDECRED__update_settings ($wcdecred_settings);
	    }
    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Process the payment and return the result
	     *
	     * @access public
	     * @param int $order_id
	     * @return array
	     */
		function process_payment ($order_id)
		{
			$order = new WC_Order ($order_id);
$order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
			//-----------------------------------
			// Save decred payment info together with the order.
			// Note: this code must be on top here, as other filters will be called from here and will use these values ...
			//
			// Calculate realtime decred price (if exchange is necessary)

			$exchange_rate = WCDECRED__get_exchange_rate_decred (get_woocommerce_currency(), 'getfirst', $this->exchange_rate_type);
			/// $exchange_rate = WCDECRED__get_exchange_rate_decred (get_woocommerce_currency(), $this->exchange_rate_retrieval_method, $this->exchange_rate_type);
			if (!$exchange_rate)
			{
				$msg = 'ERROR: Cannot determine Decred exchange rate. Possible issues: store server does not allow outgoing connections, exchange rate servers are blocking incoming connections or down. ' .
					   'You may avoid that by setting store currency directly to Decred(DECRED)';
      			WCDECRED__log_event (__FILE__, __LINE__, $msg);
      			exit ('<h2 style="color:red;">' . $msg . '</h2>');
			}

			$order_total_in_decred   = ($order->get_total() / $exchange_rate);
			if (get_woocommerce_currency() != 'DCR')
				// Apply exchange rate multiplier only for stores with non-decred default currency.
				$order_total_in_decred = $order_total_in_decred * $this->exchange_multiplier;

			$order_total_in_decred   = sprintf ("%.8f", $order_total_in_decred);

  		$decred_address = false;

  		$order_info =
  			array (
  				'order_id'				=> $order_id,
  				'order_total'			=> $order_total_in_decred,
  				'order_datetime'  => date('Y-m-d H:i:s T'),
  				'requested_by_ip'	=> @$_SERVER['REMOTE_ADDR'],
  				);

  		$ret_info_array = array();
	   			// This function generate decred address from RPC
				$ret_info_array = WCDECRED__get_decred_address_for_payment($order_info);
				$decred_address = @$ret_info_array['generated_decred_address'];

			if (!$decred_address)
			{
				$msg = "ERROR: cannot generate decred address for the order: '" . @$ret_info_array['message'] . "'";
      			WCDECRED__log_event (__FILE__, __LINE__, $msg);
      			exit ('<h2 style="color:red;">' . $msg . '</h2>');
			}

   		WCDECRED__log_event (__FILE__, __LINE__, "     Generated unique decred address: '{$decred_address}' for order_id " . $order_id);

     	update_post_meta (
     		$order->id, 			// post id ($order_id)
     		'order_total_in_decred', 	// meta key
     		$order_total_in_decred 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order->id, 			// post id ($order_id)
     		'decred_address',	// meta key
     		$decred_address 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order->id, 			// post id ($order_id)
     		'decred_paid_total',	// meta key
     		"0" 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order->id, 			// post id ($order_id)
     		'decred_refunded',	// meta key
     		"0" 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order->id, 				// post id ($order_id)
     		'_incoming_payments',	// meta key. Starts with '_' - hidden from UI.
     		array()					// array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
     		);
     	update_post_meta (
     		$order->id, 				// post id ($order_id)
     		'_payment_completed',	// meta key. Starts with '_' - hidden from UI.
     		0					// array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
     		);
		update_post_meta (
     		$order->id, 				// post id ($order_id)
     		'exchange_rate',	// meta key. Starts with '_' - hidden from UI.
     		$exchange_rate					// array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
     		);
			//-----------------------------------


			// The decred gateway does not take payment immediately, but it does need to change the orders status to on-hold
			// (so the store owner knows that decred payment is pending).
			// We also need to tell WooCommerce that it needs to redirect to the thankyou page – this is done with the returned array
			// and the result being a success.
			//
			global $woocommerce;

			//	Updating the order status:

			// Mark as on-hold (we're awaiting for decred payment to arrive)
			$order->update_status('on-hold', __('Awaiting decred payment to arrive', 'woocommerce'));

/*
			///////////////////////////////////////
			// timbowhite's suggestion:
			// -----------------------
			// Mark as pending (we're awaiting for decred payment to arrive), not 'on-hold' since
      // woocommerce does not automatically cancel expired on-hold orders. Woocommerce handles holding the stock
      // for pending orders until order payment is complete.
			$order->update_status('pending', __('Awaiting decred payment to arrive', 'woocommerce'));

			// Me: 'pending' does not trigger "Thank you" page and neither email sending. Not sure why.
			//			Also - I think cancellation of unpaid orders needs to be initiated from cron job, as only we know when order needs to be cancelled,
			//			by scanning "on-hold" orders through '' timeout check.
			///////////////////////////////////////
*/
			// Remove cart
			$woocommerce->cart->empty_cart();

			// Empty awaiting payment session
			unset($_SESSION['order_awaiting_payment']);

			// Return thankyou redirect
			if (version_compare (WOOCOMMERCE_VERSION, '2.1', '<'))
			{
				return array(
					'result' 	=> 'success',
					'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('thanks'))))
				);
			}
			else
			{
				return array(
						'result' 	=> 'success',
						'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, $this->get_return_url( $order )))
					);
			}
		}
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Output for the order received page.
	     * Public
	     * Return void
	     */
		function WCDECRED__thankyou_page($order_id)
		{
			// WCDECRED__thankyou_page is hooked into the "thank you" page and in the simplest case can just echo’s the description.








			// Get order object.
			$order = new WC_Order($order_id);

			// Assemble detailed instructions.
			$order_total_in_decred   = get_post_meta($order->id, 'order_total_in_decred',   true); // set single to true to receive properly unserialized array
			$decred_address = get_post_meta($order->id, 'decred_address', true); // set single to true to receive properly unserialized array
			$exchange_rate  = get_post_meta($order->id, 'exchange_rate',   true); // set exchange rate


			$instructions = $this->instructions;
			$instructions = str_replace ('{{{DECRED_AMOUNT}}}',  $order_total_in_decred, $instructions);
			$instructions = str_replace ('{{{CURRENCY}}}',  get_woocommerce_currency(), $instructions);
			$instructions = str_replace ('{{{CURRENCY_AMOUNT}}}',  $order_total_in_decred * $exchange_rate, $instructions);
			$instructions = str_replace ('{{{DECRED_ADDRESS}}}', $decred_address, 	$instructions);
			$instructions =
				str_replace (
					'{{{EXTRA_INSTRUCTIONS}}}',

					$this->instructions_multi_payment_str,
					$instructions
					);
            $order->add_order_note( __("Order instructions: price=&#3647;{$order_total_in_decred}, incoming account:{$decred_address}", 'woocommerce'));

	        echo wpautop (wptexturize ($instructions));
	        
	        
	        /* GAMES REDIRECION */
/*
	$order = wc_get_order( $order_id );
echo '<div class="cfgameslink">';
		echo 'ENTER YOUR PAYMENT TxID';
				echo '</div>';
				
   echo 'TxID: ';
   echo '<input type="text" id="gname">';
   echo '<div id="gamelinktx">';
	foreach( $order->get_items() as $item ) {
		$_product = wc_get_product( $item['product_id'] );
		// Add whatever product id you want below here
		echo '<div class="cfgameslink">';
		if ( $item['product_id'] == 128 ) {
			// change below to the URL that you want to send your customer to
			echo '<a href="/confront/1-vs-1/recon-arena/" class="btn-large">NEXT</a>';
		}
				if ( $item['product_id'] == 205 ) {
			// change below to the URL that you want to send your customer to
			echo '<a href="/recon-arena-fps-game/" class="btn-large">NEXT</a>';
		}
		echo '</div>';
	}
echo '</div>';
*/
/* GAMES REDIRECION */

		}
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Add content to the WC emails.
	     * Public
	     * Param WC_Order $order
	     * Pparam bool $sent_to_admin
	     * Return void
	     */
		function WCDECRED_instructions__email ($order, $sent_to_admin)
		{
	    	if ($sent_to_admin) return;
	    	if (!in_array($order->status, array('pending', 'on-hold'), true)) return;
	    	if ($order->payment_method !== 'decred') return;

	    	// Assemble payment instructions for email
			$order_total_in_decred   = get_post_meta($order->id, 'order_total_in_decred',   true); // set single to true to receive properly unserialized array
			$decred_address = get_post_meta($order->id, 'decred_address', true); // set single to true to receive properly unserialized array
			$exchange_rate  = get_post_meta($order->id, 'exchange_rate',   true); // set exchange rate


			$instructions = $this->instructions;
			$instructions = str_replace ('{{{DECRED_AMOUNT}}}',  $order_total_in_decred, 	$instructions);
			$instructions = str_replace ('{{{CURRENCY}}}',  get_woocommerce_currency(), $instructions);
			$instructions = str_replace ('{{{CURRENCY_AMOUNT}}}',  $order_total_in_decred * $exchange_rate, $instructions);
			$instructions = str_replace ('{{{DECRED_ADDRESS}}}', $decred_address, 	$instructions);
			$instructions =
				str_replace (
					'{{{EXTRA_INSTRUCTIONS}}}',

					$this->instructions_multi_payment_str,
					$instructions
					);

			echo wpautop (wptexturize ($instructions));
		}
		//-------------------------------------------------------------------

	}
	//=======================================================================


	//-----------------------------------------------------------------------
	// Hook into WooCommerce - add necessary hooks and filters
	add_filter ('woocommerce_payment_gateways', 	'WCDECRED__add_decred_gateway' );

	// Disable unnecessary billing fields.
	/// Note: it affects whole store.
	/// add_filter ('woocommerce_checkout_fields' , 	'WCDECRED__woocommerce_checkout_fields' );

	add_filter ('woocommerce_currencies', 			'WCDECRED__add_decred_currency');
	add_filter ('woocommerce_currency_symbol', 		'WCDECRED__add_decred_currency_symbol', 10, 2);

	// Change [Order] button text on checkout screen.
    /// Note: this will affect all payment methods.
    /// add_filter ('woocommerce_order_button_text', 	'WCDECRED__order_button_text');
	//-----------------------------------------------------------------------

	//=======================================================================
	/**
	 * Add the gateway to WooCommerce
	 *
	 * @access public
	 * @param array $methods
	 * @package
	 * @return array
	 */
	function WCDECRED__add_decred_gateway( $methods )
	{
		$methods[] = 'WCDECRED_Gateway';
		return $methods;
	}
	//=======================================================================

	//=======================================================================
	// Our hooked in function - $fields is passed via the filter!
	function WCDECRED__woocommerce_checkout_fields ($fields)
	{
	     unset($fields['order']['order_comments']);
	     unset($fields['billing']['billing_first_name']);
	     unset($fields['billing']['billing_last_name']);
	     unset($fields['billing']['billing_company']);
	     unset($fields['billing']['billing_address_1']);
	     unset($fields['billing']['billing_address_2']);
	     unset($fields['billing']['billing_city']);
	     unset($fields['billing']['billing_postcode']);
	     unset($fields['billing']['billing_country']);
	     unset($fields['billing']['billing_state']);
	     unset($fields['billing']['billing_phone']);
	     return $fields;
	}
	//=======================================================================

	//=======================================================================
	function WCDECRED__add_decred_currency($currencies)
	{
	     $currencies['DCR'] = __( 'Decred ( DCR)', 'woocommerce' );
	     return $currencies;
	}
	//=======================================================================

	//=======================================================================
	function WCDECRED__add_decred_currency_symbol($currency_symbol, $currency)
	{
		switch( $currency )
		{
			case 'DCR':
				$currency_symbol = ' DCR';
				break;
		}

		return $currency_symbol;
	}
	//=======================================================================

	//=======================================================================
 	function WCDECRED__order_button_text () { return 'Continue'; }
	//=======================================================================
}
//###########################################################################

//===========================================================================
function WCDECRED__process_payment_completed_for_order ($order_id, $decred_paid=false)
{

	if ($decred_paid)
		update_post_meta ($order->id, 'decred_paid_total', $decred_paid);

	// Payment completed
	// Make sure this logic is done only once, in case customer keep sending payments :)
	if (!get_post_meta($order->id, '_payment_completed', true))
	{
		update_post_meta ($order->id, '_payment_completed', '1');

		WCDECRED__log_event (__FILE__, __LINE__, "Success: order '{$order->id}' paid in full. Processing and notifying customer ...");

		// Instantiate order object.
		$order = new WC_Order($order_id);
				
		$order->add_order_note( __('Order paid in full', 'woocommerce') );

	  $order->payment_complete();
	}
}
//===========================================================================

function WCDECRED__process_cancelled_for_order ($order_id)
{

	if (!get_post_meta($order->id, '_order_cancelled', true))
	{
		update_post_meta ($order->id, '_order_cancelled', '1');

		WCDECRED__log_event (__FILE__, __LINE__, "Order Cancell: order '{$order->id}' is cancelled. ");

		// Instantiate order object.
		$order = new WC_Order($order_id);
		$order->add_order_note( __('Order cancelled', 'woocommerce') );

	  $order->cancel_order();
	}
}
//===========================================================================

function WCDECRED_order_cancelled__email ($order)
		{
	    	if (!in_array($order->status, array('pending', 'on-hold'), true)) return;
	    	if ($order->payment_method !== 'decred') return;

	    	// Assemble payment instructions for email
			$order_total_in_decred   = get_post_meta($order->id, 'order_total_in_decred',   true); // set single to true to receive properly unserialized array
			$decred_address = get_post_meta($order->id, 'decred_address', true); // set single to true to receive properly unserialized array


			$instructions = $this->instructions;
			$instructions = str_replace ('{{{DECRED_AMOUNT}}}',  $order_total_in_decred, 	$instructions);
			$instructions = str_replace ('{{{DECRED_ADDRESS}}}', $decred_address, 	$instructions);
			$instructions =
				str_replace (
					'{{{EXTRA_INSTRUCTIONS}}}',

					$this->instructions_multi_payment_str,
					$instructions
					);

			echo wpautop (wptexturize ($instructions));
		}
		//-------------------------------------------------------------------