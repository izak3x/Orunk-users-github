<?php
/**
 * Orunk Users PayPal Webhook Handler Class
 *
 * Handles incoming webhooks specifically from PayPal.
 * INCLUDES DEBUG LOGGING
 *
 * MODIFIED: Added explicit require_once for dependencies before class definition.
 *
 * @package OrunkUsers\Includes
 * @version 1.1.2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// --- Use statements for PayPal SDK Components ---
use PayPalHttp\HttpException;
// Removed HttpRequest/HttpResponse as they aren't directly used now
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
// Removed unused SDK v1 use statements

// --- FIX: Wrap class definition in class_exists check AND load dependencies inside ---
if (!class_exists('Orunk_PayPal_Webhook_Handler')) {

    // --- Ensure ORUNK_USERS_PLUGIN_DIR is defined ---
    if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
        define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__, 2))); // Go up two directories
    }

    // --- Explicitly require dependencies needed by this class HERE ---
    $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
    $gateway_path = ORUNK_USERS_PLUGIN_DIR . 'includes/gateways/class-orunk-gateway-paypal.php';
    $purchase_manager_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-purchase-manager.php';

    if (file_exists($db_path)) { require_once $db_path; }
    else { error_log("Orunk PayPal Webhook Handler FATAL: Cannot load dependency Custom_Orunk_DB."); /* Optionally return or die */ }

    if (file_exists($gateway_path)) { require_once $gateway_path; }
    else { error_log("Orunk PayPal Webhook Handler FATAL: Cannot load dependency Orunk_Gateway_Paypal."); /* Optionally return or die */ }

    if (file_exists($purchase_manager_path)) { require_once $purchase_manager_path; }
    else { error_log("Orunk PayPal Webhook Handler FATAL: Cannot load dependency Custom_Orunk_Purchase_Manager."); /* Optionally return or die */ }
    // --- End explicit requires ---


    class Orunk_PayPal_Webhook_Handler {

        /** @var Custom_Orunk_DB DB handler instance */
        private $db;

        /** @var Orunk_Gateway_Paypal PayPal Gateway instance (for settings) */
        private $paypal_gateway;

        /** @var Custom_Orunk_Purchase_Manager Purchase Manager instance */
        private $purchase_manager;

        /**
         * Constructor.
         */
        public function __construct() {
            // Instantiate handlers - classes should now definitely exist if files were found
            if (class_exists('Custom_Orunk_DB')) {
                 $this->db = new Custom_Orunk_DB();
            } else {
                 error_log("Orunk PayPal Webhook Handler Error: Custom_Orunk_DB class missing in constructor despite require attempt.");
                 $this->db = null;
            }

            if (class_exists('Custom_Orunk_Purchase_Manager')) {
                $this->purchase_manager = new Custom_Orunk_Purchase_Manager();
            } else {
                 error_log("Orunk PayPal Webhook Handler Error: Custom_Orunk_Purchase_Manager class missing in constructor despite require attempt.");
                 $this->purchase_manager = null;
            }

            // Load Gateway settings instance
            if (class_exists('Orunk_Gateway_Paypal')) {
                 $this->paypal_gateway = new Orunk_Gateway_Paypal(); // Get fresh instance for settings
            } else {
                 error_log("Orunk PayPal Webhook Handler Error: Orunk_Gateway_Paypal class missing in constructor despite require attempt.");
                 $this->paypal_gateway = null;
            }

             if (!$this->paypal_gateway) {
                 error_log("Orunk PayPal Webhook Handler FATAL Error: Could not instantiate PayPal Gateway in constructor.");
                 // We might want to prevent further processing if the gateway settings can't be loaded
             }
        }

        /**
         * Verify the incoming PayPal webhook signature.
         * (Function unchanged - placeholder logic remains)
         *
         * @param WP_REST_Request $request The request object.
         * @param string $webhook_id The Webhook ID from PayPal settings.
         * @return bool|WP_Error True if verified, WP_Error otherwise.
         */
        private function verify_webhook_signature( WP_REST_Request $request, $webhook_id ) {
            error_log('Orunk PayPal Webhook: Attempting signature verification...');
            $headers = $request->get_headers();
            $paypal_auth_algo       = $headers['paypal_auth_algo'][0] ?? null;
            $paypal_cert_url        = $headers['paypal_cert_url'][0] ?? null;
            $paypal_transmission_id = $headers['paypal_transmission_id'][0] ?? null;
            $paypal_transmission_sig= $headers['paypal_transmission_sig'][0] ?? null;
            $paypal_transmission_time = $headers['paypal_transmission_time'][0] ?? null;
            $raw_body               = $request->get_body();
            if ( !$paypal_auth_algo || !$paypal_cert_url || !$paypal_transmission_id || !$paypal_transmission_sig || !$paypal_transmission_time || empty($raw_body) ) {
                error_log('Orunk PayPal Webhook Error: Missing required verification headers.');
                return new WP_Error('missing_headers', 'Missing required PayPal verification headers.', ['status' => 400]);
            }
            // --- Placeholder for SDK Verification Logic ---
            /* try { // ... Actual SDK verification logic ... } catch (Exception $e) { return new WP_Error(...); } */
            error_log('Orunk PayPal Webhook: SKIPPING ACTUAL SIGNATURE VERIFICATION (Placeholder). Returning TRUE.');
            return true; // <-- REMOVE THIS LINE WHEN IMPLEMENTING REAL VERIFICATION
        }


        /**
         * Handle PayPal Webhook Events.
         * (Function unchanged from previous version with added subscription events)
         *
         * @param WP_REST_Request $request The request object from the REST API.
         * @return WP_REST_Response|WP_Error Response object or error.
         */
        public function handle_paypal_event(WP_REST_Request $request) {
            error_log('PayPal Webhook: handle_paypal_event started.');

            // --- MODIFIED: Essential Checks - Ensure instances are available ---
            if (!$this->db || !$this->paypal_gateway || !$this->purchase_manager) {
                 error_log("Orunk PayPal Webhook Error: DB/Gateway/PurchaseManager components missing during execution. Check constructor logs.");
                 // If purchase_manager is missing here, the error happened during instantiation
                 return new WP_Error('internal_error', 'Server components missing.', ['status' => 500]);
            }
            // --- END MODIFIED ---

            $webhook_id = $this->paypal_gateway->get_option('webhook_id');
            if (empty($webhook_id)) {
                 error_log("Orunk PayPal Webhook Error: Missing PayPal Webhook ID in settings.");
                 return new WP_Error('config_error', 'Webhook configuration missing.', ['status' => 500]);
            }

            // --- 1. Verify the Webhook Signature ---
            $verification_result = $this->verify_webhook_signature($request, $webhook_id);
            if (is_wp_error($verification_result)) {
                 return $verification_result;
            }

            // --- 2. Parse the Event ---
            $event_payload = $request->get_json_params();
            if (empty($event_payload) || !isset($event_payload['event_type'])) {
                 error_log('Orunk PayPal Webhook Error: Invalid or empty payload after parsing.');
                 return new WP_Error('invalid_payload', 'Invalid webhook payload.', ['status' => 400]);
            }
            $event_type = $event_payload['event_type'];
            $resource = $event_payload['resource'] ?? null;
            $event_id = $event_payload['id'] ?? 'N/A';
            error_log("Orunk PayPal Webhook: Processing event '{$event_type}'. Event ID: {$event_id}");

             // --- 3. Handle Specific Events ---
             global $wpdb;
             $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
             $paypal_subscription_id = null;
             $orunk_purchase_id = null;
             $transaction_reference = null;
             $amount_paid = null;
             $currency = null;

             try {
                switch ($event_type) {

                    // --- Initial Order/Subscription Approval & First Payment ---
                    case 'CHECKOUT.ORDER.APPROVED':
                        // (Logic unchanged)
                        $paypal_order_id = $resource['id'] ?? null; $orunk_purchase_id = $resource['purchase_units'][0]['custom_id'] ?? ($resource['custom_id'] ?? null); $transaction_reference = $paypal_order_id; $amount_paid = $resource['purchase_units'][0]['amount']['value'] ?? null; $currency = $resource['purchase_units'][0]['amount']['currency_code'] ?? null; error_log("PayPal Webhook: Handling {$event_type}. PayPal Order ID: {$paypal_order_id}, Orunk Purchase ID: {$orunk_purchase_id}. Awaiting capture/activation."); if ($orunk_purchase_id && $paypal_order_id) { $wpdb->update( $purchases_table, ['transaction_id' => $paypal_order_id], ['id' => absint($orunk_purchase_id), 'transaction_id' => null], ['%s'], ['%d', '%s'] ); }
                        break;

                    case 'PAYMENT.CAPTURE.COMPLETED':
                        // (Logic unchanged)
                         $capture = $resource['purchase_units'][0]['payments']['captures'][0] ?? null; if ($capture) { $orunk_purchase_id = $capture['custom_id'] ?? null; $transaction_reference = $capture['id'] ?? null; $amount_paid = $capture['amount']['value'] ?? null; $currency = $capture['amount']['currency_code'] ?? null; $paypal_order_id = null; if (isset($capture['links'])) { foreach($capture['links'] as $link) { if (isset($link['rel']) && $link['rel'] === 'up' && isset($link['href']) && preg_match('/\/v2\/checkout\/orders\/([^\/?]+)/', $link['href'], $matches)) { $paypal_order_id = $matches[1]; break; } } } if (!$orunk_purchase_id && isset($resource['custom_id'])) { $orunk_purchase_id = $resource['custom_id']; } if (!$paypal_order_id && isset($resource['id'])) { $paypal_order_id = $resource['id']; } error_log("PayPal Webhook: Handling {$event_type}. Capture ID: {$transaction_reference}, Order ID: {$paypal_order_id}, Orunk Purchase ID: {$orunk_purchase_id}. Amount: {$amount_paid} {$currency}."); if ($orunk_purchase_id) { $activation_result = $this->purchase_manager->activate_purchase( absint($orunk_purchase_id), $transaction_reference, $amount_paid, null, null, null, $currency ); if (is_wp_error($activation_result)) { if ($activation_result->get_error_code() !== 'not_pending_payment') { error_log("PayPal Webhook Error ({$event_type}): Failed to activate Orunk purchase ID {$orunk_purchase_id}. Error: " . $activation_result->get_error_message()); $this->purchase_manager->record_purchase_failure(absint($orunk_purchase_id), "Webhook activation failed ({$event_type}): " . $activation_result->get_error_message(), $transaction_reference); } else { error_log("PayPal Webhook Info ({$event_type}): Activation skipped for Orunk purchase ID {$orunk_purchase_id} (not pending). Status: " . $activation_result->get_error_data('current_status')); } } else { error_log("PayPal Webhook: Successfully activated Orunk purchase ID {$orunk_purchase_id} via {$event_type}."); } } else { error_log("PayPal Webhook Error ({$event_type}): Missing custom_id (Orunk Purchase ID) in capture data."); } } else { error_log("PayPal Webhook Error ({$event_type}): Could not find capture details in resource."); }
                        break;

                    // --- Subscription Specific Events ---
                    case 'BILLING.SUBSCRIPTION.ACTIVATED':
                        // (Logic unchanged)
                         $paypal_subscription_id = $resource['id'] ?? null; $orunk_purchase_id = $resource['custom_id'] ?? null; $transaction_reference = $paypal_subscription_id; error_log("PayPal Webhook: Handling {$event_type}. PayPal Subscription ID: {$paypal_subscription_id}, Orunk Purchase ID: {$orunk_purchase_id}."); if ($orunk_purchase_id && $paypal_subscription_id) { $purchase_id_abs = absint($orunk_purchase_id); $current_data = $wpdb->get_row($wpdb->prepare("SELECT status, gateway_subscription_id FROM $purchases_table WHERE id = %d", $purchase_id_abs), ARRAY_A); if ($current_data && empty($current_data['gateway_subscription_id'])) { $wpdb->update( $purchases_table, ['gateway_subscription_id' => $paypal_subscription_id], ['id' => $purchase_id_abs], ['%s'], ['%d'] ); error_log("PayPal Webhook ({$event_type}): Stored Subscription ID {$paypal_subscription_id} for Orunk Purchase ID {$purchase_id_abs}."); } if ($current_data && in_array($current_data['status'], ['Pending Payment', 'pending'])) { error_log("PayPal Webhook ({$event_type}): Activating Orunk Purchase ID {$purchase_id_abs} based on subscription activation."); $activation_result = $this->purchase_manager->activate_purchase( $purchase_id_abs, $transaction_reference, null , $paypal_subscription_id, null, null, null ); if (is_wp_error($activation_result)) { error_log("PayPal Webhook ({$event_type}) Error activating {$purchase_id_abs}: ".$activation_result->get_error_message()); } } else { error_log("PayPal Webhook ({$event_type}): Orunk Purchase ID {$purchase_id_abs} already active or in non-pending state."); } } else { error_log("PayPal Webhook Error ({$event_type}): Missing Orunk Purchase ID (custom_id) or PayPal Subscription ID in resource."); }
                        break;

                    case 'PAYMENT.SALE.COMPLETED': // Recurring payment success
                        // (Logic unchanged)
                         $paypal_subscription_id = $resource['billing_agreement_id'] ?? null; $transaction_reference = $resource['id'] ?? null; $amount_paid = $resource['amount']['total'] ?? null; $currency = $resource['amount']['currency'] ?? null; error_log("PayPal Webhook: Handling {$event_type}. Sale ID: {$transaction_reference}, Subscription ID: {$paypal_subscription_id}, Amount: {$amount_paid} {$currency}."); if ($paypal_subscription_id) { $last_active_purchase = $wpdb->get_row($wpdb->prepare( "SELECT * FROM $purchases_table WHERE gateway_subscription_id = %s AND status = 'active' ORDER BY purchase_date DESC LIMIT 1", $paypal_subscription_id ), ARRAY_A); if ($last_active_purchase) { $user_id = absint($last_active_purchase['user_id']); $plan_id = absint($last_active_purchase['plan_id']); $parent_purchase_id = absint($last_active_purchase['id']); error_log("PayPal Webhook ({$event_type}): Found last active purchase ID {$parent_purchase_id} for renewal. User: {$user_id}, Plan: {$plan_id}."); $duplicate_check = $wpdb->get_var($wpdb->prepare( "SELECT id FROM $purchases_table WHERE parent_purchase_id = %d AND transaction_id = %s AND transaction_type = 'renewal_success'", $parent_purchase_id, $transaction_reference )); if ($duplicate_check) { error_log("PayPal Webhook ({$event_type}): Duplicate renewal event detected for Sale ID {$transaction_reference} and Parent Purchase {$parent_purchase_id}. Skipping."); break; } $init_renewal_result = $this->purchase_manager->initiate_purchase( $user_id, $plan_id, 'paypal', 'renewal_success', $parent_purchase_id ); if (is_wp_error($init_renewal_result)) { error_log("PayPal Webhook Error ({$event_type}): Failed to initiate renewal record for Sub ID {$paypal_subscription_id}. Error: " . $init_renewal_result->get_error_message()); } else { $new_renewal_purchase_id = $init_renewal_result; error_log("PayPal Webhook ({$event_type}): Initiated new renewal record ID {$new_renewal_purchase_id}. Attempting activation..."); $activation_result = $this->purchase_manager->activate_purchase( $new_renewal_purchase_id, $transaction_reference, $amount_paid, $paypal_subscription_id, null, null, $currency ); if (is_wp_error($activation_result)) { error_log("PayPal Webhook Error ({$event_type}): Failed to activate NEW renewal purchase ID {$new_renewal_purchase_id}. Error: " . $activation_result->get_error_message()); $this->purchase_manager->record_purchase_failure($new_renewal_purchase_id, "Webhook activation failed after renewal ({$event_type}): " . $activation_result->get_error_message(), $transaction_reference); } else { error_log("PayPal Webhook ({$event_type}): Successfully activated NEW renewal purchase ID {$new_renewal_purchase_id}."); } } } else { error_log("PayPal Webhook Error ({$event_type}): Could not find previous active Orunk purchase for Subscription ID {$paypal_subscription_id}. Cannot process renewal."); } } else { error_log("PayPal Webhook Warning ({$event_type}): Missing billing_agreement_id (Subscription ID) in resource. Cannot process as renewal."); }
                        break;

                    case 'BILLING.SUBSCRIPTION.CANCELLED':
                    case 'BILLING.SUBSCRIPTION.EXPIRED':
                        // (Logic unchanged)
                         $paypal_subscription_id = $resource['id'] ?? null; $new_status = ($event_type === 'BILLING.SUBSCRIPTION.CANCELLED') ? 'cancelled' : 'expired'; error_log("PayPal Webhook: Handling {$event_type}. PayPal Subscription ID: {$paypal_subscription_id}. Setting status to {$new_status}."); if ($paypal_subscription_id) { $updated = $wpdb->update( $purchases_table, ['status' => $new_status, 'auto_renew' => 0], ['gateway_subscription_id' => $paypal_subscription_id], ['%s', '%d'], ['%s'] ); if ($updated === false) { error_log("PayPal Webhook Error ({$event_type}): DB error updating status for Sub ID {$paypal_subscription_id}. Error: " . $wpdb->last_error); } elseif ($updated > 0) { error_log("PayPal Webhook ({$event_type}): Successfully updated Orunk purchase status to '{$new_status}' for Sub ID {$paypal_subscription_id}. Rows: {$updated}."); } else { error_log("PayPal Webhook ({$event_type}): No active Orunk purchase found or status already updated for Sub ID {$paypal_subscription_id}."); } } else { error_log("PayPal Webhook Error ({$event_type}): Missing PayPal Subscription ID (resource.id) in payload."); }
                        break;

                    case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
                        // (Logic unchanged)
                         $paypal_subscription_id = $resource['id'] ?? null; $failure_reason = "PayPal subscription payment failed ({$event_type})."; error_log("PayPal Webhook: Handling {$event_type}. PayPal Subscription ID: {$paypal_subscription_id}."); if ($paypal_subscription_id) { $last_active_purchase = $wpdb->get_row($wpdb->prepare( "SELECT * FROM $purchases_table WHERE gateway_subscription_id = %s AND status = 'active' ORDER BY purchase_date DESC LIMIT 1", $paypal_subscription_id ), ARRAY_A); if ($last_active_purchase) { $purchase_id_to_fail = absint($last_active_purchase['id']); error_log("PayPal Webhook ({$event_type}): Recording failure for Orunk purchase ID {$purchase_id_to_fail} linked to Sub ID {$paypal_subscription_id}."); $this->purchase_manager->record_purchase_failure($purchase_id_to_fail, $failure_reason, $paypal_subscription_id); $wpdb->update( $purchases_table, ['status' => 'cancelled', 'auto_renew' => 0], ['id' => $purchase_id_to_fail], ['%s', '%d'], ['%d']); } else { error_log("PayPal Webhook Error ({$event_type}): No active Orunk purchase found for Sub ID {$paypal_subscription_id}. Cannot record failure."); } } else { error_log("PayPal Webhook Error ({$event_type}): Missing PayPal Subscription ID (resource.id) in payload."); }
                        break;

                    // --- Denied/Cancelled Events for Initial Order ---
                    case 'PAYMENT.CAPTURE.DENIED':
                    case 'CHECKOUT.ORDER.CANCELLED':
                        // (Logic unchanged)
                          $paypal_order_id = $resource['id'] ?? null; $orunk_purchase_id = null; if (isset($resource['custom_id'])) { $orunk_purchase_id = absint($resource['custom_id']); } elseif (isset($resource['purchase_units'][0]['custom_id'])) { $orunk_purchase_id = absint($resource['purchase_units'][0]['custom_id']); } if ($orunk_purchase_id) { $failure_reason = "PayPal payment denied or cancelled ({$event_type})."; error_log("Orunk PayPal Webhook ({$event_type}): Recording failure for Orunk Purchase ID {$orunk_purchase_id}. Reason: {$failure_reason}"); $this->purchase_manager->record_purchase_failure($orunk_purchase_id, $failure_reason, $paypal_order_id); } else { error_log("Orunk PayPal Webhook Warning ({$event_type}): Missing custom_id (Orunk Purchase ID). Cannot record failure accurately. Resource: " . print_r($resource, true)); }
                          break;

                    // --- Default Case ---
                    default:
                        error_log("Orunk PayPal Webhook: Received unhandled event type: {$event_type}");
                } // End switch
            } catch (Exception $e) {
                 // Catch errors during event processing logic
                error_log("Orunk PayPal Webhook FATAL Error during event processing ('{$event_type}'): " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                // Still return 200 OK to PayPal, but log the critical server-side error
                return new WP_REST_Response(['received' => true, 'error' => 'Internal server error during processing'], 200);
            }

            // --- 4. Respond to PayPal ---
            error_log("Orunk PayPal Webhook: Finished processing event '{$event_type}'. Event ID: {$event_id}. Sending 200 OK.");
            return new WP_REST_Response(['received' => true], 200);
        } // End handle_paypal_event
    } // End Class

} // End if (!class_exists(...