<?php
/*
Plugin Name: Marketplace Stripe Gateway
Plugin URI: http://dualcube.com
Description: Stripe Payment Gateway ( WooCommerce MarketPlace Compatible )
Author: Dualcube
Version: 1.0.1
Author URI: http://dualcube.com
*/

if ( ! class_exists( 'WCMp_Dependencies_Stripe_Gateway' ) ) {
	require_once trailingslashit(dirname(__FILE__)).'includes/class-wcmp-stripe-dependencies.php';
}
require_once trailingslashit(dirname(__FILE__)).'includes/wcmp-stripe-gateway-core-functions.php';
require_once trailingslashit(dirname(__FILE__)).'wcmp_stripe_config.php';
if(!defined('ABSPATH')) exit; // Exit if accessed directly
if(!defined('WCMp_STRIPE_GATEWAY_PLUGIN_TOKEN')) exit;
if(!defined('WCMp_STRIPE_GATEWAY_TEXT_DOMAIN')) exit;

if(!WCMp_Dependencies_Stripe_Gateway::woocommerce_plugin_active_check()) {
	add_action( 'admin_notices', 'woocommerce_inactive_notice_stripe' );
	$woocommerce_not_found_for_stripe = "not_found";
}

if(!isset($woocommerce_not_found_for_stripe) ) {
	if(!class_exists('WCMp_Stripe_Gateway')) {
		require_once( trailingslashit(dirname(__FILE__)).'classes/class-wcmp-stripe-gateway.php' );
		global $WCMp_Stripe_Gateway;
		$WCMp_Stripe_Gateway = new WCMp_Stripe_Gateway( __FILE__ );
		$GLOBALS['WCMp_Stripe_Gateway'] = $WCMp_Stripe_Gateway;
		// Activation Hooks
		register_activation_hook( __FILE__, array($WCMp_Stripe_Gateway, 'activate_wcmp_stripe_gateway') );
		register_activation_hook( __FILE__, 'flush_rewrite_rules' );
		// Deactivation Hooks
		register_deactivation_hook( __FILE__, array($WCMp_Stripe_Gateway, 'deactivate_wcmp_stripe_gateway') );
	}
}
?>
