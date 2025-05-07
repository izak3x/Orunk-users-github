<?php
/**
 * Plugin Name: Orunk Users
 * Description: A custom plugin for orunk.com to allow users to purchase individual APIs and features with tiered plans and payment gateways.
 * Version: 1.14.0 // Updated version for Activation Tracking feature
 * Author: Your Name
 * Text Domain: orunk-users
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 *
 * @package OrunkUsers
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// --- Define Plugin Constants FIRST ---
// *** MODIFIED: Updated version to match DB schema changes from Phase 1 ***
if (!defined('ORUNK_USERS_VERSION')) { define('ORUNK_USERS_VERSION', '1.14.0'); }
if (!defined('ORUNK_USERS_PLUGIN_DIR')) { define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path(__FILE__)); }
if (!defined('ORUNK_USERS_PLUGIN_URL')) { define('ORUNK_USERS_PLUGIN_URL', plugin_dir_url(__FILE__)); }
if (!defined('ORUNK_USERS_BASE_NAME')) { define('ORUNK_USERS_BASE_NAME', plugin_basename(__FILE__)); }

// --- Include Core Files ---
// *** MODIFIED: Added new License API Handler class ***
$core_files = [
    'includes/class-orunk-db.php',
    'includes/class-orunk-core.php',
    'includes/class-orunk-access.php',
    'includes/abstract-orunk-payment-gateway.php',
    'includes/countries.php',
    'includes/class-orunk-api-key-manager.php', // Needed for generic key generation
    'includes/class-orunk-purchase-manager.php',
    'includes/class-orunk-user-actions.php',
    'includes/class-orunk-otp-handler.php',
    'includes/class-orunk-webhook-handler.php',         // Stripe Webhooks
    'includes/class-orunk-paypal-webhook-handler.php',  // PayPal Webhooks
    'includes/class-orunk-license-api-handler.php',     // <<<--- NEW: Handles dynamic license API calls
    'includes/class-orunk-ajax-handlers.php',           // Remaining Frontend AJAX
    'features/bin-api/endpoints/class-bin-proxy-endpoint.php', // BIN Proxy Endpoint (Example Feature)
    'admin/class-orunk-settings.php',
    'admin/class-orunk-admin.php',
    'admin/class-orunk-products.php',
    'admin/class-orunk-reports.php',
    'public/class-orunk-frontend.php',
];
foreach($core_files as $file) {
    $path = ORUNK_USERS_PLUGIN_DIR . $file;
    if (file_exists($path)) {
        require_once $path;
    } else {
        error_log("Orunk Users CRITICAL ERROR: Required file not found - $path. Plugin initialization may fail.");
        add_action('admin_notices', function() use ($path) {
             echo '<div class="notice notice-error is-dismissible"><p><strong>Orunk Users Error:</strong> Required plugin file missing (<code>' . esc_html(basename($path)) . '</code>). Plugin functionality may be broken. Please reinstall or check plugin files.</p></div>';
        });
    }
}

// Include Frontend Archive AJAX Handlers
$archive_product_ajax_handler_file = ORUNK_USERS_PLUGIN_DIR . 'includes/frontend/ajax/archive-product-handlers.php';
if ( file_exists( $archive_product_ajax_handler_file ) ) {
    require_once $archive_product_ajax_handler_file;
} else {
    error_log('Orunk Users Error: archive-product-handlers.php not found.');
}


// --- Activation Hook (Unchanged) ---
function orunk_users_activate() {
    error_log('Orunk Users: Running Activation Hook...');
    if (!class_exists('Custom_Orunk_DB')) { $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php'; if (file_exists($db_path)) { require_once $db_path; } else { error_log('Orunk Users Activation Error: DB Class file missing.'); return; } }
    update_option('orunk_users_version', ORUNK_USERS_VERSION); // Ensure latest version is stored
    orunk_users_run_db_updates(); // Run DB updates on activation
    flush_rewrite_rules();
    set_transient('orunk_users_activated', true, 31);
    error_log('Orunk Users: Activation Hook Finished.');
}
register_activation_hook(__FILE__, 'orunk_users_activate');

// --- Function to run DB updates (Unchanged) ---
function orunk_users_run_db_updates() {
    // Ensure DB class is available
    if (!class_exists('Custom_Orunk_DB')) {
        $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
        if (file_exists($db_path)) { require_once $db_path; }
        else { error_log('Orunk Users DB Update Error: DB Class file missing.'); return; }
    }
    error_log('Orunk Users: Starting DB Updates (Target Version: ' . ORUNK_USERS_VERSION . ')...');
    if (!function_exists('dbDelta')) { require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); }
    if (class_exists('Custom_Orunk_DB') && method_exists('Custom_Orunk_DB', 'create_db_tables')) {
        $db = new Custom_Orunk_DB();
        $db->create_db_tables(); // This now includes activation tracking table/columns
        error_log('Orunk Users: Ran dbDelta via Custom_Orunk_DB::create_db_tables.');
    } else { error_log('Orunk Users DB Update Error: Custom_Orunk_DB class or create_db_tables method not found.'); }
    error_log('Orunk Users: Finished DB Updates.');
}

// --- Check plugin version on plugins_loaded (Unchanged) ---
function orunk_users_check_version() {
    $installed_version = get_option('orunk_users_db_version'); // Use a separate option for DB version tracking
    if (!$installed_version || version_compare($installed_version, ORUNK_USERS_VERSION, '<')) {
        error_log('Orunk Users: DB Version mismatch or first run (Installed: ' . ($installed_version ?: 'None') . ', Required: ' . ORUNK_USERS_VERSION . '). Running DB updates.');
        orunk_users_run_db_updates();
        update_option('orunk_users_db_version', ORUNK_USERS_VERSION); // Update the DB version marker
    }
}
add_action('plugins_loaded', 'orunk_users_check_version', 22); // Priority 22


// In orunk-users.php
// ... other includes ...
$stats_hooks_file = ORUNK_USERS_PLUGIN_DIR . 'includes/stats-tracking-hooks.php';
if (file_exists($stats_hooks_file)) {
    require_once $stats_hooks_file;
} else {
    error_log('Orunk Users Error: stats-tracking-hooks.php not found.');
}
// ...


/**
 * Initialize the plugin components.
 * *** MODIFIED: Removed Convojet Handler instantiation, added hook for generic license gen, added license API route registration ***
 */
