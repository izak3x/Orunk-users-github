<?php
/**
 * Orunk Purchase Manager Class
 *
 * Handles purchase-related logic, including initiating, activating, recording failures,
 * and approving manual plan switches for Orunk Users plugin.
 *
 * @package OrunkUsers
 * @version 1.0.3 - Fixed API key unique constraint error on switch/renewal activation.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure ORUNK_USERS_PLUGIN_DIR is defined
if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
    // Attempt to define relative to this file if not already set
    define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path(__FILE__) . '../');
    error_log('Warning: ORUNK_USERS_PLUGIN_DIR was not defined in Purchase Manager. Using fallback: ' . ORUNK_USERS_PLUGIN_DIR);
}

// Load Custom_Orunk_DB if not already loaded
if (!class_exists('Custom_Orunk_DB')) {
    $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
    if (file_exists($db_path)) {
        require_once $db_path;
    } else {
        error_log("Orunk Purchase Manager CRITICAL ERROR: Cannot load Custom_Orunk_DB. Path checked: " . $db_path);
    }
}

// Load Orunk_Api_Key_Manager if not already loaded
if (!class_exists('Orunk_Api_Key_Manager')) {
    $api_manager_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-api-key-manager.php';
    if (file_exists($api_manager_path)) {
        require_once $api_manager_path;
    } else {
        error_log("Orunk Purchase Manager CRITICAL ERROR: Cannot load Orunk_Api_Key_Manager. Path checked: " . $api_manager_path);
    }
}


class Custom_Orunk_Purchase_Manager {

    /** @var Custom_Orunk_DB Database handler instance */
    private $db;

    /**
     * Constructor. Initializes the database handler.
     */
    public function __construct() {
        if (class_exists('Custom_Orunk_DB')) {
            $this->db = new Custom_Orunk_DB();
        } else {
            error_log('Orunk Purchase Manager Error: Custom_Orunk_DB class not found during instantiation.');
            $this->db = null;
        }
    }

    /**
     * Initiates a purchase record in the database with 'Pending Payment' status
     * or 'active' status if it's a successful renewal/switch.
     * Generates initial API key if needed for a new 'purchase' type.
     *
     * @param int         $user_id             The ID of the user making the purchase.
     * @param int         $plan_id             The ID of the plan being purchased.
     * @param string      $payment_gateway_id  The ID of the payment gateway used.
     * @param string      $transaction_type    Type of transaction (e.g., 'purchase', 'renewal_success'). Default 'purchase'.
     * @param int|null    $parent_purchase_id  The ID of the parent purchase (for renewals/switches). Default null.
     * @return int|WP_Error Purchase ID on success, WP_Error on failure.
     */
    public function initiate_purchase($user_id, $plan_id, $payment_gateway_id, $transaction_type = 'purchase', $parent_purchase_id = null) {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';

        if (!$this->db) {
            return new WP_Error('db_handler_missing', __('Database handler is not available.', 'orunk-users'));
        }
        if (empty($user_id) || empty($plan_id) || empty($payment_gateway_id)) {
            return new WP_Error('invalid_input', __('Missing required information to initiate purchase.', 'orunk-users'));
        }
        // Include attempt types as valid inputs now
        $allowed_types = ['purchase', 'renewal_attempt', 'switch_attempt', 'renewal_success', 'renewal_failure', 'switch_success', 'switch_failure', 'manual_admin_switch'];
        if (!in_array($transaction_type, $allowed_types)) {
            error_log("Orunk Purchase Manager Warning: Invalid transaction_type '{$transaction_type}' passed to initiate_purchase. Defaulting to 'purchase'.");
            $transaction_type = 'purchase';
        }

        $plan = $this->db->get_plan_details($plan_id);
        if (!$plan || empty($plan['product_feature_key'])) {
             error_log("Orunk Purchase Manager Error (initiate_purchase): Invalid plan ID {$plan_id} or plan missing feature key.");
            return new WP_Error('invalid_plan', __('Invalid plan selected or plan data incomplete.', 'orunk-users'));
        }

        // Prevent duplicate active plans ONLY for initial 'purchase' type
        if ($transaction_type === 'purchase') {
            $active_plan = $this->db->get_user_active_plan_for_feature($user_id, $plan['product_feature_key']);
            if ($active_plan) {
                error_log("Orunk Purchase Manager Info (initiate_purchase): User {$user_id} already has active plan {$active_plan['id']} for feature {$plan['product_feature_key']}. Blocking new 'purchase'.");
                return new WP_Error('already_active', __('You already have an active plan for this feature.', 'orunk-users'));
            }
        }

        $api_key = null;
        // Generate API key if needed ONLY for initial 'purchase' type for API features
        $plan_feature_key = $plan['product_feature_key'] ?? '';
        $plan_needs_api_key = (strpos($plan_feature_key, '_api') !== false); // Simple check if feature key indicates API

        if ($transaction_type === 'purchase' && $plan_needs_api_key) {
            $generation_result = $this->generate_unique_api_key();
            if (is_wp_error($generation_result)) {
                error_log("Orunk Purchase Manager: Failed to generate unique API key during INITIATION for Plan ID $plan_id. Error: " . $generation_result->get_error_message());
                // Consider failing the initiation or proceeding without a key? Proceeding without for now.
                $api_key = null;
            } else {
                 $api_key = $generation_result;
                 error_log("Orunk Purchase Manager (initiate_purchase): Generated initial API key for new purchase. Plan: {$plan_feature_key}");
            }
        } else {
             error_log("Orunk Purchase Manager (initiate_purchase): No initial API key generated. Type: {$transaction_type}, Needs Key: " . ($plan_needs_api_key ? 'Yes':'No'));
        }

        $purchase_date = current_time('mysql', 1); // Use GMT time

        // Determine initial status based on transaction type
        $initial_status = 'Pending Payment'; // Default
        if (in_array($transaction_type, ['renewal_success', 'switch_success', 'manual_admin_switch'])) {
             $initial_status = 'active';
        } elseif (in_array($transaction_type, ['renewal_failure', 'switch_failure'])) {
             $initial_status = 'Failed';
        } // 'renewal_attempt', 'switch_attempt', 'purchase' default to 'Pending Payment'


        $data = array(
            'user_id'                 => absint($user_id),
            'plan_id'                 => absint($plan_id),
            'product_feature_key'     => sanitize_key($plan['product_feature_key']),
            'api_key'                 => $api_key, // Store generated key or NULL
            'status'                  => $initial_status,
            'purchase_date'           => $purchase_date,
            'activation_date'         => ($initial_status === 'active') ? $purchase_date : null, // Set activation if starting active
            'expiry_date'             => null, // Expiry calculated upon activation
            'next_payment_date'       => null, // TODO: Calculate based on expiry for subscriptions?
            'payment_gateway'         => sanitize_key($payment_gateway_id),
            'transaction_id'          => null, // Set upon payment confirmation/webhook
            'parent_purchase_id'      => $parent_purchase_id ? absint($parent_purchase_id) : null,
            'plan_details_snapshot'   => wp_json_encode($plan), // Store plan details at time of purchase
            'plan_requests_per_day'   => isset($plan['requests_per_day']) && is_numeric($plan['requests_per_day']) ? absint($plan['requests_per_day']) : null, // Store limits from plan
            'plan_requests_per_month' => isset($plan['requests_per_month']) && is_numeric($plan['requests_per_month']) ? absint($plan['requests_per_month']) : null,
            'currency'                => $plan['currency'] ?? strtoupper(get_option('orunk_currency', 'USD')), // Use plan currency or default
            'pending_switch_plan_id'  => ($transaction_type === 'switch_attempt') ? $plan_id : null, // Store target plan if it's a switch attempt
            'auto_renew'              => ($initial_status === 'active' && $plan['is_one_time'] == 0) ? 1 : 0, // Default auto-renew ON for new active subscriptions
            'transaction_type'        => $transaction_type,
            'cancellation_effective_date' => null,
            'gateway_subscription_id' => null,
            'gateway_customer_id'     => null,
            'gateway_payment_method_id' => null,
            'amount_paid'             => ($initial_status === 'active') ? ($plan['price'] ?? 0.00) : null, // Record amount if starting active
            'failure_timestamp'       => ($initial_status === 'Failed') ? $purchase_date : null, // Set failure time if starting failed
            'failure_reason'          => ($initial_status === 'Failed') ? ucfirst(str_replace('_', ' ', $transaction_type)) : null, // Set reason if starting failed
            'ip_address'              => $this->get_client_ip_for_record(),
            'user_agent'              => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255) : null,
            // Initialize other fields as NULL or default
            'gateway_metadata'        => null, 'billing_period'          => null, 'trial_start_date'        => null,
            'trial_end_date'          => null, 'discount_code_used'      => null, 'discount_amount'         => null,
            'tax_details'             => null, 'refund_details'          => null, 'chargeback_details'      => null,
            'cancellation_reason'     => null, 'admin_notes'             => null, 'dunning_status'          => null,
            'payment_attempts'        => 0,    'affiliate_id'            => null, 'license_key'             => null,
            'download_ids'            => null,
        );

        $initial_formats = $this->get_db_formats($data);

        if (!is_array($initial_formats) || count($initial_formats) !== count($data)) {
            error_log("Orunk Purchase Manager FATAL ERROR (initiate_purchase): Format mismatch for DB insert. Data keys: " . implode(',', array_keys($data)) . " | Format count: " . count($initial_formats));
            return new WP_Error('db_format_mismatch_initiate', __('Internal error preparing purchase record.', 'orunk-users'));
        }

        $inserted = $wpdb->insert($purchases_table, $data, $initial_formats);

        if ($inserted === false) {
            error_log("Orunk Purchase Manager DB Error inserting purchase: " . $wpdb->last_error);
            return new WP_Error('db_error_insert', __('Failed to record your purchase attempt. Please try again.', 'orunk-users'));
        }

        $purchase_id = $wpdb->insert_id;
        do_action('orunk_purchase_initiated', $purchase_id, $user_id, $plan_id, $payment_gateway_id, $transaction_type);
        error_log("Orunk Purchase Manager: Initiated purchase record ID {$purchase_id} with Status: {$initial_status}, Type: {$transaction_type}");

        // Auto-activate if needed (e.g., renewal_success, switch_success)
        if ($initial_status === 'active') {
            error_log("Orunk Purchase Manager: Auto-activating initiated purchase ID {$purchase_id} (Type: {$transaction_type})");
            $expiry_date_gmt_for_active = null;
            if ($plan['is_one_time'] != 1 && isset($plan['duration_days']) && $plan['duration_days'] > 0) { // Calculate expiry only for non-one-time plans
                 $start_timestamp = current_time('timestamp', 1);
                 $expiry_timestamp = strtotime('+' . intval($plan['duration_days']) . ' days', $start_timestamp);
                 $expiry_date_gmt_for_active = gmdate('Y-m-d H:i:s', $expiry_timestamp);
            } else {
                // If one-time or duration is not set/valid, expiry is NULL (never expires)
                $expiry_date_gmt_for_active = null;
            }

            // Update only the expiry date for the auto-activated record
            $wpdb->update(
                $purchases_table,
                ['expiry_date' => $expiry_date_gmt_for_active],
                ['id' => $purchase_id],
                ['%s'], // Format for expiry_date (string or NULL)
                ['%d']
            );
            error_log("Orunk Purchase Manager: Set expiry date for auto-activated purchase ID {$purchase_id} to " . ($expiry_date_gmt_for_active ?? 'NULL') . ".");

            // Trigger activation hook after setting expiry
            $activated_purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM $purchases_table WHERE id = %d", $purchase_id), ARRAY_A);
            if ($activated_purchase) {
                // Merge plan details with potentially updated purchase record for the hook
                 $hook_details = array_merge($plan, $activated_purchase);
                 do_action('orunk_purchase_activated', $purchase_id, $user_id, $hook_details);
                 error_log("Orunk Purchase Manager: Action hook 'orunk_purchase_activated' triggered for auto-activated Purchase ID {$purchase_id}.");
            } else {
                error_log("Orunk Purchase Manager Warning: Could not re-fetch purchase {$purchase_id} after auto-activation for hook.");
            }
        }

        return $purchase_id;
    } // End initiate_purchase


    /**
     * Activates a purchase after successful payment confirmation.
     * Calculates expiry, updates status, stores transaction details, and handles API key logic.
     *
     * @param int         $purchase_id             The ID of the purchase record to activate.
     * @param string|null $transaction_id          Transaction ID from the payment gateway.
     * @param float|null  $amount_paid             Amount paid, if known.
     * @param string|null $gateway_sub_id          Gateway subscription ID (for recurring).
     * @param string|null $gateway_cust_id         Gateway customer ID.
     * @param string|null $gateway_payment_method_id Gateway payment method ID.
     * @param string|null $currency                Currency code (e.g., 'usd').
     * @param bool        $force_activation        Bypass status check. Default false.
     * @return int|WP_Error Returns 1 on successful update, 0 if no update needed (already active), WP_Error on failure.
     */
    public function activate_purchase($purchase_id, $transaction_id = null, $amount_paid = null, $gateway_sub_id = null, $gateway_cust_id = null, $gateway_payment_method_id = null, $currency = null, $force_activation = false) {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';

        if (!$this->db) { return new WP_Error('db_handler_missing', __('Database handler missing.', 'orunk-users')); }
        $purchase_id = absint($purchase_id);
        if ($purchase_id <= 0) { return new WP_Error('invalid_id', __('Invalid purchase ID.', 'orunk-users')); }

        $purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM $purchases_table WHERE id = %d", $purchase_id), ARRAY_A);
        if (!$purchase) { return new WP_Error('not_found', __('Purchase record not found.', 'orunk-users')); }

        // --- Status Check ---
        if (!$force_activation && !in_array($purchase['status'], ['Pending Payment', 'pending'])) {
             $is_currently_active = ($purchase['status'] === 'active' && (empty($purchase['expiry_date']) || strtotime($purchase['expiry_date']) > current_time('timestamp', 1)));
             if ($is_currently_active) {
                  error_log("Orunk Purchase Manager Info (activate_purchase): Purchase ID {$purchase_id} is already active. Skipping full activation, potentially updating transaction info.");
                  // Optionally update transaction details even if active, if they were missing
                  $update_if_active = [];
                  if ($transaction_id !== null && ($purchase['transaction_id'] === null || $purchase['transaction_id'] !== $transaction_id)) $update_if_active['transaction_id'] = sanitize_text_field($transaction_id);
                  if ($gateway_sub_id !== null && $purchase['gateway_subscription_id'] === null) $update_if_active['gateway_subscription_id'] = sanitize_text_field($gateway_sub_id);
                  if ($gateway_cust_id !== null && $purchase['gateway_customer_id'] === null) $update_if_active['gateway_customer_id'] = sanitize_text_field($gateway_cust_id);
                  if ($gateway_payment_method_id !== null && $purchase['gateway_payment_method_id'] === null) $update_if_active['gateway_payment_method_id'] = sanitize_text_field($gateway_payment_method_id);
                  if ($amount_paid !== null && is_numeric($amount_paid) && $purchase['amount_paid'] === null) $update_if_active['amount_paid'] = floatval($amount_paid);
                  if ($currency !== null && $purchase['currency'] === null) $update_if_active['currency'] = strtoupper(substr(sanitize_key($currency), 0, 3));

                  if (!empty($update_if_active)) {
                      $update_formats = $this->get_db_formats($update_if_active);
                      $wpdb->update($purchases_table, $update_if_active, ['id' => $purchase_id], $update_formats, ['%d']);
                      error_log("Orunk Purchase Manager Info (activate_purchase): Updated transaction info for already active purchase ID {$purchase_id}.");
                  }
                  return 0; // Indicate already active (or no status change needed)
             } else {
                  // It's not pending and not currently active (e.g., expired, cancelled) - cannot reactivate this way
                  error_log("Orunk Purchase Manager Error (activate_purchase): Cannot activate purchase ID {$purchase_id}. Current status '{$purchase['status']}' is not 'Pending Payment' or 'active'.");
                  return new WP_Error('not_pending_payment', __('Purchase cannot be activated from its current state.', 'orunk-users'), array('current_status' => $purchase['status']));
             }
        }

        // Get plan details (needed for expiry and feature key)
        $plan = $this->db->get_plan_details($purchase['plan_id']);
        if (!$plan || empty($plan['product_feature_key'])) {
             error_log("Orunk Purchase Manager Error (activate_purchase): Invalid or missing plan (ID: {$purchase['plan_id']}) details for purchase ID {$purchase_id}.");
             // Record failure if plan is missing during activation attempt
             $this->record_purchase_failure($purchase_id, 'Activation failed: Plan details missing or invalid.', $transaction_id);
             return new WP_Error('plan_not_found', __('Could not find plan details to activate purchase.', 'orunk-users'));
        }

        // --- Calculate Expiry Date ---
        $start_timestamp = current_time('timestamp', 1); // GMT timestamp now
        $expiry_date_gmt = null;
        $is_one_time_payment = isset($plan['is_one_time']) && $plan['is_one_time'] == 1;

        if (!$is_one_time_payment && isset($plan['duration_days']) && $plan['duration_days'] > 0) {
            // Calculate expiry for subscription plans with valid duration
            $expiry_timestamp = strtotime('+' . intval($plan['duration_days']) . ' days', $start_timestamp);
            $expiry_date_gmt = gmdate('Y-m-d H:i:s', $expiry_timestamp); // Store expiry in GMT
        } else {
            // One-time plans or invalid duration means no expiry (NULL)
            $expiry_date_gmt = null;
        }
        error_log("Orunk Purchase Manager (activate_purchase): Calculated Expiry Date (GMT) for purchase {$purchase_id}: " . ($expiry_date_gmt ?? 'NULL'));

        // --- Prepare INITIAL Update Data (Status, Dates, Transaction Info - WITHOUT API KEY) ---
        $update_data = array(
            'status'                      => 'active',
            'activation_date'             => current_time('mysql', 1), // Store activation date in GMT
            'expiry_date'                 => $expiry_date_gmt,         // Store calculated expiry date (GMT or NULL)
            'failure_timestamp'           => null, // Clear any previous failure info
            'failure_reason'              => null,
            'auto_renew'                  => ($is_one_time_payment ? 0 : 1) // Enable auto-renew only for subscriptions
        );
        // Conditionally add other fields if provided and not already set (or different)
        if ($transaction_id !== null && ($purchase['transaction_id'] === null || $purchase['transaction_id'] !== $transaction_id)) $update_data['transaction_id'] = sanitize_text_field($transaction_id);
        if ($amount_paid !== null && is_numeric($amount_paid) && $purchase['amount_paid'] === null) $update_data['amount_paid'] = floatval($amount_paid);
        if ($gateway_sub_id !== null && $purchase['gateway_subscription_id'] === null) $update_data['gateway_subscription_id'] = sanitize_text_field($gateway_sub_id);
        if ($gateway_cust_id !== null && $purchase['gateway_customer_id'] === null) $update_data['gateway_customer_id'] = sanitize_text_field($gateway_cust_id);
        if ($gateway_payment_method_id !== null && $purchase['gateway_payment_method_id'] === null) $update_data['gateway_payment_method_id'] = sanitize_text_field($gateway_payment_method_id);
        if ($currency !== null && $purchase['currency'] === null) $update_data['currency'] = strtoupper(substr(sanitize_key($currency), 0, 3));
        // Ensure essential plan details are present if missing (perhaps set during initiation is better?)
        if (empty($purchase['product_feature_key'])) $update_data['product_feature_key'] = sanitize_key($plan['product_feature_key']);
        if ($purchase['plan_requests_per_day'] === null && isset($plan['requests_per_day']) && is_numeric($plan['requests_per_day'])) $update_data['plan_requests_per_day'] = absint($plan['requests_per_day']);
        if ($purchase['plan_requests_per_month'] === null && isset($plan['requests_per_month']) && is_numeric($plan['requests_per_month'])) $update_data['plan_requests_per_month'] = absint($plan['requests_per_month']);


        // --- Perform the INITIAL Update (Status, Dates, Transaction Info) ---
        $update_formats = $this->get_db_formats($update_data);
        if (!is_array($update_formats) || count($update_formats) !== count($update_data)) {
            error_log("Orunk Purchase Manager FATAL ERROR (activate_purchase - initial): Format mismatch for Purchase ID {$purchase_id}. Cannot update.");
            return new WP_Error('db_format_mismatch_update_initial', __('Internal error preparing purchase activation [Formats].', 'orunk-users'));
        }

        error_log("Orunk Purchase Manager Activate: Attempting INITIAL DB update (Status, Dates, etc.) for Purchase ID {$purchase_id}. Data: " . print_r($update_data, true));
        $updated = $wpdb->update(
            $purchases_table,
            $update_data,
            array('id' => $purchase_id),
            $update_formats,
            array('%d')
        );

        if ($updated === false) {
            error_log("Orunk Purchase Manager DB Error during initial activation update for purchase {$purchase_id}: " . $wpdb->last_error);
            // Attempt to record failure since activation failed
            $this->record_purchase_failure($purchase_id, 'DB error during initial activation update.', $transaction_id);
            return new WP_Error('db_error_update_initial', __('Failed to update purchase status during activation.', 'orunk-users'));
        }
        error_log("Orunk Purchase Manager Activate: Initial update successful for Purchase ID {$purchase_id}. Rows affected: {$updated}. Now handling API key.");

        // --- API Key Transfer / Generation Logic (AFTER successful initial update) ---
        $parent_purchase_id = $purchase['parent_purchase_id'] ?? null;
        $plan_feature_key = $plan['product_feature_key'] ?? '';
        $plan_needs_api_key = (strpos($plan_feature_key, '_api') !== false);
        $api_key_to_apply = null; // The key that should end up on the new record
        $key_action_result = null; // Result of specific key action

        if ($plan_needs_api_key) {
            if (!empty($parent_purchase_id) && is_numeric($parent_purchase_id) && $parent_purchase_id > 0) {
                // --- Inherit Key Logic (Renewal/Switch) ---
                error_log("Orunk Purchase Manager Activate (API Key): Handling inheritance for Purchase ID {$purchase_id} from Parent {$parent_purchase_id}.");
                $parent_api_key = $wpdb->get_var($wpdb->prepare("SELECT api_key FROM $purchases_table WHERE id = %d", $parent_purchase_id));

                if (!empty($parent_api_key) && is_string($parent_api_key) && strlen($parent_api_key) > 30) {
                    $api_key_to_apply = $parent_api_key;
                    // Step 1: Nullify key on the OLD record and set status (e.g., 'expired', 'switched')
                    // Determine appropriate old status based on transaction type of current purchase
                    $old_status = 'expired'; // Default for renewals
                    if ($purchase['transaction_type'] === 'switch_success' || $purchase['transaction_type'] === 'manual_admin_switch') {
                         $old_status = 'switched';
                    }
                    $updated_old = $wpdb->update(
                        $purchases_table,
                        ['api_key' => null, 'status' => $old_status], // NULL out API key, set appropriate status
                        ['id' => $parent_purchase_id],
                        ['%s', '%s'], // Format for NULL and status
                        ['%d']
                    );
                    if ($updated_old === false) {
                        error_log("Orunk Purchase Manager Activate (API Key) Error: Failed to nullify API key and set status on OLD purchase ID {$parent_purchase_id}. DB Error: " . $wpdb->last_error);
                        $key_action_result = new WP_Error('parent_update_failed', 'Failed to update parent record during key transfer.');
                    } else {
                        error_log("Orunk Purchase Manager Activate (API Key): Successfully NULLed key and set status='{$old_status}' on OLD purchase ID {$parent_purchase_id}. Rows: {$updated_old}. Applying key to NEW purchase ID {$purchase_id}.");
                        // Step 2: Apply key to the NEW record
                        $key_action_result = $wpdb->update( $purchases_table, ['api_key' => $api_key_to_apply], ['id' => $purchase_id], ['%s'], ['%d'] );
                         if ($key_action_result === false) { error_log("Orunk Purchase Manager Activate (API Key) Error: Failed to apply inherited API key to NEW purchase ID {$purchase_id}. DB Error: " . $wpdb->last_error); $key_action_result = new WP_Error('new_key_update_failed', 'Failed to apply inherited key to new record.'); }
                         else { error_log("Orunk Purchase Manager Activate (API Key): Successfully applied inherited key to NEW purchase ID {$purchase_id}. Rows: {$key_action_result}."); }
                    }
                } else {
                     error_log("Orunk Purchase Manager Activate (API Key) Warning: Parent {$parent_purchase_id} had no valid API key to inherit for new purchase {$purchase_id}. Generating NEW key.");
                     $generation_result = $this->generate_unique_api_key($purchase_id); // Pass current ID to exclude
                     if (is_wp_error($generation_result)) { $key_action_result = $generation_result; }
                     else {
                          $api_key_to_apply = $generation_result;
                          $key_action_result = $wpdb->update( $purchases_table, ['api_key' => $api_key_to_apply], ['id' => $purchase_id], ['%s'], ['%d'] );
                          if ($key_action_result === false) { error_log("Orunk Purchase Manager Activate (API Key) Error: Failed to apply generated API key to NEW purchase ID {$purchase_id}. DB Error: " . $wpdb->last_error); $key_action_result = new WP_Error('db_error_api_key', '...'); }
                          else { error_log("Orunk Purchase Manager Activate (API Key): Applied newly generated key to Purchase ID {$purchase_id}."); }
                     }
                }
            } else {
                 // --- Generate/Confirm New Key Logic (Initial Purchase) ---
                 $existing_api_key = $purchase['api_key'] ?? null;
                 if (empty($existing_api_key) || !is_string($existing_api_key) || strlen($existing_api_key) < 30) {
                      error_log("Orunk Purchase Manager Activate (API Key): Generating NEW key for initial purchase ID {$purchase_id}.");
                      $generation_result = $this->generate_unique_api_key($purchase_id); // Exclude self
                      if (is_wp_error($generation_result)) { $key_action_result = $generation_result; }
                      else {
                           $api_key_to_apply = $generation_result;
                           $key_action_result = $wpdb->update( $purchases_table, ['api_key' => $api_key_to_apply], ['id' => $purchase_id], ['%s'], ['%d'] );
                           if ($key_action_result === false) { error_log("Orunk Purchase Manager Activate (API Key) Error: Failed to apply generated API key to initial purchase ID {$purchase_id}. DB Error: " . $wpdb->last_error); $key_action_result = new WP_Error('db_error_api_key', '...'); }
                           else { error_log("Orunk Purchase Manager Activate (API Key): Applied newly generated key to Purchase ID {$purchase_id}."); }
                      }
                 } else {
                      error_log("Orunk Purchase Manager Activate (API Key): Valid key already exists on purchase ID {$purchase_id}. No update needed.");
                      $api_key_to_apply = $existing_api_key; // Set this for the hook
                      $key_action_result = 1; // Indicate success (or no action needed)
                 }
            }
        } else {
            // --- No Key Needed Logic ---
             error_log("Orunk Purchase Manager Activate (API Key): Plan {$plan_feature_key} does not require an API key for purchase ID {$purchase_id}.");
             if ($purchase['api_key'] !== null) { // Ensure key is NULL if not needed
                  $key_action_result = $wpdb->update($purchases_table, ['api_key' => null], ['id' => $purchase_id], ['%s'], ['%d']);
                  if ($key_action_result === false) error_log("Orunk Purchase Manager Activate (API Key) Error: Failed to NULL out API key for Purchase ID {$purchase_id}. DB Error: " . $wpdb->last_error);
                  else error_log("Orunk Purchase Manager Activate (API Key): Set API key to NULL for Purchase ID {$purchase_id}.");
             } else { $key_action_result = 1; } // Success, no action needed
             $api_key_to_apply = null;
        }

        // Check if API key operation failed
         if (is_wp_error($key_action_result)) {
             error_log("Orunk Purchase Manager Activate Error during API key handling for purchase {$purchase_id}: " . $key_action_result->get_error_message());
             $this->record_purchase_failure($purchase_id, 'API key handling failed during activation: ' . $key_action_result->get_error_message(), $transaction_id);
             return $key_action_result; // Return the WP_Error
         }
         if ($key_action_result === false) { // Check for direct DB update failure
              error_log("Orunk Purchase Manager Activate Error: Direct DB update failed during API key handling for purchase {$purchase_id}.");
              $this->record_purchase_failure($purchase_id, 'API key update failed during activation.', $transaction_id);
             return new WP_Error('db_error_api_key', __('Failed to update API key during activation.', 'orunk-users'));
         }

        // --- Final Success ---
        // Re-fetch final data for the hook
        $final_purchase_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $purchases_table WHERE id = %d", $purchase_id), ARRAY_A);
        if ($final_purchase_data) {
            $hook_details = array_merge($plan, $final_purchase_data); // Merge current plan details with final purchase record
            do_action('orunk_purchase_activated', $purchase_id, $purchase['user_id'], $hook_details);
            error_log("Orunk Purchase Manager Activate: Action hook 'orunk_purchase_activated' triggered for Purchase ID {$purchase_id}.");
        } else {
             error_log("Orunk Purchase Manager Activate Warning: Could not re-fetch purchase data for hook after activation for ID {$purchase_id}.");
        }

        // Return number of rows affected by the initial status/date update
        // Note: $updated could be 0 if only transaction info was updated on an already active record.
        // Returning 1 signifies the status was likely changed from pending to active.
        return ($updated > 0) ? 1 : 0;
    } // End activate_purchase


    /**
     * Records a purchase failure in the database.
     * Sets status to 'Failed', records timestamp and reason.
     *
     * @param int         $purchase_id     The ID of the purchase record.
     * @param string      $reason          Reason for the failure. Default 'Unknown reason'.
     * @param string|null $transaction_id  Transaction ID, if available and different from current.
     * @return bool|WP_Error True on success, WP_Error on failure or if record not found/invalid state.
     */
    public function record_purchase_failure($purchase_id, $reason = 'Unknown reason', $transaction_id = null) {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $purchase_id = absint($purchase_id);

        if (!$this->db) { return new WP_Error('db_handler_missing', __('Database handler missing.', 'orunk-users')); }
        if ($purchase_id <= 0) { return new WP_Error('invalid_id', __('Invalid Purchase ID.', 'orunk-users')); }

        // Get minimal necessary data
        $purchase = $wpdb->get_row($wpdb->prepare("SELECT id, status, transaction_id, user_id FROM $purchases_table WHERE id = %d", $purchase_id), ARRAY_A);
        if (!$purchase) { return new WP_Error('not_found', __('Purchase record not found to record failure.', 'orunk-users')); }

        // Only proceed if the status isn't already 'Failed' (or maybe 'active'?)
        // Allowing update even if active might be needed if a renewal fails after initial activation
        if ($purchase['status'] === 'Failed') {
            error_log("Orunk Purchase Manager Record Failure Info: Purchase ID {$purchase_id} is already marked as failed.");
            return true; // Or return 0 to indicate no change? Return true for idempotency.
        }

        $update_data = [
             'status'            => 'Failed',
             'failure_timestamp' => current_time('mysql', 1), // GMT
             'failure_reason'    => sanitize_textarea_field(substr($reason, 0, 1000)),
             'auto_renew'        => 0 // Ensure auto-renew is off on failure
        ];
        // Update transaction ID only if provided and different from existing one
        if ($transaction_id !== null && ($purchase['transaction_id'] === null || $purchase['transaction_id'] !== $transaction_id)) {
            $update_data['transaction_id'] = sanitize_text_field($transaction_id);
        }

        $update_formats = $this->get_db_formats($update_data);
        if (!is_array($update_formats) || count($update_formats) !== count($update_data)) {
             error_log("Orunk Purchase Manager FATAL ERROR (record_purchase_failure): Format mismatch for Purchase ID {$purchase_id}.");
            return new WP_Error('db_format_mismatch_failure', __('Internal error preparing purchase failure record.', 'orunk-users'));
        }

        error_log("Orunk Purchase Manager Record Failure: Attempting DB update for Purchase ID {$purchase_id}. Reason: {$reason}");
        $updated = $wpdb->update($purchases_table, $update_data, ['id' => $purchase_id], $update_formats, ['%d']);

        if ($updated === false) {
            error_log("Orunk Purchase Manager DB Error recording failure for purchase {$purchase_id}: " . $wpdb->last_error);
            return new WP_Error('db_error_failure', __('Failed to record purchase failure in the database.', 'orunk-users'));
        }

        error_log("Orunk Purchase Manager Record Failure: Successfully marked purchase ID {$purchase_id} as failed. Rows affected: {$updated}");
        // Trigger failure hook
        do_action('orunk_purchase_failed', $purchase_id, $purchase['user_id'] ?: 0, $reason);
        return true;
    } // End record_purchase_failure

    /**
     * Approves a manually processed plan switch (e.g., for Bank Transfer).
     * Initiates a new 'active' purchase record for the target plan,
     * marks the original as 'switched', and transfers the API key.
     *
     * @param int $original_purchase_id The ID of the original purchase which has a pending_switch_plan_id.
     * @return int|WP_Error New purchase ID on success, WP_Error on failure.
     */
    public function approve_manual_switch($original_purchase_id) {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $original_purchase_id = absint($original_purchase_id);

        if (!$this->db) { return new WP_Error('core_missing', __('Core components missing.', 'orunk-users')); }

        // Get the original purchase, ensuring it's active and has a pending switch ID
        $original_purchase = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$purchases_table` WHERE id = %d AND status = 'active' AND pending_switch_plan_id IS NOT NULL AND pending_switch_plan_id > 0",
            $original_purchase_id
        ), ARRAY_A);

        if (!$original_purchase) {
             error_log("Orunk Purchase Manager Manual Switch Error: Original purchase ID {$original_purchase_id} not found, not active, or no pending switch set.");
             return new WP_Error('original_not_found_or_no_switch', __('Original purchase not found, not active, or no switch pending.', 'orunk-users'));
        }

        $new_plan_id = absint($original_purchase['pending_switch_plan_id']);
        if (empty($new_plan_id)) { // Should be caught by query above, but double-check
            return new WP_Error('no_pending_switch', __('No pending switch plan ID found.', 'orunk-users'));
        }

        // Get details of the NEW plan
        $new_plan = $this->db->get_plan_details($new_plan_id);
        if (!$new_plan) {
             error_log("Orunk Purchase Manager Manual Switch Error: New plan ID {$new_plan_id} details not found.");
             return new WP_Error('new_plan_not_found', __('Details for the new plan could not be found.', 'orunk-users'));
        }

        // --- Step 1: Initiate new purchase record (should start as 'active') ---
        $init_result = $this->initiate_purchase(
            $original_purchase['user_id'],
            $new_plan_id,
            'manual_admin_switch', // Gateway identifier
            'switch_success',      // Transaction type indicating successful switch
            $original_purchase_id  // Link to the original purchase
        );

        if (is_wp_error($init_result)) {
             error_log("Orunk Purchase Manager Manual Switch Error: Failed to initiate new purchase record. Error: " . $init_result->get_error_message());
             return $init_result; // Return the WP_Error from initiate_purchase
        }
        $new_purchase_id = $init_result;
        error_log("Orunk Purchase Manager Manual Switch: Initiated NEW (active) purchase record ID {$new_purchase_id} for switch.");

        // Step 2: Verify activation and API key handling (handled by activate_purchase called within initiate_purchase/later hook)
        // The 'initiate_purchase' now handles auto-activation and calls 'activate_purchase' internally if needed,
        // which in turn handles API key transfer logic. We just need to ensure the status is indeed active.
        $new_purchase_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $purchases_table WHERE id = %d", $new_purchase_id));
        if ($new_purchase_status !== 'active') {
             error_log("Orunk Purchase Manager Manual Switch FATAL Error: New purchase ID {$new_purchase_id} did not become active. Status: '{$new_purchase_status}'. Manual activation might be needed via webhook/admin.");
             // Don't mark old one as switched if new one isn't active
             return new WP_Error('activation_failed_switch', __('Failed to activate the new plan record during switch.', 'orunk-users'));
        }

        // Step 3: Update the original purchase (Status = switched, clear pending ID, clear API key)
        // API key transfer is handled by activate_purchase called for the new record,
        // which should nullify the key on the parent automatically. We just set status here.
        $updated_old = $wpdb->update(
            $purchases_table,
            ['status' => 'switched', 'pending_switch_plan_id' => null], // Clear pending ID, status is switched
            ['id' => $original_purchase_id],
            ['%s', null], // Format for status, pending_id (null)
            ['%d']
        );
        if ($updated_old === false) { error_log("Orunk Purchase Manager Manual Switch Warning: Failed to update OLD purchase {$original_purchase_id} status to 'switched'. DB Error: " . $wpdb->last_error); }
        else { error_log("Orunk Purchase Manager Manual Switch: Successfully marked old purchase {$original_purchase_id} as 'switched'. API key transfer handled by activation of new record."); }

        // Step 4: Trigger Action Hook
        do_action('orunk_plan_switched', $original_purchase_id, $new_purchase_id, $original_purchase['user_id'], $original_purchase['plan_id'], $new_plan_id);
        error_log("Orunk Purchase Manager Manual Switch Success: Approved switch from purchase ID {$original_purchase_id} to new purchase ID {$new_purchase_id}.");

        return $new_purchase_id; // Return the ID of the new, active purchase record
    } // End approve_manual_switch


    /**
     * Helper function to generate unique API key using the Api Key Manager.
     *
     * @param int $exclude_purchase_id Purchase ID to exclude from the uniqueness check.
     * @param int $max_attempts Max attempts to find a unique key.
     * @return string|WP_Error Unique API key string on success, WP_Error on failure.
     */
    private function generate_unique_api_key($exclude_purchase_id = 0, $max_attempts = 10) {
        if (!class_exists('Orunk_Api_Key_Manager')) {
             error_log('Orunk Purchase Manager Error (generate_unique_api_key): Orunk_Api_Key_Manager class missing.');
             return new WP_Error('dependency_missing', __('API Key generator component is missing.', 'orunk-users'));
        }
        $api_key_manager = new Orunk_Api_Key_Manager();
        return $api_key_manager->generate_unique_api_key($exclude_purchase_id, $max_attempts);
    } // End generate_unique_api_key (helper)


    /**
     * Helper function to get database formats for wpdb::insert/update.
     *
     * @param array $data Associative array of data ($column => $value).
     * @return array|WP_Error Array of format strings (%s, %d, %f) or WP_Error on failure.
     */
    private function get_db_formats($data) {
         // Map known columns to their expected formats
         $known_formats = [
             'id' => '%d', 'user_id' => '%d', 'plan_id' => '%d', 'product_feature_key' => '%s',
             'api_key' => '%s', 'status' => '%s', 'purchase_date' => '%s', 'activation_date' => '%s',
             'expiry_date' => '%s', 'next_payment_date' => '%s', 'payment_gateway' => '%s', 'transaction_id' => '%s',
             'parent_purchase_id' => '%d', 'plan_details_snapshot' => '%s', 'plan_requests_per_day' => '%d', // Treat as int even if null in DB
             'plan_requests_per_month' => '%d', // Treat as int even if null in DB
             'currency' => '%s', 'pending_switch_plan_id' => '%d', // Treat as int even if null in DB
             'auto_renew' => '%d', 'transaction_type' => '%s', 'cancellation_effective_date' => '%s',
             'gateway_subscription_id' => '%s', 'gateway_customer_id' => '%s', 'gateway_payment_method_id' => '%s',
             'amount_paid' => '%f', // Treat as float even if null in DB
             'failure_timestamp' => '%s', 'failure_reason' => '%s', 'gateway_metadata' => '%s',
             'billing_period' => '%s', 'trial_start_date' => '%s', 'trial_end_date' => '%s',
             'discount_code_used' => '%s', 'discount_amount' => '%f', // Treat as float even if null in DB
             'tax_details' => '%s', 'refund_details' => '%s', 'chargeback_details' => '%s',
             'cancellation_reason' => '%s', 'ip_address' => '%s', 'user_agent' => '%s',
             'admin_notes' => '%s', 'dunning_status' => '%s', 'payment_attempts' => '%d', 'affiliate_id' => '%s',
             'license_key' => '%s', 'download_ids' => '%s', 'modified_at' => '%s',
         ];
         $formats = [];
         foreach (array_keys($data) as $key) {
             $value = $data[$key];
             // Use known format if defined
             if (isset($known_formats[$key])) {
                 // Special handling for NULL values - use %s for wpdb update/insert
                 if ($value === null) {
                     $formats[] = '%s';
                 } else {
                     $formats[] = $known_formats[$key];
                 }
             }
             // Fallback type detection (less reliable than known formats)
             elseif ($value === null) { $formats[] = '%s'; } // Use %s for null
             elseif (is_float($value)) { $formats[] = '%f'; }
             elseif (is_int($value)) { $formats[] = '%d'; }
             elseif (is_bool($value)) { $formats[] = '%d'; } // Treat bool as int (0 or 1)
             else { $formats[] = '%s'; } // Default to string
         }
         return $formats;
     } // End get_db_formats


    /**
     * Helper function to get client IP for logging/records.
     * (Function unchanged)
     * @return string|null Client IP address or null.
     */
    private function get_client_ip_for_record() {
         $ip_keys = [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ];
         foreach ($ip_keys as $key) { if (isset($_SERVER[$key])) { $ip_list = explode(',', $_SERVER[$key]); $ip = trim(reset($ip_list)); if (filter_var($ip, FILTER_VALIDATE_IP)) { return sanitize_text_field($ip); } } }
         return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : null;
    } // End get_client_ip_for_record

} // End Class Custom_Orunk_Purchase_Manager