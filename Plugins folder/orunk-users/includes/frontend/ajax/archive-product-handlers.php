<?php
/**
 * Orunk Users - Product Archive AJAX Handlers
 *
 * Version 1.2.5: Added generic download handler for automatic download counts.
 * Fetches static metrics from WordPress options.
 *
 * @package OrunkUsers
 * @version 1.2.5
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// (orunk_ajax_get_archive_product_categories function remains the same as the last version)
function orunk_ajax_get_archive_product_categories() {
    check_ajax_referer('orunk_product_archive_nonce', 'archive_nonce');
    global $wpdb;
    $categories_table = $wpdb->prefix . 'orunk_feature_categories';
    $products_table = $wpdb->prefix . 'orunk_products';
    $categories_data = [];

    $category_details_map = [
        'api-service'      => ['icon' => 'fa-bolt',       'color_class' => 'cat-icon-api-service'],
        'api-services'     => ['icon' => 'fa-bolt',       'color_class' => 'cat-icon-api-service'],
        'wordpress-plugin' => ['icon' => 'fab fa-wordpress','color_class' => 'cat-icon-wordpress-plugin'],
        'wordpress-plugins'=> ['icon' => 'fab fa-wordpress','color_class' => 'cat-icon-wordpress-plugin'],
        'website-feature'  => ['icon' => 'fa-star',       'color_class' => 'cat-icon-website-feature'],
        'website-features' => ['icon' => 'fa-star',       'color_class' => 'cat-icon-website-feature'],
        'themes'           => ['icon' => 'fa-paint-brush','color_class' => 'cat-icon-themes'],
        'wordpress-theme'  => ['icon' => 'fa-paint-brush','color_class' => 'cat-icon-themes'],
        'tools'            => ['icon' => 'fa-code',       'color_class' => 'cat-icon-tools'],
        'default'          => ['icon' => 'fa-cube',       'color_class' => 'cat-icon-default']
    ];

    $results = $wpdb->get_results( $wpdb->prepare("
        SELECT DISTINCT fc.category_name, fc.category_slug
        FROM `{$categories_table}` fc
        INNER JOIN `{$products_table}` p ON fc.category_slug = p.category
        WHERE p.category IS NOT NULL AND p.category != ''
        ORDER BY fc.category_name ASC
    "), ARRAY_A);

    if ($wpdb->last_error) {
        wp_send_json_error(['message' => 'Database error fetching categories.', 'db_error' => $wpdb->last_error]);
        return;
    }

    if ($results) {
        foreach ($results as $row) {
            $slug = $row['category_slug'];
            $details = $category_details_map[$slug] ?? $category_details_map['default'];
            $categories_data[] = [
                'name' => $row['category_name'],
                'slug' => $slug,
                'icon' => $details['icon']
            ];
        }
    }
    wp_send_json_success(['categories' => $categories_data]);
}
add_action('wp_ajax_orunk_get_archive_product_categories', 'orunk_ajax_get_archive_product_categories');
add_action('wp_ajax_nopriv_orunk_get_archive_product_categories', 'orunk_ajax_get_archive_product_categories');


function orunk_ajax_get_archive_products() {
    check_ajax_referer('orunk_product_archive_nonce', 'archive_nonce');
    global $wpdb;
    $products_table = $wpdb->prefix . 'orunk_products';
    $plans_table = $wpdb->prefix . 'orunk_product_plans';

    $filters = [
        'category' => isset($_GET['category']) ? sanitize_key($_GET['category']) : 'all',
        'search'   => isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '',
        'sort'     => isset($_GET['sort']) ? sanitize_key($_GET['sort']) : 'default',
    ];

    $products_data = [];
    $sql = "SELECT p.id, p.product_name, p.feature AS feature_key, p.description, p.category AS category_slug,
                   p.download_url, p.requires_license, p.created_at
            FROM `{$products_table}` p WHERE 1=1 ";
    $params = [];

    if ($filters['category'] !== 'all') {
        $sql .= " AND p.category = %s";
        $params[] = $filters['category'];
    }
    if (!empty($filters['search'])) {
        $search_term_like = '%' . $wpdb->esc_like($filters['search']) . '%';
        $sql .= " AND (p.product_name LIKE %s OR p.description LIKE %s)";
        $params[] = $search_term_like;
        $params[] = $search_term_like;
    }

    switch ($filters['sort']) {
        case 'name_asc': $sql .= " ORDER BY p.product_name ASC"; break;
        case 'name_desc': $sql .= " ORDER BY p.product_name DESC"; break;
        case 'newest': $sql .= " ORDER BY p.created_at DESC"; break;
        default: $sql .= " ORDER BY p.id ASC";
    }

    $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

    if ($wpdb->last_error) {
        wp_send_json_error(['message' => 'Database error fetching products.', 'db_error' => $wpdb->last_error, 'query' => $wpdb->last_query]);
        return;
    }

    if ($results) {
        foreach ($results as $row) {
            $product_plans = $wpdb->get_results($wpdb->prepare(
                "SELECT price, is_one_time FROM `{$plans_table}` WHERE product_feature_key = %s AND is_active = 1 ORDER BY price ASC",
                $row['feature_key']
            ), ARRAY_A);

            // --- Pricing and CTA Logic (remains the same) ---
            $price_html = __('N/A', 'orunk-users');
            $call_to_action_text = __('Learn More', 'orunk-users');
            $call_to_action_type = 'view_plans';
            $pricing_type_tag = 'N/A';
            $lowest_price = null;
            $has_free_plan = false;
            $is_subscription = false;
            $is_onetime = false;

            if (!empty($product_plans)) {
                $paid_plans_count = 0;
                foreach($product_plans as $plan) {
                    $plan_price = floatval($plan['price']);
                    if ($plan_price == 0) $has_free_plan = true; else $paid_plans_count++;
                    if ($plan['is_one_time'] == '1') $is_onetime = true; else $is_subscription = true;
                    if ($plan_price > 0 && ($lowest_price === null || $plan_price < $lowest_price)) $lowest_price = $plan_price;
                }
                if ($has_free_plan && $paid_plans_count == 0) {
                    $price_html = __('Free', 'orunk-users');
                    $call_to_action_text = ($row['download_url'] && $row['download_url'] !== '') ? __('Download', 'orunk-users') : __('Get Started', 'orunk-users');
                    $call_to_action_type = ($row['download_url'] && $row['download_url'] !== '') ? 'download' : 'get_started_free';
                    $pricing_type_tag = 'FREE';
                } elseif ($has_free_plan) {
                    $price_html = ($lowest_price !== null) ? 'From $' . number_format($lowest_price, 2) : 'Freemium';
                    $call_to_action_text = __('Get Started Free', 'orunk-users');
                    $call_to_action_type = 'get_started_free';
                    $pricing_type_tag = $is_subscription ? 'SUBSCRIPTION' : ($is_onetime ? 'ONE-TIME' : 'FREEMIUM');
                } elseif ($lowest_price !== null) {
                    if ($is_subscription && !$is_onetime) {
                        $price_html = '$' . number_format($lowest_price, 2) . ' <span class="text-xs font-normal text-gray-500">/mo</span>';
                        $pricing_type_tag = 'SUBSCRIPTION';
                    } elseif ($is_onetime && !$is_subscription) {
                        $price_html = '$' . number_format($lowest_price, 2) . ' <span class="text-xs font-normal text-gray-500">one-time</span>';
                        $pricing_type_tag = 'ONE-TIME';
                    } else { $price_html = 'From $' . number_format($lowest_price, 2); $pricing_type_tag = 'MIXED'; }
                    $call_to_action_text = __('View Plans', 'orunk-users');
                }
            } else { $pricing_type_tag = 'UNAVAILABLE'; }
            // --- End Pricing and CTA Logic ---

            $cat_icons_map = [
                'api-service' => 'fa-bolt', 'api-services' => 'fa-bolt',
                'wordpress-plugin' => 'fab fa-wordpress', 'wordpress-plugins' => 'fab fa-wordpress',
                'themes' => 'fa-paint-brush', 'wordpress-theme' => 'fa-paint-brush',
                'website-feature' => 'fa-star', 'website-features' => 'fa-star',
                'tools' => 'fa-code'
            ];
            $category_icon_class = $cat_icons_map[$row['category_slug']] ?? 'fa-cube';

            $image_url = '';
            // Check if download_url is an image (basic check)
            // A more robust solution would be to have a dedicated 'product_image_url' field
            if (!empty($row['download_url']) && preg_match('/\.(jpeg|jpg|gif|png)(\?.*)?$/i', $row['download_url'])) {
                $image_url = esc_url($row['download_url']);
            }


            // --- Fetch Static Reviews and Sales/Downloads from WordPress Options ---
            $product_id_for_options = $row['id'];
            $metrics_option_name = 'orunk_product_metrics_' . $product_id_for_options;
            $static_metrics = get_option($metrics_option_name, [
                'rating'          => 0,
                'reviews_count'   => 0,
                'sales_count'     => 0,
                'downloads_count' => 0
            ]);

            $rating = !empty($static_metrics['rating']) ? floatval($static_metrics['rating']) : null;

            $product_metric_type = 'sales';
            $product_metric_value = 0;

            if (in_array($row['category_slug'], ['wordpress-plugin', 'wordpress-plugins', 'wordpress-theme', 'themes'])) {
                $product_metric_type = 'downloads';
                $product_metric_value = !empty($static_metrics['downloads_count']) ? intval($static_metrics['downloads_count']) : 0;
            } else {
                $product_metric_value = !empty($static_metrics['sales_count']) ? intval($static_metrics['sales_count']) : 0;
            }

            $product_metric_display = $product_metric_value > 999 ? round($product_metric_value/1000, 1) . 'k+' : (string)$product_metric_value;
            // --- End Fetch Static Metrics ---

            $products_data[] = [
                'id'                => $row['id'],
                'name'              => $row['product_name'],
                'feature_key'       => $row['feature_key'],
                'url'               => home_url('/orunk-catalog/' . $row['feature_key'] . '/'),
                'short_description' => wp_kses_post(wp_trim_words($row['description'], 12, '...')),
                'price_html'        => $price_html,
                'category_slug'     => $row['category_slug'],
                'category_icon_class' => $category_icon_class,
                'image_url'         => $image_url,
                'badge'             => '',
                'pricing_type_tag'  => $pricing_type_tag,
                'call_to_action_text' => $call_to_action_text,
                'call_to_action_type' => $call_to_action_type,
                'requires_license'  => $row['requires_license'] == '1',
                'rating'            => $rating,
                'product_metric_type' => $product_metric_type,
                'product_metric_display' => $product_metric_display
            ];
        }
    }
    wp_send_json_success(['products' => $products_data]);
}
add_action('wp_ajax_orunk_get_archive_products', 'orunk_ajax_get_archive_products');
add_action('wp_ajax_nopriv_orunk_get_archive_products', 'orunk_ajax_get_archive_products');


/**
 * AJAX handler to process a product download, log it, and serve the file.
 */
