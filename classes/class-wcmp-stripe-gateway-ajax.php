<?php
class WCMp_Stripe_Gateway_Ajax {

	public function __construct() {
		add_action('wp', array(&$this, 'demo_ajax_method'));
		add_action('wp_ajax_reset_all_card_stripe', array($this,'reset_all_card_stripe'));
		add_action('wp_ajax_nopriv_reset_all_card_stripe', array($this,'reset_all_card_stripe'));
	}
	
	public function reset_all_card_stripe(){
		$current_user = wp_get_current_user();
		$user_id = $_POST['user_id'];
		$meta_key = "_stripe_customer_id";		
		if(delete_user_meta(  $current_user->ID, $meta_key ))	{
			echo $user_id;
		}
		die;
	}

	public function demo_ajax_method() {
	  // Do your ajx job here
	  
	}

}
