<?php
/**
 * Template Name: Orunk Order Confirmation (Dynamic v3.1.0 - Server-Driven Transition)
 * Template Post Type: page
 *
 * Displays dynamic order confirmation details using reference styling.
 * Shows "Processing Payment" state until a page refresh confirms success via PHP status check.
 * Uses the headline "Order Confirmed – Your Plan is Now Active" on success.
 * Includes confetti and content reveal animation triggered on successful page load.
 *
 * @package YourThemeName
 * @version 3.1.0
 */

// --- Security & Setup ---
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Redirect non-logged-in users
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// Check for required Orunk Users DB class
if (!class_exists('Custom_Orunk_DB') || !class_exists('Custom_Orunk_Core')) {
    get_header();
    echo '<div class="orunk-container orunk-error">Required Orunk Users component missing.</div>';
    get_footer();
    exit;
}

// --- Get Data from URL and Verify (PHP Logic Unchanged) ---
global $wpdb;
$user_id                = get_current_user_id();
$purchase_id            = isset($_GET['purchase_id']) ? absint($_GET['purchase_id']) : 0;
$nonce                  = isset($_GET['_wpnonce']) ? sanitize_key($_GET['_wpnonce']) : '';
$payment_status_trigger = isset($_GET['orunk_payment_status']) ? sanitize_key($_GET['orunk_payment_status']) : 'unknown';

// Instantiate core class
$orunk_core = new Custom_Orunk_Core();

// Variables for display logic
$purchase               = null;
$error_message          = null;
$confirmation_details   = null;
$show_processing_message= false; // Shows spinner/refreshing state
$show_success_message   = false; // Indicates we are on a success path (might still be processing)
$is_pending_bank        = ($payment_status_trigger === 'pending_bank');
$bank_instructions      = null;
$plan_snapshot          = null;
$feature_category       = 'unknown';
$feature_name           = 'Unknown Product';
$headline               = ''; // Initialize headline
$display_message        = ''; // Initialize display message (subtitle)
$product_label          = __('Product Name:', 'orunk-users');
$show_plan_name         = true;
$next_steps_items       = [];

