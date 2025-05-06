<?php
/**
 * Template Name: Orunk User Dashboard (v3.0.6 - Separated JS)
 * Template Post Type: page
 *
 * Modifications:
 * - REMOVED the large inline JavaScript block.
 * - ADDED a small inline script block before get_footer() to define JS constants
 * (orunkDashboardData, orunkAvailablePlans, etc.) needed by external JS files.
 * - Functionality now relies on scripts enqueued via wp_enqueue_scripts
 * (e.g., main.js, profile.js, billing.js, services.js, history.js).
 * - Includes previous mods: Conditional Manage Plan Button, Dynamic Hooks, Categories, Downloads, OTP Flow, History Limit, etc.
 */

// --- Ensure user is logged in ---
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// --- Fetch necessary data ---
$user_id = get_current_user_id();
$current_user = wp_get_current_user();
$orunk_core = null;
$orunk_access = null;
$orunk_frontend = null;
$all_purchases = [];
$plans_by_feature = [];
$plugin_active = false;
$has_ad_removal = false;
$ad_removal_plan_id = null;
$ad_removal_price = '1.99';
$cancel_nonces = [];
$switch_nonces = [];
$active_ad_removal_purchase_id = null;

// Check if the core plugin classes exist and instantiate
if (class_exists('Custom_Orunk_Core') && class_exists('Custom_Orunk_Access')) {
    $orunk_core = new Custom_Orunk_Core();
    $orunk_access = new Custom_Orunk_Access();
    $plugin_active = true;

    if (class_exists('Custom_Orunk_Frontend')) {
         $orunk_frontend = new Custom_Orunk_Frontend();
    }

    // Fetch ALL purchases for the user
    $all_purchases_raw = $orunk_core->get_user_purchases($user_id);

    // Process all purchases (for history and active checks)
    if (!empty($all_purchases_raw)) {
        $now_gmt = current_time('timestamp', 1);
        foreach ($all_purchases_raw as $purchase) {
            // Assign defaults & ensure ID exists
            $purchase_id_for_nonce = $purchase['id'] ?? 0;
            $purchase['id'] = $purchase_id_for_nonce;
            $purchase['status'] = $purchase['status'] ?? 'unknown';
            $purchase['expiry_date'] = $purchase['expiry_date'] ?? null;
            $purchase['product_feature_key'] = $purchase['product_feature_key'] ?? 'unknown';
            $purchase['api_key'] = $purchase['api_key'] ?? null;
            $purchase['license_key'] = $purchase['license_key'] ?? null;
            $purchase['plan_requests_per_day'] = isset($purchase['plan_requests_per_day']) ? (int)$purchase['plan_requests_per_day'] : null;
            $purchase['plan_requests_per_month'] = isset($purchase['plan_requests_per_month']) ? (int)$purchase['plan_requests_per_month'] : null;
            $purchase['plan_name'] = $purchase['plan_name'] ?? __('Unnamed Plan', 'orunk-users');
            $purchase['auto_renew'] = $purchase['auto_renew'] ?? 0;
            $purchase['plan_details_snapshot'] = $purchase['plan_details_snapshot'] ?? null;
            $purchase['feature_category'] = $purchase['feature_category'] ?? 'other';
            $purchase['plan_id'] = $purchase['plan_id'] ?? 0;

            // Check if active for nonce generation and ad removal flag
            $is_active_status = $purchase['status'] === 'active';
            $expiry_timestamp = $purchase['expiry_date'] ? strtotime($purchase['expiry_date']) : null;
            $is_not_expired = ($expiry_timestamp === null || $expiry_timestamp > $now_gmt);

            if ($purchase_id_for_nonce > 0 && $is_active_status && $is_not_expired) {
                $cancel_nonces[$purchase_id_for_nonce] = wp_create_nonce('orunk_cancel_plan_' . $purchase_id_for_nonce);
                $switch_nonces[$purchase_id_for_nonce] = wp_create_nonce('orunk_switch_plan_' . $purchase_id_for_nonce);
                 if (($purchase['product_feature_key'] ?? '') === 'ad_removal') {
                     $has_ad_removal = true;
                     $active_ad_removal_purchase_id = $purchase_id_for_nonce;
                 }
            }
            $all_purchases[] = $purchase;
        }
    }

    // Fetch all features with their ACTIVE plans grouped by feature key
    $all_features_with_active_plans = $orunk_core->get_product_features_with_plans();
    if(!empty($all_features_with_active_plans)) {
        foreach($all_features_with_active_plans as $feature) {
            if(!empty($feature['feature']) && !empty($feature['plans'])) {
                 $active_plans = array_filter($feature['plans'], function($plan) {
                     return isset($plan['is_active']) && $plan['is_active'] == 1;
                 });
                 if (!empty($active_plans)) {
                    $plans_by_feature[$feature['feature']] = array_values($active_plans);
                 } else {
                    $plans_by_feature[$feature['feature']] = [];
                 }

                 if ($feature['feature'] === 'ad_removal') {
                     $ad_removal_plan_id = $active_plans[0]['id'] ?? null;
                     $ad_removal_price = $active_plans[0]['price'] ?? $ad_removal_price;
                 }
            }
        }
    }
} // End if class_exists

