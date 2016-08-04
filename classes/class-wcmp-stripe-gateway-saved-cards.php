<?php
/**
 * WCMp Stripe Gateway Save Card
 */
class WCMp_Stripe_Gateway_Saved_Cards {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_after_my_account', array( $this, 'output_save_card' ) );
		add_action( 'wp', array( $this, 'delete_saved_card' ) );
	}

	/**
	 * Display all saved cards
	 */
	public function output_save_card() {
		global $WCMp_Stripe_Gateway;

		if ( ! is_user_logged_in() || ( ! $customer_id = get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) ) || ! is_string( $customer_id ) ) {
			return;
		}
		$stripe = new WCMp_Stripe_Gateway_Function();
		$cards  = $stripe->get_saved_cards( $customer_id );

		if ( $cards ) {
			$WCMp_Stripe_Gateway->template->get_template( 'saved-cards.php', array( 'cards' => $cards ) );
		}
	}

	/**
	 * Delete a card
	 */
	public function delete_saved_card() {
		if ( ! isset( $_POST['stripe_delete_card'] ) || ! is_account_page() ) {
			return;
		}
		if ( ! is_user_logged_in() || ( ! $customer_id = get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) ) || ! is_string( $customer_id ) || ! wp_verify_nonce( $_POST['_wpnonce'], "stripe_del_card" ) ) {
			wp_die( __( 'Unable to delete the card, please try again', $WCMp_Stripe_Gateway->text_domain ) );
		}
		$stripe = new WCMp_Stripe_Gateway_Function();
		$result = $stripe->stripe_request( array(), 'customers/' . $customer_id . '/cards/' . sanitize_text_field( $_POST['stripe_delete_card'] ), 'DELETE' );

		delete_transient( 'stripe_cards_' . $customer_id );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( __( 'Unable to delete saved card.', $WCMp_Stripe_Gateway->text_domain ), 'error' );
		} else {
			wc_add_notice( __( 'Saved card has been deleted.', $WCMp_Stripe_Gateway->text_domain ), 'success' );
		}

		wp_safe_redirect( apply_filters( 'wcmp_stripe_manage_saved_cards_url', get_permalink( woocommerce_get_page_id( 'myaccount' ) ) ) );
		exit;
	}
}

?>