// --- Verify Nonce ---
if ($purchase_id <= 0 || !wp_verify_nonce($nonce, 'orunk_order_confirmation_' . $purchase_id)) {
    $error_message = __('Invalid confirmation link or request expired. Please check your dashboard for order status.', 'orunk-users');
    $headline = __('Error', 'orunk-users');
} else {
    // --- Fetch Purchase Details ---
    $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
    $plans_table = $wpdb->prefix . 'orunk_product_plans';
    $products_table = $wpdb->prefix . 'orunk_products';

    $purchase = $wpdb->get_row($wpdb->prepare(
        "SELECT p.*, pl.plan_name, pr.category AS feature_category, pr.product_name AS feature_name
         FROM {$purchases_table} p
         LEFT JOIN {$plans_table} pl ON p.plan_id = pl.id
         LEFT JOIN {$products_table} pr ON p.product_feature_key = pr.feature
         WHERE p.id = %d AND p.user_id = %d",
        $purchase_id, $user_id
    ), ARRAY_A);

    if (!$purchase) {
        $error_message = __('Order not found or you do not have permission to view it.', 'orunk-users');
        $headline = __('Order Not Found', 'orunk-users');
    } else {
        $current_status = $purchase['status'] ?? 'unknown';
        $feature_category = $purchase['feature_category'] ?? 'unknown';
        $feature_name = $purchase['feature_name'] ?? $purchase['product_feature_key'] ?? __('Unknown Product', 'orunk-users');

        if (!empty($purchase['plan_details_snapshot'])) {
            $plan_snapshot = json_decode($purchase['plan_details_snapshot'], true);
             if ($feature_name === 'Unknown Product' && isset($plan_snapshot['product_name'])) {
                  $feature_name = $plan_snapshot['product_name'];
             }
        }

        // --- Determine Display State ---
        $is_success_trigger = in_array($payment_status_trigger, ['success', 'success_free', 'paypal_success', 'processing']);
        $is_pending_in_db = $current_status === 'Pending Payment';
        $is_active_in_db = $current_status === 'active';

        if ($is_success_trigger) {
            $show_success_message = true; // On success path
            if ($is_pending_in_db) {
                 $show_processing_message = true; // Still Processing
                 // Headline for processing state (matching reference style)
                 $headline = __('Processing your payment', 'orunk-users');
                 $display_message = __("We're verifying your payment details", 'orunk-users');
                 error_log("Order Confirmation: Displaying PROCESSING state for Purchase ID {$purchase_id}. Meta refresh scheduled.");
            } else if ($is_active_in_db) {
                 $show_processing_message = false; // Completed
                 // --- UPDATED: Headline for confirmed state ---
                 $headline = __('Order Confirmed – Your Plan is Now Active', 'orunk-users');
                 $display_message = ''; // No subtitle needed for this message
                 // Define next steps based on category (Logic remains the same)
                 switch ($feature_category) {
                     case 'api-service': $product_label = __('Service Name:', 'orunk-users'); $next_steps_items = [ ['icon' => 'fa-key', 'text' => __('API Keys – Find your credentials in your account dashboard', 'orunk-users')], ['icon' => 'fa-book', 'text' => __('Documentation – Check our comprehensive guides for setup', 'orunk-users')], ['icon' => 'fa-headset', 'text' => __('Support – Priority support available for all purchases', 'orunk-users')], ]; break;
                     case 'wordpress-plugin': case 'wordpress-theme': $product_label = ($feature_category === 'wordpress-plugin') ? __('Plugin Name:', 'orunk-users') : __('Theme Name:', 'orunk-users'); $next_steps_items = [ ['icon' => 'fa-envelope', 'text' => __('Instant Access – Download links and license keys sent to your email', 'orunk-users'), 'badge' => 'New'], ['icon' => 'fa-key', 'text' => __('License Keys – Also available in your account dashboard', 'orunk-users')], ['icon' => 'fa-book', 'text' => __('Documentation – Check our comprehensive guides for setup', 'orunk-users')], ['icon' => 'fa-plug', 'text' => __('Installation – Upload ZIP file via WordPress admin', 'orunk-users')], ['icon' => 'fa-headset', 'text' => __('Support – Priority support available for all purchases', 'orunk-users')], ]; break;
                     case 'ad_removal': $headline = __('Ad Removal Activated!', 'orunk-users'); $product_label = __('Product:', 'orunk-users'); $show_plan_name = false; $next_steps_items = [ ['icon' => 'fa-shield-alt', 'text' => __('Ad-Free Experience – All ads have been disabled automatically', 'orunk-users')], ['icon' => 'fa-check-circle', 'text' => __('Instant Activation – No further action is required', 'orunk-users')], ['icon' => 'fa-cog', 'text' => __('Manage Add-ons – Visit your plugin settings in WordPress', 'orunk-users')], ['icon' => 'fa-headset', 'text' => __('Support – Priority help included with your purchase', 'orunk-users')], ]; break;
                     default: $product_label = __('Feature Name:', 'orunk-users'); $next_steps_items = [ ['icon' => 'fa-check-circle', 'text' => __('Feature Activated – Your access has been granted.', 'orunk-users')], ['icon' => 'fa-tachometer-alt', 'text' => __('Go to Dashboard – Manage your account and purchases.', 'orunk-users')], ['icon' => 'fa-headset', 'text' => __('Support – Contact us if you have any questions.', 'orunk-users')], ]; break;
                 }
                 if ($payment_status_trigger === 'success_free') {
                      $headline = __('Plan Activated!', 'orunk-users'); // Keep specific free headline
                      $display_message = '';
                 }
                 error_log("Order Confirmation: Displaying FINAL success state for Purchase ID {$purchase_id}.");
            } else {
                 // Handle potential conflict: Show final success but log warning
                 $show_processing_message = false;
                 // --- UPDATED: Headline for confirmed state (even in conflict) ---
                 $headline = __('Order Confirmed – Your Plan is Now Active', 'orunk-users');
                 $display_message = '';
                 error_log("Order Confirmation: Conflicting state for Purchase ID {$purchase_id}. Showing final success layout but DB status is '{$current_status}'. Check webhook/processing logic.");
                 $next_steps_items = [ ['icon' => 'fa-tachometer-alt', 'text' => __('Go to Dashboard – Manage your account and purchases.', 'orunk-users')], ['icon' => 'fa-headset', 'text' => __('Support – Contact us if you have any questions.', 'orunk-users')], ];
            }

             // Prepare Details for Order Summary (If purchase exists)
             if($purchase) {
                 $amount_display = 'N/A';
                 if (!is_null($purchase['amount_paid']) && is_numeric($purchase['amount_paid'])) {
                      $amount_display = '$' . number_format((float)$purchase['amount_paid'], 2);
                 } elseif ($plan_snapshot && isset($plan_snapshot['price']) && is_numeric($plan_snapshot['price'])) {
                      $amount_display = '$' . number_format((float)$plan_snapshot['price'], 2) . '*';
                 }
                 $confirmation_details = [
                    'order_id' => $purchase['id'],
                    'plan_name' => $purchase['plan_name'] ?? ($plan_snapshot['plan_name'] ?? 'N/A'),
                    'feature_key' => $purchase['product_feature_key'] ?? ($plan_snapshot['product_feature_key'] ?? 'N/A'),
                    'feature_name' => $feature_name,
                    'feature_category' => $feature_category,
                    'purchase_date' => $purchase['purchase_date'] ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($purchase['purchase_date'])) : 'N/A',
                    'amount_paid' => $amount_display,
                    'transaction_id' => $purchase['transaction_id'] ?? 'N/A',
                 ];
             }

        } // End success trigger check
        elseif ($is_pending_bank && $is_pending_in_db) {
            // Handle pending bank state (PHP Logic Unchanged)
            $headline = __('Order Pending Bank Transfer', 'orunk-users');
            $display_message = __('Your order has been received. Please use the details below to complete your payment.', 'orunk-users');
            $bank_instructions = get_transient('orunk_purchase_message_' . $user_id);
            delete_transient('orunk_purchase_message_' . $user_id);
            if (!$bank_instructions) { $bank_instructions = __('Please check your email or contact support for bank transfer details. Use Purchase ID %d as reference.', 'orunk-users'); }
            $bank_instructions = sprintf($bank_instructions, $purchase_id);
            $show_processing_message = false;
            $show_success_message = false;
        } elseif (in_array($current_status, ['failed', 'cancelled', 'expired'])) {
             // Handle failed/cancelled/expired state (PHP Logic Unchanged)
             if ($current_status === 'failed') { $error_message = __('Your payment failed.', 'orunk-users'); if (!empty($purchase['failure_reason'])) $error_message .= ' Reason: ' . esc_html($purchase['failure_reason']); }
             else { $error_message = __('This order is no longer active.', 'orunk-users'); }
             $headline = __('Order Issue', 'orunk-users');
             $show_processing_message = false;
             $show_success_message = false;
        } elseif ($payment_status_trigger === 'paypal_cancelled') {
             // Handle PayPal cancelled state (PHP Logic Unchanged)
             $error_message = __('Your PayPal payment was cancelled. Your order was not completed.', 'orunk-users');
             $headline = __('Payment Cancelled', 'orunk-users');
             $show_processing_message = false;
             $show_success_message = false;
        } else {
            // Handle unknown state (PHP Logic Unchanged)
            $error_message = __('There was an issue retrieving your order details. Please check your dashboard.', 'orunk-users');
            $headline = __('Unknown Order Status', 'orunk-users');
            error_log("Order Confirmation: Unknown state for Purchase ID {$purchase_id}. Status trigger: '{$payment_status_trigger}', DB status: '{$current_status}'. Showing fallback error.");
            $show_processing_message = false;
            $show_success_message = false;
        }
    } // End main else block (nonce verified)
}

