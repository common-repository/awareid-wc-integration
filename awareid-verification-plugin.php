<?php
/*
Plugin Name: Aware Verification
Description: Seamlessly integrate Aware ID with your wordpress and woocommerce site.
Version: 2.2.0
Author: Aware
License: GPLv2 or later
*/
if (!defined('ABSPATH'))
    exit;
include (plugin_dir_path(__FILE__) . '/awareid-admin-console.php');
include (plugin_dir_path(__FILE__) . '/awareid-geofencing.php');
function awareid_enqueue_scripts()
{
    // Include the file needed to use get_plugin_data()
    require_once (ABSPATH . 'wp-admin/includes/plugin.php');

    // Get plugin data and version
    $plugin_data = get_plugin_data(__FILE__);
    $version = $plugin_data['Version'];
    // Define script and style handles
    $admin_options = get_option('awareid_options');
    $regula_license = isset($admin_options['awareid_field_development_license']) ? $admin_options['awareid_field_development_license'] : '';
    $bootstrap_css_handle = 'bootstrap-css';
    $bootstrap_js_handle = 'bootstrap-js';
    $fontawesome_css_handle = 'fontawesome-css';
    $regula_js_handle = 'regula-js';
    $aware_capture_handle = 'aware-capture-main-script';
    $aware_css_handle = 'aware-capture-main-css';
    $aware_geolocation = 'aware-geolocation';
    $aware_checkout_script = 'aware-custom-checkout';
    $admin_options = get_option('awareid_options');

    // Enqueue Bootstrap CSS and JS
    wp_enqueue_style($bootstrap_css_handle, plugins_url('styles/bootstrap.min.css', __FILE__), array(), $version);
    wp_enqueue_script($bootstrap_js_handle, plugins_url('js/bootstrap.bundle.min.js', __FILE__), array(), $version, true);
    wp_enqueue_style($fontawesome_css_handle, plugins_url('styles/fontawesome.css', __FILE__), array(), $version, true);
    wp_enqueue_script($aware_geolocation, plugins_url('js/custom-verification.js', __FILE__), array(), $version, true);

    $geo_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('verification_nonce'),
    );
    wp_localize_script($aware_geolocation, 'verification_params', $geo_data);
    $local_wasm_base_url = plugins_url('local-wasm/', __FILE__);
    // Enqueue other scripts and styles
    if ((is_checkout() || is_page('verification-required') || is_cart())) {
        // Regula is a service that handles document capture for the document verification endpoints.
        wp_enqueue_script($regula_js_handle, plugins_url('js/regula.js', __FILE__), array(), $version, true);
        wp_enqueue_style($aware_css_handle, plugins_url('styles/styles.css', __FILE__), array(), $version);
        wp_enqueue_script($aware_capture_handle, plugins_url('js/main.js', __FILE__), array($bootstrap_js_handle), $version, true);
        if (is_checkout()) {
            wp_enqueue_script($aware_checkout_script, plugins_url('js/disable-checkout.js', __FILE__), array('jquery'), $version, true);
        }
        // Localize scripts with PHP data
        $localize_data = array(
            'pluginData' => array(
                // We host our Face Capture service on a CDN, these are required to properly capture facial images.
                'knomiWebScript' => "https://awareid-wasm.web.app/KnomiWeb.js",
                'wasmBinaryFile' => "https://awareid-wasm.web.app/KnomiWeb.wasm",

            ),
            'verification_params' => array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('verification_nonce'),
            ),
        );

        $current_user = wp_get_current_user();
        $localize_data['userData'] = array(
            'rest_url' => rest_url('my_namespace/v1/validate-signature/'),
            'forward_url' => rest_url('my_namespace/v1/forward-request'),
            'add_face_url' => rest_url('my_namespace/v1/add-face'),
            'add_document_url' => rest_url('my_namespace/v1/add-document'),
            'verify_face_url' => rest_url('my_namespace/v1/verify-face'),
            'awareid_domain' => $admin_options['awareid_field_awareid_domain'],
            'realm_name' => $admin_options['awareid_field_realm_name'],
            'public_key' => $admin_options['awareid_field_pubkey'],
            'cart_url' => wc_get_cart_url(),
            'checkout_url' => wc_get_checkout_url(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_rest'),
            'awareid_email_check_nonce' => wp_create_nonce('awareid_email_check_nonce'),
            'button_check_nonce' => wp_create_nonce('button_check_nonce'),
            'email' => $current_user->user_email,
            'is_user_logged_in' => is_user_logged_in(),
            'regula_license' => isset($admin_options['awareid_field_development_field']) && $admin_options['awareid_field_development_field'] == 1 ? $regula_license : '',
            'security_level' => isset($admin_options['awareid_field_security_level']) ? $admin_options['awareid_field_security_level'] : '4'
        );

        // Pass PHP data to the enqueued scripts
        foreach ($localize_data as $handle => $data) {
            wp_localize_script($aware_capture_handle, $handle, $data);
            wp_localize_script($aware_checkout_script, $handle, $data);
        }
    }
}
add_action('wp_enqueue_scripts', 'awareid_enqueue_scripts');

add_action('rest_api_init', function () {
    register_rest_route(
        'my_namespace/v1',
        '/validate-signature/',
        array(
            'methods' => 'POST',
            'callback' => 'awareid_validate_signature_callback',
            'permission_callback' => function (WP_REST_Request $request) {
                return true;
            },
        )
    );
});

