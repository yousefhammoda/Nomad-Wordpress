<?php
define('NOMAD_JWT_SECRET', '29D3AE9CDE6DC1F6BEC884169E2AB5623456');
define('NOMAD_JWT_DEBUG', true); // Set to false in production

/*
POST /wp-json/nomad-api/v1/auth
Body: { "username": "apiuser", "password": "apipass" }
{ "token": "abc.jwt.token", "expires_in": 900 }

POST /wp-json/nomad-api/v1/update-order-status
Headers:
  Authorization: Bearer abc.jwt.token
Body:
{
  "order_id": 123,
  "status": "out-for-delivery"
}
*/

function jwt_encode_token($payload, $secret, $expiry = 900) {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload['exp'] = time() + $expiry;

    $base64UrlHeader = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $base64UrlPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $secret, true);
    $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
}

function jwt_decode_token($token, $secret) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        if (NOMAD_JWT_DEBUG) error_log("Invalid token format");
        return false;
    }

    [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

    $expectedSig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $secret, true)), '+/', '-_'), '=');
    $validSig = hash_equals(
        rtrim(strtr($signatureEncoded, '-_', '+/'), '='),
        rtrim(strtr($expectedSig, '-_', '+/'), '=')
    );

    if (!$validSig) {
        if (NOMAD_JWT_DEBUG) {
            error_log("JWT signature mismatch");
            error_log("Expected: $expectedSig");
            error_log("Got:      $signatureEncoded");
        }
        return false;
    }

    $payload = json_decode(base64_decode($payloadEncoded), true);
    if (!$payload || !isset($payload['exp'])) {
        if (NOMAD_JWT_DEBUG) error_log("Payload decode failed or missing 'exp'");
        return false;
    }

    $now = time();
    if ($payload['exp'] < $now - 5) {
        if (NOMAD_JWT_DEBUG) {
            error_log("Token expired at {$payload['exp']}, now is $now");
        }
        return false;
    }

    if (NOMAD_JWT_DEBUG) {
        error_log("Token decoded successfully: " . print_r($payload, true));
    }

    return $payload;
}

add_action('rest_api_init', function () {
    register_rest_route('nomad-api/v1', '/auth', array(
        'methods'  => 'POST',
        'callback' => 'nomad_api_jwt_auth',
        'permission_callback' => '__return_true',
        'args' => [
            'username' => ['required' => true, 'type' => 'string'],
            'password' => ['required' => true, 'type' => 'string'],
        ],
    ));
});

function nomad_api_jwt_auth($request) {
    $username = sanitize_text_field($request->get_param('username'));
    $password = $request->get_param('password');

    $user = wp_authenticate($username, $password);
    if (is_wp_error($user) || !user_can($user, 'manage_woocommerce')) {
        return new WP_Error('unauthorized', 'Invalid credentials or permissions', ['status' => 403]);
    }

    $payload = [
        'user_id' => $user->ID,
        'iat'     => time(),
    ];

    $token = jwt_encode_token($payload, NOMAD_JWT_SECRET, 900);

    return [
        'token' => $token,
        'expires_in' => 900,
    ];
}

add_action('rest_api_init', function () {
    register_rest_route('nomad-api/v1', '/update-order-status', array(
        'methods'  => 'POST',
        'callback' => 'nomad_api_update_order_status_jwt',
        'permission_callback' => 'nomad_jwt_auth_check',
        'args' => array(
            'order_id' => ['required' => true, 'type' => 'integer'],
            'status'   => ['required' => true, 'type' => 'string'],
        ),
    ));
});

function nomad_jwt_auth_check($request) {
    $auth = $request->get_header('authorization');
    if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (NOMAD_JWT_DEBUG) error_log("Authorization header: $auth");

    if (!$auth || stripos($auth, 'Bearer ') !== 0) {
        return new WP_Error('invalid_format', 'Expected Bearer token', ['status' => 401]);
    }

    $token = substr($auth, 7);
    $payload = jwt_decode_token($token, NOMAD_JWT_SECRET);

    if (!$payload || empty($payload['user_id'])) {
        if (NOMAD_JWT_DEBUG) error_log("Token verification failed or user_id missing");
        return new WP_Error('invalid_token', 'Token expired or invalid', ['status' => 403]);
    }

    wp_set_current_user($payload['user_id']);
    return true;
}

function nomad_api_update_order_status_jwt($request) {
    global $NomadSettings;
    $order_id = $request->get_param('order_id');
    $status   = $request->get_param('status');

    $valid_statuses = ['preparing', 'delivery-ready', 'out-for-delivery', 'delivered'];
    $return_statuses = ['return_scheduled','return_awaiting_pickup','return_out_for_pickup','return_collected','return_in_transit','return_received','return_under_inspection','return_accepted','return_closed'];
    if(isset($NomadSettings['order_retun']) && $NomadSettings['order_retun']=="yes"){
         $valid_statuses[] ='return_requested';
         $valid_statuses[] ='return_under_review';
         $valid_statuses[] ='return_approved';
         $valid_statuses[] ='return_rejected';
         $valid_statuses[] ='return_scheduled';
         $valid_statuses[] ='return_awaiting_pickup';
         $valid_statuses[] ='return_out_for_pickup';
         $valid_statuses[] ='return_collected';
         $valid_statuses[] ='return_in_transit';
         $valid_statuses[] ='return_received';
         $valid_statuses[] ='return_under_inspection';
         $valid_statuses[] ='return_accepted';
         $valid_statuses[] ='return_closed';
    }
    if (!in_array($status, $valid_statuses, true)) {
        return new WP_Error('invalid_status', 'Invalid status.', ['status' => 400]);
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return new WP_Error('not_found', 'Order not found.', ['status' => 404]);
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
    ///echo "<pre>";print_r($request);echo "</pre>"; echo "order_id:".$order_id;
    if (!empty($request) && in_array($status,$return_statuses)) {
        foreach($request as $_request){ 
            update_post_meta($_request->ID, 'status', $status);
        }
    }
         
    $orderStatus = str_replace("_","-",$status);
    $orderStatus = str_replace("under-","",$orderStatus);
    $orderStatus = str_replace("-pickup","",$orderStatus);
    $order->update_status($orderStatus, 'Status updated via Nomad API');

    return [
        'success' => true,
        'updated' => $orderStatus,
        'message' => "Order #$order_id updated to '$status'"
    ];
}
?>
