<?php
/**
 * Orunk Users Admin Class
 *
 * Handles the main admin page for viewing users and their purchases.
 * Allows manual updating of purchase statuses and approving pending switches.
 *
 * @package OrunkUsers\Admin
 * @version 1.2.2 // Updated switch approval logic
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Custom_Orunk_Admin {

    /**
     * Initialize admin menu and action hooks.
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // Hook 'admin_post_orunk_update_purchase_status' is added in orunk-users.php
        // It points to handle_update_purchase_status in this class.
        // Ensure the global function handle_update_purchase_status calls $this->handle_update_purchase_status()
        // OR make handle_update_purchase_status static if not relying on instance properties.
        // (Current setup uses a global function defined in orunk-users.php, which is less ideal but functional)
    }

    /**
     * Add the main "Orunk Users" menu page.
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Orunk Users Manager', 'orunk-users'), // Page Title
            __('Orunk Users', 'orunk-users'),       // Menu Title
            'manage_options',                       // Capability required
            'orunk-users-manager',                  // Menu Slug (unique ID for the page)
            array($this, 'admin_page_html'),        // Function to display page content
            'dashicons-admin-users',                // Icon URL or dashicon class
            30                                      // Position in the menu order
        );
    }

    /**
     * Displays the main admin page HTML, listing users and their purchases.
     * (This uses the standard WP list table styling)
     */
    public function admin_page_html() {
        global $wpdb;
        // Define table names
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $plans_table = $wpdb->prefix . 'orunk_product_plans';

        // Display admin notices based on redirect messages
        $this->display_admin_notices();

        // Get all users
        // TODO: Implement pagination for large user counts
        $users_query = new WP_User_Query( array( 'orderby' => 'display_name', 'order' => 'ASC' ) );
        $users = $users_query->get_results();

        ?>
        <div class="wrap orunk-users-manager-wrap">
            <h1><?php esc_html_e('Orunk Users Manager', 'orunk-users'); ?></h1>
            <p><?php esc_html_e('View registered users and manage their purchased plans and statuses.', 'orunk-users'); ?></p>

            <?php // Optional: Add search/filter controls here ?>

            <form method="post"> <?php // Form needed for bulk actions if implemented later ?>
                <?php // Add bulk action controls if needed ?>
                <table class="wp-list-table widefat fixed striped users">
                    <thead>
                        <tr>
                            <?php // Optional: Add checkbox column for bulk actions ?>
                            <?php // <th scope="col" id="cb" class="manage-column column-cb check-column"><input type="checkbox"></th> ?>
                            <th scope="col" id="username" class="manage-column column-username column-primary"><?php esc_html_e('Username'); ?></th>
                            <th scope="col" id="name" class="manage-column column-name"><?php esc_html_e('Name'); ?></th>
                            <th scope="col" id="email" class="manage-column column-email"><?php esc_html_e('Email'); ?></th>
                            <th scope="col" id="purchases" class="manage-column column-purchases" style="width: 50%;"><?php esc_html_e('Purchases', 'orunk-users'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="the-list" data-wp-lists="list:user">
                        <?php if (empty($users)) : ?>
                            <tr class="no-items"><td class="colspanchange" colspan="4"><?php esc_html_e('No users found.', 'orunk-users'); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ($users as $user) : ?>
                                <?php
                                // Fetch user's purchases, joining plan names
                                // Consider limiting the number of purchases shown directly or adding a "View All" link
                                $purchases = $wpdb->get_results($wpdb->prepare(
                                    "SELECT p.*,
                                            pl.plan_name,
                                            pl_switch.plan_name as pending_switch_plan_name
                                     FROM $purchases_table p
                                     LEFT JOIN $plans_table pl ON p.plan_id = pl.id
                                     LEFT JOIN $plans_table pl_switch ON p.pending_switch_plan_id = pl_switch.id
                                     WHERE p.user_id = %d ORDER BY p.purchase_date DESC",
                                    $user->ID
                                ), ARRAY_A);
                                ?>
                                <tr id="user-<?php echo $user->ID; ?>">
                                     <?php // Optional: Checkbox cell ?>
                                     <?php // <th scope="row" class="check-column"><input type="checkbox" name="users[]" value="<?php echo esc_attr( $user->ID ); ?>"></th> ?>
                                    <td class="username column-username has-row-actions column-primary" data-colname="<?php esc_attr_e('Username'); ?>">
                                        <?php echo get_avatar($user->ID, 32); ?>
                                        <strong><a href="<?php echo get_edit_user_link($user->ID); ?>"><?php echo esc_html($user->user_login); ?></a></strong>
                                        <div class="row-actions">
                                            <span class="edit"><a href="<?php echo get_edit_user_link($user->ID); ?>"><?php esc_html_e('Edit'); ?></a></span>
                                            <?php // Optional: Add other row actions like "View Profile" ?>
                                        </div>
                                        <button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e('Show more details'); ?></span></button>
                                    </td>
                                    <td class="name column-name" data-colname="<?php esc_attr_e('Name'); ?>"><?php echo esc_html($user->display_name); ?></td>
                                    <td class="email column-email" data-colname="<?php esc_attr_e('Email'); ?>"><a href="mailto:<?php echo esc_attr($user->user_email); ?>"><?php echo esc_html($user->user_email); ?></a></td>
                                    <td class="purchases column-purchases" data-colname="<?php esc_attr_e('Purchases'); ?>">
                                        <?php if (empty($purchases)) : ?>
                                            <span style="color: #888;"><?php esc_html_e('No purchases found.', 'orunk-users'); ?></span>
                                        <?php else : ?>
                                            <ul style="margin: 0; padding: 0; list-style: none;">
                                                <?php foreach ($purchases as $purchase) : ?>
                                                    <?php
                                                        // Check if a switch is pending
                                                        $is_switch_pending = !empty($purchase['pending_switch_plan_id']) && is_numeric($purchase['pending_switch_plan_id']) && $purchase['pending_switch_plan_id'] > 0;
                                                    ?>
                                                    <li style="margin-bottom: 1.2em; padding-bottom: 0.8em; border-bottom: 1px dotted #ccc;">
                                                        <strong style="font-size: 1.1em;"><?php echo esc_html($purchase['plan_name'] ?: __('Plan Deleted', 'orunk-users')); ?></strong>
                                                        (<?php echo esc_html($purchase['product_feature_key'] ?: __('N/A', 'orunk-users')); ?>)
                                                        <br>
                                                        <small>
                                                            <?php esc_html_e('ID:', 'orunk-users'); ?> <?php echo esc_html($purchase['id']); ?> |
                                                            <?php esc_html_e('Type:', 'orunk-users'); ?> <span style="font-style: italic;"><?php echo esc_html(ucfirst(str_replace('_', ' ', $purchase['transaction_type'] ?? 'purchase'))); ?></span> |
                                                            <?php esc_html_e('Status:', 'orunk-users'); ?> <strong style="color: <?php echo self::get_status_color($purchase['status']); ?>;"><?php echo esc_html(ucfirst($purchase['status'])); ?></strong> |
                                                            <?php esc_html_e('Purchased:', 'orunk-users'); ?> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($purchase['purchase_date']))); ?> |
                                                            <?php esc_html_e('Expires:', 'orunk-users'); ?> <?php echo $purchase['expiry_date'] ? esc_html(date_i18n(get_option('date_format'), strtotime($purchase['expiry_date']))) : esc_html__('N/A', 'orunk-users'); ?>
                                                        </small>
                                                        <?php if ($purchase['api_key']) : ?>
                                                            <br><small><?php esc_html_e('API Key:', 'orunk-users'); ?> <code><?php echo esc_html(substr($purchase['api_key'], 0, 8) . '...' . substr($purchase['api_key'], -4)); ?></code></small>
                                                        <?php endif; ?>
                                                        <br><small>
                                                            <?php esc_html_e('Gateway:', 'orunk-users'); ?> <?php echo esc_html($purchase['payment_gateway'] ?: __('N/A', 'orunk-users')); ?> |
                                                            <?php esc_html_e('Trans ID:', 'orunk-users'); ?> <?php echo esc_html($purchase['transaction_id'] ?: __('N/A', 'orunk-users')); ?>
                                                        </small>

                                                        <?php // Display Pending Switch Info ?>
                                                        <?php if ($is_switch_pending) : ?>
                                                            <div style="margin-top: 5px; padding: 5px; background-color: #fff8e1; border: 1px solid #ffe599; border-radius: 3px; font-size: 0.9em;">
                                                                <strong><?php esc_html_e('Pending Switch To:', 'orunk-users'); ?></strong>
                                                                <?php echo esc_html($purchase['pending_switch_plan_name'] ?: 'Plan ID ' . $purchase['pending_switch_plan_id']); ?>
                                                                <?php // Determine context for pending switch ?>
                                                                <?php if ($purchase['payment_gateway'] === 'bank'): ?>
                                                                     (<?php esc_html_e('Requires manual approval', 'orunk-users'); ?>)
                                                                <?php else: // Assume Stripe or other gateway waiting for payment ?>
                                                                     (<?php esc_html_e('Awaiting payment', 'orunk-users'); ?>)
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php // Form to update status manually ?>
                                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 8px;">
                                                            <input type="hidden" name="action" value="orunk_update_purchase_status">
                                                            <input type="hidden" name="purchase_id" value="<?php echo esc_attr($purchase['id']); ?>">
                                                            <?php wp_nonce_field('orunk_update_status_' . $purchase['id']); ?>
                                                            <label for="status_<?php echo esc_attr($purchase['id']); ?>" class="screen-reader-text"><?php esc_html_e('Update Status:', 'orunk-users'); ?></label>
                                                            <select name="status" id="status_<?php echo esc_attr($purchase['id']); ?>" style="width: auto; vertical-align: middle; margin-right: 5px; padding: 2px 5px; font-size: 0.9em; height: auto;">
                                                                <option value="" disabled selected><?php esc_html_e('-- Select Action --', 'orunk-users'); ?></option>
                                                                <?php // Add Approve Switch option only if a switch is pending AND it was a bank transfer ?>
                                                                <?php if ($is_switch_pending && $purchase['payment_gateway'] === 'bank'): ?>
                                                                     <option value="approve_switch" style="font-weight:bold; color: green;"><?php esc_html_e('Approve Pending Switch', 'orunk-users'); ?></option>
                                                                     <option value="" disabled>--------------------</option> <?php // Separator ?>
                                                                <?php endif; ?>
                                                                <?php
                                                                    // Generate options, disabling the current status
                                                                    $statuses = ['pending', 'active', 'expired', 'cancelled', 'failed'];
                                                                    foreach ($statuses as $status_option) {
                                                                        printf(
                                                                            '<option value="%1$s" %2$s>%3$s</option>',
                                                                            esc_attr($status_option),
                                                                            selected($purchase['status'], $status_option, false), // Don't pre-select the current status
                                                                            sprintf(esc_html__('Set to %s', 'orunk-users'), esc_html(ucfirst($status_option)))
                                                                        );
                                                                    }
                                                                ?>
                                                            </select>
                                                            <button type="submit" class="button button-secondary button-small" style="vertical-align: middle;"><?php esc_html_e('Update', 'orunk-users'); ?></button>
                                                        </form>

                                                        <?php // Display failure reason if status is 'failed' ?>
                                                        <?php if ($purchase['status'] === 'Failed' && !empty($purchase['failure_reason'])) : ?>
                                                            <div style="margin-top: 5px; padding: 5px; background-color: #fee2e2; border: 1px solid #fecaca; border-radius: 3px; font-size: 0.85em; color: #991b1b;">
                                                                <strong><?php esc_html_e('Failure Reason:', 'orunk-users'); ?></strong>
                                                                <?php echo esc_html($purchase['failure_reason']); ?>
                                                                <?php if (!empty($purchase['failure_timestamp'])): ?>
                                                                    <br><small>(<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($purchase['failure_timestamp']))); ?>)</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                     <tfoot>
                        <tr>
                             <?php // Optional: Add checkbox column for bulk actions ?>
                             <?php // <th scope="col" class="manage-column column-cb check-column"><input type="checkbox"></th> ?>
                            <th scope="col" class="manage-column column-username column-primary"><?php esc_html_e('Username'); ?></th>
                            <th scope="col" class="manage-column column-name"><?php esc_html_e('Name'); ?></th>
                            <th scope="col" class="manage-column column-email"><?php esc_html_e('Email'); ?></th>
                            <th scope="col" class="manage-column column-purchases"><?php esc_html_e('Purchases', 'orunk-users'); ?></th>
                        </tr>
                    </tfoot>
                </table>
                 <?php // Add pagination links if implemented ?>
            </form>
        </div>
        <?php
    } // end admin_page_html


    /**
     * Displays admin notices based on the 'orunk_message' query parameter.
     */
    private function display_admin_notices() {
         if (isset($_GET['orunk_message'])) {
             $code = sanitize_key($_GET['orunk_message']);
             // Define user-friendly messages for status updates
             $messages = [
                  'status_updated'           => __('Purchase status updated successfully.', 'orunk-users'),
                  'status_update_error_db'   => __('Error updating purchase status in the database.', 'orunk-users'),
                  'purchase_not_found'       => __('Error: Purchase record not found.', 'orunk-users'),
                  'invalid_status'           => __('Error: Invalid status or action provided for update.', 'orunk-users'),
                  'activate_error_no_plan'   => __('Error: Cannot activate purchase because associated plan details are missing or invalid.', 'orunk-users'),
                  'switch_approved'          => __('Pending plan switch approved successfully. New purchase record created.', 'orunk-users'), // Updated message
                  'approve_error_no_pending' => __('Error: No pending switch found for this purchase.', 'orunk-users'),
                  'approve_error_core_missing' => __('Error approving switch: Core component missing.', 'orunk-users'),
                  'approve_error_plan_missing' => __('Error approving switch: Details for the new plan could not be found.', 'orunk-users'),
                  'approve_logic_pending'    => __('Notice: Switch approval logic triggered (check results).', 'orunk-users'), // Default fallback if no error/success specifically set
                  // Add any other message codes used in redirects
             ];

             // Determine message text and CSS class
             $message_text = $messages[$code] ?? __('Unknown action status.', 'orunk-users');
             $message_class = 'notice-warning'; // Default to warning
             if (strpos($code, 'error') !== false || strpos($code, 'invalid') !== false || strpos($code, 'not_found') !== false) {
                 $message_class = 'notice-error';
             } elseif (strpos($code, 'approved') !== false || strpos($code, 'updated') !== false) {
                  $message_class = 'notice-success';
             }

             // Display the notice
             echo '<div id="message" class="notice ' . esc_attr($message_class) . ' is-dismissible"><p>' . esc_html($message_text) . '</p></div>';
        }
    }

    /**
     * Helper function to get a color based on status.
     * (Kept private and static as it doesn't rely on instance properties)
     */
    private static function get_status_color($status) {
        switch (strtolower($status)) { // Use strtolower for consistency
            case 'active': return 'green';
            case 'pending payment': return 'orange'; // Specific for 'Pending Payment'
            case 'pending': return 'orange'; // Catch generic 'pending' too
            case 'expired': return '#6b7280'; // Gray
            case 'cancelled': return '#dc2626'; // Red
            case 'failed': return '#ef4444'; // Brighter Red
            case 'switched': return '#9ca3af'; // Lighter Gray
            default: return '#4b5563'; // Darker Gray for unknown
        }
    }

    /**
     * Handles the submission from the admin user list page to update purchase status.
     * Hooked globally to `admin_post_orunk_update_purchase_status` in the main plugin file.
     * This method performs the core logic for updating status, including approving switches.
     *
     * IMPORTANT: This method is currently defined as non-static. Ensure it's called correctly
     * via an instance or make it static if it doesn't depend on instance properties ($this->db, etc.).
     * The current setup in orunk-users.php calls a global function, which then ideally calls this method.
     * Let's assume the global function properly instantiates this class if needed.
     */
    public function handle_update_purchase_status() {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $admin_page_url = admin_url('admin.php?page=orunk-users-manager'); // Redirect back to main admin page
        $purchase_id = isset($_POST['purchase_id']) ? absint($_POST['purchase_id']) : 0;
        $trigger_activation_hook = false; // Initialize flag
        $plan_details_for_hook = null; // Initialize details

        // Verify nonce and capability
        if ($purchase_id <= 0 || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'orunk_update_status_' . $purchase_id)) {
            wp_die(__('Security check failed or invalid purchase ID.', 'orunk-users'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to update purchase statuses.', 'orunk-users'));
        }

        $new_status_action = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';
        $allowed_statuses = ['pending', 'active', 'expired', 'cancelled', 'failed', 'approve_switch'];
        $message_code = ''; // Initialize message code

        if (!in_array($new_status_action, $allowed_statuses)) {
            $message_code = 'invalid_status';
        } else {
            $current_purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM $purchases_table WHERE id = %d", $purchase_id), ARRAY_A);

            if ($current_purchase) {
                $update_data = [];
                $update_formats = [];
                $message_code = 'status_updated'; // Default success message

                // Handle 'approve_switch' action specifically
                if ($new_status_action === 'approve_switch') {
                    $orunk_core = null; // Initialize Core instance

                    // Ensure Custom_Orunk_Core is available
                    if (class_exists('Custom_Orunk_Core')) {
                        $orunk_core = new Custom_Orunk_Core();
                    } else {
                        $core_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-core.php';
                        if (file_exists($core_path)) {
                            require_once $core_path;
                            if(class_exists('Custom_Orunk_Core')) {
                                $orunk_core = new Custom_Orunk_Core();
                            }
                        }
                    }

                    if (!$orunk_core || !method_exists($orunk_core, 'approve_manual_switch')) {
                         $message_code = 'approve_error_core_missing';
                         error_log("Orunk Admin Manual Switch Error: Core class or approve_manual_switch method missing.");
                    } else {
                         $switch_result = $orunk_core->approve_manual_switch($purchase_id);
                         if (is_wp_error($switch_result)) {
                              $message_code = $switch_result->get_error_code(); // Use error code from core
                              error_log("Orunk Admin Manual Switch Error: approve_manual_switch failed for purchase {$purchase_id}. Code: {$message_code}, Msg: " . $switch_result->get_error_message());
                         } else {
                              $message_code = 'switch_approved'; // Success code
                         }
                    }
                    // Prevent standard update logic from running for 'approve_switch'
                    $update_data = [];

                } else {
                    // --- Logic for standard status updates (existing code) ---
                    $target_status = ($new_status_action === 'pending') ? 'Pending Payment' : ucfirst($new_status_action); // Ensure consistent casing for DB
                    $update_data['status'] = $target_status;
                    $update_formats[] = '%s';

                    // Calculate expiry if activating
                    if ($new_status_action === 'active' && $current_purchase['status'] !== 'active') {
                        $orunk_db = null;
                        if (class_exists('Custom_Orunk_DB')) {
                            $orunk_db = new Custom_Orunk_DB();
                        } else {
                            $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
                            if (file_exists($db_path)) {
                                require_once $db_path;
                                if(class_exists('Custom_Orunk_DB')) { $orunk_db = new Custom_Orunk_DB(); }
                            }
                        }

                        if(!$orunk_db){
                             error_log("Orunk Admin: DB Class missing during activation attempt for purchase ID $purchase_id.");
                             $message_code = 'status_update_error_db';
                             $update_data = []; // Prevent update
                        } else {
                            $plan = $orunk_db->get_plan_details($current_purchase['plan_id']);

                            if ($plan && isset($plan['duration_days']) && $plan['duration_days'] > 0) {
                                $start_timestamp = current_time('timestamp', 1); // GMT
                                $expiry_timestamp = strtotime('+' . intval($plan['duration_days']) . ' days', $start_timestamp);
                                $update_data['expiry_date'] = gmdate('Y-m-d H:i:s', $expiry_timestamp); // Store GMT
                                $update_formats[] = '%s';
                                // Also set activation date
                                $update_data['activation_date'] = current_time('mysql', 1); // Store GMT
                                $update_formats[] = '%s';

                                if (empty($current_purchase['product_feature_key']) && !empty($plan['product_feature_key'])) {
                                    $update_data['product_feature_key'] = sanitize_key($plan['product_feature_key']);
                                    $update_formats[] = '%s';
                                }
                                $trigger_activation_hook = true;
                                $plan_details_for_hook = $plan; // Store plan details for the hook
                            } else {
                                error_log("Orunk Admin: Could not calculate expiry for manual activation of purchase ID $purchase_id. Plan duration invalid or missing.");
                                $message_code = 'activate_error_no_plan';
                                $update_data = []; // Prevent update if expiry cannot be calculated
                            }
                        }
                    }
                    // Clear pending switch ID if status changes to anything other than active/pending payment
                    if ($target_status !== 'active' && $target_status !== 'Pending Payment' && !empty($current_purchase['pending_switch_plan_id'])) {
                        $update_data['pending_switch_plan_id'] = null;
                        $update_formats[] = '%s'; // Use %s for NULL in WPDB update/insert
                    }
                    // Clear failure details if setting to active or pending
                    if (($target_status === 'active' || $target_status === 'Pending Payment') && (!empty($current_purchase['failure_reason']) || !empty($current_purchase['failure_timestamp']))) {
                         $update_data['failure_timestamp'] = null;
                         $update_data['failure_reason'] = null;
                         $update_formats[] = '%s';
                         $update_formats[] = '%s';
                    }
                    // --- End standard status update logic ---
                }

                // Perform DB Update if needed (only for non-approve_switch actions now)
                if (!empty($update_data)) {
                    // Remove null formats before update if corresponding data is null
                     $update_data_final = []; $update_formats_final = [];
                     $i = 0;
                     foreach($update_data as $key => $value) {
                         if($update_formats[$i] !== null) {
                             $update_data_final[$key] = $value;
                             $update_formats_final[] = $update_formats[$i];
                         } elseif ($value === null) {
                              $update_data_final[$key] = null; // Explicitly set null
                              $update_formats_final[] = '%s'; // Use %s format for null with WPDB
                         }
                         $i++;
                     }

                    $updated = $wpdb->update($purchases_table, $update_data_final, array('id' => $purchase_id), $update_formats_final, array('%d'));

                    if ($updated === false) {
                        $message_code = 'status_update_error_db';
                        error_log("Orunk Users Admin: DB Error updating purchase $purchase_id: " . $wpdb->last_error);
                    } elseif ($updated > 0 && $trigger_activation_hook && $plan_details_for_hook !== null) {
                        // Trigger activation hook only if status was changed to active successfully
                        $updated_purchase_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $purchases_table WHERE id = %d", $purchase_id), ARRAY_A);
                        if($updated_purchase_data) {
                             $hook_details = array_merge($plan_details_for_hook, $updated_purchase_data);
                             do_action('orunk_purchase_activated', $purchase_id, $current_purchase['user_id'], $hook_details);
                             error_log("Orunk Admin: Triggered orunk_purchase_activated hook for purchase ID {$purchase_id}.");
                        } else {
                             error_log("Orunk Admin Warning: Could not re-fetch purchase {$purchase_id} after manual activation for hook.");
                        }
                    } elseif ($updated === 0 && $message_code === 'status_updated') {
                         // Status didn't actually change, maybe show a different message?
                         error_log("Orunk Admin: No DB change detected for purchase {$purchase_id} (status: {$new_status_action}).");
                         // Keep 'status_updated' or change message? Let's keep it for simplicity.
                    }
                } elseif ($message_code === 'status_updated' && $new_status_action !== 'approve_switch') {
                      // If no data needed updating but no error occurred (e.g., trying to set same status)
                      error_log("Orunk Admin: No update data generated for purchase {$purchase_id}, action: {$new_status_action}. Message code: {$message_code}");
                }

            } else {
                $message_code = 'purchase_not_found';
            }
        }
        // Redirect back to the user list page with a message
        wp_safe_redirect(add_query_arg(['page' => 'orunk-users-manager', 'orunk_message' => $message_code], $admin_page_url));
        exit;
    } // End handle_update_purchase_status

} // End Class Custom_Orunk_Admin