<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCMp_Stripe_Gateway_Payment {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );
		
	}

	
	/**
	 * This function will be called when the order status is changed from on-hold to complete or processing
	 *
	 */
	function capture_payment($order_id) {
		global $WCMp_Stripe_Gateway;
		
		$order = new WC_Order( $order_id );
		
		if ( $order->payment_method == 'stripe' ) {
			$charge   = get_post_meta( $order_id, '_stripe_charge_id', true );
			$captured = get_post_meta( $order_id, '_stripe_charge_captured', true );

			if ( $charge && $captured == 'no' ) {
				$stripe = new WC_Gateway_Stripe_Function();

				$result = $stripe->stripe_request( array(
					'amount' => $order->order_total * 100
				), 'charges/' . $charge . '/capture' );

				if ( is_wp_error( $result ) ) {
					$order->add_order_note( __( 'Unable to capture charge!', $WCMp_Stripe_Gateway->text_domain ) . ' ' . $result->get_error_message() );
				} else {
					$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s)', $WCMp_Stripe_Gateway->text_domain ), $result->id ) );
					update_post_meta( $order->id, '_stripe_charge_captured', 'yes' );
					
					
					$commission_ids = commission_ids($order->id);
					
					
					$transfer = $WCMp_Stripe_Gateway->transfer->payment_transfer_to_vendor($order->id);
					
					if( isset($transfer) && !empty($transfer) ) {
						$transfer_id = $transfer->_values['id'];
					}
				
					update_post_meta( $order->id, '_stripe_vendor_transfer_id', $transfer_id );
					
					// Add order note
					$order->add_order_note( sprintf( __( 'Vendor commission transfered (Transfer ID: %s)', $WCMp_Stripe_Gateway->text_domain ), $transfer_id ) );
					
					$this->commission_status_set($order->id);

					// Store other data such as fees
					update_post_meta( $order->id, 'Stripe Payment ID', $result->id );
					update_post_meta( $order->id, 'Stripe Fee', number_format( $result->fee / 100, 2, '.', '' ) );
					update_post_meta( $order->id, 'Net Revenue From Stripe', ( $order->order_total - number_format( $result->fee / 100, 2, '.', '' ) ) );
				}
			}
		}
	}
	
	/**
	 * Set commission status after transaction complete
	 *
	 */
	function commission_status_set($order_id) {
		global $wpdb;
		
		$args = array(
			'post_type' => 'dc_commission',
			'post_status' => array( 'publish', 'private' ),
			'posts_per_page' => -1,
			'order' => 'asc',
			'meta_key' => '_commission_order_id',
			'meta_value' => $order_id
		);
		
		$commissions = get_posts($args);
		
		foreach( $commissions as $commission ) {
			$commission_id = $commission->ID;
			update_post_meta( $commission_id, '_paid_status', 'paid' );
		}
	}

	
	/**
	 * This function will be called when a payment is cancelled
	 *
	 */
	function cancel_payment($order_id) {
		global $WCMp_Stripe_Gateway;
		
		$order = new WC_Order( $order_id );

		if ( $order->payment_method == 'stripe' ) {
			$charge   = get_post_meta( $order_id, '_stripe_charge_id', true );

			if ( $charge ) {
				$stripe = new WC_Gateway_Stripe_Function();

				$result = $stripe->stripe_request( array(
					'amount' => $order->order_total * 100
				), 'charges/' . $charge . '/refund' );

				if ( is_wp_error( $result ) ) {
					$order->add_order_note( __( 'Unable to refund charge!', $WCMp_Stripe_Gateway->text_domain ) . ' ' . $result->get_error_message() );
				} else {
					$order->add_order_note( sprintf( __( 'Stripe charge refunded (Charge ID: %s)', $WCMp_Stripe_Gateway->text_domain ), $result->id ) );
					delete_post_meta( $order->id, '_stripe_charge_captured' );
					delete_post_meta( $order->id, '_stripe_charge_id' );
				}
			}
		}
	}

}


?>
