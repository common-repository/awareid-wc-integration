<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$awareid_states = array(
    'AL' => 'Alabama',
    'AK' => 'Alaska',
    'AZ' => 'Arizona',
    'AR' => 'Arkansas',
    'CA' => 'California',
    'CO' => 'Colorado',
    'CT' => 'Connecticut',
    'DE' => 'Delaware',
    'DC' => 'District of Columbia',
    'FL' => 'Florida',
    'GA' => 'Georgia',
    'HI' => 'Hawaii',
    'ID' => 'Idaho',
    'IL' => 'Illinois',
    'IN' => 'Indiana',
    'IA' => 'Iowa',
    'KS' => 'Kansas',
    'KY' => 'Kentucky',
    'LA' => 'Louisiana',
    'ME' => 'Maine',
    'MD' => 'Maryland',
    'MA' => 'Massachusetts',
    'MI' => 'Michigan',
    'MN' => 'Minnesota',
    'MS' => 'Mississippi',
    'MO' => 'Missouri',
    'MT' => 'Montana',
    'NE' => 'Nebraska',
    'NV' => 'Nevada',
    'NH' => 'New Hampshire',
    'NJ' => 'New Jersey',
    'NM' => 'New Mexico',
    'NY' => 'New York',
    'NC' => 'North Carolina',
    'ND' => 'North Dakota',
    'OH' => 'Ohio',
    'OK' => 'Oklahoma',
    'OR' => 'Oregon',
    'PA' => 'Pennsylvania',
    'RI' => 'Rhode Island',
    'SC' => 'South Carolina',
    'SD' => 'South Dakota',
    'TN' => 'Tennessee',
    'TX' => 'Texas',
    'UT' => 'Utah',
    'VT' => 'Vermont',
    'VA' => 'Virginia',
    'WA' => 'Washington',
    'WV' => 'West Virginia',
    'WI' => 'Wisconsin',
    'WY' => 'Wyoming',
    'country-AR' => 'Argentina',
    'country-BZ' => 'Belize',
    'country-BO' => 'Bolivia',
    'country-BR' => 'Brazil',
    'country-CL' => 'Chile',
    'country-CO' => 'Colombia',
    'country-CR' => 'Costa Rica',
    'country-CU' => 'Cuba',
    'country-DO' => 'Dominican Republic',
    'country-EC' => 'Ecuador',
    'country-SV' => 'El Salvador',
    'country-GT' => 'Guatemala',
    'country-GY' => 'Guyana',
    'country-HT' => 'Haiti',
    'country-HN' => 'Honduras',
    'country-MX' => 'Mexico',
    'country-NI' => 'Nicaragua',
    'country-PA' => 'Panama',
    'country-PY' => 'Paraguay',
    'country-PE' => 'Peru',
    'country-SR' => 'Suriname',
    'country-UY' => 'Uruguay',
    'country-VE' => 'Venezuela',
);

function awareid_enqueue_custom_admin_script($hook)
{
    // Only add to certain admin pages
    if ('toplevel_page_aware-verification-form' !== $hook) {
        return;
    }

    $bootstrap_css_handle = 'bootstrap-css';
    $bootstrap_js_handle = 'bootstrap-js';
    $fontawesome_css_handle = 'fontawesome-css';
    $select2_css_handle = 'select2-css';
    $select2_bootstrap_css_handle = 'select2-bootstrap-css';
    $select2_js_handle = 'select2-js';
    $plugin_data = get_plugin_data(__FILE__);
    $version = $plugin_data['Version'];

    // Enqueue styles
    wp_enqueue_style($fontawesome_css_handle,  plugins_url('styles/fontawesome.css', __FILE__), array(),$version, true);
    wp_enqueue_style($bootstrap_css_handle,  plugins_url('styles/bootstrap.min.css', __FILE__), array(), $version);
    wp_enqueue_style($select2_css_handle,  plugins_url('styles/select2.min.css', __FILE__), array(), $version);    
    wp_enqueue_style($select2_bootstrap_css_handle,  plugins_url('styles/select2-bootstrap-5-theme.min.css', __FILE__), array(), $version);

    // Enqueue scripts
    wp_enqueue_script('jquery');
    wp_enqueue_script($bootstrap_js_handle,  plugins_url('js/bootstrap.bundle.min.js', __FILE__), array(), $version, true);
    wp_enqueue_script($select2_js_handle,   plugins_url('js/select2.min.js', __FILE__), array(), $version, true);
    wp_enqueue_script('awareid-admin-settings', plugins_url('js/admin-settings.js', __FILE__), ['jquery', $select2_js_handle], '1.0.0', true);

    // Localize script for AJAX URL
    wp_localize_script('awareid-admin-settings', 'awareVerification', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'update_pubkey_nonce' => wp_create_nonce('update_pubkey_action'),
        'test_server_nonce' => wp_create_nonce('test_awareid_server') 
    ]);

    // Custom script to initialize Select2
    wp_add_inline_script($select2_js_handle, 'jQuery(document).ready(function($) { 
        $("#awareid_field_disallowed_states").select2({
            theme: "bootstrap-5",
            placeholder: "Select states or countries",
            width: "style"
        }); 
    });', 'after');
}
add_action('admin_enqueue_scripts', 'awareid_enqueue_custom_admin_script');