function orunk_users_init() {

    // Load Text Domain
    load_plugin_textdomain('orunk-users', false, dirname(ORUNK_USERS_BASE_NAME) . '/languages/');

    // Load Composer vendor autoload (for Stripe/PayPal SDKs)
    if (file_exists(ORUNK_USERS_PLUGIN_DIR . 'vendor/autoload.php')) {
        require_once ORUNK_USERS_PLUGIN_DIR . 'vendor/autoload.php';
    }

/**
 * Enqueue scripts and styles for the Orunk User Dashboard.
 */
function orunk_enqueue_dashboard_scripts_styles() {
    // Only load these scripts and styles on the page using the dashboard template
    if (is_page_template('page-orunk-dashboard.php')) {

        // Define plugin URL and version safely
        if (!defined('ORUNK_USERS_PLUGIN_URL')) {
             // Adjust the path if this code is not in the main plugin file
             // Example: If this code is in orunk-users.php, __FILE__ is correct
             // If in public/class-orunk-frontend.php, use dirname(__FILE__, 2)
             define('ORUNK_USERS_PLUGIN_URL', plugin_dir_url(dirname(__FILE__, (strpos(__FILE__, 'public') !== false ? 2 : 1) )) );
        }
        if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
             // Define the directory path similarly
             define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__, (strpos(__FILE__, 'public') !== false ? 2 : 1) )) );
        }
        $plugin_url = ORUNK_USERS_PLUGIN_URL;
        $plugin_dir = ORUNK_USERS_PLUGIN_DIR;
        $version = defined('ORUNK_USERS_VERSION') ? ORUNK_USERS_VERSION : '1.0.2'; // Increment version

        // --- Enqueue JavaScript ---
        $dashboard_js_path = $plugin_url . 'assets/js/dashboard/';

        wp_enqueue_script('orunk-dashboard-main', $dashboard_js_path . 'main.js', array('jquery'), $version, true);
        wp_enqueue_script('orunk-dashboard-profile', $dashboard_js_path . 'profile.js', array('orunk-dashboard-main'), $version, true);
        wp_enqueue_script('orunk-dashboard-billing', $dashboard_js_path . 'billing.js', array('orunk-dashboard-main'), $version, true);
        wp_enqueue_script('orunk-dashboard-services', $dashboard_js_path . 'services.js', array('orunk-dashboard-main'), $version, true);
        wp_enqueue_script('orunk-dashboard-history', $dashboard_js_path . 'history.js', array('orunk-dashboard-main'), $version, true);


        // --- Enqueue CSS Partials ---
        $css_base_path = $plugin_url . 'assets/css/dashboard/';
        $css_dir_path = $plugin_dir . 'assets/css/dashboard/'; // For filemtime check

        // Define the CSS files in the order you want them loaded (cascade matters!)
        $css_files = [
            'base'       => 'base.css',
            'buttons'    => 'buttons.css',
            'forms'      => 'forms.css',
            'cards'      => 'cards.css',
            'tables'     => 'tables.css',
            'modals'     => 'modals.css',
            'components' => 'components.css',
            'utilities'  => 'utilities.css',
        ];

        $main_style_handle = 'orunk-dashboard-base'; // Use the first file as the base dependency handle

        foreach ($css_files as $handle_suffix => $filename) {
            $handle = 'orunk-dashboard-' . $handle_suffix;
            $filepath = $css_dir_path . $filename;
            $file_url = $css_base_path . $filename;
            // Use filemtime for versioning to help with browser caching during development
            $file_version = file_exists($filepath) ? filemtime($filepath) : $version;
            // All other CSS files depend on the base file to ensure correct cascading
            $dependency = ($handle === $main_style_handle) ? array() : array($main_style_handle);

            wp_enqueue_style(
                $handle,
                $file_url,
                $dependency,
                $file_version
                // No media type specified, defaults to 'all'
            );
        }

        // --- Remove Original Single CSS Enqueue ---
        // Make sure you REMOVE the line that enqueues the original 'dashboard-style.css' if it exists elsewhere
        // For example, comment out or delete:
        // wp_enqueue_style( 'orunk-users-dashboard-style', ORUNK_USERS_PLUGIN_URL . 'assets/css/dashboard-style.css', array(), $css_version );

    }
}
// Hook the function to the script loading action (ensure this line exists and runs)
add_action('wp_enqueue_scripts', 'orunk_enqueue_dashboard_scripts_styles', 20);

/**
 * Enqueue scripts and styles for the Orunk Frontend Admin Interface.
 */
