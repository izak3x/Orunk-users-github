<?php
/**
 * Convojet Pro License Handler for Orunk Users
 *
 * Handles Convojet-specific interactions within the Orunk Users system,
 * primarily dashboard display and plugin downloads.
 *
 * Core license generation and validation are now handled by the central
 * Orunk Users system (Purchase Manager hooks and License API Handler).
 *
 * Feature Key: convojet_pro
 *
 * @package OrunkUsers\Features\ConvojetLicensing
 * @version 2.0.2 // Added download handler logging
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure ORUNK_USERS_PLUGIN_DIR is defined (usually by the main plugin file)
if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
    $plugin_dir_path = plugin_dir_path(dirname(__FILE__, 3)); // Go up three directories
    if (is_dir($plugin_dir_path)) {
         define('ORUNK_USERS_PLUGIN_DIR', $plugin_dir_path);
    } else {
        error_log('CRITICAL Convojet License Handler Error: Could not determine ORUNK_USERS_PLUGIN_DIR.');
        return;
    }
}

// Ensure DB class is loaded if potentially needed by remaining methods
if (!class_exists('Custom_Orunk_DB')) {
    $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
    if (file_exists($db_path)) {
        require_once $db_path;
    } else {
         error_log("Convojet License Handler Warning: Custom_Orunk_DB class file not found at " . $db_path . ".");
    }
}
// Ensure Frontend class is potentially available for reference (though filters are preferred)
if (!class_exists('Custom_Orunk_Frontend')) {
    $frontend_path = ORUNK_USERS_PLUGIN_DIR . 'public/class-orunk-frontend.php';
    if (file_exists($frontend_path)) {
        require_once $frontend_path;
    } else {
         error_log("Convojet License Handler WARNING: Custom_Orunk_Frontend class file not found at " . $frontend_path . ".");
    }
}


class Convojet_License_Handler {

    /** @var string The unique feature key for Convojet Pro */
    private const FEATURE_KEY = 'convojet_pro';

    /** @var Custom_Orunk_DB|null Database handler instance */
    private $db = null;

    /**
     * Constructor: Registers necessary WordPress hooks for dashboard display and downloads.
     */
    public function __construct() {
        if (class_exists('Custom_Orunk_DB')) {
            $this->db = new Custom_Orunk_DB();
        }
        add_action('orunk_dashboard_service_card_header', [$this, 'display_convojet_card_header'], 10, 3);
        add_action('orunk_dashboard_service_card_body', [$this, 'display_convojet_card_body'], 10, 2);
        add_action('wp_ajax_orunk_handle_convojet_download', [$this, 'handle_plugin_download_request']); // The hook for the download action
        add_filter('orunk_dashboard_handled_header_features', [$this, 'add_convojet_to_handled_list']);
        add_filter('orunk_dashboard_handled_body_features', [$this, 'add_convojet_to_handled_list']);
    }

    /**
     * Renders the specific header for the Convojet service card.
     */
    public function display_convojet_card_header($purchase, $feature_key, $display_info) {
        if ($feature_key !== self::FEATURE_KEY) return;
        $plan_name = $purchase['plan_name'] ?? __('N/A', 'orunk-users');
        $feature_title = $display_info['title'] ?? 'Convojet Pro';
        ?>
        <h4 class="text-base font-semibold flex items-center gap-2 flex-wrap">
            <i class="fas <?php echo esc_attr($display_info['icon'] ?: 'fa-comments'); ?>"></i>
            <span><?php echo esc_html($feature_title); ?></span>
             <?php if ($plan_name !== $feature_title) : ?>
                <span class="text-base font-medium text-gray-500">- <?php echo esc_html($plan_name); ?></span>
             <?php endif; ?>
            <span class="inline-flex items-center gap-1 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full border border-blue-200 ml-1">
                <i class="fab fa-wordpress"></i> WordPress Plugin
            </span>
        </h4>
        <?php
    }

    /**
     * Renders the specific body for the Convojet service card (License Key & Download Button).
     */
    public function display_convojet_card_body($purchase, $feature_key) {
        if ($feature_key !== self::FEATURE_KEY) return;

        $license_key = $purchase['license_key'] ?? null;
        $purchase_id = $purchase['id'] ?? 0;

        if ($license_key):
            ?>
            <div class="mb-4">
                <label class="form-label !text-xs !mb-1">License Key</label>
                <div class="flex items-center gap-2">
                    <div class="api-key-display flex-grow" id="license-key-display-<?php echo esc_attr($purchase_id); ?>"><?php echo esc_html(substr($license_key, 0, 8) . '...' . substr($license_key, -6)); ?></div>
                    <button class="orunk-button-outline orunk-button-sm orunk-button-icon orunk-copy-button" data-full-key="<?php echo esc_attr($license_key); ?>" title="Copy License Key"><i class="far fa-copy"></i></button>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2 mb-4">Use this key to activate the Convojet plugin on your website.</p>
        <?php else:
            ?>
            <p class="text-sm text-red-600">License key not generated or found for this purchase. Please contact support if activation was recent.</p>
        <?php endif;
        // NOTE: The direct download button display was removed from here in the previous step.
    }

    /**
      * AJAX Handler for Convojet download requests. Checks license and limits.
      * *** ADDED DEBUG LOGGING ***
      */
     public function handle_plugin_download_request() {
         error_log("--- Convojet Download Handler: START ---"); // Log start

         // 1. Nonce Check
         $nonce_verified = check_ajax_referer('orunk_convojet_download_nonce', 'nonce', false); // Check nonce without dying
         if (!$nonce_verified) {
             error_log("Convojet Download Handler Error: Nonce verification failed.");
             wp_send_json_error(['message' => __('Security check failed.', 'orunk-users')], 403); // Use 403 for security fail
             // wp_die() is called by wp_send_json_error
         }
         error_log("Convojet Download Handler: Nonce OK.");

         // 2. Login Check
         if (!is_user_logged_in()) {
             error_log("Convojet Download Handler Error: User not logged in.");
             wp_send_json_error(['message' => __('Login required to download.', 'orunk-users')], 401);
         }
         $user_id = get_current_user_id();
         error_log("Convojet Download Handler: User ID {$user_id} is logged in.");

         // 3. Purchase ID Check
         $purchase_id = isset($_POST['purchase_id']) ? absint($_POST['purchase_id']) : 0;
         if ($purchase_id <= 0) {
             error_log("Convojet Download Handler Error: Invalid purchase_id received: " . print_r($_POST['purchase_id'] ?? 'Not set', true));
             wp_send_json_error(['message' => __('Invalid download request (purchase ID missing).', 'orunk-users')], 400); // Use 400 for bad input
         }
         error_log("Convojet Download Handler: Received Purchase ID {$purchase_id}.");

         // 4. DB Handler Check
         if (!$this->db) {
              error_log("Convojet Download Handler Error: DB handler missing.");
              wp_send_json_error(['message' => __('Database component unavailable. Cannot process download.', 'orunk-users')], 500);
         }
         error_log("Convojet Download Handler: DB Handler OK.");

         // 5. Fetch Purchase Data
         global $wpdb;
         $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
         $purchase = $wpdb->get_row($wpdb->prepare( "SELECT id, status, expiry_date, product_feature_key FROM `{$purchases_table}` WHERE id = %d AND user_id = %d", $purchase_id, $user_id ), ARRAY_A);
         error_log("Convojet Download Handler: Purchase Query Result: " . print_r($purchase, true));

         // 6. Validate Purchase Data
         if (!$purchase) {
            error_log("Convojet Download Handler Error: Purchase ID {$purchase_id} not found for User ID {$user_id}.");
            wp_send_json_error(['message' => __('Purchase record not found or access denied.', 'orunk-users')], 404); // 404 Not Found
         }
         if ($purchase['product_feature_key'] !== self::FEATURE_KEY) {
             error_log("Convojet Download Handler Error: Feature key mismatch. Expected '" . self::FEATURE_KEY . "', found '{$purchase['product_feature_key']}'.");
             wp_send_json_error(['message' => __('License is not for this product.', 'orunk-users')], 403);
         }
         if ($purchase['status'] !== 'active') {
             error_log("Convojet Download Handler Error: Purchase status is not active ('{$purchase['status']}').");
             wp_send_json_error(['message' => __('No active license found to allow download.', 'orunk-users')], 403);
         }
         if (!empty($purchase['expiry_date'])) {
             $expiry_timestamp = strtotime($purchase['expiry_date']);
             if ($expiry_timestamp < current_time('timestamp', 1)) {
                 error_log("Convojet Download Handler Error: License expired on {$purchase['expiry_date']}.");
                 wp_send_json_error(['message' => __('Your license has expired. Cannot download.', 'orunk-users')], 403);
             }
         }
         error_log("Convojet Download Handler: Purchase Validation OK.");

         // 7. Fetch Feature Download Details
         $products_table = $wpdb->prefix . 'orunk_products';
         $feature_details = $wpdb->get_row($wpdb->prepare( "SELECT download_url, download_limit_daily FROM `{$products_table}` WHERE feature = %s", self::FEATURE_KEY ), ARRAY_A);
         error_log("Convojet Download Handler: Feature Details Query Result: " . print_r($feature_details, true));

         if (!$feature_details || empty($feature_details['download_url'])) {
             error_log("Convojet Download Handler Error: No download URL configured for feature '" . self::FEATURE_KEY . "' in products table (Purchase ID: {$purchase_id}).");
             wp_send_json_error(['message' => __('Download link is not available. Please contact support.', 'orunk-users')], 500);
         }
         $download_url = $feature_details['download_url'];
         error_log("Convojet Download Handler: Download URL Found: {$download_url}");

         // 8. Check Rate Limit
         $limit_per_day = max(1, (int)($feature_details['download_limit_daily'] ?? 5)); // Default 5, min 1
         $today_date_gmt = gmdate('Y-m-d');
         $meta_key = '_orunk_dl_count_' . self::FEATURE_KEY . '_' . $today_date_gmt;
         $current_count = (int) get_user_meta($user_id, $meta_key, true);
         error_log("Convojet Download Handler: Rate Limit Check. Key: {$meta_key}, Current Count: {$current_count}, Limit: {$limit_per_day}");

         if ($current_count >= $limit_per_day) {
            error_log("Convojet Download Handler Error: User {$user_id} reached daily limit ({$current_count}/{$limit_per_day}).");
            wp_send_json_error([
                'message' => sprintf( __('You have reached your daily download limit (%d). Please try again tomorrow.', 'orunk-users'), $limit_per_day ),
                'code' => 'limit_reached'
            ], 429); // 429 Too Many Requests
         }
         error_log("Convojet Download Handler: Rate Limit OK.");

         // 9. Increment Count & Send Success
         $new_count = $current_count + 1;
         $meta_updated = update_user_meta($user_id, $meta_key, $new_count);
         set_transient($meta_key . '_expiry', true, DAY_IN_SECONDS); // Helper for potential future cleanup

         if (!$meta_updated && $current_count === 0) {
            error_log("Convojet Download Handler Error: Failed to update download count meta '{$meta_key}' for user {$user_id}. Existing value was: " . get_user_meta($user_id, $meta_key, true));
            // Allow download anyway but log the error, as it might be a race condition or permission issue to investigate
            // wp_send_json_error(['message' => __('Could not update download count. Please try again.', 'orunk-users')], 500);
         } else {
             error_log("Convojet Download Handler: Updated download count meta '{$meta_key}' to {$new_count} for user {$user_id}.");
         }


         error_log("Convojet Download Handler: SUCCESS. Sending download URL {$download_url} to user {$user_id}.");
         error_log("--- Convojet Download Handler: END ---");
         wp_send_json_success(['download_url' => esc_url_raw($download_url)]);
         // wp_die() is called by wp_send_json_success
     }

    /**
     * Adds the Convojet feature key to the list of handled features for dashboard display.
     */
    public function add_convojet_to_handled_list($features) {
        if (!is_array($features)) { $features = []; }
        $features[] = self::FEATURE_KEY;
        return array_unique($features);
    }

} // end class Convojet_License_Handler