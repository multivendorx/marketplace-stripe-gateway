<?php
use Stripe\Stripe;
use Stripe\Transfer;
use Stripe\Account;
class WCMp_Stripe_Gateway_Frontend {
	public function __construct() {
		//enqueue scripts
		add_action('wp_enqueue_scripts', array(&$this, 'frontend_scripts'));
		//enqueue styles
		add_action('wp_enqueue_scripts', array(&$this, 'frontend_styles'));

		add_action( 'wcmp_stripe_gateway_frontend_hook', array(&$this, 'wcmp_stripe_gateway_frontend_function'), 10, 2 );
	}

	function frontend_scripts() {
		global $WCMp_Stripe_Gateway;
		$frontend_script_path = $WCMp_Stripe_Gateway->plugin_url . 'assets/frontend/js/';
		$frontend_script_path = str_replace( array( 'http:', 'https:' ), '', $frontend_script_path );
		$pluginURL = str_replace( array( 'http:', 'https:' ), '', $WCMp_Stripe_Gateway->plugin_url );
		$suffix 				= defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script('wcmp_stripe_js', $frontend_script_path. 'frontend.js', array('jquery'), $WCMp_Stripe_Gateway->version, true);
		wp_localize_script('wcmp_stripe_js', 'wcmp_stripe', array('ajaxurl' => admin_url('admin-ajax.php')));

		// Enqueue your frontend javascript from here
	}

	function frontend_styles() {
		global $WCMp_Stripe_Gateway;
		$frontend_style_path = $WCMp_Stripe_Gateway->plugin_url . 'assets/frontend/css/';
		$frontend_style_path = str_replace( array( 'http:', 'https:' ), '', $frontend_style_path );
		$suffix 				= defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_style('wcmp_stripe_css',  $frontend_style_path .'frontend.css', array(), $WCMp_Stripe_Gateway->version);

		// Enqueue your frontend stylesheet from here
	}

	function wcmp_stripe_gateway_frontend_function() {
		// Do your frontend work here
		

	}

}