add_action('rest_api_init', function () {
    register_rest_route(
        'my_namespace/v1',
        '/add-face',
        array(
            'methods' => 'POST',
            'callback' => 'awareid_add_face_callback',
            'permission_callback' => function (WP_REST_Request $request) {
                return true;
            },
        )
    );
});

add_action('rest_api_init', function () {
    register_rest_route(
        'my_namespace/v1',
        '/add-document',
        array(
            'methods' => 'POST',
            'callback' => 'awareid_add_document_callback',
            'permission_callback' => function (WP_REST_Request $request) {
                return true;
            },
        )
    );
});

add_action('rest_api_init', function () {
    register_rest_route(
        'my_namespace/v1',
        '/verify-face',
        array(
            'methods' => 'POST',
            'callback' => 'awareid_verify_face_callback',
            'permission_callback' => function (WP_REST_Request $request) {
                return true;
            },
        )
    );
});


function awareid_validate_signature_callback(WP_REST_Request $request)
{
    $data = $request->get_param('data');

    $result = awareid_validate_signature($data);

    if ($result !== false) {
        // Split the result
        $enrollmentInfo = explode("&", $result);
        $apiKey = $enrollmentInfo[1];
        $sessionKey = $enrollmentInfo[2];

        // Store in a transient, tied to the user ID for security
        $unique_id = isset($_COOKIE['cookie_user_id']) ? sanitize_key($_COOKIE['cookie_user_id']) : '';
        set_transient('awareid_api_key_' . $unique_id, $apiKey, 10 * MINUTE_IN_SECONDS);
        set_transient('awareid_session_key_' . $unique_id, $sessionKey, 10 * MINUTE_IN_SECONDS);

        return new WP_REST_Response(['sessionToken' => $sessionKey], 200);
    } else {
        return new WP_Error('invalid_signature', 'Invalid signature or decryption failed', ['status' => 400]);
    }
}

add_action('rest_api_init', function () {
    register_rest_route(
        'my_namespace/v1',
        '/forward-request',
        array(
            'methods' => 'POST',
            'callback' => 'awareid_handle_forward_request',
            'permission_callback' => function (WP_REST_Request $request) {
                return true;
            },
        )
    );
});


// Callback function to forward request from frontend, keeping important data (apikey) secure.
function awareid_handle_forward_request(WP_REST_Request $request)
{
    if (!awareid_is_nonce_valid($request)) {
        return new WP_Error('invalid_nonce', 'Invalid or expired nonce', ['status' => 403]);
    }

    $awareIDConfig = awareid_get_awareid_config();
    $userDetails = awareid_get_users_details();
    if (!$userDetails) {
        return new WP_Error('no_user_identification', 'Unable to identify user for key retrieval');
    }

    $response = awareid_process_request($request, $awareIDConfig, $userDetails);
    if (is_wp_error($response)) {
        return $response;
    }

    return awareid_handle_response($response, $request, $awareIDConfig, $userDetails);
}

function awareid_add_face_callback(WP_REST_Request $request)
{
    if (!awareid_is_nonce_valid($request)) {
        return new WP_Error('invalid_nonce', 'Invalid or expired nonce', ['status' => 403]);
    }

    $awareIDConfig = awareid_get_awareid_config();
    $userDetails = awareid_get_users_details();
    $userDetails['jwt'] = $request->get_param('jwt');
    if (!$userDetails) {
        return new WP_Error('no_user_identification', 'Unable to identify user for key retrieval');
    }

    $response = awareid_process_request($request, $awareIDConfig, $userDetails);
    if (is_wp_error($response)) {
        return $response;
    }

    return awareid_handle_response($response, $request, $awareIDConfig, $userDetails);
}

function awareid_add_document_callback(WP_REST_Request $request)
{
    if (!awareid_is_nonce_valid($request)) {
        return new WP_Error('invalid_nonce', 'Invalid or expired nonce', ['status' => 403]);
    }

    $awareIDConfig = awareid_get_awareid_config();
    $userDetails = awareid_get_users_details();
    $userDetails['jwt'] = $request->get_param('jwt');
    if (!$userDetails) {
        return new WP_Error('no_user_identification', 'Unable to identify user for key retrieval');
    }

    $response = awareid_process_request($request, $awareIDConfig, $userDetails);
    if (is_wp_error($response)) {
        return $response;
    }

    return awareid_handle_response($response, $request, $awareIDConfig, $userDetails);
}

function awareid_verify_face_callback(WP_REST_Request $request)
{
    if (!awareid_is_nonce_valid($request)) {
        return new WP_Error('invalid_nonce', 'Invalid or expired nonce', ['status' => 403]);
    }

    $awareIDConfig = awareid_get_awareid_config();
    $userDetails = awareid_get_users_details();
    $userDetails['jwt'] = $request->get_param('jwt');
    if (!$userDetails) {
        return new WP_Error('no_user_identification', 'Unable to identify user for key retrieval');
    }

    $response = awareid_process_request($request, $awareIDConfig, $userDetails);
    if (is_wp_error($response)) {
        return $response;
    }

    return awareid_handle_response($response, $request, $awareIDConfig, $userDetails);
}

