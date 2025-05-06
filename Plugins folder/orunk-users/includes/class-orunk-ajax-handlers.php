<?php
/**
 * Orunk Users AJAX Handlers Class
 *
 * Handles AJAX requests related to checkout, payment processing,
 * API key management, auto-renewal, and password reset links.
 * Admin and User Profile handlers have been moved to separate files.
 *
 * MODIFICATION: Updated handle_create_payment_intent to handle Stripe Subscription creation
 * and ensure transaction_id/gateway_subscription_id is saved before sending response.
 *
 * @package OrunkUsers\Includes
 * @version 1.5.1 // Version reflects reliability fix
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// --- Use statements for Stripe SDK ---
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Subscription;
use Stripe\Exception\ApiErrorException;

// --- Ensure ORUNK_USERS_PLUGIN_DIR is defined ---
if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
    define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path(__FILE__) . '../');
    error_log('Warning: ORUNK_USERS_PLUGIN_DIR was not defined in ajax-handlers. Using fallback: ' . ORUNK_USERS_PLUGIN_DIR);
}
// --- End DIR Check ---


// Ensure core classes are loaded (needed for constructor and remaining methods)
if (!class_exists('Custom_Orunk_Core')) {
    $core_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-core.php';
    if (file_exists($core_path)) { require_once $core_path; }
    else { error_log("Orunk AJAX FATAL: Cannot load Custom_Orunk_Core. Path: {$core_path}"); }
}
if (!class_exists('Custom_Orunk_DB')) {
    $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
    if (file_exists($db_path)) { require_once $db_path; }
    else { error_log("Orunk AJAX FATAL: Cannot load Custom_Orunk_DB. Path: {$db_path}"); }
}
// Ensure Purchase Manager is loaded (needed for payment handlers)
if (!class_exists('Custom_Orunk_Purchase_Manager')) {
    $pm_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-purchase-manager.php';
    if (file_exists($pm_path)) { require_once $pm_path; }
    else { error_log("Orunk AJAX FATAL: Cannot load Custom_Orunk_Purchase_Manager. Path: {$pm_path}"); }
}


class Orunk_AJAX_Handlers {

    /** @var Custom_Orunk_Core Core logic handler instance */
    private $core;

    /** @var Custom_Orunk_DB DB handler instance */
    private $db;

    /**
     * Constructor.
     */
    public function __construct() {
        // Instantiate Core and DB handlers as they are used by remaining methods
        if (class_exists('Custom_Orunk_Core')) { $this->core = new Custom_Orunk_Core(); } else { error_log("Orunk AJAX_Handlers: Custom_Orunk_Core missing in constructor."); $this->core = null; }
        if (class_exists('Custom_Orunk_DB')) { $this->db = new Custom_Orunk_DB(); } else { error_log("Orunk AJAX_Handlers: Custom_Orunk_DB missing in constructor."); $this->db = null; }
    }

    /**
     * Helper to check nonce and permissions for logged-in AJAX actions.
     */
    private function check_logged_in_ajax_permissions($nonce_action, $capability = 'read') {
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            return new WP_Error('nonce_fail', __('Security check failed.', 'orunk-users'), ['status' => 403]);
        }
        if (!is_user_logged_in()) {
            return new WP_Error('not_logged_in', __('You must be logged in.', 'orunk-users'), ['status' => 401]);
        }
        if (!current_user_can($capability)) {
            return new WP_Error('permission_denied', __('You do not have permission.', 'orunk-users'), ['status' => 403]);
        }
        return true;
    }

    /**
     * Helper to check nonce for non-privileged AJAX actions.
     */
    private function check_nopriv_ajax_permissions($nonce_action) {
         if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            return new WP_Error('nonce_fail', __('Security check failed.', 'orunk-users'), ['status' => 403]);
        }
        return true;
    }

    /**
     * Helper to send JSON errors from WP_Error objects.
     */
    private function send_json_error_from_wp_error(WP_Error $error) {
        $error_code = $error->get_error_code();
        $error_message = $error->get_error_message();
        $error_data = $error->get_error_data();
        $http_status = is_array($error_data) && isset($error_data['status']) ? $error_data['status'] : 400;
        wp_send_json_error(['message' => $error_message, 'code' => $error_code, 'data' => $error_data], $http_status);
        // wp_die() called by wp_send_json_error
    }

    //==========================================
    // Remaining AJAX Handlers
    //==========================================

    /**
     * AJAX Handler: Create Stripe Payment Intent or Subscription and Initiate Purchase Record.
     * Handles 'wp_ajax_orunk_create_payment_intent'.
     *
     * MODIFIED to handle both one-time payments (PaymentIntent) and
     * subscription initiation (Customer + Subscription). Ensures Stripe IDs are saved.
     */
    public function handle_create_payment_intent() {
        $permission_check = $this->check_logged_in_ajax_permissions('orunk_process_payment_nonce');
        if (is_wp_error($permission_check)) { $this->send_json_error_from_wp_error($permission_check); }

        // --- Dependency Checks ---
        if (!$this->core || !$this->db || !class_exists('Custom_Orunk_Purchase_Manager')) {
            error_log("Orunk AJAX FATAL (create_payment_intent): Core/DB/PurchaseManager missing!");
            wp_send_json_error(['message' => __('Core server components missing.', 'orunk-users')], 500);
        }
        $stripe_sdk_loaded = class_exists('\Stripe\Stripe');
        if (!$stripe_sdk_loaded) {
            $stripe_path = ORUNK_USERS_PLUGIN_DIR . 'vendor/autoload.php';
            if (file_exists($stripe_path)) { require_once $stripe_path; $stripe_sdk_loaded = class_exists('\Stripe\Stripe'); }
            else { wp_send_json_error(['message' => __('Payment library missing.', 'orunk-users')], 500); }
        }
        if (!$stripe_sdk_loaded) { wp_send_json_error(['message' => __('Payment class error.', 'orunk-users')], 500); }

        // --- Get and Validate Input ---
        $user_id = get_current_user_id();
        $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
        $checkout_type = isset($_POST['checkout_type']) ? sanitize_key($_POST['checkout_type']) : 'new_purchase';
        $existing_purchase_id = ($checkout_type === 'switch') ? absint($_POST['existing_purchase_id'] ?? 0) : 0;

        if ($plan_id <= 0) { wp_send_json_error(['message' => __('Invalid plan ID.', 'orunk-users')], 400); }
        if ($checkout_type === 'switch' && $existing_purchase_id <= 0) { wp_send_json_error(['message' => __('Missing existing purchase ID for switch.', 'orunk-users')], 400); }

        $plan = $this->core->get_plan_details($plan_id);
        if (!$plan) { wp_send_json_error(['message' => __('Plan not found.', 'orunk-users')], 404); }

        $plan_price = floatval($plan['price']);
        $amount_in_cents = intval($plan_price * 100);
        $currency = strtolower(get_option('orunk_currency', 'usd'));
        $is_subscription = isset($plan['is_one_time']) && $plan['is_one_time'] == 0;
        $stripe_price_id = $plan['stripe_price_id'] ?? null;

        if ($amount_in_cents <= 0 && $is_subscription && !$stripe_price_id) { wp_send_json_error(['message' => __('Cannot process $0 subscription without a valid Stripe Price ID configured.', 'orunk-users')], 400); }
        elseif ($amount_in_cents <= 0 && !$is_subscription) { wp_send_json_error(['message' => __('Payment intent cannot be created for free one-time plans.', 'orunk-users')], 400); }

        // --- Initiate Orunk Purchase Record ---
        $transaction_type = ($checkout_type === 'switch') ? 'switch_attempt' : 'purchase';
        $parent_id_for_initiate = ($checkout_type === 'switch') ? $existing_purchase_id : null;

        $purchase_manager = new Custom_Orunk_Purchase_Manager();
        $init_result = $purchase_manager->initiate_purchase($user_id, $plan_id, 'stripe', $transaction_type, $parent_id_for_initiate);

        if (is_wp_error($init_result)) { $this->send_json_error_from_wp_error($init_result); }
        $purchase_id = $init_result; // Store the new purchase ID

        // --- Stripe API Interaction ---
        $stripe_settings = get_option('orunk_gateway_stripe_settings', array());
        $secret_key = $stripe_settings['secret_key'] ?? null;
        if (empty($secret_key)) { $purchase_manager->record_purchase_failure($purchase_id, 'Stripe Secret Key missing in config.'); wp_send_json_error(['message' => __('Stripe gateway not configured (missing key).', 'orunk-users')], 500); }

        Stripe::setApiKey($secret_key);

        // Common metadata
        $metadata = [ /* ... same metadata ... */
            'orunk_purchase_id' => $purchase_id, 'orunk_user_id' => $user_id, 'orunk_plan_id' => $plan_id, 'orunk_action_type' => $checkout_type, 'orunk_feature_key' => $plan['product_feature_key'] ?? 'unknown', 'orunk_original_purchase_id' => ($checkout_type === 'switch' && $existing_purchase_id > 0) ? $existing_purchase_id : null,
        ];
        $metadata = array_filter($metadata, fn($v) => $v !== null);

        try {
            $client_secret = null;
            $stripe_object_id = null; // PI ID or Sub ID
            $response_data = [];
            $db_update_data = []; // Data to update in Orunk purchase record
            $db_update_formats = [];
            $pi_id_for_db = null; // Specifically track the PI/SI ID for the transaction_id column

            if ($is_subscription) {
                // --- Subscription Logic ---
                if (empty($stripe_price_id)) { throw new Exception(__('Stripe Price ID is not configured for this subscription plan.', 'orunk-users')); }

                // 1. Get/Create Stripe Customer (logic unchanged)
                $stripe_customer_id = get_user_meta($user_id, '_stripe_customer_id', true); $wp_user = get_userdata($user_id);
                if (!$stripe_customer_id) { $customer = Customer::create([ 'email' => $wp_user->user_email, 'name' => $wp_user->display_name, 'metadata' => ['wordpress_user_id' => $user_id], ]); $stripe_customer_id = $customer->id; update_user_meta($user_id, '_stripe_customer_id', $stripe_customer_id); error_log("Orunk Stripe: Created new Stripe Customer {$stripe_customer_id} for WP User {$user_id}."); }
                else { error_log("Orunk Stripe: Using existing Stripe Customer {$stripe_customer_id} for WP User {$user_id}."); }

                // 2. Create Stripe Subscription (logic unchanged)
                $subscription_params = [ /* ... same params ... */
                    'customer' => $stripe_customer_id, 'items' => [['price' => $stripe_price_id]], 'payment_behavior' => 'default_incomplete', 'payment_settings' => ['save_default_payment_method' => 'on_subscription'], 'expand' => ['latest_invoice.payment_intent', 'pending_setup_intent'], 'metadata' => $metadata,
                ];
                error_log("Orunk Stripe: Creating Subscription for Cust: {$stripe_customer_id}, Price: {$stripe_price_id}, Meta: " . print_r($metadata, true));
                $subscription = Subscription::create($subscription_params);
                $stripe_object_id = $subscription->id; // This is the Subscription ID
                error_log("Orunk Stripe: Subscription {$stripe_object_id} created for Purchase ID {$purchase_id}. Status: {$subscription->status}");

                // 3. Get Client Secret and PI/SI ID (logic unchanged)
                if ($subscription->pending_setup_intent) {
                    $client_secret = $subscription->pending_setup_intent->client_secret;
                    $pi_id_for_db = $subscription->pending_setup_intent->id; // Store Setup Intent ID
                    error_log("Orunk Stripe: Using SetupIntent client_secret for Purchase ID {$purchase_id}. SI ID: {$pi_id_for_db}");
                } elseif (isset($subscription->latest_invoice->payment_intent->client_secret)) {
                    $client_secret = $subscription->latest_invoice->payment_intent->client_secret;
                     $pi_id_for_db = $subscription->latest_invoice->payment_intent->id; // Store Payment Intent ID
                    error_log("Orunk Stripe: Using PaymentIntent client_secret from invoice for Purchase ID {$purchase_id}. PI ID: {$pi_id_for_db}");
                } else {
                     error_log("Orunk Stripe Warning: No client_secret found on Subscription {$stripe_object_id}. Status: {$subscription->status}");
                     if ($subscription->status !== 'active' && $subscription->status !== 'trialing') { throw new Exception('Could not retrieve client secret for payment confirmation.'); }
                     $client_secret = null;
                     $pi_id_for_db = null; // No immediate PI/SI ID
                }

                // Prepare DB update data for subscription
                $db_update_data['gateway_subscription_id'] = $stripe_object_id;
                $db_update_formats[] = '%s';
                $db_update_data['gateway_customer_id'] = $stripe_customer_id;
                $db_update_formats[] = '%s';
                // Store the PI or SI ID as the initial transaction_id
                if ($pi_id_for_db) {
                    $db_update_data['transaction_id'] = $pi_id_for_db;
                    $db_update_formats[] = '%s';
                }

                $response_data = [ /* response data unchanged */
                    'client_secret' => $client_secret, 'subscription_id' => $stripe_object_id, 'purchase_id' => $purchase_id, 'requires_confirmation' => ($client_secret !== null)
                ];

            } else {
                // --- One-Time Payment Logic ---
                $payment_intent_args = [ /* args unchanged */
                    'amount' => $amount_in_cents, 'currency' => $currency, 'metadata' => $metadata, 'description' => sprintf(__('Orunk Plan: %s (Purchase ID: %d)', 'orunk-users'), $plan['plan_name'], $purchase_id), 'automatic_payment_methods' => ['enabled' => true],
                ];
                 $current_user_obj = wp_get_current_user(); if ($current_user_obj && $current_user_obj->user_email) { $payment_intent_args['receipt_email'] = $current_user_obj->user_email; }

                error_log("Orunk Stripe: Creating PaymentIntent for Purchase ID {$purchase_id}. Amount: {$amount_in_cents}, Meta: " . print_r($metadata, true));
                $paymentIntent = PaymentIntent::create($payment_intent_args);
                $client_secret = $paymentIntent->client_secret;
                $stripe_object_id = $paymentIntent->id; // This is the Payment Intent ID
                $pi_id_for_db = $stripe_object_id; // Store PI ID
                error_log("Orunk Stripe: PaymentIntent {$stripe_object_id} created for Purchase ID {$purchase_id}.");

                // Prepare DB update data for one-time payment
                $db_update_data['transaction_id'] = $stripe_object_id; // Store PI ID
                $db_update_formats[] = '%s';
                $db_update_data['gateway_subscription_id'] = null; // Ensure subscription ID is null
                $db_update_formats[] = '%s';

                 $response_data = [ /* response data unchanged */
                    'client_secret' => $client_secret, 'payment_intent_id' => $stripe_object_id, 'purchase_id' => $purchase_id, 'requires_confirmation' => true
                ];
            }

            // --- Update Orunk Purchase Record ---
            // This block now reliably saves the correct ID(s) before sending the response
            if (!empty($db_update_data)) {
                global $wpdb;
                $updated_db = $wpdb->update(
                    $wpdb->prefix . 'orunk_user_purchases',
                    $db_update_data,
                    ['id' => $purchase_id],
                    $db_update_formats,
                    ['%d']
                );
                // Log success or failure of this critical DB update
                if ($updated_db === false) {
                    error_log("Orunk Stripe CRITICAL DB Error: Failed to update Purchase ID {$purchase_id} with Stripe IDs (" . implode(', ', array_keys($db_update_data)) . "). DB Error: " . $wpdb->last_error);
                    // Throw an error back to the client as the link is crucial
                    throw new Exception("Failed to store critical payment gateway reference for Purchase ID {$purchase_id}.");
                } else {
                    error_log("Orunk Stripe: Successfully updated Purchase ID {$purchase_id} with Stripe IDs. Rows affected: {$updated_db}. Data: " . print_r($db_update_data, true));
                }
            } else {
                 error_log("Orunk Stripe Warning: No specific DB update data prepared for Purchase ID {$purchase_id} in handle_create_payment_intent.");
            }

            // --- Send Success Response ---
            wp_send_json_success($response_data);

        } catch (ApiErrorException $e) { /* Error handling unchanged */
            $error_message = $e->getMessage(); error_log("Orunk Stripe API Error (Purchase ID: {$purchase_id}): {$error_message} (Code: " . $e->getStripeCode() . ")"); if (isset($purchase_manager)) $purchase_manager->record_purchase_failure($purchase_id, 'Stripe API Error: ' . $error_message, $e->getRequestId()); wp_send_json_error(['message' => __('Payment processing error: ', 'orunk-users') . $error_message], $e->getHttpStatus() ?: 500);
        } catch (Exception $e) { /* Error handling unchanged */
            $error_message = $e->getMessage(); error_log("Orunk General Error (Purchase ID: {$purchase_id}): {$error_message}"); if (isset($purchase_manager)) $purchase_manager->record_purchase_failure($purchase_id, 'Processing Error: ' . $error_message); wp_send_json_error(['message' => __('Could not initiate payment: ', 'orunk-users') . $error_message], 500);
        }
    } // End handle_create_payment_intent


    // --- handle_process_payment Method (Unchanged) ---
    /**
     * AJAX Handler: Process Payment confirmation/redirection.
     * Handles 'wp_ajax_orunk_process_payment'.
     * For Stripe, this is now mainly just for getting the redirect URL after client-side success.
     */
    public function handle_process_payment() {
        // Method code remains exactly the same as the original version
        $permission_check = $this->check_logged_in_ajax_permissions('orunk_process_payment_nonce'); if (is_wp_error($permission_check)) { $this->send_json_error_from_wp_error($permission_check); }
         $user_id = get_current_user_id(); $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0; $checkout_type = isset($_POST['checkout_type']) ? sanitize_key($_POST['checkout_type']) : 'new_purchase'; $existing_purchase_id = ($checkout_type === 'switch' && isset($_POST['existing_purchase_id'])) ? absint($_POST['existing_purchase_id']) : 0; $gateway_id = isset($_POST['payment_method']) ? sanitize_key($_POST['payment_method']) : ''; $purchase_id_from_client = isset($_POST['purchase_id']) ? absint($_POST['purchase_id']) : 0; $pi_id_from_client = isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : null;
         if ($plan_id <= 0 || empty($gateway_id)) { wp_send_json_error(['message' => __('Missing required checkout information.', 'orunk-users')], 400); } if ($checkout_type === 'switch' && $existing_purchase_id <= 0) { wp_send_json_error(['message' => __('Missing existing purchase ID for switch.', 'orunk-users')], 400); }
         if (!$this->core || !$this->db || !class_exists('Custom_Orunk_Purchase_Manager')) { wp_send_json_error(['message' => __('Core components missing.', 'orunk-users')], 500); }
         global $wpdb; $purchases_table = $wpdb->prefix . 'orunk_user_purchases'; $order_confirmation_url = home_url('/order-confirmation/'); if (!get_page_by_path('order-confirmation')) { $order_confirmation_url = home_url('/orunk-dashboard/'); }
         $plan = $this->core->get_plan_details($plan_id); if (!$plan) { wp_send_json_error(['message' => __('Selected plan not found.', 'orunk-users')], 404); } $plan_price = floatval($plan['price']);
         $purchase_manager = new Custom_Orunk_Purchase_Manager(); $orunk_db = $this->db;
         try {
             // --- Stripe Confirmation (Client-side confirmed, now just get redirect) ---
             if ($gateway_id === 'stripe') {
                  // $purchase_id_from_client should be the Orunk Purchase ID created during intent creation
                  if ($purchase_id_from_client <= 0) {
                      error_log("Orunk process_payment Error (Stripe): Received invalid purchase ID: {$purchase_id_from_client} after Stripe confirmation.");
                      wp_send_json_error(['message' => __('Invalid request data received after payment attempt.', 'orunk-users')], 400);
                  }
                  error_log("Orunk process_payment Info (Stripe): Client confirmation received for Purchase ID {$purchase_id_from_client}. Providing redirect URL.");
                  wp_send_json_success([
                      'message' => __('Payment processed! Redirecting to confirmation...', 'orunk-users'),
                      'purchase_id' => $purchase_id_from_client, // Send back the confirmed Orunk Purchase ID
                      'redirect_url' => add_query_arg([
                          'orunk_payment_status' => 'processing', // Use 'processing' status initially, webhook will confirm
                          'purchase_id' => $purchase_id_from_client,
                          '_wpnonce' => wp_create_nonce('orunk_order_confirmation_' . $purchase_id_from_client)
                          ], $order_confirmation_url)
                  ]);
             }
             // --- PayPal Flow (Initiate redirect) ---
             elseif ($gateway_id === 'paypal') { /* ... PayPal logic unchanged ... */
                 $transaction_type = ($checkout_type === 'switch') ? 'switch_attempt' : 'purchase'; $parent_id_for_initiate = ($checkout_type === 'switch') ? $existing_purchase_id : null; $init_result = $purchase_manager->initiate_purchase($user_id, $plan_id, $gateway_id, $transaction_type, $parent_id_for_initiate); if (is_wp_error($init_result)) { $this->send_json_error_from_wp_error($init_result); } $purchase_id_to_process = $init_result; $gateways = $this->core->get_available_payment_gateways(); if (!isset($gateways['paypal'])) { $purchase_manager->record_purchase_failure($purchase_id_to_process, 'PayPal gateway missing.'); wp_send_json_error(['message' => __('PayPal gateway unavailable.', 'orunk-users')], 500); } $paypal_gateway = $gateways['paypal']; $result_array = $paypal_gateway->process_payment($purchase_id_to_process); if (isset($result_array['result']) && $result_array['result'] === 'success' && !empty($result_array['redirect'])) { wp_send_json_success([ 'message' => __('Redirecting to PayPal...', 'orunk-users'), 'redirect_url' => $result_array['redirect'] ]); } else { $error_msg = $result_array['message'] ?? __('Failed to initiate PayPal payment.', 'orunk-users'); $purchase_manager->record_purchase_failure($purchase_id_to_process, 'PayPal process_payment failed: ' . $error_msg); wp_send_json_error(['message' => $error_msg], 500); }
            }
             // --- Bank Transfer / Free Checkout ---
             elseif ($gateway_id === 'bank' || ($gateway_id === 'free_checkout' && $plan_price <= 0)) { /* ... Bank/Free logic unchanged ... */
                 $transaction_type = ($checkout_type === 'switch') ? 'switch_attempt' : 'purchase'; $parent_id_for_initiate = ($checkout_type === 'switch') ? $existing_purchase_id : null; $init_result = $purchase_manager->initiate_purchase($user_id, $plan_id, $gateway_id, $transaction_type, $parent_id_for_initiate); if (is_wp_error($init_result)) { $this->send_json_error_from_wp_error($init_result); } $purchase_id_to_process = $init_result; if ($gateway_id === 'free_checkout') { $activation_result = $purchase_manager->activate_purchase($purchase_id_to_process, 'free_checkout'); if (is_wp_error($activation_result)) { $purchase_manager->record_purchase_failure($purchase_id_to_process, 'Free activation failed: ' . $activation_result->get_error_message()); wp_send_json_error(['message' => 'Activation failed: ' . $activation_result->get_error_message()], 500); } else { set_transient('orunk_purchase_message_' . $user_id, __('Your free plan has been activated!', 'orunk-users'), 300); wp_send_json_success(['message' => 'Free plan activated!', 'redirect_url' => add_query_arg(['orunk_payment_status' => 'success_free', 'purchase_id' => $purchase_id_to_process, '_wpnonce' => wp_create_nonce('orunk_order_confirmation_' . $purchase_id_to_process)], $order_confirmation_url)]); } } else { if (!class_exists('Orunk_Gateway_Bank')) { $bank_path = ORUNK_USERS_PLUGIN_DIR . 'includes/gateways/class-orunk-gateway-bank.php'; if(file_exists($bank_path)) { require_once $bank_path; } } if (class_exists('Orunk_Gateway_Bank')) { $bank_gateway = new Orunk_Gateway_Bank(); $result_array = $bank_gateway->process_payment($purchase_id_to_process); $instructions = $result_array['message'] ?? __('Use Purchase ID %d as reference.', 'orunk-users'); $instructions = sprintf($instructions, $purchase_id_to_process); if ($checkout_type === 'switch') { $instructions = str_replace('Purchase ID: ' . $purchase_id_to_process, 'REFERENCE: SWITCH-' . $existing_purchase_id . '-TO-' . $plan_id, $instructions); $wpdb->update($purchases_table, ['pending_switch_plan_id' => $plan_id], ['id' => $existing_purchase_id], ['%d'], ['%d']); } set_transient('orunk_purchase_message_' . $user_id, $instructions, 600); wp_send_json_success(['message' => __('Follow bank transfer instructions.', 'orunk-users'), 'redirect_url' => add_query_arg(['orunk_payment_status' => 'pending_bank', 'purchase_id' => $purchase_id_to_process, '_wpnonce' => wp_create_nonce('orunk_order_confirmation_' . $purchase_id_to_process)], $order_confirmation_url)]); } else { $purchase_manager->record_purchase_failure($purchase_id_to_process, 'Bank gateway class missing.'); wp_send_json_error(['message' => __('Bank transfer error.', 'orunk-users')], 500); } }
            }
             else { wp_send_json_error(['message' => __('Unsupported payment method.', 'orunk-users')], 400); }
         } catch (Exception $e) {
             error_log("Orunk process_payment Exception: " . $e->getMessage());
             wp_send_json_error(['message' => __('Unexpected server error during checkout.', 'orunk-users')], 500);
         }
    }


    /**
     * AJAX Handler: Regenerate API Key for a given purchase.
     * Handles 'wp_ajax_orunk_regenerate_api_key'.
     */
    public function handle_regenerate_api_key() {
        // --- (Method code remains exactly the same as original) ---
        $permission_check = $this->check_logged_in_ajax_permissions('orunk_regenerate_api_key_nonce'); if (is_wp_error($permission_check)) { $this->send_json_error_from_wp_error($permission_check); } $user_id = get_current_user_id(); $purchase_id = isset($_POST['purchase_id']) ? absint($_POST['purchase_id']) : 0; if ($purchase_id <= 0) { wp_send_json_error(['message' => __('Invalid purchase ID.', 'orunk-users')], 400); } global $wpdb; $purchases_table = $wpdb->prefix . 'orunk_user_purchases'; $purchase = $wpdb->get_row($wpdb->prepare("SELECT id, user_id, status, api_key FROM `$purchases_table` WHERE id = %d AND user_id = %d", $purchase_id, $user_id), ARRAY_A); if (!$purchase) { wp_send_json_error(['message' => __('Purchase not found or access denied.', 'orunk-users')], 404); } if ($purchase['status'] !== 'active') { wp_send_json_error(['message' => __('API key can only be regenerated for active plans.', 'orunk-users')], 400); } if (empty($purchase['api_key'])) { wp_send_json_error(['message' => __('This plan does not have an API key associated with it.', 'orunk-users')], 400); } if (!class_exists('Orunk_Api_Key_Manager')) { wp_send_json_error(['message' => __('API Key Manager component missing.', 'orunk-users')], 500); } $api_key_manager = new Orunk_Api_Key_Manager(); $new_api_key_result = $api_key_manager->generate_unique_api_key($purchase_id); if (is_wp_error($new_api_key_result)) { $this->send_json_error_from_wp_error($new_api_key_result); } $new_api_key = $new_api_key_result; $updated = $wpdb->update($purchases_table, ['api_key' => $new_api_key], ['id' => $purchase_id], ['%s'], ['%d']); if ($updated === false) { error_log("Orunk AJAX Error: DB error updating API key for purchase {$purchase_id}: " . $wpdb->last_error); wp_send_json_error(['message' => __('Database error updating API key.', 'orunk-users')], 500); } wp_send_json_success(['message' => __('API Key regenerated successfully!', 'orunk-users'), 'new_key' => $new_api_key, 'masked_key' => substr($new_api_key, 0, 8) . '...' . substr($new_api_key, -4)]);
    }

    /**
     * AJAX Handler: Toggle Auto-Renewal status for a purchase.
     * Handles 'wp_ajax_orunk_toggle_auto_renew'.
     */
     public function handle_toggle_auto_renew() {
         // --- (Method code remains exactly the same as original) ---
        $permission_check = $this->check_logged_in_ajax_permissions('orunk_auto_renew_nonce'); if (is_wp_error($permission_check)) { $this->send_json_error_from_wp_error($permission_check); } global $wpdb; $purchases_table = $wpdb->prefix . 'orunk_user_purchases'; $user_id = get_current_user_id(); $purchase_id = isset($_POST['purchase_id']) ? absint($_POST['purchase_id']) : 0; $new_status = (isset($_POST['enabled']) && $_POST['enabled'] == '1') ? 1 : 0; if ($purchase_id <= 0) { wp_send_json_error(['message' => __('Invalid purchase ID.', 'orunk-users')], 400); } $current_auto_renew = $wpdb->get_var($wpdb->prepare( "SELECT auto_renew FROM `$purchases_table` WHERE id = %d AND user_id = %d", $purchase_id, $user_id )); if ($current_auto_renew === null) { wp_send_json_error(['message' => __('Purchase not found or access denied.', 'orunk-users')], 404); } $updated = $wpdb->update( $purchases_table, ['auto_renew' => $new_status], ['id' => $purchase_id, 'user_id' => $user_id], ['%d'], ['%d', '%d'] ); if ($updated !== false) { $message = $new_status ? __('Auto-renewal enabled.', 'orunk-users') : __('Auto-renewal disabled.', 'orunk-users'); wp_send_json_success(['message' => $message, 'new_status' => $new_status]); } else { error_log("Orunk Users DB Error updating auto-renew for purchase $purchase_id: " . $wpdb->last_error); wp_send_json_error(['message' => __('Failed to update auto-renewal status.', 'orunk-users')], 500); }
     }

    /**
     * AJAX Handler: Send Password Reset Link (Traditional WP Flow).
     * Handles 'wp_ajax_nopriv_orunk_send_reset_link' and 'wp_ajax_orunk_send_reset_link'.
     */
     public function handle_send_reset_link() {
         // --- (Method code remains exactly the same as original) ---
         $permission_check = $this->check_nopriv_ajax_permissions('orunk_update_profile_nonce'); // Ensure nonce matches what's sent
         if (is_wp_error($permission_check)) { $this->send_json_error_from_wp_error($permission_check); }
         if (empty($_POST['email'])) { wp_send_json_error(['message' => __('Please enter your email address.', 'orunk-users')], 400); }
         $email = sanitize_email(wp_unslash($_POST['email']));
         $user_data = get_user_by('email', $email);
         // Security: Always return success message regardless of user existence
         if (!$user_data) { wp_send_json_success(['message' => __('If an account exists for this email, a password reset link has been sent.', 'orunk-users')]); }
         $user_login = $user_data->user_login;
         $user_email = $user_data->user_email;
         $user_id = $user_data->ID;
         $reset_key = get_password_reset_key($user_data);
         if (is_wp_error($reset_key)) { error_log("Orunk PW Reset Link Error: Failed key generation for user {$user_id}: " . $reset_key->get_error_message()); wp_send_json_error(['message' => __('Could not generate reset key. Please try again later.', 'orunk-users')], 500); }
         $reset_link = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user_login), 'login');
         $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
         $subject = sprintf(__('[%s] Password Reset Request', 'orunk-users'), $site_name);
         $message = __('Someone requested a password reset for the following account:', 'orunk-users') . "\r\n\r\n" .
                    sprintf(__('Site Name: %s', 'orunk-users'), $site_name) . "\r\n" .
                    sprintf(__('Username: %s', 'orunk-users'), $user_login) . "\r\n\r\n" .
                    __('If this was a mistake, ignore this email.', 'orunk-users') . "\r\n\r\n" .
                    __('To reset your password, visit:', 'orunk-users') . "\r\n" . $reset_link . "\r\n\r\n" .
                    __('This link is valid for 24 hours.', 'orunk-users') . "\r\n";
         if (!wp_mail($user_email, wp_specialchars_decode($subject), $message)) { error_log("Orunk PW Reset Link Error: wp_mail failed for {$user_id} ({$user_email})."); wp_send_json_error(['message' => __('The reset email could not be sent. Please contact support.', 'orunk-users')], 500); }
         error_log("Orunk PW Reset Link: Sent traditional reset link for {$user_id} ({$user_email}).");
         wp_send_json_success(['message' => __('If an account exists for this email, a password reset link has been sent.', 'orunk-users')]);
     }

    //==========================================
    // REMOVED ADMIN AJAX Handlers (Moved to separate files)
    //==========================================
    // ...

    //==========================================
    // REMOVED USER PROFILE/BILLING AJAX Handlers (Moved to separate file)
    //==========================================
    // ...

} // End Class Orunk_AJAX_Handlers