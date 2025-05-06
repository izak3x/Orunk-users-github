<?php
/**
 * Orunk Users - Admin Features & Plans AJAX Handlers
 *
 * Handles AJAX requests related to managing features and plans
 * in the admin interface.
 *
 * MODIFIED (Activation Tracking):
 * - handle_admin_save_feature: Added saving for 'requires_license'.
 * - handle_admin_save_plan: Added saving for 'activation_limit'.
 * - Includes previous modification for gateway IDs.
 *
 * @package OrunkUsers\Admin\AJAX
 * @version 1.2.0 // Version increment for activation tracking save logic
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure the helper function is available (defined in admin-ajax-helpers.php)
if (!function_exists('orunk_admin_check_ajax_permissions')) {
    // Attempt to include it if not already loaded (robustness)
    $helper_path = dirname(__FILE__) . '/admin-ajax-helpers.php';
    if (file_exists($helper_path)) {
        require_once $helper_path;
    } else {
        // Cannot proceed without the helper, send error
        // Note: This error won't be caught nicely if included directly without an AJAX context check
        error_log("Orunk Admin AJAX Error: admin-ajax-helpers.php not found.");
        if (defined('DOING_AJAX') && DOING_AJAX) {
            wp_send_json_error(['message' => 'Admin helper function missing.'], 500);
        }
        // Return or die if not in AJAX context to prevent further errors
        return;
    }
}


/**
 * AJAX Handler: Get all Features and their Plans for the Admin Interface.
 * Handles the 'orunk_admin_get_features_plans' action.
 * (Function unchanged from original - assuming DB schema has new columns)
 */
function handle_admin_get_features_plans() {
    // Check permissions
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce');
    global $wpdb;

    try {
        $products_table = $wpdb->prefix . 'orunk_products';
        $plans_table = $wpdb->prefix . 'orunk_product_plans';

        // Fetch all features, ordered by name
        // SELECT * will now implicitly include 'requires_license' if the DB was updated
        $features = $wpdb->get_results("SELECT * FROM `{$products_table}` ORDER BY product_name ASC", ARRAY_A);

        if ($features === false) {
            throw new Exception('Database error fetching features. DB Error: ' . $wpdb->last_error);
        }

        if (!empty($features)) {
            foreach ($features as $key => $feature) {
                if (!empty($feature['feature'])) {
                    // SELECT * will now implicitly include 'activation_limit' if the DB was updated
                    $plans = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM `{$plans_table}` WHERE product_feature_key = %s ORDER BY is_active DESC, price ASC",
                            $feature['feature']
                        ),
                        ARRAY_A
                    );

                    if ($plans === false) {
                        error_log("Orunk Admin AJAX DB Error fetching plans for feature '{$feature['feature']}': " . $wpdb->last_error);
                        $features[$key]['plans'] = [];
                    } else {
                        $features[$key]['plans'] = $plans;
                    }
                } else {
                    error_log("Orunk Admin AJAX Warning: Feature ID {$feature['id']} ('{$feature['product_name']}') is missing its 'feature' key.");
                    $features[$key]['plans'] = [];
                }
            }
        }

        wp_send_json_success(['features' => $features ?? []]);

    } catch (Exception $e) {
        error_log('AJAX Exception in handle_admin_get_features_plans: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Server error fetching features and plans.'], 500);
    }
}


/**
 * AJAX Handler: Save (Add or Update) a Feature.
 * Handles the 'orunk_admin_save_feature' action via POST request.
 * *** MODIFIED: Added saving for 'requires_license' field ***
 */