// Hook for adding admin menus
add_action('admin_menu', 'awareid_verification_plugin_menu');

// Action function for above hook
function awareid_verification_plugin_menu()
{
    add_menu_page('Aware Verification Settings', 'Aware Verification', 'manage_options', 'aware-verification-form', 'awareid_verification_plugin_page');
    $nonce = wp_create_nonce('awareid_admin_nonce');
    set_transient('awareid_admin_nonce_' . get_current_user_id(), $nonce, 12 * HOUR_IN_SECONDS);
}

// UI Rendering and setup for main admin page and tabs
function awareid_verification_plugin_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Define your tabs
    $tabs = [
        'settings' => 'Settings',
        'interface' => 'Interface Settings',
        'registered_users' => 'Registered Users'
    ];

    // Get the current tab
    $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';

    // Start HTML output
    echo '<div class="wrap">';
    echo '<h1 class="mb-4">' . esc_html(get_admin_page_title()) . '</h1>';

    // Display tabs using Bootstrap nav-tabs
    echo '<ul class="nav nav-tabs">';
    foreach ($tabs as $tab => $name) {
        $class = ($tab == $current_tab) ? ' active' : '';
        $nonce = wp_create_nonce('awareid_verification_' . $tab . '_nonce');
        echo "<li class='nav-item'>";
        echo '<a class="nav-link' . esc_attr($class) . '" href="?page=aware-verification-form&tab=' . esc_attr($tab) . '&_wpnonce=' . esc_attr($nonce) . '">' . esc_html($name) . '</a>';
        echo "</li>";
    }
    echo '</ul>';

    // Display content based on the current tab
    echo '<div class="tab-content p-4 border border-top-0">';
    switch ($current_tab) {
        case 'settings':
            echo '<form action="options.php" method="post">';
            settings_fields('awareid');
            do_settings_sections('awareid');
            echo '<div class="d-flex align-items-center">';
            echo '<button type="submit" class="btn btn-primary me-3" id="submit">Save Settings</button>';
            echo '<button type="button" class="btn btn-primary me-3" id="test-server-config">';
            echo 'Test Server Configuration';
            echo '<span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true" id="loading-spinner" style="display: none;"></span>';
            echo '</button>';
            echo '<button type="button" class="btn btn-primary" id="update-pubkey-btn">Update Public Key</button>';
            echo '<div id="test-result" class="me-2"></div>';

            echo '</div>';
            echo '</form>';
            break;
        case 'interface':
            echo '<form action="options.php" method="post">';
            settings_fields('awareid_interface_options_group');
            do_settings_sections('awareid_interface_options_group');
            submit_button('Save Interface Settings');
            echo '</form>';
            break;

            case 'registered_users':
                // Verify nonce
                if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'awareid_verification_registered_users_nonce')) {
                    wp_die('Security check failed');
                }
    
                // Pagination setup
                $users_per_page = 20;
                $current_page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
                $offset = ($current_page - 1) * $users_per_page;
    
                // Search functionality
                $search_term = '';
                if (isset($_GET['search']) && isset($_GET['search_nonce'])) {
                    if (wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['search_nonce'])), 'awareid_verification_search_nonce')) {
                        $search_term = sanitize_text_field(wp_unslash($_GET['search']));
                    } else {
                        wp_die('Search security check failed');
                    }
                }
    
                // User query arguments
                $args = [
                    'number' => $users_per_page,
                    'offset' => $offset,
                ];
    
                if (!empty($search_term)) {
                    $args['search'] = '*' . $search_term . '*';
                    $args['search_columns'] = ['user_email', 'user_login'];
                }
    
                // Fetch users
                $users = get_users($args);
    
                // Display search form
                $search_nonce = wp_create_nonce('awareid_verification_search_nonce');
                echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
                echo '<input type="hidden" name="page" value="aware-verification-form">';
                echo '<input type="hidden" name="tab" value="registered_users">';
                echo '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('awareid_verification_registered_users_nonce')) . '">';
                echo '<input type="hidden" name="search_nonce" value="' . esc_attr($search_nonce) . '">';
                echo '<input type="text" name="search" placeholder="Search Users" value="' . esc_attr($search_term) . '">';
                echo '<input type="submit" value="Search">';
                echo '</form>';



            // Display users in a table
            echo '<table class="table">';
            foreach ($users as $user) {
                $user_verified = get_user_meta($user->ID, 'user_verified', true);
                $checked = $user_verified ? ' checked' : '';
                $verification_status = $user_verified ? 'Verification Passed' : 'Not Verified';
                $last_verified = get_user_meta($user->ID, 'last_verified', true);

                echo '<tr>';
                echo '<td>' . esc_html($user->user_email) . '</td>';
                echo '<td>' . esc_html($verification_status) . '</td>';
                echo '<td>' . esc_html($last_verified) . '</td>';
                echo '<td>';
                echo '<form method="post">';
                echo '<input type="hidden" name="user_id" value="' . esc_attr($user->ID) . '">';
                echo '<input type="checkbox" name="user_verified"' . esc_attr($checked) . '>';
                echo '<input type="submit" name="update_user_meta" value="Update">';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';

            // Pagination
            $total_users = count_users();
            $total_pages = ceil($total_users['total_users'] / $users_per_page);

            echo '<nav aria-label="Page navigation">';
            echo '<ul class="pagination">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $pagination_nonce = wp_create_nonce('awareid_verification_registered_users_nonce');
                $pagination_url = add_query_arg([
                    'page' => 'aware-verification-form',
                    'tab' => 'registered_users',
                    'page_num' => $i,
                    '_wpnonce' => $pagination_nonce,
                    'search' => $search_term,
                ], admin_url('admin.php'));
                echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '"><a class="page-link" href="' . esc_url($pagination_url) . '">' . esc_html($i) . '</a></li>';
            }
            echo '</ul>';
            echo '</nav>';

            // Update user meta
            if (isset($_POST['update_user_meta'])) {
                $user_id = sanitize_text_field($_POST['user_id']);
                $user_verified = isset($_POST['user_verified']) ? '1' : '0';
                $current_user_verified = get_user_meta($user_id, 'user_verified', true);
                $last_verified = sanitize_text_field($_POST['last_verified']);
            
                update_user_meta($user_id, 'user_verified', $user_verified);
                update_user_meta($user_id, 'last_verified', $last_verified);
            
                if ($current_user_verified === '1' && $user_verified === '0') {
                    $options = get_option('awareid_options'); // Correct placement
                    if (!isset($options['awareid_field_awareid_domain'], $options['awareid_field_apikey'], $options['awareid_field_realm_name'], $options['awareid_field_client_secret'])) {
                        wp_redirect(add_query_arg(['page' => 'aware-verification-form', 'tab' => 'registered_users'], admin_url('admin.php')));
                        exit; // Use exit instead of throwing an exception
                    }
                    $registration_code = get_user_meta($user_id, 'user_registration_code', true);
                    if ($registration_code === '') {
                        wp_redirect(add_query_arg(['page' => 'aware-verification-form', 'tab' => 'registered_users'], admin_url('admin.php')));
                        exit; // Use exit instead of throwing an exception
                    }
                    $domain = $options['awareid_field_awareid_domain'];
                    $realm = $options['awareid_field_realm_name'];
                    $clientSecret = $options['awareid_field_client_secret'];
                    $apikey = $options['awareid_field_apikey'];
                    $access_token = awareid_get_openid_token($domain, $realm, $clientSecret, $apikey);

                    if (!$access_token) {
                        error_log('Error OpenID token.');
                        wp_redirect(add_query_arg(['page' => 'aware-verification-form', 'tab' => 'registered_users'], admin_url('admin.php')));
                        exit;
                    }
                    $url = $domain . 'onboarding/admin/registration/' . $registration_code;
                    $new_headers = [
                        'apikey' => $apikey,
                        'Authorization' => 'Bearer ' . $access_token
                    ];
                    $args = [
                        'method' => 'DELETE',
                        'headers' => $new_headers,
                    ];
                    $response = wp_remote_request($url, $args);
                    if (is_wp_error($response)) {
                        error_log('Error in DELETE request: ' . esc_html($response->get_error_message()));
                        wp_redirect(add_query_arg(['page' => 'aware-verification-form', 'tab' => 'registered_users'], admin_url('admin.php')));
                        exit;
                    }
                };
                wp_redirect(add_query_arg(array('page' => 'aware-verification-form', 'tab' => 'registered_users'), admin_url('admin.php')));
                exit;
            }
            break;
    }

    echo '</div>';
}

