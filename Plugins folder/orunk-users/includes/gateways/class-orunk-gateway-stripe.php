<?php
/**
 * Stripe Payment Gateway Class
 * Handles Stripe settings and the legacy Checkout Session flow.
 * The primary AJAX/Payment Element flow is handled in AJAX Handlers and Webhook Handler.
 *
 * @package OrunkUsers\Gateways
 * @version 1.2.2 // PHASE 5 FIX: Removed early call to get_rest_url().
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Ensure the abstract class is loaded
if (!class_exists('Orunk_Payment_Gateway')) {
    $abstract_path = ORUNK_USERS_PLUGIN_DIR . 'includes/abstract-orunk-payment-gateway.php';
    if (file_exists($abstract_path)) { require_once $abstract_path; }
    else { error_log("Orunk Stripe Gateway Error: Abstract Payment Gateway class file missing."); return; }
}

class Orunk_Gateway_Stripe extends Orunk_Payment_Gateway {

    /** @var string Stripe Secret Key */
    public $secret_key;
    /** @var string Stripe Publishable Key */
    public $publishable_key;
    /** @var string Stripe Webhook Signing Secret */
    public $webhook_secret;

    public function __construct() {
        $this->id = 'stripe';
        $this->method_title = __('Stripe', 'orunk-users');
        $this->method_description = __('Accept credit card payments via Stripe using the Payment Element (AJAX + Webhook). Settings configure API keys and webhook signing.', 'orunk-users');
        $this->icon = ''; // Optional: Add Stripe logo URL

        // Call parent constructor AFTER setting $this->id
        parent::__construct();

        // Load keys from settings AFTER parent constructor loads settings
         $this->secret_key = $this->get_option('secret_key');
         $this->publishable_key = $this->get_option('publishable_key');
         $this->webhook_secret = $this->get_option('webhook_secret');

         // Disable gateway if keys are missing
         if (empty($this->secret_key) || empty($this->publishable_key)) {
              $this->enabled = 'no';
              // Only log if admin, avoid logging on every page load for frontend users
              if (is_admin()) {
                    error_log('Orunk Stripe Gateway: Disabled because Secret Key or Publishable Key is missing in settings.');
              }
         }
    }

    /**
     * Define admin settings form fields for Stripe.
     * Ensures all necessary keys and webhook details are included.
     * **FIXED:** Removed get_rest_url() call.
     */
    public function init_form_fields() {
         // --- Define the RELATIVE webhook path ---
         $webhook_path = 'orunk-webhooks/v1/stripe';
         // --- We will construct the full URL hint later in the description ---

         $this->form_fields = array(
             'enabled' => array(
                 'title'       => __('Enable/Disable', 'orunk-users'),
                 'type'        => 'checkbox',
                 'label'       => __('Enable Stripe Payment', 'orunk-users'),
                 'default'     => 'no',
                 'description' => __('Allow customers to pay using Stripe.', 'orunk-users'),
             ),
             'title' => array(
                 'title'       => __('Title', 'orunk-users'),
                 'type'        => 'text',
                 'description' => __('Title shown to customer during checkout.', 'orunk-users'),
                 'default'     => __('Credit Card (Stripe)', 'orunk-users'),
                 'desc_tip'    => true,
             ),
             'description' => array(
                 'title'       => __('Description', 'orunk-users'),
                 'type'        => 'textarea',
                 'description' => __('Description shown to customer during checkout below the title.', 'orunk-users'),
                 'default'     => __('Pay securely with your credit card via Stripe.', 'orunk-users')
             ),
             'api_details' => array(
                 'title'       => __( 'API Credentials', 'orunk-users' ),
                 'type'        => 'title', // Creates a heading
                 'description' => sprintf( __( 'Enter your Stripe API keys. You can find these in your %sStripe Dashboard%s.', 'orunk-users' ), '<a href="https://dashboard.stripe.com/account/apikeys" target="_blank" rel="noopener noreferrer">', '</a>' ),
             ),
             'publishable_key' => array(
                  'title'       => __('Publishable Key', 'orunk-users'),
                  'type'        => 'text',
                  'description' => __('Your Stripe API Publishable Key (starts with pk_...). Required for Stripe Elements.', 'orunk-users'),
                  'default'     => '',
                  'custom_attributes' => array('required' => 'required')
              ),
             'secret_key' => array(
                  'title'       => __('Secret Key', 'orunk-users'),
                  'type'        => 'password',
                  'description' => __('Your Stripe API Secret Key (starts with sk_...). Keep this secure.', 'orunk-users'),
                  'default'     => '',
                  'custom_attributes' => array('required' => 'required')
              ),
               'webhook_secret' => array(
                  'title'       => __('Webhook Signing Secret', 'orunk-users'),
                  'type'        => 'text',
                  'description' => __('Enter your Stripe webhook signing secret (starts with whsec_). This is required to securely verify incoming webhook events from Stripe.', 'orunk-users')
                                    . '<br><br>' . __('You must configure a webhook endpoint in your Stripe Dashboard pointing to (replace YOUR_SITE.com):', 'orunk-users') // Modified instruction
                                    // Display the relative path and instructions on how to construct the full URL
                                    . '<br><code>' . esc_html( home_url( '/' ) ) . 'wp-json/' . $webhook_path . '</code>'
                                    . '<br>' . __('Select the following events to listen for:', 'orunk-users')
                                    . ' <code>payment_intent.succeeded</code>, <code>payment_intent.payment_failed</code>'
                                    // Add subscription events if using Stripe Subscriptions
                                    . ', <code>invoice.payment_succeeded</code>, <code>invoice.payment_failed</code>, <code>customer.subscription.deleted</code>',
                  'default'     => '',
                  'custom_attributes' => array('required' => 'required')
              ),
         );
    }

    /**
     * Process Payment (Legacy Method - Stripe Checkout Session Redirect Flow).
     * NOTE: Not used by the primary AJAX + Payment Element flow.
     * (Method unchanged from previous step)
     */
    public function process_payment($purchase_id, $context = []) {
         global $wpdb;
         if (!class_exists('Custom_Orunk_DB')) { /* ... */ return ['result' => 'failure', 'message' => __('Database component missing.', 'orunk-users')]; }
         $orunk_db = new Custom_Orunk_DB();
         $purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orunk_user_purchases WHERE id = %d", $purchase_id), ARRAY_A);
         if (!$purchase) return ['result' => 'failure', 'message' => __('Purchase record not found.', 'orunk-users')];
         $is_switch = isset($context['type']) && $context['type'] === 'switch';
         $plan_to_purchase_id = $is_switch ? ($context['new_plan_id'] ?? 0) : $purchase['plan_id'];
         if (empty($plan_to_purchase_id)) return ['result' => 'failure', 'message' => __('Target plan ID missing.', 'orunk-users')];
         $plan = $orunk_db->get_plan_details($plan_to_purchase_id);
         if (!$plan) return ['result' => 'failure', 'message' => __('Plan details not found.', 'orunk-users')];
         $user_info = get_userdata($purchase['user_id']);
         $user_email = $user_info ? $user_info->user_email : null;
         if (!class_exists('\Stripe\Stripe')) { /* ... */ return ['result' => 'failure', 'message' => __('Stripe library missing.', 'orunk-users')]; }
         if (empty($this->secret_key)) { /* ... */ return ['result' => 'failure', 'message' => __('Stripe gateway not configured (Missing Secret Key).', 'orunk-users')]; }
         \Stripe\Stripe::setApiKey($this->secret_key);
         $success_url = add_query_arg(['orunk_payment_status' => 'success', 'purchase_id' => $purchase_id], home_url('/orunk-dashboard/'));
         $cancel_url = wp_get_referer() ?: home_url('/orunk-dashboard/');
         $cancel_url = add_query_arg(['orunk_payment_status' => 'cancelled', 'purchase_id' => $purchase_id], $cancel_url);
         $metadata = ['orunk_purchase_id' => $purchase_id];
         if ($is_switch) { $metadata['orunk_switch_to_plan_id'] = $plan_to_purchase_id; $metadata['orunk_action_type'] = 'switch'; $metadata['orunk_original_purchase_id'] = $purchase_id; } else { $metadata['orunk_action_type'] = 'new_purchase'; }
         $metadata['orunk_user_id'] = $purchase['user_id'];
         try {
             $product_name = $is_switch ? sprintf(__('Switch to %s', 'orunk-users'), $plan['plan_name']) : $plan['plan_name'];
             $product_description = $plan['description'] ?? $plan['product_feature_key'];
             $checkout_session_args = [ 'payment_method_types' => ['card'], 'line_items' => [[ 'price_data' => [ 'currency' => strtolower(get_option('orunk_currency', 'usd')), 'product_data' => ['name' => $product_name, 'description' => $product_description], 'unit_amount' => intval(floatval($plan['price']) * 100), ], 'quantity' => 1, ]], 'mode' => 'payment', 'success_url' => $success_url, 'cancel_url' => $cancel_url, 'metadata' => $metadata ];
             if ($user_email) { $checkout_session_args['customer_email'] = $user_email; }
             $checkout_session = \Stripe\Checkout\Session::create($checkout_session_args);
             return ['result' => 'success', 'redirect' => $checkout_session->url];
         } catch (Exception $e) { error_log("Orunk Stripe Error creating legacy checkout session for Purchase ID {$purchase_id}: " . $e->getMessage()); $error_code = ($e instanceof \Stripe\Exception\ApiErrorException) ? $e->getError()->code : ''; return ['result' => 'failure', 'message' => __('Could not initiate payment via Stripe Checkout. Please try again later or contact support.', 'orunk-users') . ($error_code ? " (Error: $error_code)" : '')]; }
     }

} // End Class Orunk_Gateway_Stripe