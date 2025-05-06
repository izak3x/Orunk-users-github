<?php
/**
 * Orunk Users - Admin Users & Purchases AJAX Handlers
 *
 * Handles AJAX requests related to listing users and their purchases
 * in the admin interface.
 *
 * MODIFIED (Activation Tracking):
 * - handle_admin_get_user_purchases: Now fetches and includes activation count
 * and limit information for licensed purchases.
 *
 * @package OrunkUsers\Admin\AJAX
 * @version 1.2.0 // Version update for activation info fetching
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure dependencies are loaded if needed (robustness check)
if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
    // Define a fallback if not defined elsewhere
    define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__, 3)));
    error_log('Warning: ORUNK_USERS_PLUGIN_DIR was not defined in admin-users-handlers. Using fallback: ' . ORUNK_USERS_PLUGIN_DIR);
}
if (!class_exists('Custom_Orunk_DB')) {
    $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
    if (file_exists($db_path)) {
        require_once $db_path;
    } else {
        error_log("Orunk AJAX User Handlers FATAL: Cannot load Custom_Orunk_DB. Path: {$db_path}");
        // If DB class is missing, these handlers will fail. Return early if not in AJAX context.
        if (!defined('DOING_AJAX') || !DOING_AJAX) return;
    }
}
// Ensure the helper function is available (defined in admin-ajax-helpers.php)
if (!function_exists('orunk_admin_check_ajax_permissions')) {
    $helper_path = dirname(__FILE__) . '/admin-ajax-helpers.php';
    if (file_exists($helper_path)) {
        require_once $helper_path;
    } else {
        error_log("Orunk Admin AJAX Error: admin-ajax-helpers.php not found in admin-users-handlers.");
        if (defined('DOING_AJAX') && DOING_AJAX) {
            wp_send_json_error(['message' => 'Admin helper function missing.'], 500);
        }
        return;
    }
}


/**
 * AJAX Handler: Get Users List for Admin Interface.
 * Handles the 'orunk_admin_get_users_list' action.
 * (Function unchanged from original)
 */
function handle_admin_get_users_list() {
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce');
    global $wpdb;

    try {
        $search_term = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $query_args = [ 'orderby' => 'display_name', 'order' => 'ASC' ];
        if (!empty($search_term)) { $query_args['search'] = '*' . $search_term . '*'; $query_args['search_columns'] = ['user_login', 'user_email', 'display_name']; }

        $users_query = new WP_User_Query($query_args);
        $users       = $users_query->get_results();
        $users_data  = [];
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';

        if (!empty($users)) {
            foreach ($users as $user) {
                if ($user instanceof WP_User) {
                    $purchase_count = $wpdb->get_var($wpdb->prepare( "SELECT COUNT(*) FROM `$purchases_table` WHERE user_id = %d", $user->ID ));
                    if ($wpdb->last_error) { error_log("Orunk DB Error counting purchases for user {$user->ID}: " . $wpdb->last_error); $purchase_count = 0; }
                    $users_data[] = [
                        'id'             => $user->ID, 'login' => $user->user_login,
                        'display_name'   => $user->display_name, 'email' => $user->user_email,
                        'avatar'         => get_avatar_url($user->ID, ['size' => 64]),
                        'edit_link'      => get_edit_user_link($user->ID),
                        'purchase_count' => (int) $purchase_count,
                    ];
                }
            }
        }
        wp_send_json_success(['users' => $users_data]);
    } catch (Exception $e) {
        error_log('AJAX Exception in handle_admin_get_users_list: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Server error fetching users list.'], 500);
    }
}

/**
 * AJAX Handler: Get a Specific User's Purchases for Admin Interface Modal.
 * Handles the 'orunk_admin_get_user_purchases' action.
 * *** MODIFIED: Fetches and includes activation count/limit data ***
 */
