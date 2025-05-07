<?php
/**
 * Convojet Pro License Handler for Orunk Users
 *
 * Handles Convojet-specific interactions within the Orunk Users system,
 * primarily dashboard display and plugin downloads.
 * AJAX handler now returns JSON for JS to handle download/errors.
 *
 * Feature Key: convojet_pro
 *
 * @package OrunkUsers\Features\ConvojetLicensing
 * @version 2.0.5 // Returns JSON from download handler
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// ... (includes and class definition remain the same up to the method) ...
if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
    $plugin_dir_path = plugin_dir_path(dirname(__FILE__, 3));
    if (is_dir($plugin_dir_path)) {
         define('ORUNK_USERS_PLUGIN_DIR', $plugin_dir_path);
    } else {
        error_log('CRITICAL Convojet License Handler Error: Could not determine ORUNK_USERS_PLUGIN_DIR.');
        return;
    }
}
if (!class_exists('Custom_Orunk_DB')) {
    $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
    if (file_exists($db_path)) {
        require_once $db_path;
    } else {
         error_log("Convojet License Handler Warning: Custom_Orunk_DB class file not found at " . $db_path . ".");
    }
}
if (!class_exists('Custom_Orunk_Frontend')) {
    $frontend_path = ORUNK_USERS_PLUGIN_DIR . 'public/class-orunk-frontend.php';
    if (file_exists($frontend_path)) {
        require_once $frontend_path;
    } else {
         error_log("Convojet License Handler WARNING: Custom_Orunk_Frontend class file not found at " . $frontend_path . ".");
    }
}


class Convojet_License_Handler {

    private const FEATURE_KEY = 'convojet_pro';
    private $db = null;

    public function __construct() {
        if (class_exists('Custom_Orunk_DB')) {
            $this->db = new Custom_Orunk_DB();
        }
        add_action('orunk_dashboard_service_card_header', [$this, 'display_convojet_card_header'], 10, 3);
        add_action('orunk_dashboard_service_card_body', [$this, 'display_convojet_card_body'], 10, 2);
        // *** NOTE: The AJAX action name used in archive-product.php.php for Convojet is 'orunk_handle_convojet_download' ***
        // *** Ensure this matches if you intended it to be 'orunk_generic_download' or specific. Assuming it's specific. ***
        add_action('wp_ajax_orunk_handle_convojet_download', [$this, 'handle_plugin_download_request']);
        // If non-logged in users can download Convojet Pro (unlikely for a licensed product)
        // add_action('wp_ajax_nopriv_orunk_handle_convojet_download', [$this, 'handle_plugin_download_request']);
        add_filter('orunk_dashboard_handled_header_features', [$this, 'add_convojet_to_handled_list']);
        add_filter('orunk_dashboard_handled_body_features', [$this, 'add_convojet_to_handled_list']);
    }

    public function display_convojet_card_header($purchase, $feature_key, $display_info) { /* ... (no changes) ... */
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
    public function display_convojet_card_body($purchase, $feature_key) { /* ... (no changes) ... */
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
        <?php else: ?>
            <p class="text-sm text-red-600">License key not generated or found for this purchase. Please contact support if activation was recent.</p>
        <?php endif;
    }

    /**
      * AJAX Handler for Convojet download requests.
      * Now returns JSON instead of redirecting.
      */
     public function handle_plugin_download_request() {
         error_log("--- Convojet Download Handler (JSON Response Mode): START ---");

         // *** Nonce name should match what JS sends for this specific action ***
         // If JS uses orunkProductArchiveData.downloadNonce which creates 'orunk_product_download_nonce'
         // then the check_ajax_referer should use that.
         // Let's assume JS is calling 'orunk_handle_convojet_download' and using 'orunkProductArchiveData.downloadNonce'
         check_ajax_referer('orunk_product_download_nonce', 'nonce'); // Or your specific nonce for this action

         // User and purchase ID checks (assuming POST for Convojet, GET for generic)
         // For consistency, let's check $_REQUEST which covers GET and POST
         $purchase_id_from_request = isset($_REQUEST['purchase_id']) ? absint($_REQUEST['purchase_id']) : 0; // From dashboard button usually
         $feature_key_from_request = isset($_REQUEST['feature_key']) ? sanitize_key($_REQUEST['feature_key']) : null; // From archive link

         if (!is_user_logged_in()) {
             wp_send_json_error(['message' => __('Login required to download.', 'orunk-users'), 'code' => 'login_required'], 401);
         }
         $user_id = get_current_user_id();

         global $wpdb;
         $products_table = $wpdb->prefix . 'orunk_products';
         $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
         $actual_feature_key_to_check = self::FEATURE_KEY; // This handler is specific to Convojet

         // If called from archive, purchase_id might not be directly available.
         // We need to find an active purchase for this user for Convojet.
         if ($purchase_id_from_request <= 0 && $feature_key_from_request === self::FEATURE_KEY) {
             $active_convojet_purchase = $wpdb->get_row($wpdb->prepare(
                 "SELECT id, status, expiry_date, product_feature_key FROM `{$purchases_table}` WHERE user_id = %d AND product_feature_key = %s AND status = 'active' ORDER BY id DESC LIMIT 1",
                 $user_id, self::FEATURE_KEY
             ), ARRAY_A);
             if ($active_convojet_purchase) {
                 $purchase_id_from_request = $active_convojet_purchase['id'];
                 $purchase = $active_convojet_purchase;
                 error_log("Convojet Download: Found active purchase ID {$purchase_id_from_request} for user {$user_id}.");
             } else {
                 error_log("Convojet Download: No active purchase found for Convojet for user {$user_id}.");
                 wp_send_json_error(['message' => __('No active Convojet Pro license found to allow download.', 'orunk-users'), 'code' => 'no_license'], 403);
             }
         } elseif ($purchase_id_from_request > 0) {
            $purchase = $wpdb->get_row($wpdb->prepare( "SELECT id, status, expiry_date, product_feature_key FROM `{$purchases_table}` WHERE id = %d AND user_id = %d", $purchase_id_from_request, $user_id ), ARRAY_A);
         } else {
             wp_send_json_error(['message' => __('Invalid download request.', 'orunk-users'), 'code' => 'invalid_request'], 400);
         }


         if (!$purchase) { /* ... (same validation as before) ... */
            error_log("Convojet Download Handler Error: Purchase ID {$purchase_id_from_request} not found for User ID {$user_id}.");
            wp_send_json_error(['message' => __('Purchase record not found or access denied.', 'orunk-users'), 'code' => 'purchase_not_found'], 404);
         }
         if ($purchase['product_feature_key'] !== self::FEATURE_KEY) { /* ... */
             error_log("Convojet Download Handler Error: Feature key mismatch. Expected '" . self::FEATURE_KEY . "', found '{$purchase['product_feature_key']}'.");
             wp_send_json_error(['message' => __('License is not for this product.', 'orunk-users'), 'code' => 'feature_mismatch'], 403);
         }
         if ($purchase['status'] !== 'active') { /* ... */
             error_log("Convojet Download Handler Error: Purchase status is not active ('{$purchase['status']}').");
             wp_send_json_error(['message' => __('No active license found to allow download.', 'orunk-users'), 'code' => 'license_inactive'], 403);
         }
         if (!empty($purchase['expiry_date']) && strtotime($purchase['expiry_date']) < current_time('timestamp', 1) ) { /* ... */
                 error_log("Convojet Download Handler Error: License expired on {$purchase['expiry_date']}.");
                 wp_send_json_error(['message' => __('Your license has expired. Cannot download.', 'orunk-users'), 'code' => 'license_expired'], 403);
         }


         $feature_details = $wpdb->get_row($wpdb->prepare( "SELECT id as product_db_id, download_url, download_limit_daily FROM `{$products_table}` WHERE feature = %s", self::FEATURE_KEY ), ARRAY_A);
         if (!$feature_details || empty($feature_details['download_url'])) { /* ... */
             error_log("Convojet Download Handler Error: No download URL configured for feature '" . self::FEATURE_KEY . "'.");
             wp_send_json_error(['message' => __('Download link is not available. Please contact support.', 'orunk-users'), 'code' => 'no_url_configured'], 500);
         }
         $download_url = $feature_details['download_url'];
         $product_db_id_for_metric = $feature_details['product_db_id'];


         $limit_per_day = max(0, (int)($feature_details['download_limit_daily'] ?? 0));
         if ($limit_per_day > 0) {
            $today_date_gmt = gmdate('Y-m-d');
            $meta_key_daily_downloads = '_orunk_user_dl_count_' . self::FEATURE_KEY . '_' . $today_date_gmt;
            $current_daily_downloads = (int) get_user_meta($user_id, $meta_key_daily_downloads, true);
            if ($current_daily_downloads >= $limit_per_day) {
                error_log("Convojet Download Handler Error: User {$user_id} reached daily limit ({$current_daily_downloads}/{$limit_per_day}) for ".self::FEATURE_KEY.".");
                wp_send_json_error([
                    'message' => sprintf( __('You have reached your daily download limit (%d) for this product.', 'orunk-users'), $limit_per_day ),
                    'code' => 'limit_reached'
                ], 429);
            }
            update_user_meta($user_id, $meta_key_daily_downloads, $current_daily_downloads + 1);
         }

         // Increment static download count
         if (!empty($product_db_id_for_metric) && function_exists('orunk_update_product_metric')) {
             orunk_update_product_metric($product_db_id_for_metric, 'downloads_count', 1, true);
         } else {
             error_log("Convojet DL Handler: Missing product_db_id or helper to update static downloads_count.");
         }

         error_log("Convojet Download Handler: SUCCESS. Preparing JSON response with URL {$download_url} for user {$user_id}.");
         wp_send_json_success(['download_url' => esc_url_raw($download_url)]); // *** CHANGED FROM wp_redirect ***
     }

    public function add_convojet_to_handled_list($features) { /* ... (no changes) ... */
        if (!is_array($features)) { $features = []; }
        $features[] = self::FEATURE_KEY;
        return array_unique($features);
    }

} // end class Convojet_License_Handler
?>