<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Stripe\Stripe;
use Stripe\Transfer;

/**
 * @class 		WCMp Stripe Masspay Class
 *
 * @version		2.2.0
 * @package		WCMp
 * @author 		DualCube
 */ 
class WCMp_Stripe_Gateway_Masspay {
	
	public $is_masspay_enable;
	public $payment_schedule;
	public $api_username;
	public $api_pass;
	public $api_signature;	
	public $test_mode;
	
	public function __construct() {
                add_action('wcmp_payment_cron_stripe_masspay', array($this, 'do_stripe_masspay'));			
	}
	
	/**
	 * Init Stripe Mass pay api
	 */
	public function call_masspay_api($receiver_information) {
		global $WCMp_Stripe_Gateway;
                doWooStripeLOG(json_encode($receiver_information));
		$stripe_settings = get_option('woocommerce_stripe_settings');
		
		if( isset($stripe_settings) && !empty($stripe_settings) ) {
			$testmode = $stripe_settings['testmode'] === "yes" ? true : false;
			$secret_key = $testmode ? $stripe_settings['test_secret_key'] : $stripe_settings['secret_key'];
			
			try{
				Stripe::setApiKey($secret_key);
				
				$transfer_arg = array(
					'amount' => $receiver_information['total_commission'],
					'currency' => $receiver_information['currency'],
					'destination' => $receiver_information['stripe_user_id']
				);
				
				$transfer = Transfer::create($transfer_arg);
                                doWooStripeLOG(json_encode($transfer));
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
	 * Process Stripe masspay 
	 */
	public function do_stripe_masspay($data=array()) {
            global $WCMp_Stripe_Gateway;
            $vendors_data = array();
            $commissions_data = isset($data['payment_data']) ? $data['payment_data'] : array();
            $transactions_data = isset($data['transaction_data']) ? $data['transaction_data'] : array();
            //doWooStripeLOG(json_encode($commissions_data));
            foreach( $commissions_data as $commission_data ) {
                $vendor_obj = get_wcmp_vendor_by_term($commission_data['vendor_id']);
                $vendor_id = $vendor_obj->id;
                $vendor_name = $vendor_obj->user_data->data->user_login;
                $total = $commission_data['total'];
                $currency = $commission_data['currency'];
                $payout_note = $commission_data['payout_note'];
                    $check_vendor_connected = get_user_meta( $vendor_id, 'vendor_connected', true );
                    if( $check_vendor_connected = 1 ) {
                        $total_stripe_ammount = intval($total*100);
                            $vendor_stripe_user_id = get_user_meta( $vendor_id, 'stripe_user_id', true );
                            $transfer_details = array(
                                    'total_commission' => $total_stripe_ammount,
                                    'currency' => $currency,
                                    'payout_note' => $payout_note,
                                    'stripe_user_id' => $vendor_stripe_user_id
                            );

                            $transfer = $this->call_masspay_api($transfer_details);
                            if( isset($transfer) && !empty($transfer) ) {
                                foreach($transactions_data[$commission_data['vendor_id']]['commission_detail'] as $commission_id=>$order_id) {
                                    $order = new WC_Order($order_id);
                                    update_post_meta( $commission_id, '_paid_status', 'paid' );
                                    $customer_array = $transfer->__toArray(true);

                                    $transfer_id = $customer_array['id'];
                                    $commission_vendor_term_id = get_post_meta( $commission_id, '_commission_vendor', true );
                                    add_post_meta( $order_id, '_stripe_vendor_transfer_id_'.$commission_vendor_term_id, $transfer_id, true );

                                    // Add order note
                                    $order->add_order_note( sprintf( __( 'Commission transfered to vendor-%s via stripe, amount: %s (Transfer ID: %s)', $WCMp_Stripe_Gateway->text_domain ), $vendor_name, $total_ammount, $transfer_id ) );
                                }

                            } else {
                                    // Add order note when transfer failed
                                    //$order->add_order_note( sprintf( __( 'Commission transfer failed to vendor-%s via stripe', $WCMp_Stripe_Gateway->text_domain ), $vendor_name ) );
                            }
                    } else {
                            // Add order note when vendor is not connected
                            //$order->add_order_note( sprintf( __( 'Commission transfer failed to vendor-%s via stripe. Vendor-%s is not connected with stripe.', $WCMp_Stripe_Gateway->text_domain ), $vendor_name, $vendor_name ) );
                    }
            }
	}
	
	/**
	 * Get Commissions
	 *
	 * @return object $commissions
	 */
	public function get_query_commission() {
		$args = array(
			'post_type' => 'dc_commission',
			'post_status' => array( 'publish', 'private' ),
			'meta_key' => '_paid_status',
			'meta_value' => 'unpaid',
			'posts_per_page' => -1
		);
		$commissions = get_posts( $args );
		return $commissions;
	}
}
?>