function orunk_enqueue_frontend_admin_assets() {
    // Only load these assets on the page using our custom template
    // Ensure 'orunk-admin-frontend.php' is the correct template filename in your theme.
    if ( is_page_template( 'orunk-admin-frontend.php' ) ) {

        $plugin_url = ORUNK_USERS_PLUGIN_URL; // Assuming ORUNK_USERS_PLUGIN_URL is defined
        $plugin_dir = ORUNK_USERS_PLUGIN_DIR; // Assuming ORUNK_USERS_PLUGIN_DIR is defined
        $version = defined( 'ORUNK_USERS_VERSION' ) ? ORUNK_USERS_VERSION : '1.0.0';

        // --- Enqueue Main CSS for Frontend Admin ---
        $css_file_path = $plugin_dir . 'assets/css/orunk-admin-frontend/admin-main.css';
        $css_file_url = $plugin_url . 'assets/css/orunk-admin-frontend/admin-main.css';
        if ( file_exists( $css_file_path ) ) {
            wp_enqueue_style(
                'orunk-frontend-admin-main-style',
                $css_file_url,
                array(), // Add dependencies if any (e.g., Font Awesome if not loaded by template)
                filemtime( $css_file_path ) // Versioning for cache busting
            );
        } else {
            // Log error or add admin notice if critical CSS is missing
            error_log('Orunk Users Error: admin-main.css not found at ' . $css_file_path);
        }

        // --- Enqueue JavaScript Files ---
        $js_base_url = $plugin_url . 'assets/js/orunk-admin-frontend/';
        $js_base_dir = $plugin_dir . 'assets/js/orunk-admin-frontend/';

        // Core/Main JS - should be loaded first if others depend on it
        $admin_main_js_path = $js_base_dir . 'admin-main.js';
        if ( file_exists( $admin_main_js_path ) ) {
            wp_enqueue_script(
                'orunk-frontend-admin-main',
                $js_base_url . 'admin-main.js',
                array( 'jquery' ), // jQuery is a common dependency
                filemtime( $admin_main_js_path ),
                true // Load in footer
            );
        } else {
            error_log('Orunk Users Error: admin-main.js not found at ' . $admin_main_js_path);
        }

        // Section-specific JS files - list them and set 'orunk-frontend-admin-main' as a dependency
        $js_modules = [
            'users-purchases' => 'admin-users-purchases.js',
            'features-plans'  => 'admin-features-plans.js',
            'categories'      => 'admin-categories.js',
            'payment-gateways'=> 'admin-payment-gateways.js',
        ];

        foreach ( $js_modules as $handle_suffix => $filename ) {
            $file_path = $js_base_dir . $filename;
            if ( file_exists( $file_path ) ) {
                wp_enqueue_script(
                    'orunk-frontend-admin-' . $handle_suffix,
                    $js_base_url . $filename,
                    array( 'orunk-frontend-admin-main' ), // Depends on the main admin JS
                    filemtime( $file_path ),
                    true
                );
            } else {
                 error_log('Orunk Users Error: ' . $filename . ' not found at ' . $file_path);
            }
        }
    }
}
add_action( 'wp_enqueue_scripts', 'orunk_enqueue_frontend_admin_assets', 20 );

    // --- Include AJAX Handler Files (Unchanged) ---
    $ajax_admin_handlers_dir = ORUNK_USERS_PLUGIN_DIR . 'includes/admin/ajax/';
    $admin_handler_files = [ /* ... */ 'admin-ajax-helpers.php','admin-users-ajax-handlers.php','admin-purchase-status-ajax-handlers.php','admin-features-plans-ajax-handlers.php','admin-gateaway-ajax-handlers.php','admin-manage-feature-categories-ajax-handlers.php',];
    foreach ($admin_handler_files as $handler_file) { $handler_path = $ajax_admin_handlers_dir . $handler_file; if (file_exists($handler_path)) { require_once $handler_path; } else { error_log("Orunk Users Error: Admin AJAX handler file not found: " . $handler_path); } }
    $ajax_frontend_handlers_dir = ORUNK_USERS_PLUGIN_DIR . 'includes/frontend/ajax/';
    $frontend_handler_files = [ /* ... */ 'user-profile-handlers.php',];
    foreach ($frontend_handler_files as $handler_file) { $handler_path = $ajax_frontend_handlers_dir . $handler_file; if (file_exists($handler_path)) { require_once $handler_path; } else { error_log("Orunk Users Error: Frontend AJAX handler file not found: " . $handler_path); } }

    // --- Instantiate Core Components ---
    $orunk_ajax_handlers = null; if (class_exists('Orunk_AJAX_Handlers')) { $orunk_ajax_handlers = new Orunk_AJAX_Handlers(); } else { error_log('Orunk Users Init Warning: Orunk_AJAX_Handlers class not found.'); }
    if (class_exists('Orunk_OTP_Handler')) { new Orunk_OTP_Handler(); }
    if (class_exists('Orunk_Bin_Proxy_Endpoint')) { $bin_proxy = new Orunk_Bin_Proxy_Endpoint(); $bin_proxy->register_hooks(); }

    // --- Initialize Admin components (Unchanged) ---
    if (is_admin()) { /* ... admin class instantiations ... */ if (class_exists('Custom_Orunk_Admin')) { $admin = new Custom_Orunk_Admin(); $admin->init(); } if (class_exists('Custom_Orunk_Products')) { $products_admin = new Custom_Orunk_Products(); $products_admin->init(); } if (class_exists('Custom_Orunk_Settings')) { $settings_admin = new Custom_Orunk_Settings(); $settings_admin->init(); } if (class_exists('Custom_Orunk_Reports')) { $reports_admin = new Custom_Orunk_Reports(); $reports_admin->init(); } if (get_transient('orunk_users_activated')) { add_action('admin_notices', 'orunk_users_activation_notice'); delete_transient('orunk_users_activated'); } }

    // --- Initialize Public components (Unchanged) ---
    if (class_exists('Custom_Orunk_Frontend')) { /* ... frontend class instantiation ... */ $GLOBALS['orunk_frontend_instance'] = new Custom_Orunk_Frontend(); if (isset($GLOBALS['orunk_frontend_instance']) && method_exists($GLOBALS['orunk_frontend_instance'], 'init')) { $GLOBALS['orunk_frontend_instance']->init(); error_log("Orunk Users: Custom_Orunk_Frontend initialized."); } else { error_log("Orunk Users ERROR: Failed to initialize Custom_Orunk_Frontend instance."); } } else { error_log("Orunk Users WARNING: Custom_Orunk_Frontend class not found."); }

    // --- Initialize Features ---
    // --- Explicitly Include and Initialize Convojet Handler ---
