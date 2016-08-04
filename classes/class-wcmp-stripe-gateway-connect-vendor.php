<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCMp_Stripe_Gateway_Connect_Vendor {
	
	public function __construct() {
		
		//$wcmp_stripe_gateway_settings = get_stripe_gateway_settings('dc_wcmp_stripe_gateway_general_settings_name');
		//edited 29/06/16
		//$wcmp_stripe_gateway_settings = get_wcmp_stripe_gateway_settings('is_enable_stripe_connect','payment','stripe_gateway');
		//if( isset($wcmp_stripe_gateway_settings) && !empty($wcmp_stripe_gateway_settings) ) {
			//$is_enable_stripe_connect = isset($wcmp_stripe_gateway_settings['is_enable_stripe_connect']) ? $wcmp_stripe_gateway_settings['is_enable_stripe_connect'] : '';
		$is_enable_stripe_connect = get_wcmp_stripe_gateway_settings('is_enable_stripe_connect','payment','stripe_gateway');
		if( $is_enable_stripe_connect == 'Enable' ) {
			// Connect Button Edit User Page
			add_filter( 'show_user_profile', array( $this, 'vendor_stripe_connect' ), 20 );
			add_filter( 'edit_user_profile', array( $this, 'vendor_stripe_connect' ), 20 );
			
			// Connect Button Vendor Shop Page
			add_action( 'other_exta_field_dcmv', array($this, 'vendor_stripe_connect') );

			// Add stripe in the payment mode list
			add_filter( 'automatic_payment_method', array( $this, 'admin_stripe_payment_mode' ), 10 );

			$this->payment_admin_settings = get_option('wcmp_payment_settings_name');
			if(isset($this->payment_admin_settings['wcmp_disbursal_mode_admin']) &&  $this->payment_admin_settings['wcmp_disbursal_mode_admin'] = 'Enable') {
				add_filter( 'wcmp_vendor_payment_mode', array( $this, 'vendor_stripe_payment_mode' ), 10 );
			}
		}
		//}
		
		// Disconnect Vendor stripe account
		add_action( 'wp', array( $this, 'disconnect_stripe_account' ) );
		add_action( 'admin_init', array( $this, 'disconnect_stripe_account' ) );

	}

	function admin_stripe_payment_mode($arg) {
		global $WCMp;
		$admin_payment_mode_select = array_merge($arg, array('stripe_masspay' => __('Stripe Masspay', $WCMp->text_domain), 'stripe_adaptive' => __('Stripe Adaptive', $WCMp->text_domain)));
		return $admin_payment_mode_select;
	}

	function vendor_stripe_payment_mode($arg) {
		global $WCMp;
    	$payment_mode = array();
    	if(isset($this->payment_admin_settings['payment_method_stripe_masspay']) &&  $this->payment_admin_settings['payment_method_stripe_masspay'] = 'Enable') {
    		$payment_mode['stripe_masspay'] = __('Stripe Masspay', $WCMp->text_domain);
    	}
    	if(isset($this->payment_admin_settings['payment_method_stripe_adaptive']) &&  $this->payment_admin_settings['payment_method_stripe_adaptive'] = 'Enable') {
    		$payment_mode['stripe_adaptive'] = __('Stripe Adaptive', $WCMp->text_domain);
    	}
		$vendor_payment_mode_select = array_merge( $arg, $payment_mode );
		return $vendor_payment_mode_select;
	}
	
	/**
	 * This will connect a vendor's stripe account with marketplace
	 */
	function vendor_stripe_connect($user = '') { 
		global $WCMp_Stripe_Gateway;
		
		if( empty($user) ) {
			$user = wp_get_current_user();
		}
		
		$user_id = $user->ID;
		$vendor = get_wcmp_vendor( $user_id );
		
		if( $vendor ) {
			$stripe_settings = get_option('woocommerce_stripe_settings');
                        
			if( isset($stripe_settings) && !empty($stripe_settings) ) {
                                if(isset($stripe_settings['enabled']) && $stripe_settings['enabled'] == 'no') return;
				$testmode = $stripe_settings['testmode'] === "yes" ? true : false;
				//$client_id = $stripe_settings['client_id'] ? $stripe_settings['client_id'] : '';
				$client_id = $testmode ?  $stripe_settings['test_client_id'] : $stripe_settings['client_id'];
				$secret_key = $testmode ? $stripe_settings['test_secret_key'] : $stripe_settings['secret_key'];
				
				if( isset($client_id) && isset($secret_key) ) {
					
					if (isset($_GET['code'])) { // Redirect w/ code
						$code = $_GET['code'];
						
						if( !is_user_logged_in() ) {
							if( isset($_GET['state']) ) {
								$user_id = $_GET['state'];
							}
						}
						
						$token_request_body = array(
							'grant_type' => 'authorization_code',
							'client_id' => $client_id,
							'code' => $code,
							'client_secret' => $secret_key
						);
						
						$req = curl_init('https://connect.stripe.com/oauth/token');
						curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($req, CURLOPT_POST, true );
						curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($token_request_body));
						curl_setopt($req, CURLOPT_SSL_VERIFYPEER, false);
						curl_setopt($req, CURLOPT_SSL_VERIFYHOST, 2);
						curl_setopt($req, CURLOPT_VERBOSE, true);
						
						// TODO: Additional error handling
						$respCode = curl_getinfo($req, CURLINFO_HTTP_CODE);
						$resp = json_decode(curl_exec($req), true);
						curl_close($req);
						
						$vendor_connected = update_user_meta( $user_id, 'vendor_connected', 1 );
						$vendor_connected = update_user_meta( $user_id, 'admin_client_id', $client_id );
						$vendor_connected = update_user_meta( $user_id, 'access_token', $resp['access_token'] );
						$vendor_connected = update_user_meta( $user_id, 'refresh_token', $resp['refresh_token'] );
						$vendor_connected = update_user_meta( $user_id, 'stripe_publishable_key', $resp['stripe_publishable_key'] );
						$vendor_connected = update_user_meta( $user_id, 'stripe_user_id', $resp['stripe_user_id'] );
						
						if( $vendor_connected ) {
							update_user_meta( $user_id, 'vendor_connected', 1 );
							?>
							<form action="" method="POST">
								<table class="form-table">
									<tbody>
										<tr>
											<th>
												<label><?php _e( 'Stripe', $WCMp_Stripe_Gateway->text_domain );?></label>
											</th>
											<td>
												<label><?php _e( 'You are connected with Stripe', $WCMp_Stripe_Gateway->text_domain );?></label>
											</td>
										</tr>
										<tr>
											<th></th>
											<td>
												<input type="submit" class="button" name="disconnect_stripe" value="Disconnect Stripe Account" />
											</td>
										</tr>
									</tbody>
								</table>
							</form>
							<?php
						} else {
							update_user_meta( $user_id, 'vendor_connected', 0 );
							?>
							<form action="" method="POST">
								<table class="form-table">
									<tbody>
										<tr>
											<th>
												<label><?php _e( 'Stripe', $WCMp_Stripe_Gateway->text_domain );?></label>
											</th>
											<td>
												<label><?php _e( 'Please Retry!!!', $WCMp_Stripe_Gateway->text_domain );?></label>
											</td>
										</tr>
									</tbody>
								</table>
							</form>
							<?php
						}
					} else if (isset($_GET['error'])) { // Error
						update_user_meta( $user_id, 'vendor_connected', 0 );
						?>
						<table class="form-table">
							<tbody>
								<tr>
									<th>
										<label><?php _e( 'Stripe', $WCMp_Stripe_Gateway->text_domain );?></label>
									</th>
									<td>
										<label><?php _e( 'Please Retry!!!', $WCMp_Stripe_Gateway->text_domain );?></label>
									</td>
								</tr>
							</tbody>
						</table>
						<?php
					} else {
						$vendor_connected = get_user_meta( $user_id, 'vendor_connected', true );
						$connected = true;
						
						if(  isset($vendor_connected) && $vendor_connected == 1 ) {
							$admin_client_id = get_user_meta( $user_id, 'admin_client_id', true );
							
							if( $admin_client_id == $client_id ) {
								
								?>
								<table class="form-table">
									<tbody>
										<tr>
											<th>
												<label><?php _e( 'Stripe', $WCMp_Stripe_Gateway->text_domain );?></label>
											</th>
											<td>
												<label><?php _e( 'You are connected with Stripe', $WCMp_Stripe_Gateway->text_domain );?></label>
											</td>
										</tr>
										<tr>
											<th></th>
											<td>
												<input type="submit" class="button" name="disconnect_stripe" value="Disconnect Stripe Account" />
											</td>
										</tr>
									</tbody>
								</table>
								<?php
							} else {
								$connected = false;
							}
						} else {
							$connected = false;
						}
						
						if( !$connected ) {
							
							$status = delete_user_meta( $user->ID, 'vendor_connected' );
							$status = delete_user_meta( $user->ID, 'admin_client_id' );
							
							// Show OAuth link
							$authorize_request_body = array(
								'response_type' => 'code',
								'scope' => 'read_write',
								'client_id' => $client_id,
								'state' => $user->ID
							);
							$url = 'https://connect.stripe.com/oauth/authorize?' . http_build_query($authorize_request_body);
							$stripe_connect_url = $WCMp_Stripe_Gateway->plugin_url . 'assets/images/blue-on-light.png';
							
							if( ! $status ) {
								?>
								<table class="form-table">
									<tbody>
										<tr>
											<th>
												<label><?php _e( 'Stripe', $WCMp_Stripe_Gateway->text_domain ); ?></label>
											</th>
											<td><?php _e( 'You are not connected with stripe.', $WCMp_Stripe_Gateway->text_domain ); ?></td>
										</tr>
										<tr>
											<th></th>
											<td>
												<a href=<?php echo $url; ?> target="_blank"><img src="<?php echo $stripe_connect_url; ?>" /></a>
											</td>
										</tr>
									</tbody>
								</table>
								<?php
							} else {
								?>
								<table class="form-table">
									<tbody>
										<tr>
											<th>
												<label><?php _e( 'Stripe', $WCMp_Stripe_Gateway->text_domain ); ?></label>
											</th>
											<td><?php _e( 'Please connected with stripe again.', $WCMp_Stripe_Gateway->text_domain ); ?></td>
										</tr>
										<tr>
											<th></th>
											<td>
												<a href=<?php echo $url; ?> target="_blank"><img src="<?php echo $stripe_connect_url; ?>" /></a>
											</td>
										</tr>
									</tbody>
								</table>
								<?php
							}
						}
					}
				}
			}
		} else {
			?>
				<div><?php _e( 'You are not a Vendor. Please Login as a Vendor.', $WCMp_Stripe_Gateway->text_domain );?></div>
			<?php
		}
	}
	
	function disconnect_stripe_account() {
		global $WCMp_Stripe_Gateway;
		
		if( isset($_POST['disconnect_stripe']) ) {
		
			if( empty($user) ) {
				$user = wp_get_current_user();
			}
			
			$user_id = $user->ID;
			$vendor = get_wcmp_vendor( $user_id );
			
			if( $vendor ) {
				delete_user_meta( $user_id, 'vendor_connected' );
				delete_user_meta( $user_id, 'admin_client_id' );
				delete_user_meta( $user_id, 'access_token' );
				delete_user_meta( $user_id, 'refresh_token' );
				delete_user_meta( $user_id, 'stripe_publishable_key' );
				delete_user_meta( $user_id, 'stripe_user_id' );
			}
		}
		
	}
	
}
?>
