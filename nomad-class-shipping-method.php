<?php

defined( 'ABSPATH' ) || exit;

class NOMAD_Cromexgroup_Shipping_Method extends WC_Shipping_Method {

  /**
   * Shipping class
   */
  public function __construct() {

    // These title description are display on the configuration page
    $this->id = 'nomad-shipping-method';
    $this->method_title = esc_html__('Nomad Shipping', 'nomad-shipping' );
    $this->method_description = esc_html__('Nomad WooCommerce Shipping', 'nomad-shipping' );

    // Run the initial method
    $this->init();
    $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
     $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Nomad Shipping', 'nomad-shipping');

   }

   /**
    ** Load the settings API
    */
   public function init() {
     // Load the settings API
     $this->init_settings();

     // Add the form fields
     $this->init_form_fields();
     add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
   }

   public function init_form_fields() {

     $form_fields = array(

       'enabled' => array(
          'title'   => esc_html__('Enable/Disable', 'nomad-shipping' ),
          'type'    => 'checkbox',
          'label'   => esc_html__('Enable this shipping method', 'nomad-shipping'  ),
          'default' => 'no'
       ),

       'title' => array(
          'title'       => esc_html__('Method Title', 'nomad-shipping' ),
          'type'        => 'text',
          'description' => esc_html__('Enter the method title', 'nomad-shipping'  ),
          'default'     => esc_html__('', 'nomad-shipping' ),
          'desc_tip'    => true,
       ),
       'apiurl' => array(
          'title'       => esc_html__('API Url', 'nomad-shipping' ),
          'type'        => 'text',
          'description' => esc_html__('Enter the api url', 'nomad-shipping'  ),
          'default'     => esc_html__('', 'nomad-shipping' ),
          'desc_tip'    => true,
       ),
       'apikey' => array(
          'title'       => esc_html__('API Key', 'nomad-shipping' ),
          'type'        => 'text',
          'description' => esc_html__('Enter the API Key', 'nomad-shipping'  ),
          'default'     => esc_html__('', 'nomad-shipping' ),
          'desc_tip'    => true,
       ),

       'description' => array(
          'title'       => esc_html__('Description', 'nomad-shipping' ),
          'type'        => 'textarea',
          'description' => esc_html__('Enter the Description', 'nomad-shipping'  ),
          'default'     => esc_html__('', 'nomad-shipping' ),
          'desc_tip'    => true
       ),
       'order_retun' => array(
          'title'   => esc_html__('Enable/Disable', 'nomad-shipping' ),
          'type'    => 'checkbox',
          'label'   => esc_html__('Enable order return with Nomad', 'nomad-shipping'  ),
          'default' => 'no'
       ),
       'show_promo_product' => array(
          'title'   => esc_html__('Enable/Disable', 'nomad-shipping' ),
          'type'    => 'checkbox',
          'label'   => esc_html__('Show Nomad log on product page', 'nomad-shipping'  ),
          'default' => 'yes'
       ),
       'show_promo_cart' => array(
          'title'   => esc_html__('Enable/Disable', 'nomad-shipping' ),
          'type'    => 'checkbox',
          'label'   => esc_html__('Show Nomad log on cart page', 'nomad-shipping'  ),
          'default' => 'yes'
       ),
       'show_promo_checkout' => array(
          'title'   => esc_html__('Enable/Disable', 'nomad-shipping' ),
          'type'    => 'checkbox',
          'label'   => esc_html__('Show Nomad log on checkout page', 'nomad-shipping'  ),
          'default' => 'yes'
       ),
       /*'cost' => array(
          'title'       => esc_html__('Cost', 'nomad-shipping' ),
          'type'        => 'number',
          'description' => esc_html__('Add the method cost', 'nomad-shipping'  ),
          'default'     => esc_html__('', 'nomad-shipping' ),
          'desc_tip'    => true
       )*/
     );

      $this->form_fields = $form_fields;
   }

   /**
    ** Calculate Shipping rate
    */
   public function calculate_shipping( $package = array() ) {
        
        $userId = get_current_user_id();
        $customerName = "";
        $customerPhone = "";
        if($userId){
            $user_info = get_userdata($userId);
            $customerName = $user_info->user_firstname." ".$user_info->user_lastname;
            $customerPhone = get_user_meta( $userId, 'billing_phone', true );
        }
        //echo "<pre>";print_r($package['destination']);echo "</pre>";
        $address = !empty($package['destination']['address'])?$package['destination']['address']:"Test";
        $data = [];
        $postcode = isset($package['destination']['postcode']) && !empty($package['destination']['postcode'])?$package['destination']['postcode']:$package['destination']['city'];
        //$postcode = "Dubai";
	    $_destination_address = [
	        "contact_person_name"=> $customerName,
    		"contact_person_number"=> $customerPhone,
    		"country"=>$package['destination']['country'],
    		"state"=>$package['destination']['state'],
    		"postcode"=>$postcode,
    		"city"=>$package['destination']['city'],
    		"address"=>$package['destination']['address_1'],
    		"address1"=>$package['destination']['address_2'],
    		"address2"=>""
	    ];
        $items = "";
        $data["destination_address"] = $_destination_address;
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            //echo "<pre>";print_r($cart_item);echo "</pre>";
           $product = $cart_item['data'];
           //$product_id = $cart_item['product_id'];
           //$variation_id = $cart_item['variation_id'];
           $quantity = $cart_item['quantity'];
          
           $price = $cart_item['line_total'];
           //$subtotal = WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] );
           //$link = $product->get_permalink( $cart_item );
           $dimensions = $product->get_dimensions(false);
           $image_id  = $product->get_image_id();
           $image_url = wp_get_attachment_image_url( $image_id, 'full' );
           $weight = $product->get_weight()."<br>";
           $length = isset($dimensions['length'])?$dimensions['length']:0;
           $width = isset($dimensions['width'])?$dimensions['width']:0;
           $height = isset($dimensions['height'])?$dimensions['height']:0;
           $_items[] = ["name"=> $product->get_title(),"price"=> $price,"quantity"=> $quantity,"image_url"=> $image_url,"dimensions"=> ["length_cm"=> $length,"width_cm"=> $width,"height_cm"=> $height],"weight_kg"=> $weight];
        }
       // $items = rtrim($items,',');
        //$_items = json_encode($_items);
        //$_destination_address = json_encode($_destination_address);
        //$data["destination_address"] = $_destination_addres;
        $data["items"] = $_items;
        $apiResponse = wp_remote_post( $this->settings['apiurl']."/api/rates/get", array(
            'body'    => json_encode($data),
            'headers' => array(
            'Authorization' => 'Bearer ' .$this->settings['apikey'],
                               'Content-Type' => 'application/json',
            ),
        ) ); 
        $apiBody = json_decode( wp_remote_retrieve_body( $apiResponse ) );
        //echo "<pre>";print_r($data);echo "</pre>";
        //echo "<pre>";print_r(json_encode($data));echo "</pre>";
        //echo "<pre>";print_r($apiBody);echo "</pre>";
        // $apiBody->status;
        if($apiBody->status==1 && (!is_null($apiBody->data->total_flat_rate))){
            $this->add_rate( array(
                'id'     => $this->id,
                'label'  => $this->settings['title'],
                'cost'   => $apiBody->data->total_flat_rate
            ));
        }
   }

}