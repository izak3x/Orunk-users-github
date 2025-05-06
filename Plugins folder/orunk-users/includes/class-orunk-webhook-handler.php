<?php
/**
 * Orunk Users Webhook Handler Class
 *
 * Handles incoming webhooks, primarily from Stripe, for payment confirmations,
 * renewals, and switch finalization.
 * Uses Purchase Manager for activation/failure recording.
 *
 * MODIFICATION: Added FINAL fallback using Payment Intent ID for linking.
 *
 * @package OrunkUsers\Includes
 * @version 2.0.8
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// --- Use statements ---
use Stripe\Stripe;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Subscription;
use Stripe\Charge;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;


// --- Ensure ORUNK_USERS_PLUGIN_DIR is defined ---
if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
    define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__, 2)));
}

// --- Ensure dependencies are loaded if needed ---
if (!class_exists('Custom_Orunk_DB')) {
    $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
    if (file_exists($db_path)) { require_once $db_path; }
    else { error_log("Orunk Stripe Webhook Handler CRITICAL ERROR: Cannot load Custom_Orunk_DB."); }
}
if (!class_exists('Custom_Orunk_Purchase_Manager')) {
    $pm_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-purchase-manager.php';
    if (file_exists($pm_path)) { require_once $pm_path; }
    else { error_log("Orunk Stripe Webhook Handler CRITICAL ERROR: Cannot load Custom_Orunk_Purchase_Manager."); }
}


// --- Wrap class definition ---
if (!class_exists('Orunk_Webhook_Handler')) {

    class Orunk_Webhook_Handler {

        /** @var Custom_Orunk_DB DB handler instance */
        public $db;

        /** @var Custom_Orunk_Purchase_Manager Purchase Manager instance */
        public $purchase_manager;

        /**
         * Constructor.
         */
        public function __construct() {
            // Instantiate handlers
            if (class_exists('Custom_Orunk_DB')) {
                $this->db = new Custom_Orunk_DB();
            } else {
                error_log('Orunk Stripe Webhook Handler Constructor Error: Custom_Orunk_DB class not available.');
                $this->db = null;
            }

            if (class_exists('Custom_Orunk_Purchase_Manager')) {
                $this->purchase_manager = new Custom_Orunk_Purchase_Manager();
            } else {
                 error_log('Orunk Stripe Webhook Handler Constructor Error: Custom_Orunk_Purchase_Manager class not available.');
                 $this->purchase_manager = null;
            }
        }

        /**
         * Handle Stripe Webhook Events.
         *
         * @param WP_REST_Request $request The request object from the REST API.
         * @return WP_REST_Response|WP_Error Response object or error.
         */
        public function handle_stripe_event(WP_REST_Request $request) {
             error_log('Orunk Stripe Webhook: handle_stripe_event START.');

            // Ensure Stripe SDK is loaded
            if (!class_exists('\Stripe\Stripe')) {
                 error_log('Orunk Stripe Webhook FATAL Error: Stripe PHP SDK class not found.');
                 return new WP_Error('stripe_sdk_missing', 'Stripe library missing.', array('status' => 500));
            }

            // Ensure dependencies loaded correctly
            if (!$this->db || !$this->purchase_manager) {
                 error_log('Orunk Stripe Webhook Processing FATAL Error: DB or Purchase Manager handler instance is null IN METHOD.');
                 return new WP_Error('processing_handler_missing', 'Core components missing.', array('status' => 500));
            }

            // Get Stripe settings
            $stripe_settings = get_option('orunk_gateway_stripe_settings', array());
            $endpoint_secret = $stripe_settings['webhook_secret'] ?? null;
            $secret_key      = $stripe_settings['secret_key'] ?? null;

            if (empty($endpoint_secret) || empty($secret_key)) {
                error_log('Orunk Stripe Webhook FATAL Error: Stripe configuration missing (Secret Key or Webhook Secret).');
                return new WP_Error('webhook_config_missing', 'Webhook configuration missing on server.', array('status' => 500));
            }

            // Verify the webhook signature
            $payload    = $request->get_body();
            $sig_header = $request->get_header('stripe_signature');
            if (empty($sig_header)) { return new WP_Error('missing_signature', 'Missing Stripe-Signature header.', array('status' => 400)); }

            $event = null;
            try {
                Stripe::setApiKey($secret_key);
                $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
                 error_log('Orunk Stripe Webhook: Signature verified. Event type: ' . ($event->type ?? 'N/A') . ', ID: ' . ($event->id ?? 'N/A'));

            } catch (\UnexpectedValueException $e) { return new WP_Error('invalid_payload', 'Invalid webhook payload: ' . $e->getMessage(), array('status' => 400));
            } catch (SignatureVerificationException $e) { return new WP_Error('invalid_signature', 'Invalid webhook signature: ' . $e->getMessage(), array('status' => 400));
            } catch (Exception $e) { return new WP_Error('webhook_verification_error', 'Webhook verification failed: ' . $e->getMessage(), array('status' => 400)); }


            global $wpdb;
            $purchases_table = $wpdb->prefix . 'orunk_user_purchases';

            // --- Handle the event based on its type ---
            try {
                switch ($event->type) {

                    case 'customer.subscription.created':
                        // --- Logic remains the same: Store Sub ID early ---
                        $subscription = $event->data->object; $gateway_sub_id = $subscription->id; $metadata = $subscription->metadata ? $subscription->metadata->toArray() : []; $purchase_id = $metadata['orunk_purchase_id'] ?? null; $purchase_id = $purchase_id ? absint($purchase_id) : 0; $gateway_cust_id = $subscription->customer ?? null;
                        error_log("Orunk Stripe Webhook: Processing {$event->type}. Sub ID: {$gateway_sub_id}, Purchase ID from Meta: {$purchase_id}");
                        if ($purchase_id > 0 && $gateway_sub_id) { $updated = $wpdb->update( $purchases_table, ['gateway_subscription_id' => $gateway_sub_id, 'gateway_customer_id' => $gateway_cust_id], ['id' => $purchase_id, 'gateway_subscription_id' => null], ['%s', '%s'], ['%d', '%s']); if ($updated === false) { error_log("Orunk Stripe Webhook Error ({$event->type}): Failed to store Sub ID {$gateway_sub_id} for Purchase {$purchase_id}. DB Error: " . $wpdb->last_error); } elseif ($updated > 0) { error_log("Orunk Stripe Webhook ({$event->type}): Successfully stored Sub ID {$gateway_sub_id} and Cust ID {$gateway_cust_id} for Purchase {$purchase_id}."); } } else { error_log("Orunk Stripe Webhook Warning ({$event->type}): Missing Purchase ID or Subscription ID in metadata. Cannot update record."); }
                        break;

                    case 'payment_intent.succeeded':
                        $paymentIntent = $event->data->object;
                        $transaction_id = $paymentIntent->id; // PI ID
                        $purchase_id = null; // Reset purchase ID
                        $metadata = $paymentIntent->metadata ? $paymentIntent->metadata->toArray() : [];
                        $action_type = $metadata['orunk_action_type'] ?? 'unknown';
                        $amount_paid = ($paymentIntent->amount_received ?? 0) / 100.0;
                        $gateway_cust_id = $paymentIntent->customer ?? null;
                        $gateway_sub_id = $paymentIntent->subscription ?? null; // Check PI directly
                        $currency = $paymentIntent->currency ?? null;
                        $gateway_payment_method_id = $paymentIntent->payment_method ?? null;
                        $invoice_id = $paymentIntent->invoice ?? null;

                        // --- Find the Orunk Purchase ID (Revised Logic) ---
                        // 1. Check PI Metadata
                        $purchase_id = $metadata['orunk_purchase_id'] ?? null;
                        if ($purchase_id) { error_log("Orunk Stripe Webhook ({$event->type}): Found Purchase ID {$purchase_id} in PI metadata."); }

                        // 2. DB Lookup using Subscription ID (if available on PI)
                        if (empty($purchase_id) && !empty($gateway_sub_id)) {
                             error_log("Orunk Stripe Webhook ({$event->type}): Purchase ID missing on PI, trying DB lookup using Sub ID {$gateway_sub_id} from PI...");
                             $purchase_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$purchases_table} WHERE gateway_subscription_id = %s ORDER BY id DESC LIMIT 1", $gateway_sub_id));
                             if($purchase_id) error_log("Orunk Stripe Webhook ({$event->type}): Found Purchase ID {$purchase_id} via DB lookup using Sub ID from PI.");
                        }

                        // 3. Check Invoice Metadata (Fallback)
                        if (empty($purchase_id) && !empty($invoice_id)) {
                            error_log("Orunk Stripe Webhook ({$event->type}): Purchase ID still missing, trying Invoice {$invoice_id} metadata...");
                            try { Stripe::setApiKey($secret_key); $invoice_obj = Invoice::retrieve($invoice_id); $invoice_metadata = $invoice_obj->metadata ? $invoice_obj->metadata->toArray() : []; $purchase_id = $invoice_metadata['orunk_purchase_id'] ?? null; if ($purchase_id) error_log("Orunk Stripe Webhook ({$event->type}): Found Purchase ID {$purchase_id} on Invoice metadata."); if (empty($gateway_sub_id)) { $gateway_sub_id = $invoice_obj->subscription ?? null; if($gateway_sub_id) error_log("Orunk Stripe Webhook ({$event->type}): Found Sub ID {$gateway_sub_id} on Invoice object."); } } catch (Exception $e) { error_log("Orunk Stripe Webhook Warning ({$event->type}): Could not retrieve Invoice {$invoice_id}. Error: " . $e->getMessage()); }
                        }

                        // 4. <<< NEW FINAL FALLBACK: DB Lookup using PI ID (Transaction ID) >>>
                        if (empty($purchase_id) && !empty($transaction_id)) {
                             error_log("Orunk Stripe Webhook ({$event->type}): Purchase ID still missing, trying DB lookup using PI ID {$transaction_id}...");
                             $purchase_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$purchases_table} WHERE transaction_id = %s ORDER BY id DESC LIMIT 1", $transaction_id));
                             if($purchase_id) {
                                 error_log("Orunk Stripe Webhook ({$event->type}): Found Purchase ID {$purchase_id} via DB lookup using PI ID.");
                                 // If we found purchase via PI, try to get Sub ID from the purchase record if missing
                                 if (empty($gateway_sub_id)) {
                                     $gateway_sub_id = $wpdb->get_var($wpdb->prepare("SELECT gateway_subscription_id FROM {$purchases_table} WHERE id = %d", $purchase_id));
                                     if($gateway_sub_id) error_log("Orunk Stripe Webhook ({$event->type}): Retrieved Sub ID {$gateway_sub_id} from DB record {$purchase_id}.");
                                 }
                             }
                        }
                        // --- End Purchase ID finding logic ---

                        $purchase_id = $purchase_id ? absint($purchase_id) : 0;

                        error_log("Orunk Stripe Webhook: Processing {$event->type}. PI_ID: {$transaction_id}, Purchase_ID_Found: {$purchase_id}, Sub_ID: " . ($gateway_sub_id ?? 'N/A') . ", Action: {$action_type}, Amount: {$amount_paid}");

                        if (empty($purchase_id)) {
                            error_log("Orunk Stripe Webhook FATAL Error ({$event->type}): Still missing Purchase ID linkage after ALL fallbacks for PI {$transaction_id}. Cannot activate.");
                            return new WP_REST_Response(array('received' => true, 'error' => 'Missing purchase ID linkage'), 200);
                        }

                        // --- Activation logic (unchanged) ---
                        error_log("Orunk Stripe Webhook: Attempting to activate purchase ID {$purchase_id} for PI {$transaction_id} using Purchase Manager...");
                        $activation_result = $this->purchase_manager->activate_purchase( $purchase_id, $transaction_id, $amount_paid, $gateway_sub_id, $gateway_cust_id, $gateway_payment_method_id, $currency );
                        if (is_wp_error($activation_result)) { /* ... error handling ... */
                            $error_code = $activation_result->get_error_code();
                            if ($error_code === 'not_pending_payment') { error_log("Orunk Stripe Webhook Info ({$event->type}): Purchase ID {$purchase_id} activation skipped (Status not 'Pending Payment'). Current Status: " . ($activation_result->get_error_data('current_status') ?? 'N/A')); }
                            else { error_log("Orunk Stripe Webhook Error ({$event->type}): Failed to activate purchase ID {$purchase_id}. Error Code: {$error_code}, Message: " . $activation_result->get_error_message()); $this->purchase_manager->record_purchase_failure($purchase_id, "Webhook activation failed (PI): " . $activation_result->get_error_message(), $transaction_id); }
                         } else { /* ... success handling ... */
                            error_log("Orunk Stripe Webhook: Successfully activated/updated purchase ID {$purchase_id} via {$event->type} using Purchase Manager.");
                            // Handle switch completion logic (if applicable)
                            if ($action_type === 'switch') { /* ... switch logic unchanged ... */
                                $original_purchase_id = $metadata['orunk_original_purchase_id'] ?? null; $original_purchase_id = $original_purchase_id ? absint($original_purchase_id) : 0;
                                if ($original_purchase_id > 0) { error_log("Orunk Stripe Webhook Switch: Attempting to mark original purchase ID {$original_purchase_id} as 'switched'."); $updated_old = $wpdb->update( $purchases_table, ['status' => 'switched', 'pending_switch_plan_id' => null], ['id' => $original_purchase_id], ['%s', null], ['%d'] ); if ($updated_old !== false) { error_log("Orunk Stripe Webhook Switch: Successfully updated OLD purchase {$original_purchase_id} status to 'switched'."); $user_id_for_hook = $metadata['orunk_user_id'] ?? $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $purchases_table WHERE id = %d", $original_purchase_id)); $switched_from_plan_id = $wpdb->get_var($wpdb->prepare("SELECT plan_id FROM $purchases_table WHERE id = %d", $original_purchase_id)); $switched_to_plan_id = $metadata['orunk_plan_id'] ?? $wpdb->get_var($wpdb->prepare("SELECT plan_id FROM $purchases_table WHERE id = %d", $purchase_id)); do_action('orunk_plan_switched', $original_purchase_id, $purchase_id, $user_id_for_hook, $switched_from_plan_id, $switched_to_plan_id); } else { error_log("Orunk Stripe Webhook Switch Error: Failed to update OLD purchase {$original_purchase_id}."); } }
                                else { error_log("Orunk Stripe Webhook Switch Warning: Missing original_purchase_id in metadata for switch action. PI: {$transaction_id}"); }
                            }
                        }
                        break;

                    case 'payment_intent.payment_failed':
                        // --- Add similar final fallback using PI ID ---
                        $paymentIntent = $event->data->object;
                        $transaction_id = $paymentIntent->id; // PI ID
                        $purchase_id = null; // Reset
                        $metadata = $paymentIntent->metadata ? $paymentIntent->metadata->toArray() : [];
                        $invoice_id = $paymentIntent->invoice ?? null;
                        $gateway_sub_id = $paymentIntent->subscription ?? null;

                        // 1. Check PI Metadata
                        $purchase_id = $metadata['orunk_purchase_id'] ?? null;
                        // 2. DB Lookup using Subscription ID (if available on PI)
                        if (empty($purchase_id) && !empty($gateway_sub_id)) {
                             $purchase_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$purchases_table} WHERE gateway_subscription_id = %s ORDER BY id DESC LIMIT 1", $gateway_sub_id));
                        }
                        // 3. Check Invoice Metadata (Fallback)
                        if (empty($purchase_id) && !empty($invoice_id)) {
                            try { Stripe::setApiKey($secret_key); $invoice_obj = Invoice::retrieve($invoice_id); $invoice_metadata = $invoice_obj->metadata ? $invoice_obj->metadata->toArray() : []; $purchase_id = $invoice_metadata['orunk_purchase_id'] ?? null; } catch (Exception $e) { /* Log silently */ }
                        }
                        // 4. DB Lookup using PI ID (Final Fallback)
                        if (empty($purchase_id) && !empty($transaction_id)) {
                             $purchase_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$purchases_table} WHERE transaction_id = %s ORDER BY id DESC LIMIT 1", $transaction_id));
                        }

                        $purchase_id = $purchase_id ? absint($purchase_id) : 0;
                        $failure_reason = 'Payment Intent Failed.'; if (!empty($paymentIntent->last_payment_error->message)) { $failure_reason = 'Stripe: ' . $paymentIntent->last_payment_error->message; } $failure_code = $paymentIntent->last_payment_error->code ?? 'unknown';
                        error_log("Orunk Stripe Webhook: Handling {$event->type}. PI_ID: {$transaction_id}, Purchase_ID_Found: {$purchase_id}, Reason: {$failure_reason} (Code: {$failure_code})");

                        if (empty($purchase_id)) {
                             error_log("Orunk Stripe Webhook Error ({$event->type}): Missing Purchase ID linkage after all fallbacks. Cannot record failure for PI {$transaction_id}.");
                             return new WP_REST_Response(array('received' => true, 'error' => 'Missing purchase ID linkage'), 200);
                        }

                        // --- Record failure logic (unchanged) ---
                        error_log("Orunk Stripe Webhook: Attempting to record failure for purchase ID {$purchase_id} with reason: {$failure_reason} using Purchase Manager");
                        $failure_result = $this->purchase_manager->record_purchase_failure($purchase_id, $failure_reason, $transaction_id);
                        if (is_wp_error($failure_result)) { error_log("Orunk Stripe Webhook Error ({$event->type}): Failed to record failure for purchase ID {$purchase_id}. Error: " . $failure_result->get_error_message()); }
                        else { error_log("Orunk Stripe Webhook: Failure recorded successfully for purchase ID {$purchase_id}."); }
                        break;


                    // --- Renewal Handling ---
                    case 'invoice.payment_succeeded':
                        // --- Add final fallback using PI ID ---
                        $invoice = $event->data->object;
                        $subscription_id = $invoice->subscription ?? null;
                        $customer_id = $invoice->customer ?? null;
                        $invoice_id = $invoice->id;
                        $amount_paid = ($invoice->amount_paid ?? 0) / 100.0;
                        $currency = $invoice->currency ?? null;
                        $payment_method_id = null;
                        $purchase_id = null;
                        $payment_intent_id_from_invoice = $invoice->payment_intent ?? null; // Get PI ID from invoice

                         // 1. Try Invoice Metadata
                        $invoice_metadata = $invoice->metadata ? $invoice->metadata->toArray() : [];
                        $purchase_id = $invoice_metadata['orunk_purchase_id'] ?? null;
                         // 2. Try Subscription Metadata (if Sub ID present)
                        if (!$purchase_id && $subscription_id) {
                             try { Stripe::setApiKey($secret_key); $sub_obj = Subscription::retrieve($subscription_id); $sub_metadata = $sub_obj->metadata ? $sub_obj->metadata->toArray() : []; $purchase_id = $sub_metadata['orunk_purchase_id'] ?? null; } catch (Exception $e) { /* Log silently */ }
                        }
                         // 3. Try Payment Intent Metadata (if PI ID present on Invoice)
                         if (!$purchase_id && $payment_intent_id_from_invoice) {
                              try { Stripe::setApiKey($secret_key); $pi = PaymentIntent::retrieve($payment_intent_id_from_invoice); $pi_metadata = $pi->metadata ? $pi->metadata->toArray() : []; $purchase_id = $pi_metadata['orunk_purchase_id'] ?? null; $payment_method_id = $pi->payment_method ?? null; } catch (Exception $e) { error_log("Stripe Webhook ({$event->type}): Error retrieving PI {$payment_intent_id_from_invoice}: " . $e->getMessage()); }
                         }
                         // 4. Fallback DB Lookup by Sub ID (if Sub ID present)
                         $last_purchase = null;
                         if ($subscription_id) {
                            $last_purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM $purchases_table WHERE gateway_subscription_id = %s ORDER BY purchase_date DESC LIMIT 1", $subscription_id), ARRAY_A);
                            // If we found the last purchase via Sub ID, make sure purchase_id is set for context later if it was missing
                            if ($last_purchase && empty($purchase_id)) {
                                $purchase_id = $last_purchase['id'];
                                error_log("Orunk Stripe Webhook ({$event->type}): Identified last purchase ID {$purchase_id} via Sub ID {$subscription_id} DB lookup.");
                            }
                         }
                         // 5. Final Fallback DB Lookup by PI ID (if PI ID present)
                         if (!$last_purchase && empty($purchase_id) && $payment_intent_id_from_invoice) {
                              error_log("Orunk Stripe Webhook ({$event->type}): All metadata/Sub ID lookups failed, trying DB lookup using PI ID {$payment_intent_id_from_invoice}...");
                              $purchase_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$purchases_table} WHERE transaction_id = %s ORDER BY id DESC LIMIT 1", $payment_intent_id_from_invoice));
                              if ($purchase_id) {
                                  error_log("Orunk Stripe Webhook ({$event->type}): Found Purchase ID {$purchase_id} via DB lookup using PI ID.");
                                  // We found the purchase that was just paid, now find the one BEFORE it based on subscription ID to treat as renewal
                                  $temp_sub_id = $wpdb->get_var($wpdb->prepare("SELECT gateway_subscription_id FROM {$purchases_table} WHERE id = %d", $purchase_id));
                                  if ($temp_sub_id) {
                                      $subscription_id = $temp_sub_id; // Ensure sub ID is set
                                      $last_purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM $purchases_table WHERE gateway_subscription_id = %s AND id < %d ORDER BY purchase_date DESC LIMIT 1", $subscription_id, $purchase_id), ARRAY_A);
                                      if ($last_purchase) {
                                          error_log("Orunk Stripe Webhook ({$event->type}): Found prior purchase {$last_purchase['id']} for renewal context using Sub ID from purchase {$purchase_id}.");
                                      } else {
                                          error_log("Orunk Stripe Webhook ({$event->type}): Found purchase {$purchase_id} via PI ID, but couldn't find prior purchase with same Sub ID ({$subscription_id}). Treating as initial activation.");
                                          // It might be the initial activation, handle similar to PI Succeeded
                                          $activation_result = $this->purchase_manager->activate_purchase( $purchase_id, $invoice_id, $amount_paid, $subscription_id, $customer_id, $payment_method_id, $currency );
                                          // ... handle activation result ... (similar to PI succeeded)
                                          break; // Exit switch after handling as initial activation
                                      }
                                  } else {
                                      error_log("Orunk Stripe Webhook ({$event->type}): Found purchase {$purchase_id} via PI ID, but it has no Sub ID stored. Cannot process as renewal.");
                                      $last_purchase = null; // Ensure we don't proceed with renewal logic
                                  }
                              }
                         }
                         // --- End Find Purchase Record ---

                        error_log("Orunk Stripe Webhook: Handling {$event->type}. Sub ID: " . ($subscription_id ?? 'N/A') . ", Invoice ID: " . $invoice_id . ", Purchase ID Context: " . ($last_purchase['id'] ?? $purchase_id ?? 'N/A') . ", Amount: " . $amount_paid);

                        if (!$last_purchase) { error_log("Orunk Stripe Webhook Error ({$event->type}): Could not find related Orunk purchase record context to renew based on available IDs. Cannot process renewal."); break; }
                        $user_id = absint($last_purchase['user_id']); $plan_id = absint($last_purchase['plan_id']);
                        error_log("Orunk Stripe Webhook Renewal: Context from previous purchase ID {$last_purchase['id']} for User $user_id, Plan $plan_id.");

                        // Check for duplicate processing (unchanged)
                        $duplicate_check = $wpdb->get_var($wpdb->prepare("SELECT id FROM $purchases_table WHERE transaction_id = %s AND transaction_type = 'renewal_success'", $invoice_id));
                        if ($duplicate_check) { error_log("Orunk Stripe Webhook Info ({$event->type}): Duplicate event detected for Invoice ID {$invoice_id}. Skipping."); break; }

                        // Initiate and Activate Renewal Record (unchanged)
                        $init_renewal_result = $this->purchase_manager->initiate_purchase( $user_id, $plan_id, 'stripe', 'renewal_success', $last_purchase['id'] );
                        if (is_wp_error($init_renewal_result)) { error_log("Orunk Stripe Webhook Renewal Error: Failed to initiate renewal record for Sub ID {$subscription_id}. Error: " . $init_renewal_result->get_error_message()); break; }
                        $new_renewal_purchase_id = $init_renewal_result;
                        error_log("Orunk Stripe Webhook Renewal: Initiated new renewal record ID {$new_renewal_purchase_id}. Attempting activation...");
                        $sub_id_for_activation = $subscription_id ?: ($last_purchase['gateway_subscription_id'] ?? null);
                        $activation_result = $this->purchase_manager->activate_purchase( $new_renewal_purchase_id, $invoice_id, $amount_paid, $sub_id_for_activation, $customer_id, $payment_method_id, $currency );
                        if (is_wp_error($activation_result)) { error_log("Orunk Stripe Webhook Renewal Error: Failed to activate new renewal purchase ID {$new_renewal_purchase_id}. Error: " . $activation_result->get_error_message()); $this->purchase_manager->record_purchase_failure($new_renewal_purchase_id, 'Failed to activate after successful renewal payment (Stripe Invoice): ' . $activation_result->get_error_message(), $invoice_id); break; }

                        error_log("Orunk Stripe Webhook Renewal: Successfully activated NEW purchase record ID {$new_renewal_purchase_id} using Purchase Manager.");
                        break;

                    // --- Other cases remain unchanged ---
                    case 'invoice.payment_failed':
                         /* ... same logic ... */
                         $invoice = $event->data->object; $subscription_id = $invoice->subscription ?? null; $invoice_id = $invoice->id; $failure_reason = 'Stripe renewal payment failed.'; if (!empty($invoice->last_payment_error->message)) { $failure_reason = 'Stripe: ' . $invoice->last_payment_error->message; } error_log("Orunk Stripe Webhook: Handling invoice.payment_failed. Sub ID: " . ($subscription_id ?? 'N/A') . ", Invoice ID: {$invoice_id}, Reason: {$failure_reason}");
                         if (!$subscription_id) { error_log("Orunk Stripe Webhook Error (invoice.payment_failed): Missing subscription ID."); break; } $last_active_purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM $purchases_table WHERE gateway_subscription_id = %s AND status = 'active' ORDER BY purchase_date DESC LIMIT 1", $subscription_id), ARRAY_A); if (!$last_active_purchase) { error_log("Orunk Stripe Webhook Error (invoice.payment_failed): No active purchase found for Sub ID {$subscription_id}."); break; } $user_id = absint($last_active_purchase['user_id']); $plan_id = absint($last_active_purchase['plan_id']); $init_failure_result = $this->purchase_manager->initiate_purchase( $user_id, $plan_id, 'stripe', 'renewal_failure', $last_active_purchase['id'] ); if (is_wp_error($init_failure_result)) { error_log("Orunk Stripe Webhook Renewal Failure Error: Failed to initiate failure record for Sub ID {$subscription_id}. Error: " . $init_failure_result->get_error_message()); break; } $failed_renewal_purchase_id = $init_failure_result; error_log("Orunk Stripe Webhook Renewal Failure: Initiated failure record ID {$failed_renewal_purchase_id}. Recording details..."); $failure_recorded = $this->purchase_manager->record_purchase_failure( $failed_renewal_purchase_id, $failure_reason, $invoice_id ); if (is_wp_error($failure_recorded)) { error_log("Orunk Stripe Webhook Renewal Failure Error: Failed to record failure details for record ID {$failed_renewal_purchase_id}. Error: " . $failure_recorded->get_error_message()); } else { error_log("Orunk Stripe Webhook Renewal Failure: Successfully recorded failure for record ID {$failed_renewal_purchase_id} using Purchase Manager."); } $updated_prev_count = $wpdb->update( $purchases_table, ['status' => 'cancelled', 'auto_renew' => 0], ['id' => $last_active_purchase['id'], 'status' => 'active'], ['%s', '%d'], ['%d', '%s'] ); if ($updated_prev_count > 0) { error_log("Orunk Stripe Webhook Renewal Failure: Marked previous active purchase ID {$last_active_purchase['id']} as cancelled."); } else { error_log("Orunk Stripe Webhook Renewal Failure Warning: Could not mark previous active purchase ID {$last_active_purchase['id']} as cancelled (Rows updated: {$updated_prev_count})."); }
                        break;

                    case 'customer.subscription.deleted':
                         /* ... same logic ... */
                         $subscription = $event->data->object; $subscription_id = $subscription->id; $cancellation_reason = $subscription->cancellation_details->reason ?? 'Cancelled via Stripe'; $cancellation_comment = $subscription->cancellation_details->comment ?? null; $effective_at = $subscription->cancel_at_period_end ? $subscription->current_period_end : time(); error_log("Orunk Stripe Webhook: Handling customer.subscription.deleted for Sub ID: $subscription_id. Reason: {$cancellation_reason}. Effective: " . date('Y-m-d H:i:s', $effective_at)); $purchase_to_cancel = $wpdb->get_row($wpdb->prepare( "SELECT id, status, user_id FROM $purchases_table WHERE gateway_subscription_id = %s ORDER BY purchase_date DESC LIMIT 1", $subscription_id ), ARRAY_A); if ($purchase_to_cancel) { $purchase_id_to_cancel = $purchase_to_cancel['id']; $user_id_for_cancel_hook = $purchase_to_cancel['user_id']; $new_status = 'cancelled'; $update_data = ['status' => $new_status, 'auto_renew' => 0]; if ($cancellation_reason) $update_data['cancellation_reason'] = sanitize_text_field($cancellation_reason . ($cancellation_comment ? " - Comment: ".$cancellation_comment : '')); if ($subscription->cancel_at_period_end) $update_data['cancellation_effective_date'] = gmdate('Y-m-d H:i:s', $subscription->current_period_end); else $update_data['cancellation_effective_date'] = gmdate('Y-m-d H:i:s', $effective_at); $update_formats = $this->purchase_manager->get_db_formats($update_data); $updated = $wpdb->update( $purchases_table, $update_data, ['id' => $purchase_id_to_cancel], $update_formats, ['%d'] ); if ($updated === false) { error_log("Orunk Stripe Webhook (Sub Deleted) Error: DB error updating status for Purchase ID {$purchase_id_to_cancel}. Sub ID {$subscription_id}. Error: " . $wpdb->last_error); } elseif ($updated > 0) { error_log("Orunk Stripe Webhook (Sub Deleted): Updated status to '{$new_status}' for Purchase ID {$purchase_id_to_cancel}."); do_action('orunk_plan_cancelled', $purchase_id_to_cancel, $user_id_for_cancel_hook); } else { error_log("Orunk Stripe Webhook (Sub Deleted) Warning: No rows updated for Purchase ID {$purchase_id_to_cancel}. Status might have been already 'cancelled'."); } } else { error_log("Orunk Stripe Webhook (Sub Deleted) Warning: No Orunk purchase found for cancelled Stripe Subscription ID {$subscription_id}."); }
                        break;

                    default:
                        // Log unhandled events but don't treat as an error
                        if (!in_array($event->type, [ /* Harmless events */
                            'payment_intent.created', 'invoice.created', 'invoice.finalized',
                            'customer.subscription.updated', 'invoice.updated', 'charge.succeeded',
                            'payment_method.attached', 'invoice.paid'
                            ])) {
                             error_log('Orunk Stripe Webhook: Received potentially important but unhandled event type: ' . ($event->type ?? 'Unknown'));
                        }
                } // End switch ($event->type)

            } catch (Exception $e) {
                 error_log('Orunk Stripe Webhook FATAL Error during event processing (' . ($event->type ?? 'Unknown') . '): ' . $e->getMessage() . ' | Event ID: ' . ($event->id ?? 'N/A'));
                 return new WP_REST_Response(array('received' => true, 'error' => 'Internal server error during processing'), 200);
            }

            // Return a 200 response to Stripe to acknowledge receipt
            error_log('Orunk Stripe Webhook: Event processed (' . ($event->type ?? 'Unknown') . '). ID: ' . ($event->id ?? 'N/A') . '. Sending 200 OK.');
            return new WP_REST_Response(array('received' => true), 200);
        } // End handle_stripe_event

    } // End Orunk_Webhook_Handler Class

} // End if (!class_exists(...