function handle_admin_save_feature() {
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce');
    global $wpdb;

    $products_table = $wpdb->prefix . 'orunk_products';

    // --- Sanitize POST data ---
    $feature_id   = isset($_POST['feature_id']) ? absint($_POST['feature_id']) : 0;
    $feature_key  = isset($_POST['feature_key']) ? sanitize_key($_POST['feature_key']) : '';
    $product_name = isset($_POST['product_name']) ? sanitize_text_field(wp_unslash($_POST['product_name'])) : '';
    $description  = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
    $category_slug = (isset($_POST['category']) && !empty($_POST['category'])) ? sanitize_key($_POST['category']) : null;
    // Sanitize existing download fields (assuming they are still needed)
    $download_url = isset($_POST['download_url']) ? esc_url_raw(wp_unslash(trim($_POST['download_url']))) : null;
    $download_limit = isset($_POST['download_limit_daily']) ? absint($_POST['download_limit_daily']) : 5;
    // *** Added: Sanitize requires_license checkbox ***
    $requires_license = (isset($_POST['requires_license']) && $_POST['requires_license'] == '1') ? 1 : 0;

    // --- Validate required fields ---
    if (empty($feature_key) || empty($product_name)) {
        wp_send_json_error(['message' => __('Feature Key and Feature Name are required fields.', 'orunk-users')], 400);
    }
    if (!preg_match('/^[a-z0-9_]+$/', $feature_key)) {
         wp_send_json_error(['message' => __('Feature Key can only contain lowercase letters, numbers, and underscores.', 'orunk-users')], 400);
    }

    // --- Prepare data for DB ---
    $data = [
        'product_name' => $product_name,
        'description'  => $description,
        'category'     => $category_slug,
        'download_url' => $download_url, // Keep existing download fields if needed
        'download_limit_daily' => $download_limit, // Keep existing download fields if needed
        'requires_license' => $requires_license, // *** Added requires_license ***
    ];
    // Define formats matching the $data array order
    $format = ['%s', '%s', '%s', '%s', '%d', '%d']; // name, desc, cat, dl_url, dl_limit, req_license

    $success = false;
    $new_feature_id = $feature_id;

    if ($feature_id > 0) {
        // --- UPDATE existing feature ---
        // Feature key ('feature') cannot be updated.
        $success = $wpdb->update(
            $products_table,
            $data,                   // Data to update
            array('id' => $feature_id), // Where clause
            $format,                 // Format for data being updated
            array('%d')              // Format for where clause
        );
    } else {
        // --- INSERT new feature ---
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$products_table` WHERE feature = %s", $feature_key));
        if ($exists == 0) {
            // Add feature key only on insert
            $data = array_merge(['feature' => $feature_key], $data); // Add feature key to start
            array_unshift($format, '%s'); // Prepend format for feature key

            $success = $wpdb->insert($products_table, $data, $format);
            if ($success) {
                $new_feature_id = $wpdb->insert_id;
            }
        } else {
            wp_send_json_error(['message' => __('Feature Key already exists and must be unique.', 'orunk-users')], 409); // 409 Conflict
        }
    }

    // --- Send response ---
    if ($success !== false) {
        $saved_feature = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$products_table` WHERE id = %d", $new_feature_id), ARRAY_A);
        wp_send_json_success([
            'message' => __('Feature saved successfully.', 'orunk-users'),
            'feature' => $saved_feature // Send back the saved data
        ]);
    } else {
        error_log("Orunk Admin AJAX DB Error saving feature (ID: {$feature_id}): " . $wpdb->last_error);
        wp_send_json_error(['message' => __('Error saving feature to the database.', 'orunk-users')], 500);
    }
}

/**
 * AJAX Handler: Delete a Feature and its associated Plans.
 * Handles the 'orunk_admin_delete_feature' action via POST request.
 * (Function unchanged)
 */
