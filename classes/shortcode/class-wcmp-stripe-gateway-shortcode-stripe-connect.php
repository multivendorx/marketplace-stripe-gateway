<?php
class WCMp_Stripe_Connect_Shortcode {

	public function __construct() {

	}

	/**
	 * Output the Stripe Connect Shortcode.
	 *
	 * @access public
	 * @param array $atts
	 * @return void
	 */
	public function output( $attr ) {
		global $WCMp_Stripe_Gateway;
		$WCMp_Stripe_Gateway->nocache();
		$vendor_connected = false;
		
		$user = wp_get_current_user();
		$user_id = $user->ID;
		
		$vendor = get_wcmp_vendor( $user_id );
		
		if( $vendor ) {
			
			$stripe_settings = get_option('woocommerce_stripe_settings');
			
			if( isset($stripe_settings) && !empty($stripe_settings) ) {
			
				$testmode = $stripe_settings['testmode'] === "yes" ? true : false;
				$client_id = $stripe_settings['client_id'] ? $stripe_settings['client_id'] : '';
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
						
						$vendor_connected = update_user_meta( $user_id, 'access_token', $resp['access_token'] );
						$vendor_connected = update_user_meta( $user_id, 'refresh_token', $resp['refresh_token'] );
						$vendor_connected = update_user_meta( $user_id, 'stripe_publishable_key', $resp['stripe_publishable_key'] );
						$vendor_connected = update_user_meta( $user_id, 'stripe_user_id', $resp['stripe_user_id'] );
						
						if( $vendor_connected ) {
							update_user_meta( $user_id, 'vendor_connected', 1 );
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
								</tbody>
							</table>
							<?php
						} else {
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
						}
						
					} else if (isset($_GET['error'])) { // Error
						echo $_GET['error_description'];
					} else {
						$vendor_connected = get_user_meta( $user_id, 'vendor_connected', true );
						
						if( $vendor_connected == 0 ) {
							
							// Show OAuth link
							$authorize_request_body = array(
								'response_type' => 'code',
								'scope' => 'read_write',
								'client_id' => $client_id,
								'state' => $user_id
							);
							$url = 'https://connect.stripe.com/oauth/authorize?' . http_build_query($authorize_request_body);
							$stripe_connect_url = $WCMp_Stripe_Gateway->plugin_url . 'assets/images/blue-on-light.png';
							?>
							<table class="form-table">
								<tbody>
									<tr>
										<th>
											<label><?php _e( 'Stripe', $WCMp_Stripe_Gateway->text_domain );?></label>
										</th>
										<td>
											<a href=<?php echo $url; ?> target="_blank"><img src="<?php echo $stripe_connect_url; ?>" /></a>
										</td>
									</tr>
								</tbody>
							</table>
							<?php
						} else if( $vendor_connected == 1 ) {
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
								</tbody>
							</table>
							<?php
						}
						
					}
				}
			}
		} else {
			?>
				<div><?php _e( 'You are not a Vendor. Please Login as a Vendor.', $WCMp_Stripe_Gateway->text_domain );?></div>
			<?php
		}
		
		do_action('wcmp-gateway-stripe_template_stripe_connect');

	}
}
