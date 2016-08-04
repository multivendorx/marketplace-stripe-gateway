<?php
class WCMp_Stripe_Gateway {

	public $plugin_url;
	public $plugin_path;
	public $version;
	public $token;
	public $text_domain;
	public $library;
	public $shortcode;
	public $admin;
	public $frontend;
	public $template;
	public $ajax;
	private $file;
	public $settings;
	public $dc_wp_fields;
	public $saved_cards;
	public $payment;
	public $connect_vendor;
	public $transfer;
	public $reset_cards_obj;

	public function __construct($file) {
		$this->file = $file;
		$this->plugin_url = trailingslashit(plugins_url('', $plugin = $file));
		$this->plugin_path = trailingslashit(dirname($file));
		$this->token = WCMp_STRIPE_GATEWAY_PLUGIN_TOKEN;
		$this->text_domain = WCMp_STRIPE_GATEWAY_TEXT_DOMAIN;
		$this->version = WCMp_STRIPE_GATEWAY_PLUGIN_VERSION;
		add_action('init', array(&$this, 'init'), 5);
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_stripe_gateway' ) );
	}

	/**
	 * initilize plugin on WP init
	 */
	function init() {

		// Init Text Domain
		$this->load_plugin_textdomain();

		// Init library
		$this->load_class('library');
		$this->library = new WCMp_Stripe_Gateway_Library();
		$this->library->stripe_library();

		// Init ajax
		if(defined('DOING_AJAX')) {
			$this->load_class('ajax');
			$this->ajax = new  WCMp_Stripe_Gateway_Ajax();
		}

		if (is_admin()) {
			$this->load_class('admin');
			$this->admin = new WCMp_Stripe_Gateway_Admin();
		}

		if (!is_admin() || defined('DOING_AJAX')) {
			$this->load_class('frontend');
			$this->frontend = new WCMp_Stripe_Gateway_Frontend();

			// init shortcode
			$this->load_class('shortcode');
			$this->shortcode = new WCMp_Stripe_Gateway_Shortcode();

			// init templates
			$this->load_class('template');
			$this->template = new WCMp_Stripe_Gateway_Template();
		}

		// WCMp Wp Fields
		$this->dc_wp_fields = $this->library->load_wp_fields();

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		} else {
			// Stripe Gateway Class
			$this->load_class('functions');

			// Stripe Gateway Saved Card
			$this->load_class('saved-cards');
			$this->saved_cards = new WCMp_Stripe_Gateway_Saved_Cards();
			
			// Stripe Gateway Reset Card Section
			$this->load_class('reset-cards');
			$this->reset_cards_obj = new WCMp_Stripe_Gateway_Reset_Cards();

			// Stripe Gateway for Subscriptions
			if ( class_exists( 'WC_Subscriptions_Order' ) || class_exists( 'WC_Pre_Orders_Order' ) ) {
				$this->load_class('functions-addon');
			}

			// Stripe Gateway Payment
			$this->load_class('payment');
			$this->payment = new WCMp_Stripe_Gateway_Payment();
			// Vendor Account Connect
			$this->load_class('connect-vendor');
			$this->connect_vendor = new WCMp_Stripe_Gateway_Connect_Vendor();
			// Stripe Money Transfer
			$this->load_class('transfer');
			$this->transfer = new WCMp_Stripe_Gateway_Transfer();
			// Stripe Mass Pay
			$this->load_class('masspay');
			$this->masspay = new WCMp_Stripe_Gateway_Masspay();
		}
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present
	 *
	 * @access public
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), $this->token );

		load_textdomain( $this->text_domain, WP_LANG_DIR . "/wcmp-stripe-gateway/wcmp-stripe-gateway-$locale.mo" );
		load_textdomain( $this->text_domain, $this->plugin_path . "/languages/wcmp-stripe-gateway-$locale.mo" );
	}

	public function load_class($class_name = '') {
		if ('' != $class_name && '' != $this->token) {
			require_once ('class-' . esc_attr($this->token) . '-' . esc_attr($class_name) . '.php');
		} // End If Statement
	}// End load_class()

	/**
	 * Install upon activation.
	 *
	 * @access public
	 * @return void
	 */
	function activate_wcmp_stripe_gateway() {
		global $WCMp_Stripe_Gateway;
	}

	/**
	 * UnInstall upon deactivation.
	 *
	 * @access public
	 * @return void
	 */
	function deactivate_wcmp_stripe_gateway() {
		global $WCMp_Stripe_Gateway;
		delete_option( 'wcmp_stripe_gateway_installed' );
	}

	/**
	 * Register Stripe Gateway
	 */
	function register_stripe_gateway( $methods ) {
		if ( class_exists( 'WC_Subscriptions_Order' ) || class_exists( 'WC_Pre_Orders_Order' ) ) {
			$methods[] = 'WCMp_Gateway_Stripe_Function_Addons';
		}
		else {
			$methods[] = 'WCMp_Stripe_Gateway_Function';
		}

		return $methods;
	}
	/** Cache Helpers *********************************************************/

	/**
	 * Sets a constant preventing some caching plugins from caching a page. Used on dynamic pages
	 *
	 * @access public
	 * @return void
	 */
	function nocache() {
		if (!defined('DONOTCACHEPAGE'))
			define("DONOTCACHEPAGE", "true");
		// WP Super Cache constant
	}

}