function handle_admin_delete_feature() {
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce', 'manage_options');
    global $wpdb;

    $feature_id = isset($_POST['feature_id']) ? absint($_POST['feature_id']) : 0;
    if ($feature_id <= 0) {
        wp_send_json_error(['message' => __('Invalid Feature ID.', 'orunk-users')], 400);
    }

    $products_table = $wpdb->prefix . 'orunk_products';
    $plans_table    = $wpdb->prefix . 'orunk_product_plans';

    $feature_key = $wpdb->get_var($wpdb->prepare("SELECT feature FROM `$products_table` WHERE id = %d", $feature_id));

    if ($feature_key) {
        $wpdb->query('START TRANSACTION');
        $deleted_plans = $wpdb->delete($plans_table, ['product_feature_key' => $feature_key], ['%s']);
        if ($deleted_plans === false) {
             $wpdb->query('ROLLBACK');
             error_log("Orunk Admin AJAX DB Error deleting plans for feature '{$feature_key}': " . $wpdb->last_error);
             wp_send_json_error(['message' => __('Error deleting associated plans.', 'orunk-users')], 500);
        }
        $deleted_feature = $wpdb->delete($products_table, ['id' => $feature_id], ['%d']);
        if ($deleted_feature !== false) {
            $wpdb->query('COMMIT');
            wp_send_json_success([
                'message' => sprintf(__('Feature and %d associated plan(s) deleted successfully.', 'orunk-users'), $deleted_plans),
                'deleted_feature_id' => $feature_id
            ]);
        } else {
            $wpdb->query('ROLLBACK');
            error_log("Orunk Admin AJAX DB Error deleting feature (ID: {$feature_id}): " . $wpdb->last_error);
            wp_send_json_error(['message' => __('Error deleting feature from the database.', 'orunk-users')], 500);
        }
    } else {
        wp_send_json_error(['message' => __('Feature to delete was not found.', 'orunk-users')], 404);
    }
}


/**
 * AJAX Handler: Save (Add or Update) a Plan.
 * Handles the 'orunk_admin_save_plan' action via POST request.
 * *** MODIFIED: Added saving for 'activation_limit' field ***
 */
