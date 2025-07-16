<?php
//// Order Status
function nomad_register_multiple_order_statuses() {
    global $NomadSettings;
    $statuses = [
        'preparing'          => 'Preparing',
        'delivery-ready' => 'Ready for Delivery',
        'out-for-delivery'   => 'Out for Delivery',
        'delivered'          => 'Delivered'
    ];
    // Order return status
    if(isset($NomadSettings['order_retun']) && $NomadSettings['order_retun']=="yes"){
         $statuses['return-requested'] = 'Return Requested';
         $statuses['return-review'] = 'Return Under Review';
         $statuses['return-approved'] = 'Return Approved';
         $statuses['return-rejected'] = 'Return Rejected';
         $statuses['return-scheduled'] = 'Return Scheduled for Pickup';
         $statuses['return-awaiting'] = 'Return Awaiting Pickup';
         $statuses['return-out-for'] = 'Return Out for Pickup';
         $statuses['return-collected'] = 'Return Item Collected';
         $statuses['return-in-transit'] = 'Return In Transit';
         $statuses['return-received'] = 'Return Received';
         $statuses['return-inspection'] = 'Return Under Inspection';
         $statuses['return-accepted'] = 'Return Accepted';
         $statuses['return-closed'] = 'Return Closed';
    }
    foreach ( $statuses as $slug => $label ) {
        register_post_status( 'wc-' . $slug, array(
            'label'                     => $label,
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                "$label <span class='count'>(%s)</span>",
                "$label <span class='count'>(%s)</span>"
            ),
        ));
    }
}
add_action('init', 'nomad_register_multiple_order_statuses');
function nomad_add_multiple_order_statuses($order_statuses) {
    global $NomadSettings;
    $custom_statuses = [
        'wc-preparing'          => 'Preparing',
        'wc-delivery-ready' => 'Ready for Delivery',
        'wc-out-for-delivery'   => 'Out for Delivery',
        'wc-delivered'          => 'Delivered'
    ];
    // Order return status
    if(isset($NomadSettings['order_retun']) && $NomadSettings['order_retun']=="yes"){
         $custom_statuses['wc-return-requested'] = 'Return Requested';
         $custom_statuses['wc-return-review'] = 'Return Under Review';
         $custom_statuses['wc-return-approved'] = 'Return Approved';
         $custom_statuses['wc-return-rejected'] = 'Return Rejected';
         $custom_statuses['wc-return-scheduled'] = 'Return Scheduled for Pickup';
         $custom_statuses['wc-return-awaiting'] = 'Return Awaiting Pickup';
         $custom_statuses['wc-return-out-for'] = 'Return Out for Pickup';
         $custom_statuses['wc-return-collected'] = 'Return Item Collected';
         $custom_statuses['wc-return-in-transit'] = 'Return In Transit';
         $custom_statuses['wc-return-received'] = 'Return Received';
         $custom_statuses['wc-return-inspection'] = 'Return Under Inspection';
         $custom_statuses['wc-return-accepted'] = 'Return Accepted';
         $custom_statuses['wc-return-closed'] = 'Return Closed';
    }
    // Optional: Insert after 'processing'
    $new_statuses = [];
    foreach ( $order_statuses as $key => $label ) {
        $new_statuses[$key] = $label;
        if ( 'wc-processing' === $key ) {
            $new_statuses = array_merge($new_statuses, $custom_statuses);
        }
    }

    return $new_statuses;
}
add_filter('wc_order_statuses', 'nomad_add_multiple_order_statuses');
function nomad_add_bulk_actions($bulk_actions) {
    $bulk_actions['mark_preparing'] = 'Change status to Preparing';
    $bulk_actions['mark_delivery-ready'] = 'Change status to Ready for Delivery';
    $bulk_actions['mark_out-for-delivery'] = 'Change status to Out for Delivery';
    $bulk_actions['mark_delivered'] = 'Change status to Delivered';
    $bulk_actions['mark_return-requested'] = 'Change status to Return Requested';
    $bulk_actions['mark_return-review'] = 'Change status to Return Under Review';
    $bulk_actions['mark_return-approved'] = 'Change status to Return Approved';
    $bulk_actions['mark_return-rejected'] = 'Change status to Return Rejected';
    return $bulk_actions;
}
add_filter('bulk_actions-edit-shop_order', 'nomad_add_bulk_actions');