<?php
/**
 * WCMp Stripe Gateway Save Card
 */
class WCMp_Stripe_Gateway_Reset_Cards {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_after_my_account', array( $this, 'output_reset_card' ) );		
	}

	/**
	 * Display all saved cards
	 */
	public function output_reset_card() {
		global $WCMp_Stripe_Gateway;
		if ( ! is_user_logged_in() || ( ! $customer_id = get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) ) || ! is_string( $customer_id ) ) {
			return;
		}
		$stripe = new WCMp_Stripe_Gateway_Function();
		$reset_cards  = $stripe->reset_cards;
		if($reset_cards){
			$WCMp_Stripe_Gateway->template->get_template( 'reset-stripe.php');
		}		
	}

}

?>