// Initializing admin settings
add_action('admin_init', 'awareid_settings_init');
function awareid_settings_init()
{
    // Register a new setting for "awareid" page
    register_setting('awareid', 'awareid_options');
    // Register a new setting for "awareid_interface_options" page
    register_setting('awareid_interface_options_group', 'awareid_interface_options');

    /*

    Settings area for AwareID Administration

    */
    add_settings_section(
        'awareid_section_developers',
        __('AwareID Settings', 'awareid-wc-integration'),
        'awareid_section_developers_callback',
        'awareid'
    );

    add_settings_field(
        'awareid_field_awareid_domain',
        __('AwareID Domain', 'awareid-wc-integration'),
        'awareid_field_awareid_domain_cb',
        'awareid',
        'awareid_section_developers',
        [
            'label_for' => 'awareid_field_awareid_domain',
            'class' => 'awareid_row',
        ]
    );


    add_settings_field(
        'awareid_field_realm_name',
        __('Realm Name', 'awareid-wc-integration'),
        'awareid_field_realm_name_cb',
        'awareid',
        'awareid_section_developers',
        [
            'label_for' => 'awareid_field_realm_name',
            'class' => 'awareid_row',
        ]
    );

    add_settings_field(
        'awareid_field_client_secret',
        __('Client Secret', 'awareid-wc-integration'),
        'awareid_field_client_secret_cb',
        'awareid',
        'awareid_section_developers',
        [
            'label_for' => 'awareid_field_client_secret',
            'class' => 'awareid_row',
        ]
    );

    add_settings_field(
        'awareid_field_apikey',
        __('API Key', 'awareid-wc-integration'),
        'awareid_field_apikey_cb',
        'awareid',
        'awareid_section_developers',
        [
            'label_for' => 'awareid_field_apikey',
            'class' => 'awareid_row',
        ]
    );

    add_settings_field(
        'awareid_field_pubkey',
        __('Public Key', 'awareid-wc-integration'),
        'awareid_field_pubkey_cb',
        'awareid',
        'awareid_section_developers',
        [
            'label_for' => 'awareid_field_pubkey',
            'class' => 'awareid_row',
        ]
    );

    add_settings_field(
        'awareid_field_geocode',
        __('Geocode Earth API Key', 'awareid-wc-integration'),
        'awareid_field_geocode_cb',
        'awareid',
        'awareid_section_developers',
        [
            'label_for' => 'awareid_field_geocode',
            'class' => 'awareid_row',
        ]
    );

    add_settings_field(
        'awareid_field_ipinfo',
        __('IP Info API Key', 'awareid-wc-integration'),
        'awareid_field_ipinfo_cb',
        'awareid',
        'awareid_section_developers',
        [
            'label_for' => 'awareid_field_ipinfo',
            'class' => 'awareid_row',
        ]
    );

    add_settings_field(
        'awareid_field_development_field',
        __('Enable Development License For Document Capture? <span class="awareid-custom-title"></span>', 'awareid-wc-integration'),
        'awareid_field_development_field_cb',
        'awareid',
        'awareid_section_developers'
    );
    
    add_settings_field(
        'awareid_field_development_license',
        __('Development License Key', 'awareid-wc-integration'),
        'awareid_field_development_license_cb',
        'awareid',
        'awareid_section_developers',
        [
            'label_for' => 'awareid_field_development',
            'class' => 'awareid_row',
        ]
    );

    add_settings_field(
        'awareid_field_redirect_checkbox_field',
        __('Allow Unregistered Users to Checkout', 'awareid-wc-integration'),
        'awareid_field_redirect_checkbox_cb',
        'awareid',
        'awareid_section_developers'
    );

    add_settings_field(
        'awareid_field_minimum_age',
        __('Minimum Age', 'awareid-wc-integration'),
        'awareid_field_minimum_age_cb',
        'awareid',
        'awareid_section_developers',
        [
            'label_for' => 'awareid_field_minimum_age',
            'class' => 'awareid_row',
        ]
    );

    add_settings_field(
        'awareid_field_checkbox_field',
        __('ReValidate Face?', 'awareid-wc-integration'),
        'awareid_field_checkbox_field_cb',
        'awareid',
        'awareid_section_developers'
    );


    add_settings_field(
        'awareid_field_duration_number',
        __('Duration Number', 'awareid-wc-integration'),
        'awareid_field_duration_number_cb',
        'awareid',
        'awareid_section_developers'
    );

    add_settings_field(
        'awareid_field_duration_unit',
        __('Duration Unit', 'awareid-wc-integration'),
        'awareid_field_duration_unit_cb',
        'awareid',
        'awareid_section_developers'
    );


    add_settings_field(
        'awareid_field_disallowed_states',
        'Disallowed States or Countries',
        'awareid_disallowed_states_callback',
        'awareid',
        'awareid_section_developers',
        [
            'label_for' => 'awareid_field_disallowed_states',
            'class' => 'awareid_row',
        ]
    );

    add_settings_field(
        'awareid_field_security_level',
        __('Security Level', 'awareid-wc-integration'),
        'awareid_field_security_level_dd',
        'awareid',
        'awareid_section_developers',
        [
            'label_for' => 'awareid_field_security_level',
            'class' => 'awareid_row',
        ]
    );

    /* 

    Interface Settings

    */

    add_settings_section(
        'awareid_interface_options_section',
        __('Interface Settings', 'awareid-wc-integration'),
        'awareid_interface_options_section_callback',
        'awareid_interface_options_group'
    );

    add_settings_field(
        'awareid_interface_options_welcome_tagline',
        __('Welcome Tagline', 'awareid-wc-integration'),
        'awareid_interface_options_group_tagline_cb',
        'awareid_interface_options_group',
        'awareid_interface_options_section',
        [
            'label_for' => 'awareid_interface_options_welcome_tagline',
            'class' => 'awareid_row',
        ]
    );
}