// ================================================================
// START: Helper Function Definitions (Keep these PHP helpers)
// ================================================================
if (!function_exists('orunk_get_usage_data')) {
     function orunk_get_usage_data($api_key, $limit_day, $limit_month) {
        global $wpdb; $requests_table = 'bin_api_requests'; static $usage_table_exists = null; if ($usage_table_exists === null) { $usage_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $requests_table)) === $requests_table; } if (!$usage_table_exists) { return ['error' => 'Log table missing']; } try { $count_today = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$requests_table} WHERE api_key = %s AND DATE(request_date) = CURDATE()", $api_key)); $count_month = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$requests_table} WHERE api_key = %s AND request_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", $api_key)); if($count_today === null || $count_month === null) { error_log("Orunk Dashboard: DB error fetching usage for API Key $api_key. Error: " . $wpdb->last_error); return ['error' => 'DB query failed']; } $today = (int) $count_today; $month = (int) $count_month; $limit_day_int = is_numeric($limit_day) ? (int)$limit_day : null; $limit_month_int = is_numeric($limit_month) ? (int)$limit_month : null; $percent_today = ($limit_day_int !== null && $limit_day_int > 0) ? min(100, round(($today / $limit_day_int) * 100)) : 0; $percent_month = ($limit_month_int !== null && $limit_month_int > 0) ? min(100, round(($month / $limit_month_int) * 100)) : 0; return [ 'today' => $today, 'month' => $month, 'limit_day' => $limit_day_int, 'limit_month' => $limit_month_int, 'percent_today' => $percent_today, 'percent_month' => $percent_month, 'daily_used' => $today, 'monthly_used' => $month ]; } catch (Exception $e) { error_log("Orunk Dashboard: Error in orunk_get_usage_data for key $api_key: " . $e->getMessage()); return ['error' => 'Processing error']; }
     }
}
if (!function_exists('orunk_get_feature_display_info')) {
     function orunk_get_feature_display_info($feature_key) {
        $title = ucwords(str_replace(['_', '-api', ' api'], [' ', '', ''], $feature_key)); $icon_class = 'fa-cube text-blue-500'; $bg_class = 'bg-blue-100'; $progress_class = 'bg-blue-500'; $tag_class = 'tag-other'; $border_class = 'border-blue-200'; if ($feature_key === 'convojet_pro') { $icon_class = 'fa-comments text-blue-500'; $bg_class = 'bg-blue-100'; $progress_class = 'bg-blue-500'; $tag_class = 'tag-convojet'; $border_class = 'border-blue-200'; $title = 'Convojet Pro'; } elseif (strpos($feature_key, 'bin') !== false) { $icon_class = 'fa-credit-card text-purple-600'; $bg_class = 'bg-purple-100'; $progress_class = 'bg-purple-500'; $tag_class = 'tag-bin'; $border_class = 'border-purple-200'; $title = 'Bin Lookup'; } elseif (strpos($feature_key, 'identity') !== false) { $icon_class = 'fa-user-secret text-pink-500'; $bg_class = 'bg-pink-100'; $progress_class = 'bg-pink-500'; $tag_class = 'tag-identity'; $border_class = 'border-pink-200'; $title = 'Fake Identity';} elseif (strpos($feature_key, 'finance') !== false) { $icon_class = 'fa-chart-line text-green-600'; $bg_class = 'bg-green-100'; $progress_class = 'bg-green-500'; $tag_class = 'tag-finance'; $border_class = 'border-green-200'; $title = 'Finance Data';} elseif ($feature_key === 'ad_removal') { $icon_class = 'fa-shield-alt text-teal-600'; $bg_class = 'bg-teal-100'; $progress_class = 'bg-teal-500'; $tag_class = 'tag-adfree'; $border_class = 'border-teal-200'; $title = 'Ad Removal';} return ['title' => $title, 'icon' => $icon_class, 'bg' => $bg_class, 'progress' => $progress_class, 'tag' => $tag_class, 'border' => $border_class];
     }
}
// ================================================================
// END: Helper Function Definitions
// ================================================================

// --- Start HTML Output ---
get_header();
?>
<head> <?php // Specific styles/scripts for this template ?>
    <?php // Tailwind is assumed to be loaded globally or via plugin/theme ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php // The dashboard CSS is enqueued via wp_enqueue_style now ?>
