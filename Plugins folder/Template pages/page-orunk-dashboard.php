<?php
/**
 * Template Name: Orunk User Dashboard (v3.0.5 - Conditional Manage Plan Button)
 * Template Post Type: page
 *
 * Modifications:
 * - Added logic via Custom_Orunk_Frontend::should_show_manage_plan_button() to hide
 * 'Manage Plan' button for one-time plans or when no other plans are available.
 * - Removed initial `opacity: 0` from `.modal-content` base style.
 * - Kept forceful `!important` visibility/opacity styles on active modal content.
 * - Added console logs to openModal JS function for debugging class addition.
 * - Includes previous mods: Dynamic Hooks, Categories, Downloads, OTP Flow, History Limit, etc.
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
$orunk_frontend = null; // <-- Added variable for frontend class instance
$all_purchases = []; // Holds ALL purchase history items
$plans_by_feature = []; // Holds available plans for modal
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

    // <-- START: Instantiate Frontend Class -->
    if (class_exists('Custom_Orunk_Frontend')) {
         $orunk_frontend = new Custom_Orunk_Frontend();
         // If init() is needed for hook registration elsewhere, call it.
         // If hooks are added statically or in main plugin, this isn't needed here.
         // $orunk_frontend->init();
    }
    // <-- END: Instantiate Frontend Class -->


    // Fetch ALL purchases for the user (assuming get_user_purchases joins products table for category)
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
            $purchase['feature_category'] = $purchase['feature_category'] ?? 'other'; // Get category slug
             $purchase['plan_id'] = $purchase['plan_id'] ?? 0; // Ensure plan_id exists

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
            // Add to the final list (all statuses included for history)
            $all_purchases[] = $purchase;
        }
    }

    // Fetch all features with their ACTIVE plans grouped by feature key (for plan selection modal)
    $all_features_with_active_plans = $orunk_core->get_product_features_with_plans();
    if(!empty($all_features_with_active_plans)) {
        foreach($all_features_with_active_plans as $feature) {
            if(!empty($feature['feature']) && !empty($feature['plans'])) {
                 // Filter only active plans before storing
                 $active_plans = array_filter($feature['plans'], function($plan) {
                     return isset($plan['is_active']) && $plan['is_active'] == 1;
                 });
                 if (!empty($active_plans)) {
                    $plans_by_feature[$feature['feature']] = array_values($active_plans); // Re-index array
                 } else {
                    $plans_by_feature[$feature['feature']] = []; // Store empty array if no active plans
                 }

                 if ($feature['feature'] === 'ad_removal') {
                     // Use the potentially filtered $active_plans here too
                     $ad_removal_plan_id = $active_plans[0]['id'] ?? null;
                     $ad_removal_price = $active_plans[0]['price'] ?? $ad_removal_price;
                 }
            }
        }
    }
} // End if class_exists

// ================================================================
// START: Helper Function Definitions (Unchanged)
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>
<body <?php body_class('orunk-dashboard bg-gray-50'); ?>>
    <div class="dashboard-container orunk-container">
        <?php // --- Header & Message Area (Unchanged) --- ?>
        <div class="mb-6"> <h1 class="text-2xl font-bold text-gray-800">My Dashboard</h1> <p class="text-sm text-gray-500">Manage your services and account</p> </div>
        <div id="messages" class="mb-6 space-y-2"> <?php if(isset($_GET['orunk_error'])): ?> <div class="alert alert-error !text-sm"><i class="fas fa-times-circle"></i> <?php echo esc_html(urldecode(wp_unslash($_GET['orunk_error']))); ?></div> <?php endif; ?> <?php if(get_transient('orunk_purchase_message_' . $user_id)): ?> <div class="alert alert-success !text-sm"><i class="fas fa-check-circle"></i> <?php echo esc_html(get_transient('orunk_purchase_message_' . $user_id)); delete_transient('orunk_purchase_message_' . $user_id); ?></div> <?php endif; ?> <div id="ajax-message" class="alert !text-sm hidden"><i class="fas fa-info-circle"></i> <span id="ajax-text"></span></div> </div>

         <?php if (!$plugin_active): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Orunk Users plugin is not active. Dashboard functionality is limited.</div>
         <?php else: // Plugin is active ?>

            <?php // --- Account / Billing / Ad Experience Cards (Unchanged) --- ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                 <div class="styled-card"> <div class="styled-card-header"> <h3 class="flex items-center gap-2"><i class="fas fa-user text-indigo-500"></i>Account</h3> </div> <div class="styled-card-body"> <div class="flex items-center mb-4"> <?php echo get_avatar($user_id, 48, '', '', ['class' => 'rounded-full w-12 h-12']); ?> <div class="ml-3"> <p class="text-sm font-medium leading-tight text-gray-800"><?php echo esc_html($current_user->display_name); ?></p> <p class="text-xs text-gray-500 leading-tight"><?php echo esc_html($current_user->user_email); ?></p> </div> </div> <dl class="text-xs space-y-1 text-gray-600"> <div class="flex justify-between"><dt>Joined:</dt><dd class="font-medium text-gray-700"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($current_user->user_registered))); ?></dd></div> </dl> </div> <div class="styled-card-footer justify-between"> <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="orunk-button-outline orunk-button-sm" onclick="return confirm('Are you sure you want to logout?');"> <i class="fas fa-sign-out-alt"></i> Logout </a> <button id="edit-profile-btn" class="orunk-button orunk-button-sm"> <i class="fas fa-edit"></i> Edit Profile </button> </div> </div>
                 <div class="styled-card"> <div class="styled-card-header"> <h3 class="flex items-center gap-2"><i class="fas fa-credit-card text-blue-500"></i>Billing</h3> </div> <div class="styled-card-body flex-grow"> <div id="billing-address-display" class="text-xs text-gray-600 space-y-1"> <div class="space-y-1 animate-pulse"><div class="h-3 bg-gray-200 rounded w-3/4 skeleton"></div><div class="h-3 bg-gray-200 rounded w-full skeleton"></div><div class="h-3 bg-gray-200 rounded w-1/2 skeleton"></div></div> </div> </div> <div class="styled-card-footer"> <button id="manage-address-btn" class="orunk-button orunk-button-sm" disabled> <i class="fas fa-pencil-alt"></i> Manage Address </button> </div> </div>
                 <div class="styled-card"> <div class="styled-card-header"> <h3 class="flex items-center gap-2"><i class="fas fa-ad text-teal-500"></i>Ad Experience</h3> </div> <div class="styled-card-body text-center"> <?php if ($has_ad_removal): ?> <div class="p-3 rounded-full bg-green-100 inline-block mb-2"> <i class="fas fa-shield-alt text-xl text-green-600"></i></div> <p class="text-sm font-semibold text-green-700">Ad-Free Active</p> <p class="text-xs text-gray-500 mt-1">Enjoy an uninterrupted experience.</p> <?php if ($has_ad_removal && isset($active_ad_removal_purchase_id) && isset($cancel_nonces[$active_ad_removal_purchase_id])): ?> <form id="ad-removal-cancel-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Are you sure you want to cancel your Ad Removal subscription? This action cannot be undone.');" class="mt-2"> <input type="hidden" name="action" value="orunk_cancel_plan"> <input type="hidden" name="purchase_id_to_cancel" value="<?php echo esc_attr($active_ad_removal_purchase_id); ?>"> <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($cancel_nonces[$active_ad_removal_purchase_id]); ?>"> <button type="submit" class="orunk-button-danger orunk-button-sm"> <i class="fas fa-times mr-1"></i> Cancel Ad Removal </button> </form> <?php endif; ?> <?php else: ?> <div class="p-3 rounded-full bg-orange-100 inline-block mb-2"> <i class="fas fa-bullhorn text-xl text-orange-400"></i></div> <p class="text-sm font-medium text-gray-700 mb-2">Ads Currently Enabled</p> <?php if($ad_removal_plan_id): ?> <a href="<?php echo esc_url(add_query_arg('plan_id', $ad_removal_plan_id, home_url('/checkout/'))); ?>" class="orunk-button orunk-button-sm shine-effect bg-teal-500 hover:bg-teal-600"> <i class="fas fa-shield-alt"></i> Remove Ads ($<?php echo esc_html(number_format((float)$ad_removal_price, 2)); ?>/mo) </a> <?php else: ?> <p class="text-xs text-gray-400">(Ad removal plan unavailable)</p> <?php endif; ?> <?php endif; ?> </div> <div class="styled-card-footer"></div> </div>
            </div>


            <?php // --- PHP logic to group services by category (Unchanged)---
                 $services_by_category = ['wp' => [], 'api' => [], 'other' => []];
                 $found_active_service = false;
                 $now_gmt_for_grouping = current_time('timestamp', 1);
                 if (!empty($all_purchases)) {
                     foreach ($all_purchases as $purchase_item) {
                         $is_active = ($purchase_item['status'] ?? '') === 'active'; $expiry_ts = isset($purchase_item['expiry_date']) ? strtotime($purchase_item['expiry_date']) : null; $is_not_expired = ($expiry_ts === null || $expiry_ts > $now_gmt_for_grouping); $feature_key_item = $purchase_item['product_feature_key'] ?? 'unknown'; $category_slug = $purchase_item['feature_category'] ?? 'other'; // Added category slug retrieval here
                         if ($is_active && $is_not_expired && $feature_key_item !== 'ad_removal') {
                            $found_active_service = true;
                            // Use category slug for grouping
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

            <?php // --- WordPress Downloads Section (Includes Hook Structure and Conditional Download Button) --- ?>
            <?php if (!empty($services_by_category['wp'])): ?>
            <div class="mb-8">
                 <div class="styled-card-header mb-4 !rounded-md !border !border-b-2 !border-gray-300">
                     <h2 class="text-lg font-semibold text-gray-800">WordPress Downloads</h2>
                     <a href="<?php echo esc_url(get_permalink(get_page_by_path('orunk-catalog'))); ?>" class="orunk-button orunk-button-sm"> <i class="fas fa-plus"></i> Add Service </a>
                 </div>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                     <?php foreach ($services_by_category['wp'] as $purchase) : ?>
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
                             $category_slug = $purchase['feature_category'] ?? 'wp'; // Get category slug for context

                             // --- START: Call helper to check button visibility ---
                             $show_manage_button = ($orunk_frontend instanceof Custom_Orunk_Frontend)
                                                   ? $orunk_frontend->should_show_manage_plan_button($purchase, $plans_by_feature, $orunk_core)
                                                   : false; // Default to false if class isn't loaded properly
                             // --- END: Call helper ---
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
                                    <?php // *** ADDED: Conditional Download Button *** ?>
                                    <?php if ($feature_key === 'convojet_pro'): ?>
                                        <button type="button" class="orunk-button button-primary download-plugin-btn orunk-button-sm" data-purchase-id="<?php echo esc_attr($purchase_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('orunk_convojet_download_nonce')); ?>">
                                            <span class="button-text"><i class="fas fa-download mr-1"></i>Download</span>
                                            <span class="button-spinner" style="display: none;"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                                        </button>
                                    <?php endif; ?>
                                    <?php // *** END ADDED *** ?>

                                    <?php // --- START: Conditional Manage Plan Button --- ?>
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
                                    <?php // --- END: Conditional Manage Plan Button --- ?>

                                     <a href="<?php echo esc_url($docs_url); ?>" target="_blank" class="orunk-button-outline orunk-button-sm" title="Documentation"><i class="fas fa-book"></i>Docs</a>
                                     <a href="<?php echo esc_url($help_url); ?>" target="_blank" class="orunk-button-outline orunk-button-sm" title="Get Support"><i class="fas fa-question-circle"></i> Help</a>
                              </div>
                               <?php // Download Feedback Div (Moved here to be consistently below buttons) ?>
                               <?php if ($feature_key === 'convojet_pro'): ?>
                                   <div id="download-feedback-<?php echo esc_attr($purchase_id); ?>" class="w-full text-xs px-4 pb-2 text-center h-4"></div>
                               <?php endif; ?>
                          </div>
                     <?php endforeach; ?>
                 </div>
            </div>
            <?php endif; ?>


            <?php // --- API Services Section (Includes Hook Structure and Conditional Download Button) --- ?>
            <?php if (!empty($services_by_category['api'])): ?>
            <div class="mb-8">
                 <div class="styled-card-header mb-4 !rounded-md !border !border-b-2 !border-gray-300">
                     <h2 class="text-lg font-semibold text-gray-800">API Services</h2>
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
                             $category_slug = $purchase['feature_category'] ?? 'api'; // Get category slug for context

                             // --- START: Call helper to check button visibility ---
                             $show_manage_button = ($orunk_frontend instanceof Custom_Orunk_Frontend)
                                                   ? $orunk_frontend->should_show_manage_plan_button($purchase, $plans_by_feature, $orunk_core)
                                                   : false; // Default to false if class isn't loaded properly
                             // --- END: Call helper ---
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
                                      <?php // No download button expected for API services by default ?>

                                     <?php // --- START: Conditional Manage Plan Button --- ?>
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
                                     <?php // --- END: Conditional Manage Plan Button --- ?>

                                     <a href="<?php echo esc_url($docs_url); ?>" target="_blank" class="orunk-button-outline orunk-button-sm" title="Documentation"><i class="fas fa-book"></i>Docs</a>
                                     <a href="<?php echo esc_url($help_url); ?>" target="_blank" class="orunk-button-outline orunk-button-sm" title="Get Support"><i class="fas fa-question-circle"></i> Help</a>
                               </div>
                           </div>
                      <?php endforeach; ?>
                  </div>
             </div>
             <?php endif; ?>


             <?php // --- Other Services Section (Includes Hook Structure and Conditional Download Button) --- ?>
             <?php if (!empty($services_by_category['other'])): ?>
             <div class="mb-8">
                 <div class="styled-card-header mb-4 !rounded-md !border !border-b-2 !border-gray-300">
                     <h2 class="text-lg font-semibold text-gray-800">Other Active Services</h2>
                 </div>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                       <?php foreach ($services_by_category['other'] as $purchase) : ?>
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
                                 $category_slug = $purchase['feature_category'] ?? 'other'; // Get category slug for context

                                 // --- START: Call helper to check button visibility ---
                                 $show_manage_button = ($orunk_frontend instanceof Custom_Orunk_Frontend)
                                                       ? $orunk_frontend->should_show_manage_plan_button($purchase, $plans_by_feature, $orunk_core)
                                                       : false; // Default to false if class isn't loaded properly
                                 // --- END: Call helper ---
                            ?>
                            <div class="styled-card service-card" id="purchase-<?php echo esc_attr($purchase['id']); ?>">
                                <div class="styled-card-header !py-2 !px-4 justify-between">
                                    <div class="flex-1 min-w-0"> <?php do_action('orunk_dashboard_service_card_header', $purchase, $feature_key, $display_info); ?> </div>
                                    <span class="status-badge active">Active</span>
                                </div>
                                <div class="styled-card-body">
                                   <?php do_action('orunk_dashboard_service_card_body', $purchase, $feature_key); ?>
                                    <?php if ($is_switch_pending): ?> <div class="alert alert-warning !py-1.5 !px-3 !text-xs !mb-0 mt-4"> <i class="fas fa-clock"></i> Plan switch pending. </div> <?php endif; ?>
                                </div>
                                <div class="styled-card-footer">
                                     <?php // No download button expected for 'other' services by default ?>

                                      <?php // --- START: Conditional Manage Plan Button --- ?>
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
                                      <?php // --- END: Conditional Manage Plan Button --- ?>

                                     <a href="<?php echo esc_url($docs_url); ?>" target="_blank" class="orunk-button-outline orunk-button-sm" title="Documentation"><i class="fas fa-book"></i>Docs</a>
                                     <a href="<?php echo esc_url($help_url); ?>" target="_blank" class="orunk-button-outline orunk-button-sm" title="Get Support"><i class="fas fa-question-circle"></i> Help</a>
                                </div>
                            </div>
                       <?php endforeach; ?>
                  </div>
             </div>
             <?php endif; ?>

             <?php // --- Message if NO active services found (Unchanged) --- ?>
             <?php if (!$found_active_service) : ?>
                 <div class="styled-card"> <div class="styled-card-body text-center py-6"> <i class="fas fa-box-open text-3xl text-gray-300 mb-3"></i> <h3 class="text-sm font-medium text-gray-700 mb-1">No active services</h3> <p class="text-xs text-gray-500">You don't have any active services right now.</p></div></div>
             <?php endif; ?>


            <?php // --- Purchase History Section (Unchanged) --- ?>
            <div class="mb-6"> <div class="styled-card-header mb-4 !rounded-md !border !border-b-2 !border-gray-300"> <h2>Purchase History</h2> </div> <div class="styled-card !shadow-none !border-0"> <div class="styled-card-body !p-0 overflow-x-auto"> <?php if (empty($all_purchases)) : ?> <div class="text-center py-6 text-gray-500 text-sm"> <i class="fas fa-history text-3xl text-gray-300 mb-3"></i> <p>No purchase history found.</p> </div> <?php else : ?> <table class="styled-table"> <thead> <tr> <th>Date</th><th>Type</th><th>Plan</th><th>Feature</th><th>Amount</th><th>Status</th><th>Payment</th><th>Expiry</th><th>ID</th> </tr> </thead> <tbody id="history-table-body"> <?php $history_counter = 0; ?> <?php foreach ($all_purchases as $hist_purchase) : ?> <?php $history_counter++; $row_class = ($history_counter > 5) ? 'history-hidden' : ''; $hist_plan_name = $hist_purchase['plan_name'] ?? __('N/A', 'orunk-users'); $hist_feature_key = $hist_purchase['product_feature_key'] ?? 'other'; $hist_gateway = $hist_purchase['payment_gateway'] ?? 'N/A'; $hist_purchase_date = $hist_purchase['purchase_date'] ? date_i18n(get_option('date_format'), strtotime($hist_purchase['purchase_date'])) : '-'; $hist_expiry = $hist_purchase['expiry_date'] ? date_i18n(get_option('date_format'), strtotime($hist_purchase['expiry_date'])) : '-'; $hist_display = function_exists('orunk_get_feature_display_info') ? orunk_get_feature_display_info($hist_feature_key) : ['title' => ucfirst(str_replace('_', ' ', $hist_feature_key))]; $hist_formatted_id = '#ORD-' . esc_html($hist_purchase['id']); $hist_price_display = __('N/A'); if (!empty($hist_purchase['plan_details_snapshot'])) { $snapshot_data = json_decode($hist_purchase['plan_details_snapshot'], true); if ($snapshot_data && isset($snapshot_data['price'])) { $hist_price_display = '$' . number_format((float)$snapshot_data['price'], 2); } } $raw_type = $hist_purchase['transaction_type'] ?? 'purchase'; $hist_transaction_type_display = 'Purchase'; switch ($raw_type) { case 'purchase': $hist_transaction_type_display = 'Initial Purchase'; break; case 'renewal_success': $hist_transaction_type_display = 'Renewal'; break; case 'switch_success': $hist_transaction_type_display = 'Plan Switch'; break; case 'renewal_failure': $hist_transaction_type_display = 'Renewal Failed'; break; } $hist_payment_display = ucfirst(str_replace('_', ' ', $hist_gateway)); $hist_status_orig = $hist_purchase['status'] ?? 'unknown'; $hist_status_display = $hist_status_orig; $status_badge_class = 'unknown'; switch (strtolower($hist_status_orig)) { case 'active': $expiry_timestamp_hist = $hist_purchase['expiry_date'] ? strtotime($hist_purchase['expiry_date']) : null; $is_not_expired_hist = $expiry_timestamp_hist === null || $expiry_timestamp_hist > current_time('timestamp', 1); if (!$is_not_expired_hist) { $hist_status_display = 'expired'; $status_badge_class = 'expired'; } else { $status_badge_class = 'active'; } break; case 'pending payment': case 'pending': $status_badge_class = 'pending'; break; case 'cancelled': $status_badge_class = 'cancelled'; break; case 'failed': $status_badge_class = 'failed'; break; case 'expired': $status_badge_class = 'expired'; break; } ?> <tr class="<?php echo esc_attr($row_class); ?>"> <td><?php echo esc_html($hist_purchase_date); ?></td><td><?php echo esc_html($hist_transaction_type_display); ?></td><td><?php echo esc_html($hist_plan_name); ?></td><td><?php echo esc_html($hist_display['title']); ?></td><td><?php echo esc_html($hist_price_display); ?></td><td><span class="status-badge <?php echo esc_attr($status_badge_class); ?>"><?php echo esc_html(ucfirst($hist_status_display)); ?></span></td><td><?php echo esc_html($hist_payment_display); ?></td><td><?php echo esc_html($hist_expiry); ?></td><td class="font-mono text-xs"><?php echo esc_html($hist_formatted_id); ?></td> </tr> <?php endforeach; ?> </tbody> </table> <?php if (count($all_purchases) > 5): ?> <div class="text-center py-4"> <button id="show-more-history" class="orunk-button orunk-button-outline orunk-button-sm">Show More History</button> </div> <?php endif; ?> <?php endif; ?> </div> </div> </div>

         <?php endif; // End plugin active check ?>

    </div> <?php // End .dashboard-container ?>

    <?php // --- Modals (HTML Structure Unchanged) --- ?>
    <div id="profile-modal" class="modal-overlay hidden"> <?php /* Profile Modal HTML... */ ?> <div class="modal-content"> <button class="modal-close-btn" onclick="closeModal('profile-modal')"><i class="fas fa-times"></i></button> <div class="modal-header"> <h3 class="text-lg font-semibold flex items-center gap-2"><i class="fas fa-user-edit mr-2 text-indigo-500"></i> Edit Profile</h3> </div> <div class="modal-body !p-4"> <form id="profile-form" enctype="multipart/form-data"> <div id="profile-details-section"> <div class="profile-picture-grid-item"> <img id="profile-picture-preview-img" src="<?php echo esc_url(get_avatar_url($user_id, ['size' => 64])); ?>" alt="Profile Picture Preview" class="profile-picture-preview"> <div class="profile-picture-actions"> <label for="profile_picture" class="form-label mb-0">Profile Picture</label> <input type="file" name="profile_picture" id="profile_picture" accept="image/png, image/jpeg, image/gif"> <input type="hidden" name="remove_profile_picture" id="remove_profile_picture" value="0"> <div class="flex gap-2 mt-1"> <button type="button" id="upload-picture-btn" class="orunk-button-outline orunk-button-sm"><i class="fas fa-upload"></i> Upload New</button> <button type="button" id="remove-picture-btn" class="orunk-button-outline orunk-button-sm !border-red-300 !text-red-600 hover:!bg-red-50"><i class="fas fa-trash-alt"></i> Remove</button> </div> <p class="form-description !mt-1">Max 2MB (JPG, PNG, GIF)</p> </div> </div> <div class="profile-display-name-item"> <label for="profile_display_name" class="form-label">Display Name</label> <input type="text" name="display_name" id="profile_display_name" value="<?php echo esc_attr($current_user->display_name); ?>" class="form-input" required> </div> <div class="profile-email-item"> <label for="profile_email" class="form-label">Email Address <span class="text-red-500">*</span></label> <input type="email" name="email" id="profile_email" value="<?php echo esc_attr($current_user->user_email); ?>" class="form-input" required> <p class="form-description">Requires current password below if changing email or password.</p> </div> <div class="change-password-grid-item"> <p class="text-xs font-medium text-gray-700 mb-2" style="grid-column: 1 / -1;">Change Password (optional)</p> <div class="current-password-item"> <div> <label for="profile_current_password" class="form-label">Current Password</label> <input type="password" name="current_password" id="profile_current_password" class="form-input" placeholder="Required to change email or password"> </div> <div class="text-right mt-1"> <button type="button" id="show-forgot-password-modal" class="text-xs text-indigo-600 hover:underline focus:outline-none">Forgot Password?</button> </div> </div> <div class="new-password-item"> <label for="profile_new_password" class="form-label">New Password</label> <input type="password" name="new_password" id="profile_new_password" class="form-input" placeholder="Enter New password"> </div> <div class="confirm-password-item"> <label for="profile_confirm_password" class="form-label">Confirm New Password</label> <input type="password" name="confirm_password" id="profile_confirm_password" class="form-input"> </div> </div> </div> <div class="modal-footer mt-4" id="profile-form-footer"> <div id="modal-feedback-profile" class="text-xs text-right mr-auto h-4"></div> <button type="button" class="orunk-button-outline orunk-button-sm" onclick="closeModal('profile-modal')"> Cancel </button> <button type="submit" id="save-profile-submit-btn" class="orunk-button orunk-button-sm"> <i class="fas fa-save mr-1"></i> Save Changes <span class="save-spinner spinner" style="display: none;"></span> </button> </div> </form> <div id="forgot-password-section" class="hidden forgot-password-section"> <div id="request-otp-section"> <h4 class="text-md font-medium mb-2">Reset Password</h4> <p class="form-description">Enter your account email address below to receive a password reset OTP.</p> <div> <label for="forgot-email" class="form-label">Email Address</label> <input type="email" name="forgot_email" id="forgot-email" value="<?php echo esc_attr($current_user->user_email); ?>" class="form-input" required placeholder="Your account email"> </div> <div id="modal-feedback-forgot" class="h-5 mt-2"></div> <div class="modal-footer mt-4 forgot-password-footer-buttons"> <button type="button" id="back-to-profile-view" class="orunk-button-outline orunk-button-sm"><i class="fas fa-arrow-left mr-1"></i> Back</button> <button type="button" id="submit-forgot-email" class="orunk-button orunk-button-sm"> Send OTP <span class="forgot-spinner spinner" style="display: none;"></span> </button> </div> </div> <div id="otp-verify-section" class="hidden mt-4"> <h4 class="text-md font-medium mb-2">Enter OTP</h4> <p class="form-description">An OTP has been sent to <strong id="otp-sent-to-email">your email</strong>. Enter it below.</p> <div> <label for="otp-code" class="form-label">One-Time Password</label> <input type="text" inputmode="numeric" pattern="[0-9]*" name="otp_code" id="otp-code" class="form-input otp-input" required placeholder="Enter OTP"> </div> <div id="modal-feedback-verify" class="h-5 mt-2"></div> <div class="modal-footer mt-4 forgot-password-footer-buttons"> <button type="button" id="back-to-email-entry" class="orunk-button-outline orunk-button-sm"><i class="fas fa-arrow-left mr-1"></i> Back</button> <button type="button" id="submit-verify-otp" class="orunk-button orunk-button-sm"> Verify OTP <span class="verify-spinner spinner" style="display: none;"></span> </button> </div> </div> <div id="reset-password-otp-section" class="hidden mt-4"> <h4 class="text-md font-medium mb-2">Set New Password</h4> <p class="form-description">Enter and confirm your new password.</p> <div class="grid grid-cols-1 md:grid-cols-2 gap-3"> <div> <label for="reset-new-password" class="form-label">New Password</label> <input type="password" name="reset_new_password" id="reset-new-password" class="form-input" required placeholder="Enter new password"> </div> <div> <label for="reset-confirm-password" class="form-label">Confirm New Password</label> <input type="password" name="reset_confirm_password" id="reset-confirm-password" class="form-input" required placeholder="Confirm new password"> </div> </div> <div id="modal-feedback-reset" class="h-5 mt-2"></div> <div class="modal-footer mt-4 forgot-password-footer-buttons"> <button type="button" id="back-to-otp-entry" class="orunk-button-outline orunk-button-sm"><i class="fas fa-arrow-left mr-1"></i> Back</button> <button type="button" id="submit-reset-password-otp" class="orunk-button orunk-button-sm"> Set New Password <span class="reset-spinner spinner" style="display: none;"></span> </button> </div> </div> </div> </div> </div> </div>
     <div id="billing-modal" class="modal-overlay hidden"> <?php /* Billing Modal HTML... */ ?> <div class="modal-content"> <button class="modal-close-btn" onclick="closeModal('billing-modal')"><i class="fas fa-times"></i></button> <div class="modal-header"> <h3 class="text-lg font-semibold flex items-center gap-2"><i class="fas fa-map-marker-alt mr-2 text-indigo-500"></i> Billing Address</h3> </div> <form id="billing-form"> <div class="modal-body !p-4"> <div class="grid grid-cols-1 md:grid-cols-2 gap-2"> <div><label for="billing_first_name" class="form-label">First Name</label><input type="text" name="billing_first_name" id="billing_first_name" class="form-input" placeholder="Enter first name"></div> <div><label for="billing_last_name" class="form-label">Last Name</label><input type="text" name="billing_last_name" id="billing_last_name" class="form-input" placeholder="Enter last name"></div> <div class="md:col-span-2"><label for="billing_company" class="form-label">Company Name (Optional)</label><input type="text" name="billing_company" id="billing_company" class="form-input"></div> <div class="md:col-span-2"><label for="billing_address_1" class="form-label">Street Address</label><input type="text" name="billing_address_1" id="billing_address_1" class="form-input" placeholder="House number and street name"></div> <div class="md:col-span-2"><label for="billing_address_2" class="form-label visually-hidden">Apartment, suite, etc. (optional)</label><input type="text" name="billing_address_2" id="billing_address_2" class="form-input" placeholder="Apartment, suite, unit, etc. (optional)"></div> <div><label for="billing_city" class="form-label">Town / City</label><input type="text" name="billing_city" id="billing_city" class="form-input"></div> <div><label for="billing_state" class="form-label">State / County</label><input type="text" name="billing_state" id="billing_state" class="form-input"></div> <div><label for="billing_postcode" class="form-label">Postcode / ZIP</label><input type="text" name="billing_postcode" id="billing_postcode" class="form-input"></div> <div><label for="billing_country" class="form-label">Country</label><input type="text" name="billing_country" id="billing_country" class="form-input"></div> <div class="md:col-span-2"><label for="billing_phone" class="form-label">Phone</label><input type="tel" name="billing_phone" id="billing_phone" class="form-input" placeholder="Enter phone number"></div> </div> </div> <div class="modal-footer"> <div id="modal-feedback-billing" class="text-xs text-right mr-auto h-4"></div> <button type="button" class="orunk-button-outline orunk-button-sm" onclick="closeModal('billing-modal')">Cancel</button> <button type="submit" id="save-billing-submit-btn" class="orunk-button orunk-button-sm"> <i class="fas fa-save mr-1"></i> Save Address <span class="save-spinner spinner" style="display: none;"></span> </button> </div> </form> </div> </div>
     <div id="plan-modal" class="modal-overlay hidden"> <?php /* Plan Change Modal HTML... */ ?> <div class="modal-content"> <div class="modal-header"> <h3 class="text-lg font-semibold flex items-center gap-2"><i class="fas fa-exchange-alt mr-2 text-indigo-500"></i><span id="plan-modal-title">Change Plan</span></h3> <button class="modal-close-btn" onclick="closeModal('plan-modal')"><i class="fas fa-times"></i></button> </div> <div class="modal-body !pt-4"> <div id="current-plan-info" class="mb-3 p-3 bg-indigo-50 border border-indigo-200 rounded-md text-sm" style="display: none;"> <p id="current-plan-details"></p> </div> <div id="plan-renewal-section" class="text-xs text-gray-500 flex justify-between items-center mb-3 p-3 border-t border-b border-gray-100 hidden"> <span><i class="fas fa-calendar-times w-3 inline-block text-center opacity-60 mr-1"></i> Renews: <span class="font-medium text-gray-700" id="plan-modal-expiry-date">N/A</span></span> <div class="flex items-center gap-1" title="Toggle Auto-Renewal"> <span class="text-xs text-gray-500 mr-1">Renew</span> <label class="toggle-switch"> <input type="checkbox" class="auto-renew-toggle" id="plan-modal-auto-renew-toggle" data-purchase-id=""> <span class="toggle-slider"></span> </label> </div> </div> <p class="text-sm text-gray-600 mb-3">Select a new plan for <strong id="plan-modal-service-name" class="text-gray-800">Service</strong>.</p> <form id="plan-modal-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"> <input type="hidden" name="action" value="orunk_switch_plan"> <input type="hidden" name="current_purchase_id" id="plan-modal-purchase-id" value=""> <input type="hidden" name="new_plan_id" id="plan-modal-selected-plan-id" value=""> <input type="hidden" name="_wpnonce" id="plan-modal-nonce" value=""> <div id="plan-modal-options" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-3 max-h-[45vh] overflow-y-auto p-1"> <div class="flex justify-center items-center p-8 md:col-span-2 lg:col-span-3"><div class="spinner"></div></div> </div> </form> </div> <div class="modal-footer"> <form id="plan-modal-cancel-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="contents" onsubmit="return confirm('Are you sure you want to cancel this subscription?');"> <input type="hidden" name="action" value="orunk_cancel_plan"> <input type="hidden" name="purchase_id_to_cancel" id="plan-modal-cancel-purchase-id" value=""> <input type="hidden" name="_wpnonce" id="plan-modal-cancel-nonce" value=""> <button type="submit" id="confirm-plan-cancel" class="orunk-button-danger orunk-button-sm"> <i class="fas fa-times mr-1"></i> Cancel Subscription </button> </form> <div id="modal-feedback-plan" class="text-xs text-right mr-auto h-4 order-first w-full md:w-auto md:order-none"></div> <button type="button" class="orunk-button-outline orunk-button-sm" onclick="closeModal('plan-modal')">Close</button> <button type="button" class="orunk-button orunk-button-sm" id="confirm-plan-change" disabled> <i class="fas fa-check mr-1"></i> Confirm Change </button> </div> </div> </div>

