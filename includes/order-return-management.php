<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_Order_Return_Management {

    public function __construct() {
        add_action('init', [$this, 'register_return_post_type']);
        add_action('init', [$this, 'register_return_statuses']);
        add_shortcode('return_request_form', [$this, 'render_return_form']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_post_submit_return_request', [$this, 'handle_form_submission']);
        add_action('add_meta_boxes', [$this, 'register_status_metabox']);
        add_action('save_post', [$this, 'save_status_metabox']);
        add_filter('manage_return_request_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_return_request_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        add_action('post_updated', [$this, 'track_status_history'], 10, 3);
        add_action('init', [$this, 'add_order_return_endpoint']);
        //add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'order_return_template']);
        add_action('woocommerce_order_details_after_order_table', [$this, 'add_return_button_to_order_view']);
        add_action('add_meta_boxes', [$this, 'register_items_metabox']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_return_requests_tab']);
        add_action('init', [$this, 'add_return_requests_endpoint']);
        add_action('woocommerce_account_return-requests_endpoint', [$this, 'render_return_requests_tab']);
        add_action('admin_notices', [$this, 'nomad_show_admin_notices']);
    }

    public function register_return_post_type() {
        register_post_type('return_request', [
            'label' => 'Return Requests',
            'public' => false,
            'show_ui' => true,
            'supports' => ['title', 'editor', 'custom-fields'],
            'capability_type' => 'post',
            'menu_position' => 25,
            'menu_icon' => 'dashicons-undo',
            'labels' => [
                'name' => 'Return Requests',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Return Request',
                'edit_item' => 'Edit Return Request',
                'new_item' => 'New Return Request',
                'view_item' => 'View Return Request',
                'search_items' => 'Search Return Requests',
                'not_found' => 'No Return Requests Found',
                'not_found_in_trash' => 'No Return Requests in Trash'
            ],
        ]);
    }

   public function register_return_statuses() {
        $statuses = [
            'return_requested' => 'Return Requested',
            'return_under_review' => 'Under Review',
            'return_approved' => 'Approved',
            'return_rejected' => 'Rejected',
            'return_scheduled' => 'Scheduled for Pickup',
            'return_awaiting_pickup' => 'Awaiting Pickup',
            'return_out_for_pickup' => 'Out for Pickup',
            'return_collected' => 'Item Collected',
            'return_in_transit' => 'In Transit',
            'return_received' => 'Received',
            'return_under_inspection' => 'Under Inspection',
            'return_accepted' => 'Accepted',
            'return_closed' => 'Closed'
        ];

        foreach ($statuses as $slug => $label) {
            register_post_status($slug, [
                'label' => $label,
                'public' => false,
                'internal' => true,
                'label_count' => _n_noop("$label <span class=\"count\">(%s)</span>", "$label <span class=\"count\">(%s)</span>")
            ]);
        }
    }
    public function nomad_show_admin_notices(){
        if ( isset( $_GET['nomad_msg'] ) ) {
            $msg = sanitize_text_field( $_GET['nomad_msg'] );
    
            switch ( $msg ) {
                case 'request_update':
                    echo '<div class="notice notice-success is-dismissible"><p>Request updated successfully!</p></div>';
                    break;
                case 'error':
                    echo '<div class="notice notice-error"><p>There was an error processing your request.</p></div>';
                    break;
            }
        }
    }
    public function render_return_form() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : '';
        $order = wc_get_order($order_id);
    
        ob_start();
    
        if (!$order) {
            echo '<p>Invalid order ID.</p>';
            return ob_get_clean();
        }
        $args = [
            'post_type' => 'return_request',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'order_id',
                    'value' => $order_id,
                    'compare' => '='
                ]
            ]
        ];
        $request = get_posts($args);
        if (!empty($request)) {
            echo '<p>You already requested for retun to this order.</p>';
            return ob_get_clean();
        }
        $current_user = wp_get_current_user();
        ?>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit_return_request">
            <?php wp_nonce_field('return_request_nonce', 'return_request_nonce_field'); ?>
    
            <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="return_user_email">Your Email:<span class="required" aria-hidden="true">*</span></label> 
            <input class="woocommerce-Input woocommerce-Input--text input-text" type="email" value="<?=$current_user->user_email;?>" name="customer_email" required>
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="return_user_items">Select items to return:</label> 
                <?php foreach ($order->get_items() as $item_id => $item): ?>
                    <?php $product = $item->get_product(); ?>
                    <label>
                        <input type="checkbox" name="return_items[]" value="<?php echo esc_attr($item_id); ?>">
                        <?php echo esc_html($product->get_name()); ?> — Qty: <?php echo esc_html($item->get_quantity()); ?>
                    </label>
                <?php endforeach; ?>
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label  for="return_reason">Reason:</label>
                    <select name="reason" class="woocommerce-Select woocommerce-Input--select input-select">
                        <option>Wrong size</option>
                        <option>Wrong item received</option>
                        <option>Item damaged</option>
                        <option>Item defective</option>
                        <option>Item not as described</option>
                        <option>Changed mind</option>
                        <option>Received too late</option>
                        <option>Duplicate item</option>
                        <option>Quality not as expected</option>
                    </select>
                </label>
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label>Comments: </label>
                <textarea name="comments" class="woocommerce-Input woocommerce-Input--text input-text"></textarea>
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label>Upload Images:</label> 
                <input type="file" name="return_images[]" multiple>
            </p>
            <button type="submit" class="woocommerce-Button button wp-element-button">Submit Return Request</button>
        </form>
        <?php
    
        return ob_get_clean();
    }



    public function handle_form_submission() {
        global $NomadSettings;
        $apiurl = $NomadSettings['apiurl'];
        $apikey = $NomadSettings['apikey'];
        //echo "<pre>";print_r($NomadSettings);echo "</pre>";die('here');
        if (!isset($_POST['return_request_nonce_field']) || !wp_verify_nonce($_POST['return_request_nonce_field'], 'return_request_nonce')) {
            wp_die('Security check failed');
        }

        $order_id = sanitize_text_field($_POST['order_id']);
        $email = sanitize_email($_POST['customer_email']);
        $reason = sanitize_text_field($_POST['reason']);
        $return_items = isset($_POST['return_items']) ? array_map('intval', $_POST['return_items']) : [];
        $comments = sanitize_textarea_field($_POST['comments']);
        $status = "return_requested";
        $order = wc_get_order($order_id);
        $_items = [];
        foreach ($order->get_items() as $item_id => $_item) {
            if (in_array($item_id, $return_items)) {
                $product = $_item->get_product();
                $_items[]=["name"=>esc_html($_item->get_name()),"sku"=>esc_html($product->get_sku()),"qty"=>esc_html($_item->get_quantity())];            
            }
        }
        if($reason=="Wrong item received"||$reason=="Item damaged"){$status = "return_approved";}
        $return_images = [];
        if (!empty($_FILES['return_images']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $files = $_FILES['return_images'];
            
            foreach ($files['name'] as $key => $value) {
                if ($files['name'][$key]) {
                    $file = [
                        'name' => $files['name'][$key],
                        'type' => $files['type'][$key],
                        'tmp_name' => $files['tmp_name'][$key],
                        'error' => $files['error'][$key],
                        'size' => $files['size'][$key]
                    ];

                    $upload = wp_handle_upload($file, ['test_form' => false]);
                    if (!isset($upload['error']) && isset($upload['url'])) {
                        $return_images[] = $upload['url'];
                    }
                }
            }
        }
        $data['order_id'] = $order_id;
        $data['reason'] = $reason;
        $data['comments'] = $comments;
        $data['return_images'] = $return_images;
        $data['status'] = $status;
        $data['item'] = $_items;
        $url = $apiurl."/api/orders/return";
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
        $apiBody = json_decode( wp_remote_retrieve_body( $response ) );
        if($apiBody->status){
            $post_id = wp_insert_post([
                'post_type' => 'return_request',
                'post_title' => 'Return for Order #' . $order_id,
                'post_status' => 'publish',
                'meta_input' => [
                    'order_id' => $order_id,
                    'reason' => $reason,
                    'comments' => $comments,
                    'status' => $status,
                    'user_email' => $email,
                    'return_items' => $return_items
                ]
            ]);
            if (!is_wp_error($post_id) && !empty($return_images)){
                add_post_meta($post_id, 'return_image', implode(',',$return_images));
            }
            $order = wc_get_order($order_id);
            $orderStatus = str_replace("_","-",$status);
    		$orderStatus = str_replace("under-","",$orderStatus);
    		$orderStatus = str_replace("-pickup","",$orderStatus);
            $order->update_status($orderStatus, 'Status updated via return request');
            wp_mail($email, 'Return Request Received', "Thank you for submitting a return request for Order #$order_id. Our team will review it shortly.");
            // Notify admin
            $admins = get_users(['role' => 'administrator']);
            foreach ($admins as $admin) {
                wp_mail(
                    $admin->user_email,
                    'New Return Request Submitted',
                    "A new return request has been submitted for Order #$order_id.\n\nView it in the dashboard:\n" . admin_url('post.php?post=' . $post_id . '&action=edit')
                );
            }
           wp_redirect(home_url()."/my-account/return-requests?request_status=success");
           exit;
        }else{
            wp_redirect(home_url()."/my-account/return-requests?request_status=error");
            exit;
        }
        
        wp_redirect(home_url()."/my-account/return-requests");
        exit;
    }
    public function register_items_metabox() {
        add_meta_box('return_items_metabox', 'Returned Items', [$this, 'render_items_metabox'], 'return_request', 'normal', 'default');
    }

    public function render_items_metabox($post) {
        $item_ids = get_post_meta($post->ID, 'return_items', true);
        $order_id = get_post_meta($post->ID, 'order_id', true);
        $images = get_post_meta($post->ID, 'return_image');
        $order = wc_get_order($order_id);

        if (!$order || empty($item_ids)) {
            echo '<p>No return items selected or invalid order.</p>';
        } else {
            echo '<ul>';
            foreach ($order->get_items() as $item_id => $item) {
                if (in_array($item_id, $item_ids)) {
                    $product = $item->get_product();
                    echo '<li>';
                    echo '<strong>' . esc_html($item->get_name()) . '</strong><br>';
                    echo 'Qty: ' . esc_html($item->get_quantity()) . '<br>';
                    echo 'SKU: ' . esc_html($product->get_sku()) . '<br>';
                    echo get_the_post_thumbnail($product->get_id(), 'thumbnail');
                    echo '</li>';
                }
            }
            echo '</ul>';
        }

        if (!empty($images)) {
            echo '<h4>Uploaded Images:</h4><div style="display:flex; gap:10px; flex-wrap:wrap;">';
            foreach ($images as $image_url) {
                echo '<img src="' . esc_url($image_url) . '" style="max-width:150px; height:auto; border:1px solid #ccc; padding:5px;">';
            }
            echo '</div>';
        }
    }
    public function add_return_requests_tab($items) {
        // Add our tab after "Orders"
        $new_items = [];
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            if ($key === 'orders') {
                $new_items['return-requests'] = __('Return Requests', 'woocommerce');
            }
        }
        return $new_items;
    }

    public function add_return_requests_endpoint() {
        add_rewrite_endpoint('return-requests', EP_PAGES);
    }
    
    public function render_return_requests_tab() {
        $user_email = wp_get_current_user()->user_email;
        $args = [
            'post_type' => 'return_request',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'user_email',
                    'value' => $user_email,
                    'compare' => '='
                ]
            ]
        ];
        $requests = get_posts($args);
        if ( isset( $_GET['request_status'] ) ) {
            if ( $_GET['request_status'] === 'success' ) {
                echo '<div class="nomad-message success" style="background: #d4edda;color: #155724;padding: 12px;border-radius: 6px;font-weight: bold;">Your return request was processed successfully!</div>';
            } elseif ( $_GET['request_status'] === 'error' ) {
                echo '<div class="nomad-message error" style="background: #f8d7da;color: #721c24;padding: 12px;border-radius: 6px;font-weight: bold;">There was an error processing your return request.</div>';
            }
        }
        
        echo '<h3>Your Return Requests</h3>';
    
        if (empty($requests)) {
            echo '<p>You have not submitted any return requests yet.</p>';
            return;
        }
    
        echo '<ul style="list-style: none;padding-left: 0;">';
        foreach ($requests as $request) {
            $order_id = get_post_meta($request->ID, 'order_id', true);
            $status = get_post_meta($request->ID, 'status', true);
            $date = get_the_date('', $request->ID);
            $reason = get_post_meta($request->ID, 'reason', true);
            echo '<li>';
            echo '<strong>Order #:</strong> ' . esc_html($order_id) . '<br>';
            echo '<strong>Date:</strong> ' . esc_html($date) . '<br>';
            echo '<strong>Status:</strong> ' . esc_html(ucwords(str_replace('_', ' ', $status))) . '<br>';
            echo '<strong>Reason:</strong> ' . esc_html($reason) . '<br>';
            echo '</li><hr>';
        }
        echo '</ul>';
    }

    public function add_order_return_endpoint() {
        add_rewrite_endpoint('return-request', EP_ROOT | EP_PAGES);
    }

    public function add_query_vars($vars) {
        $vars[] = 'order_return';
        return $vars;
    }

    public function order_return_template() {
        add_action('woocommerce_account_return-request_endpoint', [$this, 'render_my_account_return_form']);
    }
    public function render_my_account_return_form() {
        echo do_shortcode('[return_request_form]');
    }
    public function add_return_button_to_order_view($order) {
        $order_id = $order->get_id();
        $args = [
            'post_type' => 'return_request',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'order_id',
                    'value' => $order_id,
                    'compare' => '='
                ]
            ]
        ];
        $request = get_posts($args);
        if (empty($request)) {
            $return_url = wc_get_account_endpoint_url('return-request');
            $return_url = add_query_arg('order_id', $order_id, $return_url);
            echo '<a class="woocommerce-button wp-element-button button view" href="' . esc_url($return_url) . '" style="display: inline-block;margin: 10px 0px;">Return This Order</a>';
        }
    }

    public function register_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=return_request',
            'Return Management',
            'Return Management',
            'manage_options',
            'return-management',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page() {
        require_once plugin_dir_path(__FILE__) . 'class-return-requests-list-table.php';
		$list_table = new Return_Requests_List_Table();
		$list_table->prepare_items();

		echo '<div class="wrap"><h1>Return Management Dashboard</h1>';

		// Status filter dropdown
		$statuses = [
			'' => 'All Statuses',
			'return_requested' => 'Return Requested',
			'return_under_review' => 'Under Review',
            'return_approved' => 'Approved',
            'return_rejected' => 'Rejected',
            'return_scheduled' => 'Scheduled for Pickup',
            'return_awaiting_pickup' => 'Awaiting Pickup',
            'return_out_for_pickup' => 'Out for Pickup',
            'return_collected' => 'Item Collected',
            'return_in_transit' => 'In Transit',
            'return_received' => 'Received',
            'return_under_inspection' => 'Under Inspection',
            'return_accepted' => 'Accepted',
            'return_closed' => 'Return Closed',
		];

		echo '<form method="get" style="float: right;">';
		echo '<input type="hidden" name="page" value="return-management" />';
		echo '<input type="hidden" name="post_type" value="return_request" />';
		echo '<select name="filter_status">';
		foreach ($statuses as $key => $label) {
			printf('<option value="%s"%s>%s</option>', esc_attr($key), selected($_GET['filter_status'] ?? '', $key, false), esc_html($label));
		}
		echo '</select> <input type="submit" class="button" value="Filter" />';
		echo '</form>';

		// Bulk action form
		echo '<form method="post">';
		$list_table->process_bulk_action();
		$list_table->display();
		echo '</form>';

		echo '</div>';
    }

    public function register_status_metabox() {
        add_meta_box(
            'return_status_metabox',
            'Return Request Status',
            [$this, 'render_status_metabox'],
            'return_request',
            'side',
            'default'
        );
    }

    public function render_status_metabox($post) {
        $current_status = get_post_meta($post->ID, 'status', true);
        // these status handle by Nomad;
        //'return_scheduled','return_awaiting_pickup', 'return_out_for_pickup', 'return_collected', 'return_in_transit', 'return_received'
        $statuses = [
            'return_requested', 'return_under_review', 'return_approved', 'return_rejected','return_under_inspection', 'return_accepted', 'return_closed'
        ];
        if($current_status=="return_closed"||$current_status=="return_accepted"){
            if($current_status=="return_accepted"){update_post_meta($post->ID, 'status', "return_closed");}
           $statuses = ['return_closed']; 
        }
        echo '<select name="return_status">';
        foreach ($statuses as $status) {
            echo '<option value="' . esc_attr($status) . '"' . selected($current_status, $status, false) . '>' . esc_html(ucwords(str_replace('_', ' ', $status))) . '</option>';
        }
        echo '</select>';

        $history = get_post_meta($post->ID, 'status_history', true);
        if ($history && is_array($history)) {
            echo '<h4>Status History</h4><ul>';
            foreach ($history as $entry) {
                echo '<li>' . esc_html($entry['date']) . ': ' . esc_html($entry['status']) . '</li>';
            }
            echo '</ul>';
        }
    }

    public function save_status_metabox($post_id) {
        if (array_key_exists('return_status', $_POST)) {
            $new_status = sanitize_text_field($_POST['return_status']);
            $old_status = get_post_meta($post_id, 'status', true);
            if ($new_status !== $old_status) {
                update_post_meta($post_id, 'status', $new_status);

                $history = get_post_meta($post_id, 'status_history', true);
                if (!is_array($history)) $history = [];

                $history[] = [
                    'status' => $new_status,
                    'date' => current_time('mysql')
                ];

                update_post_meta($post_id, 'status_history', $history);

                $user_email = get_post_meta($post_id, 'user_email', true);
                if ($user_email) {
                    wp_mail($user_email, 'Your return request status has changed', 'New status: ' . $new_status);
                }
            }
        }
    }

    public function add_custom_columns($columns) {
        $columns['order_id'] = 'Order ID';
        $columns['status'] = 'Status';
        return $columns;
    }

    public function render_custom_columns($column, $post_id) {
        if ($column === 'order_id') {
            echo esc_html(get_post_meta($post_id, 'order_id', true));
        }
        if ($column === 'status') {
            echo esc_html(get_post_meta($post_id, 'status', true));
        }
    }

    public function track_status_history($post_ID, $post_after, $post_before) {
        $old_status = get_post_meta($post_ID, 'status', true);
        $new_status = isset($_POST['return_status'])?$_POST['return_status']:get_post_meta($post_ID, 'status', true);
        $refund_id = get_post_meta($post_ID, 'refund_id', true);
        if ($old_status !== $new_status && $new_status === 'return_accepted' && empty($refund_id)) {
            
            $this->nomad_create_refund($post_ID);
        }elseif($old_status !== $new_status){
            $list_table = new Return_Requests_List_Table();
    		$list_table->updateRequestStatus($post_ID,$new_status);
    		$order_id = get_post_meta($post_ID, 'order_id', true);
    		$order = wc_get_order($order_id);
    		$orderStatus = str_replace("_","-",$new_status);
    		$orderStatus = str_replace("under-","",$orderStatus);
    		$orderStatus = str_replace("-pickup","",$orderStatus);
            $order->update_status($orderStatus, 'Status updated via return request');
            //die($new_status);
        }
    }
    public function nomad_create_refund($return_post_id){
        $order_id = get_post_meta($return_post_id, 'order_id', true);
        $item_ids = get_post_meta($return_post_id, 'return_items', true);
        $order = wc_get_order($order_id);
    
        if (!$order || empty($item_ids)) return;
    
        $refund_total = 0;
    
        foreach ($order->get_items() as $item_id => $item) {
            if (in_array($item_id, $item_ids)) {
                $refund_total += $item->get_total();
            }
        }
    
        if ($refund_total <= 0) return;
    
        $refund = wc_create_refund([
            'amount' => $refund_total,
            'reason' => 'Auto refund on return request accepted',
            'order_id' => $order_id,
            'line_items' => $this->prepare_refund_line_items($order, $item_ids)
        ]);
    
        if (!is_wp_error($refund)) {
            add_post_meta($return_post_id, 'refund_id', $refund->get_id());
    
            // Update return request status to "Refund Initiated"
            wp_update_post([
                'ID' => $return_post_id,
                'meta_input' => ['status' => 'return_closed']
            ]);
    		$list_table = new Return_Requests_List_Table();
    		$list_table->updateRequestStatus($return_post_id,'return_closed');
    		$order->update_status("return-closed", 'Status updated via return request');
        }
    }
    private function prepare_refund_line_items($order, $item_ids) {
        $line_items = [];
    
        foreach ($order->get_items() as $item_id => $item) {
            if (in_array($item_id, $item_ids)) {
                $line_items[$item_id] = [
                    'qty' => $item->get_quantity(),
                    'refund_total' => $item->get_total(),
                    'refund_tax' => 0
                ];
            }
        }
    
        return $line_items;
    }
}

new WP_Order_Return_Management();