</head>
<body <?php body_class('orunk-dashboard bg-gray-50'); ?>>
    <div class="dashboard-container orunk-container">
        <?php // --- Header & Message Area --- ?>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">My Dashboard</h1>
            <p class="text-sm text-gray-500">Manage your services and account</p>
        </div>
        <div id="messages" class="mb-6 space-y-2">
             <?php // Display initial messages rendered by PHP ?>
            <?php if(isset($_GET['orunk_error'])): ?>
                <div class="alert alert-error !text-sm"><i class="fas fa-times-circle"></i> <?php echo esc_html(urldecode(wp_unslash($_GET['orunk_error']))); ?></div>
            <?php endif; ?>
            <?php if(get_transient('orunk_purchase_message_' . $user_id)): ?>
                <?php // Note: This transient is now also read by the inline JS block ?>
                <div class="alert alert-success !text-sm"><i class="fas fa-check-circle"></i> <?php echo esc_html(get_transient('orunk_purchase_message_' . $user_id)); /* Don't delete transient here if JS also needs it */ ?></div>
            <?php endif; ?>
            <?php // Placeholder for AJAX messages ?>
            <div id="ajax-message" class="alert !text-sm hidden"><i class="fas fa-info-circle"></i> <span id="ajax-text"></span></div>
        </div>

         <?php if (!$plugin_active): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Orunk Users plugin is not active. Dashboard functionality is limited.</div>
         <?php else: // Plugin is active ?>

            <?php // --- Top Row Cards (Account, Billing, Ads) --- ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                 <?php // Account Card ?>
                 <div class="styled-card">
                     <div class="styled-card-header"> <h3 class="flex items-center gap-2"><i class="fas fa-user text-indigo-500"></i>Account</h3> </div>
                     <div class="styled-card-body">
                         <div class="flex items-center mb-4">
                             <?php echo get_avatar($user_id, 48, '', '', ['class' => 'rounded-full w-12 h-12']); ?>
                             <div class="ml-3">
                                 <p class="text-sm font-medium leading-tight text-gray-800"><?php echo esc_html($current_user->display_name); ?></p>
                                 <p class="text-xs text-gray-500 leading-tight"><?php echo esc_html($current_user->user_email); ?></p>
                             </div>
                         </div>
                         <dl class="text-xs space-y-1 text-gray-600">
                             <div class="flex justify-between"><dt>Joined:</dt><dd class="font-medium text-gray-700"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($current_user->user_registered))); ?></dd></div>
                         </dl>
                     </div>
                     <div class="styled-card-footer justify-between">
                         <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="orunk-button-outline orunk-button-sm" onclick="return confirm('Are you sure you want to logout?');">
                             <i class="fas fa-sign-out-alt"></i> Logout
                         </a>
                         <button id="edit-profile-btn" class="orunk-button orunk-button-sm">
                             <i class="fas fa-edit"></i> Edit Profile
                         </button>
                     </div>
                 </div>

                 <?php // Billing Card ?>
                 <div class="styled-card">
                      <div class="styled-card-header"> <h3 class="flex items-center gap-2"><i class="fas fa-credit-card text-blue-500"></i>Billing</h3> </div>
                      <div class="styled-card-body flex-grow">
                          <div id="billing-address-display" class="text-xs text-gray-600 space-y-1">
                              <?php // Skeleton loader shown initially, JS fetches address ?>
                              <div class="space-y-1 animate-pulse">
                                  <div class="h-3 bg-gray-200 rounded w-3/4 skeleton"></div>
                                  <div class="h-3 bg-gray-200 rounded w-full skeleton"></div>
                                  <div class="h-3 bg-gray-200 rounded w-1/2 skeleton"></div>
                              </div>
                          </div>
                      </div>
                      <div class="styled-card-footer">
                          <button id="manage-address-btn" class="orunk-button orunk-button-sm" disabled>
                              <i class="fas fa-pencil-alt"></i> Manage Address
                          </button>
                      </div>
                 </div>

                 <?php // Ad Experience Card ?>
                 <div class="styled-card">
                     <div class="styled-card-header"> <h3 class="flex items-center gap-2"><i class="fas fa-ad text-teal-500"></i>Ad Experience</h3> </div>
                     <div class="styled-card-body text-center">
                         <?php if ($has_ad_removal): ?>
                             <div class="p-3 rounded-full bg-green-100 inline-block mb-2"> <i class="fas fa-shield-alt text-xl text-green-600"></i></div>
                             <p class="text-sm font-semibold text-green-700">Ad-Free Active</p>
                             <p class="text-xs text-gray-500 mt-1">Enjoy an uninterrupted experience.</p>
                             <?php // Cancel Form (uses standard POST, no JS needed for basic cancel) ?>
                             <?php if ($has_ad_removal && isset($active_ad_removal_purchase_id) && isset($cancel_nonces[$active_ad_removal_purchase_id])): ?>
                                 <form id="ad-removal-cancel-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Are you sure you want to cancel your Ad Removal subscription? This action cannot be undone.');" class="mt-2">
                                     <input type="hidden" name="action" value="orunk_cancel_plan">
                                     <input type="hidden" name="purchase_id_to_cancel" value="<?php echo esc_attr($active_ad_removal_purchase_id); ?>">
                                     <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($cancel_nonces[$active_ad_removal_purchase_id]); ?>">
                                     <button type="submit" class="orunk-button-danger orunk-button-sm">
                                         <i class="fas fa-times mr-1"></i> Cancel Ad Removal
                                     </button>
                                 </form>
                             <?php endif; ?>
                         <?php else: ?>
                              <div class="p-3 rounded-full bg-orange-100 inline-block mb-2"> <i class="fas fa-bullhorn text-xl text-orange-400"></i></div>
                              <p class="text-sm font-medium text-gray-700 mb-2">Ads Currently Enabled</p>
                              <?php if($ad_removal_plan_id): ?>
                                  <a href="<?php echo esc_url(add_query_arg('plan_id', $ad_removal_plan_id, home_url('/checkout/'))); ?>" class="orunk-button orunk-button-sm shine-effect bg-teal-500 hover:bg-teal-600">
                                      <i class="fas fa-shield-alt"></i> Remove Ads ($<?php echo esc_html(number_format((float)$ad_removal_price, 2)); ?>/mo)
                                  </a>
                              <?php else: ?>
                                  <p class="text-xs text-gray-400">(Ad removal plan unavailable)</p>
                              <?php endif; ?>
                         <?php endif; ?>
                     </div>
                     <div class="styled-card-footer"></div> <?php // Empty footer for consistent look ?>
                 </div>
            </div> <?php // End Top Row Grid ?>


            <?php // --- Group Active Services by Category (PHP logic remains) --- ?>
            <?php
                 $services_by_category = ['wp' => [], 'api' => [], 'other' => []];
                 $found_active_service = false;
                 $now_gmt_for_grouping = current_time('timestamp', 1);
                 if (!empty($all_purchases)) {
                     foreach ($all_purchases as $purchase_item) {
                         $is_active = ($purchase_item['status'] ?? '') === 'active';
                         $expiry_ts = isset($purchase_item['expiry_date']) ? strtotime($purchase_item['expiry_date']) : null;
                         $is_not_expired = ($expiry_ts === null || $expiry_ts > $now_gmt_for_grouping);
                         $feature_key_item = $purchase_item['product_feature_key'] ?? 'unknown';
                         $category_slug = $purchase_item['feature_category'] ?? 'other';

                         if ($is_active && $is_not_expired && $feature_key_item !== 'ad_removal') {
                            $found_active_service = true;
                            if ($category_slug === 'wordpress-plugin' || $category_slug === 'wordpress-theme') {
                                $services_by_category['wp'][] = $purchase_item;
                            } elseif ($category_slug === 'api-service') {
                                $services_by_category['api'][] = $purchase_item;
                            } else {
                                $services_by_category['other'][] = $purchase_item;
                            }
                         }
                     }
                 }
            ?>

            <?php // --- Render Service Sections --- ?>

            <?php // WordPress Downloads Section ?>
            <?php if (!empty($services_by_category['wp'])): ?>
            <div class="mb-8">
                 <div class="styled-card-header mb-4 !rounded-md !border !border-b-2 !border-gray-300">
                     <h2 class="text-lg font-semibold text-gray-800">WordPress Downloads</h2>
                     <a href="<?php echo esc_url(get_permalink(get_page_by_path('orunk-catalog'))); ?>" class="orunk-button orunk-button-sm"> <i class="fas fa-plus"></i> Add Service </a>
                 </div>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                     <?php foreach ($services_by_category['wp'] as $purchase) : ?>
                         <?php
                             $feature_key = $purchase['product_feature_key'];
                             $display_info = orunk_get_feature_display_info($feature_key);
                             $purchase_id = $purchase['id'];
                             $expiry_date_ts = $purchase['expiry_date'] ? strtotime($purchase['expiry_date']) : null;
                             $expiry_date_display = $expiry_date_ts ? date_i18n(get_option('date_format'), $expiry_date_ts) : __('Never', 'orunk-users');
                             $auto_renew_enabled = $purchase['auto_renew'] ?? 0;
                             $is_switch_pending = !empty($purchase['pending_switch_plan_id']);
                             $current_plan_id = $purchase['plan_id'];
                             $feature_slug = sanitize_title($display_info['title']);
                             $docs_url = home_url('/' . $feature_slug . '-docs/');
                             $help_url = home_url('/' . $feature_slug . '-help/');
                             $category_slug = $purchase['feature_category'] ?? 'wp';
                             $show_manage_button = ($orunk_frontend instanceof Custom_Orunk_Frontend)
                                                   ? $orunk_frontend->should_show_manage_plan_button($purchase, $plans_by_feature, $orunk_core)
                                                   : false;
                         ?>
                          <div class="styled-card service-card" id="purchase-<?php echo esc_attr($purchase_id); ?>">
                               <div class="styled-card-header !py-2 !px-4 justify-between">
                                   <div class="flex-1 min-w-0"> <?php do_action('orunk_dashboard_service_card_header', $purchase, $feature_key, $display_info); ?> </div>
                                   <span class="status-badge active">Active</span>
                              </div>
                              <div class="styled-card-body">
                                  <?php do_action('orunk_dashboard_service_card_body', $purchase, $feature_key); ?>
                                  <?php if ($is_switch_pending): ?> <div class="alert alert-warning !py-1.5 !px-3 !text-xs !mb-0 mt-4"> <i class="fas fa-clock"></i> Plan switch pending. </div> <?php endif; ?>
                              </div>
                              <div class="styled-card-footer">
                                    <?php // Conditional Download Button - Requires JS handler ?>
                                    <?php if ($feature_key === 'convojet_pro'): ?>
                                        <button type="button" class="orunk-button button-primary download-plugin-btn orunk-button-sm" data-purchase-id="<?php echo esc_attr($purchase_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('orunk_convojet_download_nonce')); ?>">
                                            <span class="button-text"><i class="fas fa-download mr-1"></i>Download</span>
                                            <span class="button-spinner" style="display: none;"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                                        </button>
                                    <?php endif; ?>

                                    <?php // Conditional Manage Plan Button - Requires JS handler ?>
                                    <?php if ($show_manage_button): ?>
                                        <button class="orunk-button-outline orunk-button-sm change-plan-btn"
                                                data-purchase-id="<?php echo esc_attr($purchase_id); ?>"
                                                data-feature-key="<?php echo esc_attr($feature_key); ?>"
                                                data-current-plan-id="<?php echo esc_attr($current_plan_id); ?>"
                                                data-service-name="<?php echo esc_attr($display_info['title']); ?>"
                                                data-expiry-date-display="<?php echo esc_attr($expiry_date_display); ?>"
                                                data-auto-renew-enabled="<?php echo esc_attr($auto_renew_enabled); ?>"
                                                <?php disabled($is_switch_pending); ?>>
                                            <i class="fas fa-exchange-alt"></i> Manage Plan
                                        </button>
                                    <?php endif; ?>

                                     <a href="<?php echo esc_url($docs_url); ?>" target="_blank" class="orunk-button-outline orunk-button-sm" title="Documentation"><i class="fas fa-book"></i>Docs</a>
                                     <a href="<?php echo esc_url($help_url); ?>" target="_blank" class="orunk-button-outline orunk-button-sm" title="Get Support"><i class="fas fa-question-circle"></i> Help</a>
                              </div>
                               <?php // Download Feedback Div ?>
                               <?php if ($feature_key === 'convojet_pro'): ?>
                                   <div id="download-feedback-<?php echo esc_attr($purchase_id); ?>" class="w-full text-xs px-4 pb-2 text-center h-4"></div>
                               <?php endif; ?>
                          </div>
                     <?php endforeach; ?>
                 </div>
            </div>
            <?php endif; ?>

            <?php // API Services Section ?>
            <?php if (!empty($services_by_category['api'])): ?>
            <div class="mb-8">
                 <div class="styled-card-header mb-4 !rounded-md !border !border-b-2 !border-gray-300">
                     <h2 class="text-lg font-semibold text-gray-800">API Services</h2>
                      <?php // Optional: Add API service link? ?>
                 </div>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                     <?php foreach ($services_by_category['api'] as $purchase) : ?>
                          <?php // Setup variables
                             $feature_key = $purchase['product_feature_key'];
                             $display_info = orunk_get_feature_display_info($feature_key);
                             $purchase_id = $purchase['id'];
                             $expiry_date_ts = $purchase['expiry_date'] ? strtotime($purchase['expiry_date']) : null;
                             $expiry_date_display = $expiry_date_ts ? date_i18n(get_option('date_format'), $expiry_date_ts) : __('Never', 'orunk-users');
                             $auto_renew_enabled = $purchase['auto_renew'] ?? 0;
                             $is_switch_pending = !empty($purchase['pending_switch_plan_id']);
                             $current_plan_id = $purchase['plan_id'];
                             $feature_slug = sanitize_title($display_info['title']);
                             $docs_url = home_url('/' . $feature_slug . '-docs/');
                             $help_url = home_url('/' . $feature_slug . '-help/');
                             $category_slug = $purchase['feature_category'] ?? 'api';
                             $show_manage_button = ($orunk_frontend instanceof Custom_Orunk_Frontend)
                                                   ? $orunk_frontend->should_show_manage_plan_button($purchase, $plans_by_feature, $orunk_core)
                                                   : false;
                          ?>
                           <div class="styled-card service-card" id="purchase-<?php echo esc_attr($purchase_id); ?>">
                                <div class="styled-card-header !py-2 !px-4 justify-between">
                                   <div class="flex-1 min-w-0"> <?php do_action('orunk_dashboard_service_card_header', $purchase, $feature_key, $display_info); ?> </div>
                                   <span class="status-badge active">Active</span>
                               </div>
                               <div class="styled-card-body">
                                  <?php do_action('orunk_dashboard_service_card_body', $purchase, $feature_key); // Renders API key and usage ?>
                                   <?php if ($is_switch_pending): ?> <div class="alert alert-warning !py-1.5 !px-3 !text-xs !mb-0 mt-4"> <i class="fas fa-clock"></i> Plan switch pending. </div> <?php endif; ?>
                               </div>
                               <div class="styled-card-footer">
                                     <?php // Conditional Manage Plan Button - Requires JS handler ?>
                                     <?php if ($show_manage_button): ?>
                                         <button class="orunk-button-outline orunk-button-sm change-plan-btn"
                                                 data-purchase-id="<?php echo esc_attr($purchase_id); ?>"
                                                 data-feature-key="<?php echo esc_attr($feature_key); ?>"
                                                 data-current-plan-id="<?php echo esc_attr($current_plan_id); ?>"
                                                 data-service-name="<?php echo esc_attr($display_info['title']); ?>"
                                                 data-expiry-date-display="<?php echo esc_attr($expiry_date_display); ?>"
                                                 data-auto-renew-enabled="<?php echo esc_attr($auto_renew_enabled); ?>"
                                                 <?php disabled($is_switch_pending); ?>>
                                             <i class="fas fa-exchange-alt"></i> Manage Plan
                                         </button>
                                     <?php endif; ?>
                                     <a href="<?php echo esc_url($docs_url); ?>" target="_blank" class="orunk-button-outline orunk-button-sm" title="Documentation"><i class="fas fa-book"></i>Docs</a>
                                     <a href="<?php echo esc_url($help_url); ?>" target="_blank" class="orunk-button-outline orunk-button-sm" title="Get Support"><i class="fas fa-question-circle"></i> Help</a>
                               </div>
                           </div>
                      <?php endforeach; ?>
                  </div>
             </div>
             <?php endif; ?>

             <?php // Other Services Section ?>
             <?php if (!empty($services_by_category['other'])): ?>
             <div class="mb-8">
                 <div class="styled-card-header mb-4 !rounded-md !border !border-b-2 !border-gray-300">
                     <h2 class="text-lg font-semibold text-gray-800">Other Active Services</h2>
                      <?php // Optional: Add service link? ?>
                 </div>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                       <?php foreach ($services_by_category['other'] as $purchase) : ?>
                            <?php // Setup variables (similar to API section)
                                 $feature_key = $purchase['product_feature_key'];
                                 $display_info = orunk_get_feature_display_info($feature_key);
                                 $purchase_id = $purchase['id'];
                                 $expiry_date_ts = $purchase['expiry_date'] ? strtotime($purchase['expiry_date']) : null;
                                 $expiry_date_display = $expiry_date_ts ? date_i18n(get_option('date_format'), $expiry_date_ts) : __('Never', 'orunk-users');
                                 $auto_renew_enabled = $purchase['auto_renew'] ?? 0;
                                 $is_switch_pending = !empty($purchase['pending_switch_plan_id']);
                                 $current_plan_id = $purchase['plan_id'];
                                 $feature_slug = sanitize_title($display_info['title']);
                                 $docs_url = home_url('/' . $feature_slug . '-docs/');
                                 $help_url = home_url('/' . $feature_slug . '-help/');
                                 $category_slug = $purchase['feature_category'] ?? 'other';
                                 $show_manage_button = ($orunk_frontend instanceof Custom_Orunk_Frontend)
                                                       ? $orunk_frontend->should_show_manage_plan_button($purchase, $plans_by_feature, $orunk_core)
                                                       : false;
                            ?>
                            <div class="styled-card service-card" id="purchase-<?php echo esc_attr($purchase['id']); ?>">
                                <div class="styled-card-header !py-2 !px-4 justify-between">
                                    <div class="flex-1 min-w-0"> <?php do_action('orunk_dashboard_service_card_header', $purchase, $feature_key, $display_info); ?> </div>
                                    <span class="status-badge active">Active</span>
                                </div>
                                <div class="styled-card-body">
                                   <?php do_action('orunk_dashboard_service_card_body', $purchase, $feature_key); // Default body handler potentially used ?>
                                    <?php if ($is_switch_pending): ?> <div class="alert alert-warning !py-1.5 !px-3 !text-xs !mb-0 mt-4"> <i class="fas fa-clock"></i> Plan switch pending. </div> <?php endif; ?>
                                </div>
                                <div class="styled-card-footer">
                                      <?php // Conditional Manage Plan Button - Requires JS handler ?>
                                      <?php if ($show_manage_button): ?>
                                          <button class="orunk-button-outline orunk-button-sm change-plan-btn"
                                                  data-purchase-id="<?php echo esc_attr($purchase_id); ?>"
                                                  data-feature-key="<?php echo esc_attr($feature_key); ?>"
                                                  data-current-plan-id="<?php echo esc_attr($current_plan_id); ?>"
                                                  data-service-name="<?php echo esc_attr($display_info['title']); ?>"
                                                  data-expiry-date-display="<?php echo esc_attr($expiry_date_display); ?>"
                                                  data-auto-renew-enabled="<?php echo esc_attr($auto_renew_enabled); ?>"
                                                  <?php disabled($is_switch_pending); ?>>
                                              <i class="fas fa-exchange-alt"></i> Manage Plan
                                          </button>
                                      <?php endif; ?>
                                     <a href="<?php echo esc_url($docs_url); ?>" target="_blank" class="orunk-button-outline orunk-button-sm" title="Documentation"><i class="fas fa-book"></i>Docs</a>
                                     <a href="<?php echo esc_url($help_url); ?>" target="_blank" class="orunk-button-outline orunk-button-sm" title="Get Support"><i class="fas fa-question-circle"></i> Help</a>
                                </div>
                            </div>
                       <?php endforeach; ?>
                  </div>
             </div>
             <?php endif; ?>

             <?php // Message if NO active services found ?>
             <?php if (!$found_active_service) : ?>
                 <div class="styled-card">
                     <div class="styled-card-body text-center py-6">
                         <i class="fas fa-box-open text-3xl text-gray-300 mb-3"></i>
                         <h3 class="text-sm font-medium text-gray-700 mb-1">No active services</h3>
                         <p class="text-xs text-gray-500">You don't have any active services right now.</p>
                         <a href="<?php echo esc_url(get_permalink(get_page_by_path('orunk-catalog'))); ?>" class="orunk-button orunk-button-sm mt-4">
                             <i class="fas fa-store"></i> Browse Services
                         </a>
                     </div>
                 </div>
             <?php endif; ?>


            <?php // --- Purchase History Section --- ?>
            <div class="mb-6">
                <div class="styled-card-header mb-4 !rounded-md !border !border-b-2 !border-gray-300">
                    <h2>Purchase History</h2>
                </div>
                <div class="styled-card !shadow-none !border-0">
                    <div class="styled-card-body !p-0 overflow-x-auto">
                        <?php if (empty($all_purchases)) : ?>
                            <div class="text-center py-6 text-gray-500 text-sm">
                                <i class="fas fa-history text-3xl text-gray-300 mb-3"></i>
                                <p>No purchase history found.</p>
                            </div>
                        <?php else : ?>
                            <table class="styled-table">
                                <thead>
                                    <tr> <th>Date</th><th>Type</th><th>Plan</th><th>Feature</th><th>Amount</th><th>Status</th><th>Payment</th><th>Expiry</th><th>ID</th> </tr>
                                </thead>
                                <tbody id="history-table-body">
                                    <?php // PHP loop remains the same for rendering initial rows ?>
                                    <?php $history_counter = 0; ?>
                                    <?php foreach ($all_purchases as $hist_purchase) : ?>
                                        <?php
                                            $history_counter++;
                                            $row_class = ($history_counter > 5) ? 'history-hidden' : ''; // Class handled by JS now
                                            $hist_plan_name = $hist_purchase['plan_name'] ?? __('N/A', 'orunk-users');
                                            $hist_feature_key = $hist_purchase['product_feature_key'] ?? 'other';
                                            $hist_gateway = $hist_purchase['payment_gateway'] ?? 'N/A';
                                            $hist_purchase_date = $hist_purchase['purchase_date'] ? date_i18n(get_option('date_format'), strtotime($hist_purchase['purchase_date'])) : '-';
                                            $hist_expiry = $hist_purchase['expiry_date'] ? date_i18n(get_option('date_format'), strtotime($hist_purchase['expiry_date'])) : '-';
                                            $hist_display = function_exists('orunk_get_feature_display_info') ? orunk_get_feature_display_info($hist_feature_key) : ['title' => ucfirst(str_replace('_', ' ', $hist_feature_key))];
                                            $hist_formatted_id = '#ORD-' . esc_html($hist_purchase['id']);
                                            $hist_price_display = __('N/A');
                                            if (!empty($hist_purchase['plan_details_snapshot'])) { $snapshot_data = json_decode($hist_purchase['plan_details_snapshot'], true); if ($snapshot_data && isset($snapshot_data['price'])) { $hist_price_display = '$' . number_format((float)$snapshot_data['price'], 2); } }
                                            $raw_type = $hist_purchase['transaction_type'] ?? 'purchase'; $hist_transaction_type_display = 'Purchase'; switch ($raw_type) { case 'purchase': $hist_transaction_type_display = 'Initial Purchase'; break; case 'renewal_success': $hist_transaction_type_display = 'Renewal'; break; case 'switch_success': $hist_transaction_type_display = 'Plan Switch'; break; case 'renewal_failure': $hist_transaction_type_display = 'Renewal Failed'; break; }
                                            $hist_payment_display = ucfirst(str_replace('_', ' ', $hist_gateway));
                                            $hist_status_orig = $hist_purchase['status'] ?? 'unknown'; $hist_status_display = $hist_status_orig; $status_badge_class = 'unknown'; switch (strtolower($hist_status_orig)) { case 'active': $expiry_timestamp_hist = $hist_purchase['expiry_date'] ? strtotime($hist_purchase['expiry_date']) : null; $is_not_expired_hist = $expiry_timestamp_hist === null || $expiry_timestamp_hist > current_time('timestamp', 1); if (!$is_not_expired_hist) { $hist_status_display = 'expired'; $status_badge_class = 'expired'; } else { $status_badge_class = 'active'; } break; case 'pending payment': case 'pending': $status_badge_class = 'pending'; break; case 'cancelled': $status_badge_class = 'cancelled'; break; case 'failed': $status_badge_class = 'failed'; break; case 'expired': $status_badge_class = 'expired'; break; }
                                        ?>
                                        <tr class="<?php echo esc_attr($row_class); // JS will handle visibility based on this class ?>">
                                            <td><?php echo esc_html($hist_purchase_date); ?></td><td><?php echo esc_html($hist_transaction_type_display); ?></td><td><?php echo esc_html($hist_plan_name); ?></td><td><?php echo esc_html($hist_display['title']); ?></td><td><?php echo esc_html($hist_price_display); ?></td><td><span class="status-badge <?php echo esc_attr($status_badge_class); ?>"><?php echo esc_html(ucfirst($hist_status_display)); ?></span></td><td><?php echo esc_html($hist_payment_display); ?></td><td><?php echo esc_html($hist_expiry); ?></td><td class="font-mono text-xs"><?php echo esc_html($hist_formatted_id); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php // Button handled by JS now ?>
                            <?php if (count($all_purchases) > 5): ?>
                                <div class="text-center py-4">
                                    <button id="show-more-history" class="orunk-button orunk-button-outline orunk-button-sm <?php echo ($history_counter <= 5 ? 'hidden' : ''); // Initially hide if not needed ?>">Show More History</button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div> <?php // End Purchase History ?>

         <?php endif; // End plugin active check ?>

    </div> <?php // End .dashboard-container ?>

    <?php // --- Modals (HTML Structure Unchanged) --- ?>
    <div id="profile-modal" class="modal-overlay hidden"> <?php /* Profile Modal HTML... */ ?> <div class="modal-content"> <button class="modal-close-btn" onclick="closeModal('profile-modal')"><i class="fas fa-times"></i></button> <div class="modal-header"> <h3 class="text-lg font-semibold flex items-center gap-2"><i class="fas fa-user-edit mr-2 text-indigo-500"></i> Edit Profile</h3> </div> <div class="modal-body !p-4"> <form id="profile-form" enctype="multipart/form-data"> <div id="profile-details-section"> <div class="profile-picture-grid-item"> <img id="profile-picture-preview-img" src="<?php echo esc_url(get_avatar_url($user_id, ['size' => 64])); ?>" alt="Profile Picture Preview" class="profile-picture-preview"> <div class="profile-picture-actions"> <label for="profile_picture" class="form-label mb-0">Profile Picture</label> <input type="file" name="profile_picture" id="profile_picture" accept="image/png, image/jpeg, image/gif"> <input type="hidden" name="remove_profile_picture" id="remove_profile_picture" value="0"> <div class="flex gap-2 mt-1"> <button type="button" id="upload-picture-btn" class="orunk-button-outline orunk-button-sm"><i class="fas fa-upload"></i> Upload New</button> <button type="button" id="remove-picture-btn" class="orunk-button-outline orunk-button-sm !border-red-300 !text-red-600 hover:!bg-red-50"><i class="fas fa-trash-alt"></i> Remove</button> </div> <p class="form-description !mt-1">Max 2MB (JPG, PNG, GIF)</p> </div> </div> <div class="profile-display-name-item"> <label for="profile_display_name" class="form-label">Display Name</label> <input type="text" name="display_name" id="profile_display_name" value="<?php echo esc_attr($current_user->display_name); ?>" class="form-input" required> </div> <div class="profile-email-item"> <label for="profile_email" class="form-label">Email Address <span class="text-red-500">*</span></label> <input type="email" name="email" id="profile_email" value="<?php echo esc_attr($current_user->user_email); ?>" class="form-input" required> <p class="form-description">Requires current password below if changing email or password.</p> </div> <div class="change-password-grid-item"> <p class="text-xs font-medium text-gray-700 mb-2" style="grid-column: 1 / -1;">Change Password (optional)</p> <div class="current-password-item"> <div> <label for="profile_current_password" class="form-label">Current Password</label> <input type="password" name="current_password" id="profile_current_password" class="form-input" placeholder="Required to change email or password"> </div> <div class="text-right mt-1"> <button type="button" id="show-forgot-password-modal" class="text-xs text-indigo-600 hover:underline focus:outline-none">Forgot Password?</button> </div> </div> <div class="new-password-item"> <label for="profile_new_password" class="form-label">New Password</label> <input type="password" name="new_password" id="profile_new_password" class="form-input" placeholder="Enter New password"> </div> <div class="confirm-password-item"> <label for="profile_confirm_password" class="form-label">Confirm New Password</label> <input type="password" name="confirm_password" id="profile_confirm_password" class="form-input"> </div> </div> </div> <div class="modal-footer mt-4" id="profile-form-footer"> <div id="modal-feedback-profile" class="text-xs text-right mr-auto h-4"></div> <button type="button" class="orunk-button-outline orunk-button-sm" onclick="closeModal('profile-modal')"> Cancel </button> <button type="submit" id="save-profile-submit-btn" class="orunk-button orunk-button-sm"> <i class="fas fa-save mr-1"></i> Save Changes <span class="save-spinner spinner" style="display: none;"></span> </button> </div> </form> <div id="forgot-password-section" class="hidden forgot-password-section"> <div id="request-otp-section"> <h4 class="text-md font-medium mb-2">Reset Password</h4> <p class="form-description">Enter your account email address below to receive a password reset OTP.</p> <div> <label for="forgot-email" class="form-label">Email Address</label> <input type="email" name="forgot_email" id="forgot-email" value="<?php echo esc_attr($current_user->user_email); ?>" class="form-input" required placeholder="Your account email"> </div> <div id="modal-feedback-forgot" class="h-5 mt-2"></div> <div class="modal-footer mt-4 forgot-password-footer-buttons"> <button type="button" id="back-to-profile-view" class="orunk-button-outline orunk-button-sm"><i class="fas fa-arrow-left mr-1"></i> Back</button> <button type="button" id="submit-forgot-email" class="orunk-button orunk-button-sm"> Send OTP <span class="forgot-spinner spinner" style="display: none;"></span> </button> </div> </div> <div id="otp-verify-section" class="hidden mt-4"> <h4 class="text-md font-medium mb-2">Enter OTP</h4> <p class="form-description">An OTP has been sent to <strong id="otp-sent-to-email">your email</strong>. Enter it below.</p> <div> <label for="otp-code" class="form-label">One-Time Password</label> <input type="text" inputmode="numeric" pattern="[0-9]*" name="otp_code" id="otp-code" class="form-input otp-input" required placeholder="Enter OTP"> </div> <div id="modal-feedback-verify" class="h-5 mt-2"></div> <div class="modal-footer mt-4 forgot-password-footer-buttons"> <button type="button" id="back-to-email-entry" class="orunk-button-outline orunk-button-sm"><i class="fas fa-arrow-left mr-1"></i> Back</button> <button type="button" id="submit-verify-otp" class="orunk-button orunk-button-sm"> Verify OTP <span class="verify-spinner spinner" style="display: none;"></span> </button> </div> </div> <div id="reset-password-otp-section" class="hidden mt-4"> <h4 class="text-md font-medium mb-2">Set New Password</h4> <p class="form-description">Enter and confirm your new password.</p> <div class="grid grid-cols-1 md:grid-cols-2 gap-3"> <div> <label for="reset-new-password" class="form-label">New Password</label> <input type="password" name="reset_new_password" id="reset-new-password" class="form-input" required placeholder="Enter new password"> </div> <div> <label for="reset-confirm-password" class="form-label">Confirm New Password</label> <input type="password" name="reset_confirm_password" id="reset-confirm-password" class="form-input" required placeholder="Confirm new password"> </div> </div> <div id="modal-feedback-reset" class="h-5 mt-2"></div> <div class="modal-footer mt-4 forgot-password-footer-buttons"> <button type="button" id="back-to-otp-entry" class="orunk-button-outline orunk-button-sm"><i class="fas fa-arrow-left mr-1"></i> Back</button> <button type="button" id="submit-reset-password-otp" class="orunk-button orunk-button-sm"> Set New Password <span class="reset-spinner spinner" style="display: none;"></span> </button> </div> </div> </div> </div> </div> </div>
     <div id="billing-modal" class="modal-overlay hidden"> <?php /* Billing Modal HTML... */ ?> <div class="modal-content"> <button class="modal-close-btn" onclick="closeModal('billing-modal')"><i class="fas fa-times"></i></button> <div class="modal-header"> <h3 class="text-lg font-semibold flex items-center gap-2"><i class="fas fa-map-marker-alt mr-2 text-indigo-500"></i> Billing Address</h3> </div> <form id="billing-form"> <div class="modal-body !p-4"> <div class="grid grid-cols-1 md:grid-cols-2 gap-2"> <div><label for="billing_first_name" class="form-label">First Name</label><input type="text" name="billing_first_name" id="billing_first_name" class="form-input" placeholder="Enter first name"></div> <div><label for="billing_last_name" class="form-label">Last Name</label><input type="text" name="billing_last_name" id="billing_last_name" class="form-input" placeholder="Enter last name"></div> <div class="md:col-span-2"><label for="billing_company" class="form-label">Company Name (Optional)</label><input type="text" name="billing_company" id="billing_company" class="form-input"></div> <div class="md:col-span-2"><label for="billing_address_1" class="form-label">Street Address</label><input type="text" name="billing_address_1" id="billing_address_1" class="form-input" placeholder="House number and street name"></div> <div class="md:col-span-2"><label for="billing_address_2" class="form-label visually-hidden">Apartment, suite, etc. (optional)</label><input type="text" name="billing_address_2" id="billing_address_2" class="form-input" placeholder="Apartment, suite, unit, etc. (optional)"></div> <div><label for="billing_city" class="form-label">Town / City</label><input type="text" name="billing_city" id="billing_city" class="form-input"></div> <div><label for="billing_state" class="form-label">State / County</label><input type="text" name="billing_state" id="billing_state" class="form-input"></div> <div><label for="billing_postcode" class="form-label">Postcode / ZIP</label><input type="text" name="billing_postcode" id="billing_postcode" class="form-input"></div> <div><label for="billing_country" class="form-label">Country</label><input type="text" name="billing_country" id="billing_country" class="form-input"></div> <div class="md:col-span-2"><label for="billing_phone" class="form-label">Phone</label><input type="tel" name="billing_phone" id="billing_phone" class="form-input" placeholder="Enter phone number"></div> </div> </div> <div class="modal-footer"> <div id="modal-feedback-billing" class="text-xs text-right mr-auto h-4"></div> <button type="button" class="orunk-button-outline orunk-button-sm" onclick="closeModal('billing-modal')">Cancel</button> <button type="submit" id="save-billing-submit-btn" class="orunk-button orunk-button-sm"> <i class="fas fa-save mr-1"></i> Save Address <span class="save-spinner spinner" style="display: none;"></span> </button> </div> </form> </div> </div>
     <div id="plan-modal" class="modal-overlay hidden"> <?php /* Plan Change Modal HTML... */ ?> <div class="modal-content"> <div class="modal-header"> <h3 class="text-lg font-semibold flex items-center gap-2"><i class="fas fa-exchange-alt mr-2 text-indigo-500"></i><span id="plan-modal-title">Change Plan</span></h3> <button class="modal-close-btn" onclick="closeModal('plan-modal')"><i class="fas fa-times"></i></button> </div> <div class="modal-body !pt-4"> <div id="current-plan-info" class="mb-3 p-3 bg-indigo-50 border border-indigo-200 rounded-md text-sm" style="display: none;"> <p id="current-plan-details"></p> </div> <div id="plan-renewal-section" class="text-xs text-gray-500 flex justify-between items-center mb-3 p-3 border-t border-b border-gray-100 hidden"> <span><i class="fas fa-calendar-times w-3 inline-block text-center opacity-60 mr-1"></i> Renews: <span class="font-medium text-gray-700" id="plan-modal-expiry-date">N/A</span></span> <div class="flex items-center gap-1" title="Toggle Auto-Renewal"> <span class="text-xs text-gray-500 mr-1">Renew</span> <label class="toggle-switch"> <input type="checkbox" class="auto-renew-toggle" id="plan-modal-auto-renew-toggle" data-purchase-id=""> <span class="toggle-slider"></span> </label> </div> </div> <p class="text-sm text-gray-600 mb-3">Select a new plan for <strong id="plan-modal-service-name" class="text-gray-800">Service</strong>.</p> <form id="plan-modal-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"> <input type="hidden" name="action" value="orunk_switch_plan"> <input type="hidden" name="current_purchase_id" id="plan-modal-purchase-id" value=""> <input type="hidden" name="new_plan_id" id="plan-modal-selected-plan-id" value=""> <input type="hidden" name="_wpnonce" id="plan-modal-nonce" value=""> <div id="plan-modal-options" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-3 max-h-[45vh] overflow-y-auto p-1"> <div class="flex justify-center items-center p-8 md:col-span-2 lg:col-span-3"><div class="spinner"></div></div> </div> </form> </div> <div class="modal-footer"> <form id="plan-modal-cancel-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="contents" onsubmit="return confirm('Are you sure you want to cancel this subscription?');"> <input type="hidden" name="action" value="orunk_cancel_plan"> <input type="hidden" name="purchase_id_to_cancel" id="plan-modal-cancel-purchase-id" value=""> <input type="hidden" name="_wpnonce" id="plan-modal-cancel-nonce" value=""> <button type="submit" id="confirm-plan-cancel" class="orunk-button-danger orunk-button-sm"> <i class="fas fa-times mr-1"></i> Cancel Subscription </button> </form> <div id="modal-feedback-plan" class="text-xs text-right mr-auto h-4 order-first w-full md:w-auto md:order-none"></div> <button type="button" class="orunk-button-outline orunk-button-sm" onclick="closeModal('plan-modal')">Close</button> <button type="button" class="orunk-button orunk-button-sm" id="confirm-plan-change" disabled> <i class="fas fa-check mr-1"></i> Confirm Change </button> </div> </div> </div>

