<?php
/**
 * Orunk Users Products Admin Class
 *
 * Handles the admin interface for managing Features and their associated Pricing Plans.
 *
 * MODIFIED (Activation Tracking):
 * - Added 'Requires License Key?' checkbox to Feature form.
 * - Added 'Activation Limit' number input to Plan form.
 * - Includes previous modifications (Download URL/Limit).
 * - MODIFIED (Save License Requirement): Implemented saving for requires_license checkbox.
 * - MODIFIED (Save Plan): Corrected $format array for $wpdb calls in handle_save_plan.
 * - MODIFIED (Save Plan): Added fallback for wc_format_decimal and ensure 0 is saved instead of NULL for nullable integers.
 *
 * @package OrunkUsers\Admin
 * @version 1.3.3 // Version increment for plan saving safeguards
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Custom_Orunk_Products {

    /**
     * Initialize admin menu and action hooks.
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Action hooks for handling form submissions (defined in orunk-users.php)
        add_action('admin_post_orunk_save_feature', array($this, 'handle_save_feature'));
        add_action('admin_post_orunk_save_plan', array($this, 'handle_save_plan'));
        add_action('admin_post_orunk_delete_feature', array($this, 'handle_delete_feature'));
        add_action('admin_post_orunk_delete_plan', array($this, 'handle_delete_plan'));
    }

    /**
     * Add the "Features & Plans" submenu page.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'orunk-users-manager',                   // Parent slug
            __('Features & Plans', 'orunk-users'),   // Page title
            __('Features & Plans', 'orunk-users'),   // Menu title
            'manage_options',                       // Capability required
            'orunk-users-features-plans',           // Menu slug (unique)
            array($this, 'admin_page_html')         // Function to display page content
        );
    }

    //--------------------------------------------------
    // Form Submission Handlers (Called via admin-post.php)
    //--------------------------------------------------

    /**
     * Handles saving (adding or updating) a Feature.
     * MODIFIED: Handles saving download_url, download_limit_daily, and requires_license.
     */
    public function handle_save_feature() {
        global $wpdb;
        $products_table = $wpdb->prefix . 'orunk_products';

        // 1. Verify nonce and user capability
        if (!isset($_POST['orunk_save_feature_nonce']) || !wp_verify_nonce(sanitize_key($_POST['orunk_save_feature_nonce']), 'orunk_save_feature_action')) {
            wp_die(__('Security check failed.', 'orunk-users'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to manage features.', 'orunk-users'));
        }

        // 2. Sanitize input data (including new fields)
        $feature_id   = isset($_POST['feature_id']) ? absint($_POST['feature_id']) : 0;
        $feature_key  = isset($_POST['feature_key']) ? sanitize_key($_POST['feature_key']) : '';
        $product_name = isset($_POST['product_name']) ? sanitize_text_field(wp_unslash($_POST['product_name'])) : '';
        $description  = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $category_slug= (isset($_POST['category']) && !empty($_POST['category'])) ? sanitize_key($_POST['category']) : null;
        $download_url = isset($_POST['download_url']) ? esc_url_raw(wp_unslash(trim($_POST['download_url']))) : null;
        $download_limit = isset($_POST['download_limit_daily']) ? absint($_POST['download_limit_daily']) : 5;
        // --- Get requires_license value (1 if checked, 0 if not) ---
        $requires_license = isset($_POST['requires_license']) ? 1 : 0;

        // 3. Validate required fields
        if (empty($feature_key) || empty($product_name)) {
            wp_safe_redirect(add_query_arg(['page' => 'orunk-users-features-plans', 'orunk_message' => 'feature_error_required'], admin_url('admin.php')));
            exit;
        }

        // --- Prepare $data and $format arrays ---
        $data = array(
            'product_name'  => $product_name,
            'description'   => $description,
            'category'      => $category_slug,
            'download_url' => $download_url,
            'download_limit_daily' => $download_limit,
            'requires_license' => $requires_license, // --- Added requires_license ---
        );

        $success = false;
        if ($feature_id > 0) {
            // --- UPDATE existing feature ---
            $format = array('%s', '%s', '%s', '%s', '%d', '%d'); // name, desc, cat, dl_url, dl_limit, requires_license
            $success = $wpdb->update($products_table, $data, array('id' => $feature_id), $format, array('%d'));
        } else {
            // --- INSERT new feature ---
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$products_table` WHERE feature = %s", $feature_key));
            if ($exists == 0) {
                 $data = array_merge(['feature' => $feature_key], $data);
                 $format = array('%s', '%s', '%s', '%s', '%s', '%d', '%d'); // feature_key, name, desc, cat, dl_url, dl_limit, requires_license
                 $success = $wpdb->insert($products_table, $data, $format);
            } else {
                wp_safe_redirect(add_query_arg(['page' => 'orunk-users-features-plans', 'orunk_message' => 'feature_key_exists'], admin_url('admin.php')));
                exit;
            }
        }

        // 6. Redirect back with appropriate success or error message
        $message_code = ($success !== false) ? 'feature_saved' : 'feature_error_db';
        wp_safe_redirect(add_query_arg(['page' => 'orunk-users-features-plans', 'orunk_message' => $message_code], admin_url('admin.php')));
        exit;
    }

    /**
     * Handles saving (adding or updating) a Plan associated with a Feature.
     * MODIFIED: Corrected $format array for DB operations. Added WC fallback. Saves 0 for null ints.
     * TODO (Phase 4): Update to handle saving activation_limit.
     */
    public function handle_save_plan() {
        global $wpdb;
        $plans_table = $wpdb->prefix . 'orunk_product_plans';

        // Nonce & Perm Check
        if (!isset($_POST['orunk_save_plan_nonce']) || !wp_verify_nonce(sanitize_key($_POST['orunk_save_plan_nonce']), 'orunk_save_plan_action')) { wp_die(__('Security check failed.', 'orunk-users')); }
        if (!current_user_can('manage_options')) { wp_die(__('You do not have permission to manage plans.', 'orunk-users')); }

        // Sanitize Input
        $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
        $feature_key = isset($_POST['product_feature_key']) ? sanitize_key($_POST['product_feature_key']) : '';
        $plan_name = isset($_POST['plan_name']) ? sanitize_text_field($_POST['plan_name']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

        // Check if WooCommerce function exists before calling it
        if (function_exists('wc_format_decimal')) {
             $price = isset($_POST['price']) ? wc_format_decimal($_POST['price']) : 0.00;
        } else {
             // Fallback if WC not active - may cause issues if WC formatting is expected elsewhere
             $price_input = $_POST['price'] ?? '0';
             $price = floatval(preg_replace('/[^0-9.]/', '', $price_input));
             // Optionally log a warning if WC isn't active and price formatting might be important
             // error_log('Orunk Users Warning: wc_format_decimal function not found. Using basic floatval for price.');
        }

        $duration_days = isset($_POST['duration_days']) ? absint($_POST['duration_days']) : 30;
        // Get requests_per_day/month, defaulting to null if empty or invalid
        $requests_per_day = isset($_POST['requests_per_day']) && trim($_POST['requests_per_day']) !== '' ? filter_var($_POST['requests_per_day'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : null; if ($requests_per_day === false) $requests_per_day = null;
        $requests_per_month = isset($_POST['requests_per_month']) && trim($_POST['requests_per_month']) !== '' ? filter_var($_POST['requests_per_month'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : null; if ($requests_per_month === false) $requests_per_month = null;

        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_one_time = (isset($_POST['payment_type']) && $_POST['payment_type'] === 'one_time') ? 1 : 0;
        $paypal_plan_id_input = isset($_POST['paypal_plan_id']) ? sanitize_text_field(wp_unslash($_POST['paypal_plan_id'])) : null;
        $stripe_price_id_input = isset($_POST['stripe_price_id']) ? sanitize_text_field(wp_unslash($_POST['stripe_price_id'])) : null;
        // --- PHASE 4 will add sanitization for activation_limit ---
        // $activation_limit = isset($_POST['activation_limit']) && trim($_POST['activation_limit']) !== '' ? filter_var($_POST['activation_limit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : null;
        // if ($activation_limit === false) $activation_limit = 1;

        // Duration Override
        if ($is_one_time === 1) { $duration_days = 9999; }

        // Validation
        if (empty($feature_key) || empty($plan_name) || !is_numeric($price) || $price < 0 || $duration_days <= 0) {
             wp_safe_redirect(add_query_arg(['page' => 'orunk-users-features-plans', 'view' => 'feature', 'feature_key' => $feature_key, 'orunk_message' => 'plan_error_required'], admin_url('admin.php')));
             exit;
        }

        // Data Array Preparation
        $data = array(
            'product_feature_key' => $feature_key,
            'plan_name' => $plan_name,
            'description' => $description,
            'price' => $price,
            'duration_days' => $duration_days,
            'requests_per_day' => $requests_per_day ?? 0, // Use ?? 0 to save 0 instead of NULL
            'requests_per_month' => $requests_per_month ?? 0, // Use ?? 0 to save 0 instead of NULL
            /* PHASE 4: 'activation_limit' => $activation_limit ?? 1, */ // Example if activation limit is added
            'is_active' => $is_active,
            'is_one_time' => $is_one_time,
            'paypal_plan_id' => $paypal_plan_id_input ?: null, // Keep null for gateway IDs if empty
            'stripe_price_id' => $stripe_price_id_input ?: null, // Keep null for gateway IDs if empty
        );

        // --- Corrected, Static Format Array ---
        $format = array(
            '%s', // product_feature_key
            '%s', // plan_name
            '%s', // description
            '%f', // price
            '%d', // duration_days
            '%d', // requests_per_day (Now always passing an integer)
            '%d', // requests_per_month (Now always passing an integer)
            /* PHASE 4: Add format for activation_limit here, likely %d */
            '%d', // is_active
            '%d', // is_one_time
            '%s', // paypal_plan_id (Allowing null via %s)
            '%s'  // stripe_price_id (Allowing null via %s)
        );

        // DB Operation
        $success = false;
        try {
            if ($plan_id > 0) {
                // Update (where format is 3rd arg, data types for WHERE are 4th)
                $success = $wpdb->update($plans_table, $data, array('id' => $plan_id), $format, array('%d'));
            } else {
                // Insert (where format is 3rd arg)
                $success = $wpdb->insert($plans_table, $data, $format);
            }

            // Check for $wpdb errors if $success is false
            if ($success === false) {
                error_log("Orunk Users DB Error (handle_save_plan): " . $wpdb->last_error);
                $message_code = 'plan_error_db';
            } else {
                $message_code = 'plan_saved';
            }

        } catch (Exception $e) {
             error_log("Orunk Users Exception (handle_save_plan): " . $e->getMessage());
             $message_code = 'plan_error_db';
             $success = false; // Ensure success is false on exception
        }


        // Redirect
        wp_safe_redirect(add_query_arg(['page' => 'orunk-users-features-plans', 'view' => 'feature', 'feature_key' => $feature_key, 'orunk_message' => $message_code], admin_url('admin.php')));
        exit;
    }

    /**
     * Handles deleting a Feature and its associated Plans.
     */
    public function handle_delete_feature() {
        $feature_id = isset($_GET['feature_id']) ? absint($_GET['feature_id']) : 0; if ($feature_id <= 0 || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'orunk_delete_feature_' . $feature_id)) { wp_die(__('Security check failed or invalid feature ID.', 'orunk-users')); } if (!current_user_can('manage_options')) { wp_die(__('You do not have permission to delete features.', 'orunk-users')); } global $wpdb; $products_table = $wpdb->prefix . 'orunk_products'; $plans_table = $wpdb->prefix . 'orunk_product_plans'; $feature_key = $wpdb->get_var($wpdb->prepare("SELECT feature FROM `$products_table` WHERE id = %d", $feature_id)); if ($feature_key) { $wpdb->delete($plans_table, ['product_feature_key' => $feature_key], ['%s']); $deleted_feature = $wpdb->delete($products_table, ['id' => $feature_id], ['%d']); $message_code = ($deleted_feature !== false) ? 'feature_deleted' : 'feature_delete_error_db'; } else { $message_code = 'feature_delete_error_not_found'; } wp_safe_redirect(add_query_arg(['page' => 'orunk-users-features-plans', 'orunk_message' => $message_code], admin_url('admin.php'))); exit;
    }

    /**
     * Handles deleting a specific Plan.
     */
     public function handle_delete_plan() {
        $plan_id = isset($_GET['plan_id']) ? absint($_GET['plan_id']) : 0; $feature_key = isset($_GET['feature_key']) ? sanitize_key($_GET['feature_key']) : ''; if ($plan_id <= 0 || empty($feature_key) || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'orunk_delete_plan_' . $plan_id)) { wp_die(__('Security check failed or invalid plan/feature ID.', 'orunk-users')); } if (!current_user_can('manage_options')) { wp_die(__('You do not have permission to delete plans.', 'orunk-users')); } global $wpdb; $plans_table = $wpdb->prefix . 'orunk_product_plans'; $deleted = $wpdb->delete($plans_table, ['id' => $plan_id], ['%d']); $message_code = ($deleted !== false) ? 'plan_deleted' : 'plan_delete_error_db'; wp_safe_redirect(add_query_arg(['page' => 'orunk-users-features-plans', 'view' => 'feature', 'feature_key' => $feature_key, 'orunk_message' => $message_code], admin_url('admin.php'))); exit;
     }


    //--------------------------------------------------
    // HTML Display Functions
    //--------------------------------------------------

    /**
     * Displays the main admin page content, routing to different views.
     */
    public function admin_page_html() {
        global $wpdb; $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'list_features'; echo '<div class="wrap orunk-users-features-plans-wrap">'; echo '<h1>' . esc_html__('Manage Features & Plans', 'orunk-users') . '</h1>'; $this->display_admin_notices(); switch ($view) { case 'edit_feature': case 'add_feature': $this->display_feature_form(); break; case 'feature': $this->display_plans_list(); break; case 'edit_plan': case 'add_plan': $this->display_plan_form(); break; case 'list_features': default: $this->display_features_list(); break; } echo '</div>';
    }

    /**
     * Displays admin notices based on the 'orunk_message' query parameter.
     */
    private function display_admin_notices() {
        if (isset($_GET['orunk_message'])) { $code = sanitize_key($_GET['orunk_message']); $messages = [ 'feature_saved' => __('Feature saved successfully.', 'orunk-users'), 'feature_error_required' => __('Error: Feature Key and Name are required.', 'orunk-users'), 'feature_key_exists' => __('Error: Feature Key already exists and must be unique.', 'orunk-users'), 'feature_error_db' => __('Error saving feature to the database.', 'orunk-users'), 'feature_deleted' => __('Feature and associated plans deleted successfully.', 'orunk-users'), 'feature_delete_error_db' => __('Error deleting feature from the database.', 'orunk-users'), 'feature_delete_error_not_found' => __('Error: Feature to delete was not found.', 'orunk-users'), 'plan_saved' => __('Plan saved successfully.', 'orunk-users'), 'plan_error_required' => __('Error: Plan Name, Price, Duration, and Payment Type are required and must be valid.', 'orunk-users'), // Updated error message
             'plan_error_db' => __('Error saving plan to the database.', 'orunk-users'), 'plan_deleted' => __('Plan deleted successfully.', 'orunk-users'), 'plan_delete_error_db' => __('Error deleting plan from the database.', 'orunk-users'), ]; $message_text = $messages[$code] ?? __('An unknown action occurred.', 'orunk-users'); $message_class = (strpos($code, 'error') !== false || strpos($code, 'exists') !== false) ? 'notice-error' : 'notice-success'; echo '<div id="message" class="notice ' . esc_attr($message_class) . ' is-dismissible"><p>' . esc_html($message_text) . '</p></div>'; }
    }

    /**
     * Displays the list of all defined Features.
     */
    private function display_features_list() {
        global $wpdb; $features = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}orunk_products ORDER BY product_name ASC", ARRAY_A); echo '<p><a href="' . admin_url('admin.php?page=orunk-users-features-plans&view=add_feature') . '" class="page-title-action">' . esc_html__('Add New Feature', 'orunk-users') . '</a></p>'; ?> <table class="wp-list-table widefat fixed striped features-table"> <thead> <tr> <th scope="col" class="manage-column column-name"><?php esc_html_e('Feature Name', 'orunk-users'); ?></th> <th scope="col" class="manage-column column-key"><?php esc_html_e('Feature Key', 'orunk-users'); ?></th> <th scope="col" class="manage-column column-description"><?php esc_html_e('Description', 'orunk-users'); ?></th> <th scope="col" class="manage-column column-actions"><?php esc_html_e('Actions', 'orunk-users'); ?></th> </tr> </thead> <tbody id="the-list"> <?php if (empty($features)) : ?> <tr> <td colspan="4"><?php esc_html_e('No features found. Add a feature to define product types.', 'orunk-users'); ?></td> </tr> <?php else : ?> <?php foreach ($features as $feature) : ?> <?php $edit_url = admin_url('admin.php?page=orunk-users-features-plans&view=edit_feature&feature_id=' . $feature['id']); $view_plans_url = admin_url('admin.php?page=orunk-users-features-plans&view=feature&feature_key=' . $feature['feature']); $delete_url = add_query_arg( ['_wpnonce' => wp_create_nonce('orunk_delete_feature_' . $feature['id'])], admin_url('admin-post.php?action=orunk_delete_feature&feature_id=' . $feature['id']) ); ?> <tr> <td class="column-name has-row-actions column-primary"> <strong><a href="<?php echo esc_url($view_plans_url); ?>" aria-label="<?php printf(esc_attr__('View plans for %s', 'orunk-users'), $feature['product_name']); ?>"><?php echo esc_html($feature['product_name']); ?></a></strong> <div class="row-actions"> <span class="view"><a href="<?php echo esc_url($view_plans_url); ?>"><?php esc_html_e('View/Add Plans', 'orunk-users'); ?></a> | </span> <span class="edit"><a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit Feature', 'orunk-users'); ?></a> | </span> <span class="delete"><a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this feature AND all its associated plans? This cannot be undone.', 'orunk-users'); ?>');" style="color:red;"><?php esc_html_e('Delete', 'orunk-users'); ?></a></span> </div> </td> <td class="column-key"><code><?php echo esc_html($feature['feature']); ?></code></td> <td class="column-description"><?php echo esc_html($feature['description']); ?></td> <td class="column-actions"> <a href="<?php echo esc_url($view_plans_url); ?>" class="button button-secondary"><?php esc_html_e('View Plans', 'orunk-users'); ?></a> </td> </tr> <?php endforeach; ?> <?php endif; ?> </tbody> </table> <?php
    }

    /**
     * Displays the form for adding or editing a Feature.
     */
    private function display_feature_form() {
        global $wpdb;
        $feature_id = isset($_GET['feature_id']) ? absint($_GET['feature_id']) : 0;
        $feature = null;
        $is_editing = false;
        $feature_categories = []; // Load categories for dropdown

        // Load categories from DB
        if (class_exists('Custom_Orunk_DB')) {
             $orunk_db_temp = new Custom_Orunk_DB();
             if (method_exists($orunk_db_temp, 'get_all_feature_categories')) {
                $feature_categories = $orunk_db_temp->get_all_feature_categories();
             }
        }

        if ($feature_id > 0) {
            $feature = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orunk_products WHERE id = %d", $feature_id), ARRAY_A);
            if ($feature) {
                $is_editing = true;
            }
        }
        ?>
        <h2><?php echo $is_editing ? esc_html__('Edit Feature', 'orunk-users') : esc_html__('Add New Feature', 'orunk-users'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="orunk_save_feature">
            <input type="hidden" name="feature_id" value="<?php echo esc_attr($feature_id); ?>">
            <?php wp_nonce_field('orunk_save_feature_action', 'orunk_save_feature_nonce'); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="feature_key"><?php esc_html_e('Feature Key *', 'orunk-users'); ?></label></th>
                    <td>
                        <input name="feature_key" type="text" id="feature_key" value="<?php echo $is_editing ? esc_attr($feature['feature']) : ''; ?>" class="regular-text" required aria-required="true" <?php echo $is_editing ? 'readonly' : ''; ?> pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only.">
                        <p class="description"><?php esc_html_e('Unique identifier (e.g., "bin_api", "convojet_pro"). Lowercase letters, numbers, underscores only. Cannot be changed after creation.', 'orunk-users'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="product_name"><?php esc_html_e('Feature Name *', 'orunk-users'); ?></label></th>
                    <td><input name="product_name" type="text" id="product_name" value="<?php echo $is_editing ? esc_attr($feature['product_name']) : ''; ?>" class="regular-text" required aria-required="true"></td>
                </tr>
                 <tr valign="top"> <?php // Category Dropdown ?>
                    <th scope="row"><label for="category"><?php esc_html_e('Category', 'orunk-users'); ?></label></th>
                    <td>
                        <select name="category" id="category">
                            <option value=""><?php esc_html_e('-- Select Category --', 'orunk-users'); ?></option>
                            <?php if (!empty($feature_categories)): ?>
                                <?php foreach ($feature_categories as $category): ?>
                                    <option value="<?php echo esc_attr($category['category_slug']); ?>" <?php selected($is_editing ? ($feature['category'] ?? '') : '', $category['category_slug']); ?>>
                                        <?php echo esc_html($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                         <p class="description"><?php esc_html_e('Assign this feature to a category (optional). Manage categories in the admin interface.', 'orunk-users'); ?></p>
                    </td>
                 </tr>
                <tr valign="top">
                     <th scope="row"><label for="description"><?php esc_html_e('Description', 'orunk-users'); ?></label></th>
                     <td><textarea name="description" id="description" rows="3" class="large-text"><?php echo $is_editing ? esc_textarea($feature['description']) : ''; ?></textarea></td>
                </tr>

                <?php // --- START: Added Requires License Checkbox --- ?>
                 <tr valign="top">
                    <th scope="row"><?php esc_html_e('License Required?', 'orunk-users'); ?></th>
                    <td>
                         <fieldset>
                            <legend class="screen-reader-text"><span><?php esc_html_e('Requires License', 'orunk-users'); ?></span></legend>
                            <label for="requires_license">
                                <input name="requires_license" type="checkbox" id="requires_license" value="1"
                                    <?php checked($is_editing ? ($feature['requires_license'] ?? 0) : 0, 1); ?>>
                                <?php esc_html_e('Generate a unique license key upon purchase activation?', 'orunk-users'); ?>
                            </label>
                             <p class="description"><?php esc_html_e('Check this if the feature needs a license key for activation (e.g., downloadable plugins, APIs). The system will generate a key when a purchase becomes active.', 'orunk-users'); ?></p>
                        </fieldset>
                    </td>
                 </tr>
                 <?php // --- END: Added Requires License Checkbox --- ?>


                 <tr valign="top"> <?php // Download URL (Existing) ?>
                    <th scope="row"><label for="download_url"><?php esc_html_e('Download URL (Optional)', 'orunk-users'); ?></label></th>
                    <td>
                        <input name="download_url" type="url" id="download_url" value="<?php echo $is_editing ? esc_url($feature['download_url'] ?? '') : ''; ?>" class="regular-text code" placeholder="https://example.com/path/to/plugin.zip">
                        <p class="description"><?php esc_html_e('Enter the direct URL to the downloadable file (e.g., ZIP). Leave blank if not applicable.', 'orunk-users'); ?></p>
                    </td>
                 </tr>
                 <tr valign="top"> <?php // Download Limit (Existing) ?>
                    <th scope="row"><label for="download_limit_daily"><?php esc_html_e('Daily Download Limit', 'orunk-users'); ?></label></th>
                    <td>
                        <input name="download_limit_daily" type="number" step="1" min="0" id="download_limit_daily" value="<?php echo esc_attr($is_editing ? ($feature['download_limit_daily'] ?? 5) : 5); ?>" class="small-text">
                        <p class="description"><?php esc_html_e('Maximum downloads allowed per user per day (0 for unlimited, default 5).', 'orunk-users'); ?></p>
                    </td>
                 </tr>

            </table>
            <?php submit_button($is_editing ? __('Update Feature', 'orunk-users') : __('Add Feature', 'orunk-users')); ?>
        </form>
        <p><a href="<?php echo admin_url('admin.php?page=orunk-users-features-plans'); ?>"><?php esc_html_e('&larr; Back to Features List', 'orunk-users'); ?></a></p>
        <?php
    }

    /**
     * Displays the list of Plans for a specific Feature.
     */
    private function display_plans_list() {
        global $wpdb; $feature_key = isset($_GET['feature_key']) ? sanitize_key($_GET['feature_key']) : ''; if (empty($feature_key)) { echo '<div class="notice notice-error"><p>' . esc_html__('Error: No feature key specified.', 'orunk-users') . '</p></div>'; echo '<p><a href="' . admin_url('admin.php?page=orunk-users-features-plans') . '">&larr; Back to Features List</a></p>'; return; } $feature_details = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orunk_products WHERE feature = %s", $feature_key), ARRAY_A); $plans = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orunk_product_plans WHERE product_feature_key = %s ORDER BY price ASC", $feature_key), ARRAY_A); if (!$feature_details) { echo '<div class="notice notice-error"><p>' . esc_html__('Error: Feature not found.', 'orunk-users') . '</p></div>'; echo '<p><a href="' . admin_url('admin.php?page=orunk-users-features-plans') . '">&larr; Back to Features List</a></p>'; return; } echo '<h2>' . esc_html($feature_details['product_name']) . ' (' . esc_html($feature_key) . ') - ' . esc_html__('Plans', 'orunk-users') . '</h2>'; echo '<p><a href="' . admin_url('admin.php?page=orunk-users-features-plans&view=add_plan&feature_key=' . $feature_key) . '" class="page-title-action">' . esc_html__('Add New Plan', 'orunk-users') . '</a>'; echo ' <a href="' . admin_url('admin.php?page=orunk-users-features-plans') . '" class="page-title-action">' . esc_html__('&larr; Back to Features List', 'orunk-users') . '</a></p>'; ?> <table class="wp-list-table widefat fixed striped plans-table"> <thead> <tr> <th scope="col" class="manage-column column-name"><?php esc_html_e('Plan Name', 'orunk-users'); ?></th> <th scope="col" class="manage-column column-price"><?php esc_html_e('Price', 'orunk-users'); ?></th> <th scope="col" class="manage-column column-duration"><?php esc_html_e('Duration', 'orunk-users'); ?></th> <th scope="col" class="manage-column column-limits"><?php esc_html_e('Limits (Day/Month)', 'orunk-users'); ?></th> <th scope="col" class="manage-column column-active"><?php esc_html_e('Active', 'orunk-users'); ?></th> <th scope="col" class="manage-column column-actions"><?php esc_html_e('Actions', 'orunk-users'); ?></th> </tr> </thead> <tbody id="the-list"> <?php if (empty($plans)) : ?> <tr> <td colspan="6"><?php esc_html_e('No plans found for this feature. Add one now!', 'orunk-users'); ?></td> </tr> <?php else : ?> <?php foreach ($plans as $plan) : ?> <?php $edit_url = admin_url('admin.php?page=orunk-users-features-plans&view=edit_plan&plan_id=' . $plan['id'] . '&feature_key=' . $feature_key); $delete_url = add_query_arg( ['_wpnonce' => wp_create_nonce('orunk_delete_plan_' . $plan['id'])], admin_url('admin-post.php?action=orunk_delete_plan&plan_id=' . $plan['id'] . '&feature_key=' . $feature_key) ); ?> <tr> <td class="column-name has-row-actions column-primary"> <strong><a href="<?php echo esc_url($edit_url); ?>" aria-label="<?php printf(esc_attr__('Edit %s', 'orunk-users'), $plan['plan_name']); ?>"><?php echo esc_html($plan['plan_name']); ?></a></strong> <div class="row-actions"> <span class="edit"><a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit', 'orunk-users'); ?></a> | </span> <span class="delete"><a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this plan?', 'orunk-users'); ?>');" style="color:red;"><?php esc_html_e('Delete', 'orunk-users'); ?></a></span> </div> </td> <td class="column-price">$<?php echo esc_html(number_format(floatval($plan['price']), 2)); ?></td> <td class="column-duration"><?php echo esc_html($plan['duration_days']); ?> <?php esc_html_e('days', 'orunk-users'); ?></td> <td class="column-limits"><?php echo esc_html($plan['requests_per_day'] ?? 'N/A'); ?> / <?php echo esc_html($plan['requests_per_month'] ?? 'N/A'); ?></td> <td class="column-active"><?php echo $plan['is_active'] ? esc_html__('Yes', 'orunk-users') : esc_html__('No', 'orunk-users'); ?></td> <td class="column-actions"> <a href="<?php echo esc_url($edit_url); ?>" class="button button-secondary"><?php esc_html_e('Edit', 'orunk-users'); ?></a> </td> </tr> <?php endforeach; ?> <?php endif; ?> </tbody> </table> <?php
    }

    /**
     * Displays the form for adding or editing a Plan.
     */
    private function display_plan_form() {
        global $wpdb; $plan_id = isset($_GET['plan_id']) ? absint($_GET['plan_id']) : 0; $feature_key = isset($_GET['feature_key']) ? sanitize_key($_GET['feature_key']) : ''; $plan = null; $is_editing = false; if ($plan_id > 0) { $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orunk_product_plans WHERE id = %d", $plan_id), ARRAY_A); if ($plan) { $is_editing = true; $feature_key = $plan['product_feature_key']; } } if (empty($feature_key)) { echo '<div class="notice notice-error"><p>' . esc_html__('Error: Feature key is missing or invalid.', 'orunk-users') . '</p></div>'; echo '<p><a href="' . admin_url('admin.php?page=orunk-users-features-plans') . '">&larr; Back to Features List</a></p>'; return; } $feature_name = $wpdb->get_var($wpdb->prepare("SELECT product_name FROM {$wpdb->prefix}orunk_products WHERE feature = %s", $feature_key)); ?>
        <h2><?php echo $is_editing ? esc_html__('Edit Plan', 'orunk-users') : esc_html__('Add New Plan', 'orunk-users'); ?></h2>
        <p><?php printf(esc_html__('For Feature: %s (%s)', 'orunk-users'), esc_html($feature_name ?: 'Unknown'), '<code>' . esc_html($feature_key) . '</code>'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="orunk_save_plan">
            <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan_id); ?>">
            <input type="hidden" name="product_feature_key" value="<?php echo esc_attr($feature_key); ?>">
            <?php wp_nonce_field('orunk_save_plan_action', 'orunk_save_plan_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="plan_name"><?php esc_html_e('Plan Name *', 'orunk-users'); ?></label></th>
                    <td><input name="plan_name" type="text" id="plan_name" value="<?php echo $is_editing ? esc_attr($plan['plan_name']) : ''; ?>" class="regular-text" required aria-required="true"></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="description"><?php esc_html_e('Description', 'orunk-users'); ?></label></th>
                    <td><textarea name="description" id="description" rows="3" class="large-text"><?php echo $is_editing ? esc_textarea($plan['description']) : ''; ?></textarea>
                    <p class="description"><?php esc_html_e('Optional description shown on pricing tables or checkout.', 'orunk-users'); ?></p></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="price"><?php esc_html_e('Price ($) *', 'orunk-users'); ?></label></th>
                    <td><input name="price" type="number" step="0.01" min="0" id="price" value="<?php echo $is_editing ? esc_attr($plan['price']) : '0.00'; ?>" class="small-text" required aria-required="true"></td>
                </tr>
                 <tr valign="top"> <?php // Payment Type Radio Buttons ?>
                     <th scope="row"><?php esc_html_e('Payment Type *', 'orunk-users'); ?></th>
                     <td>
                         <fieldset>
                            <legend class="screen-reader-text"><span><?php esc_html_e('Payment Type', 'orunk-users'); ?></span></legend>
                            <label style="margin-right: 20px;">
                                <input name="payment_type" type="radio" value="subscription" <?php checked($is_editing ? ($plan['is_one_time'] != 1) : true); ?>>
                                <?php esc_html_e('Subscription (Recurring)', 'orunk-users'); ?>
                            </label>
                             <label>
                                <input name="payment_type" type="radio" value="one_time" <?php checked($is_editing ? ($plan['is_one_time'] == 1) : false); ?>>
                                <?php esc_html_e('One-Time Payment', 'orunk-users'); ?>
                            </label>
                             <p class="description"><?php esc_html_e('Choose if this plan requires recurring payments or a single payment.', 'orunk-users'); ?></p>
                        </fieldset>
                     </td>
                 </tr>
                <tr valign="top" id="plan-duration-wrapper"> <?php // Wrapper for duration ?>
                    <th scope="row"><label for="duration_days"><?php esc_html_e('Duration (days) *', 'orunk-users'); ?></label></th>
                    <td><input name="duration_days" type="number" step="1" min="1" id="duration_days" value="<?php echo $is_editing ? esc_attr($plan['duration_days']) : '30'; ?>" class="small-text" required aria-required="true">
                    <p class="description"><?php esc_html_e('Number of days the plan/subscription period is active. Ignored for One-Time Payment.', 'orunk-users'); ?></p></td>
                </tr>

                 <?php // --- START: Added Activation Limit --- ?>
                 <tr valign="top">
                    <th scope="row"><label for="activation_limit"><?php esc_html_e('Activation Limit', 'orunk-users'); ?></label></th>
                    <td>
                        <input name="activation_limit" type="number" step="1" min="0" id="activation_limit" value="<?php echo esc_attr($is_editing ? ($plan['activation_limit'] ?? 1) : 1); ?>" class="small-text">
                         <p class="description"><?php esc_html_e('Maximum number of sites this license key can be activated on. Set 0 or leave blank for unlimited (though NULL is stored). Default is 1.', 'orunk-users'); ?></p>
                    </td>
                 </tr>
                 <?php // --- END: Added Activation Limit --- ?>


                 <tr valign="top">
                    <th scope="row"><label for="requests_per_day"><?php esc_html_e('Requests/Day Limit', 'orunk-users'); ?></label></th>
                    <td><input name="requests_per_day" type="number" step="1" min="0" id="requests_per_day" value="<?php echo $is_editing ? esc_attr($plan['requests_per_day'] ?? '') : ''; ?>" class="small-text" placeholder="<?php esc_attr_e('Unlimited', 'orunk-users'); ?>">
                    <p class="description"><?php esc_html_e('Maximum API requests allowed per day. Leave blank for unlimited.', 'orunk-users'); ?></p></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="requests_per_month"><?php esc_html_e('Requests/Month Limit', 'orunk-users'); ?></label></th>
                    <td><input name="requests_per_month" type="number" step="1" min="0" id="requests_per_month" value="<?php echo $is_editing ? esc_attr($plan['requests_per_month'] ?? '') : ''; ?>" class="small-text" placeholder="<?php esc_attr_e('Unlimited', 'orunk-users'); ?>">
                    <p class="description"><?php esc_html_e('Maximum API requests allowed per month (or billing cycle). Leave blank for unlimited.', 'orunk-users'); ?></p></td>
                </tr>
                 <tr valign="top"> <?php // Gateway IDs ?>
                    <th scope="row"><?php esc_html_e('Gateway IDs (Optional)', 'orunk-users'); ?></th>
                    <td>
                         <p style="margin-bottom: 0.5rem;">
                            <label for="paypal_plan_id" style="display: block; margin-bottom: 0.2rem;"><?php esc_html_e('PayPal Plan ID:', 'orunk-users'); ?></label>
                            <input name="paypal_plan_id" type="text" id="paypal_plan_id" value="<?php echo $is_editing ? esc_attr($plan['paypal_plan_id'] ?? '') : ''; ?>" class="regular-text code">
                         </p>
                          <p>
                            <label for="stripe_price_id" style="display: block; margin-bottom: 0.2rem;"><?php esc_html_e('Stripe Price ID:', 'orunk-users'); ?></label>
                            <input name="stripe_price_id" type="text" id="stripe_price_id" value="<?php echo $is_editing ? esc_attr($plan['stripe_price_id'] ?? '') : ''; ?>" class="regular-text code">
                         </p>
                         <p class="description"><?php esc_html_e('Enter the corresponding Plan/Price IDs from PayPal and Stripe if this is a recurring subscription plan handled by them.', 'orunk-users'); ?></p>
                     </td>
                 </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Status', 'orunk-users'); ?></th>
                    <td><fieldset>
                        <legend class="screen-reader-text"><span><?php esc_html_e('Status', 'orunk-users'); ?></span></legend>
                        <label for="is_active">
                            <input name="is_active" type="checkbox" id="is_active" value="1" <?php checked($is_editing ? ($plan['is_active'] ?? 1) : 1); ?>>
                            <?php esc_html_e('Plan is Active', 'orunk-users'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Only active plans can be purchased by users.', 'orunk-users'); ?></p>
                    </fieldset></td>
                </tr>
            </table>
            <?php submit_button($is_editing ? __('Update Plan', 'orunk-users') : __('Add Plan', 'orunk-users')); ?>
        </form>
        <p><a href="<?php echo admin_url('admin.php?page=orunk-users-features-plans&view=feature&feature_key=' . $feature_key); ?>"><?php esc_html_e('&larr; Back to Plans List', 'orunk-users'); ?></a></p>

        <?php // Script to handle payment type change disabling duration ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                const paymentTypeRadios = document.querySelectorAll('input[name="payment_type"]');
                const durationWrapper = document.getElementById('plan-duration-wrapper');
                const durationInput = document.getElementById('duration_days');

                function handlePaymentTypeChangeDisplay() {
                    // Ensure the checked radio button exists before accessing its value
                    const checkedRadio = document.querySelector('input[name="payment_type"]:checked');
                    if (!checkedRadio) return; // Exit if no radio is checked

                    const selectedType = checkedRadio.value;

                    if (selectedType === 'one_time') {
                        if(durationInput) {
                            // Store original value if not already stored and input is writable
                            if (!durationInput.readOnly && typeof durationInput.dataset.originalValue === 'undefined') {
                                durationInput.dataset.originalValue = durationInput.value;
                            }
                            durationInput.value = '9999'; // Use a high value to represent lifetime/one-time
                            durationInput.readOnly = true;
                        }
                        if(durationWrapper) durationWrapper.style.opacity = '0.5';
                    } else { // Subscription
                        if(durationInput) {
                            // Restore original value if it was stored
                            durationInput.value = durationInput.dataset.originalValue || '30'; // Default to 30 if no original value
                            durationInput.readOnly = false;
                            // Remove the stored original value attribute if desired, or keep it
                            // delete durationInput.dataset.originalValue;
                        }
                         if(durationWrapper) durationWrapper.style.opacity = '1';
                    }
                }

                paymentTypeRadios.forEach(radio => {
                    radio.addEventListener('change', handlePaymentTypeChangeDisplay);
                });

                // Initial check on page load
                handlePaymentTypeChangeDisplay();
            });
        </script>
        <?php
    }

} // End Class Custom_Orunk_Products