// Callback for rendering AwareID title
function awareid_section_developers_callback($args)
{
    ?>
    <p id="<?php echo esc_attr($args['id']); ?>">
        <?php esc_html_e('Enter your AwareID backend settings here.', 'awareid-wc-integration'); ?>
    </p>
    <?php
}

// Callback for rendering interface options
function awareid_interface_options_section_callback($args)
{
    ?>
    <p id="<?php echo esc_attr($args['id']); ?>">
        <?php esc_html_e('Configure your interface options here.', 'awareid_interface_options_group'); ?>
    </p>
    <?php
}

function awareid_field_awareid_domain_cb($args)
{
    // Get the value of the setting we've registered with register_setting()
    $options = get_option('awareid_options');
    $awareid_domain = '';
    if (isset($options['awareid_field_awareid_domain'])) {
        $awareid_domain = $options['awareid_field_awareid_domain'];
    }
    ?>
    <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
        name="awareid_options[<?php echo esc_attr($args['label_for']); ?>]" value="<?php echo esc_attr($awareid_domain); ?>"
        class="form-control">
    <div id="domain-error" class="invalid-feedback" style="display: none;"></div>
    <?php
}

function awareid_field_realm_name_cb($args)
{
    // Get the value of the setting we've registered with register_setting()
    $options = get_option('awareid_options');
    $realm_name = '';
    if (isset($options['awareid_field_realm_name'])) {
        $realm_name = $options['awareid_field_realm_name'];
    }
    ?>
    <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
        name="awareid_options[<?php echo esc_attr($args['label_for']); ?>]" value="<?php echo esc_attr($realm_name); ?>"
        class="form-control">
    <div id="realm-error" class="invalid-feedback" style="display: none;"></div>
    <?php
}

