<?php
/**
 * Plugin Name: BIN Lookup API
 * Plugin URI: https://orunk.xyz/bin-lookup-api
 * Description: Provides a REST API endpoint for BIN lookup, integrating with Orunk Users for plan-based access control and rate limiting. Logs API requests.
 * Version: 3.4.0
 * Author: Your Name
 * Author URI: https://orunk.xyz
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bin-lookup-api
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 *
 * @package BinLookupApi
 */

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BIN_LOOKUP_API_VERSION', '3.4.0');
define('BIN_LOOKUP_API_DIR', plugin_dir_path(__FILE__));
define('BIN_LOOKUP_API_URL', plugin_dir_url(__FILE__));
define('BIN_LOOKUP_API_BASE_NAME', plugin_basename(__FILE__));


// Include the database handler class for request logging
require_once BIN_LOOKUP_API_DIR . 'class-bin-api-requests-db.php';

/**
 * Main Class for the BIN Lookup API Plugin.
 */
class Custom_BIN_API {

    /** @var Bin_API_Requests_DB Handles logging API requests to its dedicated table. */
    private $db_handler;

    /** @var int Default cache duration in seconds for successful BIN lookups. */
    private $cache_duration = 86400; // 24 hours

    /**
     * Constructor.
     * Initializes the request DB handler and registers WordPress hooks.
     */
    public function __construct() {
        // Instantiate the request logger database handler
        // Ensure the class file was included successfully
        if (class_exists('Bin_API_Requests_DB')) {
            $this->db_handler = new Bin_API_Requests_DB();
        } else {
            // Log error or handle missing class file - prevents fatal errors later
            error_log('BIN Lookup API Error: Bin_API_Requests_DB class not found.');
            // We might want to prevent further initialization if this fails
        }

        // Hook for loading translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Hook to check for dependencies (like Orunk Users) and display admin notices
        add_action('admin_notices', array($this, 'check_dependencies'));
    }

