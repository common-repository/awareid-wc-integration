<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function awareid_reverse_geocode($lat, $lon) {
    $admin_options = get_option('awareid_options');
    $apiKey = isset($admin_options['awareid_field_geocode']) ? $admin_options['awareid_field_geocode'] : '';    
    if ($apiKey == '') {
        throw new Exception("Unable to determine geocode earth API Key, please check admin console.");
    }
    $url = "https://api.geocode.earth/v1/reverse?point.lat=$lat&point.lon=$lon&api_key=$apiKey";

    $response = wp_remote_get($url, array('headers' => array('Accept' => 'application/json')));

    if (is_wp_error($response)) {
        throw new Exception("Request failed: " . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response");
    }

    $latinAmericaCountries = ['AR', 'BO', 'BR', 'CL', 'CO', 'CR', 'CU', 'DO', 'EC', 'SV', 'GT', 'HN', 'MX', 'NI', 'PA', 'PY', 'PE', 'PR', 'UY', 'VE'];

    $countryCode = $data['features'][0]['properties']['country_code'];
    if (!in_array($countryCode, array_merge(['US'], $latinAmericaCountries))) {
        throw new Exception("User is not located in the allowed regions");
    }

    if ($countryCode === 'US') {
        return [
            'code' => $data['features'][0]['properties']['region_a'],
            'type' => 'state'
        ];
    } else {
        return [
            'code' => $countryCode,
            'type' => 'country'
        ];
    }
}

// Including Forwarded IP and Real IP for reverse proxy consideration.
function awareid_get_client_ip() {
    $headers = array(
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    );

    foreach ($headers as $header) {
        if (isset($_SERVER[$header])) {
            $header = sanitize_text_field($header);
            $ip_list = array_map('trim', explode(',', sanitize_text_field($_SERVER[$header] ?? '')));
            $client_ip = trim(end($ip_list));
            if (filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
                // If the IP is localhost, return a fallback response or handle appropriately
                if ($client_ip === '127.0.0.1' || $client_ip === '::1') {
                    return 'localhost'; // Special handling for local IP
                }
                return $client_ip;
            }
        }
    }
    return '0.0.0.0';  // Fallback IP
}

function awareid_state_checker_ajax() {
    check_ajax_referer('verification_nonce', 'nonce');

    $lat = isset($_POST['latitude']) ? sanitize_text_field($_POST['latitude']) : null;
    $lng = isset($_POST['longitude']) ? sanitize_text_field($_POST['longitude']) : null;

    // Fallback to IP location if latitude and longitude are not provided
    if (empty($lat) || empty($lng)) {
        $ip = awareid_get_client_ip(); // Ensure it's getting the actual client's IP
        if ($ip == "0.0.0.0") {
            wp_send_json_error('IP address could not be determined.');
            return;
        }
        list($lat, $lng) = awareid_get_location_from_ip($ip); // Ensure this function uses a reliable source to resolve IPs to lat/lng
    }


    $options = get_option('awareid_options');
    // Rename to $disallowedLocations and include both state and country codes
    $disallowedLocations = isset($options['awareid_disallowed_states']) ? $options['awareid_disallowed_states'] : [];
    $locationInfo = awareid_reverse_geocode($lat, $lng);
    $locationCode = $locationInfo['code'];
    $locationType = $locationInfo['type'];

    if(!session_id()) {
        session_start();
    }

    $is_in_disallowed_location = false; // Default to false
    $locationFound = 'none';

    // // Check if the location (state or country) is in the disallowed list
    if (in_array($locationCode, $disallowedLocations)) {
        $is_in_selected_state = true;
        $_SESSION['is_in_selected_state'] = $is_in_selected_state;
        $locationFound = $locationCode;
    } else {
        $is_in_selected_state = false;
        $_SESSION['is_in_selected_state'] = $is_in_selected_state;
    }

    wp_send_json_success($locationFound);
}


function awareid_get_location_from_ip($ip) {
    // Skip geolocation for localhost IPs
    if ($ip === 'localhost') {
        error_log("Using test coordinates for localhost.");
        return [37.7749, -122.4194];
    }

    $admin_options = get_option('awareid_options');
    $apiKey = isset($admin_options['awareid_field_ipinfo']) ? $admin_options['awareid_field_ipinfo'] : '';
    $url = "https://ipinfo.io/" . $ip . "?token=" . $apiKey;

    $response = wp_remote_get($url, array('headers' => array('Accept' => 'application/json')));

    if (is_wp_error($response)) {
        throw new Exception("Request failed: " . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from IP Geolocation service");
    }

    if (!isset($data['loc'])) {
        throw new Exception("Location data not available for IP: $ip");
    }

    list($lat, $lng) = explode(',', $data['loc']);
    return [$lat, $lng];
}


function awareid_geofencing_authentication_logic($user, $username, $password) {
    
    // Check if a username is provided and get the user data
    if ($username) {
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            // Username is an email address
            $user = get_user_by('email', $username);
        } else {
            // Username is a regular username
            $user = get_user_by('login', $username);
        }
    }

    // If the user is not found or an error occurred, return the original response
    if (!$user || is_wp_error($user)) {
        return $user;
    }

    // Now, let's check the password for the found user
    if (!$password || !wp_check_password($password, $user->user_pass, $user->ID)) {
        // If the password is incorrect or not provided, return a WP_Error
        return new WP_Error('denied', __("ERROR: Incorrect username or password.", 'awareid-wc-integration'));
    }

    // Check if the user is an admin
    if (user_can($user->ID, 'manage_options')) {
        error_log("Admin user bypassing state check.");
        return $user;
    }

    // State check for non-admin users
    if (isset($_SESSION['is_in_selected_state']) && $_SESSION['is_in_selected_state']) {
        return new WP_Error('denied', __("ERROR: You cannot log in from the state you are currently in.", 'awareid-wc-integration'));
    }

    // If all checks pass, return the user object
    return $user;
}

add_action('wp_ajax_check_state', 'awareid_state_checker_ajax');
add_action('wp_ajax_nopriv_check_state', 'awareid_state_checker_ajax');
add_filter('authenticate', 'awareid_geofencing_authentication_logic', 30, 3);


function awareid_wc_checkout_state_check() {
    if ( !session_id() ) {
        session_start();
    }

    if ( isset( $_SESSION['is_in_selected_state'] ) && $_SESSION['is_in_selected_state'] ) {
        // Display an error message and stop the checkout process
        wc_add_notice( __( 'You cannot complete the checkout from the state you are currently in.', 'awareid-wc-integration'), 'error' );
    }
}

add_action( 'woocommerce_checkout_process', 'awareid_wc_checkout_state_check' );