function awareid_field_client_secret_cb($args)
{
    $options = get_option('awareid_options');
    $client_secret = '';
    if (isset($options['awareid_field_client_secret'])) {
        $client_secret = $options['awareid_field_client_secret'];
    }
    ?>
    <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
        name="awareid_options[<?php echo esc_attr($args['label_for']); ?>]" value="<?php echo esc_attr($client_secret); ?>"
        class="form-control">
    <?php
}

function awareid_field_apikey_cb($args)
{
    $options = get_option('awareid_options');
    $apikey = '';
    if (isset($options['awareid_field_apikey'])) {
        $apikey = $options['awareid_field_apikey'];
    }
    ?>
    <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
        name="awareid_options[<?php echo esc_attr($args['label_for']); ?>]" value="<?php echo esc_attr($apikey); ?>"
        class="form-control">
    <?php
}

function awareid_field_geocode_cb($args)
{
    $options = get_option('awareid_options');
    $geocode = '';
    if (isset($options['awareid_field_geocode'])) {
        $geocode = $options['awareid_field_geocode'];
    }
    ?>
    <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
        name="awareid_options[<?php echo esc_attr($args['label_for']); ?>]" value="<?php echo esc_attr($geocode); ?>"
        class="form-control">
    <?php
}

function awareid_field_ipinfo_cb($args)
{
    $options = get_option('awareid_options');
    $geocode = '';
    if (isset($options['awareid_field_ipinfo'])) {
        $geocode = $options['awareid_field_ipinfo'];
    }
    ?>
    <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
        name="awareid_options[<?php echo esc_attr($args['label_for']); ?>]" value="<?php echo esc_attr($geocode); ?>"
        class="form-control">
    <?php
}

