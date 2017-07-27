<?php
/*
Plugin Name: Decred - WooCommerce Gateway Payment
Plugin URI: http://decred.org
Description: Extends WooCommerce by adding the Decred Gateway
Version: 1.0
Author: SerHack
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}
add_action( 'plugins_loaded', 'decred_init', 0 );
function decred_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// Include our gateway
	require_once('gateway.php');
	// Lets add it too WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'decred_gateway' );
	function decred_gateway( $methods ) {
		$methods[] = 'decred_gateway';
		return $methods;
	}
	
	
}
}
