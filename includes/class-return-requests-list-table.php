<?php
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Return_Requests_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'Return Request',
            'plural'   => 'Return Requests',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'title'      => 'Title',
            'order_id'   => 'Order ID',
            'reason'     => 'Reason',
            'status'     => 'Status',
            'items'      => 'Items',
            'date'       => 'Date'
        ];
    }
    
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="request[]" value="%s" />', $item->ID);
    }

    public function column_title($item) {
        $edit_link = get_edit_post_link($item->ID);
        return sprintf('<a href="%s">%s</a>', esc_url($edit_link), esc_html($item->post_title));
    }

   public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'order_id':
                return get_post_meta($item->ID, 'order_id', true);
            case 'reason':
                return get_post_meta($item->ID, 'reason', true);
            case 'status':
                return ucwords(str_replace('_', ' ', get_post_meta($item->ID, 'status', true)));
            case 'items':
                $item_ids = get_post_meta($item->ID, 'return_items', true);
                $order_id = get_post_meta($item->ID, 'order_id', true);
                $images = get_post_meta($item->ID, 'return_image');
                $order = wc_get_order($order_id);
                $html = "";
                if (!$order || empty($item_ids)) {
                    $html .= '<p>No return items selected or invalid order.</p>';
                } else {
                    $html .= '<ul style="margin: 0px;">';
                    foreach ($order->get_items() as $item_id => $_item) {
                        if (in_array($item_id, $item_ids)) {
                            $product = $_item->get_product();
                            $html .= '<li>';
                            $html .= '<strong>' . esc_html($_item->get_name()) . '</strong><br>';
                            $html .= 'Qty: ' . esc_html($_item->get_quantity()) . '<br>';
                            $html .=  'SKU: ' . esc_html($product->get_sku()) . '<br>';
                           $html .=  get_the_post_thumbnail($product->get_id(), 'thumbnail');
                            $html .=  '</li>';
                        }
                    }
                    $html .=  '</ul>';
                }
        
                if (!empty($images)) {
                    $html .=  '<h4 style="margin: 0px;">Uploaded Images:</h4><div style="display:flex; gap:10px; flex-wrap:wrap;">';
                    foreach ($images as $image_url) {
                        $html .=  '<img src="' . esc_url($image_url) . '" style="max-width:150px; height:auto; border:1px solid #ccc; padding:5px;">';
                    }
                    $html .=  '</div>';
                }
                return $html;
            case 'date':
                return get_the_date('', $item);
            default:
                return '';
        }
    }
      
    public function get_bulk_actions() {
        return [
            'mark_return_under_review' => 'Mark as Under Review',
            'mark_return_approved'     => 'Mark as Approved',
            'mark_return_rejected'     => 'Mark as Rejected',
            //'mark_return_under_inspection' => 'Mark as Under Inspection',
            //'mark_return_accepted' => 'Mark as Accepted',
            //'mark_return_closed'       => 'Mark as Closed',
        ];
    }

    public function process_bulk_action() {
        if (!empty($_POST['request']) && is_array($_POST['request'])) {
            $status = false;
            switch ($this->current_action()) {
                case 'mark_return_under_review':
                    $status = 'return_under_review';
                    break;
                case 'mark_return_approved':
                    $status = 'return_approved';
                    break;
                case 'mark_return_rejected':
                    $status = 'return_rejected';
                    break;
                case 'mark_return_under_inspection':
                    $status = 'return_under_inspection';
                    break;
                case 'mark_return_accepted':
                    $status = 'return_accepted';
                    break;
                case 'mark_return_closed':
                    $status = 'return_closed';
                    break;
            }

            if ($status) {
                $order_id = "";
                $flag = false;
                foreach ($_POST['request'] as $post_id) {
                   $flag = $this->updateRequestStatus($post_id,$status);
                }
                $order_id = get_post_meta($post_id, 'order_id', true);
                if($flag && !empty($order_id)){
                    $order = wc_get_order($order_id);
                    $orderStatus = str_replace("_","-",$status);
                    $orderStatus = str_replace("under-","",$orderStatus);
                    $orderStatus = str_replace("-pickup","",$orderStatus);
                    $order->update_status($orderStatus, 'Status updated via return request');
                }
                $current_url = $_SERVER['REQUEST_URI'];
                $redirect_url = add_query_arg( 'nomad_msg', 'request_update', $current_url );
                wp_redirect( $redirect_url );
                exit;
            }
        }
    }

    public function prepare_items() {
        $per_page = 10;
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
        $status_filter = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';

        $args = [
            'post_type'      => 'return_request',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => $orderby,
            'order'          => $order,
        ];

        if ($status_filter) {
            $args['meta_query'] = [[
                'key'   => 'status',
                'value' => $status_filter,
            ]];
        }

        $query = new WP_Query($args);
        $this->items = $query->posts;
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
    
        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => $query->max_num_pages,
        ]);
    }
    public function updateRequestStatus($post_id,$status){
        global $NomadSettings;
         $flag = false;
        $apiurl = $NomadSettings['apiurl'];
        $apikey = $NomadSettings['apikey'];
        $item_ids = get_post_meta($post_id, 'return_items', true);
        $order_id = get_post_meta($post_id, 'order_id', true);
        $order = wc_get_order($order_id);
        $_items = [];
        $data['order_id'] = $order_id;
        $data['status'] = $status;
        foreach ($order->get_items() as $item_id => $_item) {
            if (in_array($item_id, $item_ids)) {
                $product = $_item->get_product();
                 $_items[]=["name"=>esc_html($_item->get_name()),"sku"=>esc_html($product->get_sku()),"qty"=>esc_html($_item->get_quantity())];
            }
        }
        $data['item'] = $_items;
        $url = $apiurl."/api/orders/return/status";
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
        //echo "<pre>";print_r($apiBody);die('here');
        if($apiBody->status){
            update_post_meta($post_id, 'status', $status);
            $flag = true;
            
        }else{
            $current_url = $_SERVER['REQUEST_URI'];
            $redirect_url = add_query_arg( 'error', 'request_update', $current_url );
            wp_redirect( $redirect_url );
            exit;
        }
        return $flag;
    }
}