function awareid_field_pubkey_cb($args)
{
    $options = get_option('awareid_options');
    $pubkey = '';
    if (isset($options['awareid_field_pubkey'])) {
        $pubkey = $options['awareid_field_pubkey'];
    }
    ?>
    <textarea
        id="<?php echo esc_attr($args['label_for']); ?>"
        name="awareid_options[<?php echo esc_attr($args['label_for']); ?>]" rows="4" class="form-control"><?php echo esc_attr($pubkey); ?></textarea>
    <?php
}

function awareid_field_development_field_cb()
{
    $options = get_option('awareid_options');
    $checkbox_value = isset($options['awareid_field_development_field']) ? $options['awareid_field_development_field'] : '';
    ?>
    <input class="form-control" type='checkbox' id='awareid_field_development_field' name='awareid_options[awareid_field_development_field]' <?php checked($checkbox_value, 1); ?> value='1'>
    <?php
}

function awareid_field_development_license_cb($args)
{
    $options = get_option('awareid_options');
    $development_license_value = isset($options['awareid_field_development_license']) ? $options['awareid_field_development_license'] : '';
    ?>
        <input type='text' id='awareid_field_development_license' class="form-control" name='awareid_options[awareid_field_development_license]'
        value='<?php echo esc_attr($development_license_value); ?>'>
    <?php
    }


function awareid_field_redirect_checkbox_cb()
{
    $options = get_option('awareid_options');
    $checkbox_value = isset($options['awareid_field_redirect_checkbox_field']) ? $options['awareid_field_redirect_checkbox_field'] : '';
    ?>
    <input type='checkbox' id='awareid_field_redirect_checkbox_field'
        name='awareid_options[awareid_field_redirect_checkbox_field]' <?php checked($checkbox_value, 1); ?> value='1'>
    <?php
}

function awareid_field_minimum_age_cb($args)
{
    $options = get_option('awareid_options');
    $minimum_age = '';
    if (isset($options['awareid_field_minimum_age'])) {
        $minimum_age = $options['awareid_field_minimum_age'];
    }
    ?>
    <input type="number" id="<?php echo esc_attr($args['label_for']); ?>"
        name="awareid_options[<?php echo esc_attr($args['label_for']); ?>]" value="<?php echo esc_attr($minimum_age); ?>"
        class="form-control" min="0" max="100" aria-describedby="ageHelp" required>

    <div id="realm-error" class="invalid-feedback">
        Please enter a number between 0 and 100.
    </div>
    <small id="ageHelp" class="form-text text-muted">Enter an age between 0 and 100.</small>
    <?php
}



