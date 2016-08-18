<?php
class WCMp_Payment_Stripe_Gateway_Settings_Gneral {
  /**
   * Holds the values to be used in the fields callbacks
   */
  private $options;
  
  private $tab;
  
  private $subsection;

  /**
   * Start up
   */
  public function __construct($tab,$subsection) {
    $this->tab = $tab;
    $this->subsection = $subsection;
    $this->options = get_option( "wcmp_{$this->tab}_{$this->subsection}_settings_name" );
    $this->settings_page_init();
  }
  
  /**
   * Register and add settings
   */
  public function settings_page_init() {
    global $WCMp,$WCMp_Stripe_Gateway;
    
    $settings_tab_options = array("tab" => "{$this->tab}",
                                  "ref" => &$this,
                                  "subsection" => "{$this->subsection}",
                                  "sections" => array(
                                                      "default_settings_section" => array("title" =>  __('', $WCMp_Stripe_Gateway->text_domain), // Section one
                                                                                          "fields" => array("is_enable_stripe_connect" => array('title' => __('Enable Stripe Connect', $WCMp_Stripe_Gateway->text_domain), 'type' => 'checkbox', 'value' => 'Enable') ), // Checkbox
                                                                                          )
                                                      )      
                                  ); 
    
    $WCMp->admin->settings->settings_field_withsubtab_init(apply_filters("settings_{$this->tab}_{$this->subsection}_tab_options", $settings_tab_options));
  }

  /**
   * Sanitize each setting field as needed
   *
   * @param array $input Contains all settings fields as array keys
   */
  public function wcmp_payment_stripe_gateway_settings_sanitize( $input ) {
    global $WCMp_Stripe_Gateway;
    $new_input = array();
    
    $hasError = false;
    
    if( isset( $input['is_enable_stripe_connect'] ) )
      $new_input['is_enable_stripe_connect'] = sanitize_text_field( $input['is_enable_stripe_connect'] );

    if(!$hasError) {
			add_settings_error(
			 "wcmp_{$this->tab}_{$this->subsection}_settings_name",
			 esc_attr( "wcmp_{$this->tab}_{$this->subsection}_settings_admin_updated" ),
			 __('Stripe Gateway Settings Updated', $WCMp_Stripe_Gateway->text_domain),
			 'updated'
			);
    }
    return apply_filters("settings_{$this->tab}_{$this->subsection}_tab_new_input", $new_input , $input);
  }

   
  /** 
   * Print the Section text
   */
  public function default_settings_section_info() {
    global $WCMp_Stripe_Gateway;
    printf( __( '', $WCMp_Stripe_Gateway->text_domain ) );
  }
  
   /** 
   * Print the Section text
   */
  public function WCMp_Stripe_Gateway_store_policies_admin_details_section_info() {
    global $WCMp_Stripe_Gateway;
   
  }
  
}
