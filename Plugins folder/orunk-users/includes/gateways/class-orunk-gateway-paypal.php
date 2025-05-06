<?php
/**
 * PayPal Payment Gateway Class for Orunk Users
 *
 * Handles PayPal payments using the PayPal Server SDK v1+ components.
 * Uses a redirect flow.
 * Handles both one-time payments (Orders API v2) and recurring subscriptions (Subscriptions API v1 via direct HTTP calls).
 * Retrieves PayPal Plan ID from Orunk Plan details stored in the database.
 *
 * @package OrunkUsers\Gateways
 * @version 1.4.0
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// --- Use statements for PayPal SDK Components ---
use PayPalHttp\HttpException;
use PayPalHttp\HttpRequest; // Needed for crafting generic requests
use PayPalHttp\HttpResponse; // Potentially useful for type hinting
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest; // Still needed for one-time payments

// Ensure the abstract class is loaded
if (!class_exists('Orunk_Payment_Gateway')) {
    // Define ORUNK_USERS_PLUGIN_DIR if it's not already set
    if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
        define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path(__FILE__) . '../../');
    }
    $abstract_path = ORUNK_USERS_PLUGIN_DIR . 'includes/abstract-orunk-payment-gateway.php';
    if (file_exists($abstract_path)) {
        require_once $abstract_path;
    } else {
        error_log("Orunk PayPal Gateway Error: Abstract Payment Gateway class file missing at $abstract_path.");
        return; // Cannot proceed without the base class
    }
}

class Orunk_Gateway_Paypal extends Orunk_Payment_Gateway {

    /** @var string PayPal API Client ID */
    public $client_id;
    /** @var string PayPal API Secret */
    public $secret;
    /** @var string Environment ('sandbox' or 'live') */
    public $environment;
    /** @var PayPalHttpClient PayPal API Client */
    protected $client = null;

    public function __construct() {
        $this->id                 = 'paypal';
        $this->method_title       = __('PayPal', 'orunk-users');
        $this->method_description = __('Accept payments via PayPal. Redirects customers to PayPal to complete their purchase.', 'orunk-users');
        $this->icon               = defined('ORUNK_USERS_PLUGIN_URL') ? ORUNK_USERS_PLUGIN_URL . 'assets/images/paypal-logo.png' : ''; // Optional: Add a PayPal logo image

        // Call parent constructor AFTER setting $this->id
        parent::__construct();

        // Load settings
        $this->client_id   = $this->get_option('client_id');
        $this->secret      = $this->get_option('secret');
        $this->environment = $this->get_option('environment', 'sandbox'); // Default to sandbox

         // Disable gateway if credentials are missing
         if (empty($this->client_id) || empty($this->secret)) {
              $this->enabled = 'no';
              if (is_admin()) {
                    error_log('Orunk PayPal Gateway: Disabled because Client ID or Secret is missing in settings.');
              }
         } else {
             // Initialize the PayPal HTTP Client here if credentials exist
             $this->init_paypal_client();
         }
    }

    /**
     * Initialize the PayPal HTTP Client based on settings.
     * (Function unchanged)
     */
    protected function init_paypal_client() {
        if ($this->client_id && $this->secret) {
             // Ensure underlying HTTP client library classes are available
             if (!class_exists('PayPalCheckoutSdk\Core\PayPalHttpClient') || !class_exists('PayPalHttp\HttpRequest')) {
                  error_log('Orunk PayPal Gateway Error: PayPal SDK Core/HTTP classes not found. Check Composer installation and autoloading.');
                  $this->client = null;
                  $this->enabled = 'no'; // Disable if SDK missing
                  return;
             }
            try {
                if ($this->environment === 'live') {
                    $environment = new ProductionEnvironment($this->client_id, $this->secret);
                } else {
                    $environment = new SandboxEnvironment($this->client_id, $this->secret);
                }
                // $this->client is an instance of PayPalHttpClient
                $this->client = new PayPalHttpClient($environment);
            } catch (Exception $e) {
                 error_log('Orunk PayPal Gateway: Exception during PayPal client initialization - ' . $e->getMessage());
                 $this->client = null;
                 $this->enabled = 'no'; // Disable on error
            }
        } else {
            $this->client = null; // Ensure client is null if no credentials
        }
    }


    /**
     * Define admin settings form fields for PayPal.
     * (Function unchanged)
     */
    public function init_form_fields() {
        $webhook_path = 'orunk-webhooks/v1/paypal'; // Define your desired webhook path

        $this->form_fields = array(
            'enabled' => array( /* ... */ 'title' => __('Enable/Disable', 'orunk-users'), 'type' => 'checkbox', 'label' => __('Enable PayPal Payment', 'orunk-users'), 'default' => 'no', 'description' => __('Allow customers to pay using PayPal.', 'orunk-users'), ),
            'title' => array( /* ... */ 'title' => __('Title', 'orunk-users'), 'type' => 'text', 'description' => __('Title shown to customer during checkout.', 'orunk-users'), 'default' => __('PayPal', 'orunk-users'), 'desc_tip' => true, ),
            'description' => array( /* ... */ 'title' => __('Description', 'orunk-users'), 'type' => 'textarea', 'description' => __('Description shown to customer during checkout below the title.', 'orunk-users'), 'default' => __('Pay via PayPal; you can pay with your PayPal account or credit card.', 'orunk-users') ),
            'api_details' => array( /* ... */ 'title' => __('API Credentials', 'orunk-users'), 'type' => 'title', 'description' => sprintf(__('Enter your PayPal REST API credentials. Find these in your %sPayPal Developer Dashboard%s.', 'orunk-users'), '<a href="https://developer.paypal.com/developer/applications/" target="_blank" rel="noopener noreferrer">', '</a>'), ),
            'environment' => array( /* ... */ 'title' => __('Environment', 'orunk-users'), 'type' => 'select', 'description' => __('Select Sandbox for testing or Live for production payments.', 'orunk-users'), 'default' => 'sandbox', 'options' => array( 'sandbox' => __('Sandbox', 'orunk-users'), 'live' => __('Live', 'orunk-users'), ), 'desc_tip' => true, ),
            'client_id' => array( /* ... */ 'title' => __('Client ID', 'orunk-users'), 'type' => 'text', 'description' => __('Your PayPal API Client ID.', 'orunk-users'), 'default' => '', 'custom_attributes' => array('required' => 'required') ),
            'secret' => array( /* ... */ 'title' => __('Secret', 'orunk-users'), 'type' => 'password', 'description' => __('Your PayPal API Secret. Keep this secure.', 'orunk-users'), 'default' => '', 'custom_attributes' => array('required' => 'required') ),
            'webhook_setup' => array( /* ... */ 'title' => __('Webhook Setup', 'orunk-users'), 'type' => 'title', 'description' => __('You must configure a webhook in your PayPal Developer Dashboard to receive payment confirmations and subscription updates.', 'orunk-users') . '<br><br>' . __('Webhook URL (replace YOUR_SITE.com):', 'orunk-users') . '<br><code>' . esc_html(home_url('/')) . 'wp-json/' . $webhook_path . '</code>' . '<br>' . __('Subscribe to events like:', 'orunk-users') . ' <code>CHECKOUT.ORDER.APPROVED</code>, <code>PAYMENT.CAPTURE.COMPLETED</code>, <code>PAYMENT.CAPTURE.DENIED</code>' . ', <code>BILLING.SUBSCRIPTION.ACTIVATED</code>, <code>PAYMENT.SALE.COMPLETED</code>, <code>BILLING.SUBSCRIPTION.CANCELLED</code>, <code>BILLING.SUBSCRIPTION.PAYMENT.FAILED</code>' . '<br>' . __('You also need to add the Webhook ID below for verification.', 'orunk-users') ),
            'webhook_id' => array( /* ... */ 'title' => __('Webhook ID', 'orunk-users'), 'type' => 'text', 'description' => __('Enter the Webhook ID from your PayPal REST App settings. Required for verifying payment notifications.', 'orunk-users'), 'default' => '', 'desc_tip' => true, 'custom_attributes' => array('required' => 'required') ),
        );
    }

    /**
     * Process the payment via PayPal redirect flow.
     * Handles both one-time payments (Orders API v2) and recurring subscriptions (Subscriptions API v1 via direct HTTP).
     * Retrieves PayPal Plan ID from Orunk Plan details.
     *
     * @param int $purchase_id The ID of the pending purchase record.
     * @return array Result array with 'result' ('success'/'failure') and 'redirect' URL.
     */
    public function process_payment($purchase_id) {
        global $wpdb;

        // Check if PayPal client was initialized
        if (!$this->client) {
            error_log('Orunk PayPal Error (Purchase ID: ' . $purchase_id . '): PayPal client not initialized. Check credentials or SDK.');
            return ['result' => 'failure', 'message' => __('PayPal gateway is not configured correctly. Please contact support.', 'orunk-users')];
        }

        // Load required classes if not already available
        if (!class_exists('Custom_Orunk_DB')) {
             error_log('Orunk PayPal Error (Purchase ID: ' . $purchase_id . '): Custom_Orunk_DB class missing.');
             return ['result' => 'failure', 'message' => __('Database component missing.', 'orunk-users')];
        }
        $orunk_db = new Custom_Orunk_DB();

        // 1. Get Purchase and Plan Details
        $purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orunk_user_purchases WHERE id = %d", $purchase_id), ARRAY_A);
        if (!$purchase || !isset($purchase['plan_id'])) {
             error_log('Orunk PayPal Error (Purchase ID: ' . $purchase_id . '): Purchase record not found or invalid.');
            return ['result' => 'failure', 'message' => __('Purchase record not found or invalid.', 'orunk-users')];
        }
        $plan = $orunk_db->get_plan_details($purchase['plan_id']);
        if (!$plan || !isset($plan['price'])) {
             error_log('Orunk PayPal Error (Purchase ID: ' . $purchase_id . '): Plan details not found or invalid.');
            return ['result' => 'failure', 'message' => __('Plan details not found or invalid.', 'orunk-users')];
        }

        $is_one_time = isset($plan['is_one_time']) && $plan['is_one_time'] == 1;
        $plan_price = floatval($plan['price']);
        $plan_name = $plan['plan_name'] ?? 'Orunk Plan';
        $currency = get_option('orunk_currency', 'USD');
        $plan_feature_key = $plan['product_feature_key'] ?? 'unknown';

        // 2. Prepare Common Data (Unchanged)
        $confirmation_nonce = wp_create_nonce('orunk_order_confirmation_' . $purchase_id);
        $return_url = add_query_arg([ 'orunk_payment_status' => 'paypal_success', 'purchase_id' => $purchase_id, '_wpnonce' => $confirmation_nonce ], home_url('/order-confirmation/'));
        $cancel_url = add_query_arg([ 'orunk_payment_status' => 'paypal_cancelled', 'purchase_id' => $purchase_id ], home_url('/checkout/?plan_id=' . $purchase['plan_id']));

        $approval_url = null;
        $paypal_transaction_id = null;
        $api_error_message = null;

        // 3. --- Conditional Logic: One-Time vs Subscription ---
        if ($is_one_time) {
            // --- ONE-TIME PAYMENT (Orders API v2 - Unchanged) ---
            error_log("Orunk PayPal: Processing ONE-TIME payment for Purchase ID {$purchase_id}, Plan ID {$plan['id']}.");
            if ($plan_price <= 0) { return ['result' => 'failure', 'message' => __('PayPal cannot process free plans.', 'orunk-users')]; }
            $order_payload = [ 'intent' => 'CAPTURE', 'purchase_units' => [[ 'amount' => [ 'currency_code' => strtoupper($currency), 'value' => number_format($plan_price, 2, '.', ''), ], 'description' => substr($plan_name . ' - ' . ($plan['description'] ?? $plan_feature_key), 0, 127), 'custom_id' => (string) $purchase_id, 'soft_descriptor' => substr(preg_replace('/[^a-zA-Z0-9\s-]/', '', get_bloginfo('name')), 0, 22), ]], 'application_context' => [ 'return_url' => $return_url, 'cancel_url' => $cancel_url, 'brand_name' => get_bloginfo('name'), 'shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW', ] ];
            try {
                if (!class_exists('PayPalCheckoutSdk\Orders\OrdersCreateRequest')) { throw new Exception('PayPal SDK OrdersCreateRequest class not found.'); }
                $request = new OrdersCreateRequest(); $request->prefer('return=representation'); $request->body = $order_payload;
                $response = $this->client->execute($request); $result = $response->result; $paypal_transaction_id = $result->id; // Order ID
                error_log('Orunk PayPal: One-Time Order created successfully. PayPal Order ID: ' . $paypal_transaction_id . ' for Purchase ID: ' . $purchase_id);
                if (!empty($result->links)) { foreach ($result->links as $link) { if (isset($link->rel) && strtoupper($link->rel) === 'APPROVE') { $approval_url = $link->href; break; } } }
                if (!$approval_url) { throw new Exception('Could not find PayPal approval URL in the order response.'); }
            } catch (HttpException $e) { $error_body = $e->getMessage(); $error_details = json_encode($e->result); $api_error_message = "PayPal API Error ({$e->statusCode}): {$error_body} | Details: {$error_details}"; error_log("Orunk PayPal HttpException (One-Time Order - Purchase ID: $purchase_id): " . $api_error_message);
            } catch (Exception $e) { $api_error_message = $e->getMessage(); error_log("Orunk PayPal General Exception (One-Time Order - Purchase ID: $purchase_id): " . $api_error_message); }

        } else {
            // --- RECURRING PAYMENT (Subscriptions API v1 via Direct HTTP) ---
            error_log("Orunk PayPal: Processing RECURRING payment for Purchase ID {$purchase_id}, Plan ID {$plan['id']}.");
            if ($plan_price <= 0) { return ['result' => 'failure', 'message' => __('PayPal cannot process free subscriptions.', 'orunk-users')]; }

            // --- 3.1 Get PayPal Plan ID from Orunk Plan data ---
            $paypal_plan_id = $plan['paypal_plan_id'] ?? null; // Use value from DB

            if (empty($paypal_plan_id)) {
                 error_log("Orunk PayPal Subscription Error: PayPal Plan ID is not set for Orunk Plan ID {$plan['id']} in WordPress admin/database.");
                 return ['result' => 'failure', 'message' => __('PayPal Plan ID not configured for this plan in WordPress settings.', 'orunk-users')];
            }
            error_log("Orunk PayPal: Using PayPal Plan ID '{$paypal_plan_id}' for Orunk Plan ID {$plan['id']}.");

            // --- 3.2 Construct Subscription Payload ---
            $subscription_payload = [
                'plan_id' => $paypal_plan_id,
                'custom_id' => (string) $purchase_id, // Link back to Orunk purchase
                'application_context' => [
                    'brand_name' => get_bloginfo('name'),
                    'locale' => str_replace('_', '-', get_locale()),
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'payment_method' => [
                        'payer_selected' => 'PAYPAL',
                        'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED'
                    ],
                    'return_url' => $return_url,
                    'cancel_url' => $cancel_url
                ]
                // Optional: Add subscriber details if needed/available
                 // 'subscriber' => [ 'email_address' => '...', 'name' => [ 'given_name' => '...', 'surname' => '...' ] ],
            ];

            try {
                 // --- 3.3 Make Direct API Call using paypalhttp Client ---
                 // Path for the v1 Subscriptions API
                 $api_path = '/v1/billing/subscriptions';

                 // Use the HttpRequest class from paypalhttp
                 if (!class_exists('PayPalHttp\HttpRequest')) {
                    throw new Exception('PayPal SDK HttpRequest class not found.');
                 }

                 $request = new HttpRequest($api_path, 'POST');
                 $request->headers['Content-Type'] = 'application/json';
                 // Add Prefer header to get full representation back
                 $request->headers['Prefer'] = 'return=representation';
                 // Add a unique request ID for idempotency (optional but recommended)
                 $request->headers['PayPal-Request-Id'] = 'orunk-' . $purchase_id . '-' . time();
                 // Set the body
                 $request->body = $subscription_payload; // paypalhttp client usually handles json_encode

                 error_log("Orunk PayPal Subscription: Sending API request to {$api_path} for Purchase ID {$purchase_id}.");
                 // Execute the request using the initialized $this->client
                 $response = $this->client->execute($request);

                 // Check status code (execute should throw HttpException on non-2xx)
                 if ($response->statusCode == 201) { // 201 Created for subscriptions
                     $result = $response->result; // Result is likely already an object
                     $paypal_transaction_id = $result->id ?? null; // PayPal Subscription ID

                     if (!$paypal_transaction_id) {
                          throw new Exception('Subscription ID missing in PayPal response.');
                     }
                     error_log('Orunk PayPal: Subscription created successfully via HTTP. PayPal Subscription ID: ' . $paypal_transaction_id . ' for Purchase ID: ' . $purchase_id);

                     // Find the 'approve' link
                     if (!empty($result->links)) {
                         foreach ($result->links as $link) {
                             if (isset($link->rel) && strtoupper($link->rel) === 'APPROVE') {
                                 $approval_url = $link->href;
                                 break;
                             }
                         }
                     }
                     if (!$approval_url) { throw new Exception('Could not find PayPal approval URL in the subscription response.'); }

                 } else {
                     // This part might not be reached if execute throws HttpException
                     throw new Exception("PayPal API returned unexpected status code: {$response->statusCode}");
                 }

             } catch (HttpException $e) {
                 $error_body = $e->getMessage(); // Includes status code and message
                  // Attempt to decode the result for more details
                  $error_details_str = '';
                  if (property_exists($e, 'result') && $e->result) {
                     if (is_string($e->result)) {
                         $decoded_result = json_decode($e->result, true);
                         $error_details_str = json_encode($decoded_result ?: $e->result); // Encode again if decoding fails or it wasn't json
                     } else {
                         $error_details_str = json_encode($e->result);
                     }
                  } elseif(method_exists($e, 'getMessage') && strpos($e->getMessage(), '{') !== false) {
                      // Try to extract JSON from the main message if result is empty
                      $error_details_str = $e->getMessage();
                  }
                 $api_error_message = "PayPal API Error ({$e->statusCode}): {$error_body} | Details: {$error_details_str}";
                 error_log("Orunk PayPal HttpException (Subscription - Purchase ID: $purchase_id): " . $api_error_message);
             } catch (Exception $e) {
                 $api_error_message = $e->getMessage();
                 error_log("Orunk PayPal General Exception (Subscription - Purchase ID: $purchase_id): " . $api_error_message);
             }
        } // End else (recurring)

        // 4. Handle Final Result (Common for both flows - Unchanged)
        if ($approval_url && $paypal_transaction_id) {
            $column_to_update = $is_one_time ? 'transaction_id' : 'gateway_subscription_id';
            $updated = $wpdb->update( $wpdb->prefix . 'orunk_user_purchases', [$column_to_update => $paypal_transaction_id], ['id' => $purchase_id], ['%s'], ['%d'] );
            if ($updated === false) { error_log("Orunk PayPal Warning: Failed to store PayPal " . ($is_one_time ? "Order" : "Subscription") . " ID {$paypal_transaction_id} for Purchase ID {$purchase_id}. DB Error: " . $wpdb->last_error); }
            else { error_log("Orunk PayPal: Stored PayPal " . ($is_one_time ? "Order" : "Subscription") . " ID {$paypal_transaction_id} for Purchase ID {$purchase_id}. Redirecting user."); }
            return [ 'result' => 'success', 'redirect' => $approval_url ];
        } else {
            // Record failure in our DB if API call failed before redirect
            if ($purchase_id && class_exists('Custom_Orunk_Purchase_Manager')) {
                 $pm = new Custom_Orunk_Purchase_Manager();
                 $fail_reason = 'PayPal API call failed: ' . ($api_error_message ?: 'Unknown SDK/HTTP error');
                 $pm->record_purchase_failure($purchase_id, $fail_reason, $paypal_transaction_id);
            }
            return [ 'result' => 'failure', 'message' => __('Could not initiate PayPal payment. Please try again later or contact support.', 'orunk-users') . ($api_error_message ? ' (Ref: PP-ERR)' : ''), ];
        }
    } // End process_payment

    /**
     * Handle Webhook (Placeholder - Actual logic is in Orunk_PayPal_Webhook_Handler)
     * (Function unchanged)
     */
    public function handle_webhook() {
        error_log('Orunk_Gateway_Paypal::handle_webhook called - This should normally be handled by Orunk_PayPal_Webhook_Handler.');
    }

} // End Class Orunk_Gateway_Paypal