function handle_admin_get_user_purchases() {
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce');
    global $wpdb;

    // Ensure DB Class is available
    if (!class_exists('Custom_Orunk_DB')) {
         wp_send_json_error(['message' => 'Database class missing.'], 500);
    }
    $orunk_db = new Custom_Orunk_DB(); // Instantiate DB handler

    // Validate User ID input
    $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    if ($user_id <= 0) {
        wp_send_json_error(['message' => __('Invalid User ID provided.', 'orunk-users')], 400);
    }

    try {
        // Fetch ALL purchases for the user using the updated DB method (from Phase 1)
        // This method now joins plans/products and includes activation_limit, feature_name etc.
        $purchases = $orunk_db->get_user_purchases($user_id); // Fetch all statuses

        if ($purchases === false) { // Check if the DB method itself failed
             error_log("Orunk DB Error calling get_user_purchases for user {$user_id}: " . $wpdb->last_error);
             throw new Exception('Database error fetching purchases.');
        }

        $sanitized_purchases = [];
        if (!empty($purchases)) {
            foreach ($purchases as $purchase) {
                // --- Activation Info Variables ---
                $activation_count = null;
                $effective_limit = null;
                $activation_limit_display = 'N/A'; // Text display for limit
                $activation_summary = 'N/A'; // e.g., "3 / 5 Used"
                $requires_license = false;
                $supports_activation_management = false;

                // --- Check if this feature requires licensing ---
                $feature_key = $purchase['product_feature_key'] ?? null;
                $license_key = $purchase['license_key'] ?? null;

                if ($feature_key) {
                    $requires_license = $orunk_db->get_feature_requires_license($feature_key);
                }

                // --- If licensed, get activation details ---
                if ($requires_license && !empty($license_key)) {
                    $supports_activation_management = true; // Mark as manageable

                    // Get active count
                    $activation_count = $orunk_db->get_active_activation_count($license_key);

                    // Determine effective limit (override or plan)
                    if (isset($purchase['override_activation_limit']) && is_numeric($purchase['override_activation_limit']) && $purchase['override_activation_limit'] > 0) {
                         $effective_limit = intval($purchase['override_activation_limit']);
                    } elseif (isset($purchase['activation_limit']) && is_numeric($purchase['activation_limit']) && $purchase['activation_limit'] > 0) {
                         $effective_limit = intval($purchase['activation_limit']);
                    } // else effective_limit remains null (unlimited)

                    // Format display strings
                    $activation_limit_display = ($effective_limit === null) ? 'Unlimited' : $effective_limit;
                    $activation_summary = sprintf('%d / %s Used', $activation_count, $activation_limit_display);

                } // End if requires license and has key

                // --- Sanitize and format purchase data for the response ---
                $sanitized_purchases[] = [
                    'id'                        => $purchase['id'],
                    'plan_name'                 => $purchase['plan_name'] ?: __('Plan Deleted', 'orunk-users'),
                    'feature_key'               => $feature_key ?: __('N/A', 'orunk-users'),
                    'feature_name'              => $purchase['feature_name'] ?: ($feature_key ?: __('N/A', 'orunk-users')), // Use product name if available
                    'status'                    => $purchase['status'],
                    'purchase_date'             => $purchase['purchase_date'] ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($purchase['purchase_date'])) : 'N/A',
                    'expiry_date'               => $purchase['expiry_date'] ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($purchase['expiry_date'])) : __('N/A', 'orunk-users'),
                    'api_key_masked'            => $purchase['api_key'] ? substr($purchase['api_key'], 0, 8) . '...' . substr($purchase['api_key'], -4) : null,
                    'license_key_masked'        => $license_key ? substr($license_key, 0, 8) . '...' . substr($license_key, -6) : null, // Mask license key
                    'gateway'                   => $purchase['payment_gateway'] ?: __('N/A', 'orunk-users'),
                    'transaction_id'            => $purchase['transaction_id'] ?: __('N/A', 'orunk-users'),
                    'transaction_type'          => $purchase['transaction_type'] ?? 'purchase',
                    'is_switch_pending'         => !empty($purchase['pending_switch_plan_id']) && is_numeric($purchase['pending_switch_plan_id']) && $purchase['pending_switch_plan_id'] > 0,
                    'pending_switch_plan_id'    => $purchase['pending_switch_plan_id'],
                    'pending_switch_plan_name'  => $purchase['pending_switch_plan_name'] ?: ($purchase['pending_switch_plan_id'] ? 'Plan ID ' . $purchase['pending_switch_plan_id'] : null),
                    'can_approve_switch'        => (!empty($purchase['pending_switch_plan_id']) && $purchase['payment_gateway'] === 'bank'),
                    'failure_reason'            => $purchase['failure_reason'],
                    'failure_timestamp'         => $purchase['failure_timestamp'] ? date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($purchase['failure_timestamp'])) : null,
                    // --- Added Activation Fields ---
                    'activation_count'          => $activation_count, // Integer or null
                    'activation_limit'          => $effective_limit, // Integer or null (for unlimited)
                    'activation_limit_display'  => $activation_limit_display, // String 'Unlimited' or number
                    'activation_summary'        => $activation_summary, // String like "3 / 5 Used" or "N/A"
                    'supports_activation_management' => $supports_activation_management, // Boolean
                ];
            }
        }

        // Send successful JSON response
        wp_send_json_success(['purchases' => $sanitized_purchases]);

    } catch (Exception $e) {
        error_log('AJAX Exception in handle_admin_get_user_purchases: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Server error fetching user purchases.'], 500);
    }
}

?>