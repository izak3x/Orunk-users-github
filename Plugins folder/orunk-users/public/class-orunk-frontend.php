<?php
/**
 * Orunk Users Frontend Class
 *
 * Handles frontend display logic via template loading and displays confirmation messages.
 * ADDED: Default callbacks for dynamic dashboard card rendering using filters.
 * ADDED: Helper function to determine manage plan button visibility.
 *
 * @package OrunkUsers\Public
 * @version 1.2.2 // Version reflects added helper function
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Custom_Orunk_Frontend {

    /** @var Custom_Orunk_Access Access control handler */
    private $access;

    /** @var Custom_Orunk_Core Core logic handler */
    private $core;


    /**
     * Constructor.
     */
    public function __construct() {
        // Instantiate handlers (ensure classes are available)
        // Ensure dependencies are defined/loaded BEFORE requiring them
        if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
             // Attempt to define relative to this file if not already set
             $plugin_dir_path = plugin_dir_path(dirname(__FILE__, 2)); // Go up two directories
             if (is_dir($plugin_dir_path)) {
                  define('ORUNK_USERS_PLUGIN_DIR', $plugin_dir_path);
             } else {
                 error_log('CRITICAL Orunk Frontend Error: Could not determine ORUNK_USERS_PLUGIN_DIR.');
                 return; // Cannot proceed without base directory
             }
        }

        if (!class_exists('Custom_Orunk_Access')) {
            $access_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-access.php';
             if (file_exists($access_path)) { require_once $access_path; }
             else { error_log("Orunk Frontend Error: Cannot load Custom_Orunk_Access from $access_path"); }
        }
        if (!class_exists('Custom_Orunk_Core')) {
            $core_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-core.php';
            if (file_exists($core_path)) { require_once $core_path; }
            else { error_log("Orunk Frontend Error: Cannot load Custom_Orunk_Core from $core_path"); }
        }

        // Only instantiate if classes exist
        if (class_exists('Custom_Orunk_Access')) {
            $this->access = new Custom_Orunk_Access();
        } else { $this->access = null; }

        if (class_exists('Custom_Orunk_Core')) {
            $this->core   = new Custom_Orunk_Core();
        } else { $this->core = null; }
    }

    /**
     * Initialize frontend hooks.
     * MODIFIED: Added hooks for dashboard card rendering.
     */
    public function init() {
        // Filter to load custom page templates from the theme directory
        add_filter('template_include', array($this, 'load_custom_templates'));

        // Action to display purchase confirmation messages stored in transients (e.g., bank details)
        add_action('wp_footer', array($this, 'display_purchase_confirmation_message'));

        // Enqueue checkout assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_assets'));

        // *** ADDED: Hooks for default dashboard card rendering (low priority 20) ***
        add_action('orunk_dashboard_service_card_header', [$this, 'render_default_card_header'], 20, 3);
        add_action('orunk_dashboard_service_card_body', [$this, 'render_default_card_body'], 20, 2);
        // *** END ADDED ***

        // Enqueue frontend scripts/styles if needed (Original commented out code)
        // add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /* Optional: Enqueue scripts/styles (Original commented out code)
    public function enqueue_scripts() {
        // Example: wp_enqueue_style('orunk-frontend-css', ORUNK_USERS_PLUGIN_URL . 'public/css/frontend.css', array(), ORUNK_USERS_VERSION);
        // Example: wp_enqueue_script('orunk-frontend-js', ORUNK_USERS_PLUGIN_URL . 'public/js/frontend.js', array('jquery'), ORUNK_USERS_VERSION, true);
    }
    */

    /**
     * Loads custom page templates from the active theme directory if they exist for specific page slugs.
     * (Original function - unchanged)
     * @param string $template The original template path determined by WordPress.
     * @return string The potentially modified template path.
     */
    public function load_custom_templates($template) {
        // Define the mapping of page slugs to template filenames in the theme
        $template_map = array(
            'orunk-signup' => 'page-orunk-signup.php',
            'orunk-login' => 'page-orunk-login.php',
            'orunk-catalog' => 'page-orunk-catalog.php',
            'orunk-dashboard' => 'page-orunk-dashboard.php',
        );

        if (is_page()) {
            $queried_object = get_queried_object();
            if ($queried_object instanceof WP_Post) {
                 $page_slug = $queried_object->post_name;
                if (isset($template_map[$page_slug])) {
                    $theme_template = get_stylesheet_directory() . '/' . $template_map[$page_slug];
                    if (file_exists($theme_template)) {
                        return $theme_template;
                    }
                }
            }
        }
        return $template;
    }

    /**
     * Enqueues scripts and styles specifically for the checkout page.
     * (Original function - unchanged)
     */
    public function enqueue_checkout_assets() {
        if (is_page_template('page-checkout.php') || is_page('checkout')) {

            // CSS
            wp_enqueue_style( 'orunk-checkout-style', ORUNK_USERS_PLUGIN_URL . 'assets/css/orunk-checkout-style.css', array(), defined('ORUNK_USERS_VERSION') ? ORUNK_USERS_VERSION : '1.0' );

            // Stripe JS (Conditionally)
            $stripe_publishable_key = null; $plan_price = 0.00; $plan_id_to_purchase = isset($_GET['plan_id']) ? absint($_GET['plan_id']) : 0;
            if (class_exists('Custom_Orunk_Core')) { $temp_core = new Custom_Orunk_Core(); if ($plan_id_to_purchase > 0) { $plan = $temp_core->get_plan_details($plan_id_to_purchase); if($plan) { $plan_price = floatval($plan['price']); } } $gateways = $temp_core->get_available_payment_gateways(); if (isset($gateways['stripe'])) { $stripe_publishable_key = $gateways['stripe']->get_option('publishable_key'); } }
            $setup_dependencies = array('jquery');
            if (!empty($stripe_publishable_key) && $plan_price > 0) { wp_enqueue_script('stripe-v3', 'https://js.stripe.com/v3/', array(), null, true); $setup_dependencies[] = 'stripe-v3'; }

            // Setup Script
            wp_enqueue_script( 'orunk-checkout-setup', ORUNK_USERS_PLUGIN_URL . 'assets/js/checkout-setup.js', $setup_dependencies, defined('ORUNK_USERS_VERSION') ? ORUNK_USERS_VERSION : '1.0', true );
            wp_localize_script( 'orunk-checkout-setup', 'orunkCheckoutData', [ 'ajaxUrl' => admin_url('admin-ajax.php'), 'processPaymentNonce' => wp_create_nonce('orunk_process_payment_nonce'), 'createIntentNonce' => wp_create_nonce('orunk_process_payment_nonce'), 'planPrice' => $plan_price, 'isFreePlan' => ($plan_price <= 0), 'stripePublishableKey'=> $stripe_publishable_key, ] );

            // Validation Script
            wp_enqueue_script( 'orunk-checkout-validation', ORUNK_USERS_PLUGIN_URL . 'assets/js/checkout-validation.js', array('orunk-checkout-setup'), defined('ORUNK_USERS_VERSION') ? ORUNK_USERS_VERSION : '1.0', true );

            // Payment Script
            wp_enqueue_script( 'orunk-checkout-payment', ORUNK_USERS_PLUGIN_URL . 'assets/js/checkout-payment.js', array('orunk-checkout-validation'), defined('ORUNK_USERS_VERSION') ? ORUNK_USERS_VERSION : '1.0', true );
        }
    }

     /**
      * Displays purchase confirmation messages (like bank details) stored in transients.
      * Hooked into wp_footer.
      * (Original function - unchanged)
      */
     public function display_purchase_confirmation_message() {
         if (!is_user_logged_in()) return;
         $user_id = get_current_user_id(); $message_transient_key = 'orunk_purchase_message_' . $user_id; $message = get_transient($message_transient_key);
         if ($message) { ?> <div id="orunk-purchase-confirmation-notice" style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); max-width: 90%; width: 500px; background: #f0fff0; color: #3c763d; border: 1px solid #d6e9c6; border-radius: 5px; padding: 15px 20px; text-align: left; z-index: 10000; box-shadow: 0 2px 10px rgba(0,0,0,0.1);"> <p style="margin: 0; font-size: 0.9em; line-height: 1.5;"><?php echo wp_kses_post($message); ?></p> <button onclick="this.parentElement.style.display='none';" style="position: absolute; top: 8px; right: 8px; background: transparent; border: none; font-size: 1.4em; line-height: 1; cursor: pointer; color: #aaa;">&times;</button> </div> <?php delete_transient($message_transient_key); }
     }

     // *** ADDED: Default Card Rendering Functions ***

     /**
      * Renders the default header for a service card on the dashboard.
      * Hooked to 'orunk_dashboard_service_card_header' with lower priority (20).
      * Checks filter 'orunk_dashboard_handled_header_features' to avoid double rendering.
      *
      * @param array  $purchase     Purchase data.
      * @param string $feature_key  Feature key.
      * @param array  $display_info Pre-calculated display info (icon, title).
      */
     public function render_default_card_header($purchase, $feature_key, $display_info) {
         // Check if a specific handler has already claimed this feature via filter
         $handled_features = apply_filters('orunk_dashboard_handled_header_features', []);
         if (in_array($feature_key, $handled_features)) {
             // error_log("Orunk Frontend: Default header rendering skipped for handled feature '{$feature_key}'."); // Optional debug log
             return; // Do nothing if handled elsewhere
         }
         // error_log("Orunk Frontend: Rendering DEFAULT header for feature '{$feature_key}'."); // Optional debug log

         // --- Render Default Header ---
         $plan_name = $purchase['plan_name'] ?? __('N/A', 'orunk-users');
         $default_icon = $display_info['icon'] ?? 'fa-cube'; // Fallback icon
         $default_title = $display_info['title'] ?? ucfirst(str_replace('_', ' ', $feature_key)); // Fallback title
         ?>
         <h4 class="text-base font-semibold flex items-center gap-2 flex-wrap">
             <i class="fas <?php echo esc_attr($default_icon); ?>"></i>
             <span><?php echo esc_html($default_title); ?> - <?php echo esc_html($plan_name); ?></span>
             <?php // Default header doesn't add extra tags unless overridden by a specific feature hook ?>
         </h4>
         <?php
     }

     /**
      * Renders the default body for a service card (API Key, Usage).
      * Hooked to 'orunk_dashboard_service_card_body' with lower priority (20).
      * Checks filter 'orunk_dashboard_handled_body_features' to avoid double rendering.
      *
      * @param array  $purchase     Purchase data.
      * @param string $feature_key  Feature key.
      */
     public function render_default_card_body($purchase, $feature_key) {
         // Check if a specific handler has already claimed this feature via filter
         $handled_features = apply_filters('orunk_dashboard_handled_body_features', []);
         if (in_array($feature_key, $handled_features)) {
             // error_log("Orunk Frontend: Default body rendering skipped for handled feature '{$feature_key}'."); // Optional debug log
             return; // Do nothing if handled elsewhere
         }
         // error_log("Orunk Frontend: Rendering DEFAULT body for feature '{$feature_key}'."); // Optional debug log

         // --- Render Default Body (API Key & Usage) ---
         $api_key = $purchase['api_key'] ?? null;
         $purchase_id = $purchase['id'] ?? 0;
         $is_switch_pending = !empty($purchase['pending_switch_plan_id']); // Need this check for disabling button

         if ($api_key):
             // Only attempt to get usage if the function exists (for BIN API logs etc.)
             $usage = function_exists('orunk_get_usage_data') ? orunk_get_usage_data($api_key, $purchase['plan_requests_per_day'], $purchase['plan_requests_per_month']) : ['error' => 'Usage unavailable'];
             $usage_error = ($usage && isset($usage['error']));
         ?>
             <div class="mb-4">
                 <label class="form-label !text-xs !mb-1">API Key</label>
                 <div class="flex items-center gap-2">
                     <div class="api-key-display flex-grow" id="api-key-display-<?php echo esc_attr($purchase_id); ?>"><?php echo esc_html(substr($api_key, 0, 8) . '...' . substr($api_key, -4)); ?></div>
                     <button class="orunk-button-outline orunk-button-sm orunk-button-icon orunk-copy-button" data-full-key="<?php echo esc_attr($api_key); ?>" title="Copy API Key"><i class="far fa-copy"></i></button>
                     <button class="orunk-button-outline orunk-button-sm orunk-button-icon orunk-regenerate-key-button" data-purchase-id="<?php echo esc_attr($purchase_id); ?>" title="Regenerate API Key" <?php disabled($is_switch_pending); ?>>
                         <i class="fas fa-sync-alt"></i><span class="regenerate-spinner spinner" style="display: none;"></span>
                     </button>
                 </div>
                 <div id="regenerate-feedback-<?php echo esc_attr($purchase_id); ?>" class="text-xs mt-1 h-4"></div>
             </div>

             <?php // Display usage only if no error and usage data exists ?>
             <?php if (!$usage_error && $usage): ?>
                 <div class="space-y-3">
                    <?php // Daily Usage Bar
                    $limit_day_display = $usage['limit_day'] !== null ? number_format_i18n($usage['limit_day']) : '∞';
                    $daily_used_title = number_format_i18n($usage['daily_used'] ?? 0);
                    $daily_limit_title = $usage['limit_day'] !== null ? number_format_i18n($usage['limit_day']) : 'Unlimited';
                    $daily_percent_title = $usage['percent_today'] ?? 0;
                    ?>
                    <div>
                        <div class="flex justify-between items-baseline mb-1">
                            <span class="text-xs font-medium text-gray-600">Daily Usage</span>
                            <span class="text-xs font-medium text-gray-600" title="<?php printf('%s / %s Used', $daily_used_title, $daily_limit_title); ?>"><?php echo $daily_used_title; ?> / <?php echo $limit_day_display; ?> (<?php echo esc_html($daily_percent_title); ?>%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-bar-fill <?php echo $daily_percent_title >= 90 ? 'bg-red' : ($daily_percent_title >= 75 ? 'bg-yellow' : ''); ?>" style="width: <?php echo esc_attr($daily_percent_title); ?>%"></div>
                        </div>
                    </div>
                    <?php // Monthly Usage Bar
                     $limit_month_display = $usage['limit_month'] !== null ? number_format_i18n($usage['limit_month']) : '∞';
                     $monthly_used_title = number_format_i18n($usage['monthly_used'] ?? 0);
                     $monthly_limit_title = $usage['limit_month'] !== null ? number_format_i18n($usage['limit_month']) : 'Unlimited';
                     $monthly_percent_title = $usage['percent_month'] ?? 0;
                     ?>
                    <div>
                        <div class="flex justify-between items-baseline mb-1">
                            <span class="text-xs font-medium text-gray-600">Monthly Usage</span>
                             <span class="text-xs font-medium text-gray-600" title="<?php printf('%s / %s Used', $monthly_used_title, $monthly_limit_title); ?>"><?php echo $monthly_used_title; ?> / <?php echo $limit_month_display; ?> (<?php echo esc_html($monthly_percent_title); ?>%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-bar-fill <?php echo $monthly_percent_title >= 90 ? 'bg-red' : ($monthly_percent_title >= 75 ? 'bg-yellow' : ''); ?>" style="width: <?php echo esc_attr($monthly_percent_title); ?>%"></div>
                        </div>
                    </div>
                 </div>
             <?php elseif ($usage_error): ?>
                  <div class="text-xs text-gray-500 italic pt-1"><i class="fas fa-info-circle mr-1"></i> Usage data unavailable.</div>
             <?php endif; ?>
         <?php else: ?>
              <?php // No API Key found, and not handled by a specific feature hook. Output nothing or a generic message. ?>
         <?php endif; // End check for API key
     }
     // *** END ADDED ***


     // --- START: Added Helper Function for Manage Plan Button ---
    /**
     * Determines if the 'Manage Plan' button should be shown for a purchase.
     *
     * Hides the button if:
     * 1. The current plan is a one-time payment plan.
     * 2. There are no other active plans available for the same feature.
     *
     * @param array $purchase         The purchase details array.
     * @param array $plans_by_feature All available active plans, keyed by feature.
     * @param Custom_Orunk_Core|null $orunk_core The core class instance for DB calls.
     * @return bool True to show the button, false to hide it.
     */
    public function should_show_manage_plan_button($purchase, $plans_by_feature, $orunk_core) {
        $current_plan_id = isset($purchase['plan_id']) ? absint($purchase['plan_id']) : 0;
        $feature_key = $purchase['product_feature_key'] ?? '';

        // If essential data is missing, default to hiding for safety.
        if (empty($current_plan_id) || empty($feature_key) || !$orunk_core) {
            // Optional: Log this scenario for debugging
            // error_log("Orunk Frontend: Hiding Manage button due to missing data. Purchase ID: " . ($purchase['id'] ?? 'N/A') . ", Plan ID: $current_plan_id, Feature: $feature_key, Core: " . ($orunk_core ? 'Yes' : 'No'));
            return false;
        }

        // --- Check Condition 1: Is the current plan one-time? ---
        $current_plan_details = $orunk_core->get_plan_details($current_plan_id);

        // Hide if it's explicitly a one-time plan
        if ($current_plan_details && isset($current_plan_details['is_one_time']) && $current_plan_details['is_one_time'] == 1) {
            // error_log("Orunk Frontend: Hiding Manage button for one-time plan. Purchase ID: " . ($purchase['id'] ?? 'N/A'));
            return false; // Don't show for one-time plans
        }

        // --- Check Condition 2: Are there other active plans available? ---
        $available_plans_for_feature = isset($plans_by_feature[$feature_key]) ? $plans_by_feature[$feature_key] : [];
        $other_available_plans_count = 0;

        foreach ($available_plans_for_feature as $plan) {
            // Count plans that are active and have a DIFFERENT ID than the current one
            if (isset($plan['id']) && $plan['id'] != $current_plan_id && isset($plan['is_active']) && $plan['is_active'] == 1) {
                $other_available_plans_count++;
            }
        }

        // Hide if no *other* active plans exist for this feature
        if ($other_available_plans_count === 0) {
             // error_log("Orunk Frontend: Hiding Manage button because no other plans available for feature '{$feature_key}'. Purchase ID: " . ($purchase['id'] ?? 'N/A'));
            return false;
        }

        // If neither condition to hide was met, show the button
        // error_log("Orunk Frontend: Showing Manage button for Purchase ID: " . ($purchase['id'] ?? 'N/A'));
        return true;
    }
    // --- END: Added Helper Function ---

} // End Class Custom_Orunk_Frontend
?>