<?php
/**
 * Plugin Name: Nomad WooCommerce Shipping
 * Plugin URI: https://www.cromexgroup.us/
 * Description:This is the Nomad shipping from Cromexgroup
 * Author: Cromexgroup
 * Author URI: https://www.cromexgroup.us/
 * Version: 1.0.0
 * Text Domain: nomad-shipping
 * Domain Path: languages/
 *
 * @package Nomad WooCommerce Shipping
 */

/*----- Preventing Direct Access -----*/
defined( 'ABSPATH' ) || exit;
require_once plugin_dir_path(__FILE__) . 'includes/order_status_api.php';
require_once plugin_dir_path(__FILE__) . 'includes/order_status.php';
global $NomadSettings;
$NomadSettings = get_option( 'woocommerce_nomad-shipping-method_settings' );
/**
 * Include order return managemen files.
 */
if(isset($NomadSettings['order_retun']) && $NomadSettings['order_retun']=="yes"){
    require_once plugin_dir_path(__FILE__) . 'includes/class-return-requests-list-table.php';
    require_once plugin_dir_path(__FILE__) . 'includes/order-return-management.php';
}
       
/**
 * Include your shipping file.
 */
function nomad_include_shipping_method() {
    require_once 'nomad-class-shipping-method.php';
    $method_instance = new NOMAD_Cromexgroup_Shipping_Method;
    $order_retun = isset($method_instance->settings['order_retun'])?$method_instance->settings['order_retun']:0;
    if($order_retun){
        
    }
}
add_action( 'woocommerce_shipping_init', 'nomad_include_shipping_method' );


/**
 * Add Your shipping method class in the shipping list
 */
function nomad_add_shipping_method( $methods ) {
  $methods[] = 'NOMAD_Cromexgroup_Shipping_Method';
  return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'nomad_add_shipping_method' );

add_action( 'woocommerce_thankyou', function( $order_id ){
    $order = new WC_Order( $order_id );
    $method_instance = new NOMAD_Cromexgroup_Shipping_Method;
    $title = $method_instance->settings['title'];
    $apiurl = $method_instance->settings['apiurl'];
    $apikey = $method_instance->settings['apikey'];

    if ( $order->get_status() != 'failed' ) {
        if($order->get_shipping_method()==$title && empty(get_post_meta( $order_id, '_nomad_order_id', true ))){
            $url = $apiurl."/api/orders/place";
            $data["order_id"] = $order_id;
            $customer = [
                    "first_name"=>$order->get_billing_first_name(),
                    "last_name"=>$order->get_billing_last_name(),
                    "email"=>$order->get_billing_email(),
                    "phone"=>$order->get_billing_phone()
                    ];
            $data['customer'] = $customer;  
            $more_address_info = get_post_meta( $order_id, '_nomad_more_address_info', true );
    	    $_destination_address = [
    	        "contact_person_name"=> $order->get_shipping_first_name()." ".$order->get_shipping_last_name(),
        		"contact_person_number"=> $order->get_billing_phone(),
        		"country"=>$order->get_shipping_country(),
        		"state"=>$order->get_shipping_state(),
        		"postcode"=>$order->get_shipping_postcode(),
        		"city"=>$order->get_shipping_city(),
        		"address"=>$order->get_shipping_address_1(),
        		"address1"=>$order->get_shipping_address_2(),
        		"address2"=>"",
        		"more_address_info"=>$more_address_info
    	    ];
    	    $data['destination_address'] = $_destination_address;
    	    $_items = [];
            foreach ($order->get_items() as $item_id => $item ) {
               $product = $item->get_product();
               $dimensions = $product->get_dimensions(false);
               $image_id  = $product->get_image_id();
               $image_url = wp_get_attachment_image_url( $image_id, 'full' );
               $weight = $product->get_weight()."<br>";
               $length = isset($dimensions['length'])?$dimensions['length']:0;
               $width = isset($dimensions['width'])?$dimensions['width']:0;
               $height = isset($dimensions['height'])?$dimensions['height']:0;
               $_items[] = ["name"=> $item->get_name(),"price"=> $product->get_price(),"quantity"=> $item->get_quantity(),"image_url"=> $image_url,"dimensions"=> ["length_cm"=> $length,"width_cm"=> $width,"height_cm"=> $height],"weight_kg"=> $weight];
            }
            $data['items'] = $_items;
            $response = wp_remote_post(
                $url,
                array(
                    'body' => json_encode($data),
                    'headers' => array(
                    'Authorization' => 'Bearer ' .$apikey,
                                       'Content-Type' => 'application/json',
                    ),
                )
            );
            //echo "<pre>";print_r($data);echo "</pre>";
            $apiBody = json_decode( wp_remote_retrieve_body( $response ) );
            //echo "<pre>";print_r($apiBody);echo "</pre>";
            if($apiBody->status==1){
                update_post_meta( $order->get_id(), '_nomad_order_id', $apiBody->order_id );
            }
        }
    }
});