function awareid_is_nonce_valid($request)
{
    $nonce = $request->get_param('nonce');
    return wp_verify_nonce($nonce, 'wp_rest');
}

function awareid_get_awareid_config()
{
    $options = get_option('awareid_options');
    if (!isset($options['awareid_field_awareid_domain'], $options['awareid_field_realm_name'], $options['awareid_field_client_secret'])) {
        throw new Exception('Error in AwareID Configuration.');
    }
    return [
        'domain' => $options['awareid_field_awareid_domain'],
        'realm' => $options['awareid_field_realm_name'],
        'secret' => $options['awareid_field_client_secret']
    ];
}

function awareid_get_users_details()
{
    if (!isset($_COOKIE['cookie_user_id'])) {
        return false;
    }
    $unique_id = isset($_COOKIE['cookie_user_id']) ? sanitize_key($_COOKIE['cookie_user_id']) : '';
    return [
        'apiKey' => get_transient('awareid_api_key_' . $unique_id),
        'awareid_session_key' => get_transient('awareid_session_key_' . $unique_id),
        'jwt' => get_transient('awareid_jwt_' . $unique_id),
        'awareid_email' => get_transient('awareid_email_' . $unique_id),
        'unique_id' => $unique_id
    ];
}

function awareid_process_request($request, $awareIDConfig, $userDetails)
{
    $targetUrl = $request->get_param('targetUrl');
    $headers = awareid_prepare_headers($targetUrl, $awareIDConfig, $userDetails);
    $body = awareid_prepare_body($request);
    $formattedUrl = awareid_prepare_url($targetUrl, $awareIDConfig);
    return wp_remote_post($formattedUrl, [
        'method' => 'POST',
        'headers' => $headers,
        'body' => $body,
        'timeout' => 15,
    ]);
}

function awareid_prepare_headers($targetUrl, $awareIDConfig, $userDetails)
{
    $headers = ['Content-Type' => 'application/json'];
    if (str_contains($targetUrl, '/tokenVerify/validateSession')) {
        $headers['apikey'] = $userDetails['apiKey'];
    } else {
        $headers['apikey'] = $userDetails['apiKey'];
        $headers['Authorization'] = 'Bearer ' . $userDetails['jwt'];
    }
    return $headers;
}

function awareid_prepare_body($request)
{
    $rawBody = $request->get_param('body');
    return is_array($rawBody) ? wp_json_encode($rawBody) : $rawBody;
}

function awareid_prepare_url($targetUrl, $awareIDConfig)
{
    if (str_contains($targetUrl, 'triggerEnroll') || str_contains($targetUrl, 'triggerAuth')) {
        return $awareIDConfig['domain'] . $awareIDConfig['realm'] . $targetUrl;
    }
    return $awareIDConfig['domain'] . $targetUrl;
}

function awareid_handle_response($response, $request, $awareIDConfig, $userDetails)
{
    $decoded_response = json_decode(wp_remote_retrieve_body($response));

    if (str_contains($request->get_param('targetUrl'), 'proxy/validateSession')) {
        set_transient('awareid_email_' . $userDetails['unique_id'], $request->get_param('email'), 10 * MINUTE_IN_SECONDS);
        set_transient('awareid_jwt_' . $userDetails['unique_id'], $decoded_response->accessToken, 10 * MINUTE_IN_SECONDS);
    }

    if (isset($decoded_response->enrollmentStatus) && $decoded_response->enrollmentStatus == 2) {
        return awareid_handle_enrollment_status($decoded_response, $awareIDConfig, $userDetails);
    }

    if (get_current_user_id() && isset($decoded_response->authStatus) && $decoded_response->authStatus == 2) {
        update_user_meta(get_current_user_id(), 'user_verified', 1);
        update_user_meta(get_current_user_id(), 'last_verified', current_time('mysql'));
    }

    return $decoded_response;
}

function awareid_handle_enrollment_status($decoded_response, $awareIDConfig, $userDetails)
{
    $options = get_option('awareid_options');
    $minimumAge = $options['awareid_field_minimum_age'];
    $ageObject = awareid_find_age_object($decoded_response->ocrResults->fieldType);

    if ($ageObject && intval($ageObject->fieldResult->visual) < $minimumAge) {
        awareid_process_age_rejection($decoded_response, $awareIDConfig, $userDetails);
        return new WP_Error('403', 'You do not meet the minimum age to use this site.', ['status' => 403]);
    }

    awareid_update_user_meta_based_on_enrollment($decoded_response);
    return $decoded_response;
}

function awareid_process_age_rejection($decoded_response, $awareIDConfig, $userDetails)
{
    $openid_url = $awareIDConfig['domain'] . 'auth/realms/' . $awareIDConfig['realm'] . '/protocol/openid-connect/token';
    $openid_body = [
        'client_id' => 'bimaas-b2b',
        'client_secret' => $awareIDConfig['secret'],
        'grant_type' => 'client_credentials',
        'scope' => 'openid'
    ];
    $openid_args = [
        'headers' => 'Content-Type application/x-www-form-urlencoded',
        'body' => $openid_body
    ];
    $openid_response = wp_remote_post($openid_url, $openid_args);
    $openid_decoded_response = json_decode(wp_remote_retrieve_body($openid_response));
    $url = $awareIDConfig['domain'] . 'onboarding/admin/registration/' . $decoded_response->registrationCode;
    $new_headers = [
        'apikey' => $userDetails['apiKey'],
        'Authorization' => 'Bearer ' . $openid_decoded_response->access_token
    ];
    $args = [
        'method' => 'DELETE',
        'headers' => $new_headers,
    ];
    wp_remote_request($url, $args);
}

