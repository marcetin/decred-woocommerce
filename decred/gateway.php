<?php

class Decred_Gateway extends WC_Payment_Gateway {

        function __construct(){
            
            $this->id               = "decred";
            $this->has_fields 		= false;
            $this->method_title     = __( 'Decred', 'woocommerce' );
			$this->init_settings();
            
        }
    
        public function admin_options(){
            echo "<h1>Decred Payment Gateway Settings</h1>";
            echo "<table class='form-table'>";
            $this->generate_settings_html();
            echo "</table>";
        }
    
        public function generate_settings_html(){
            $this->form_fields = array(
												'enabled' => array(
																'title' => __('Enable / Disable', 'decred_gateway'),
																'label' => __('Enable this payment gateway', 'decred_gateway'),
																'type' => 'checkbox',
																'default' => 'no'
												),
												
												'title' => array(
																'title' => __('Title', 'decred_gateway'),
																'type' => 'text',
																'desc_tip' => __('Payment title the customer will see during the checkout process.', 'decred_gateway'),
																'default' => __('Decred Gateway', 'decred_gateway')
												),
												'description' => array(
																'title' => __('Description', 'decred_gateway'),
																'type' => 'textarea',
																'desc_tip' => __('Payment description the customer will see during the checkout process.', 'decred_gateway'),
																'default' => __('Pay with Decred .', 'decred_gateway')
																
												),
												'decred_address' => array(
																'title' => __('Decred Address', 'decred_gateway'),
																'type' => 'text',
																'desc_tip' => __('decred Wallet Address', 'decred_gateway')
												)
												
								);

        }
    
        public function process_payment($order_id){
            // Process Payment and redirect to a page with instructions
        }
    
        public function instructions(){
            // Page with instructions
            // Here the plugin will convert $amount from USD, EUR to decred using an api
            // After that plugin will print a box where there will be instructions for sending payments
            // TODO:
            // - QR Code (maybe?)
            // - Verifying payment (how? with api?)
            
        }
}