add_action('woocommerce_after_order_notes', 'custom_checkout_field_for_flat_rate');

function custom_checkout_field_for_flat_rate($checkout) {
    echo '<div id="custom_nomad_field" style="display:none;">';
        woocommerce_form_field('nomad_more_address_info', array(
            'type'        => 'text',
            'class'       => array('form-row-wide'),
            'label'       => __('More Address Info/Land Mark'),
            'placeholder' => __('More address info for Nomad shipping'),
            'required'      => true,
        ), $checkout->get_value('nomad_more_address_info'));
    echo '</div>';
}
add_action('wp_footer', 'custom_checkout_nomad_script');

function custom_checkout_nomad_script() {
    if (is_checkout() && !is_wc_endpoint_url()) {
        ?>
        <script>
        jQuery(function($) {
            function toggleNomadRateField() {
                var chosen = $('input[name^="shipping_method"]:checked').val();
                if (chosen && chosen.indexOf('nomad-shipping-method') !== -1) {
                    $('#custom_nomad_field').show();
                } else {
                    $('#custom_nomad_field').hide();
                }
            }

            toggleNomadRateField(); // Run on page load

            $(document.body).on('change', 'input[name^="shipping_method"]', function() {
                toggleNomadRateField();
            });
        });
        </script>
        <?php
    }
}

add_action('woocommerce_checkout_process', 'custom_validate_nomad_field');

function custom_validate_nomad_field() {
    $chosen_methods = WC()->session->get('chosen_shipping_methods');
    if (!empty($chosen_methods) && strpos($chosen_methods[0], 'nomad-shipping-method') !== false) {
        if (empty($_POST['nomad_more_address_info'])) {
            wc_add_notice(__('Please enter the more address info.'), 'error');
        }
    }
}
add_action('woocommerce_checkout_update_order_meta', 'custom_save_nomad_field');

function custom_save_nomad_field($order_id) {
    if (!empty($_POST['nomad_more_address_info'])) {
        update_post_meta($order_id, '_nomad_more_address_info', sanitize_text_field($_POST['nomad_more_address_info']));
    }
}
add_action( 'woocommerce_after_add_to_cart_button', 'nomad_block_after_add_to_cart' );

function nomad_block_after_add_to_cart() {
    global $NomadSettings;
    if(isset($NomadSettings['show_promo_product']) && $NomadSettings['show_promo_product']=="yes"){
        $logo_url = plugins_url( 'assets/images/nomad-logo.png', __FILE__ );
        echo '<div class="nomad-after-cart-block" style="margin-top:20px; padding:15px; background:#f8f8f8; border:1px solid #ddd;font-size: 16px;border-radius: 8px;box-shadow: 0 2px 4px rgba(0,0,0,0.05);font-weight: 600;">';
        echo '<div><img src="' . esc_url( $logo_url ) . '" alt="Nomadwear" style="max-width:100px; margin-right:10px; vertical-align:middle;">';
        echo 'Buy Now, Get It Today!</div>';
        echo '</div>';
    }
}
add_action('woocommerce_after_cart_totals', 'nomad_block_after_proceed_to_checkout');

function nomad_block_after_proceed_to_checkout() {
    global $NomadSettings;
    //echo "<pre>";print_r($NomadSettings);echo "</pre>";
    if(isset($NomadSettings['show_promo_cart']) && $NomadSettings['show_promo_cart']=="yes"){
        $logo_url = plugins_url( 'assets/images/nomad-logo.png', __FILE__ );
        echo '<div class="nomad-after-cart-block" style="margin-top:20px; padding:15px; background:#f8f8f8; border:1px solid #ddd;font-size: 16px;border-radius: 8px;box-shadow: 0 2px 4px rgba(0,0,0,0.05);font-weight: 600;">';
        echo '<div><img src="' . esc_url( $logo_url ) . '" alt="Nomadwear" style="max-width:100px; margin-right:10px; vertical-align:middle;">';
        echo 'Get it with Nomad today!</div>';
        echo '</div>';
    }
}

add_action('woocommerce_review_order_before_submit', 'nomad_block_before_place_order', 10);

function nomad_block_before_place_order() {
     global $NomadSettings;
    //echo "<pre>";print_r($NomadSettings);echo "</pre>";
    if(isset($NomadSettings['show_promo_checkout']) && $NomadSettings['show_promo_checkout']=="yes"){
        $logo_url = plugins_url( 'assets/images/nomad-logo.png', __FILE__ );
        echo '<div class="nomad-after-cart-block" style="margin-top:20px; padding:15px; background:#f8f8f8; border:1px solid #ddd;font-size: 16px;border-radius: 8px;box-shadow: 0 2px 4px rgba(0,0,0,0.05);font-weight: 600;">';
        echo '<div><img src="' . esc_url( $logo_url ) . '" alt="Nomadwear" style="max-width:100px; margin-right:10px; vertical-align:middle;">';
        echo 'Get it with Nomad today!</div>';
        echo '</div>';
    }
}