function awareid_update_user_meta_based_on_enrollment($decoded_response)
{
    if (is_page('verification-required')) {
        wp_redirect(wc_get_checkout_url());
        exit;
    }

    if (is_user_logged_in()) {
        $current_user_id = get_current_user_id();
        update_user_meta($current_user_id, 'user_registration_code', $decoded_response->registrationCode);
        update_user_meta($current_user_id, 'user_verified', 1);
        update_user_meta($current_user_id, 'last_verified', current_time('mysql'));
    } else {
        $unique_id = isset($_COOKIE['cookie_user_id']) ? sanitize_key($_COOKIE['cookie_user_id']) : '';
        $userDetails = awareid_get_users_details();
        $email_exists = email_exists($userDetails['awareid_email']);
        if (email_exists($userDetails['awareid_email'])) {
            return new WP_Error('400', 'User already exists.');
        } else {
            // Email does not exist, create a new user
            $user_id = wp_create_user($userDetails['awareid_email'], wp_generate_password(), $userDetails['awareid_email']);
            if (is_wp_error($user_id)) {
                error_log('Failed to create user: ' . $user_id->get_error_message());
            } else {
                update_user_meta($user_id, 'user_registration_code', $decoded_response->registrationCode);
                update_user_meta($user_id, 'user_verified', 1);
                update_user_meta($user_id, 'last_verified', current_time('mysql'));
                
                // After user meta is updated, send password reset email
                $result = retrieve_password($userDetails['awareid_email']);
                
                if (is_wp_error($result)) {
                    error_log('Password retrieval failed for user ' . $user_id . ': ' . $result->get_error_message());
                }
            }
        }
    }
}


function awareid_base64url_to_base64($base64url)
{
    $base64 = str_replace(['-', '_'], ['+', '/'], $base64url);
    return str_pad($base64, strlen($base64) + 4 - (strlen($base64) % 4), '=', STR_PAD_RIGHT);
}

function awareid_find_age_object($fieldTypeArray)
{
    foreach ($fieldTypeArray as $obj) {
        if (isset($obj->name) && $obj->name == "Age") {
            return $obj;
        }
    }
    return null;
}

add_action('template_redirect', 'awareid_custom_redirect_non_verified_users');


/**
 * Redirect non-verified users away from the checkout page.
 */
function awareid_custom_redirect_non_verified_users()
{
    $options = get_option('awareid_options');
    $checkbox_value = isset($options['awareid_field_redirect_checkbox_field']) ? $options['awareid_field_redirect_checkbox_field'] : '';
    if (is_checkout() && !is_wc_endpoint_url('order-received') && $checkbox_value != 1) {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $is_verified = get_user_meta($user_id, 'user_verified', true);
            if ($is_verified != '1') {
                wp_redirect(home_url('/verification-required'));
                exit;
            }
        } else {
            wp_redirect(home_url('/my-account')); // Redirect non-logged-in users to the login page
            exit;
        }
    }
}

function awareid_validate_signature($b64UrlDataStr)
{
    // Decode from base64url to base64 and then decode to raw data
    $serverPublicKeyEncoded = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAj1v8H6ehFSCfm23Z12W5tWr9UQM8+kWJsZqplgE7QcuhbM97G1bs874ouKjojdWmFHuLSkEuUNi9oIU3USJhSaCUmseD7CI0Tui4ODy8Y/3++BNqYGXAG2XQhNuGx4/6JVCg33zL46yl7zAj5fdl5+tJ5DrITDqf12dcwdPNTOM18ddBkKRL0oD4eIx2IGc4oCEBPghJlQCmDAuuXsuaNQc12sOo2BC9Uf+TQgIe5OjptAEaZLIEYTKle66yrWlZ+o0T028DtK971UUMmBQ0Uk+JKKfvTMkgCam4lZ8UMmBeaogYF5UPcMsoQlfkqjo3RILEyCaeW97ZAw3oq70DjwIDAQAB'; // The public key in PEM format
    $serverKey = 'w2svxZJZYoQHaVTtt5Oq/QdQRPa5zobb4LVbINTDB2A=';

    // Convert base64url to base64 and then to raw data
    $b64DataStr = awareid_base64url_to_base64($b64UrlDataStr);
    $combinedDecoded = base64_decode($b64DataStr, true);

    // Assume the first 256 bytes are the RSA signature
    $signatureExtracted = substr($combinedDecoded, 0, 256);
    $encryptedDataExtracted = substr($combinedDecoded, 256);

    // Convert the server public key to PEM format
    $publicKey = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" .
        chunk_split($serverPublicKeyEncoded, 64, "\n") .
        "-----END PUBLIC KEY-----");

    // The data that was signed is the encrypted data
    $originalData = $encryptedDataExtracted; // The signed data is the encrypted data itself

    // Verify the signature
    $verificationResult = openssl_verify($originalData, $signatureExtracted, $publicKey, OPENSSL_ALGO_SHA256);

    if ($verificationResult === 1) {
        // Use the same IV as used during encryption (typically this should be received separately)
        $iv = str_repeat(chr(0), 16);

        // Decode the server key for decryption
        $decryptionKey = base64_decode($serverKey);

        // Decrypt the data
        $decryptedData = openssl_decrypt($encryptedDataExtracted, 'aes-256-cbc', $decryptionKey, OPENSSL_RAW_DATA, $iv);

        if ($decryptedData === false) {
            return false;
        }

        return substr($decryptedData, 16);
    } elseif ($verificationResult === 0) {
        // Signature verification failed
        error_log('Signature verification failed.');
        return null;
    } else {
        // An error occurred during signature verification
        error_log('An error occurred during signature verification: ' . openssl_error_string());
        return null;
    }
}