    /**
     * Load plugin textdomain for internationalization.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'bin-lookup-api',
            false,
            dirname(BIN_LOOKUP_API_BASE_NAME) . '/languages'
        );
    }

    /**
     * Check if required dependencies (Orunk Users plugin) are active.
     * Displays an admin notice if dependencies are missing.
     */
    public function check_dependencies() {
        // Check if the core class from Orunk Users is available
        if (!class_exists('Custom_Orunk_DB')) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php esc_html_e('BIN Lookup API Error:', 'bin-lookup-api'); ?></strong>
                    <?php esc_html_e('The required "Orunk Users" plugin is not active or its core database class is missing. API functionality will be disabled.', 'bin-lookup-api'); ?>
                </p>
            </div>
            <?php
        }
        // Check if its own DB handler loaded correctly
         if (!isset($this->db_handler) || !is_object($this->db_handler)) {
             ?>
             <div class="notice notice-error is-dismissible">
                 <p>
                     <strong><?php esc_html_e('BIN Lookup API Error:', 'bin-lookup-api'); ?></strong>
                     <?php esc_html_e('The request logging database class (Bin_API_Requests_DB) failed to load. Logging may be affected.', 'bin-lookup-api'); ?>
                 </p>
             </div>
             <?php
         }
    }


    /**
     * Initialize WordPress hooks for REST API, admin menu, settings, scripts, etc.
     */
    public function init() {
        // Register REST API routes only if dependencies are met
        if (class_exists('Custom_Orunk_DB')) {
            add_action('rest_api_init', array($this, 'register_routes'));
        }

        // Add Admin Menu pages
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register Plugin Settings
        add_action('admin_init', array($this, 'register_settings'));

        // Enqueue Admin Scripts & Styles (for dashboard interactions)
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Action hook for external cache clearing requests (if needed)
        add_action('bin_lookup_api_clear_cache', array($this, 'clear_cache'));

        // Action hook to handle CSV log download requests
        add_action('admin_init', array($this, 'handle_log_download'));

        // --- AJAX Action Hooks ---
        // Only keep AJAX actions relevant to this plugin's current scope
        add_action('wp_ajax_reset_bin_requests', array($this, 'handle_ajax_reset_requests')); // Reset request logs for a key
        add_action('wp_ajax_get_bin_key_details', array($this, 'handle_ajax_get_key_details'));   // Show details (including plan limits)
        // Key management AJAX handlers are REMOVED as management is now in Orunk Users
    }

    /**
     * Handles the admin action request to download the full request log as a CSV file.
     */
    public function handle_log_download() {
        // Check if the download action is triggered, nonce is valid, and user has permission
        if (
            isset($_GET['action'], $_GET['_wpnonce']) &&
            $_GET['action'] === 'download_bin_log' &&
            wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'download_bin_log_nonce') &&
            current_user_can('manage_options')
        ) {
            // Ensure the DB handler and its method exist
            if (!isset($this->db_handler) || !method_exists($this->db_handler, 'get_all_requests')) {
                wp_die(esc_html__('Request log database handler is not available.', 'bin-lookup-api'), '', ['response' => 500]);
            }

            // Fetch all log entries
            $all_logs = $this->db_handler->get_all_requests();

            // Handle case where there are no logs
            if (empty($all_logs)) {
                wp_die(esc_html__('No request logs found to download.', 'bin-lookup-api'), '', ['response' => 404]);
            }

            // Prepare CSV file
            $filename = 'bin-api-requests-log-' . date('Y-m-d') . '.csv';

            // Set headers for CSV download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            header('Pragma: no-cache');
            header('Expires: 0');

            // Open output stream
            $output = fopen('php://output', 'w');
            if ($output === false) {
                wp_die(esc_html__('Failed to open output stream for CSV export.', 'bin-lookup-api'), '', ['response' => 500]);
            }

            // Add CSV Header Row (Translated)
            fputcsv($output, array(
                __('Log ID', 'bin-lookup-api'),
                __('Date', 'bin-lookup-api'),
                __('API Key (Partial)', 'bin-lookup-api'),
                __('User ID', 'bin-lookup-api'),
                __('Username', 'bin-lookup-api'),
                __('IP Address', 'bin-lookup-api'),
                __('Status', 'bin-lookup-api')
            ));

            // Add Log Data Rows
            foreach ($all_logs as $log) {
                fputcsv($output, array(
                    $log['id'] ?? '',
                    $log['request_date'] ?? '',
                    isset($log['api_key']) ? substr($log['api_key'], 0, 8) . '...' : '',
                    $log['user_id'] ?: '',
                    isset($log['user_login']) ? $log['user_login'] : '', // Username might be from join in get_all_requests
                    $log['ip_address'] ?? '',
                    $log['status'] ?? ''
                ));
            }

            // Close the output stream and exit script execution
            fclose($output);
            exit;
        }
    }

    /**
     * Register the REST API routes for the '/bin/v1/lookup/{bin}' endpoint.
     */
    public function register_routes() {
        // Double-check dependency again, although init checks it too
        if (!class_exists('Custom_Orunk_DB')) {
             return;
        }

        // Check if API is enabled in settings
        $options = get_option('bin_api_settings', array('api_enabled' => 'yes'));
        if ($options['api_enabled'] !== 'yes') {
            return;
        }

        // Register the route
        register_rest_route(
            'bin/v1', // Namespace
            '/lookup/(?P<bin>\d{6,8})', // Route pattern with BIN parameter (6-8 digits)
            array(
                'methods' => $this->get_allowed_methods(), // Allowed HTTP methods from settings
                'callback' => array($this, 'lookup_bin'), // Function to handle the request
                'permission_callback' => array($this, 'check_access'), // Function to check access permissions
                'args' => array( // Define expected arguments
                    'bin' => array(
                        'description' => __('The 6 to 8 digit Bank Identification Number.', 'bin-lookup-api'),
                        'type' => 'string', // Handled as string internally
                        'pattern' => '^\d{6,8}$', // Regex pattern for validation
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param) && strlen($param) >= 6 && strlen($param) <= 8;
                        },
                        'sanitize_callback' => 'sanitize_text_field', // Basic sanitization
                        'required' => true,
                    ),
                    'api_key' => array(
                        'description' => __('Your API key for authentication.', 'bin-lookup-api'),
                        'type' => 'string',
                        'required' => true,
                        'validate_callback' => function($param, $request, $key) {
                            // Basic non-empty check; thorough validation happens in check_access
                            return !empty(trim($param));
                        },
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
    }

    /**
     * Get the allowed HTTP methods for the API endpoint from plugin settings.
     * Defaults to 'GET' if the setting is missing or invalid.
     *
     * @return array An array of uppercase HTTP methods (e.g., ['GET', 'POST']).
     */
    public function get_allowed_methods() {
        $options = get_option('bin_api_settings', array('allowed_methods' => array('GET')));
        $allowed_methods = !empty($options['allowed_methods']) && is_array($options['allowed_methods'])
                           ? $options['allowed_methods']
                           : array('GET'); // Default to GET

        // Ensure methods are uppercase and filter out invalid values if necessary
        $valid_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
        $allowed_methods = array_map('strtoupper', $allowed_methods);
        $allowed_methods = array_intersect($allowed_methods, $valid_methods);

        // Apply a filter for potential modification by other plugins/themes
        return apply_filters('bin_lookup_api_allowed_methods', $allowed_methods);
    }


    /**
     * Permission callback for the REST API endpoint '/bin/v1/lookup/{bin}'.
     * This is the core access control function. It checks:
     * 1. Dependency on Orunk Users.
     * 2. IP Address restrictions (if configured).
     * 3. API Key presence and validity (via Orunk Users purchase records).
     * 4. Purchase status (must be 'active').
     * 5. Feature match (key must be for 'bin_api').
     * 6. Expiry date.
     * 7. Rate limits (daily/monthly) based on the purchased plan.
     *
     * @param WP_REST_Request $request The incoming request object.
     * @return bool|WP_Error True if access is granted, WP_Error object otherwise.
     */
    public function check_access(WP_REST_Request $request) {
        // --- 1. Dependency Check ---
        if (!class_exists('Custom_Orunk_DB')) {
             error_log("BIN API Access Check Error: Custom_Orunk_DB class (from Orunk Users) not found.");
             return new WP_Error(
                 'plugin_dependency_missing',
                 __('API access check failed due to a missing server dependency. Please contact support.', 'bin-lookup-api'),
                 array('status' => 503) // Service Unavailable
            );
        }
        // Instantiate Orunk DB handler to fetch purchase details
        $orunk_db = new Custom_Orunk_DB();

        // --- 2. Get Settings & Request Details ---
        $options = get_option('bin_api_settings', array( /* Defaults */
            'allowed_ips' => '',
            'error_unauthorized' => __('Access denied.', 'bin-lookup-api'),
            'error_rate_limit' => __('Rate limit exceeded.', 'bin-lookup-api'),
        ));
        $client_ip = $this->get_client_ip();
        $api_key = $request->get_param('api_key'); // Sanitized by arg definition

        // --- 3. IP Address Check ---
        $allowed_ips = !empty($options['allowed_ips']) ? array_map('trim', explode(',', $options['allowed_ips'])) : array();
        if (!empty($allowed_ips) && !in_array($client_ip, $allowed_ips)) {
            $this->log_request($api_key, 0, $client_ip, 'IP not allowed'); // Log attempt
            return new WP_Error(
                'ip_not_allowed',
                __('Access from your IP address is not allowed.', 'bin-lookup-api'),
                array('status' => 403) // Forbidden
            );
        }

        // --- 4. API Key Presence Check ---
        // This is technically redundant due to 'required' => true in register_rest_route,
        // but serves as an extra safeguard.
        if (empty($api_key)) {
            $this->log_request(null, 0, $client_ip, 'No API key provided');
            return new WP_Error(
                'no_api_key',
                __('An API key is required for this request.', 'bin-lookup-api'),
                array('status' => 401) // Unauthorized
            );
        }

        // --- 5. Fetch Purchase Details using API Key ---
        $purchase = $orunk_db->get_purchase_by_api_key($api_key);

        // --- 6. Validate Purchase Record Existence ---
        if (!$purchase) {
            $this->log_request($api_key, 0, $client_ip, 'Invalid API Key');
            return new WP_Error(
                'invalid_api_key',
                $options['error_unauthorized'], // Use custom error message from settings
                array('status' => 403) // Forbidden
            );
        }

        // Store user ID for logging purposes
        $user_id = $purchase['user_id'];

        // --- 7. Check Purchase Status ---
        if ($purchase['status'] !== 'active') {
            $log_status = 'Key Not Active (Status: ' . sanitize_text_field($purchase['status']) . ')';
            $this->log_request($api_key, $user_id, $client_ip, $log_status);
            return new WP_Error(
                'key_not_active',
                $options['error_unauthorized'], // Use custom error message
                array('status' => 403)
            );
        }

        // --- 8. Check Feature Match ---
        $required_feature = 'bin_api'; // The specific feature this plugin endpoint grants access to
        if (!isset($purchase['product_feature_key']) || $purchase['product_feature_key'] !== $required_feature) {
            $log_status = 'Feature Mismatch (Key for: ' . sanitize_text_field($purchase['product_feature_key'] ?? 'Unknown') . ')';
            $this->log_request($api_key, $user_id, $client_ip, $log_status);
            return new WP_Error(
                'feature_mismatch',
                __('This API key is not valid for the requested feature.', 'bin-lookup-api'),
                array('status' => 403)
            );
        }

        // --- 9. Check Expiry Date ---
        if (!empty($purchase['expiry_date'])) {
             // Compare current GMT time with the stored GMT expiry date
             if (current_time('timestamp', 1) > strtotime($purchase['expiry_date'])) {
                 $this->log_request($api_key, $user_id, $client_ip, 'Key Expired');
                 // Optional: Update status to 'expired' in Orunk Users DB if desired
                 // global $wpdb; $wpdb->update($wpdb->prefix.'orunk_user_purchases', ['status' => 'expired'], ['id' => $purchase['id']]);
                 return new WP_Error(
                     'key_expired',
                     __('Your API key subscription has expired.', 'bin-lookup-api'),
                     array('status' => 403)
                 );
             }
        } // If expiry_date is NULL, treat as non-expiring (or handle based on specific plan logic if needed)


        // --- 10. Rate Limiting based on Plan ---
        // Limits are retrieved directly from the purchase record.
        $limit_day = $purchase['plan_requests_per_day'];   // Integer or NULL
        $limit_month = $purchase['plan_requests_per_month']; // Integer or NULL

        // Ensure the request logging DB handler is available
        if (!isset($this->db_handler)) {
             error_log("BIN API Access Check Error: Request DB handler not available for rate limiting.");
              return new WP_Error(
                 'rate_limit_handler_missing',
                 __('API access check failed due to an internal server issue. Please contact support.', 'bin-lookup-api'),
                 array('status' => 500) // Internal Server Error
            );
        }

        // Check Daily Limit (only if $limit_day is a non-negative integer)
        if ($limit_day !== null && $limit_day >= 0) {
             $count_today = $this->db_handler->get_request_count_today($api_key);
             if ($count_today >= $limit_day) {
                 $this->log_request($api_key, $user_id, $client_ip, 'Daily rate limit exceeded (' . $count_today . '/' . $limit_day . ')');
                 return new WP_Error(
                     'rate_limit_exceeded_day',
                     $options['error_rate_limit'], // Use custom error message
                     array('status' => 429) // 429 Too Many Requests
                );
             }
        }

        // Check Monthly Limit (only if $limit_month is a non-negative integer)
         if ($limit_month !== null && $limit_month >= 0) {
             // Using the existing rolling 30-day count from db_handler.
             // For strict calendar month or purchase cycle limits, get_request_counts_for_key would need modification.
              $counts = $this->db_handler->get_request_counts_for_key($api_key); // Gets today, week, month(30d), total
              $count_month = $counts['month']; // Rolling 30 days count

              if ($count_month >= $limit_month) {
                   $this->log_request($api_key, $user_id, $client_ip, 'Monthly rate limit exceeded (' . $count_month . '/' . $limit_month . ')');
                   return new WP_Error(
                       'rate_limit_exceeded_month',
                       $options['error_rate_limit'], // Use custom error message
                       array('status' => 429)
                    );
              }
         }

        // --- Access Granted ---
        // If all checks passed, the user has permission to access the endpoint.
        // Logging of the actual lookup result happens in the `lookup_bin` callback.
        return true;
    }

    /**
     * Get the client's real IP address, considering common proxy headers.
     *
     * @return string The client IP address, or '0.0.0.0' if undetermined/invalid.
     */
    public function get_client_ip() {
        $ip_keys = [
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];
        $ip = '0.0.0.0'; // Default

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                // Sanitize and potentially take the first IP if X-Forwarded-For
                $ip_string = sanitize_text_field(wp_unslash($_SERVER[$key]));
                if ($key === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $ip_string);
                    $potential_ip = trim($ips[0]);
                } else {
                    $potential_ip = $ip_string;
                }

                // Validate the potential IP
                if (filter_var($potential_ip, FILTER_VALIDATE_IP)) {
                    $ip = $potential_ip;
                    break; // Found a valid IP, stop checking
                }
            }
        }
        return $ip;
    }

    /**
     * Log an API request attempt to the dedicated database table.
     *
     * @param string|null $api_key The API key used (or null if missing/invalid).
     * @param int $user_id The associated WordPress User ID (0 if unknown).
     * @param string $ip The client's IP address.
     * @param string $status A short status message describing the outcome (e.g., 'Success', 'Invalid API Key', 'Rate limit exceeded').
     */
    public function log_request($api_key, $user_id, $ip, $status) {
        // Ensure the db_handler is initialized and has the log_request method
        if (isset($this->db_handler) && method_exists($this->db_handler, 'log_request')) {
            // Ensure user ID is an integer
            $user_id = absint($user_id);
            // Log the request using the handler
            $this->db_handler->log_request($api_key, $user_id, $ip, $status);
        } else {
            // Log an error if the handler isn't available
            error_log("BIN API Log Error: db_handler not available for logging request. Status: $status, IP: $ip");
        }

        // Optional: File logging (if enabled in settings)
        $options = get_option('bin_api_settings', array('logging_enabled' => 'no'));
        if ($options['logging_enabled'] === 'yes') {
            $log_entry = sprintf(
                "[%s] IP:%s | Key:%s | User:%d | Status:%s\n",
                current_time('mysql'), // Use WordPress current time
                $ip,
                $api_key ? substr($api_key, 0, 8) . '...' : 'N/A',
                $user_id,
                $status
            );
            $log_file = WP_CONTENT_DIR . '/bin-api-logs.txt'; // Define log file path

            // Attempt to write to the log file
            // Use error_log as a fallback if file writing fails?
            if (is_writable(WP_CONTENT_DIR)) {
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            } else {
                error_log("BIN API File Log Error: Log directory not writable: " . WP_CONTENT_DIR);
                error_log("BIN API File Log Entry: " . $log_entry); // Log to PHP error log instead
            }
        }
    }


    /**
     * Callback function for the '/bin/v1/lookup/{bin}' endpoint.
     * Performs the actual BIN lookup after access checks have passed.
     * Uses caching (transients) for successful lookups.
     * Logs the outcome of the lookup ('Success (Cache)', 'Success (DB)', 'Not Found', 'Error (...)').
     *
     * @param WP_REST_Request $request The request object containing parameters.
     * @return WP_REST_Response|WP_Error Response object with BIN data or a WP_Error on failure.
     */
    public function lookup_bin(WP_REST_Request $request) {
        global $wpdb;

        // --- Get parameters (already validated/sanitized by REST API args) ---
        $bin = $request['bin'];
        $api_key = $request->get_param('api_key');
        $client_ip = $this->get_client_ip();
        $user_id_for_log = 0; // Initialize user ID for logging

        // --- Get User ID from API Key (for accurate logging) ---
        // Assumes check_access passed, so the key exists in Orunk Users purchase records.
         if (class_exists('Custom_Orunk_DB')) {
             $orunk_db = new Custom_Orunk_DB();
             $purchase = $orunk_db->get_purchase_by_api_key($api_key);
             if ($purchase && !empty($purchase['user_id'])) {
                 $user_id_for_log = absint($purchase['user_id']);
             }
         } // Log as user 0 if Orunk DB isn't available or key lookup fails unexpectedly


        // --- 1. Check Cache ---
        $cache_key = 'bin_lookup_v1_' . md5($bin); // Cache key based on BIN
        $cached_data = get_transient($cache_key);
        // Check if cached data is valid (not false and is an array)
        if (false !== $cached_data && is_array($cached_data)) {
            $this->log_request($api_key, $user_id_for_log, $client_ip, 'Success (Cache)');
            return $this->format_response($cached_data, $request); // Return cached response
        }

        // --- 2. Database Lookup ---
        // Define the name of your BIN data table
        $bin_table_name = $wpdb->prefix . 'bsp_bins'; // <<< IMPORTANT: Verify this table name!

        // Check if the BIN data table exists to prevent fatal errors
        // Use $wpdb->get_var for efficiency
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $bin_table_name)) != $bin_table_name) {
            error_log("BIN API Error: BIN data table '$bin_table_name' not found.");
            $this->log_request($api_key, $user_id_for_log, $client_ip, 'Error (BIN Table Missing)');
            return new WP_Error(
                'db_table_missing',
                __('An internal server error occurred while processing your request.', 'bin-lookup-api'),
                array('status' => 500) // Internal Server Error
            );
        }

        // Prepare the SQL query to find the best match for the BIN prefix
        // Adjust selected columns based on your actual table schema.
        $sql = $wpdb->prepare(
            "SELECT bin, bank_name, card_brand, card_type, card_level, country
             FROM {$bin_table_name}
             WHERE %s LIKE CONCAT(bin, '%%') -- Match rows where the requested BIN starts with the stored BIN
             ORDER BY LENGTH(bin) DESC       -- Prioritize longer matches (e.g., 8-digit over 6-digit)
             LIMIT 1",                        // Get only the best match
            $bin
        );
        // Execute the query
        $result = $wpdb->get_row($sql, ARRAY_A);

        // --- 3. Process Lookup Result ---
        $bin_data = array();
        $log_status = 'Not Found'; // Default status if no match is found

        if ($result) {
            // Match found in the database
            $log_status = 'Success (DB)';

            // Format the successful response data
            $country_mapping = $this->get_country_mapping(); // Get country code mapping
            // Normalize the country code from DB (uppercase, handle empty/null)
            $country_code_from_db = !empty($result['country']) ? trim(strtoupper($result['country'])) : 'UNKNOWN';
            // Find country name from mapping, default to the code itself if not found
            $country_name = $country_mapping[$country_code_from_db] ?? $country_code_from_db;

            $bin_data = array(
                'bin'           => $result['bin'] ?? $bin, // Use matched BIN, fallback to requested BIN
                'bank_name'     => $result['bank_name'] ?? __('Unknown', 'bin-lookup-api'),
                'card_brand'    => $result['card_brand'] ?? __('Unknown', 'bin-lookup-api'),
                'card_type'     => $result['card_type'] ?? __('Unknown', 'bin-lookup-api'),
                'card_level'    => $result['card_level'] ?? __('Unknown', 'bin-lookup-api'),
                'country_iso'   => $country_code_from_db, // e.g., US, GB, UNKNOWN
                'country_name'  => $country_name,         // e.g., United States, United Kingdom
                'source'        => 'database'              // Indicate data source
            );

            // Cache the successful result using WordPress transients
            set_transient($cache_key, $bin_data, $this->cache_duration);

        } else {
            // BIN not found in the database
            $bin_data = array(
                'bin'           => $bin, // Return the requested BIN
                'bank_name'     => __('Unknown', 'bin-lookup-api'),
                'card_brand'    => __('Unknown', 'bin-lookup-api'),
                'card_type'     => __('Unknown', 'bin-lookup-api'),
                'card_level'    => __('Unknown', 'bin-lookup-api'),
                'country_iso'   => __('Unknown', 'bin-lookup-api'),
                'country_name'  => __('Unknown', 'bin-lookup-api'),
                'source'        => 'not_found' // Indicate BIN was not found
            );
            // Optional: Cache 'not found' results for a very short duration to reduce DB load?
            // set_transient($cache_key, $bin_data, 60); // Cache 'not found' for 1 minute
        }

        // --- 4. Log the final outcome of the lookup ---
        $this->log_request($api_key, $user_id_for_log, $client_ip, $log_status);

        // --- 5. Allow filtering of the response data ---
        $bin_data = apply_filters('bin_lookup_api_response_data', $bin_data, $bin, $result);

        // --- 6. Format and return the response ---
        return $this->format_response($bin_data, $request);
    }

    /**
     * Helper function to get a mapping of country ISO codes to full names.
     * Can be expanded or loaded dynamically if needed.
     * @return array Associative array [ISO_CODE => Country Name].
     */
    private function get_country_mapping() {
        // Static list for simplicity, could be loaded from a file or database option
         return apply_filters('bin_lookup_api_country_mapping', array(
            'US' => __('United States', 'bin-lookup-api'),
            'CA' => __('Canada', 'bin-lookup-api'),
            'GB' => __('United Kingdom', 'bin-lookup-api'),
            'AU' => __('Australia', 'bin-lookup-api'),
            'DE' => __('Germany', 'bin-lookup-api'),
            'FR' => __('France', 'bin-lookup-api'),
            'IN' => __('India', 'bin-lookup-api'),
            'BR' => __('Brazil', 'bin-lookup-api'),
            'MX' => __('Mexico', 'bin-lookup-api'),
            'JP' => __('Japan', 'bin-lookup-api'),
            'CN' => __('China', 'bin-lookup-api'),
            'RU' => __('Russia', 'bin-lookup-api'),
            // Add more mappings...
            'UNKNOWN' => __('Unknown', 'bin-lookup-api') // Handle unknown codes
        ));
    }


    /**
     * Format the response data array into either JSON or XML based on plugin settings.
     *
     * @param array $bin_data The associative array containing the BIN lookup result.
     * @param WP_REST_Request $request The original request object (passed for context, not used directly here).
     * @return WP_REST_Response A WordPress REST Response object ready to be sent.
     */
    private function format_response($bin_data, $request) {
        // Get the desired response format from settings, default to JSON
        $options = get_option('bin_api_settings', array('response_format' => 'json'));
        $format = $options['response_format'] ?? 'json';

        // Basic validation of input data
        if (!is_array($bin_data)) {
            return new WP_Error(
                'internal_data_error',
                __('Invalid internal data format encountered.', 'bin-lookup-api'),
                array('status' => 500)
            );
        }

        // Handle XML formatting if selected
        if ($format === 'xml') {
            try {
                // Create the root XML element
                $xml = new SimpleXMLElement('<bin_lookup/>');

                // Recursively convert the array data to XML elements
                $this->array_to_xml($bin_data, $xml);

                // Generate the XML string output
                $xml_output = $xml->asXML();
                if ($xml_output === false) {
                     throw new Exception('Failed to generate XML string from SimpleXMLElement.');
                }

                // Create a REST response with the XML output and correct content type
                $response = new WP_REST_Response($xml_output, 200);
                $response->header('Content-Type', 'application/xml; charset=' . get_option('blog_charset'));
                return $response;

            } catch (Exception $e) {
                // Log the error and return a server error response
                error_log("BIN API XML Formatting Error: " . $e->getMessage());
                return new WP_Error(
                    'xml_format_error',
                    __('An error occurred while formatting the XML response.', 'bin-lookup-api'),
                    array('status' => 500)
                );
            }
        }

        // Default to JSON response using WordPress helper function
        return rest_ensure_response($bin_data);
    }

     /**
      * Recursively converts an associative array to XML elements using SimpleXMLElement.
      * Handles numeric keys by using 'item' as the element name.
      * Ensures valid XML element names.
      *
      * @param array $array The array to convert.
      * @param SimpleXMLElement $xml The SimpleXMLElement object to append child elements to.
      */
     private function array_to_xml(array $array, SimpleXMLElement &$xml) {
         foreach ($array as $key => $value) {
             $key_sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '', $key); // Basic sanitization for element name
             $element_name = is_numeric($key) ? 'item' : ($key_sanitized ?: 'item'); // Use 'item' for numeric or invalid keys

             if (is_array($value)) {
                 // If the value is an array, create a child element and recurse
                 $child = $xml->addChild($element_name);
                 // If the original key was numeric or sanitized, add it as an attribute
                 if (is_numeric($key) || $key !== $element_name) {
                     $child->addAttribute('key', $key);
                 }
                 $this->array_to_xml($value, $child);
             } else {
                 // If the value is scalar, add it as element content
                 // Use htmlspecialchars for proper XML encoding
                 $child = $xml->addChild($element_name, htmlspecialchars((string)$value, ENT_XML1, 'UTF-8'));
                  // If the original key was numeric or sanitized, add it as an attribute
                 if (is_numeric($key) || $key !== $element_name) {
                     $child->addAttribute('key', $key);
                 }
             }
         }
     }


    /**
     * Clear BIN lookup cache (WordPress transients).
     * Triggered by the 'Clear Cache' button on the dashboard or the action hook.
     */
    public function clear_cache() {
        global $wpdb;
        $transient_pattern = $wpdb->esc_like('_transient_bin_lookup_v1_') . '%';
        $timeout_pattern = $wpdb->esc_like('_transient_timeout_bin_lookup_v1_') . '%';

        // Delete matching transients from the options table
        $deleted_transients = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $transient_pattern,
            $timeout_pattern
        ));

        // Clear WordPress object cache as well
        wp_cache_flush();
        error_log("BIN API: Lookup cache cleared. Transients deleted: " . ($deleted_transients !== false ? $deleted_transients : 'Error'));

        // Add an admin notice for user feedback (will be displayed on next admin page load)
        add_action('admin_notices', function() {
             echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('BIN API lookup cache cleared successfully.', 'bin-lookup-api') . '</p></div>';
        });
    }

    /**
     * Enqueue necessary admin scripts and styles for the plugin's admin pages.
     * Localizes data needed by the admin JavaScript.
     *
     * @param string $hook The hook suffix for the current admin page.
     */
    public function enqueue_admin_scripts($hook) {
        // Define the specific admin pages where scripts should be loaded
        $pages = array(
            'toplevel_page_bin-api-manager',        // Main dashboard page slug
            'bin-lookup-api_page_bin-api-settings', // Settings page slug
            'bin-lookup-api_page_bin-api-docs'      // Documentation page slug
        );

        // Only load scripts on the designated pages
        if (!in_array($hook, $pages)) {
            return;
        }

        // Enqueue Admin CSS (for styling dashboard elements, logs, modal)
        wp_enqueue_style(
            'bin-api-admin-css',
            BIN_LOOKUP_API_URL . 'assets/admin.css',
            array(), // Dependencies
            BIN_LOOKUP_API_VERSION // Version number
        );

        // Enqueue Admin JS (needed for Details modal, Reset button confirmation)
        wp_enqueue_script(
            'bin-api-admin-js',
            BIN_LOOKUP_API_URL . 'assets/admin.js',
            array('jquery'), // Dependencies (jQuery)
            BIN_LOOKUP_API_VERSION, // Version number
            true // Load in footer
        );

        // Localize script data - Pass only necessary data for the current functionality
        wp_localize_script('bin-api-admin-js', 'binApiAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'), // URL for AJAX requests
            // Nonces for security
            'nonce_reset' => wp_create_nonce('bin_api_reset_req_nonce'),
            'nonce_get_details' => wp_create_nonce('bin_api_get_details_nonce'),
            // Translatable text strings used in JavaScript
            'text_confirm_reset' => __('Are you sure you want to reset request counts for this API key? This deletes associated logs.', 'bin-lookup-api'),
            'text_resetting' => __('Resetting...', 'bin-lookup-api'),
            'text_reset' => __('Reset Counts', 'bin-lookup-api'), // Button text might differ
            'text_fetching' => __('Fetching Details...', 'bin-lookup-api'),
            'text_details' => __('Details', 'bin-lookup-api'),
            'text_close' => __('Close', 'bin-lookup-api'),
            'text_error_generic' => __('An error occurred. Please try again.', 'bin-lookup-api'),
            // Add more strings if needed by admin.js
        ));
    }

    /**
     * Add the main admin menu and submenus for the plugin.
     */
    public function add_admin_menu() {
        // Add the top-level menu item
        add_menu_page(
            __('BIN API Manager','bin-lookup-api'), // Page Title
            __('BIN API','bin-lookup-api'),        // Menu Title
            'manage_options',                       // Capability required to see menu
            'bin-api-manager',                      // Menu Slug (unique ID)
            array($this,'admin_page_html'),         // Function to display the page content
            'dashicons-bank',                       // Icon (Dashicon class)
            31                                      // Position in the menu order
        );
        // Add Dashboard submenu (links to the same page as the top-level menu)
        add_submenu_page(
            'bin-api-manager',                      // Parent Slug
            __('Dashboard','bin-lookup-api'),       // Page Title
            __('Dashboard','bin-lookup-api'),       // Menu Title
            'manage_options',                       // Capability
            'bin-api-manager',                      // Menu Slug (same as parent for default page)
            array($this,'admin_page_html')          // Display Function
        );
        // Add Settings submenu
        add_submenu_page(
            'bin-api-manager',
            __('Settings','bin-lookup-api'),
            __('Settings','bin-lookup-api'),
            'manage_options',
            'bin-api-settings',                     // Unique slug for settings page
            array($this,'settings_page_html')       // Distinct display function
        );
        // Add Documentation submenu
         add_submenu_page(
            'bin-api-manager',
            __('Documentation','bin-lookup-api'),
            __('Documentation','bin-lookup-api'),
            'manage_options',
            'bin-api-docs',                         // Unique slug for docs page
            array($this,'docs_page_html')           // Distinct display function
        );
    }


    /**
     * Display the HTML content for the main admin dashboard page.
     * Shows usage statistics and recent request logs.
     * Key management functionality has been moved to the Orunk Users plugin.
     */
    public function admin_page_html() {
        global $wpdb;

        // --- Pre-computation and Dependency Checks ---
        // Check if dependencies are met before proceeding
        if (!class_exists('Custom_Orunk_DB') || !isset($this->db_handler)) {
            echo '<div class="wrap"><h1>' . esc_html__('BIN Lookup API Dashboard', 'bin-lookup-api') . '</h1>';
            $this->check_dependencies(); // Display dependency error notices
            echo '</div>';
            return;
        }

        $options = get_option('bin_api_settings', array('logging_enabled' => 'no'));
        // Check if the request logging table exists
        $requests_table_exists = $this->db_handler->check_table_exists();
        $requests_table_name = $this->db_handler->get_requests_table_name();

        // --- Handle Form Submissions (Clear Cache/Logs) ---
        // Check if the form was submitted from this page
        if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['bin_api_action_nonce']) && wp_verify_nonce(sanitize_key($_POST['bin_api_action_nonce']), 'bin_api_dashboard_action')) {
            $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';

            if ($action === 'clear_cache') {
                $this->clear_cache(); // This method adds its own admin notice
            } elseif ($action === 'clear_logs' && $options['logging_enabled'] === 'yes') {
                 $log_file = WP_CONTENT_DIR . '/bin-api-logs.txt';
                if (@file_put_contents($log_file, '[' . current_time('mysql') . '] Logs cleared by admin.' . "\n")) { // Try writing, suppress errors
                    add_settings_error('bin_api_notices', 'logs_cleared', __('File log cleared.', 'bin-lookup-api'), 'updated');
                } else {
                    add_settings_error('bin_api_notices', 'logs_fail', __('Failed to clear file log. Check file permissions for wp-content directory.', 'bin-lookup-api'), 'warning');
                }
            }
            // Persist admin notices across the redirect implicitly handled by WP
            // settings_errors('bin_api_notices'); // Not needed here as clear_cache adds its own notice directly
        }

        // --- Fetch Data for Display ---
        $counts = $requests_table_exists ? $this->db_handler->get_request_counts() : ['total' => 0, 'today' => 0, 'week' => 0, 'month' => 0];
        $recent_requests_limit = 50; // Number of recent logs to display
        $recent_requests = $requests_table_exists ? $this->db_handler->get_recent_requests($recent_requests_limit) : array();
        $download_nonce = wp_create_nonce('download_bin_log_nonce');
        $download_url = add_query_arg(array('action' => 'download_bin_log', '_wpnonce' => $download_nonce), admin_url('admin.php'));

        // --- Start HTML Output ---
        ?>
        <div class="wrap bin-api-dashboard">
            <h1><?php esc_html_e('BIN Lookup API Dashboard', 'bin-lookup-api'); ?></h1>
            <p><?php esc_html_e('Monitor API usage statistics and view recent request logs.', 'bin-lookup-api'); ?></p>

            <?php settings_errors('bin_api_notices'); // Display any notices (e.g., from log clearing) ?>

            <?php // Warning if logging table is missing ?>
            <?php if (!$requests_table_exists) : ?>
                <div class="notice notice-warning">
                    <p><?php printf(esc_html__('Warning: The request logging table (%s) does not seem to exist. Request logging and statistics will not function correctly.', 'bin-lookup-api'), '<code>' . esc_html($requests_table_name) . '</code>'); ?></p>
                </div>
            <?php endif; ?>

            <?php // Usage Statistics Section ?>
            <h2><?php esc_html_e('Usage Statistics', 'bin-lookup-api'); ?></h2>
            <div class="stats-overview" style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
                 <div class="stat-card" style="border: 1px solid #ddd; background: #fff; padding: 15px 20px; border-radius: 4px; text-align: center; flex: 1; min-width: 150px;">
                     <h3 style="margin: 0 0 5px 0; font-size: 1em; color: #555;"><?php esc_html_e('Total Req.', 'bin-lookup-api'); ?></h3>
                     <p style="font-size: 1.8em; margin: 0; font-weight: 600; color: #333;"><?php echo esc_html(number_format_i18n($counts['total'])); ?></p>
                 </div>
                 <div class="stat-card" style="border: 1px solid #ddd; background: #fff; padding: 15px 20px; border-radius: 4px; text-align: center; flex: 1; min-width: 150px;">
                     <h3 style="margin: 0 0 5px 0; font-size: 1em; color: #555;"><?php esc_html_e('Today', 'bin-lookup-api'); ?></h3>
                     <p style="font-size: 1.8em; margin: 0; font-weight: 600; color: #333;"><?php echo esc_html(number_format_i18n($counts['today'])); ?></p>
                 </div>
                 <div class="stat-card" style="border: 1px solid #ddd; background: #fff; padding: 15px 20px; border-radius: 4px; text-align: center; flex: 1; min-width: 150px;">
                     <h3 style="margin: 0 0 5px 0; font-size: 1em; color: #555;"><?php esc_html_e('Last 7 Days', 'bin-lookup-api'); ?></h3>
                     <p style="font-size: 1.8em; margin: 0; font-weight: 600; color: #333;"><?php echo esc_html(number_format_i18n($counts['week'])); ?></p>
                 </div>
                 <div class="stat-card" style="border: 1px solid #ddd; background: #fff; padding: 15px 20px; border-radius: 4px; text-align: center; flex: 1; min-width: 150px;">
                     <h3 style="margin: 0 0 5px 0; font-size: 1em; color: #555;"><?php esc_html_e('Last 30 Days', 'bin-lookup-api'); ?></h3>
                     <p style="font-size: 1.8em; margin: 0; font-weight: 600; color: #333;"><?php echo esc_html(number_format_i18n($counts['month'])); ?></p>
                 </div>
            </div>

            <?php // Recent Requests Log Section ?>
            <div class="recent-requests">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1em; flex-wrap: wrap; gap: 10px;">
                    <h2 style="margin: 0;"><?php printf(esc_html__('Recent API Requests Log (Last %d)', 'bin-lookup-api'), $recent_requests_limit); ?></h2>
                    <?php if ($requests_table_exists): ?>
                    <a href="<?php echo esc_url($download_url); ?>" class="button button-secondary">
                        <?php esc_html_e('Download Full Log (CSV)', 'bin-lookup-api'); ?>
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (!$requests_table_exists): ?>
                     <p><?php esc_html_e('Request logging table not found. Cannot display logs.', 'bin-lookup-api'); ?></p>
                <?php elseif (empty($recent_requests)) : ?>
                    <p><?php esc_html_e('No recent API requests have been logged.', 'bin-lookup-api'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped logs">
                        <thead>
                            <tr>
                                <th scope="col" style="width:18%;"><?php esc_html_e('Date', 'bin-lookup-api'); ?></th>
                                <th scope="col" style="width:15%;"><?php esc_html_e('API Key (Start)', 'bin-lookup-api'); ?></th>
                                <th scope="col" style="width:20%;"><?php esc_html_e('User', 'bin-lookup-api'); ?></th>
                                <th scope="col" style="width:15%;"><?php esc_html_e('IP Address', 'bin-lookup-api'); ?></th>
                                <th scope="col" style="width:15%;"><?php esc_html_e('Status', 'bin-lookup-api'); ?></th>
                                <th scope="col" style="width:17%;"><?php esc_html_e('Actions', 'bin-lookup-api'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="the-list">
                            <?php foreach ($recent_requests as $log) : ?>
                                <?php
                                    // Prepare display values, handling missing data gracefully
                                    $user_display = __('N/A', 'bin-lookup-api');
                                    if (!empty($log['user_id']) && $log['user_id'] > 0) {
                                        $user_info = get_userdata($log['user_id']);
                                        $user_display = $user_info ? esc_html($user_info->user_login) . ' <small>(ID: ' . $log['user_id'] . ')</small>' : '<small>ID: ' . $log['user_id'] . '</small>';
                                    } elseif (!empty($log['user_login'])) { // Fallback if user deleted but log has username
                                         $user_display = esc_html($log['user_login']) . ' <small>(?)</small>';
                                    }
                                    $api_key_display = isset($log['api_key']) ? substr($log['api_key'], 0, 8) . '...' : __('N/A', 'bin-lookup-api');
                                    $log_status_class = 'status-' . sanitize_html_class(strtolower(str_replace(' ', '-', $log['status'] ?? 'unknown')));
                                ?>
                                <tr>
                                    <td data-colname="<?php esc_attr_e('Date', 'bin-lookup-api'); ?>">
                                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['request_date']))); ?>
                                    </td>
                                    <td data-colname="<?php esc_attr_e('API Key (Start)', 'bin-lookup-api'); ?>">
                                        <code><?php echo esc_html($api_key_display); ?></code>
                                    </td>
                                    <td data-colname="<?php esc_attr_e('User', 'bin-lookup-api'); ?>">
                                        <?php echo wp_kses($user_display, ['small' => []]); // Allow small tag for ID ?>
                                    </td>
                                    <td data-colname="<?php esc_attr_e('IP Address', 'bin-lookup-api'); ?>">
                                        <?php echo esc_html($log['ip_address'] ?? ''); ?>
                                    </td>
                                    <td class="<?php echo $log_status_class; ?>" data-colname="<?php esc_attr_e('Status', 'bin-lookup-api'); ?>">
                                        <?php echo esc_html($log['status'] ?? ''); ?>
                                    </td>
                                    <td class="actions-column" data-colname="<?php esc_attr_e('Actions', 'bin-lookup-api'); ?>">
                                        <?php if (isset($log['api_key']) && !empty($log['api_key'])) : ?>
                                            <div class="button-group"> <?php // Group buttons for better spacing ?>
                                                <button type="button" class="button button-secondary button-small key-details-button" data-key="<?php echo esc_attr($log['api_key']); ?>" title="<?php esc_attr_e('View details for this API key', 'bin-lookup-api'); ?>"><?php esc_html_e('Details', 'bin-lookup-api'); ?></button>
                                                <button type="button" class="button button-secondary button-small ajax-action-button" data-action="reset_requests" data-key="<?php echo esc_attr($log['api_key']); ?>" title="<?php esc_attr_e('Reset request counts and delete logs for this API key', 'bin-lookup-api'); ?>"><?php esc_html_e('Reset Counts', 'bin-lookup-api'); ?></button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div> <?php // end .recent-requests ?>

             <?php // Quick Actions Section ?>
             <div class="quick-actions" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                 <h2><?php esc_html_e('Quick Actions', 'bin-lookup-api'); ?></h2>
                 <form method="post" style="display:inline-block; margin-right: 10px;">
                     <?php wp_nonce_field('bin_api_dashboard_action', 'bin_api_action_nonce'); ?>
                     <input type="hidden" name="action" value="clear_cache">
                     <button type="submit" class="button button-secondary"><?php esc_html_e('Clear BIN Lookup Cache', 'bin-lookup-api'); ?></button>
                 </form>
                 <?php // Only show clear file log button if file logging is enabled ?>
                 <?php if ($options['logging_enabled'] === 'yes') : ?>
                     <form method="post" style="display:inline-block;">
                         <?php wp_nonce_field('bin_api_dashboard_action', 'bin_api_action_nonce'); ?>
                         <input type="hidden" name="action" value="clear_logs">
                         <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear the API request file log (wp-content/bin-api-logs.txt)? This cannot be undone.', 'bin-lookup-api'); ?>')"><?php esc_html_e('Clear File Log', 'bin-lookup-api'); ?></button>
                     </form>
                 <?php endif; ?>
             </div>

            <?php // --- Details Modal HTML (Remains the same structurally) --- ?>
            <div id="bin-api-key-details-modal" class="bin-api-modal" style="display:none;">
                <div id="bin-api-key-details-content" class="bin-api-modal-content">
                    <button class="button modal-close" title="<?php esc_attr_e('Close', 'bin-lookup-api'); ?>" style="position: absolute; top: 10px; right: 10px; padding: 5px 10px; line-height: 1;">&times;</button>
                    <h2><?php esc_html_e('API Key Details', 'bin-lookup-api'); ?></h2>
                    <div id="bin-api-key-details-body">
                        <p class="loading-message"><em><?php esc_html_e('Loading...', 'bin-lookup-api'); ?></em></p>
                        <?php // Content will be loaded via AJAX ?>
                    </div>
                </div>
            </div>

        </div><?php
    } // end admin_page_html


    // --- AJAX Handler for Resetting Requests (Kept) ---
     public function handle_ajax_reset_requests() {
         // 1. Check Nonce & Permissions
         check_ajax_referer('bin_api_reset_req_nonce', 'nonce');
         if (!current_user_can('manage_options')) {
             wp_send_json_error(['message' => __('Permission denied.', 'bin-lookup-api')], 403);
         }

         // 2. Get & Validate API Key
         $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : null;
         if (empty($api_key)) {
             wp_send_json_error(['message' => __('Missing API key.', 'bin-lookup-api')], 400);
         }

         // 3. Check DB Handler
         if (!isset($this->db_handler) || !method_exists($this->db_handler, 'reset_requests')) {
              error_log('BIN API Reset Error: DB handler or reset_requests method missing.');
             wp_send_json_error(['message' => __('Internal server error during reset.', 'bin-lookup-api')], 500);
         }

         // 4. Perform Reset (Deletes logs for the key from bin_api_requests table)
         $result = $this->db_handler->reset_requests($api_key);

         // 5. Send Response
         if ($result !== false) { // reset_requests returns false on failure, number of rows on success (or 0)
             wp_send_json_success(['message' => __('Request counts reset successfully. Associated logs deleted.', 'bin-lookup-api')]);
         } else {
             global $wpdb;
             error_log('BIN API Reset DB Error: ' . $wpdb->last_error);
             wp_send_json_error(['message' => __('Failed to reset request counts due to a database error.', 'bin-lookup-api')], 500);
         }
     } // end handle_ajax_reset_requests

     // --- AJAX Handler for Getting Key Details (Updated) ---
     public function handle_ajax_get_key_details() {
         // 1. Check Nonce & Permissions
         check_ajax_referer('bin_api_get_details_nonce', 'nonce');
         if (!current_user_can('manage_options')) {
             wp_send_json_error(['message' => __('Permission denied.', 'bin-lookup-api')], 403);
         }

         // 2. Get & Validate API Key
         $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : null;
         if (empty($api_key)) {
             wp_send_json_error(['message' => __('Missing API key.', 'bin-lookup-api')], 400);
         }

         // 3. Check Orunk Users Dependency
         if (!class_exists('Custom_Orunk_DB')) {
              wp_send_json_error(['message' => __('Required plugin component (Orunk DB) is missing.', 'bin-lookup-api')], 500);
         }
         $orunk_db = new Custom_Orunk_DB();

         // 4. Fetch Purchase Details from Orunk Users DB
         $purchase = $orunk_db->get_purchase_by_api_key($api_key);

         if (!$purchase) {
              // Key might exist in logs but not have a current purchase record
              wp_send_json_error(['message' => __('API Key not found in active purchase records.', 'bin-lookup-api')], 404);
         }

         // 5. Fetch User Details (if user ID exists)
         $user_info = !empty($purchase['user_id']) ? get_userdata($purchase['user_id']) : null;

         // 6. Fetch Request Counts from *this* plugin's DB Handler
         $counts = ['today' => __('N/A', 'bin-lookup-api'), 'week' => __('N/A', 'bin-lookup-api'), 'month' => __('N/A', 'bin-lookup-api'), 'total' => __('N/A', 'bin-lookup-api')];
         if (isset($this->db_handler)) {
              $counts = $this->db_handler->get_request_counts_for_key($api_key);
         }

         // 7. Prepare Details for Response (Sanitize all output)
         $details = array(
             'api_key'         => esc_html($purchase['api_key']),
             'status'          => esc_html(ucfirst($purchase['status'])),
             'user_id'         => esc_html($purchase['user_id']),
             'user_login'      => $user_info ? esc_html($user_info->user_login) : __('N/A', 'bin-lookup-api'),
             'display_name'    => $user_info ? esc_html($user_info->display_name) : __('N/A', 'bin-lookup-api'),
             'user_email'      => $user_info ? esc_html($user_info->user_email) : __('N/A', 'bin-lookup-api'),
             'plan_name'       => esc_html($purchase['plan_name'] ?? __('N/A', 'bin-lookup-api')),
             'feature_key'     => esc_html($purchase['product_feature_key'] ?? __('N/A', 'bin-lookup-api')),
             'purchase_date'   => esc_html($purchase['purchase_date'] ? date_i18n(get_option('date_format'), strtotime($purchase['purchase_date'])) : __('N/A', 'bin-lookup-api')),
             'expiry_date'     => esc_html($purchase['expiry_date'] ? date_i18n(get_option('date_format'), strtotime($purchase['expiry_date'])) : __('N/A', 'bin-lookup-api')),
             'limit_day'       => esc_html($purchase['plan_requests_per_day'] ?? __('Unlimited', 'bin-lookup-api')),
             'limit_month'     => esc_html($purchase['plan_requests_per_month'] ?? __('Unlimited', 'bin-lookup-api')),
             'count_today'     => esc_html(number_format_i18n($counts['today'])),
             'count_week'      => esc_html(number_format_i18n($counts['week'])),
             'count_month'     => esc_html(number_format_i18n($counts['month'])), // Rolling 30-day
             'count_total'     => esc_html(number_format_i18n($counts['total'])),
             'payment_gateway' => esc_html($purchase['payment_gateway'] ?: __('N/A', 'bin-lookup-api')),
             'transaction_id'  => esc_html($purchase['transaction_id'] ?: __('N/A', 'bin-lookup-api')),
         );

         // 8. Send successful JSON response
         wp_send_json_success($details);
     } // end handle_ajax_get_key_details


    // ===========================================
    //      Settings Page & Registration
    // ===========================================

    /**
     * Register plugin settings using the WordPress Settings API.
     * Defines sections and fields for the settings page.
     */
    public function register_settings() {
        // Register the main setting group and option name
        register_setting(
            'bin_api_settings_group',       // Option group (used in settings_fields())
            'bin_api_settings',             // Option name (stored in wp_options)
            array($this, 'sanitize_settings') // Callback function to sanitize data before saving
        );

        // --- Add Settings Sections ---
        add_settings_section( 'bin_api_general_section', __('General API Settings', 'bin-lookup-api'), null, 'bin-api-settings-page');
        add_settings_section( 'bin_api_security_section', __('Security Settings', 'bin-lookup-api'), null, 'bin-api-settings-page');
        add_settings_section( 'bin_api_error_section', __('Custom Error Messages', 'bin-lookup-api'), null, 'bin-api-settings-page');
        add_settings_section( 'bin_api_logging_section', __('Logging Settings', 'bin-lookup-api'), null, 'bin-api-settings-page');

        // --- Add Settings Fields ---
        // General Section
        add_settings_field('api_enabled', __('Enable API', 'bin-lookup-api'), array($this, 'api_enabled_field_callback'), 'bin-api-settings-page', 'bin_api_general_section');
        add_settings_field('allowed_methods', __('Allowed HTTP Methods', 'bin-lookup-api'), array($this, 'allowed_methods_field_callback'), 'bin-api-settings-page', 'bin_api_general_section');
        add_settings_field('response_format', __('Default Response Format', 'bin-lookup-api'), array($this, 'response_format_field_callback'), 'bin-api-settings-page', 'bin_api_general_section');
        // Security Section
        add_settings_field('allowed_ips', __('Allowed IP Addresses', 'bin-lookup-api'), array($this, 'allowed_ips_field_callback'), 'bin-api-settings-page', 'bin_api_security_section');
        // Error Message Section
        add_settings_field('error_unauthorized', __('Unauthorized Access Error', 'bin-lookup-api'), array($this, 'error_unauthorized_field_callback'), 'bin-api-settings-page', 'bin_api_error_section');
        add_settings_field('error_rate_limit', __('Rate Limit Exceeded Error', 'bin-lookup-api'), array($this, 'error_rate_limit_field_callback'), 'bin-api-settings-page', 'bin_api_error_section');
        // Logging Section
        add_settings_field('logging_enabled', __('Enable File Logging', 'bin-lookup-api'), array($this, 'logging_enabled_field_callback'), 'bin-api-settings-page', 'bin_api_logging_section');

        // Global Rate Limit field is intentionally removed.
    }

    // --- Settings Field Callback Functions ---
    // These functions generate the HTML for each setting field.

    public function api_enabled_field_callback() {
        $options = get_option('bin_api_settings', array('api_enabled' => 'yes'));
        $value = $options['api_enabled'] ?? 'yes';
        ?>
        <input type="checkbox" id="api_enabled" name="bin_api_settings[api_enabled]" value="yes" <?php checked('yes', $value); ?> />
        <label for="api_enabled"><?php esc_html_e('Enable the BIN Lookup REST API endpoint (/wp-json/bin/v1/...).', 'bin-lookup-api'); ?></label>
        <?php
    }

    public function allowed_methods_field_callback() {
        $options = get_option('bin_api_settings', array('allowed_methods' => array('GET')));
        $allowed = $options['allowed_methods'] ?? array('GET');
        $methods = ['GET', 'POST', 'PUT', 'DELETE']; // Common methods to offer
        ?>
        <fieldset>
            <legend class="screen-reader-text"><span><?php esc_html_e('Allowed HTTP Methods', 'bin-lookup-api'); ?></span></legend>
            <?php foreach ($methods as $method) : ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="bin_api_settings[allowed_methods][]" value="<?php echo esc_attr($method); ?>" <?php checked(in_array($method, $allowed)); ?>>
                    <?php echo esc_html($method); ?>
                </label>
            <?php endforeach; ?>
            <p class="description"><?php esc_html_e('Select the HTTP methods allowed for API requests. GET is recommended for lookups.', 'bin-lookup-api'); ?></p>
        </fieldset>
        <?php
    }

     public function response_format_field_callback() {
        $options = get_option('bin_api_settings', array('response_format' => 'json'));
        $value = $options['response_format'] ?? 'json';
        ?>
        <select id="response_format" name="bin_api_settings[response_format]">
            <option value="json" <?php selected('json', $value); ?>><?php esc_html_e('JSON (JavaScript Object Notation)', 'bin-lookup-api'); ?></option>
            <option value="xml" <?php selected('xml', $value); ?>><?php esc_html_e('XML (Extensible Markup Language)', 'bin-lookup-api'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Choose the default format for API responses.', 'bin-lookup-api'); ?></p>
        <?php
    }

     public function allowed_ips_field_callback() {
        $options = get_option('bin_api_settings', array('allowed_ips' => ''));
        $value = $options['allowed_ips'] ?? '';
        ?>
        <textarea id="allowed_ips" name="bin_api_settings[allowed_ips]" rows="4" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('Enter allowed IP addresses, one per line or separated by commas. Leave blank to allow requests from any IP address.', 'bin-lookup-api'); ?></p>
        <?php
    }

    public function error_unauthorized_field_callback() {
         $options = get_option('bin_api_settings', array('error_unauthorized' => __('Access denied.', 'bin-lookup-api')));
         $value = $options['error_unauthorized'] ?? __('Access denied.', 'bin-lookup-api');
         ?>
         <input type="text" id="error_unauthorized" name="bin_api_settings[error_unauthorized]" value="<?php echo esc_attr($value); ?>" class="regular-text">
         <p class="description"><?php esc_html_e('Error message shown for invalid/inactive API key, feature mismatch, or permission issues.', 'bin-lookup-api'); ?></p>
         <?php
    }

    public function error_rate_limit_field_callback() {
         $options = get_option('bin_api_settings', array('error_rate_limit' => __('Rate limit exceeded.', 'bin-lookup-api')));
         $value = $options['error_rate_limit'] ?? __('Rate limit exceeded.', 'bin-lookup-api');
         ?>
         <input type="text" id="error_rate_limit" name="bin_api_settings[error_rate_limit]" value="<?php echo esc_attr($value); ?>" class="regular-text">
         <p class="description"><?php esc_html_e('Error message shown when daily or monthly request limits (defined by the user\'s plan) are reached.', 'bin-lookup-api'); ?></p>
         <?php
    }

     public function logging_enabled_field_callback() {
        $options = get_option('bin_api_settings', array('logging_enabled' => 'no'));
        $value = $options['logging_enabled'] ?? 'no';
        ?>
        <input type="checkbox" id="logging_enabled" name="bin_api_settings[logging_enabled]" value="yes" <?php checked('yes', $value); ?> />
        <label for="logging_enabled"><?php printf(esc_html__('Enable logging requests to %s (in addition to database logging). Requires write permissions in the wp-content directory.', 'bin-lookup-api'), '<code>wp-content/bin-api-logs.txt</code>'); ?></label>
        <?php
    }

    /**
     * Sanitize settings data before saving it to the database.
     * Ensures data types are correct and potentially harmful input is removed.
     *
     * @param array $input Raw input array from the settings form submission ($_POST).
     * @return array The sanitized settings array ready to be saved.
     */
    public function sanitize_settings($input) {
        $sanitized_input = array();
        $defaults = array( // Define defaults for settings
             'api_enabled' => 'yes',
             'allowed_methods' => array('GET'),
             'response_format' => 'json',
             'allowed_ips' => '',
             'error_unauthorized' => __('Access denied.', 'bin-lookup-api'),
             'error_rate_limit' => __('Rate limit exceeded.', 'bin-lookup-api'),
             'logging_enabled' => 'no',
        );

        // Sanitize 'api_enabled' checkbox
        $sanitized_input['api_enabled'] = (isset($input['api_enabled']) && $input['api_enabled'] === 'yes') ? 'yes' : 'no';

        // Sanitize 'allowed_methods' checkbox group
        if (isset($input['allowed_methods']) && is_array($input['allowed_methods'])) {
             $valid_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
             $methods = array_map('strtoupper', $input['allowed_methods']);
             $sanitized_input['allowed_methods'] = array_intersect($methods, $valid_methods);
             // Ensure at least GET is allowed if none are selected? Or allow empty? Let's default to GET if empty.
             if (empty($sanitized_input['allowed_methods'])) {
                 $sanitized_input['allowed_methods'] = ['GET'];
             }
        } else {
            $sanitized_input['allowed_methods'] = $defaults['allowed_methods'];
        }

        // Sanitize 'response_format' select dropdown
        $sanitized_input['response_format'] = (isset($input['response_format']) && in_array($input['response_format'], ['json', 'xml']))
                                             ? $input['response_format']
                                             : $defaults['response_format'];

        // Sanitize 'allowed_ips' textarea
        if (isset($input['allowed_ips'])) {
            // Trim whitespace from each IP, remove empty entries, sanitize remaining
            $ips = array_filter(array_map('trim', explode("\n", sanitize_textarea_field($input['allowed_ips']))));
            $sanitized_ips = [];
            foreach ($ips as $ip) {
                // Remove potential commas and extra spaces within a line
                 $line_ips = array_filter(array_map('trim', explode(',', $ip)));
                 foreach ($line_ips as $single_ip) {
                     // Basic validation - check if it looks like an IP or CIDR
                      if (filter_var($single_ip, FILTER_VALIDATE_IP) || (strpos($single_ip, '/') !== false && filter_var(explode('/', $single_ip)[0], FILTER_VALIDATE_IP))) {
                          $sanitized_ips[] = $single_ip;
                      }
                 }
            }
            $sanitized_input['allowed_ips'] = implode(',', array_unique($sanitized_ips)); // Store as comma-separated
        } else {
            $sanitized_input['allowed_ips'] = $defaults['allowed_ips'];
        }

        // Sanitize error message text fields
        $sanitized_input['error_unauthorized'] = isset($input['error_unauthorized'])
                                               ? sanitize_text_field($input['error_unauthorized'])
                                               : $defaults['error_unauthorized'];
        $sanitized_input['error_rate_limit'] = isset($input['error_rate_limit'])
                                             ? sanitize_text_field($input['error_rate_limit'])
                                             : $defaults['error_rate_limit'];

         // Sanitize 'logging_enabled' checkbox
        $sanitized_input['logging_enabled'] = (isset($input['logging_enabled']) && $input['logging_enabled'] === 'yes') ? 'yes' : 'no';

        // Apply filter for potential modification
        return apply_filters('bin_lookup_api_sanitize_settings', $sanitized_input, $input);
    }

    /**
     * Display the HTML structure for the settings page.
     * Uses the WordPress Settings API functions settings_fields() and do_settings_sections().
     */
    public function settings_page_html() {
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'bin-lookup-api'));
        }
        ?>
        <div class="wrap bin-api-settings">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                // Output nonce, action, and option_page fields for the Settings API.
                // Parameter must match the settings group name used in register_setting().
                settings_fields('bin_api_settings_group');

                // Output the settings sections and their fields.
                // Parameter must match the page slug used in add_settings_section/add_settings_field.
                do_settings_sections('bin-api-settings-page');

                // Output the save settings button.
                submit_button(__('Save Settings', 'bin-lookup-api'));
                ?>
            </form>
        </div><?php
    }

    /**
     * Display the HTML content for the documentation page.
     * Provides basic usage instructions and examples.
     */
    public function docs_page_html() {
         ?>
        <div class="wrap bin-api-docs">
            <h1><?php esc_html_e('BIN Lookup API Documentation', 'bin-lookup-api'); ?></h1>
            <p><?php esc_html_e('This documentation provides basic instructions and examples for using the BIN Lookup API endpoint.', 'bin-lookup-api'); ?></p>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('API Endpoint', 'bin-lookup-api'); ?></span></h2>
                            <div class="inside">
                                <p><?php esc_html_e('The primary endpoint for performing BIN lookups is:', 'bin-lookup-api'); ?></p>
                                <p><code><?php echo esc_url(get_rest_url(null, 'bin/v1/lookup/{bin_number}')); ?></code></p>
                                <p><?php esc_html_e('Replace {bin_number} with the 6 to 8 digit Bank Identification Number you wish to look up.', 'bin-lookup-api'); ?></p>
                            </div>
                        </div>

                        <div class="postbox">
                             <h2 class="hndle"><span><?php esc_html_e('Authentication', 'bin-lookup-api'); ?></span></h2>
                             <div class="inside">
                                 <p><?php esc_html_e('Authentication is handled via an API key provided as a query string parameter.', 'bin-lookup-api'); ?></p>
                                 <p><?php esc_html_e('You must include your assigned API key (obtained through the Orunk Users system after purchasing a plan) in each request using the <code>api_key</code> parameter.', 'bin-lookup-api'); ?></p>
                                 <p><strong><?php esc_html_e('Example Request URL:', 'bin-lookup-api'); ?></strong></p>
                                 <p><code><?php echo esc_url(get_rest_url(null, 'bin/v1/lookup/457173')); ?>?api_key=YOUR_API_KEY_HERE</code></p>
                                 <p><small><?php esc_html_e('Replace YOUR_API_KEY_HERE with your actual API key.', 'bin-lookup-api'); ?></small></p>
                             </div>
                         </div>

                         <div class="postbox">
                              <h2 class="hndle"><span><?php esc_html_e('Request Method', 'bin-lookup-api'); ?></span></h2>
                              <div class="inside">
                                  <p><?php printf(__('The allowed HTTP methods for requests are configured in the plugin settings. Currently allowed: %s.', 'bin-lookup-api'), '<strong><code>' . implode('</code>, <code>', $this->get_allowed_methods()) . '</code></strong>'); ?></p>
                                  <p><?php esc_html_e('Using the GET method is generally recommended for simple lookups.', 'bin-lookup-api'); ?></p>
                              </div>
                          </div>

                          <div class="postbox">
                               <h2 class="hndle"><span><?php esc_html_e('Response Format', 'bin-lookup-api'); ?></span></h2>
                               <div class="inside">
                                   <?php $options = get_option('bin_api_settings', array('response_format' => 'json')); ?>
                                   <p><?php printf(__('The API response format is determined by the plugin settings (currently default is %s).', 'bin-lookup-api'), '<strong>' . esc_html(strtoupper($options['response_format'])) . '</strong>'); ?></p>
                                   <p><strong><?php esc_html_e('Example JSON Response (Success):', 'bin-lookup-api'); ?></strong></p>
                                   <pre><code class="language-json"><?php echo esc_html(wp_json_encode([
                                        'bin'           => '457173',
                                        'bank_name'     => 'JPMORGAN CHASE BANK, N.A.',
                                        'card_brand'    => 'VISA',
                                        'card_type'     => 'DEBIT',
                                        'card_level'    => 'CLASSIC',
                                        'country_iso'   => 'US',
                                        'country_name'  => 'United States',
                                        'source'        => 'database'
                                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></pre>
                                   <p><strong><?php esc_html_e('Example JSON Response (Not Found):', 'bin-lookup-api'); ?></strong></p>
                                    <pre><code class="language-json"><?php echo esc_html(wp_json_encode([
                                        'bin'           => '123456',
                                        'bank_name'     => 'Unknown',
                                        'card_brand'    => 'Unknown',
                                        'card_type'     => 'Unknown',
                                        'card_level'    => 'Unknown',
                                        'country_iso'   => 'Unknown',
                                        'country_name'  => 'Unknown',
                                        'source'        => 'not_found'
                                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></pre>
                                     <p><strong><?php esc_html_e('Example XML Response (Success):', 'bin-lookup-api'); ?></strong></p>
                                     <pre><code class="language-xml"><?php
                                         $sample_data = [
                                            'bin'           => '457173', 'bank_name'     => 'JPMORGAN CHASE BANK, N.A.', 'card_brand'    => 'VISA',
                                            'card_type'     => 'DEBIT', 'card_level'    => 'CLASSIC', 'country_iso'   => 'US',
                                            'country_name'  => 'United States', 'source'        => 'database' ];
                                         $xml = new SimpleXMLElement('<bin_lookup/>');
                                         $this->array_to_xml($sample_data, $xml);
                                         $dom = dom_import_simplexml($xml)->ownerDocument;
                                         $dom->formatOutput = true;
                                         echo esc_html($dom->saveXML($dom->documentElement));
                                     ?></code></pre>
                               </div>
                           </div>

                           <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e('Error Responses', 'bin-lookup-api'); ?></span></h2>
                                <div class="inside">
                                    <p><?php esc_html_e('If an error occurs (e.g., invalid API key, rate limit exceeded), the API will return a standard WordPress REST API error response. The format (JSON/XML) depends on the request context and server configuration, but JSON is typical.', 'bin-lookup-api'); ?></p>
                                    <p><strong><?php esc_html_e('Example JSON Error Response:', 'bin-lookup-api'); ?></strong></p>
                                     <pre><code class="language-json"><?php echo esc_html(wp_json_encode([
                                          'code' => 'invalid_api_key',
                                          'message' => 'Access denied.', // This message comes from settings
                                          'data' => ['status' => 403]
                                     ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></pre>
                                     <p><strong><?php esc_html_e('Common Error Codes:', 'bin-lookup-api'); ?></strong></p>
                                     <ul style="list-style: disc; margin-left: 20px;">
                                          <li><code>rest_no_route</code> (404): Endpoint URL is incorrect or API is disabled.</li>
                                          <li><code>no_api_key</code> (401): The <code>api_key</code> parameter is missing from the request.</li>
                                          <li><code>invalid_api_key</code> (403): The provided API key was not found in purchase records.</li>
                                          <li><code>key_not_active</code> (403): The API key exists but its associated purchase is not 'active' (e.g., pending, expired, cancelled).</li>
                                          <li><code>feature_mismatch</code> (403): The API key is valid but not for the 'bin_api' feature.</li>
                                          <li><code>key_expired</code> (403): The API key's associated purchase has passed its expiry date.</li>
                                          <li><code>ip_not_allowed</code> (403): The request originated from an IP address not configured in the 'Allowed IP Addresses' setting.</li>
                                          <li><code>rate_limit_exceeded_day</code> (429): The daily request limit for the API key's plan has been reached.</li>
                                          <li><code>rate_limit_exceeded_month</code> (429): The monthly request limit for the API key's plan has been reached.</li>
                                          <li><code>db_table_missing</code> (500): Internal Server Error - The required BIN data table (e.g., <code>wp_bsp_bins</code>) could not be found.</li>
                                          <li><code>plugin_dependency_missing</code> (503): Service Unavailable - The required 'Orunk Users' plugin is inactive or missing.</li>
                                          <li><code>xml_format_error</code> (500): Internal Server Error - Failed to generate XML response.</li>
                                          <li><code>internal_data_error</code> (500): Internal Server Error - Unexpected data format during response generation.</li>
                                     </ul>
                                </div>
                            </div>

                    </div><div id="postbox-container-1" class="postbox-container">
                         <?php // Sidebar with links or additional info can go here ?>
                         <div class="postbox">
                              <h2 class="hndle"><span><?php esc_html_e('Need Help?', 'bin-lookup-api'); ?></span></h2>
                              <div class="inside">
                                   <p><?php esc_html_e('Refer to the plugin settings and ensure the Orunk Users plugin is active and configured correctly.', 'bin-lookup-api'); ?></p>
                                   <?php // Add links to support or contact page if available ?>
                              </div>
                          </div>
                    </div></div><br class="clear">
            </div></div><?php
    } // end docs_page_html


} // End Class Custom_BIN_API


// ===========================================
//      Plugin Initialization Function
// ===========================================

/**
 * Instantiate the main plugin class and initialize hooks.
 * This function is hooked to 'plugins_loaded' to ensure dependencies are available.
 */
function bin_api_init() {
    // Check if the main class exists before instantiating to prevent fatal errors
    if (class_exists('Custom_BIN_API')) {
        $bin_api_instance = new Custom_BIN_API();
        // Register WordPress hooks (actions and filters)
        $bin_api_instance->init();
    } else {
        // Log an error or display an admin notice if the main class is missing
        error_log('BIN Lookup API Error: Main class Custom_BIN_API not found.');
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('BIN Lookup API Error:', 'bin-lookup-api') . '</strong> ' . esc_html__('Main plugin class failed to load. The plugin might be corrupted or installed incorrectly.', 'bin-lookup-api') . '</p></div>';
        });
    }
}
// Use 'plugins_loaded' hook for initialization to ensure all plugins and themes are loaded.
add_action('plugins_loaded', 'bin_api_init');


// ===========================================
//      Plugin Action Links
// ===========================================

/**
 * Add a "Settings" link to the plugin's action links on the WordPress Plugins page.
 *
 * @param array $links Existing action links for the plugin.
 * @return array Modified links array with the Settings link added.
 */
function bin_api_add_settings_link($links) {
    // Check if the current user has the capability to manage options
    if (current_user_can('manage_options')) {
        // Create the settings link pointing to the plugin's settings page
        $settings_link = '<a href="' . admin_url('admin.php?page=bin-api-settings') . '">' . esc_html__('Settings', 'bin-lookup-api') . '</a>';
        // Add the link to the beginning of the links array
        array_unshift($links, $settings_link);
    }
    return $links;
}
// Hook the function to the plugin_action_links filter for this specific plugin file
add_filter('plugin_action_links_' . BIN_LOOKUP_API_BASE_NAME, 'bin_api_add_settings_link');


// ===========================================
//      Optional: Documentation Shortcode
// ===========================================
/*
// Example: If you have a page with the slug 'bin-api-documentation' and content '[bin_lookup_api_docs]'
function bin_api_docs_shortcode_output() {
    // You could include a template file here or generate HTML directly
    ob_start();
    // include BIN_LOOKUP_API_DIR . 'templates/docs-template.php'; // Example template include
    echo "<p>This is where documentation generated by the shortcode would go.</p>";
    return ob_get_clean();
}
add_shortcode('bin_lookup_api_docs', 'bin_api_docs_shortcode_output');
*/

// Note: It's best practice in PHP files containing only code to omit the closing ?>
