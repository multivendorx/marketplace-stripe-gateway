<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCMp_Gateway_Stripe_Function_Addons extends WCMp_Stripe_Gateway_Function {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 3 );
			add_filter( 'woocommerce_subscriptions_renewal_order_meta_query', array( $this, 'remove_renewal_order_meta' ), 10, 4 );
			add_action( 'woocommerce_subscriptions_changed_failing_payment_method_stripe', array( $this, 'update_failing_payment_method' ), 10, 3 );
			// display the current payment method used for a subscription in the "My Subscriptions" table
			add_filter( 'woocommerce_my_subscriptions_recurring_payment_method', array( $this, 'maybe_render_subscription_payment_method' ), 10, 3 );
		}

		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment' ) );
		}
	}
	
	/**
	 * scheduled_subscription_payment function
	 *
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order, $product_id ) {
		$result = $this->process_subscription_payment( $order, $amount_to_charge );

		if ( is_wp_error( $result ) ) {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
		} else {
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
		}
	}
	
	/**
	 * Don't transfer Stripe customer/token meta when creating a parent renewal order.
	 *
	 */
	public function remove_renewal_order_meta( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {
		if ( 'parent' == $new_order_role ) {
			$order_meta_query .= " AND `meta_key` NOT LIKE '_stripe_customer_id' "
							  .  " AND `meta_key` NOT LIKE '_stripe_card_id' ";
		}
		return $order_meta_query;
	}
	
	/**
	 * Update the customer_id for a subscription after using Stripe to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 */
	public function update_failing_payment_method( $original_order, $renewal_order, $subscription_key ) {
		$new_customer_id = get_post_meta( $renewal_order->id, '_stripe_customer_id', true );
		$new_card_id     = get_post_meta( $renewal_order->id, '_stripe_card_id', true );
		update_post_meta( $original_order->id, '_stripe_customer_id', $new_customer_id );
		update_post_meta( $original_order->id, '_stripe_card_id', $new_card_id );
	}
	
	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription_details, WC_Order $order ) {
		global $WCMp_Stripe_Gateway;
		
		// bail for other payment methods
		if ( $this->id !== $order->recurring_payment_method || ! $order->customer_user ) {
			return $payment_method_to_display;
		}

		$user_id         = $order->customer;
		$stripe_customer = get_user_meta( $order->id, '_stripe_customer_id', true );

		// If we couldn't find a Stripe customer linked to the account, fallback to the order meta data.
		if ( ! $stripe_customer || ! is_string( $stripe_customer ) ) {
			$stripe_customer = get_post_meta( $order->id, '_stripe_customer_id', true );
		}

		// Card specified?
		$stripe_card = get_user_meta( $order->id, '_stripe_card_id', true );

		// Get cards from API
		$cards       = $this->get_saved_cards( $stripe_customer );

		if ( $cards ) {
			$found_card = false;
			foreach ( $cards as $card ) {
				if ( $card->id === $stripe_card ) {
					$found_card                = true;
					$payment_method_to_display = sprintf( __( 'Via %s card ending in %s', $WCMp_Stripe_Gateway->text_domain ), ( isset( $card->type ) ? $card->type : $card->brand ), $card->last4 );
					break;
				}
			}
			if ( ! $found_card ) {
				$payment_method_to_display = sprintf( __( 'Via %s card ending in %s', $WCMp_Stripe_Gateway->text_domain ), ( isset( $cards[0]->type ) ? $cards[0]->type : $cards[0]->brand ), $cards[0]->last4 );
			}
		}

		return $payment_method_to_display;
	}
	
	/**
	 * Process a pre-order payment when the pre-order is released
	 *
	 */
	public function process_pre_order_release_payment( $order ) {
		global $WCMp_Stripe_Gateway;
		
		try {
			$post_data['customer']    = get_post_meta( $order->id, '_stripe_customer_id', true );
			$post_data['card']        = get_post_meta( $order->id, '_stripe_card_id', true );
			$post_data['amount']      = $this->get_stripe_amount( $order->order_total );
			$post_data['currency']    = strtolower( get_woocommerce_currency() );
			$post_data['description'] = sprintf( __( '%s - Order %s', $WCMp_Stripe_Gateway->text_domain ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
			$post_data['capture']     = $this->capture ? 'true' : 'false';
			$post_data['expand[]']    = 'balance_transaction';

			// Make the request
			$response = $this->stripe_request( $post_data );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}

			// Store charge ID
			update_post_meta( $order->id, '_stripe_charge_id', $response->id );

			// Store other data such as fees
			update_post_meta( $order->id, 'Stripe Payment ID', $response->id );

			if ( isset( $response->balance_transaction ) && isset( $response->balance_transaction->fee ) ) {
				$fee = number_format( $response->balance_transaction->fee / 100, 2, '.', '' );
				update_post_meta( $order->id, 'Stripe Fee', $fee );
				update_post_meta( $order->id, 'Net Revenue From Stripe', $order->order_total - $fee );
			}

			if ( $response->captured ) {

				// Store captured value
				update_post_meta( $order->id, '_stripe_charge_captured', 'yes' );

				// Payment complete
				$order->payment_complete( $response->id );

				// Add order note
				$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s)', $WCMp_Stripe_Gateway->text_domain ), $response->id ) );

			} else {

				// Store captured value
				update_post_meta( $order->id, '_stripe_charge_captured', 'no' );
				add_post_meta( $order->id, '_transaction_id', $response->id, true );

				// Mark as on-hold
				$order->update_status( 'on-hold', sprintf( __( 'Stripe charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', $WCMp_Stripe_Gateway->text_domain ), $response->id ) );
			}

		} catch ( Exception $e ) {
			$order_note = sprintf( __( 'Stripe Transaction Failed (%s)', $WCMp_Stripe_Gateway->text_domain ), $e->getMessage() );

			// Mark order as failed if not already set,
			// otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
			if ( 'failed' != $order->status ) {
				$order->update_status( 'failed', $order_note );
			} else {
				$order->add_order_note( $order_note );
			}
		}
	}

	/**
	 * Process the pre-order
	 *
	 */
	public function process_pre_order( $order_id ) {
		global $WCMp_Stripe_Gateway;
		
		if ( WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id ) ) {
			$order        = new WC_Order( $order_id );
			$stripe_token = isset( $_POST['stripe_token'] ) ? wc_clean( $_POST['stripe_token'] ) : '';
			$card_id      = isset( $_POST['stripe_card_id'] ) ? wc_clean( $_POST['stripe_card_id'] ) : '';
			$customer_id  = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) : 0;

			if ( ! $customer_id || ! is_string( $customer_id ) ) {
				$customer_id = 0;
			}

			try {
				$post_data = array();

				// Check amount
				if ( $order->order_total * 100 < 50 ) {
					throw new Exception( __( 'Sorry, the minimum allowed order total is 0.50 to use this payment method.', $WCMp_Stripe_Gateway->text_domain ) );
				}

				// Pay using a saved card!
				if ( $card_id !== 'new' && $card_id && $customer_id ) {
					$post_data['customer'] = $customer_id;
					$post_data['card']     = $card_id;
				}

				// If not using a saved card, we need a token
				elseif ( empty( $stripe_token ) ) {
					$error_msg = __( 'Please make sure your card details have been entered correctly and that your browser supports JavaScript.', $WCMp_Stripe_Gateway->text_domain );

					if ( $this->testmode ) {
						$error_msg .= ' ' . __( 'Developers: Please make sure that you are including jQuery and there are no JavaScript errors on the page.', $WCMp_Stripe_Gateway->text_domain );
					}

					throw new Exception( $error_msg );
				}

				// Save token
				if ( ! $customer_id ) {
					$customer_id = $this->add_customer( $order, $stripe_token );

					if ( is_wp_error( $customer_id ) ) {
						throw new Exception( $customer_id->get_error_message() );
					}

					unset( $post_data['card'] );
					$post_data['customer'] = $customer_id;

				} elseif ( ! $card_id || $card_id === 'new' ) {
					$card_id = $this->add_card( $customer_id, $stripe_token );

					if ( is_wp_error( $card_id ) ) {
						throw new Exception( $card_id->get_error_message() );
					}

					$post_data['card']     = $card_id;
					$post_data['customer'] = $customer_id;
				}

				// Store the ID in the order
				update_post_meta( $order->id, '_stripe_customer_id', $customer_id );

				// Store the ID in the order
				update_post_meta( $order->id, '_stripe_card_id', $card_id );

				// Reduce stock levels
				$order->reduce_order_stock();

				// Remove cart
				WC()->cart->empty_cart();

				// Is pre ordered!
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );

				// Return thank you page redirect
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);

			} catch ( Exception $e ) {
				WC()->add_error( $e->getMessage() );
				return;
			}
		} else {
			return parent::process_payment( $order_id );
		}
	}

	/**
	 * Process the payment
	 *
	 */
	public function process_payment( $order_id ) {
		// Processing subscription
		if ( class_exists( 'WC_Subscriptions_Order' ) && WC_Subscriptions_Order::order_contains_subscription( $order_id ) ) {
			return $this->process_subscription( $order_id );

		// Processing pre-order
		} elseif ( class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Order::order_contains_pre_order( $order_id ) ) {
			return $this->process_pre_order( $order_id );

		// Processing regular product
		} else {
			return parent::process_payment( $order_id );
		}
	}

	/**
	 * process_subscription_payment function.
	 *
	 */
	public function process_subscription_payment( $order = '', $amount = 0 ) {
		global $WCMp_Stripe_Gateway;
		
		$order_items       = $order->get_items();
		$order_item        = array_shift( $order_items );
		$subscription_name = sprintf( __( 'Subscription for "%s"', $WCMp_Stripe_Gateway->text_domain ), $order_item['name'] ) . ' ' . sprintf( __( '(Order %s)', $WCMp_Stripe_Gateway->text_domain ), $order->get_order_number() );

		if ( $amount * 100 < 50 ) {
			return new WP_Error( 'stripe_error', __( 'Sorry, the minimum allowed order total is 0.50 to use this payment method.', $WCMp_Stripe_Gateway->text_domain ) );
		}

		// We need a customer in Stripe. First, look for the customer ID linked to the USER.
		$user_id         = $order->customer;
		$stripe_customer = get_user_meta( $order->id, '_stripe_customer_id', true );

		// If we couldn't find a Stripe customer linked to the account, fallback to the order meta data.
		if ( ! $stripe_customer || ! is_string( $stripe_customer ) ) {
			$stripe_customer = get_post_meta( $order->id, '_stripe_customer_id', true );
		}

		// Or fail :(
		if ( ! $stripe_customer ) {
			return new WP_Error( 'stripe_error', __( 'Customer not found', $WCMp_Stripe_Gateway->text_domain ) );
		}

		$stripe_payment_args = array(
			'amount'      => $this->get_stripe_amount( $amount ),
			'currency'    => strtolower( get_woocommerce_currency() ),
			'description' => $subscription_name,
			'customer'    => $stripe_customer,
			'expand[]'    => 'balance_transaction'
		);

		// See if we're using a particular card
		if ( $card_id = get_post_meta( $order->id, '_stripe_card_id', true ) ) {
			$stripe_payment_args['card'] = $card_id;
		}

		// Charge the customer
		$response = $this->stripe_request( $stripe_payment_args, 'charges' );

		if ( is_wp_error( $response ) ) {
			return $response;
		} else {
			$order->add_order_note( sprintf( __( 'Stripe subscription payment completed (Charge ID: %s)', $WCMp_Stripe_Gateway->text_domain ), $response->id ) );
			add_post_meta( $order->id, '_transaction_id', $response->id, true );
			return true;
		}
	}

	/**
     * Process the subscription
     *
     */
	public function process_subscription( $order_id ) {
		global $WCMp_Stripe_Gateway;
		
		$order        = new WC_Order( $order_id );
		$stripe_token = isset( $_POST['stripe_token'] ) ? wc_clean( $_POST['stripe_token'] ) : '';
		$card_id      = isset( $_POST['stripe_card_id'] ) ? wc_clean( $_POST['stripe_card_id'] ) : '';
		$customer_id  = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) : 0;

		if ( ! $customer_id || ! is_string( $customer_id ) ) {
			$customer_id = 0;
		}

		// Use Stripe CURL API for payment
		try {
			$post_data = array();

			// Pay using a saved card!
			if ( $card_id !== 'new' && $card_id && $customer_id ) {
				$post_data['customer'] = $customer_id;
				$post_data['card']     = $card_id;
			}

			// If not using a saved card, we need a token
			elseif ( empty( $stripe_token ) ) {
				$error_msg = __( 'Please make sure your card details have been entered correctly and your browser supports JavaScript.', $WCMp_Stripe_Gateway->text_domain );

				if ( $this->testmode ) {
					$error_msg .= ' ' . __( 'Developers: Please make sure that you are including jQuery and there are no JavaScript errors on the page.', $WCMp_Stripe_Gateway->text_domain );
				}

				throw new Exception( $error_msg );
			}

			// Save token
			if ( ! $customer_id ) {
				$customer_id = $this->add_customer( $order, $stripe_token );

				if ( is_wp_error( $customer_id ) ) {
					throw new Exception( $customer_id->get_error_message() );
				}

				unset( $post_data['card'] );
				$post_data['customer'] = $customer_id;

			} elseif ( ! $card_id || $card_id === 'new' ) {
				$card_id = $this->add_card( $customer_id, $stripe_token );

				if ( is_wp_error( $card_id ) ) {
					throw new Exception( $card_id->get_error_message() );
				}

				$post_data['card']     = $card_id;
				$post_data['customer'] = $customer_id;
			}

			// Store the ID in the order
			update_post_meta( $order->id, '_stripe_customer_id', $customer_id );

			// Store the ID in the order
			update_post_meta( $order->id, '_stripe_card_id', $card_id );

			$initial_payment = WC_Subscriptions_Order::get_total_initial_payment( $order );

			if ( $initial_payment > 0 ) {
				$payment_response = $this->process_subscription_payment( $order, $initial_payment );
			}

			if ( isset( $payment_response ) && is_wp_error( $payment_response ) ) {

				throw new Exception( $payment_response->get_error_message() );

			} else {

				if ( isset( $payment_response->balance_transaction ) && isset( $payment_response->balance_transaction->fee ) ) {
					$fee = number_format( $payment_response->balance_transaction->fee / 100, 2, '.', '' );
					update_post_meta( $order->id, 'Stripe Fee', $fee );
					update_post_meta( $order->id, 'Net Revenue From Stripe', $order->order_total - $fee );
				}

				// Payment complete
				$order->payment_complete( $payment_response->id );

				// Remove cart
				WC()->cart->empty_cart();

				// Activate subscriptions
				WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );

				// Return thank you page redirect
				return array(
					'result' 	=> 'success',
					'redirect'	=> $this->get_return_url( $order )
				);
			}

		} catch ( Exception $e ) {
			wc_add_notice( __('Error:', $WCMp_Stripe_Gateway->text_domain) . ' "' . $e->getMessage() . '"', 'error' );
			return;
		}
	}

}

?>