<?php // --- Small inline script to define JS constants --- ?>
<script type="text/javascript">
    const orunkDashboardData = {
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        regenNonce: '<?php echo wp_create_nonce('orunk_regenerate_api_key_nonce'); ?>',
        profileNonce: '<?php echo wp_create_nonce('orunk_update_profile_nonce'); ?>',
        billingNonce: '<?php echo wp_create_nonce('orunk_billing_address_nonce'); ?>',
        autoRenewNonce: '<?php echo wp_create_nonce('orunk_auto_renew_nonce'); ?>',
        otpRequestNonce: '<?php echo wp_create_nonce('orunk_request_otp_action'); ?>',
        otpVerifyNonce: '<?php echo wp_create_nonce('orunk_verify_otp_action'); ?>',
        otpResetNonce: '<?php echo wp_create_nonce('orunk_reset_password_otp_action'); ?>',
        currentUserId: <?php echo esc_js($user_id); ?>,
        userAvatarUrl: '<?php echo esc_url(get_avatar_url($user_id, ["size" => 64])); ?>' // Pass current avatar URL
    };
    const orunkAvailablePlans = <?php echo wp_json_encode($plans_by_feature); ?>;
    const orunkAllPurchases = <?php echo wp_json_encode($all_purchases); ?>;
    const orunkCancelNonces = <?php echo wp_json_encode($cancel_nonces); ?>;
    const orunkSwitchNonces = <?php echo wp_json_encode($switch_nonces ?? []); ?>;
    // Define transient message for JS access
    const orunkInitialSuccessMessage = <?php echo json_encode(get_transient('orunk_purchase_message_' . $user_id) ?: null); delete_transient('orunk_purchase_message_' . $user_id); ?>;

    // Define global `let` variables needed across files
    let currentBillingAddress = null;
    let forgotPasswordUserIdentifier = '';
</script>

<?php
// Enqueued scripts (main.js, profile.js, etc.) will be loaded here by wp_footer()
get_footer();
?>

<?php // --- The large <script> block previously here should be DELETED --- ?>

</body>
</html>