<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Stripe\Stripe;
use Stripe\Transfer;

class WCMp_Stripe_Gateway_Transfer {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		
		// Transfer money to Vendor Account
		add_action( 'woocommerce_payment_complete', array($this, 'commission_transfer'), 50, 1 );
	}
	
	function commission_transfer($order_id) {
		global $WCMp_Stripe_Gateway;
                
		$commission_ids = get_commission_id($order_id);
		$order = new WC_Order($order_id);
                
                foreach( $commission_ids as $commission_id ) {
                    $commission_status = get_post_meta( $commission_id, '_paid_status', true );
                    if( isset($commission_status) && !empty($commission_status) ) {
                            if( $commission_status == 'unpaid' ) {
                                    $commission_vendor_term_id = get_post_meta( $commission_id, '_commission_vendor', true );
                                    $vendor_obj = get_wcmp_vendor_by_term($commission_vendor_term_id);
                                    $vendor_id = $vendor_obj->id;
                                    $vendor_name = $vendor_obj->user_data->data->user_login;
                                    $vendor_payment_mode = get_user_meta($vendor_id, '_vendor_payment_mode', true);
                                    if(empty($vendor_payment_mode)) {
                                        continue;
                                    }
                                    if($vendor_payment_mode != 'stripe_adaptive') {
                                        continue;
                                    }
                                    $commission_ammount = get_post_meta( $commission_id, '_commission_amount', true );
                                    $shipping_ammount = get_post_meta( $commission_id, '_shipping', true );
                                    $tax_ammount = get_post_meta( $commission_id, '_tax', true );
                                    $total_ammount = $commission_ammount + $shipping_ammount + $tax_ammount;						
                                    $total_stripe_ammount = $this->get_stripe_amount($total_ammount);
                                    $check_vendor_connected = get_user_meta( $vendor_id, 'vendor_connected', true );
                                    if( $check_vendor_connected = 1 ) {
                                            $vendor_stripe_user_id = get_user_meta( $vendor_id, 'stripe_user_id', true );
                                            $transfer_details = array(
                                                    'total_commission' => $total_stripe_ammount,
                                                    'stripe_user_id' => $vendor_stripe_user_id
                                            );

                                            $transfer = $this->vendor_commission_transfer($transfer_details);

                                            if( isset($transfer) && !empty($transfer) ) {
                                                    update_post_meta( $commission_id, '_paid_status', 'paid' );
                                                    $customer_array = $transfer->__toArray(true);

                                                    $transfer_id = $customer_array['id'];
                                                    add_post_meta( $order_id, '_stripe_vendor_transfer_id_'.$commission_vendor_term_id, $transfer_id, true );

                                                    // Add order note
                                                    $order->add_order_note( sprintf( __( 'Commission transfered to vendor-%s via stripe, amount: %s (Transfer ID: %s)', $WCMp_Stripe_Gateway->text_domain ), $vendor_name, $total_ammount, $transfer_id ) );

                                            } else {
                                                    // Add order note when transfer failed
                                                    $order->add_order_note( sprintf( __( 'Commission transfer failed to vendor-%s via stripe', $WCMp_Stripe_Gateway->text_domain ), $vendor_name ) );
                                            }
                                    } else {
                                            // Add order note when vendor is not connected
                                            $order->add_order_note( sprintf( __( 'Commission transfer failed to vendor-%s via stripe. Vendor-%s is not connected with stripe.', $WCMp_Stripe_Gateway->text_domain ), $vendor_name, $vendor_name ) );
                                    }
                            } else {
                                    // Add order note when commission is already paid
                                    $order->add_order_note( sprintf( __( 'Commission is already paid to vendor-%s', $WCMp_Stripe_Gateway->text_domain ), $vendor_name ) );
                            }
                    }
            }
	}
	
  /**
	 * Transfer Commision to Vendors
	 */
  public function vendor_commission_transfer($transfer_details) {
  	global $WCMp_Stripe_Gateway;
  	
		$stripe_settings = get_option('woocommerce_stripe_settings');
		
		if( isset($stripe_settings) && !empty($stripe_settings) ) {
			$testmode = $stripe_settings['testmode'] === "yes" ? true : false;
			$secret_key = $testmode ? $stripe_settings['test_secret_key'] : $stripe_settings['secret_key'];
			
			try{
				Stripe::setApiKey($secret_key);
				
				$transfer_arg = array(
					'amount' => $transfer_details['total_commission'],
					'currency' => strtolower( get_woocommerce_currency() ),
					'destination' => $transfer_details['stripe_user_id']
				);
				
				$transfer = Transfer::create($transfer_arg);
			
				return $transfer;
			} catch (\Stripe\Error\InvalidRequest $e) {
				// Invalid parameters were supplied to Stripe's API
				$error = $e->getMessage();
			} catch (\Stripe\Error\Authentication $e) {
				// Authentication with Stripe's API failed
				// (maybe you changed API keys recently)
				$error = $e->getMessage();
			} catch (\Stripe\Error\ApiConnection $e) {
				// Network communication with Stripe failed
				$error = $e->getMessage();
			} catch (\Stripe\Error\Base $e) {
				// Display a very generic error to the user, and maybe send
				// yourself an email
				$error = $e->getMessage();
			} catch (Exception $e) {
				// Something else happened, completely unrelated to Stripe
				$error = $e->getMessage();
			}
			
			doWooStripeLOG(print_r($error, true));
			
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
	
}

?>
