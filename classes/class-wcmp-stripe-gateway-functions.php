<?php
class WCMp_Stripe_Gateway_Function extends WC_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		global $WCMp_Stripe_Gateway;

		$this->id = 'stripe';
		$this->method_title = __( 'Stripe (WCMp Compatible)', $WCMp_Stripe_Gateway->text_domain );
		$this->method_description = __( 'Stripe (WCMp Compatible) accepts credit card details from customer on the checkout page and then sends the details to Stripe for verification once they varify payment process will proceed.', $WCMp_Stripe_Gateway->text_domain );
		$this->has_fields = true;
		$this->api_endpoint = 'https://api.stripe.com/';
		$this->view_transaction_url = 'https://dashboard.stripe.com/payments/%s';
		$this->supports = array(
			'subscriptions',
			'products',
			'refunds',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change',
			'subscription_date_changes',
			'pre-orders'
		);

		// Icon
		$icon = WC()->countries->get_base_country() == 'US' ? 'cards.png' : 'eu_cards.png';
		$this->icon = apply_filters( 'stripe_icon', $WCMp_Stripe_Gateway->plugin_url . 'assets/images/' . $icon );

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values
		$this->title                 = $this->get_option( 'title' );
		$this->description           = $this->get_option( 'description' );
		$this->enabled               = $this->get_option( 'enabled' );
		$this->testmode              = $this->get_option( 'testmode' ) === "yes" ? true : false;
		$this->capture               = $this->get_option( 'capture', "yes" ) === "yes" ? true : false;
		$this->stripe_checkout       = $this->get_option( 'stripe_checkout' ) === "yes" ? true : false;
		$this->stripe_checkout_image = $this->get_option( 'stripe_checkout_image', '' );
		$this->saved_cards           = $this->get_option( 'saved_cards' ) === "yes" ? true : false;
		$this->reset_cards           = $this->get_option( 'reset_cards' ) === "yes" ? true : false;
		$this->client_id             = $this->testmode ? $this->get_option( 'test_client_id' ) : $this->get_option( 'client_id' );
		$this->secret_key            = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
		$this->publishable_key       = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );

		if ( $this->stripe_checkout ) {
			$this->order_button_text = __( 'Continue to payment', $WCMp_Stripe_Gateway->text_domain );
		}

		if ( $this->testmode ) {
			$this->description .= ' ' . __( 'TEST MODE IS ENABLED. Now, you can use the card number 4242424242424242 with any CVC and a valid expiration date.', $WCMp_Stripe_Gateway->text_domain );
			$this->description  = trim( $this->description );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'wcmp_stripe_payment_scripts' ) );
		add_action( 'admin_notices', array( $this, 'stripe_admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initialise Stripe Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		global $WCMp_Stripe_Gateway;

		$this->form_fields = apply_filters( 'wcmp_stripe_settings', array(
			'confinguration_section_title' => array(
				'title' => __( 'Configure the stripe settings', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'title',
				'description' => __( 'Please configure the requested settings.', $WCMp_Stripe_Gateway->text_domain )				
			),
			'enabled' => array(
				'title' => __( 'Enable/Disable', $WCMp_Stripe_Gateway->text_domain ),
				'label' => __( 'Enable Stripe', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'checkbox',
				'description' => '',
				'default'  => 'no'
			),
			'testmode' => array(
				'title' => __( 'Test mode', $WCMp_Stripe_Gateway->text_domain ),
				'label' => __( 'Enable Test Mode', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'checkbox',
				'description' => __( 'This will enable the test mode of Stripe using test API keys.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => 'yes'
			),
			
			'display_section_title' => array(
				'title' => __( 'Enter your display details settings', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'title',
				'description' => __( 'Enter All requested information given below.', $WCMp_Stripe_Gateway->text_domain )				
			),
			
			'title' => array(
				'title' => __( 'Stripe Gateway Title', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'text',
				'description' => __( 'Title of Stripe Gateway will shown on checkout page.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => __( 'Credit card (Stripe)', $WCMp_Stripe_Gateway->text_domain )
			),
			'description' => array(
				'title' => __( 'Description', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'textarea',
				'description' => __( 'Description of Stripe Gateway on checkout page.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => __( 'Pay with your credit card via Stripe.', $WCMp_Stripe_Gateway->text_domain)
			),
			'api_section_title' => array(
				'title' => __( 'Enter your API Details', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'title',
				'description' => __( 'Enter All requested information given below.', $WCMp_Stripe_Gateway->text_domain )				
			),			
			'client_id' => array(
				'title' => __( 'Live Client ID', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'text',
				'description' => __( 'Get your Client ID from your Stripe account.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => ''
			),
			'secret_key' => array(
				'title' => __( 'Live Secret Key', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'text',
				'description' => __( 'Get your API keys from your Stripe account.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => ''
			),
			'publishable_key' => array(
				'title' => __( 'Live Publishable Key', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'text',
				'description' => __( 'Get your API keys from your Stripe account.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => ''
			),
			'test_client_id' => array(
				'title' => __( 'Test Client ID', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'text',
				'description' => __( 'Get your Client ID from your Stripe account.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => ''
			),
			'test_secret_key' => array(
				'title' => __( 'Test Secret Key', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'text',
				'description' => __( 'Get your API keys from your Stripe account.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => ''
			),
			'test_publishable_key' => array(
				'title' => __( 'Test Publishable Key', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'text',
				'description' => __( 'Get your API keys from your Stripe account.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => ''
			),
			'other_section_title' => array(
				'title' => __( 'Configure the other settings', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'title',
				'description' => __( 'Please configure the requested settings.', $WCMp_Stripe_Gateway->text_domain )				
			),
			'capture' => array(
				'title' => __( 'Capture', $WCMp_Stripe_Gateway->text_domain ),
				'label' => __( 'Capture charge immediately', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'checkbox',
				'description' => __( 'The charge will be captured immediately when checked. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => 'yes'
			),
			'stripe_checkout' => array(
				'title' => __( 'Stripe Checkout', $WCMp_Stripe_Gateway->text_domain ),
				'label' => __( 'Enable Stripe Checkout', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'checkbox',
				'description' => __( 'If enabled, this option shows a "pay" button and modal credit card form on the checkout, instead of credit card fields directly on the page.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => 'yes'
			),
			'stripe_checkout_image' => array(
				'title' => __( 'Stripe Checkout Image', $WCMp_Stripe_Gateway->text_domain ),
				'description' => __( 'Optionally enter the URL to a 128x128px image of your brand or product. e.g. <code>https://yoursite.com/wp-content/uploads/2013/09/yourimage.jpg</code>', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'text',
				'default' => ''
			),
			'saved_cards' => array(
				'title' => __( 'Saved cards', $WCMp_Stripe_Gateway->text_domain ),
				'label' => __( 'Enable saved cards', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Stripe servers, not on your store.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => 'no'
			),
			'reset_cards' => array(
				'title' => __( 'Reset card section', $WCMp_Stripe_Gateway->text_domain ),
				'label' => __( 'Enable reset card section', $WCMp_Stripe_Gateway->text_domain ),
				'type' => 'checkbox',
				'description' => __( 'If enabled, users will be able to reset his card information in one click. and it is also useful when customer id deleted from stripe but not from your website.', $WCMp_Stripe_Gateway->text_domain ),
				'default' => 'no'
			),
		) );
	}

	/**
	 * Outputs scripts used for stripe payment
	 *
	 */
	public function wcmp_stripe_payment_scripts() {
		global $WCMp_Stripe_Gateway;

		if ( ! is_checkout() ) {
			return;
		}

		if ( $this->stripe_checkout ) {
			wp_enqueue_script( 'stripe', 'https://checkout.stripe.com/v2/checkout.js', '', '2.0', true );
			wp_enqueue_script( 'wcmp_stripe', $WCMp_Stripe_Gateway->plugin_url . 'assets/js/stripe_checkout.js', array( 'stripe' ), $WCMp_Stripe_Gateway->version, true );
		} else {
			wp_enqueue_script( 'stripe', 'https://js.stripe.com/v1/', '', '1.0', true );
			wp_enqueue_script( 'wcmp_stripe', $WCMp_Stripe_Gateway->plugin_url . 'assets/js/stripe.js', array( 'stripe' ), $WCMp_Stripe_Gateway->version, true );
		}
		$stripe_params = array(
			'key'                  => $this->publishable_key,
			'i18n_terms'           => __( 'Please accept the terms and conditions first', $WCMp_Stripe_Gateway->text_domain ),
			'i18n_required_fields' => __( 'Please fill in required checkout fields first', $WCMp_Stripe_Gateway->text_domain ),
		);

		// If we're on the pay page we need to pass stripe.js the address of the order.
		if ( is_checkout_pay_page() && isset( $_GET['order'] ) && isset( $_GET['order_id'] ) ) {
			$order_key = urldecode( $_GET['order'] );
			$order_id  = absint( $_GET['order_id'] );
			$order     = new WC_Order( $order_id );

			if ( $order->id == $order_id && $order->order_key == $order_key ) {
				$stripe_params['billing_first_name'] = $order->billing_first_name;
				$stripe_params['billing_last_name']  = $order->billing_last_name;
				$stripe_params['billing_address_1']  = $order->billing_address_1;
				$stripe_params['billing_address_2']  = $order->billing_address_2;
				$stripe_params['billing_state']      = $order->billing_state;
				$stripe_params['billing_city']       = $order->billing_city;
				$stripe_params['billing_postcode']   = $order->billing_postcode;
				$stripe_params['billing_country']    = $order->billing_country;
			}
		}

		wp_localize_script( 'wcmp_stripe', 'wc_stripe_params', $stripe_params );
	}

	/**
	 * Check if SSL is enabled, keys are added, then notify user
	 */
	public function stripe_admin_notices() {
		global $WCMp_Stripe_Gateway;

		if ( $this->enabled == 'no' ) {
			return;
		}

		// Check required fields
		if ( ! $this->secret_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'Stripe error: Please enter your secret key <a href="%s">here</a>', $WCMp_Stripe_Gateway->text_domain ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wcmp_stripe_gateway_function' ) ) . '</p></div>';
			return;
		} elseif ( ! $this->publishable_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'Stripe error: Please enter your publishable key <a href="%s">here</a>', $WCMp_Stripe_Gateway->text_domain ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wcmp_stripe_gateway_function' ) ) . '</p></div>';
			return;
		}

		// Check client id
		if ( ! $this->client_id ) {
			echo '<div class="error"><p>' . sprintf( __( 'Stripe error: Please enter your client id <a href="%s">here</a>', $WCMp_Stripe_Gateway->text_domain ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wcmp_stripe_gateway_function' ) ) . '</p></div>';
			return;
		}

		// Simple check for duplicate keys
		if ( $this->secret_key == $this->publishable_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'Stripe error: Your secret and publishable keys match. Please check.', $WCMp_Stripe_Gateway->text_domain ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wcmp_stripe_gateway_function' ) ) . '</p></div>';
			return;
		}

		// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected
		if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && ! class_exists( 'WordPressHTTPS' ) ) {
			echo '<div class="error"><p>' . sprintf( __( 'Stripe is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate - Stripe will only work in test mode.', $WCMp_Stripe_Gateway->text_domain ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
		}
	}

	/**
	 * Stripe amount filter
	 */
	public function get_stripe_amount( $total ) {
		switch ( get_woocommerce_currency() ) {
			// Zero decimal currencies
			case 'BIF' :
			case 'CLP' :
			case 'DJF' :
			case 'GNF' :
			case 'JPY' :
			case 'KMF' :
			case 'KRW' :
			case 'MGA' :
			case 'PYG' :
			case 'RWF' :
			case 'VND' :
			case 'VUV' :
			case 'XAF' :
			case 'XOF' :
			case 'XPF' :
				$total = absint( $total );
				break;
			default :
				$total = $total * 100; // In cents
				break;
		}
		return $total;
	}

	/**
	 * Check if this gateway is enabled or available for use
	 */
	public function is_available() {
		if ( $this->enabled == "yes" ) {
			/*if ( ! is_ssl() && ! $this->testmode ) {
				return false;
			}*/
			// Required fields check
			if ( ! $this->secret_key || ! $this->publishable_key || ! $this->client_id ) {
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		global $WCMp_Stripe_Gateway;
		$checked = 1;
		?>
		<fieldset>
			<?php
				if ( $this->description ) {
					echo wpautop( esc_html( $this->description ) );
				}
				$customer_id = get_user_meta( get_current_user_id(), '_stripe_customer_id', true );
				$cards = $this->get_saved_cards( $customer_id );
				if ( $this->saved_cards && is_user_logged_in() && $customer_id && is_string( $customer_id ) && $cards ) {
					?>
					<p class="form-row form-row-wide">
						<a class="button" style="float:right;" href="<?php echo apply_filters( 'wcmp_stripe_manage_saved_cards_url', get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ); ?>#saved-cards"><?php _e( 'Manage cards', $WCMp_Stripe_Gateway->text_domain ); ?></a>
						<?php if ( $cards ) : ?>
							<?php foreach ( (array) $cards as $card ) : ?>
								<label for="stripe_card_<?php echo $card->id; ?>">
									<input type="radio" id="stripe_card_<?php echo $card->id; ?>" name="stripe_card_id" value="<?php echo $card->id; ?>" <?php checked( $checked, 1 ) ?> />
									<?php printf( __( '%s card ending in %s (Expires %s/%s)', $WCMp_Stripe_Gateway->text_domain ), (isset( $card->type ) ? $card->type : $card->brand ), $card->last4, $card->exp_month, $card->exp_year ); ?>
								</label>
								<?php $checked = 0; endforeach; ?>
						<?php endif; ?>
						<label for="new">
							<input type="radio" id="new" name="stripe_card_id" <?php checked( $checked, 1 ) ?> value="new" />
							<?php _e( 'Use a new credit card', $WCMp_Stripe_Gateway->text_domain ); ?>
						</label>
					</p>
					<?php
				}
			?>
			<div class="stripe_new_card" <?php if ( $checked === 0 ) : ?>style="display:none;"<?php endif; ?>
				data-description=""
				data-amount="<?php echo $this->get_stripe_amount( WC()->cart->total ); ?>"
				data-name="<?php echo sprintf( __( '%s', $WCMp_Stripe_Gateway->text_domain ), get_bloginfo( 'name' ) ); ?>"
				data-label="<?php _e( 'Confirm and Pay', $WCMp_Stripe_Gateway->text_domain ); ?>"
				data-currency="<?php echo strtolower( get_woocommerce_currency() ); ?>"
				data-image="<?php echo $this->stripe_checkout_image; ?>"
				>
				<?php if ( ! $this->stripe_checkout ) : ?>
					<?php $WC_Payment_Gateway_CC = new WC_Payment_Gateway_CC(); $WC_Payment_Gateway_CC->form( array( 'fields_have_names' => false ) ); ?>
				<?php endif; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Get a customers saved cards
	 *
	 */
	public function get_saved_cards( $customer_id ) {
		if ( false === ( $cards = get_transient( 'stripe_cards_' . $customer_id ) ) ) {
			$response = $this->request_stripe_api( array(
				'limit'       => 100
			), 'customers/' . $customer_id . '/cards', 'GET' );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$cards = $response->data;

			set_transient( 'stripe_cards_' . $customer_id, $cards, HOUR_IN_SECONDS * 48 );
		}

		return $cards;
	}


	/**
	 * Process the payment
	 */
	public function process_payment( $order_id ) {
		global $WCMp_Stripe_Gateway;

		$order        = new WC_Order( $order_id );
		$stripe_token = isset( $_POST['stripe_token'] ) ? wc_clean( $_POST['stripe_token'] ) : '';
		$card_id      = isset( $_POST['stripe_card_id'] ) ? wc_clean( $_POST['stripe_card_id'] ) : '';
		$customer_id  = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) : 0;
		if ( ! $customer_id || ! is_string( $customer_id ) ) {
			$customer_id = 0;
		}		
		try {
			$post_data = array();			
			if ( $order->order_total * 100 < 50 ) {
				throw new Exception( __( 'Sorry, minimum allowed order total is 0.50 to use stripe payment method.', $WCMp_Stripe_Gateway->text_domain ) );
			}
			// Pay by a saved card
			if ( $card_id !== 'new' && $card_id && $customer_id ) {
				$post_data['customer'] = $customer_id;
				$post_data['card']     = $card_id;
			}
			// If not using a saved card, we need a token
			elseif ( empty( $stripe_token ) ) {
				$error_msg = __( 'Please make sure card details you have given is correct and your browser must have support javascript.', $WCMp_Stripe_Gateway->text_domain );
				if ( $this->testmode ) {
					$error_msg .= ' ' . __( 'Desr Developers please make sure that you are including jQuery files and there is no js error in this page.', $WCMp_Stripe_Gateway->text_domain );
				}
				throw new Exception( $error_msg );
			}
			// Use token
			else {
				// Save token if logged in
				if ( is_user_logged_in() && $this->saved_cards ) {
					if ( ! $customer_id ) {
						$customer_id = $this->add_customer( $order, $stripe_token );
						if ( is_wp_error( $customer_id ) ) {
							throw new Exception( $customer_id->get_error_message() );
						}
					} else {
						$card_id = $this->add_card( $customer_id, $stripe_token );

						if ( is_wp_error( $card_id ) ) {
							throw new Exception( $card_id->get_error_message() );
						}
						$post_data['card'] = $card_id;
					}
					$post_data['customer'] = $customer_id;
				} else {
					$post_data['card'] = $stripe_token;
				}
			}

			
			$post_data['amount']      = $this->get_stripe_amount( $order->order_total );
			$post_data['currency']    = strtolower( get_woocommerce_currency() );
			$post_data['description'] = sprintf( __( '%s - Order %s', $WCMp_Stripe_Gateway->text_domain ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
			$post_data['capture']     = $this->capture ? 'true' : 'false';
			$post_data['expand[]']    = 'balance_transaction';

			// Make the request
			$response = $this->request_stripe_api( $post_data );

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

				// Reduce stock levels
				$order->reduce_order_stock();
			}

			// Remove cart
			WC()->cart->empty_cart();

			// Return thank you page redirect
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);

		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			return;
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
			update_post_meta( $commission->ID, '_paid_status', 'paid' );
		}
	}

	/**
	 * Refund a charge
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		global $WCMp_Stripe_Gateway;

		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_transaction_id() ) {
			return false;
		}

		if ( is_null( $amount ) ) {
			$response = $this->request_stripe_api( array(), 'charges/' . $order->get_transaction_id() . '/refunds' );
		} else {
			$response = $this->request_stripe_api( array(
				'amount' => $this->get_stripe_amount( $amount )
			), 'charges/' . $order->get_transaction_id() . '/refunds' );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( ! empty( $response->id ) ) {
			$order->add_order_note( sprintf( __( 'Refunded %s - Refund ID: %s', 'woocommerce' ), $response->amount, $response->id ) );
			return true;
		}
	}

	/**
	 * Add a customer to Stripe
	 *
	 */
	public function add_customer( $order, $stripe_token ) {
		global $WCMp_Stripe_Gateway;

		if ( $stripe_token && is_user_logged_in() ) {
			$response = $this->request_stripe_api( array(
				'email'       => $order->billing_email,
				'description' => 'Customer: ' . $order->billing_first_name . ' ' . $order->billing_last_name,
				'card'        => $stripe_token,
				'expand[]'    => 'default_card'
			), 'customers' );

			if ( is_wp_error( $response ) ) {
				return $response;
			} elseif ( ! empty( $response->id ) ) {
				// Store the ID on the user account
				update_user_meta( get_current_user_id(), '_stripe_customer_id', $response->id );

				// Store the ID in the order
				update_post_meta( $order->id, '_stripe_customer_id', $response->id );

				return $response->id;
			}
		}
		return new WP_Error( 'error', __( 'Unable to add customer', $WCMp_Stripe_Gateway->text_domain ) );
	}

	/**
	 * Add a card to a customer
	 *
	 */
	public function add_card( $customer_id, $stripe_token ) {
		global $WCMp_Stripe_Gateway;

		if ( $stripe_token ) {
			$response = $this->request_stripe_api( array(
				'card'        => $stripe_token
			), 'customers/' . $customer_id . '/cards' );

			delete_transient( 'stripe_cards_' . $customer_id );

			if ( is_wp_error( $response ) ) {
				return $response;
			} elseif ( ! empty( $response->id ) ) {
				return $response->id;
			}
		}
		return new WP_Error( 'error', __( 'Unable to add card', $WCMp_Stripe_Gateway->text_domain ) );
	}

	/**
	 * Send the request using API
	 *
	 */
	public function request_stripe_api( $request, $api = 'charges', $method = 'POST' ) {
		global $WCMp_Stripe_Gateway;

		$response = wp_remote_post(
			$this->api_endpoint . 'v1/' . $api,
			array(
				'method'        => strtoupper($method),
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( $this->secret_key . ':' ),
					'Stripe-Version' => '2015-06-15'
				),
				'body'       => apply_filters( 'wcmp_request_stripe_api_body', $request, $api ),
				'timeout'    => 12,
				'sslverify'  => false,
				'user-agent' => 'WooCommerce ' . WC()->version
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'stripe_error', __( 'There was a problem connecting to the payment gateway.', $WCMp_Stripe_Gateway->text_domain ) );
		}

		if ( empty( $response['body'] ) ) {
			return new WP_Error( 'stripe_error', __( 'Empty response.', $WCMp_Stripe_Gateway->text_domain ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			return new WP_Error( 'stripe_error', $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}

}

?>