// --- Start HTML Output ---
get_header();
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($headline); ?> - Order Confirmation</title>
    <?php // --- RESTORED: Meta refresh added back conditionally --- ?>
    <?php if ($show_processing_message): ?>
        <meta http-equiv="refresh" content="7;url=<?php echo esc_url(add_query_arg( array_merge($_GET, ['refresh_count' => (isset($_GET['refresh_count']) ? intval($_GET['refresh_count']) + 1 : 1)]), get_permalink($purchase_id ? get_the_ID() : null) )); ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- Reference Styles Merged (Unchanged from previous step) --- */
        @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.05); opacity: 0.8; } }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes checkmark { 0% { stroke-dashoffset: 50; } 100% { stroke-dashoffset: 0; } }
        @keyframes confetti { 0% { transform: translateY(0) rotate(0deg); opacity: 1; } 100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; } }
        @keyframes progress { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        @keyframes fadeIn { 0% { opacity: 0; transform: translateY(10px); } 100% { opacity: 1; transform: translateY(0); } }

        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }

        .confirmation-wrapper { max-width: 42rem; width: 100%; background: white; border-radius: 0.75rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -4px rgba(0,0,0,.1); overflow: hidden; position: relative; margin: 3rem auto; border: 1px solid #e5e7eb; }

        .state-header { padding: 1.5rem; color: white; transition: background 0.8s ease-out; }
        #processing-state { background: linear-gradient(to right, #4f46e5, #7c3aed); }
        #confirmed-state { background: linear-gradient(to right, #10b981, #059669); }

        .state-header .header-content { display: flex; align-items: center; justify-content: space-between; }
        .state-header h1 { font-size: 1.5rem; font-weight: 700; color: white !important; margin: 0; }
        .state-header p { margin-top: 0.25rem; margin-bottom: 0; }
        #processing-state p { color: #e0e7ff; }
        #confirmed-state p { color: #d1fae5; }
        .state-header .icon-container { background: rgba(255, 255, 255, 0.2); border-radius: 9999px; padding: 1rem; display: flex; align-items: center; justify-content: center; }
        #processing-state .icon-container { animation: pulse 1.5s infinite ease-in-out; }
        #confirmed-state .icon-container { transition: all 0.6s cubic-bezier(0.68, -0.55, 0.27, 1.55); }

        .processing-icon { font-size: 1.5rem; animation: spin 1.5s linear infinite; }
        .checkmark-icon { width: 2rem; height: 2rem; }
        .checkmark-icon path { stroke: white; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 50; stroke-dashoffset: 50; animation: checkmark 0.6s ease-out forwards; animation-delay: 0.2s; }

        .progress-bar { height: 4px; margin-top: 1rem; border-radius: 9999px; background: linear-gradient(90deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0.8) 50%, rgba(255,255,255,0.3) 100%); background-size: 200% 100%; animation: progress 2s linear infinite; }

        .content-section { max-height: 0; overflow: hidden; transition: max-height 0.8s cubic-bezier(0.68, -0.55, 0.27, 1.55), opacity 0.6s ease; opacity: 0; }
        .content-section.visible { max-height: 1500px; opacity: 1; }
        .content-padding { padding: 1.5rem; }

        .order-details-grid { display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); gap: 1rem; }
        @media (min-width: 768px) { .order-details-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .order-details-item { background-color: #f9fafb; padding: 1rem; border-radius: 0.5rem; border: 1px solid #f3f4f6; }
        .order-details-item dt { display: block; font-size: 0.875rem; color: #6b7280; margin-bottom: 0.125rem; }
        .order-details-item dd { display: block; font-weight: 500; color: #1f2937; font-size: 0.875rem; word-break: break-all; }
        .order-details-item code { font-size: 0.9em; background-color: #e5e7eb; padding: 2px 4px; border-radius: 3px; }
        .amount-note { font-size: 0.75em; color: #9ca3af; margin-left: 2px; }

        .next-steps-heading { display: flex; align-items: center; margin-bottom: 1rem; font-size: 1.125rem; font-weight: 600; color: #1f2937; }
        .next-steps-heading i { color: #10b981; margin-right: 0.5rem; }
        .dashboard-link { display: inline-flex; align-items: center; background: rgba(79, 70, 229, 0.05); color: #4f46e5; padding: 0.375rem 0.75rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 500; text-decoration: none; transition: all 0.2s ease; border: 1px solid rgba(79, 70, 229, 0.2); margin-left: 0.75rem; }
        .dashboard-link:hover { background: rgba(79, 70, 229, 0.1); border-color: rgba(79, 70, 229, 0.3); }
        .dashboard-link i { margin-right: 0.375rem; font-size: 0.75rem; }
        .next-steps-list { margin-top: 1rem; }
        .next-steps-item { display: flex; align-items: flex-start; padding: 0.75rem 0; border-bottom: 1px solid rgba(79, 70, 229, 0.1); transition: all 0.3s ease; line-height: 1.5; }
        .next-steps-item:last-child { border-bottom: none; }
        .next-steps-item:hover { transform: translateX(5px); }
        .next-steps-icon { margin-right: 0.75rem; margin-top: 0.15rem; color: #4f46e5; background-color: rgba(79, 70, 229, 0.1); border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .next-steps-icon i { line-height: 1; font-size: 0.75rem; }
        .next-steps-text { color: #1f2937; font-size: 0.875rem; }
        .next-steps-text .font-medium { font-weight: 500; }
        .feature-badge { background: rgba(79, 70, 229, 0.1); color: #4f46e5; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; margin-left: 0.5rem; display: inline-flex; align-items: center; animation: float 3s ease-in-out infinite; }
        .feature-badge i { margin-right: 0.25rem; font-size: 0.625rem; }

        .help-section { background-color: #f0fdf4; border-radius: 0.5rem; padding: 1rem; margin-top: 2rem; animation: fadeIn 0.6s ease-out forwards; animation-delay: 0.5s; }
        .help-section .help-content { display: flex; align-items: flex-start; }
        .help-icon-container { background-color: #d1fae5; border-radius: 9999px; padding: 0.5rem; margin-right: 0.75rem; display: flex; align-items: center; justify-content: center; }
        .help-icon-container i { color: #059669; }
        .help-text h3 { font-weight: 500; color: #1f2937; margin-bottom: 0.25rem; }
        .help-text p { font-size: 0.875rem; color: #4b5563; }

        .confetti { position: absolute; width: 10px; height: 10px; background-color: #f00; opacity: 0; top: 0; }

        .fade-in { animation: fadeIn 0.6s ease-out forwards; }
        .hidden { display: none; }

        .fallback-icon-container { display: flex; justify-content: center; margin-bottom: 1.5rem; }
        .fallback-icon { background-color: #f3f4f6; border-radius: 9999px; padding: 1rem; color: #9ca3af; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); }
        .fallback-icon i { font-size: 2em; }
        .fallback-title { text-align: center; font-size: 1.75rem; font-weight: 700; color: #1f2937; margin-top: 0; margin-bottom: 0.75rem; }
        .fallback-message { text-align: center; color: #4b5563; margin-bottom: 1.5rem; line-height: 1.6; }
        .fallback-actions { text-align: center; margin-top: 1rem; }
        .orunk-error { border: 1px solid #fecaca; background: #fef2f2; color: #b91c1c; text-align: left; padding: 1rem; margin-bottom: 1rem; border-radius: 0.5rem; }
        .orunk-error strong { color: #991b1b; }
        .bank-instructions { text-align: left; background: #f8fafc; border: 1px dashed #e2e8f0; border-radius: 0.5rem; padding: 1rem; font-size: 0.85rem; color: #475569; white-space: pre-wrap; line-height: 1.5; margin-top: 1.5rem; margin-bottom: 1.5rem; }
        .orunk-button { display: inline-block; padding: 0.6rem 1.2rem; background-color: #4f46e5; color: white; border-radius: 6px; text-decoration: none; font-weight: 500; transition: background-color 0.2s ease; }
        .orunk-button:hover { background-color: #4338ca; }

    </style>
    <?php wp_head(); ?>
</head>
<body <?php body_class('bg-gray-50 min-h-screen flex items-center justify-center p-4'); ?>> <?php // Match reference body styling ?>

<div id="primary" class="content-area w-full"> <?php // Ensure primary takes width ?>
    <main id="main" class="site-main">

        <?php // Main container matching reference ?>
        <div class="confirmation-wrapper">

            <?php // --- ERROR STATE --- ?>
            <?php if ($error_message) : ?>
                 <div class="content-padding"> <?php // Use consistent padding ?>
                     <div class="fallback-icon-container">
                         <div class="fallback-icon bg-red-100 text-red-500"> <i class="fas fa-times-circle fa-2x"></i> </div>
                     </div>
                     <h1 class="fallback-title"><?php echo esc_html($headline); ?></h1>
                     <div class="fallback-message orunk-error !text-center !mb-4"><?php echo esc_html($error_message); ?></div>
                     <div class="fallback-actions">
                         <a href="<?php echo esc_url(home_url('/orunk-dashboard/')); ?>" class="orunk-button"><?php esc_html_e('Go to Dashboard', 'orunk-users'); ?></a>
                         <a href="<?php echo esc_url(home_url('/orunk-catalog/')); ?>" class="orunk-button" style="margin-left: 10px; background-color: #6b7280;"><?php esc_html_e('View Plans', 'orunk-users'); ?></a>
                     </div>
                 </div>

            <?php // --- PENDING BANK TRANSFER STATE --- ?>
            <?php elseif ($is_pending_bank) : ?>
                 <div class="content-padding"> <?php // Use consistent padding ?>
                      <div class="fallback-icon-container">
                         <div class="fallback-icon bg-yellow-100 text-yellow-500"> <i class="fas fa-university fa-2x"></i> </div>
                     </div>
                     <h1 class="fallback-title"><?php echo esc_html($headline); ?></h1>
                     <p class="fallback-message"><?php echo esc_html($display_message); ?></p>
                     <?php if ($bank_instructions): ?> <div class="bank-instructions"><?php echo nl2br(esc_html($bank_instructions)); ?></div> <?php endif; ?>
                     <div class="fallback-actions"> <a href="<?php echo esc_url(home_url('/orunk-dashboard/')); ?>" class="orunk-button"><?php esc_html_e('Go to Dashboard', 'orunk-users'); ?></a> </div>
                  </div>

            <?php // --- PROCESSING STATE (Rendered by PHP if $show_processing_message is true) --- ?>
            <?php elseif ($show_processing_message) : ?>
                 <div id="processing-state" class="state-header"> <?php // Visible ?>
                    <div class="header-content">
                        <div>
                            <h1 class="text-2xl font-bold"><?php echo esc_html($headline); // Processing headline ?></h1>
                            <p class="text-indigo-200 mt-1"><?php echo esc_html($display_message); // Processing subtitle ?></p>
                            <div class="progress-bar mt-4 rounded-full"></div>
                        </div>
                        <div class="icon-container pulse">
                            <i class="fas fa-circle-notch text-2xl spin processing-icon"></i>
                        </div>
                    </div>
                 </div>
                 <?php // Content section is NOT rendered while processing ?>

            <?php // --- SUCCESS STATE (Rendered by PHP if !$show_processing_message && $show_success_message) --- ?>
            <?php elseif (!$show_processing_message && $show_success_message && $confirmation_details) : ?>
                 <?php // Processing state is hidden ?>
                 <div id="processing-state" class="state-header hidden">...</div>

                 <?php // Confirmed state is visible ?>
                <div id="confirmed-state" class="state-header"> <?php // Visible ?>
                    <div class="header-content">
                        <div>
                            <h1 class="text-2xl font-bold"><?php echo esc_html($headline); // Confirmed headline ?></h1>
                            <p class="text-green-100 mt-1"><?php echo esc_html($display_message); // Confirmed subtitle ?></p>
                        </div>
                        <div class="icon-container">
                            <svg class="checkmark-icon" viewBox="0 0 24 24" fill="none">
                                <path d="M5 13L9 17L19 7" class="checkmark-animation"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <?php // --- Content Section (JS will make this visible) --- ?>
                <div id="content-section" class="content-section">
                    <div class="content-padding">

                        <?php // === Order Details Grid === ?>
                        <div class="mb-8">
                             <h2 class="text-lg font-semibold text-gray-800 mb-4">Order Details</h2>
                             <div class="order-details-grid">
                                 <div class="order-details-item">
                                     <dt><?php esc_html_e('Order ID', 'orunk-users'); ?></dt>
                                     <dd>#<?php echo esc_html($confirmation_details['order_id']); ?></dd>
                                 </div>
                                 <div class="order-details-item">
                                     <dt><?php esc_html_e('Date', 'orunk-users'); ?></dt>
                                     <dd><?php echo esc_html($confirmation_details['purchase_date']); ?></dd>
                                 </div>
                                 <?php if ($show_plan_name): ?>
                                 <div class="order-details-item">
                                     <dt><?php esc_html_e('Plan', 'orunk-users'); ?></dt>
                                     <dd><?php echo esc_html($confirmation_details['plan_name']); ?></dd>
                                 </div>
                                 <?php endif; ?>
                                 <div class="order-details-item">
                                      <dt><?php echo esc_html($product_label); ?></dt>
                                     <dd><?php echo esc_html($confirmation_details['feature_name']); ?></dd>
                                 </div>
                                 <div class="order-details-item">
                                     <dt><?php esc_html_e('Amount Paid:', 'orunk-users'); ?></dt>
                                     <dd>
                                          <?php echo esc_html(str_replace('*', '', $confirmation_details['amount_paid'])); ?>
                                          <?php if (strpos($confirmation_details['amount_paid'], '*') !== false): ?>
                                             <span class="amount-note" title="<?php esc_attr_e('Plan price shown, actual amount may differ slightly or update shortly.', 'orunk-users'); ?>">*</span>
                                          <?php endif; ?>
                                     </dd>
                                 </div>
                                 <?php if (!empty($confirmation_details['transaction_id']) && $confirmation_details['transaction_id'] !== 'N/A' && strpos($confirmation_details['transaction_id'], 'free_checkout') === false && strpos($confirmation_details['transaction_id'], 'manual_') === false ): ?>
                                  <div class="order-details-item">
                                     <dt><?php esc_html_e('Transaction ID:', 'orunk-users'); ?></dt>
                                     <dd><code><?php echo esc_html($confirmation_details['transaction_id']); ?></code></dd>
                                  </div>
                                  <?php endif; ?>
                             </div>
                        </div>

                         <?php // What's Next? Section ?>
                         <?php if (!empty($next_steps_items)): ?>
                         <div class="mb-8">
                             <div class="next-steps-heading">
                                 <i class="fas fa-rocket"></i> <h2><?php esc_html_e("What's Next?", 'orunk-users'); ?></h2>
                                 <a href="<?php echo esc_url(home_url('/orunk-dashboard/')); ?>" class="dashboard-link fade-in" style="animation-delay: 0.1s">
                                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                                 </a>
                             </div>
                             <div class="next-steps-list space-y-1">
                                 <?php foreach ($next_steps_items as $index => $step): ?>
                                 <div class="next-steps-item fade-in" style="animation-delay: <?php echo esc_attr(($index + 2) * 0.1); ?>s">
                                     <div class="next-steps-icon"> <i class="fas <?php echo esc_attr($step['icon']); ?>"></i> </div>
                                     <div class="next-steps-text">
                                         <p>
                                              <?php $text_parts = explode('–', $step['text'], 2); if (count($text_parts) === 2) { echo '<span class="font-medium">' . esc_html(trim($text_parts[0])) . '</span> –' . esc_html(trim($text_parts[1])); } else { echo esc_html($step['text']); } ?>
                                             <?php if (isset($step['badge'])): ?>
                                                 <span class="feature-badge">
                                                     <?php if (strtolower($step['badge']) === 'new'): ?><i class="fas fa-external-link-alt"></i><?php elseif (strtolower($step['badge']) === 'instant'): ?><i class="fas fa-bolt"></i><?php endif; ?>
                                                     <?php echo esc_html($step['badge']); ?>
                                                 </span>
                                             <?php endif; ?>
                                         </p>
                                     </div>
                                 </div>
                                 <?php endforeach; ?>
                             </div>
                         </div>
                         <?php endif; ?>

                        <?php // Additional Help Section ?>
                        <div class="help-section fade-in" style="animation-delay: <?php echo esc_attr((count($next_steps_items) + 2) * 0.1); ?>s">
                            <div class="help-content">
                                <div class="help-icon-container"> <i class="fas fa-question-circle"></i> </div>
                                <div class="help-text">
                                    <h3>Need help?</h3>
                                    <p>Our support team is available 24/7 to assist you with any questions.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php // --- Fallback for Unknown State --- ?>
            <?php else: ?>
                 <div class="content-padding">
                     <div class="fallback-icon-container"> <div class="fallback-icon"> <i class="fas fa-question-circle fa-2x"></i> </div> </div>
                     <h1 class="fallback-title"><?php echo esc_html($headline ?: __('Order Status', 'orunk-users')); ?></h1>
                     <p class="fallback-message"><?php esc_html_e('We could not determine the status of your order. Please check your dashboard or contact support.', 'orunk-users'); ?></p>
                     <div class="fallback-actions"> <a href="<?php echo esc_url(home_url('/orunk-dashboard/')); ?>" class="orunk-button"><?php esc_html_e('Go to Dashboard', 'orunk-users'); ?></a> </div>
                  </div>
            <?php endif; ?>

        </div> <?php // end .confirmation-wrapper ?>
    </main>
</div> <?php // #primary ?>

<?php // --- JavaScript for Confetti and Content Reveal (NO setTimeout transition) --- ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const confirmedState = document.getElementById('confirmed-state');
        const contentSection = document.getElementById('content-section');
        const confirmationWrapper = document.querySelector('.confirmation-wrapper'); // Target main container for confetti

        // Check if the page loaded in the confirmed state (based on PHP rendering)
        const isConfirmedOnLoad = confirmedState && !confirmedState.classList.contains('hidden');

        console.log('Order Confirmation Page Loaded. State:', '<?php echo $show_processing_message ? "Processing (Refresh Pending)" : ($error_message ? "Error" : ($is_pending_bank ? "Pending Bank" : ($show_success_message ? "Success" : "Unknown"))); ?>');

        if (isConfirmedOnLoad) {
            // If page loaded directly in confirmed state (after refresh or for free plan)
            createConfetti(); // Trigger confetti

            if (contentSection) {
                 // Make content visible immediately (CSS handles fade-in animation)
                 // Use a tiny delay just to ensure rendering completes before animation starts
                 setTimeout(() => {
                     contentSection.classList.add('visible');
                 }, 50); // Minimal delay
            }
        }
        // No JS needed for the processing state display - PHP handles that.

        // Confetti function from reference (Unchanged)
        function createConfetti() {
            if (!confirmationWrapper) return;

            const colors = ['#4ade80', '#60a5fa', '#fbbf24', '#f472b6', '#a78bfa'];
            const confettiCount = 50;

            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.position = 'absolute';
                confetti.style.top = '0';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.width = Math.random() * 8 + 4 + 'px';
                confetti.style.height = confetti.style.width;
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.opacity = '0';
                confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                confetti.style.animation = `confetti ${Math.random() * 3 + 2}s ease-out forwards`;
                confetti.style.animationDelay = Math.random() * 0.5 + 's';

                confirmationWrapper.appendChild(confetti);

                setTimeout(() => {
                    if (confetti) confetti.remove();
                }, 5000);
            }
        }
    });
</script>

<?php
get_footer();
?>