add_action('woocommerce_before_checkout_form', 'awareid_ts_handle_checkout_button');
add_action('wp_footer', 'awareid_ts_add_block_checkout_script');

function awareid_ts_add_block_checkout_script() {
    if (!is_cart()) {
        return;
    }
    $options = get_option('awareid_options');
    $redirect_checkbox_value = isset($options['awareid_field_redirect_checkbox_field']) ? $options['awareid_field_redirect_checkbox_field'] : '';

    if (!is_user_logged_in() && $redirect_checkbox_value) {
        return;
    }

    $user_id = get_current_user_id();
    $duration_number = isset($options['awareid_field_duration_number']) ? intval($options['awareid_field_duration_number']) : 0;
    $duration_unit = isset($options['awareid_field_duration_unit']) ? $options['awareid_field_duration_unit'] : 'hours';
    $checkbox_value = isset($options['awareid_field_checkbox_field']) ? $options['awareid_field_checkbox_field'] : '';

    $user_verified = get_user_meta($user_id, 'user_verified', true);
    $user_last_verified = get_user_meta($user_id, 'last_verified', true);

    $needs_verification = false;

    if (!is_user_logged_in() || $user_verified != '1') {
        $needs_verification = true;
    } else {
        // Check for reverification
        $user_last_verified_time = strtotime($user_last_verified);
        $duration_in_seconds = ($duration_unit === 'days') ? $duration_number * DAY_IN_SECONDS : $duration_number * HOUR_IN_SECONDS;
        $current_time = current_time('timestamp');

        if ($checkbox_value && $user_last_verified && ($current_time - $user_last_verified_time >= $duration_in_seconds)) {
            $needs_verification = true;
        }
    }

    if ($needs_verification && (!is_cart() || $redirect_checkbox_value != 1) && is_user_logged_in()) {
        ?>
        <style>
            .ts-verification-button-container {
                position: relative;
            }
            .ts-verification-button {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                display: flex;
                justify-content: center;
                align-items: center;
                background: rgba(255, 255, 255, 0.9);
                z-index: 1000;
            }
        </style>
        <script>
            jQuery(document).ready(function ($) {
                const isBlockBasedWooCommercePage = () => {
                    return document.querySelector('.wc-block-cart, .wc-block-checkout') !== null;
                }
                const observeButton = () => {
                    console.log("In observeButton");
                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.type === 'childList') {
                                const checkoutButton = document.querySelector('.wc-block-components-checkout-place-order-button');
                                const cartButton = document.querySelector('.wc-block-cart__submit-container a');
                                if (checkoutButton || cartButton) {
                                    handleButton(checkoutButton || cartButton);
                                }
                            }
                        });
                    });

                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                };
                if (isBlockBasedWooCommercePage) {
                    observeButton();
                }

                const handleButton = (button) => {
                    if (!button.parentNode.classList.contains('ts-verification-button-container')) {
                        const wcCartButton = document.querySelector('a.wc-block-components-button.wp-element-button.wc-block-cart__submit-button.contained');
                        if(wcCartButton){
                            wcCartButton.classList.add('d-none');
                        }
                        const wcCheckoutButton = document.querySelector('button.wc-block-components-button.wp-element-button.wc-block-components-checkout-place-order-button.contained')
                        const container = document.createElement('div');
                        container.className = 'ts-verification-button-container';
                        button.parentNode.insertBefore(container, button);
                        container.appendChild(button);

                        const verifyButton = document.createElement('div');
                        verifyButton.className = 'wc-block-components-button wp-element-button wc-block-cart__submit-button contained';                        
                        verifyButton.type = 'button';
                        verifyButton.id = 'consentButton';
                        verifyButton.textContent = 'Verify Your Identity';
                        container.appendChild(verifyButton);

                        $('#consentButton').on('click', function(e) {
                            console.log("Testing click");
                            e.preventDefault();
                            $('#consentModal').modal('show');
                        });
                    }
                };
            });
        </script>
        <?php
    }
}

function awareid_ts_add_modals() {
    if ((is_checkout() || is_cart()) && !is_wc_endpoint_url('order-received') && is_woocommerce_blocks_based()) {
        awareid_ts_custom_checkout_button();
    }
}
add_action('wp_footer', 'awareid_ts_add_modals');

function is_woocommerce_blocks_based() {
    if (wp_script_is('wc-blocks-checkout', 'enqueued') || wp_script_is('wc-blocks-cart', 'enqueued')) {
        return true;
    }
    return false;
}




function awareid_ts_is_user_verified()
{
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        return get_user_meta($user_id, 'user_verified', true) == '1';
    }
    return false;
}

