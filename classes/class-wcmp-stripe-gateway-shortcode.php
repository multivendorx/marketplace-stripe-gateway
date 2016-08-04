<?php
class WCMp_Stripe_Gateway_Shortcode {

	public $list_product;

	public function __construct() {
		// stripe connect shortcodes
		add_shortcode('vendor_stripe_connect', array(&$this, 'stripe_connect_shortcode'));
	}

	public function stripe_connect_shortcode($attr) {
		global $WCMp_Stripe_Gateway;
		$this->load_class('stripe-connect');
		return $this->shortcode_wrapper(array('WCMp_Stripe_Connect_Shortcode', 'output'));
	}

	/**
	 * Helper Functions
	 */

	/**
	 * Shortcode Wrapper
	 *
	 * @access public
	 * @param mixed $function
	 * @param array $atts (default: array())
	 * @return string
	 */
	public function shortcode_wrapper($function, $atts = array()) {
		ob_start();
		call_user_func($function, $atts);
		return ob_get_clean();
	}

	/**
	 * Shortcode CLass Loader
	 *
	 * @access public
	 * @param mixed $class_name
	 * @return void
	 */
	public function load_class($class_name = '') {
		global $WCMp_Stripe_Gateway;
		if ('' != $class_name && '' != $WCMp_Stripe_Gateway->token) {
			require_once ('shortcode/class-' . esc_attr($WCMp_Stripe_Gateway->token) . '-shortcode-' . esc_attr($class_name) . '.php');
		}
	}

}
?>