$convojet_handler_path = ORUNK_USERS_PLUGIN_DIR . 'features/convojet-licensing/class-convojet-license-handler.php';
error_log("Orunk Users Init: Attempting to require Convojet handler: " . $convojet_handler_path); // Log path
if (file_exists($convojet_handler_path)) {
    require_once $convojet_handler_path;
    if (class_exists('Convojet_License_Handler')) {
        new Convojet_License_Handler(); // Instantiate it here
        error_log("Orunk Users Init: Explicitly Initialized Feature - Convojet_License_Handler");
    } else {
         error_log("Orunk Users Init FATAL: Convojet_License_Handler class NOT FOUND after require_once.");
    }
} else {
    error_log("Orunk Users Init FATAL: Convojet handler file NOT FOUND at: " . $convojet_handler_path);
}
// --- End Explicit Include ---

// --- Original Dynamic Feature Loading Loop (Can potentially be kept or modified) ---
$feature_files = glob(ORUNK_USERS_PLUGIN_DIR . 'features/*/class-*.php');
// ... (rest of the original loop follows) ...
    // Include feature classes dynamically. If a feature class exists, instantiate it.
    // This loop will still find and instantiate Convojet_License_Handler if the file exists.
    // Its constructor now only hooks UI/Download related actions (as modified in Phase 5).
    $feature_files = glob(ORUNK_USERS_PLUGIN_DIR . 'features/*/class-*.php'); // Search deeper for structured features
    if ($feature_files) {
        foreach ($feature_files as $feature_file) {
            $path_parts = explode('/', str_replace(ORUNK_USERS_PLUGIN_DIR . 'features/', '', $feature_file));
            $class_file_name = basename($feature_file, '.php');

            // Basic check to avoid endpoint classes or non-feature classes
            if (strpos($class_file_name, 'endpoint') !== false || strpos($class_file_name, 'class-feature-') === false) {
                continue;
            }

            // Attempt to derive class name from file name (e.g., class-feature-ad-removal -> Custom_Feature_Ad_Removal)
            // This logic might need adjustment based on your exact naming convention.
            $class_name_base = str_replace(['class-feature-', '-'], ['', '_'], $class_file_name);
            $class_name = 'Custom_Feature_' . implode('', array_map('ucfirst', explode('_', $class_name_base)));
            // Special case for Convojet handler if its naming is different
            if ($class_file_name === 'class-convojet-license-handler') {
                 $class_name = 'Convojet_License_Handler';
            }

            if (file_exists($feature_file)) {
                 require_once $feature_file; // Ensure file is included before class_exists check
                 if (class_exists($class_name)) {
                    try {
                         $feature_instance = new $class_name();
                         // Check if the instance has an init method before calling
                         if (method_exists($feature_instance, 'init')) {
                             $feature_instance->init();
                         }
                         error_log("Orunk Users: Initialized Feature - {$class_name}");
                    } catch (Exception $e) {
                        error_log("Orunk Init Error instantiating feature {$class_name}: " . $e->getMessage());
                    }
                 } else {
                      error_log("Orunk Init Warning: Feature class '{$class_name}' not found in {$feature_file} after include.");
                 }
            }
        }
    }

    // --- Standard Action Hooks (Non-AJAX form submissions - Unchanged) ---
    add_action('admin_post_nopriv_orunk_purchase_plan', 'orunk_redirect_to_checkout');
    add_action('admin_post_orunk_purchase_plan', 'orunk_redirect_to_checkout');
    add_action('admin_post_orunk_switch_plan', 'orunk_handle_plan_switch');
    add_action('admin_post_orunk_cancel_plan', 'orunk_handle_plan_cancel');
    add_action('admin_post_orunk_update_purchase_status', 'handle_update_purchase_status'); // Keep if non-AJAX admin form exists

    // --- Setup Remaining Frontend AJAX Action Hooks (via Orunk_AJAX_Handlers class - Unchanged) ---
    if ($orunk_ajax_handlers) { /* ... hooks for payment intent, payment process, key regen, auto renew, reset link ... */ add_action('wp_ajax_orunk_create_payment_intent', [$orunk_ajax_handlers, 'handle_create_payment_intent']); add_action('wp_ajax_orunk_process_payment', [$orunk_ajax_handlers, 'handle_process_payment']); add_action('wp_ajax_orunk_regenerate_api_key', [$orunk_ajax_handlers, 'handle_regenerate_api_key']); add_action('wp_ajax_orunk_toggle_auto_renew', [$orunk_ajax_handlers, 'handle_toggle_auto_renew']); add_action('wp_ajax_nopriv_orunk_send_reset_link', [$orunk_ajax_handlers, 'handle_send_reset_link']); add_action('wp_ajax_orunk_send_reset_link', [$orunk_ajax_handlers, 'handle_send_reset_link']); } else { error_log('Orunk Users Init Error: AJAX Handler Instance not available for non-admin hooks.'); }

    // --- Setup Profile/Billing AJAX Action Hooks (Point to standalone functions - Unchanged) ---
    add_action('wp_ajax_orunk_update_profile', 'handle_update_profile'); add_action('wp_ajax_orunk_get_billing_address', 'handle_get_billing_address'); add_action('wp_ajax_orunk_save_billing_address', 'handle_save_billing_address');

    // --- Setup Admin AJAX Action Hooks (Point to standalone functions - Unchanged) ---
    add_action('wp_ajax_orunk_admin_get_users_list', 'handle_admin_get_users_list'); add_action('wp_ajax_orunk_admin_get_user_purchases', 'handle_admin_get_user_purchases'); add_action('wp_ajax_orunk_admin_update_purchase_status', 'handle_admin_update_purchase_status'); add_action('wp_ajax_orunk_admin_get_features_plans', 'handle_admin_get_features_plans'); add_action('wp_ajax_orunk_admin_save_feature', 'handle_admin_save_feature'); add_action('wp_ajax_orunk_admin_delete_feature', 'handle_admin_delete_feature'); add_action('wp_ajax_orunk_admin_save_plan', 'handle_admin_save_plan'); add_action('wp_ajax_orunk_admin_delete_plan', 'handle_admin_delete_plan'); add_action('wp_ajax_orunk_admin_get_gateways', 'handle_admin_get_gateways'); add_action('wp_ajax_orunk_admin_save_gateway_settings', 'handle_admin_save_gateway_settings'); add_action('wp_ajax_orunk_admin_get_categories', 'handle_admin_get_categories'); add_action('wp_ajax_orunk_admin_save_category', 'handle_admin_save_category'); add_action('wp_ajax_orunk_admin_delete_category', 'handle_admin_delete_category');

    // --- Register Webhook Routes (Unchanged - points to existing function) ---
    add_action('rest_api_init', 'orunk_register_webhook_routes');

    // *** NEW: Register License API Routes ***
    add_action('rest_api_init', 'orunk_register_license_api_routes');

    // *** NEW: Hook for Generic License Generation ***
    add_action('orunk_purchase_activated', 'orunk_handle_generic_license_generation', 10, 3);

}
add_action('plugins_loaded', 'orunk_users_init', 10); // Initialize after plugins loaded