function awareid_field_checkbox_field_cb()
{
    $options = get_option('awareid_options');
    $checkbox_value = isset($options['awareid_field_checkbox_field']) ? $options['awareid_field_checkbox_field'] : '';
    ?>
    <input type='checkbox' id='awareid_field_checkbox_field' name='awareid_options[awareid_field_checkbox_field]' <?php checked($checkbox_value, 1); ?> value='1'>
    <?php
}


function awareid_field_duration_number_cb()
{
    $options = get_option('awareid_options');
    $duration_number = isset($options['awareid_field_duration_number']) ? $options['awareid_field_duration_number'] : '';

    ?>
    <input type='number' min='0' id='awareid_field_duration_number' name='awareid_options[awareid_field_duration_number]'
        value='<?php echo esc_attr($duration_number); ?>'>
    <?php
}

function awareid_field_duration_unit_cb()
{
    // Fetch the option with a default value in case it doesn't exist
    $options = get_option('awareid_options', array('awareid_field_duration_unit' => 'hours'));

    // Ensure that $options is an array
    if (!is_array($options)) {
        $options = array('awareid_field_duration_unit' => 'hours');
    }

    ?>
    <select id='awareid_field_duration_unit' name='awareid_options[awareid_field_duration_unit]'>
        <option value='hours' <?php selected($options['awareid_field_duration_unit'], 'hours'); ?>>Hours</option>
        <option value='days' <?php selected($options['awareid_field_duration_unit'], 'days'); ?>>Days</option>
    </select>
    <?php
}

function awareid_field_security_level_dd($args)
{
    $options = get_option('awareid_options');
    $security_level = '';
    if (isset($options['awareid_field_security_level'])) {
        $security_level = $options['awareid_field_security_level'];
    }
    if (empty($security_level)) {
        $security_level = '4';
    }
    ?>
    <select id="<?php echo esc_attr($args['label_for']); ?>"
        name="awareid_options[<?php echo esc_attr($args['label_for']); ?>]" class="form-control">
        <option value="2" <?php selected($security_level, '2'); ?>>2 - Low Security, Easier Capture</option>
        <option value="4" <?php selected($security_level, '4'); ?>>4 - Balanced Security and Capture</option>
        <option value="6" <?php selected($security_level, '6'); ?>>6 - High Security, Harder Capture</option>
    </select>
    <div id="realm-error" class="invalid-feedback" style="display: none;"></div>
    <?php
}


function awareid_disallowed_states_callback($args) {
    global $awareid_states;
    $options = get_option('awareid_options');

    if (!isset($options['awareid_disallowed_states']) || !is_array($options['awareid_disallowed_states'])) {
        $options['awareid_disallowed_states'] = [];
    }

    echo '<select multiple class="form-select" name="awareid_options[disallowed_states][]" id="awareid_field_disallowed_states">';

    $isCountryGroupStarted = false; // Flag to indicate whether the countries group has started

    // Start the U.S. States group
    echo '<optgroup label="U.S. States">';
    foreach ($awareid_states as $key => $value) {
        if (strpos($key, 'country-') === 0) {
            if (!$isCountryGroupStarted) {
                // Close the U.S. States group and start the Latin American Countries group
                echo '</optgroup><optgroup label="Latin American Countries">';
                $isCountryGroupStarted = true; // Prevent further group starts
            }
            // For countries, strip the 'country-' prefix for display
            $displayKey = str_replace('country-', '', $key);
        } else {
            // Keep the key as is for U.S. states
            $displayKey = $key;
        }
        $selected = in_array($displayKey, $options['awareid_disallowed_states']) ? 'selected' : '';
        echo '<option value="' . esc_attr($displayKey) . '"' . esc_attr($selected) . '>' . esc_html($value) . '</option>';
    }
    // Close the last optgroup
    echo '</optgroup>'; 

    echo '</select>';
}


add_action('wp_ajax_update_pubkey_action', 'awareid_handle_pubkey_update');