function awareid_ts_handle_checkout_button()
{
    if (!is_wc_endpoint_url('order-received')) {
        if (wp_script_is('wc-blocks-checkout', 'enqueued') || wp_script_is('wc-blocks-cart', 'enqueued')) {
            // Block-based checkout
            echo '<div id="ts-verification-button-container" style="display: none;"></div>';
        } else {
            // Traditional checkout
            awareid_ts_custom_checkout_button();
        }
    }
}


// Shortcode for the login form
add_shortcode('awareid_verification_form', 'awareid_verification_form_shortcode');
function awareid_verification_form_shortcode()
{
    $flip_url = plugins_url('assets/flip.png', __FILE__);
    $face_image = plugins_url('assets/face-id-icon.png', __FILE__);
    $options = get_option('awareid_interface_options');
    if (isset($options['awareid_interface_options_welcome_tagline'])) {
        $tagline = $options['awareid_interface_options_welcome_tagline'];
    } else {
        $tagline = 'Welcome to our verification page.';
    }
    // Output the login form HTML
    ob_start();
    ?>

    <!-- Button to trigger modal -->
    <?php if (!is_checkout() && is_woocommerce_blocks_based()): ?>
        <button type="button" class="checkout-button wp-element-button d-none" data-bs-toggle="modal" disabled id="consentButton"
            data-bs-target="#consentModal">
            Verify Your Identity
        </button>
    <?php endif; ?>
    <?php if (!is_checkout() && !is_woocommerce_blocks_based()): ?>
        <button type="button" class="checkout-button wp-element-button" data-bs-toggle="modal" disabled id="consentButton"
            data-bs-target="#consentModal">
            Verify Your Identity
        </button>
    <?php endif; ?>
    <?php if (is_checkout()): ?>
        <button type="button" class="checkout-button wp-element-button d-none" data-bs-toggle="modal" disabled
            id="consentButton" data-bs-target="#consentModal">
            Verify Your Identity
        </button>
    <?php endif; ?>
    <!-- Consent Modal -->
    <div class="modal fade custom-centered-modal" id="consentModal" tabindex="-1" aria-labelledby="consentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-dark" id="consentModalLabel">Biometric Consent and Document Preparation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-dark"><strong >What You’re Agreeing To:</strong> We will capture and store your biometric information,
                        including your facial data and identification document.</p>
                    <p class="text-dark"><strong>Why This is Needed:</strong> This information is required for identity verification to ensure
                        a secure and seamless process.</p>
                    <div class="alert alert-warning">
                        <strong>Important:</strong>
                        <div class="mt-2">
                            <span class="text-primary"><strong>First:</strong></span> We will capture your face. Please
                            position yourself in good lighting.
                        </div>
                        <div class="mt-2">
                            <span class="text-primary"><strong>Next:</strong></span> You will have <strong>20
                                seconds</strong> to capture your identification document, so have it ready.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Exit</button>
                    <button type="button" class="btn btn-outline-primary wp-element-button" data-bs-toggle="modal"
                        data-bs-dismiss="modal" id="captureBtn" data-bs-target="#identityVerificationModal">Agree and
                        Proceed</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal -->
    <div class="modal fade custom-centered-modal" id="identityVerificationModal" tabindex="-1"
        aria-labelledby="identityVerificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-dark" id="identityVerificationModalLabel">Identity Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="mainCard">
                    <div id="loadingSpinner" class="d-flex justify-content-center my-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden text-dark">Loading...</span>
                        </div>
                    </div>
                    <div class="main-box order-sm-1" id="loginBox">
                        <img src="" id="frontDriverImage" style="display: none;">
                        <div class="row d-flex justify-content-center main-title" id="main-title"
                            style="text-align: center; padding-top: 1em">
                            <p class="fs-6 text-dark">
                                <?php echo esc_html($tagline) ?>
                            </p>
                            <p class="fs-6 text-dark">You must verify your identity in order to proceed with checkout.</p>
                        </div>

                        <div id="previewSection" class="video-section">
                            <div id="feedbackSection" style="max-width: 100%;" class="top-part">
                                <button id="stopAutocaptureBtn" type="button" class="btn"
                                    style="max-height: 47px; font-weight: 800; font-size: 22px; display: none;">&#8666;</i>
                                </button>
                                <input class="form-control"
                                    style="background-color: #95a4b12f; font-size: 22px; text-align: center; color: black;font-weight: 800;"
                                    id="feedbackText" rows="1" cols="80" readonly="readonly" />
                            </div>
                            <div class="bottom-part">
                                <div id="previewContentSection" class="previewContent">
                                    <div id="previewWindowParent" class="previewWindowParent">
                                        <div id="loadingSpinnerVideoParent" class="d-none">
                                            <div id="loadingSpinnerVideo" class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <img src="<?php echo esc_url(plugins_url('assets/lottie/VohtnzX4pk.gif', __FILE__)); ?>" id="failAnimation" 
                                            class="d-none w-75">
                                            <img src="<?php echo esc_url(plugins_url('assets/lottie/YdgocJvy4O.gif', __FILE__)); ?>" id="successAnimation" 
                                            class="d-none w-75">
                                        </div>
                                        <div id="documentContainer">
                                        </div>
                                        <video id="previewWindow" class="shadow bg-dark" playsinline autoplay>
                                            Your browser does not support the video tag.
                                        </video>
                                        <div>
                                            <canvas class="ovalOverlay" id="ovalOverlay"></canvas>
                                            <img src="<?php echo esc_url($face_image); ?>" alt="Face Image" id="faceImage">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="loginView" style="text-align: center;" hidden>
                            <i class="fa fa-user fa-5x" aria-hidden="true"
                                style="color: #2ba2db; padding: 0em 0em .5em 0em"></i>
                            <p id="loginTextBox">Verifying Identity</p>
                        </div>

                        <div id="successSection" class="alert alert-success alert-dismissible collapse">
                            <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                            <strong>Success!</strong>
                            <span id="successText"></span>
                        </div>

                        <div id="errorSection" class="alert alert-danger alert-dismissible collapse">
                            <a href="#" class="close" aria-label="close" id="closeError">&times;</a>
                            <strong>Error!</strong>
                            <span id="errorText"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function awareid_check_button_status_ajax_handler()
{
    $current_user_id = get_current_user_id();
    $user_verified = get_user_meta($current_user_id, 'user_verified', true);

    if ($user_verified) {
        wp_send_json(array('disable_old_button' => true));
    } else {
        wp_send_json(array('disable_old_button' => false));
    }
}
add_action('wp_ajax_check_button_status', 'awareid_check_button_status_ajax_handler');
add_action('wp_ajax_nopriv_check_button_status', 'awareid_check_button_status_ajax_handler');


function awareid_check_email_existence_ajax_handler() {

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'awareid_email_check_nonce')) {
        wp_send_json_error('Nonce verification failed', 403);
        return;
    }

    if (!isset($_POST['email'])) {
        wp_send_json_error('Email not provided', 400);
        return;
    }

    $email = sanitize_email(wp_unslash($_POST['email']));
    $userDetails = awareid_get_users_details();
    set_transient('awareid_email_' . $userDetails['unique_id'], $email, 10 * MINUTE_IN_SECONDS);

    if (email_exists($email)) {
        wp_send_json_success(array('exists' => true));
    } else {
        wp_send_json_success(array('exists' => false));
    }
}