// --- Enqueue Dashboard Styles Function (Moved outside init) ---
function orunk_users_enqueue_dashboard_styles() {
    // Check if we are on a page using the specific dashboard template
    if (is_page_template('page-orunk-dashboard.php')) {
        if (!defined('ORUNK_USERS_PLUGIN_URL') || !defined('ORUNK_USERS_PLUGIN_DIR')) { if (!defined('ORUNK_USERS_PLUGIN_URL')) { define('ORUNK_USERS_PLUGIN_URL', plugin_dir_url( __FILE__ )); } if (!defined('ORUNK_USERS_PLUGIN_DIR')) { define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path( __FILE__ )); } }
        $css_file_path = ORUNK_USERS_PLUGIN_DIR . 'assets/css/dashboard-style.css';
        $css_file_url = ORUNK_USERS_PLUGIN_URL . 'assets/css/dashboard-style.css';
        $css_version = file_exists($css_file_path) ? filemtime($css_file_path) : '1.0';
        wp_enqueue_style( 'orunk-users-dashboard-style', $css_file_url, array(), $css_version );
    }
}

// --- Webhook Route Registration & Callbacks (Unchanged) ---
function orunk_register_webhook_routes() {
    // Stripe Route
    register_rest_route('orunk-webhooks/v1', '/stripe', array( 'methods' => 'POST', 'callback' => 'orunk_handle_stripe_webhook_callback', 'permission_callback' => '__return_true', ));
    // PayPal Route
    register_rest_route('orunk-webhooks/v1', '/paypal', array( 'methods' => 'POST', 'callback' => 'orunk_handle_paypal_webhook_callback', 'permission_callback' => '__return_true', ));
}
function orunk_handle_stripe_webhook_callback(WP_REST_Request $request) { /* ... unchanged ... */ if (class_exists('Orunk_Webhook_Handler')) { $handler = new Orunk_Webhook_Handler(); return $handler->handle_stripe_event($request); } error_log("Orunk Stripe Webhook Error: Orunk_Webhook_Handler class not found."); return new WP_Error('handler_class_missing', 'Webhook handler class missing.', ['status' => 500]); }
function orunk_handle_paypal_webhook_callback(WP_REST_Request $request) { /* ... unchanged ... */ if (class_exists('Orunk_PayPal_Webhook_Handler')) { $handler = new Orunk_PayPal_Webhook_Handler(); return $handler->handle_paypal_event($request); } error_log("Orunk PayPal Webhook Error: Orunk_PayPal_Webhook_Handler class not found."); return new WP_Error('handler_class_missing', 'Webhook handler class missing.', ['status' => 500]); }


// --- *** NEW: License API Route Registration & Callbacks *** ---
/**
 * Registers the dynamic REST API routes for license validation and deactivation.
 */