function awareid_handle_pubkey_update() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'update_pubkey_action')) {
        wp_die('Nonce verification failed, action not allowed.', 403);
    }
    if (!current_user_can('manage_options')) {
        wp_die('You are not authorized to perform this action.');
    }

    $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
    $realm = isset($_POST['realm']) ? sanitize_text_field($_POST['realm']) : '';
    $clientSecret = isset($_POST['client_secret']) ? sanitize_text_field($_POST['client_secret']) : '';
    $apikey = isset($_POST['apikey']) ? sanitize_text_field($_POST['apikey']) : '';

    $access_token = awareid_get_openid_token($domain, $realm, $clientSecret, $apikey);
    if (!$access_token) {
        echo('Authentication failed, please check configuration.');
        wp_die();
    }
    $public_key = awareid_get_public_key($domain, $apikey, $access_token);
    if (!$public_key) {
        echo('Test Failed, please verify your API Key.');
        wp_die();
    }
    echo(esc_html($public_key));
    wp_die(); // End AJAX request or function call
}




function
    awareid_interface_options_group_tagline_cb(
    $args
) {
    // Get the value of the setting we've registered with register_setting()
    $options = get_option('awareid_interface_options');
    $welcome_tagline = '';
    if (isset($options['awareid_interface_options_welcome_tagline'])) {
        $welcome_tagline = $options['awareid_interface_options_welcome_tagline'];
    }
    ?>
    <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
        name="awareid_interface_options[<?php echo esc_attr($args['label_for']); ?>]"
        value="<?php echo esc_attr($welcome_tagline); ?>" class="form-control">
    <?php
}


function awareid_validate_server_callback()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'test_awareid_server')) {
        wp_send_json_error(['message' => 'Nonce verification failed, action not allowed.'], 403);
        wp_die();
    }
    $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
    $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
    $realm = isset($_POST['realm_name']) ? sanitize_text_field($_POST['realm_name']) : '';
    $clientSecret = isset($_POST['client_secret']) ? sanitize_text_field($_POST['client_secret']) : '';
    $apikey = isset($_POST['apikey']) ? sanitize_text_field($_POST['apikey']) : '';

    $access_token = awareid_get_openid_token($domain, $realm, $clientSecret, $apikey);
    if (!$access_token) {
        wp_send_json_error(['message' => 'Authentication failed, please check configuration.']);
        wp_die();
    }
    
    $public_key = awareid_get_public_key($domain, $apikey, $access_token);
    if (!$public_key) {
        wp_send_json_error(['message' => 'Failed to retrieve public key, please check configuration.']);
        wp_die();
    }

    // Check if the body contains the specific phrase
    if (strpos($public_key, 'BEGIN PUBLIC KEY') !== false) {
        wp_send_json_success(['message' => 'Server properly configured!']);
    } else {
        wp_send_json_error(['message' => "Server configuration incorrect or service unavailable."]);
    }

    wp_die(); // This is required to terminate immediately and return a proper response
}


add_action('wp_ajax_test_awareid_server', 'awareid_validate_server_callback');

function awareid_get_openid_token($domain, $realm, $clientSecret, $apikey) {
    $openid_url = $domain . 'auth/realms/' . $realm . '/protocol/openid-connect/token';
    $openid_body = [
        'client_id' => 'bimaas-b2b',
        'client_secret' => $clientSecret,
        'grant_type' => 'client_credentials',
        'scope' => 'openid'
    ];
    $openid_args = [
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        'body' => $openid_body
    ];
    $openid_response = wp_remote_post($openid_url, $openid_args);
    
    if (is_wp_error($openid_response)) {
        return false; // Handle the error appropriately
    }
    
    $openid_decoded_response = json_decode(wp_remote_retrieve_body($openid_response), true);
    return $openid_decoded_response['access_token'] ?? false;
}

function awareid_get_public_key($domain, $apikey, $access_token) {
    $url = $domain . 'onboarding/admin/getPublicKey';
    $headers = [
        'apikey' => $apikey,
        'Authorization' => 'Bearer ' . $access_token
    ];
    $args = [
        'method' => 'GET',
        'headers' => $headers,
    ];
    
    $response = wp_remote_request($url, $args);
    if (is_wp_error($response)) {
        return false; // Handle the error appropriately
    }
    
    $public_key = wp_remote_retrieve_body($response);
    // Depending on your need, you might want to validate the public key here
    
    return $public_key;
}