function orunk_handle_generic_product_download() {
    check_ajax_referer('orunk_product_download_nonce', 'nonce');

    if (empty($_GET['feature_key'])) {
        wp_send_json_error(['message' => __('Product identifier missing.', 'orunk-users')], 400);
        return;
    }

    $feature_key = sanitize_key($_GET['feature_key']);
    global $wpdb;
    $products_table = $wpdb->prefix . 'orunk_products';

    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT id, product_name, download_url, download_limit_daily FROM `{$products_table}` WHERE feature = %s", // Added download_limit_daily
        $feature_key
    ), ARRAY_A);

    if (!$product || empty($product['download_url'])) {
        error_log("Orunk Generic Download: Product not found or no download URL for feature '{$feature_key}'.");
        wp_send_json_error(['message' => __('Product not found or download link is invalid.', 'orunk-users')], 404);
        return;
    }

    $product_id = $product['id'];
    $actual_download_url = $product['download_url'];

    // --- Optional: Permission/Rate Limit Checks ---
    $user_id = get_current_user_id(); // Get user ID if downloads are for logged-in users or per-user limits apply
    if ($user_id) { // Only apply daily limit if user is logged in
        $limit_per_day = max(0, (int)($product['download_limit_daily'] ?? 0)); // 0 means unlimited
        if ($limit_per_day > 0) { // Only check if there's an actual limit
            $today_date_gmt = gmdate('Y-m-d');
            $meta_key_daily_downloads = '_orunk_user_dl_count_' . $feature_key . '_' . $today_date_gmt;
            $current_daily_downloads = (int) get_user_meta($user_id, $meta_key_daily_downloads, true);

            if ($current_daily_downloads >= $limit_per_day) {
                error_log("Orunk Generic Download: User {$user_id} reached daily limit ({$limit_per_day}) for feature '{$feature_key}'.");
                wp_send_json_error([
                    'message' => sprintf( __('You have reached your daily download limit (%d) for this product.', 'orunk-users'), $limit_per_day ),
                    'code' => 'limit_reached'
                ], 429); // HTTP 429 Too Many Requests
                return;
            }
            // Increment daily download count for the user for this specific feature
            update_user_meta($user_id, $meta_key_daily_downloads, $current_daily_downloads + 1);
            error_log("Orunk Generic Download: User {$user_id} daily download count for {$feature_key} incremented to " . ($current_daily_downloads + 1));
        }
    }
    // --- End Optional Checks ---

    // Increment Static Download Count using the helper function
    // Ensure the helper function orunk_update_product_metric is defined and loaded
    if (function_exists('orunk_update_product_metric')) {
        orunk_update_product_metric($product_id, 'downloads_count', 1, true); // Set autoload to 'no'
        error_log("Orunk Generic Download: Incremented static downloads_count for product ID {$product_id} (Feature: {$feature_key}).");
    } else {
        error_log("Orunk Generic Download Error: Helper function orunk_update_product_metric() not found for product ID {$product_id}.");
    }

    if (filter_var($actual_download_url, FILTER_VALIDATE_URL)) {
        wp_redirect(esc_url_raw($actual_download_url));
        exit;
    } else {
        error_log("Orunk Generic Download: Invalid actual download URL '{$actual_download_url}' for feature '{$feature_key}'.");
        wp_send_json_error(['message' => __('The file URL is invalid.', 'orunk-users')], 500);
    }
}
add_action('wp_ajax_orunk_generic_download', 'orunk_handle_generic_product_download');
add_action('wp_ajax_nopriv_orunk_generic_download', 'orunk_handle_generic_product_download'); // Nopriv for guest downloads if allowed

?>