add_action('wp_ajax_nopriv_awareid_check_email_existence', 'awareid_check_email_existence_ajax_handler');
add_action('wp_ajax_awareid_check_email_existence', 'awareid_check_email_existence_ajax_handler');

add_action('init', 'awareid_start_session', 1);


function awareid_start_session() {
    if (!isset($_COOKIE['cookie_user_id'])) {
        $unique_id = uniqid('nluid_', true);
        setcookie('cookie_user_id', $unique_id, time() + (86400 * 30), "/", "", true, true);
    }
    
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}

function awareid_create_verification_page()
{
    // Check if the page exists
    $page = get_page_by_path('verification-required');
    if (!$page) {
        // Create the page
        $page_data = array(
            'post_title' => 'Verification Required',
            'post_name' => 'verification-required',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => '',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        );
        wp_insert_post($page_data);
    }
}
register_activation_hook(__FILE__, 'awareid_create_verification_page');

function awareid_verification_page_content($content)
{
    $image_url = plugins_url('assets/verify.png', __FILE__);
    $login_form_html = awareid_verification_form_shortcode();

    if (is_page('verification-required')) {
        if (is_user_logged_in()) {
            $custom_content = '<div class="container">
            <div class="row justify-content-center align-items-center">
                <div class="col-md-6 text-center">
                    <img src="' . esc_url($image_url) . '" class="img-fluid pb-5" alt="Verification Image">' .
                $login_form_html . '
                </div>
            </div>
            </div>';

            $custom_content .= '<script type="text/javascript">
                document.addEventListener("DOMContentLoaded", function() {
                    var consentButton = document.getElementById("consentButton");
                    if (consentButton) {
                        consentButton.classList.remove("d-none");
                    }
                });
            </script>';

            return $custom_content;
        } else {
            wp_redirect(home_url('/my-account')); // Redirect non-logged-in users to the login page
            exit;
        }
    }
    return $content;
}
add_filter('the_content', 'awareid_verification_page_content');


function awareid_exclude_verification_from_nav($pages)
{
    $remove_page = get_page_by_path('verification-required');
    if ($remove_page) {
        $pages = array_filter($pages, function ($page) use ($remove_page) {
            return $page->ID !== $remove_page->ID;
        });
    }
    return $pages;
}
add_filter('get_pages', 'awareid_exclude_verification_from_nav');

function awareid_activate_verification_page()
{
    awareid_create_verification_page();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'awareid_activate_verification_page');

function awareid_deactivate_verification_page()
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'awareid_deactivate_verification_page');

