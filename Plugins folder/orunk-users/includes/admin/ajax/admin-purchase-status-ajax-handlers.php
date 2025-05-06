<?php
/**
 * Orunk Users - Admin Purchase Status AJAX Handlers
 *
 * Handles AJAX requests related to updating purchase statuses in the admin interface.
 *
 * @package OrunkUsers\Admin\AJAX
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure Core, DB, and Purchase Manager classes are potentially available if not loaded globally
// (These checks add robustness in case this file is somehow loaded standalone)
if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
    // Define a fallback if not defined elsewhere (adjust path if needed)
    define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__, 3))); // Go up 3 levels from includes/admin/ajax/
    error_log('Warning: ORUNK_USERS_PLUGIN_DIR was not defined in purchase-status-handlers. Using fallback: ' . ORUNK_USERS_PLUGIN_DIR);
}
if (!class_exists('Custom_Orunk_Core')) {
    $core_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-core.php';
    if (file_exists($core_path)) { require_once $core_path; }
    else { error_log("Orunk AJAX Purchase Status FATAL: Cannot load Custom_Orunk_Core. Path: {$core_path}"); }
}
if (!class_exists('Custom_Orunk_DB')) {
    $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
    if (file_exists($db_path)) { require_once $db_path; }
    else { error_log("Orunk AJAX Purchase Status FATAL: Cannot load Custom_Orunk_DB. Path: {$db_path}"); }
}
// Need Purchase Manager for activating/failing/approving purchases
if (!class_exists('Custom_Orunk_Purchase_Manager')) {
    $pm_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-purchase-manager.php';
    if (file_exists($pm_path)) { require_once $pm_path; }
    else { error_log("Orunk AJAX Purchase Status FATAL: Cannot load Custom_Orunk_Purchase_Manager. Path: {$pm_path}"); }
}

/**
 * AJAX Handler: Update Purchase Status (Admin Action).
 * Handles the 'orunk_admin_update_purchase_status' action.
 */
