<?php
/**
 * Orunk Users Database Class
 *
 * Handles database schema creation, updates, and data retrieval for the plugin.
 *
 * MODIFIED: Added download_url, download_limit_daily columns to products table.
 * MODIFIED: Joined products table in get_user_purchases to retrieve category.
 *
 * @package OrunkUsers
 * @version 1.13.0 // <<<--- INCREMENTED DB Version (Example)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Custom_Orunk_DB {

    /**
     * Constructor.
     */
    public function __construct() {
        // Constructor logic can remain empty if no immediate actions are needed upon instantiation
        // The create_db_tables method is typically called during activation or version check.
    }


    /**
     * Creates or updates the necessary database tables using dbDelta.
     * Includes paypal_plan_id and stripe_price_id in the plans table.
     * Includes download_url and download_limit_daily in the products table. // <-- New Comment
     */
   public function create_db_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Ensure dbDelta function is available.
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        // --- wp_orunk_products Table (Stores Feature Groups) ---
        $products_table = $wpdb->prefix . 'orunk_products';
        // *** MODIFICATION START: Added download columns ***
        $products_sql = "CREATE TABLE `{$products_table}` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `feature` VARCHAR(50) NOT NULL,
            `product_name` VARCHAR(100) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `category` VARCHAR(50) DEFAULT NULL,
            `download_url` VARCHAR(255) DEFAULT NULL,         -- Added for plugin/theme downloads
            `download_limit_daily` INT DEFAULT 5,             -- Added for download limits
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `feature` (`feature`),
            INDEX `idx_category` (`category`)                 -- Ensure index exists
        ) {$charset_collate};";
        // *** MODIFICATION END ***
        dbDelta($products_sql);

        // --- wp_orunk_product_plans Table (Stores Pricing Plans/Tiers) ---
        // (Includes previous modification for gateway IDs - unchanged in this step)
        $plans_table = $wpdb->prefix . 'orunk_product_plans';
        $plans_sql = "CREATE TABLE `{$plans_table}` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `product_feature_key` VARCHAR(50) NOT NULL,
            `plan_name` VARCHAR(100) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `price` DECIMAL(10,2) NOT NULL,
            `duration_days` INT NOT NULL DEFAULT 30,
            `requests_per_day` INT DEFAULT NULL,
            `requests_per_month` INT DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `is_one_time` TINYINT(1) NOT NULL DEFAULT 0,
            `paypal_plan_id` VARCHAR(100) DEFAULT NULL,
            `stripe_price_id` VARCHAR(100) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_feature_key` (`product_feature_key`),
            INDEX `idx_is_active` (`is_active`),
            INDEX `idx_paypal_plan_id` (`paypal_plan_id`),
            INDEX `idx_stripe_price_id` (`stripe_price_id`)
        ) {$charset_collate};";
        dbDelta($plans_sql);

        // --- wp_orunk_user_purchases Table ---
        // (Schema definition unchanged from previous version)
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $purchases_sql = "CREATE TABLE `{$purchases_table}` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) UNSIGNED NOT NULL,
            `plan_id` mediumint(9) NOT NULL,
            `product_feature_key` VARCHAR(50) DEFAULT NULL,
            `api_key` VARCHAR(64) DEFAULT NULL,
            `license_key` VARCHAR(100) DEFAULT NULL,          -- Existing license key column
            `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
            `purchase_date` DATETIME NOT NULL,
            `activation_date` DATETIME DEFAULT NULL,
            `expiry_date` DATETIME DEFAULT NULL,
            `next_payment_date` DATETIME DEFAULT NULL,
            `payment_gateway` VARCHAR(50) DEFAULT NULL,
            `transaction_id` VARCHAR(150) DEFAULT NULL,
            `parent_purchase_id` mediumint(9) DEFAULT NULL,
            `plan_details_snapshot` TEXT DEFAULT NULL,
            `plan_requests_per_day` INT DEFAULT NULL,
            `plan_requests_per_month` INT DEFAULT NULL,
            `currency` VARCHAR(3) DEFAULT NULL,
            `pending_switch_plan_id` mediumint(9) DEFAULT NULL,
            `auto_renew` TINYINT(1) NOT NULL DEFAULT 0,
            `transaction_type` VARCHAR(30) NOT NULL DEFAULT 'purchase',
            `cancellation_effective_date` DATETIME DEFAULT NULL,
            `gateway_subscription_id` VARCHAR(150) DEFAULT NULL,
            `gateway_customer_id` VARCHAR(150) DEFAULT NULL,
            `gateway_payment_method_id` VARCHAR(150) DEFAULT NULL,
            `amount_paid` DECIMAL(10,2) DEFAULT NULL,
            `failure_timestamp` DATETIME DEFAULT NULL,
            `failure_reason` TEXT DEFAULT NULL,
            `gateway_metadata` TEXT DEFAULT NULL,
            `billing_period` VARCHAR(20) DEFAULT NULL,
            `trial_start_date` DATETIME DEFAULT NULL,
            `trial_end_date` DATETIME DEFAULT NULL,
            `discount_code_used` VARCHAR(100) DEFAULT NULL,
            `discount_amount` DECIMAL(10,2) DEFAULT NULL,
            `tax_details` TEXT DEFAULT NULL,
            `refund_details` TEXT DEFAULT NULL,
            `chargeback_details` TEXT DEFAULT NULL,
            `cancellation_reason` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `admin_notes` TEXT DEFAULT NULL,
            `dunning_status` VARCHAR(20) DEFAULT NULL,
            `payment_attempts` TINYINT(2) NOT NULL DEFAULT 0,
            `affiliate_id` VARCHAR(100) DEFAULT NULL,
            `download_ids` TEXT DEFAULT NULL,                -- Existing downloads column (maybe unused?)
            `modified_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `api_key` (`api_key`),
            UNIQUE KEY `license_key` (`license_key`),          -- Existing unique key constraint
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_expiry_date` (`expiry_date`),
            INDEX `idx_feature_key_user` (`product_feature_key`, `user_id`),
            INDEX `idx_transaction_type` (`transaction_type`),
            INDEX `idx_parent_purchase` (`parent_purchase_id`),
            INDEX `idx_gateway_sub` (`gateway_subscription_id`),
            INDEX `idx_gateway_cust` (`gateway_customer_id`),
            INDEX `idx_next_payment_date` (`next_payment_date`),
            INDEX `idx_failure_timestamp` (`failure_timestamp`),
            INDEX `idx_discount_code` (`discount_code_used`),
            INDEX `idx_affiliate_id` (`affiliate_id`)
        ) {$charset_collate};";
        dbDelta($purchases_sql);

        // --- wp_orunk_feature_categories Table ---
        // (Schema definition unchanged)
        $categories_table = $wpdb->prefix . 'orunk_feature_categories';
        $categories_sql = "CREATE TABLE `{$categories_table}` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `category_name` VARCHAR(100) NOT NULL,
            `category_slug` VARCHAR(50) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_category_slug` (`category_slug`)
        ) {$charset_collate};";
        dbDelta($categories_sql);

        // --- wp_orunk_otp_tokens Table ---
        // (Schema definition unchanged)
        $otp_tokens_table = $wpdb->prefix . 'orunk_otp_tokens';
        $otp_tokens_sql = "CREATE TABLE `{$otp_tokens_table}` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) UNSIGNED NOT NULL,
            `token_hash` VARCHAR(255) NOT NULL,
            `expiry_timestamp` INT(11) NOT NULL,
            `resend_count` TINYINT(2) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_user_id_expiry` (`user_id`, `expiry_timestamp`)
        ) {$charset_collate};";
        dbDelta($otp_tokens_sql);

        // --- wp_orunk_feature_types Table ---
        // (Schema definition unchanged)
        $types_table = $wpdb->prefix . 'orunk_feature_types';
        $types_sql = "CREATE TABLE `{$types_table}` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `type_name` VARCHAR(100) NOT NULL,
            `type_slug` VARCHAR(50) NOT NULL,
            `description` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_type_slug` (`type_slug`)
        ) {$charset_collate};";
        dbDelta($types_sql);


        // Insert/Update default data
        // (Function call unchanged)
        try {
             error_log('Orunk DB: Attempting to insert default data (after schema update check)...');
             $this->insert_default_data();
             error_log('Orunk DB: Finished inserting default data.');
        } catch (Exception $e) {
            error_log('Orunk DB Error during insert_default_data: ' . $e->getMessage());
        }
    }

    /**
     * Inserts default features, plans, and categories if they don't exist.
     * (Function unchanged)
     */
    public function insert_default_data() {
        global $wpdb;
        $products_table = $wpdb->prefix . 'orunk_products';
        $plans_table = $wpdb->prefix . 'orunk_product_plans';
        $categories_table = $wpdb->prefix . 'orunk_feature_categories';
        $types_table = $wpdb->prefix . 'orunk_feature_types';

        // --- Default Categories ---
        $default_categories = [ /* ... (unchanged) ... */ ['name' => 'API Service', 'slug' => 'api-service'], ['name' => 'WordPress Plugin', 'slug' => 'wordpress-plugin'], ['name' => 'WordPress Theme', 'slug' => 'wordpress-theme'], ['name' => 'Website Feature', 'slug' => 'website-feature'], ]; $cat_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $categories_table)) === $categories_table; if($cat_table_exists) { $slug_exists = $wpdb->get_var( $wpdb->prepare("SHOW COLUMNS FROM `$categories_table` LIKE %s", 'category_slug') ); if ($slug_exists) { foreach ($default_categories as $category) { $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$categories_table` WHERE `category_slug` = %s", $category['slug'])); if ($exists == 0) { $wpdb->insert( $categories_table, ['category_name' => $category['name'], 'category_slug' => $category['slug']], ['%s', '%s'] ); } } } else { error_log("Orunk DB insert_default_data WARNING: category_slug column missing in $categories_table"); } } else { error_log("Orunk DB insert_default_data WARNING: Categories table $categories_table missing."); }

        // --- BIN API Feature & Plans (Assign default category) ---
        $bin_api_feature = 'bin_api'; $bin_api_category = 'api-service'; $prod_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $products_table)) === $products_table; if($prod_table_exists) { $feature_exists = $wpdb->get_var( $wpdb->prepare("SHOW COLUMNS FROM `$products_table` LIKE %s", 'feature') ); if ($feature_exists) { $bin_api_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$products_table` WHERE `feature` = %s", $bin_api_feature)); if ($bin_api_exists == 0) { $wpdb->insert( $products_table, [ 'feature' => $bin_api_feature, 'product_name' => 'BIN Lookup API', 'description' => 'Access tiers for the BIN Lookup API.', 'category' => $bin_api_category ], ['%s', '%s', '%s', '%s'] ); } else { $wpdb->update( $products_table, ['category' => $bin_api_category], ['feature' => $bin_api_feature, 'category' => null], ['%s'], ['%s', '%s'] ); } $plan_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $plans_table)) === $plans_table; if($plan_table_exists) { $bin_api_plans_exist = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$plans_table` WHERE `product_feature_key` = %s", $bin_api_feature)); if ($bin_api_plans_exist == 0) { $wpdb->insert( $plans_table, ['product_feature_key' => $bin_api_feature, 'plan_name' => 'Free', 'price' => 0.00, 'duration_days' => 30, 'requests_per_day' => 100, 'requests_per_month' => 3000, 'is_one_time' => 0], ['%s', '%s', '%f', '%d', '%d', '%d', '%d']); $wpdb->insert( $plans_table, ['product_feature_key' => $bin_api_feature, 'plan_name' => 'Pro', 'price' => 9.00, 'duration_days' => 30, 'requests_per_day' => null, 'requests_per_month' => 50000, 'is_one_time' => 0], ['%s', '%s', '%f', '%d', null, '%d', '%d']); $wpdb->insert( $plans_table, ['product_feature_key' => $bin_api_feature, 'plan_name' => 'Business', 'price' => 29.00, 'duration_days' => 30, 'requests_per_day' => null, 'requests_per_month' => 200000, 'is_one_time' => 0], ['%s', '%s', '%f', '%d', null, '%d', '%d']); } } else { error_log("Orunk DB insert_default_data WARNING: Plans table $plans_table missing."); } } else { error_log("Orunk DB insert_default_data WARNING: feature column missing in $products_table"); } } else { error_log("Orunk DB insert_default_data WARNING: Products table $products_table missing."); }

        // --- Ad Removal Feature & Plan (Assign default category) ---
        $ad_removal_feature = 'ad_removal'; $ad_removal_category = 'website-feature'; if($prod_table_exists && $feature_exists) { $ad_removal_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$products_table` WHERE `feature` = %s", $ad_removal_feature)); if ($ad_removal_exists == 0) { $wpdb->insert( $products_table, [ 'feature' => $ad_removal_feature, 'product_name' => 'Ad Removal', 'description' => 'Remove ads from the website.', 'category' => $ad_removal_category ], ['%s', '%s', '%s', '%s'] ); } else { $wpdb->update( $products_table, ['category' => $ad_removal_category], ['feature' => $ad_removal_feature, 'category' => null], ['%s'], ['%s', '%s'] ); } $plan_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $plans_table)) === $plans_table; if($plan_table_exists) { $ad_removal_plans_exist = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$plans_table` WHERE `product_feature_key` = %s", $ad_removal_feature)); if ($ad_removal_plans_exist == 0) { $wpdb->insert( $plans_table, ['product_feature_key' => $ad_removal_feature, 'plan_name' => 'Monthly Ad Removal', 'price' => 2.00, 'duration_days' => 30, 'is_one_time' => 0], ['%s', '%s', '%f', '%d', '%d']); } } else { error_log("Orunk DB insert_default_data WARNING: Plans table $plans_table missing."); } }

        // --- Add default feature types ---
         $types_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $types_table)) === $types_table; if ($types_table_exists) { error_log('Orunk DB insert_default_data: Feature Types table exists, proceeding with defaults.'); $default_types = [ ['type_name' => 'API Service', 'type_slug' => 'api', 'description' => 'Provides access to an Application Programming Interface.'], ['type_name' => 'WordPress Plugin', 'type_slug' => 'plugin', 'description' => 'Adds functionality to a WordPress website.'], ['type_name' => 'Website Feature', 'type_slug' => 'feature', 'description' => 'Unlocks a specific feature or content on the website.'] ]; $slug_column_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$types_table` LIKE %s", 'type_slug')); $name_column_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$types_table` LIKE %s", 'type_name')); if ($slug_column_exists && $name_column_exists) { foreach ($default_types as $type) { $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$types_table` WHERE `type_slug` = %s", $type['type_slug'])); if ($exists == 0) { $insert_result = $wpdb->insert($types_table, $type, ['%s', '%s', '%s']); if ($insert_result === false) { error_log('Orunk DB insert_default_data ERROR inserting type (' . $type['type_slug'] . '): ' . $wpdb->last_error); } } } } else { error_log('Orunk DB insert_default_data WARNING: type_slug or type_name column missing, cannot check/insert default types.'); } } else { error_log('Orunk DB insert_default_data ERROR: Feature Types table (' . $types_table . ') does not exist.'); }
    }

    /**
     * Retrieves details for a specific plan by its ID.
     * (Function unchanged)
     * @param int $plan_id The ID of the plan.
     * @return array|null Plan details as an associative array, or null if not found.
     */
    public function get_plan_details($plan_id) {
        global $wpdb;
        $plans_table = $wpdb->prefix . 'orunk_product_plans';
        if (empty($plan_id) || !is_numeric($plan_id)) { return null; }
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM `$plans_table` WHERE id = %d", absint($plan_id)), ARRAY_A);
    }

    /**
     * Retrieves user purchases, optionally filtered by status.
     * Includes plan name, plan's is_one_time flag, gateway IDs, and feature category.
     * *** MODIFIED: Joined products table to get category ***
     *
     * @param int $user_id The WordPress User ID.
     * @param string|null $status Optional status to filter by (e.g., 'active', 'pending'). If null, fetches ALL statuses.
     * @return array List of purchase records (associative arrays).
     */
    public function get_user_purchases($user_id, $status = null) {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $plans_table = $wpdb->prefix . 'orunk_product_plans';
        $products_table = $wpdb->prefix . 'orunk_products'; // Define products table

        if (empty($user_id) || !is_numeric($user_id)) { return array(); }

        // *** MODIFICATION START: Join products table and select category ***
        $sql = "SELECT p.*,
                       pl.plan_name, pl.is_one_time, pl.paypal_plan_id, pl.stripe_price_id,
                       pr.category AS feature_category -- Get the category slug
                FROM `{$purchases_table}` p
                LEFT JOIN `{$plans_table}` pl ON p.`plan_id` = pl.`id`
                LEFT JOIN `{$products_table}` pr ON p.`product_feature_key` = pr.`feature` -- Join products table
                WHERE p.`user_id` = %d";
        // *** MODIFICATION END ***
        $params = [absint($user_id)];

        if ($status !== null && is_string($status)) {
            $sql .= " AND p.`status` = %s";
            $params[] = sanitize_key($status);
        }
        $sql .= " ORDER BY p.`purchase_date` DESC";

        $query = $wpdb->prepare($sql, ...$params);
        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Retrieves a purchase record based on the API key.
     * Includes essential plan details needed for access checks and plan's gateway IDs.
     * (Function unchanged)
     * @param string $api_key The API key to search for.
     * @return array|null Purchase details as an associative array, or null if not found/invalid key.
     */
    public function get_purchase_by_api_key($api_key) {
        global $wpdb; $purchases_table = $wpdb->prefix . 'orunk_user_purchases'; $plans_table = $wpdb->prefix . 'orunk_product_plans'; if (empty($api_key) || !is_string($api_key)) { return null; } $sql = "SELECT p.`id`, p.`user_id`, p.`plan_id`, p.`api_key`, p.`status`, p.`purchase_date`, p.`expiry_date`, p.`payment_gateway`, p.`transaction_id`, p.`plan_requests_per_day`, p.`plan_requests_per_month`, p.`product_feature_key`, p.`transaction_type`, p.`failure_timestamp`, p.`failure_reason`, pl.`plan_name`, pl.`is_one_time`, pl.`paypal_plan_id`, pl.`stripe_price_id` FROM `{$purchases_table}` p LEFT JOIN `{$plans_table}` pl ON p.`plan_id` = pl.`id` WHERE p.`api_key` = %s"; return $wpdb->get_row($wpdb->prepare($sql, sanitize_text_field($api_key)), ARRAY_A);
    }

    /**
     * Retrieves the currently active purchase/plan for a user and specific feature key.
     * Includes plan's is_one_time flag and gateway IDs.
     * (Function unchanged)
     * @param int $user_id The WordPress User ID.
     * @param string $feature_key The unique feature key (e.g., 'bin_api').
     * @return array|null Active purchase details as an associative array, or null if none found.
     */
     public function get_user_active_plan_for_feature( $user_id, $feature_key ) {
        global $wpdb; $purchases_table = $wpdb->prefix . 'orunk_user_purchases'; $plans_table = $wpdb->prefix . 'orunk_product_plans'; if (empty($user_id) || !is_numeric($user_id) || empty($feature_key) || !is_string($feature_key)) { return null; } $sql = $wpdb->prepare( "SELECT p.*, pl.`plan_name`, pl.`is_one_time`, pl.`paypal_plan_id`, pl.`stripe_price_id` FROM `{$purchases_table}` p LEFT JOIN `{$plans_table}` pl ON p.`plan_id` = pl.`id` WHERE p.`user_id` = %d AND p.`product_feature_key` = %s AND p.`status` = 'active' AND (p.`expiry_date` IS NULL OR p.`expiry_date` >= %s) ORDER BY p.`purchase_date` DESC LIMIT 1", absint($user_id), sanitize_key($feature_key), current_time('mysql', 1) ); return $wpdb->get_row( $sql, ARRAY_A );
    }

    // --- Category Management Functions (Unchanged) ---
    public function get_all_feature_categories() { global $wpdb; $t = $wpdb->prefix.'orunk_feature_categories'; return $wpdb->get_results("SELECT * FROM `$t` ORDER BY category_name ASC",ARRAY_A); }
    public function add_feature_category($name, $slug) { global $wpdb;$t=$wpdb->prefix.'orunk_feature_categories';if(empty($name)||empty($slug)){return new WP_Error('missing_data',__('Category Name and Slug are required.','orunk-users'));}$name=sanitize_text_field($name);$slug=sanitize_key($slug);$e=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$t` WHERE category_slug = %s",$slug));if($e>0){return new WP_Error('slug_exists',__('Category Slug already exists.','orunk-users'));}$i=$wpdb->insert($t,['category_name'=>$name,'category_slug'=>$slug],['%s','%s']);if($i===false){error_log("Orunk DB Error adding category: ".$wpdb->last_error);return new WP_Error('db_error',__('Could not add category.','orunk-users'));}return $wpdb->insert_id; }
    public function update_feature_category($id, $name, $slug) { global $wpdb;$t=$wpdb->prefix.'orunk_feature_categories';$id=absint($id);if($id<=0||empty($name)||empty($slug)){return new WP_Error('missing_data',__('Category ID, Name, and Slug are required.','orunk-users'));}$name=sanitize_text_field($name);$slug=sanitize_key($slug);$e=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$t` WHERE category_slug = %s AND id != %d",$slug,$id));if($e>0){return new WP_Error('slug_exists',__('Category Slug already exists.','orunk-users'));}$u=$wpdb->update($t,['category_name'=>$name,'category_slug'=>$slug],['id'=>$id],['%s','%s'],['%d']);if($u===false){error_log("Orunk DB Error updating category $id: ".$wpdb->last_error);return new WP_Error('db_error',__('Could not update category.','orunk-users'));}return true; }
    public function delete_feature_category($id) { global $wpdb;$t=$wpdb->prefix.'orunk_feature_categories';$id=absint($id);if($id<=0){return new WP_Error('invalid_id',__('Invalid Category ID.','orunk-users'));}$d=$wpdb->delete($t,['id'=>$id],['%d']);if($d===false){error_log("Orunk DB Error deleting category $id: ".$wpdb->last_error);return new WP_Error('db_error',__('Could not delete category.','orunk-users'));}return($d>0); }

    // --- OTP Token Management Functions (Unchanged) ---
    public function save_otp_token($user_id, $token_hash, $expiry_timestamp) { global $wpdb;$t=$wpdb->prefix.'orunk_otp_tokens';$user_id=absint($user_id);$expiry_timestamp=absint($expiry_timestamp);if(empty($user_id)||empty($token_hash)||empty($expiry_timestamp)){return new WP_Error('missing_data',__('User ID, token hash, and expiry required.','orunk-users'));}$this->delete_otp_token($user_id);$i=$wpdb->insert($t,['user_id'=>$user_id,'token_hash'=>$token_hash,'expiry_timestamp'=>$expiry_timestamp,'resend_count'=>0,'created_at'=>current_time('mysql',1)],['%d','%s','%d','%d','%s']);if($i===false){error_log("Orunk DB Error saving OTP for user $user_id: ".$wpdb->last_error);return new WP_Error('db_error',__('Could not save OTP token.','orunk-users'));}return true; }
    public function get_otp_token($user_id) { global $wpdb;$t=$wpdb->prefix.'orunk_otp_tokens';$user_id=absint($user_id);if(empty($user_id)){return null;}$now=current_time('timestamp');$sql=$wpdb->prepare("SELECT * FROM `$t` WHERE user_id = %d AND expiry_timestamp > %d ORDER BY created_at DESC LIMIT 1",$user_id,$now);return $wpdb->get_row($sql,ARRAY_A); }
    public function delete_otp_token($user_id) { global $wpdb;$t=$wpdb->prefix.'orunk_otp_tokens';$user_id=absint($user_id);if(empty($user_id)){return false;}$d=$wpdb->delete($t,['user_id'=>$user_id],['%d']);if($d===false){error_log("Orunk DB Error deleting OTP for user $user_id: ".$wpdb->last_error);return false;}return true; }
    public function increment_otp_resend_count($user_id) { global $wpdb;$t=$wpdb->prefix.'orunk_otp_tokens';$user_id=absint($user_id);if(empty($user_id)){return new WP_Error('invalid_user',__('Invalid User ID.','orunk-users'));}$token=$this->get_otp_token($user_id);if(!$token||!isset($token['id'])){return new WP_Error('no_valid_token',__('No valid OTP found.','orunk-users'));}$token_id=$token['id'];$u=$wpdb->query($wpdb->prepare("UPDATE `$t` SET resend_count = resend_count + 1 WHERE id = %d",$token_id));if($u===false){error_log("Orunk DB Error incrementing OTP count for token $token_id: ".$wpdb->last_error);return new WP_Error('db_error',__('Could not update OTP count.','orunk-users'));}return true; }
    public function get_otp_resend_count($user_id) { $token=$this->get_otp_token($user_id); return $token?(int)$token['resend_count']:0; }

} // End Class Custom_Orunk_DB