function handle_admin_save_plan() {
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce', 'manage_options');
    global $wpdb;

    $plans_table = $wpdb->prefix . 'orunk_product_plans';

    // --- Sanitize POST data ---
    $plan_id            = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
    $feature_key        = isset($_POST['product_feature_key']) ? sanitize_key($_POST['product_feature_key']) : '';
    $plan_name          = isset($_POST['plan_name']) ? sanitize_text_field(wp_unslash($_POST['plan_name'])) : '';
    $description        = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
    $price_input        = isset($_POST['price']) ? wp_unslash($_POST['price']) : '0.00';
    $requests_per_day   = isset($_POST['requests_per_day']) && trim($_POST['requests_per_day']) !== '' ? filter_var(wp_unslash($_POST['requests_per_day']), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : null;
    $requests_per_month = isset($_POST['requests_per_month']) && trim($_POST['requests_per_month']) !== '' ? filter_var(wp_unslash($_POST['requests_per_month']), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : null;
    $is_active          = (isset($_POST['is_active']) && $_POST['is_active'] == '1') ? 1 : 0;
    $is_one_time        = (isset($_POST['is_one_time']) && $_POST['is_one_time'] == '1') ? 1 : 0;
    $duration_days_input = isset($_POST['duration_days']) ? absint($_POST['duration_days']) : 0;
    $paypal_plan_id_input = isset($_POST['paypal_plan_id']) ? sanitize_text_field(wp_unslash($_POST['paypal_plan_id'])) : null;
    $stripe_price_id_input = isset($_POST['stripe_price_id']) ? sanitize_text_field(wp_unslash($_POST['stripe_price_id'])) : null;

    // *** Added: Sanitize activation_limit ***
    $activation_limit_input = isset($_POST['activation_limit']) ? trim(wp_unslash($_POST['activation_limit'])) : '1'; // Default to 1 if not sent
    $activation_limit = null; // Default to NULL (unlimited)
    if ($activation_limit_input !== '') {
        $activation_limit_validated = filter_var($activation_limit_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        // Treat 0 as unlimited (NULL), otherwise use the validated integer (or default to 1 if invalid non-blank value)
        if ($activation_limit_validated === false) {
            $activation_limit = 1; // Invalid number entered, default to 1
            error_log("Orunk Admin AJAX Warning: Invalid activation_limit '{$activation_limit_input}' provided. Defaulting to 1.");
        } elseif ($activation_limit_validated > 0) {
            $activation_limit = $activation_limit_validated;
        } // If 0, $activation_limit remains NULL (unlimited)
    }
    // *** End Sanitize activation_limit ***


    // --- Validate Input ---
    if (empty($feature_key) || empty($plan_name) || !is_numeric($price_input) || floatval($price_input) < 0) {
        wp_send_json_error(['message' => __('Feature Key, Plan Name, and a valid Price ($0+) are required.', 'orunk-users')], 400);
    }
    if ($is_one_time === 1) {
         $duration_days = 9999;
    } elseif ($duration_days_input <= 0) {
         wp_send_json_error(['message' => __('Duration must be greater than 0 days for subscription plans.', 'orunk-users')], 400);
    } else {
         $duration_days = $duration_days_input;
    }
    // No validation needed for activation_limit here, handled during sanitization


    // --- Prepare data for DB ---
    $data = [
        'product_feature_key' => $feature_key,
        'plan_name'           => $plan_name,
        'description'         => $description,
        'price'               => floatval($price_input),
        'duration_days'       => $duration_days,
        'requests_per_day'    => $requests_per_day,    // Will be NULL if blank/invalid
        'requests_per_month'  => $requests_per_month, // Will be NULL if blank/invalid
        'activation_limit'    => $activation_limit,    // *** Added activation_limit (NULL or int) ***
        'is_active'           => $is_active,
        'is_one_time'         => $is_one_time,
        'paypal_plan_id'      => $paypal_plan_id_input ?: null,
        'stripe_price_id'     => $stripe_price_id_input ?: null,
    ];
    // Define formats carefully, aligning with $data keys order
    // Use %s for columns that can be NULL
    $format = [
        '%s', // product_feature_key
        '%s', // plan_name
        '%s', // description
        '%f', // price
        '%d', // duration_days
        ($requests_per_day === null) ? '%s' : '%d',
        ($requests_per_month === null) ? '%s' : '%d',
        ($activation_limit === null) ? '%s' : '%d', // *** Use %s if NULL, %d otherwise ***
        '%d', // is_active
        '%d', // is_one_time
        '%s', // paypal_plan_id (allow NULL)
        '%s', // stripe_price_id (allow NULL)
    ];

    $success = false;
    $new_plan_id = $plan_id;

    // --- Perform DB Operation ---
    if ($plan_id > 0) {
        $success = $wpdb->update( $plans_table, $data, ['id' => $plan_id], $format, ['%d']);
    } else {
        $success = $wpdb->insert($plans_table, $data, $format);
        if ($success) {
            $new_plan_id = $wpdb->insert_id;
        }
    }

    // --- Send Response ---
    if ($success !== false) {
        $saved_plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$plans_table` WHERE id = %d", $new_plan_id), ARRAY_A);
        wp_send_json_success([
            'message' => __('Plan saved successfully.', 'orunk-users'),
            'plan' => $saved_plan
        ]);
    } else {
        error_log("Orunk Admin AJAX DB Error saving plan (ID: {$plan_id}): " . $wpdb->last_error);
        wp_send_json_error(['message' => __('Error saving plan to the database.', 'orunk-users')], 500);
    }
}


/**
 * AJAX Handler: Delete a Plan.
 * Handles the 'orunk_admin_delete_plan' action via POST request.
 * (Function unchanged)
 */
function handle_admin_delete_plan() {
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce', 'manage_options');
    global $wpdb;

    $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
    if ($plan_id <= 0) {
        wp_send_json_error(['message' => __('Invalid Plan ID.', 'orunk-users')], 400);
    }

    $plans_table = $wpdb->prefix . 'orunk_product_plans';
    $deleted = $wpdb->delete($plans_table, ['id' => $plan_id], ['%d']);

    if ($deleted !== false) {
        if ($deleted > 0) {
            wp_send_json_success([
                'message' => __('Plan deleted successfully.', 'orunk-users'),
                'deleted_plan_id' => $plan_id
            ]);
        } else {
            wp_send_json_error(['message' => __('Plan not found or already deleted.', 'orunk-users')], 404);
        }
    } else {
        error_log("Orunk Admin AJAX DB Error deleting plan (ID: {$plan_id}): " . $wpdb->last_error);
        wp_send_json_error(['message' => __('Error deleting plan from the database.', 'orunk-users')], 500);
    }
}

?>