function handle_admin_update_purchase_status() {
    // Check nonce and capability
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce');
    global $wpdb;

    // Ensure required classes are actually loaded before proceeding
    // Note: Custom_Orunk_Core is not directly used here anymore, but DB/PM are.
    if (!class_exists('Custom_Orunk_DB') || !class_exists('Custom_Orunk_Purchase_Manager')) {
        error_log("Orunk AJAX Purchase Update Error: Required DB or Purchase Manager class not loaded.");
        wp_send_json_error(['message' => __('Core components missing. Cannot update status.', 'orunk-users')], 500);
    }

    // Instantiate handlers
    $orunk_db         = new Custom_Orunk_DB();
    $purchase_manager = new Custom_Orunk_Purchase_Manager();

    // Get and validate input
    $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
    $purchase_id = isset($_POST['purchase_id']) ? absint($_POST['purchase_id']) : 0;
    $new_status_action = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';
    $allowed_statuses = ['pending', 'active', 'expired', 'cancelled', 'failed', 'approve_switch', 'Pending Payment'];

    if ($purchase_id <= 0) {
        wp_send_json_error(['message' => __('Invalid Purchase ID.', 'orunk-users')], 400);
    }

    if (!in_array($new_status_action, $allowed_statuses)) {
        wp_send_json_error(['message' => __('Invalid status or action provided.', 'orunk-users')], 400);
    }

    // Fetch the current purchase details
    $current_purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$purchases_table` WHERE id = %d", $purchase_id), ARRAY_A);
    if (!$current_purchase) {
        wp_send_json_error(['message' => __('Purchase record not found.', 'orunk-users')], 404);
    }

    // Initialize response variables
    $message = __('Status updated successfully.', 'orunk-users'); // Default success message
    $error_message = null; // Initialize error message tracker

    try {
        // --- Handle different actions ---

        if ($new_status_action === 'approve_switch') {
            // Use Purchase Manager to handle the switch approval logic
            if (empty($current_purchase['pending_switch_plan_id'])) {
                $error_message = __('No pending switch found for this purchase.', 'orunk-users');
            } else {
                $result = $purchase_manager->approve_manual_switch($purchase_id);
                if (is_wp_error($result)) {
                    $error_message = $result->get_error_message();
                } else {
                    $message = __('Pending plan switch approved successfully.', 'orunk-users');
                }
            }
        } elseif ($new_status_action === 'active') {
            // Use Purchase Manager to handle activation
            $result = $purchase_manager->activate_purchase(
                $purchase_id,
                $current_purchase['transaction_id'] ?? 'manual_admin_activation', // Use existing or default reason
                null, null, null, null, null, // Pass null for optional params not available here
                true // Force activation check bypass
            );
            if (is_wp_error($result)) {
                // Don't show error if already active or couldn't activate (e.g. not pending)
                if (!in_array($result->get_error_code(), ['already_active', 'not_pending_payment'])) {
                    $error_message = $result->get_error_message();
                } else {
                    // It was already active or in a state that couldn't be activated - consider it "done"
                    $message = __('Purchase status is already active or cannot be activated from its current state.', 'orunk-users');
                }
            }
        } elseif ($new_status_action === 'failed') {
            // Use Purchase Manager to handle recording failure
            $result = $purchase_manager->record_purchase_failure(
                $purchase_id,
                'Manually set to Failed by admin',
                $current_purchase['transaction_id'] // Pass existing TX ID if available
            );
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
            }
        } else {
            // Handle standard status updates directly (pending, expired, cancelled)
            // Map 'pending' UI action to 'Pending Payment' DB status
            $status_to_set = ($new_status_action === 'pending') ? 'Pending Payment' : ucfirst($new_status_action); // Ensure casing
            $update_data = ['status' => $status_to_set];
            $update_format = ['%s'];

            // Also clear pending switch ID if setting to expired/cancelled/failed
            if (in_array($status_to_set, ['Expired', 'Cancelled']) && !empty($current_purchase['pending_switch_plan_id'])) {
                 $update_data['pending_switch_plan_id'] = null;
                 $update_format[] = '%s'; // Format for null
            }
             // Clear failure details if setting to a non-failed state
             if ($status_to_set !== 'Failed' && (!empty($current_purchase['failure_reason']) || !empty($current_purchase['failure_timestamp']))) {
                  $update_data['failure_timestamp'] = null;
                  $update_data['failure_reason'] = null;
                  $update_format[] = '%s';
                  $update_format[] = '%s';
             }

            // Perform the direct DB update
            $updated = $wpdb->update(
                $purchases_table,
                $update_data,
                ['id' => $purchase_id],
                $update_format,
                ['%d']
            );

            if ($updated === false) {
                $error_message = __('Database error updating status.', 'orunk-users');
                error_log("Admin AJAX: DB Error updating purchase $purchase_id to status $status_to_set: " . $wpdb->last_error);
            } elseif ($updated === 0) {
                 // No rows affected, maybe status was already set?
                 error_log("Admin AJAX: DB update for purchase $purchase_id status to $status_to_set affected 0 rows.");
                 // Consider the operation successful if no DB error occurred.
            }
        }

        // --- Handle Response ---
        if ($error_message !== null) {
            wp_send_json_error(['message' => $error_message]);
        } else {
            // Fetch the latest details to send back for UI update
            $updated_purchase_for_response = $wpdb->get_row($wpdb->prepare(
                "SELECT p.status, p.expiry_date, p.pending_switch_plan_id, p.payment_gateway,
                        pl_switch.plan_name as pending_switch_plan_name
                 FROM `$purchases_table` p
                 LEFT JOIN `{$wpdb->prefix}orunk_product_plans` pl_switch ON p.pending_switch_plan_id = pl_switch.id
                 WHERE p.id = %d",
                $purchase_id
            ), ARRAY_A);

            // Determine final status and expiry display
            $final_status = $updated_purchase_for_response['status'] ?? $new_status_action; // Fallback
            $final_expiry = $updated_purchase_for_response['expiry_date']
                            ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($updated_purchase_for_response['expiry_date']))
                            : __('N/A', 'orunk-users');
            $final_can_approve_switch = (!empty($updated_purchase_for_response['pending_switch_plan_id']) && $updated_purchase_for_response['payment_gateway'] === 'bank');

            wp_send_json_success([
                'message'                  => $message, // Send the specific success message
                'updated_status'           => $final_status,
                'updated_expiry'           => $final_expiry,
                'is_switch_pending'        => !empty($updated_purchase_for_response['pending_switch_plan_id']),
                'pending_switch_plan_name' => $updated_purchase_for_response['pending_switch_plan_name'] ?: ($updated_purchase_for_response['pending_switch_plan_id'] ? 'Plan ID ' . $updated_purchase_for_response['pending_switch_plan_id'] : null),
                'can_approve_switch'       => $final_can_approve_switch // Check latest state
            ]);
        }

    } catch (Exception $e) {
         // Catch any other unexpected errors
         error_log('AJAX Exception in handle_admin_update_purchase_status: ' . $e->getMessage());
         wp_send_json_error(['message' => 'Server error updating purchase status.'], 500);
    }
     // wp_die(); // implicit in wp_send_json_*
}

?>