function awareid_ts_custom_proceed_to_checkout_button()
{
    if (is_cart()) {
        // Get the current user ID
        $user_id = get_current_user_id();
        $options = get_option('awareid_options');
        $duration_number = isset($options['awareid_field_duration_number']) ? intval($options['awareid_field_duration_number']) : 0;
        $duration_unit = isset($options['awareid_field_duration_unit']) ? $options['awareid_field_duration_unit'] : 'hours';
        $checkbox_value = isset($options['awareid_field_checkbox_field']) ? $options['awareid_field_checkbox_field'] : '';

        // Check if the user is verified
        $user_verified = get_user_meta($user_id, 'user_verified', true);
        if (is_user_logged_in()) {
            if ($user_verified != '1') {
                // User not verified, remove the default checkout button and add custom button
                remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
                add_action('woocommerce_proceed_to_checkout', 'awareid_ts_custom_checkout_button', 20);
            } else {
                // User is verified, perform step-up authentication check
                $user_last_verified = get_user_meta($user_id, 'last_verified', true);

                // Convert the last verified time to a timestamp
                $user_last_verified_time = strtotime($user_last_verified);

                // Calculate duration in seconds
                $duration_in_seconds = ($duration_unit === 'days') ? $duration_number * DAY_IN_SECONDS : $duration_number * HOUR_IN_SECONDS;

                // Current time
                $current_time = current_time('timestamp');
                // Check if the checkbox is enabled and if the user_last_verified time is within the specified duration
                if ($checkbox_value && $user_last_verified && ($current_time - $user_last_verified_time >= $duration_in_seconds)) {
                    // The last verification time is outside the specified duration, modify the checkout button
                    remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
                    add_action('woocommerce_proceed_to_checkout', 'awareid_ts_custom_checkout_button', 20);
                }
            }
        }
    }
}


add_action('template_redirect', 'awareid_ts_custom_proceed_to_checkout_button');

function awareid_ts_custom_checkout_button()
{
    $allowed_tags = array(
        'div' => array(
            'class' => array(),
            'id' => array(),
            'style' => array(),
            'hidden' => array(),
            'aria-labelledby' => array(),
            'aria-hidden' => array(),
            'tabindex' => array(),
        ),
        'span' => array(
            'class' => array(),
            'id' => array(),
            'role' => array(),
        ),
        'p' => array(
            'class' => array(),
            'id' => array(),
        ),
        'button' => array(
            'type' => array(),
            'class' => array(),
            'id' => array(),
            'data-bs-dismiss' => array(),
            'data-bs-toggle' => array(), // Important for Bootstrap modal functionality
            'aria-label' => array(),
            'disabled' => array(),
            'data-bs-target' => array(), // Important for Bootstrap modal functionality
        ),
        'a' => array(
            'href' => array(),
            'class' => array(),
            'aria-label' => array(),
            'id' => array(),
            'data-dismiss' => array(), // Important for Bootstrap alert dismiss functionality
        ),
        'img' => array(
            'src' => array(),
            'alt' => array(),
            'class' => array(),
            'id' => array(),
            'style' => array(),
        ),
        'iframe' => array(
            'src' => array(),
            'class' => array(),
            'id' => array(),
            'frameborder' => array(),
            'allowfullscreen' => array(),
        ),
        'video' => array(
            'class' => array(),
            'autoplay' => array(),
            'playsinline' => array(),
            'id' => array(),
            'controls' => array(),
        ),
        'input' => array(
            'type' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
            'rows' => array(),
            'cols' => array(),
            'readonly' => array(),
        ),
        'canvas' => array(
            'class' => array(),
            'id' => array(),
        ),
        'h5' => array(
            'class' => array(),
            'id' => array(),
        ),
        'i' => array(
            'class' => array(),
            'aria-hidden' => array(),
        ),
        'strong' => array(),
        'alert' => array(
            'class' => array(),
            'id' => array(),
        ),
        'close' => array(
            'class' => array(),
            'data-dismiss' => array(),
            'aria-label' => array(),
        ),
    );
    echo wp_kses(awareid_verification_form_shortcode(), $allowed_tags);
}

add_action('woocommerce_review_order_before_submit', function () {
    wp_nonce_field('add_wc_errors_nonce_action', 'add_wc_errors_nonce');
});

function awareid_add_wc_errors($posted)
{
    // Verify nonce first
    if (!isset($_POST['add_wc_errors_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['add_wc_errors_nonce'])), 'add_wc_errors_nonce_action')) {
        error_log("Nonce verification failed");
        wp_die('Security check failed', 'Security Error', ['response' => 403]);
    }

    $user_id = get_current_user_id();
    $user_verified = get_user_meta($user_id, 'user_verified', true);
    if ($user_verified == 1) {
        return;
    }

    if (isset($_POST['confirm-order-flag']) && $_POST['confirm-order-flag'] == "1") {
        if (!is_user_logged_in() && isset($_POST['billing_email']) && !empty($_POST['billing_email'])) {
            $billing_email = sanitize_email($_POST['billing_email']);
            $userDetails = awareid_get_users_details();
            set_transient('awareid_email_' . $userDetails['unique_id'], $billing_email, 10 * MINUTE_IN_SECONDS);


            if (email_exists($billing_email)) {
                wc_add_notice(__("Email already exists, please log in. Redirecting in 3 seconds...", 'awareid-wc-integration'), 'error');
                return;
            }
        }

        if (!awareid_ts_is_user_verified()) {
            wc_add_notice(__("User must verify document.", 'awareid-wc-integration'), 'error');
        }
    }
}
add_action('woocommerce_after_checkout_validation', 'awareid_add_wc_errors');


function awareid_associate_order_with_existing_user($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order->get_user_id()) {
        $order_email = $order->get_billing_email();
        $user = get_user_by('email', $order_email);
        if ($user) {
            $order->set_customer_id($user->ID);
            $order->save();
        }
    }
}
add_action('woocommerce_checkout_order_processed', 'awareid_associate_order_with_existing_user');