function orunk_register_license_api_routes() {
    // Ensure the handler class is available
    if (!class_exists('Orunk_License_Api_Handler')) {
        error_log('Orunk Users FATAL ERROR: Orunk_License_Api_Handler class not found during REST route registration.');
        return;
    }

    $license_api_handler = new Orunk_License_Api_Handler();
    $namespace = 'orunk/v1'; // Consider 'orunk-license/v1' if preferred

    // --- Dynamic Validation/Activation Endpoint ---
    register_rest_route($namespace, '/license/validate', array(
        'methods'             => WP_REST_Server::CREATABLE, // POST method
        'callback'            => [$license_api_handler, 'handle_dynamic_license_validation'],
        'permission_callback' => '__return_true', // Public endpoint; validation logic handles key checks
        'args'                => array( // Define expected arguments for validation & sanitization
            'license_key' => array(
                'required'          => true,
                'type'              => 'string',
                'description'       => __('The license key to validate/activate.', 'orunk-users'),
                'validate_callback' => function($param, $request, $key) { return is_string($param) && !empty($param) && strlen($param) > 10; /* Basic format check */ },
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'site_url' => array(
                'required'          => true,
                'type'              => 'string',
                'format'            => 'uri',
                'description'       => __('The URL of the site requesting activation.', 'orunk-users'),
                'validate_callback' => function($param, $request, $key) { return filter_var($param, FILTER_VALIDATE_URL); },
                'sanitize_callback' => 'esc_url_raw', // Use raw for DB comparison
            ),
             'feature_key' => array(
                'required'          => true,
                'type'              => 'string',
                'description'       => __('The unique feature key this license is for (e.g., convojet_pro).', 'orunk-users'),
                'validate_callback' => function($param, $request, $key) { return is_string($param) && preg_match('/^[a-z0-9_]+$/', $param); }, // Matches keys like 'abc_123'
                'sanitize_callback' => 'sanitize_key',
            ),
            'plugin_version' => array( // Optional
                'required'          => false,
                'type'              => 'string',
                'description'       => __('The version of the client plugin.', 'orunk-users'),
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));

    // --- Dynamic Deactivation Endpoint ---
     register_rest_route($namespace, '/license/deactivate', array(
        'methods'             => WP_REST_Server::CREATABLE, // POST method
        'callback'            => [$license_api_handler, 'handle_dynamic_license_deactivation'],
        // *** IMPORTANT: Replace __return_true with a REAL permission check ***
        // This callback must verify if the request has authority to deactivate.
        // E.g., check if it's an admin action, or if an authenticated user owns the license.
        // Implementing this securely depends heavily on your authentication flow for deactivation.
        'permission_callback' => 'orunk_check_license_deactivation_permission', // <<<--- REPLACE THIS with actual permission check function
        'args'                => array(
             'activation_id' => array( // Preferred method
                'required'          => false,
                'type'              => 'integer',
                'description'       => __('The specific activation record ID to deactivate.', 'orunk-users'),
                'validate_callback' => function($param, $request, $key) { return is_numeric($param) && $param > 0; },
                'sanitize_callback' => 'absint',
            ),
             'license_key' => array( // Fallback method
                'required'          => false, // Required only if activation_id is missing
                'type'              => 'string',
                'description'       => __('License key (used with site_url if activation_id is unknown).', 'orunk-users'),
                 'validate_callback' => function($param, $request, $key) { return is_string($param) && !empty($param); },
                'sanitize_callback' => 'sanitize_text_field',
            ),
             'site_url' => array( // Fallback method
                'required'          => false, // Required only if activation_id is missing
                'type'              => 'string',
                'format'            => 'uri',
                'description'       => __('Site URL (used with license_key if activation_id is unknown).', 'orunk-users'),
                 'validate_callback' => function($param, $request, $key) { return filter_var($param, FILTER_VALIDATE_URL); },
                 'sanitize_callback' => 'esc_url_raw',
            ),
        ),
    ));

    // REMOVED: Old Convojet Specific Route Registration
    /* register_rest_route('orunk/v1', '/license/validate/convojet', ... ); */
}

/**
 * Placeholder permission callback for deactivation endpoint.
 * !!! THIS MUST BE REPLACED with secure logic !!!
 *
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function orunk_check_license_deactivation_permission(WP_REST_Request $request) {
     // Example (INSECURE - REPLACE THIS): Allow only logged-in admins
     // if (!current_user_can('manage_options')) {
     //     return new WP_Error('rest_forbidden', __('Permission denied.', 'orunk-users'), ['status' => 403]);
     // }
     // return true;

     // --- PRODUCTION implementation needs ---
     // 1. Identify the user making the request (e.g., via WP nonce for AJAX from dashboard, or application passwords/JWT for external API calls).
     // 2. Get the activation details (either via activation_id or key/url lookup).
     // 3. Check if the identified user owns the purchase linked to the activation OR if the user is an admin ('manage_options').
     // 4. Return true if authorized, WP_Error otherwise.
     error_log("Orunk Users SECURITY WARNING: Using placeholder permission callback for license deactivation. Implement proper checks!");
     return true; // <<<--- INSECURE PLACEHOLDER
}


// --- *** NEW: Generic License Key Generation on Purchase Activation *** ---
/**
 * Handles license key generation for any feature marked as requiring one.
 * Hooked to 'orunk_purchase_activated'.
 *
 * @param int    $purchase_id The ID of the activated purchase record.
 * @param int    $user_id     The ID of the user.
 * @param array  $details     Details of the activated purchase (should include plan info).
 */
function orunk_handle_generic_license_generation($purchase_id, $user_id, $details) {
    global $wpdb;
    $purchases_table = $wpdb->prefix . 'orunk_user_purchases';

    // Ensure necessary classes are available
    if (!class_exists('Custom_Orunk_DB') || !class_exists('Orunk_Api_Key_Manager')) {
        error_log("Orunk Generic License Gen Error (Purchase #{$purchase_id}): Missing DB or API Key Manager class.");
        return;
    }
    $orunk_db = new Custom_Orunk_DB();
    $api_key_manager = new Orunk_Api_Key_Manager();

    // Get feature key from details (should be populated by Purchase Manager)
    $feature_key = $details['product_feature_key'] ?? null;
    if (empty($feature_key)) {
        error_log("Orunk Generic License Gen Error (Purchase #{$purchase_id}): Missing product_feature_key in details array.");
        return;
    }

    // Check if this feature requires a license key
    $requires_license = $orunk_db->get_feature_requires_license($feature_key);
    if (!$requires_license) {
         error_log("Orunk Generic License Gen Info (Purchase #{$purchase_id}): Feature '{$feature_key}' does not require a license. Skipping generation.");
        return; // Feature doesn't need a key
    }

    // Check if a license key already exists for this purchase (e.g., if hook runs multiple times)
    $existing_key = $details['license_key'] ?? $wpdb->get_var($wpdb->prepare(
        "SELECT license_key FROM {$purchases_table} WHERE id = %d", $purchase_id
    ));

    if (!empty($existing_key) && strlen($existing_key) > 10) { // Basic check if key seems valid
        error_log("Orunk Generic License Gen Info (Purchase #{$purchase_id}): License key already exists. Skipping generation.");
        return;
    }

    // Generate a unique license key
    error_log("Orunk Generic License Gen: Feature '{$feature_key}' requires license. Attempting generation for Purchase #{$purchase_id}.");
    $new_license_key_result = $api_key_manager->generate_unique_api_key($purchase_id); // Use API Key Manager

    if (is_wp_error($new_license_key_result)) {
        error_log("Orunk Generic License Gen Error (Purchase #{$purchase_id}): Failed to generate unique key. Error: " . $new_license_key_result->get_error_message());
        return; // Failed to generate
    }

    $new_license_key = $new_license_key_result;

    // Save the new key to the purchase record
    $updated = $wpdb->update(
        $purchases_table,
        ['license_key' => $new_license_key],
        ['id' => $purchase_id],
        ['%s'], // Format for license_key
        ['%d']  // Format for WHERE id
    );

    if ($updated === false) {
        error_log("Orunk Generic License Gen DB Error (Purchase #{$purchase_id}): Failed to save new license key '{$new_license_key}'. DB Error: " . $wpdb->last_error);
    } elseif ($updated > 0) {
        error_log("Orunk Generic License Gen SUCCESS (Purchase #{$purchase_id}): Saved new license key.");
        // Optional: Trigger a specific action now that the key is generated
        do_action('orunk_license_key_generated', $purchase_id, $user_id, $new_license_key, $feature_key);
    } else {
         error_log("Orunk Generic License Gen Warning (Purchase #{$purchase_id}): DB update for license key affected 0 rows.");
    }
}


// --- Non-AJAX Action Handlers (Unchanged) ---
function orunk_redirect_to_checkout() { /* ... unchanged ... */ $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0; if ($plan_id <= 0 || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'orunk_purchase_plan_' . $plan_id)) { wp_die(__('Security check failed.', 'orunk-users')); } if (!is_user_logged_in()) { $checkout_url = add_query_arg(['plan_id' => $plan_id], home_url('/checkout/')); $login_url = wp_login_url($checkout_url); wp_safe_redirect($login_url); exit; } $user_id = get_current_user_id(); if (!class_exists('Custom_Orunk_Core')) { wp_die('Core component missing.'); } $orunk_core = new Custom_Orunk_Core(); $plan = $orunk_core->get_plan_details($plan_id); if (!$plan) { wp_die(__('Invalid plan selected.', 'orunk-users')); } $feature_key = $plan['product_feature_key']; if(empty($feature_key)) { wp_die(__('Plan is missing feature key association.', 'orunk-users')); } $current_active_plan = $orunk_core->get_user_active_plan($user_id, $feature_key); if ($current_active_plan) { $dashboard_url = home_url('/orunk-dashboard/'); wp_safe_redirect(add_query_arg('orunk_error', urlencode(__('You already have an active plan for this feature. Please manage it from your dashboard.', 'orunk-users')), $dashboard_url)); exit; } $checkout_page_url = home_url('/checkout/'); $redirect_url = add_query_arg('plan_id', $plan_id, $checkout_page_url); wp_safe_redirect($redirect_url); exit; }
function orunk_handle_plan_switch() { /* ... unchanged ... */ $dashboard_url = home_url('/orunk-dashboard/'); $checkout_url = home_url('/checkout/'); $purchase_id = isset($_POST['current_purchase_id']) ? absint($_POST['current_purchase_id']) : 0; if (!is_user_logged_in() || $purchase_id <= 0 || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'orunk_switch_plan_' . $purchase_id)) { wp_safe_redirect(add_query_arg('orunk_error', urlencode(__('Security check failed.', 'orunk-users')), $dashboard_url)); exit; } $user_id = get_current_user_id(); $new_plan_id = isset($_POST['new_plan_id']) ? absint($_POST['new_plan_id']) : 0; if ($new_plan_id <= 0) { wp_safe_redirect(add_query_arg('orunk_error', urlencode(__('Invalid new plan selected.', 'orunk-users')), $dashboard_url)); exit; } if (!class_exists('Custom_Orunk_Core') || !class_exists('Custom_Orunk_DB')) { wp_safe_redirect(add_query_arg('orunk_error', urlencode(__('Core components missing.', 'orunk-users')), $dashboard_url)); exit; } $orunk_core = new Custom_Orunk_Core(); global $wpdb; $purchases_table = $wpdb->prefix . 'orunk_user_purchases'; $current_purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$purchases_table` WHERE id = %d AND user_id = %d AND status = 'active'", $purchase_id, $user_id), ARRAY_A); $new_plan = $orunk_core->get_plan_details($new_plan_id); if (!$current_purchase || !$new_plan) { wp_safe_redirect(add_query_arg('orunk_error', urlencode(__('Could not find current purchase or new plan details.', 'orunk-users')), $dashboard_url)); exit; } if (!empty($current_purchase['pending_switch_plan_id'])) { wp_safe_redirect(add_query_arg('orunk_error', urlencode(__('A plan switch is already pending payment for this service.', 'orunk-users')), $dashboard_url)); exit; } $current_feature_key = $current_purchase['product_feature_key'] ?? null; if (!$current_feature_key) { $current_plan_details_snapshot = json_decode($current_purchase['plan_details_snapshot'] ?? '', true); $current_feature_key = $current_plan_details_snapshot['product_feature_key'] ?? null; } if (!$current_feature_key || $current_feature_key !== $new_plan['product_feature_key']) { wp_safe_redirect(add_query_arg('orunk_error', urlencode(__('Cannot switch between different features.', 'orunk-users')), $dashboard_url)); exit; } $redirect_url = add_query_arg(['plan_id' => $new_plan_id, 'purchase_id' => $purchase_id], $checkout_url); wp_safe_redirect($redirect_url); exit; }
function orunk_handle_plan_cancel() { /* ... unchanged ... */ $dashboard_url = home_url('/orunk-dashboard/'); $purchase_id = isset($_POST['purchase_id_to_cancel']) ? absint($_POST['purchase_id_to_cancel']) : 0; if (!is_user_logged_in() || $purchase_id <= 0 || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'orunk_cancel_plan_' . $purchase_id)) { wp_safe_redirect(add_query_arg('orunk_error', urlencode(__('Security check failed.', 'orunk-users')), $dashboard_url)); exit; } $user_id = get_current_user_id(); global $wpdb; $purchases_table = $wpdb->prefix . 'orunk_user_purchases'; $updated = $wpdb->update($purchases_table, ['status' => 'cancelled', 'pending_switch_plan_id' => null], ['id' => $purchase_id, 'user_id' => $user_id], ['%s', null], ['%d', '%d']); if ($updated) { set_transient('orunk_purchase_message_' . $user_id, __('Plan cancelled successfully.', 'orunk-users'), 300); do_action('orunk_plan_cancelled', $purchase_id, $user_id); } elseif ($updated === 0) { set_transient('orunk_purchase_message_' . $user_id, __('Could not cancel plan. Plan not found or already cancelled.', 'orunk-users'), 300); } else { $error_msg = __('Failed to cancel plan due to a database error.', 'orunk-users'); error_log("Orunk Users: Failed to cancel purchase ID $purchase_id. DB Error: " . $wpdb->last_error); wp_safe_redirect(add_query_arg('orunk_error', urlencode($error_msg), $dashboard_url)); exit; } wp_safe_redirect($dashboard_url); exit; }

// --- Admin Update Status (Non-AJAX - Unchanged - Relies on Purchase Manager) ---
function handle_update_purchase_status() { /* ... unchanged ... */ global $wpdb; $purchases_table = $wpdb->prefix . 'orunk_user_purchases'; $admin_page_url = admin_url('admin.php?page=orunk-users-manager'); $purchase_id = isset($_POST['purchase_id']) ? absint($_POST['purchase_id']) : 0; if ($purchase_id <= 0 || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'orunk_update_status_' . $purchase_id)) { wp_die(__('Security check failed.', 'orunk-users')); } if (!current_user_can('manage_options')) { wp_die(__('Permission denied.', 'orunk-users')); } $new_status_action = isset($_POST['status']) ? sanitize_key($_POST['status']) : ''; $allowed_statuses = ['pending', 'active', 'expired', 'cancelled', 'failed', 'approve_switch']; $message_code = ''; if (!in_array($new_status_action, $allowed_statuses)) { $message_code = 'invalid_status'; } else { if (!class_exists('Custom_Orunk_Purchase_Manager')) { $pm_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-purchase-manager.php'; if (file_exists($pm_path)) { require_once $pm_path; } } if (!class_exists('Custom_Orunk_Purchase_Manager')) { error_log("Admin Non-AJAX Update Error: Purchase Manager missing for purchase {$purchase_id}."); $message_code = 'status_update_error_db'; } else { $purchase_manager = new Custom_Orunk_Purchase_Manager(); $current_purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$purchases_table` WHERE id = %d", $purchase_id), ARRAY_A); if ($current_purchase) { $message_code = 'status_updated'; $result = null; if ($new_status_action === 'approve_switch') { $result = $purchase_manager->approve_manual_switch($purchase_id); if (!is_wp_error($result)) { $message_code = 'switch_approved'; } } elseif ($new_status_action === 'active') { $result = $purchase_manager->activate_purchase($purchase_id, 'manual_admin_activation_nonajax', null, null, null, null, null, true); if (is_wp_error($result) && !in_array($result->get_error_code(), ['already_active', 'not_pending_payment'])) { $message_code = 'activate_error_no_plan'; } } elseif ($new_status_action === 'failed') { $result = $purchase_manager->record_purchase_failure($purchase_id, 'Manually set to Failed by admin (Non-AJAX)'); } else { $status_to_set = ($new_status_action === 'pending') ? 'Pending Payment' : ucfirst($new_status_action); $update_data = ['status' => $status_to_set]; $update_format = ['%s']; if (in_array($status_to_set, ['Expired', 'Cancelled', 'Failed']) && !empty($current_purchase['pending_switch_plan_id'])) { $update_data['pending_switch_plan_id'] = null; $update_format[] = '%s'; } if ($status_to_set !== 'Failed' && (!empty($current_purchase['failure_reason']) || !empty($current_purchase['failure_timestamp']))) { $update_data['failure_timestamp'] = null; $update_data['failure_reason'] = null; $update_format[] = '%s'; $update_format[] = '%s'; } $updated = $wpdb->update($purchases_table, $update_data, ['id' => $purchase_id], $update_format, ['%d']); if ($updated === false) { $message_code = 'status_update_error_db'; error_log("Admin Non-AJAX: DB Error updating purchase $purchase_id to $status_to_set: " . $wpdb->last_error); } } if (is_wp_error($result)) { $message_code = $result->get_error_code(); error_log("Admin Non-AJAX Update Error for purchase {$purchase_id}, action '{$new_status_action}': Code '{$message_code}' - " . $result->get_error_message()); if (empty($message_code)) { $message_code = 'status_update_error_db'; } } } else { $message_code = 'purchase_not_found'; } } } wp_safe_redirect(add_query_arg(['page' => 'orunk-users-manager', 'orunk_message' => $message_code], $admin_page_url)); exit; }

// --- Other Helper Functions (Unchanged) ---
function orunk_users_add_settings_link($links) { /* ... */ if (current_user_can('manage_options')) { $settings_link = '<a href="' . admin_url('admin.php?page=orunk-users-settings') . '">' . __('Settings', 'orunk-users') . '</a>'; array_unshift($links, $settings_link); } return $links; } add_filter('plugin_action_links_' . ORUNK_USERS_BASE_NAME, 'orunk_users_add_settings_link');
function orunk_users_activation_notice() { /* ... */ ?> <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Orunk Users plugin activated/updated successfully. Please review settings if necessary.', 'orunk-users'); ?></p></div> <?php }
function orunk_custom_get_avatar_data( $args, $id_or_email ) { /* ... unchanged ... */ $user_id = null; $avatar_meta_key = 'orunk_profile_picture_attachment_id'; if ( is_numeric( $id_or_email ) ) { $user_id = (int) $id_or_email; } elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) { $user_id = (int) $id_or_email->user_id; } elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) { $user = get_user_by( 'email', $id_or_email ); if ( $user ) { $user_id = $user->ID; } } elseif ($id_or_email instanceof WP_User) { $user_id = $id_or_email->ID; } elseif ($id_or_email instanceof WP_Post) { $user_id = (int) $id_or_email->post_author; } elseif ($id_or_email instanceof WP_Comment) { if (!empty($id_or_email->user_id)) { $user_id = (int) $id_or_email->user_id; } } if ( $user_id ) { $attachment_id = get_user_meta( $user_id, $avatar_meta_key, true ); if ( ! empty( $attachment_id ) ) { $custom_avatar_url = wp_get_attachment_image_url( $attachment_id, array( $args['width'], $args['height'] ) ); if ( $custom_avatar_url ) { $args['url'] = $custom_avatar_url; $args['found_avatar'] = true; } } } return $args; } add_filter( 'get_avatar_data', 'orunk_custom_get_avatar_data', 10, 2 );

?>