<?php get_footer(); ?>

<?php // --- JavaScript (Includes Download Button Handler and Refined Animation Logic, and openModal logging) --- ?>
<script type="text/javascript">
    const orunkDashboardData={ajaxUrl:'<?php echo admin_url('admin-ajax.php'); ?>',regenNonce:'<?php echo wp_create_nonce('orunk_regenerate_api_key_nonce'); ?>',profileNonce:'<?php echo wp_create_nonce('orunk_update_profile_nonce'); ?>',billingNonce:'<?php echo wp_create_nonce('orunk_billing_address_nonce'); ?>',autoRenewNonce:'<?php echo wp_create_nonce('orunk_auto_renew_nonce'); ?>',otpRequestNonce:'<?php echo wp_create_nonce('orunk_request_otp_action'); ?>',otpVerifyNonce:'<?php echo wp_create_nonce('orunk_verify_otp_action'); ?>',otpResetNonce:'<?php echo wp_create_nonce('orunk_reset_password_otp_action'); ?>',currentUserId:<?php echo esc_js($user_id); ?>};const orunkAvailablePlans=<?php echo wp_json_encode($plans_by_feature); ?>;const orunkAllPurchases=<?php echo wp_json_encode($all_purchases); ?>;const orunkCancelNonces=<?php echo wp_json_encode($cancel_nonces); ?>;const orunkSwitchNonces=<?php echo wp_json_encode($switch_nonces ?? []); ?>;let currentBillingAddress=null;let forgotPasswordUserIdentifier='';const modals=document.querySelectorAll('.modal-overlay');const billingForm=document.getElementById('billing-form');const profileForm=document.getElementById('profile-form');const historyTableBody=document.getElementById('history-table-body');const showMoreHistoryBtn=document.getElementById('show-more-history');document.addEventListener('DOMContentLoaded',function(){console.log('Dashboard DOM Loaded.');setupEventListeners();displayInitialMessages();fetchBillingAddress();initializePurchaseHistoryView();});function setupEventListeners(){console.log('Dashboard JS: setupEventListeners called.');document.body.addEventListener('click',function(event){console.log('Dashboard JS: Body click detected. Target:',event.target);const copyBtn=event.target.closest('.orunk-copy-button');const regenBtn=event.target.closest('.orunk-regenerate-key-button');const profileBtn=event.target.closest('#edit-profile-btn');const billingBtn=event.target.closest('#manage-address-btn');const changePlanBtn=event.target.closest('.change-plan-btn');const planCard=event.target.closest('#plan-modal .plan-card');const confirmPlanBtn=event.target.closest('#confirm-plan-change');const uploadPicBtn=event.target.closest('#upload-picture-btn');const removePicBtn=event.target.closest('#remove-picture-btn');const showForgotBtn=event.target.closest('#show-forgot-password-modal');const backToProfileBtn=event.target.closest('#back-to-profile-view');const submitForgotBtn=event.target.closest('#submit-forgot-email');const backToEmailEntryBtn=event.target.closest('#back-to-email-entry');const submitVerifyOtpBtn=event.target.closest('#submit-verify-otp');const backToOtpEntryBtn=event.target.closest('#back-to-otp-entry');const submitResetPasswordOtpBtn=event.target.closest('#submit-reset-password-otp');const showMoreHistory=event.target.closest('#show-more-history');const downloadBtn=event.target.closest('.download-plugin-btn');if(copyBtn){console.log('Handling copy key click.');handleCopyKey(copyBtn);}
if(regenBtn){console.log('Handling regenerate key click.');handleRegenerateKey(regenBtn);}
if(profileBtn){console.log('Handling Edit Profile button click.');openModal('profile-modal');}
if(billingBtn){console.log('Handling Manage Address button click.');openModal('billing-modal');}
if(changePlanBtn){console.log('Handling Manage Plan button click.');openPlanChangeModal(changePlanBtn);}
if(planCard){console.log('Handling plan card select.');selectPlanCard(planCard);}
if(confirmPlanBtn){console.log('Handling confirm plan change click.');handlePlanChangeConfirm(confirmPlanBtn);}
if(uploadPicBtn){console.log('Handling upload picture click.');document.getElementById('profile_picture').click();}
if(removePicBtn){console.log('Handling remove picture click.');handleRemovePicture();}
if(showForgotBtn){console.log('Handling show forgot password click.');showForgotPasswordView();}
if(backToProfileBtn){console.log('Handling back to profile click.');showProfileView();}
if(submitForgotBtn){console.log('Handling request OTP submit.');handleForgotPasswordSubmit(submitForgotBtn);}
if(backToEmailEntryBtn){console.log('Handling back to email entry click.');transitionForgotPasswordStep(1);}
if(submitVerifyOtpBtn){console.log('Handling verify OTP submit.');handleVerifyOtpSubmit(submitVerifyOtpBtn);}
if(backToOtpEntryBtn){console.log('Handling back to OTP entry click.');transitionForgotPasswordStep(2);}
if(submitResetPasswordOtpBtn){console.log('Handling reset password submit.');handleResetPasswordOtpSubmit(submitResetPasswordOtpBtn);}
if(showMoreHistory){console.log('Handling show more history click.');handleShowMoreHistory(showMoreHistory);}
if(downloadBtn){console.log('Handling download plugin click.');handlePluginDownload(downloadBtn);}});if(profileForm){console.log("Attaching submit listener to profile form.");profileForm.addEventListener('submit',handleProfileSave);}
if(billingForm){console.log("Attaching submit listener to billing form.");billingForm.addEventListener('submit',handleBillingSave);}
document.body.addEventListener('change',function(event){if(event.target.classList.contains('auto-renew-toggle')){console.log("Handling auto-renew toggle change.");handleAutoRenewToggle(event.target);}
if(event.target.id==='profile_picture'){console.log("Handling profile picture file change.");previewProfilePicture(event.target);}});modals.forEach(modal=>{if(!modal)return;const closeBtn=modal.querySelector('.modal-close-btn');const cancelBtn=modal.querySelector('.modal-footer button.orunk-button-outline');const modalId=modal.id;if(closeBtn){closeBtn.addEventListener('click',()=>{console.log(`Close button clicked for modal: ${modalId}`);closeModal(modalId);});}
if(cancelBtn&&!cancelBtn.closest('#plan-modal-cancel-form')&&!cancelBtn.closest('#forgot-password-section')){cancelBtn.addEventListener('click',()=>{console.log(`Cancel button clicked for modal: ${modalId}`);closeModal(modalId);});}
modal.addEventListener('click',(event)=>{if(event.target===modal){console.log(`Overlay clicked for modal: ${modalId}`);closeModal(modalId);}});});}
function displayInitialMessages(){const messagesContainer=document.getElementById('messages');if(!messagesContainer)return;const urlParams=new URLSearchParams(window.location.search);const errorMsg=urlParams.get('orunk_error');const successMsg=<?php echo json_encode(get_transient('orunk_purchase_message_' . $user_id) ?: null); delete_transient('orunk_purchase_message_' . $user_id); ?>;if(errorMsg)displayMessage('error',decodeURIComponent(errorMsg));if(successMsg)displayMessage('success',successMsg);}
function displayMessage(type,text,targetElement=null){const container=targetElement||document.getElementById('messages');if(!container)return;const ajaxMessageDiv=document.getElementById('ajax-message');const ajaxTextSpan=document.getElementById('ajax-text');if(ajaxMessageDiv&&ajaxTextSpan){ajaxTextSpan.innerHTML=escapeHTML(text);ajaxMessageDiv.className=`alert alert-${type} !text-sm`;ajaxMessageDiv.classList.remove('hidden');const icon=ajaxMessageDiv.querySelector('i');if(icon)icon.className=`fas ${type==='success'?'fa-check-circle':(type==='error'?'fa-times-circle':'fa-info-circle')}`;setTimeout(()=>{ajaxMessageDiv.classList.add('hidden');},5000);}else{const alertDiv=document.createElement('div');alertDiv.classList.add('alert',`alert-${type}`,'!text-sm');alertDiv.innerHTML=`<i class="fas ${type==='success'?'fa-check-circle':(type==='error'?'fa-times-circle':'fa-info-circle')}"></i> ${escapeHTML(text)}`;container.insertBefore(alertDiv,container.firstChild);setTimeout(()=>{alertDiv.remove();},5000);}}
function openModal(modalId){
    // --- Start JS Debug Log Changes ---
    console.log('>>> Entering openModal for ID:', modalId);
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.error(`Modal with ID ${modalId} not found.`);
        return;
    }
    console.log(`Modal ${modalId} - Before removing hidden: classes=${modal.className}`);
    modal.classList.remove('hidden');
    void modal.offsetWidth; // Force reflow

    console.log(`Modal ${modalId} - Attempting to add 'active' class...`);
    modal.classList.add('active');

    // Check if 'active' was added
    if (modal.classList.contains('active')) {
        console.log(`Modal ${modalId} - SUCCESSFULLY added 'active' class. Current classes: ${modal.className}`);
    } else {
         console.error(`Modal ${modalId} - FAILED to add 'active' class! Current classes: ${modal.className}`);
         // Consider adding a fallback display message here if needed
    }
    // --- End JS Debug Log Changes ---

    const form = modal.querySelector('form');
    const feedback = modal.querySelector('[id^="modal-feedback-"]');

    if(feedback){
        feedback.textContent = '';
        feedback.className = feedback.className.replace(/text-(red|green)-[0-9]+/g,'');
    }
    const forgotFeedback = modal.querySelector('#modal-feedback-forgot');
    if (forgotFeedback) forgotFeedback.textContent = '';

    // Conditional population / reset
    if (modalId === 'billing-modal' && form && currentBillingAddress) {
        populateBillingForm(form, currentBillingAddress);
    } else if (form && modalId !== 'plan-modal') { // Don't reset plan modal form fields here
        form.reset();
        if (modalId === 'profile-modal') {
            showProfileView(); // Reset profile view to details
            const previewImg = document.getElementById('profile-picture-preview-img');
            const removeInput = document.getElementById('remove_profile_picture');
            if (previewImg) previewImg.src = '<?php echo esc_url(get_avatar_url($user_id, ['size' => 64])); ?>';
            if (removeInput) removeInput.value = '0';
        }
    }

    const firstInput = modal.querySelector('input:not([type=hidden]), select, textarea');
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 100);
    }
     console.log(`<<< Exiting openModal for ID: ${modalId}`); // Add Log
}
function closeModal(modalId){console.log('Dashboard JS: closeModal called for ID:',modalId);const modal=document.getElementById(modalId);if(modal&&modal.classList.contains('active')){console.log(`Modal ${modalId} - Before closing: classes=${modal.className}`);modal.classList.remove('active');const handleTransitionEnd=(event)=>{if(event.target===modal&&(event.propertyName==='opacity'||event.propertyName==='transform')){modal.classList.add('hidden');console.log(`Modal ${modalId} - AFTER adding 'hidden' (on transitionend): classes=${modal.className}`);modal.removeEventListener('transitionend',handleTransitionEnd);}};modal.addEventListener('transitionend',handleTransitionEnd);setTimeout(()=>{if(!modal.classList.contains('hidden')){modal.classList.add('hidden');modal.removeEventListener('transitionend',handleTransitionEnd);console.warn(`Modal ${modalId} - Added 'hidden' via fallback timeout.`);}},300);}else if(modal){console.log(`Modal ${modalId} - closeModal called, but modal was not active. Current classes: ${modal.className}`);modal.classList.add('hidden');modal.classList.remove('active');}else{console.error(`Modal ${modalId} not found in closeModal.`);}
if(modalId==='profile-modal'){showProfileView();}}
function handleCopyKey(button){const fullKey=button.dataset.fullKey;if(!fullKey)return;navigator.clipboard.writeText(fullKey).then(()=>{const originalIconHTML=button.innerHTML;button.innerHTML='<i class="fas fa-check text-green-500 m-0"></i>';button.disabled=true;button.title="Copied!";setTimeout(()=>{button.innerHTML=originalIconHTML;button.disabled=false;button.title=button.closest('.license-key-display')?'Copy License Key':'Copy API Key';},1500);}).catch(err=>{console.error('Failed to copy key: ',err);});}
function handleRegenerateKey(button){const purchaseId=button.dataset.purchaseId;const feedbackDiv=document.getElementById(`regenerate-feedback-${purchaseId}`);const spinner=button.querySelector('.regenerate-spinner');if(!purchaseId||!confirm('Are you sure? The old API key will stop working immediately.'))return;if(feedbackDiv){feedbackDiv.textContent='';feedbackDiv.className='text-xs mt-1 h-4';}
button.disabled=true;if(spinner)spinner.style.display='inline-block';const formData=new FormData();formData.append('action','orunk_regenerate_api_key');formData.append('nonce',orunkDashboardData.regenNonce);formData.append('purchase_id',purchaseId);fetch(orunkDashboardData.ajaxUrl,{method:'POST',body:formData}).then(response=>response.json()).then(data=>{if(data.success){if(feedbackDiv){feedbackDiv.textContent=data.message||'Key regenerated!';feedbackDiv.classList.add('text-green-600');}
const displayDiv=document.getElementById(`api-key-display-${purchaseId}`);const copyButton=button.closest('.flex').querySelector('.orunk-copy-button');if(displayDiv)displayDiv.textContent=data.data.masked_key;if(copyButton)copyButton.dataset.fullKey=data.data.new_key;}else{if(feedbackDiv){feedbackDiv.textContent=data.data?.message||'Error regenerating key.';feedbackDiv.classList.add('text-red-600');}}}).catch(error=>{console.error('Regen key error:',error);if(feedbackDiv){feedbackDiv.textContent='Request failed.';feedbackDiv.classList.add('text-red-600');}}).finally(()=>{button.disabled=false;if(spinner)spinner.style.display='none';setTimeout(()=>{if(feedbackDiv)feedbackDiv.textContent='';feedbackDiv.className='text-xs mt-1 h-4';},4000);});}
function handleAutoRenewToggle(toggleInput){const purchaseId=toggleInput.dataset.purchaseId;const isEnabled=toggleInput.checked?'1':'0';console.log(`Toggling auto-renew for Purchase ${purchaseId} to ${isEnabled}`);toggleInput.disabled=true;const formData=new FormData();formData.append('action','orunk_toggle_auto_renew');formData.append('nonce',orunkDashboardData.autoRenewNonce);formData.append('purchase_id',purchaseId);formData.append('enabled',isEnabled);fetch(orunkDashboardData.ajaxUrl,{method:'POST',body:formData}).then(response=>response.json()).then(data=>{if(data.success){displayMessage('success',data.data.message||'Auto-renew status updated.');}else{displayMessage('error',data.data?.message||'Failed to update auto-renew status.');toggleInput.checked=!toggleInput.checked;}}).catch(error=>{console.error('Auto Renew Error:',error);displayMessage('error','An error occurred while updating auto-renew.');toggleInput.checked=!toggleInput.checked;}).finally(()=>{toggleInput.disabled=false;});}
function handleProfileSave(event){event.preventDefault();const form=event.target;const button=form.querySelector('button[type="submit"]');showButtonSpinner(button,true);setModalFeedback('profile-modal','',true);const formData=new FormData(form);formData.append('action','orunk_update_profile');formData.append('nonce',orunkDashboardData.profileNonce);fetch(orunkDashboardData.ajaxUrl,{method:'POST',body:formData}).then(response=>response.json()).then(data=>{if(data.success){setModalFeedback('profile-modal',data.message||'Profile updated!',true);const newAvatarUrl=data.data?.new_avatar_url;if(newAvatarUrl){const previewImg=document.getElementById('profile-picture-preview-img');if(previewImg)previewImg.src=newAvatarUrl;const accountCard=document.querySelector('.styled-card .fa-user')?.closest('.styled-card');if(accountCard){const accountCardAvatar=accountCard.querySelector('.styled-card-body .rounded-full');if(accountCardAvatar)accountCardAvatar.src=newAvatarUrl;}}
const accountCardName=document.querySelector('.styled-card .fa-user')?.closest('.styled-card').querySelector('.text-sm.font-medium');if(accountCardName)accountCardName.textContent=formData.get('display_name');const accountCardEmail=document.querySelector('.styled-card .fa-user')?.closest('.styled-card').querySelector('.text-xs.text-gray-500');if(accountCardEmail)accountCardEmail.textContent=formData.get('email');setTimeout(()=>{closeModal('profile-modal');},1500);}else{setModalFeedback('profile-modal',data.data?.message||'Update failed.',false);}}).catch(error=>{console.error('Profile Save Error:',error);setModalFeedback('profile-modal','An error occurred.',false);}).finally(()=>{showButtonSpinner(button,false);form.elements['current_password'].value='';form.elements['new_password'].value='';form.elements['confirm_password'].value='';const removeInput=document.getElementById('remove_profile_picture');if(removeInput)removeInput.value='0';const fileInput=document.getElementById('profile_picture');if(fileInput)fileInput.value='';});}
function fetchBillingAddress(){const displayDiv=document.getElementById('billing-address-display');const button=document.getElementById('manage-address-btn');if(!displayDiv||!button){console.error("Billing display or button not found");return;}
displayDiv.innerHTML='<div class="space-y-1 animate-pulse"><div class="h-3 bg-gray-200 rounded w-3/4 skeleton"></div><div class="h-3 bg-gray-200 rounded w-full skeleton"></div><div class="h-3 bg-gray-200 rounded w-1/2 skeleton"></div></div>';button.disabled=true;const formData=new FormData();formData.append('action','orunk_get_billing_address');formData.append('nonce',orunkDashboardData.billingNonce);fetch(orunkDashboardData.ajaxUrl,{method:'POST',body:formData}).then(response=>response.json()).then(data=>{if(data.success&&data.data.address){currentBillingAddress=data.data.address;displayFormattedAddress(displayDiv,currentBillingAddress);button.disabled=false;}else{currentBillingAddress=null;console.error('Failed to fetch billing address:',data.data?.message||'Unknown error');displayDiv.innerHTML='<p class="text-gray-500 text-xs italic">Could not load billing address.</p>';button.disabled=false;}}).catch(error=>{currentBillingAddress=null;console.error('Error fetching billing address:',error);displayDiv.innerHTML='<p class="text-red-500 text-xs italic">Error loading address.</p>';button.disabled=false;});}
function displayFormattedAddress(element,address){if(!address||typeof address!=='object'){element.innerHTML='<p class="text-gray-500 text-xs italic">No billing address on file.</p>';return;}
let displayHTML='';let hasAddress=false;if(address.billing_first_name||address.billing_last_name){displayHTML+=`<p><strong>${escapeHTML(address.billing_first_name||'')} ${escapeHTML(address.billing_last_name||'')}</strong></p>`;hasAddress=true;}
if(address.billing_address_1){displayHTML+=`<p>${escapeHTML(address.billing_address_1)}</p>`;hasAddress=true;}
if(address.billing_address_2){displayHTML+=`<p>${escapeHTML(address.billing_address_2)}</p>`;hasAddress=true;}
let cityStateZip='';if(address.billing_city)cityStateZip+=escapeHTML(address.billing_city);if(address.billing_city&&address.billing_state)cityStateZip+=', ';if(address.billing_state)cityStateZip+=escapeHTML(address.billing_state);if(cityStateZip&&address.billing_postcode)cityStateZip+=' ';if(address.billing_postcode)cityStateZip+=escapeHTML(address.billing_postcode);if(cityStateZip){displayHTML+=`<p>${cityStateZip}</p>`;hasAddress=true;}
if(address.billing_country){displayHTML+=`<p>${escapeHTML(address.billing_country)}</p>`;hasAddress=true;}
if(address.billing_phone){displayHTML+=`<p>Tel: ${escapeHTML(address.billing_phone)}</p>`;hasAddress=true;}
element.innerHTML=hasAddress?displayHTML:'<p class="text-gray-500 text-xs italic">No billing address on file.</p>';}
function populateBillingForm(form,address){if(!form||!address||typeof address!=='object')return;const keys=['first_name','last_name','company','address_1','address_2','city','state','postcode','country','phone'];keys.forEach(key=>{const inputName=`billing_${key}`;if(form.elements[inputName]){form.elements[inputName].value=address[inputName]||'';}});if(form.elements['billing_email']){form.elements['billing_email'].value=address.billing_email||'';}}
function handleBillingSave(event){event.preventDefault();const form=event.target;const button=form.querySelector('button[type="submit"]');showButtonSpinner(button,true);setModalFeedback('billing-modal','',true);const formData=new FormData(form);formData.append('action','orunk_save_billing_address');formData.append('nonce',orunkDashboardData.billingNonce);fetch(orunkDashboardData.ajaxUrl,{method:'POST',body:formData}).then(response=>response.json()).then(data=>{if(data.success){setModalFeedback('billing-modal',data.message||'Address saved!',true);fetchBillingAddress();setTimeout(()=>closeModal('billing-modal'),1500);}else{setModalFeedback('billing-modal',data.data?.message||'Save failed.',false);}}).catch(error=>{console.error('Billing Save Error:',error);setModalFeedback('billing-modal','An error occurred.',false);}).finally(()=>{showButtonSpinner(button,false);});}
function openPlanChangeModal(button){console.log('Dashboard JS: openPlanChangeModal called.');const purchaseId=button.dataset.purchaseId;const featureKey=button.dataset.featureKey;const currentPlanId=button.dataset.currentPlanId;const serviceName=button.dataset.serviceName||'this service';const expiryDateDisplay=button.dataset.expiryDateDisplay||'N/A';const autoRenewEnabled=button.dataset.autoRenewEnabled=='1';console.log(`Opening Plan Modal - Purchase ID: ${purchaseId}, Feature: ${featureKey}, Current Plan: ${currentPlanId}, Expires: ${expiryDateDisplay}, Renew: ${autoRenewEnabled}`);const modal=document.getElementById('plan-modal');if(!modal){console.error("Plan modal element not found!");return;}
const modalOptionsContainer=document.getElementById('plan-modal-options');const modalForm=document.getElementById('plan-modal-form');const confirmBtn=document.getElementById('confirm-plan-change');const nonceField=document.getElementById('plan-modal-nonce');const currentPlanInfoDiv=document.getElementById('current-plan-info');const currentPlanDetailsP=document.getElementById('current-plan-details');const cancelPurchaseIdField=document.getElementById('plan-modal-cancel-purchase-id');const cancelNonceField=document.getElementById('plan-modal-cancel-nonce');const renewalSection=document.getElementById('plan-renewal-section');const expiryDateSpan=document.getElementById('plan-modal-expiry-date');const autoRenewToggle=document.getElementById('plan-modal-auto-renew-toggle');console.log('Global orunkAvailablePlans data:',orunkAvailablePlans);if(!featureKey||typeof orunkAvailablePlans[featureKey]==='undefined'){console.error(`Error: No feature key provided or no plans found for the key "${featureKey}" in orunkAvailablePlans.`);alert('Could not find available plans for this service.');return;}
console.log(`Plans found for feature key "${featureKey}":`,orunkAvailablePlans[featureKey]);const currentPurchase=orunkAllPurchases.find(p=>p.id==purchaseId);const availablePlans=orunkAvailablePlans[featureKey].filter(p=>p.id!=currentPlanId);const currentPlan=currentPurchase?orunkAvailablePlans[featureKey]?.find(p=>p.id==currentPlanId):null;const currentPrice=currentPlan?parseFloat(currentPlan.price):0;console.log(`Current Plan Details (if found):`,currentPlan);console.log(`Filtered available plans (excluding current plan ID ${currentPlanId}):`,availablePlans);document.getElementById('plan-modal-title').textContent=`Upgrade / Manage Plan`;document.getElementById('plan-modal-service-name').textContent=serviceName;modalForm.elements['current_purchase_id'].value=purchaseId;modalForm.elements['new_plan_id'].value='';nonceField.value=orunkSwitchNonces[purchaseId]||'';if(!nonceField.value)console.error(`Switch plan nonce for purchase ${purchaseId} is missing!`);cancelPurchaseIdField.value=purchaseId;cancelNonceField.value=orunkCancelNonces[purchaseId]||'';if(!cancelNonceField.value)console.error(`Cancel nonce for purchase ${purchaseId} is missing!`);confirmBtn.disabled=true;if(currentPlan&&currentPlanDetailsP){const reqDay=currentPlan.requests_per_day??'Unltd';const reqMonth=currentPlan.requests_per_month??'Unltd';currentPlanDetailsP.innerHTML=`<span class="font-medium">${escapeHTML(currentPlan.plan_name)}</span> - $${currentPrice.toFixed(2)}/mo <span class="text-gray-500 ml-2">(${reqDay}/${reqMonth} req.)</span>`;currentPlanInfoDiv.style.display='block';}else{currentPlanInfoDiv.style.display='none';if(currentPlanDetailsP)currentPlanDetailsP.innerHTML='';}
if(renewalSection&&expiryDateSpan&&autoRenewToggle){expiryDateSpan.textContent=escapeHTML(expiryDateDisplay);autoRenewToggle.checked=autoRenewEnabled;autoRenewToggle.dataset.purchaseId=purchaseId;renewalSection.classList.remove('hidden');}else{console.error('Could not find renewal section elements in plan modal.');if(renewalSection)renewalSection.classList.add('hidden');}
let optionsHTML='';if(availablePlans.length>0){optionsHTML='';availablePlans.forEach(plan=>{const price=parseFloat(plan.price).toFixed(2);const priceDiff=parseFloat(plan.price)-currentPrice;let priceDiffHtml='';if(priceDiff>0)priceDiffHtml=`<span class="text-xs text-green-600 ml-1 price-diff">(+$${priceDiff.toFixed(2)})</span>`;else if(priceDiff<0)priceDiffHtml=`<span class="text-xs text-red-600 ml-1 price-diff">(-$${Math.abs(priceDiff).toFixed(2)})</span>`;const reqDay=plan.requests_per_day??'Unlimited';const reqMonth=plan.requests_per_month??'Unlimited';const isOneTime=plan.is_one_time=='1';const durationText=isOneTime?'Lifetime Access':`${plan.duration_days} days`;let featuresList='';if(featureKey==='convojet_pro'){featuresList+=`<li><i class="fas fa-check"></i> Pro Features</li>`;featuresList+=`<li><i class="fas fa-check"></i> ${durationText}</li>`;}else if(featureKey.includes('_api')||featureKey.includes('bin')){featuresList+=`<li><i class="fas fa-check"></i> ${reqDay} daily req.</li>`;featuresList+=`<li><i class="fas fa-check"></i> ${reqMonth} monthly req.</li>`;featuresList+=`<li><i class="fas fa-check"></i> ${durationText}</li>`;}else{featuresList+=`<li><i class="fas fa-check"></i> Standard Access</li>`;featuresList+=`<li><i class="fas fa-check"></i> ${durationText}</li>`;}
optionsHTML+=`<div class="plan-card" data-plan-id="${plan.id}"><div class="plan-header"><div><h4 class="plan-name">${escapeHTML(plan.plan_name)}</h4><p class="plan-desc">${escapeHTML(plan.description||'')}</p></div><div class="plan-pricing"><span class="price">$${escapeHTML(price)}</span><span class="period">${isOneTime?'/one-time':'/mo'}</span> ${priceDiffHtml}</div></div><ul class="plan-features">${featuresList}</ul></div>`;});}else{optionsHTML='<p class="text-center text-gray-500 text-sm md:col-span-2 lg:col-span-3">No other plans available for this service.</p>';}
console.log(`Generated optionsHTML:`,optionsHTML);modalOptionsContainer.innerHTML=optionsHTML;openModal('plan-modal');}
function selectPlanCard(selectedCard){const modalOptionsContainer=document.getElementById('plan-modal-options');modalOptionsContainer.querySelectorAll('.plan-card').forEach(card=>card.classList.remove('selected'));selectedCard.classList.add('selected');const selectedPlanId=selectedCard.dataset.planId;document.getElementById('plan-modal-selected-plan-id').value=selectedPlanId;document.getElementById('confirm-plan-change').disabled=false;}
function handlePlanChangeConfirm(button){const modalForm=document.getElementById('plan-modal-form');const selectedPlanId=modalForm.elements['new_plan_id'].value;const currentPurchaseId=modalForm.elements['current_purchase_id'].value;const nonceField=modalForm.elements['_wpnonce'];if(!selectedPlanId){setModalFeedback('plan-modal','Please select a plan.',false);return;}
if(!nonceField.value){console.error("Plan change nonce is missing!");setModalFeedback('plan-modal','Security error. Cannot submit. Please refresh and try again.',false);return;}
console.log(`Submitting plan change form for Purchase ID: ${currentPurchaseId} to Plan ID: ${selectedPlanId}`);showButtonSpinner(button,true);modalForm.submit();}
function escapeHTML(str){if(str===null||typeof str==='undefined')return'';const div=document.createElement('div');div.textContent=str;return div.innerHTML;}
function showButtonSpinner(button,show=true){const spinner=button?.querySelector('.save-spinner, .spinner, .forgot-spinner, .verify-spinner, .reset-spinner, .button-spinner .spinner');if(spinner)spinner.closest('.button-spinner').style.display=show?'inline-block':'none';const text=button?.querySelector('.button-text');if(text)text.style.display=show?'none':'inline-flex';if(button)button.disabled=show;}
function setModalFeedback(modalId,message,isSuccess){const feedback=document.getElementById(`modal-feedback-${modalId.replace('-modal','')}`);if(feedback){feedback.textContent=message;feedback.className=`text-xs text-right mr-auto h-4 ${isSuccess?'text-green-600':'text-red-600'}`;setTimeout(()=>{if(feedback)feedback.textContent='';feedback.className='text-xs text-right mr-auto h-4';},4000);}}
function previewProfilePicture(input){const preview=document.getElementById('profile-picture-preview-img');const removeInput=document.getElementById('remove_profile_picture');if(input.files&&input.files[0]&&preview){const reader=new FileReader();reader.onload=function(e){preview.src=e.target.result;if(removeInput)removeInput.value='0';}
reader.readAsDataURL(input.files[0]);}}
function handleRemovePicture(){const preview=document.getElementById('profile-picture-preview-img');const removeInput=document.getElementById('remove_profile_picture');const fileInput=document.getElementById('profile_picture');const defaultAvatar='<?php echo esc_url(get_avatar_url($user_id, ['size' => 64, 'default' => 'mystery'])); ?>';if(preview)preview.src=defaultAvatar;if(removeInput)removeInput.value='1';if(fileInput)fileInput.value='';}
function transitionForgotPasswordStep(stepToShow){console.log('Transitioning to forgot password step:',stepToShow);const forgotSection=document.getElementById('forgot-password-section');if(!forgotSection){console.error("Forgot password section not found!");return;}
const sections={1:forgotSection.querySelector('#request-otp-section'),2:forgotSection.querySelector('#otp-verify-section'),3:forgotSection.querySelector('#reset-password-otp-section')};Object.values(sections).forEach(section=>{if(section)section.classList.add('hidden');});if(sections[stepToShow]){console.log(`Showing forgot password section ${stepToShow}`);sections[stepToShow].classList.remove('hidden');const firstInput=sections[stepToShow].querySelector('input:not([type=hidden])');if(firstInput){setTimeout(()=>firstInput.focus(),100);}}else{console.error('Target forgot password step section not found:',stepToShow);}}
function showForgotPasswordView(){const profileSection=document.getElementById('profile-details-section');const profileFooter=document.getElementById('profile-form-footer');const forgotSection=document.getElementById('forgot-password-section');if(profileSection)profileSection.classList.add('hidden');if(profileFooter)profileFooter.classList.add('hidden');if(forgotSection)forgotSection.classList.remove('hidden');document.getElementById('modal-feedback-forgot').textContent='';document.getElementById('modal-feedback-verify').textContent='';document.getElementById('modal-feedback-reset').textContent='';document.getElementById('forgot-email').value='<?php echo esc_js($current_user->user_email); ?>';forgotPasswordUserIdentifier='';transitionForgotPasswordStep(1);}
function showProfileView(){const profileSection=document.getElementById('profile-details-section');const profileFooter=document.getElementById('profile-form-footer');const forgotSection=document.getElementById('forgot-password-section');if(forgotSection)forgotSection.classList.add('hidden');if(profileSection)profileSection.classList.remove('hidden');if(profileFooter)profileFooter.classList.remove('hidden');document.getElementById('modal-feedback-forgot').textContent='';}
function setForgotFeedback(message,isSuccess,step=1){let feedbackDivId='modal-feedback-forgot';if(step===2)feedbackDivId='modal-feedback-verify';else if(step===3)feedbackDivId='modal-feedback-reset';const feedbackDiv=document.getElementById(feedbackDivId);if(feedbackDiv){feedbackDiv.textContent=message;feedbackDiv.className=`h-5 mt-2 text-left font-medium ${isSuccess?'text-green-600 success':'text-red-600 error'}`;setTimeout(()=>{if(feedbackDiv){feedbackDiv.textContent='';feedbackDiv.className=`h-5 mt-2`;}},5000);}else{console.warn('Could not find feedback div for step:',step,'ID:',feedbackDivId);}}
function handleForgotPasswordSubmit(button){console.log("Step 1: Requesting OTP...");const emailInput=document.getElementById('forgot-email');const email=emailInput?emailInput.value.trim():'';setForgotFeedback('',true,1);if(!email){setForgotFeedback('Please enter your email address.',false,1);return;}
if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){setForgotFeedback('Please enter a valid email address.',false,1);return;}
forgotPasswordUserIdentifier=email;showButtonSpinner(button,true);const formData=new FormData();formData.append('action','orunk_ajax_request_otp');formData.append('orunk_request_otp_nonce',orunkDashboardData.otpRequestNonce);formData.append('user_login',email);fetch(orunkDashboardData.ajaxUrl,{method:'POST',body:formData}).then(response=>response.json()).then(data=>{if(data.success){console.log("Step 1 Success: OTP Request successful.");document.getElementById('otp-sent-to-email').textContent=escapeHTML(email);transitionForgotPasswordStep(2);}else{console.error("Step 1 Error:",data.message);setForgotFeedback(data.data?.message||'Failed to send OTP.',false,1);}}).catch(error=>{console.error('Forgot Password AJAX Error:',error);setForgotFeedback('An error occurred. Please try again.',false,1);}).finally(()=>{showButtonSpinner(button,false);});}
function handleVerifyOtpSubmit(button){console.log("Step 2: Verifying OTP...");const otpInput=document.getElementById('otp-code');const otpCode=otpInput?otpInput.value.trim():'';setForgotFeedback('',true,2);if(!otpCode){setForgotFeedback('Please enter the OTP code.',false,2);return;}
if(!/^\d{6}$/.test(otpCode)){setForgotFeedback('OTP must be 6 digits.',false,2);return;}
if(!forgotPasswordUserIdentifier){console.error("Step 2 Error: User identifier missing.");setForgotFeedback('User identifier missing. Please start over.',false,1);transitionForgotPasswordStep(1);return;}
showButtonSpinner(button,true);const formData=new FormData();formData.append('action','orunk_ajax_otp_verify');formData.append('orunk_verify_otp_nonce',orunkDashboardData.otpVerifyNonce);formData.append('otp_login',forgotPasswordUserIdentifier);formData.append('otp_code',otpCode);fetch(orunkDashboardData.ajaxUrl,{method:'POST',body:formData}).then(response=>response.json()).then(data=>{if(data.success){console.log("Step 2 Success: OTP Verified.");transitionForgotPasswordStep(3);}else{console.error("Step 2 Error:",data.message);setForgotFeedback(data.data?.message||'Invalid or expired OTP.',false,2);otpInput.value='';}}).catch(error=>{console.error('Verify OTP AJAX Error:',error);setForgotFeedback('An error occurred verifying OTP.',false,2);}).finally(()=>{showButtonSpinner(button,false);});}
function handleResetPasswordOtpSubmit(button){console.log("Step 3: Resetting Password...");const newPassInput=document.getElementById('reset-new-password');const confirmPassInput=document.getElementById('reset-confirm-password');const newPass=newPassInput?newPassInput.value:'';const confirmPass=confirmPassInput?confirmPassInput.value:'';setForgotFeedback('',true,3);if(!newPass||!confirmPass){setForgotFeedback('Please enter and confirm your new password.',false,3);return;}
if(newPass!==confirmPass){setForgotFeedback('New passwords do not match.',false,3);return;}
if(!forgotPasswordUserIdentifier){console.error("Step 3 Error: User identifier missing.");setForgotFeedback('User identifier missing. Please start over.',false,1);transitionForgotPasswordStep(1);return;}
showButtonSpinner(button,true);const formData=new FormData();formData.append('action','orunk_ajax_reset_password_otp');formData.append('orunk_reset_password_otp_nonce',orunkDashboardData.otpResetNonce);formData.append('rp_login',forgotPasswordUserIdentifier);formData.append('pass1',newPass);formData.append('pass2',confirmPass);fetch(orunkDashboardData.ajaxUrl,{method:'POST',body:formData}).then(response=>response.json()).then(data=>{if(data.success){console.log("Step 3 Success: Password Reset.");setModalFeedback('profile-modal',data.message||'Password reset successfully!',true);closeModal('profile-modal');}else{console.error("Step 3 Error:",data.message);setForgotFeedback(data.data?.message||'Failed to reset password.',false,3);}}).catch(error=>{console.error('Reset Password OTP AJAX Error:',error);setForgotFeedback('An error occurred resetting the password.',false,3);}).finally(()=>{showButtonSpinner(button,false);});}
function initializePurchaseHistoryView(){if(!historyTableBody){return;}
if(!showMoreHistoryBtn){return;}
const rows=historyTableBody.querySelectorAll('tr');console.log(`Found ${rows.length} history rows.`);if(rows.length<=5){showMoreHistoryBtn.classList.add('hidden');console.log("Hiding 'Show More' button (<= 5 items).");return;}else{showMoreHistoryBtn.classList.remove('hidden');console.log("Showing 'Show More' button (> 5 items).");}
rows.forEach((row,index)=>{if(index>=5){row.classList.add('history-hidden');}else{row.classList.remove('history-hidden');}});console.log("Initial history view set (showing max 5).");}
function handleShowMoreHistory(button){if(!historyTableBody)return;console.log("Handling 'Show More' click.");const hiddenRows=historyTableBody.querySelectorAll('tr.history-hidden');hiddenRows.forEach(row=>{row.classList.remove('history-hidden');});console.log(`Revealed ${hiddenRows.length} hidden rows.`);button.classList.add('hidden');console.log("Hiding 'Show More' button after click.");}
function handlePluginDownload(button){const purchaseId=button.dataset.purchaseId;const nonce=button.dataset.nonce;const feedbackDiv=document.getElementById(`download-feedback-${purchaseId}`);const buttonTextSpan=button.querySelector('.button-text');const spinnerSpan=button.querySelector('.button-spinner');const originalButtonText=buttonTextSpan?buttonTextSpan.innerHTML:'<i class="fas fa-download mr-1"></i>Download';if(!purchaseId||!nonce){console.error('Missing data for download.');if(feedbackDiv)feedbackDiv.textContent='Error: Missing data.';return;}
button.disabled=true;button.classList.remove('is-success','is-error');button.classList.add('is-downloading');if(feedbackDiv){feedbackDiv.textContent='Preparing download...';feedbackDiv.className='text-xs mt-2 text-center h-4 text-gray-500';}
const formData=new FormData();formData.append('action','orunk_handle_convojet_download');formData.append('nonce',nonce);formData.append('purchase_id',purchaseId);fetch(orunkDashboardData.ajaxUrl,{method:'POST',body:formData}).then(response=>response.json()).then(data=>{if(data.success&&data.data.download_url){button.classList.remove('is-downloading');button.classList.add('is-success');if(buttonTextSpan)buttonTextSpan.innerHTML='<i class="fas fa-check mr-1"></i>Download';if(feedbackDiv){feedbackDiv.textContent='Download starting...';feedbackDiv.className='text-xs mt-2 text-center h-4 text-green-600';}
window.location.href=data.data.download_url;setTimeout(()=>{button.disabled=false;button.classList.remove('is-success');if(buttonTextSpan)buttonTextSpan.innerHTML=originalButtonText;if(feedbackDiv)feedbackDiv.textContent='';},3000);}else{let errorMsg=data.data?.message||'Download failed.';button.classList.remove('is-downloading');button.classList.add('is-error');if(buttonTextSpan)buttonTextSpan.innerHTML='<i class="fas fa-times mr-1"></i>Error';if(feedbackDiv){feedbackDiv.textContent=errorMsg;feedbackDiv.className='text-xs mt-2 text-center h-4 text-red-600';}
if(data.data?.code==='limit_reached'){alert(errorMsg);}
console.error('Download Error:',data.data);setTimeout(()=>{button.disabled=false;button.classList.remove('is-error');if(buttonTextSpan)buttonTextSpan.innerHTML=originalButtonText;if(feedbackDiv)feedbackDiv.textContent='';},4000);}}).catch(error=>{console.error('Download AJAX Error:',error);button.classList.remove('is-downloading');button.classList.add('is-error');if(buttonTextSpan)buttonTextSpan.innerHTML='<i class="fas fa-times mr-1"></i>Error';if(feedbackDiv){feedbackDiv.textContent='Error preparing download.';feedbackDiv.className='text-xs mt-2 text-center h-4 text-red-600';}
setTimeout(()=>{button.disabled=false;button.classList.remove('is-error');if(buttonTextSpan)buttonTextSpan.innerHTML=originalButtonText;if(feedbackDiv)feedbackDiv.textContent='';},4000);});}
</script>

</body>
</html>