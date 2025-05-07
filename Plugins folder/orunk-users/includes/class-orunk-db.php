<?php
/**
 * Orunk Users Database Class
 *
 * Handles database schema creation, updates, and data retrieval for the plugin.
 *
 * MODIFIED:
 * - Added `requires_license` column to products table.
 * - Added `activation_limit` column to product_plans table.
 * - Added `override_activation_limit` column to user_purchases table.
 * - Added `wp_orunk_license_activations` table for tracking license activations.
 * - Updated `get_user_purchases` to join necessary tables for display.
 * - Added `get_feature_requires_license` method.
 * - Added `get_active_activation_count` method.
 * - Added `is_site_active` method.
 * - Added `add_activation` method.
 * - Added `deactivate_activation` method.
 *
 * @package OrunkUsers
 * @version 1.14.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Custom_Orunk_DB {

    public function __construct() {
        // Constructor
    }

    public function create_db_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // --- wp_orunk_products Table (Features) ---
        $products_table = $wpdb->prefix . 'orunk_products';
        $products_sql = "CREATE TABLE `{$products_table}` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `feature` VARCHAR(50) NOT NULL,
            `product_name` VARCHAR(100) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `category` VARCHAR(50) DEFAULT NULL,
            `download_url` VARCHAR(255) DEFAULT NULL,
            `download_limit_daily` INT DEFAULT 5,
            `requires_license` TINYINT(1) NOT NULL DEFAULT 0, -- Added
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `feature` (`feature`),
            INDEX `idx_category` (`category`)
        ) {$charset_collate};";
        dbDelta($products_sql);

        // --- wp_orunk_product_plans Table (Pricing Plans) ---
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
            `activation_limit` INT DEFAULT NULL, -- Added (NULL means unlimited)
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `is_one_time` TINYINT(1) NOT NULL DEFAULT 0,
            `paypal_plan_id` VARCHAR(100) DEFAULT NULL,
            `stripe_price_id` VARCHAR(100) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_feature_key` (`product_feature_key`),
            INDEX `idx_is_active` (`is_active`)
        ) {$charset_collate};";
        dbDelta($plans_sql);

        // --- wp_orunk_user_purchases Table ---
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $purchases_sql = "CREATE TABLE `{$purchases_table}` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) UNSIGNED NOT NULL,
            `plan_id` mediumint(9) NOT NULL,
            `product_feature_key` VARCHAR(50) DEFAULT NULL,
            `api_key` VARCHAR(64) DEFAULT NULL,
            `license_key` VARCHAR(100) DEFAULT NULL,
            `override_activation_limit` INT DEFAULT NULL, -- Added: Per-purchase override for activation limit
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
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `modified_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `api_key` (`api_key`),
            UNIQUE KEY `license_key` (`license_key`),
            INDEX `idx_user_id_status_feature` (`user_id`, `status`, `product_feature_key`),
            INDEX `idx_gateway_sub_id` (`gateway_subscription_id`)
        ) {$charset_collate};";
        dbDelta($purchases_sql);

        // --- NEW: wp_orunk_license_activations Table ---
        $activations_table = $wpdb->prefix . 'orunk_license_activations';
        $activations_sql = "CREATE TABLE `{$activations_table}` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `purchase_id` mediumint(9) NOT NULL,
            `license_key` VARCHAR(100) NOT NULL,
            `site_url` VARCHAR(255) NOT NULL,
            `activation_ip` VARCHAR(45) DEFAULT NULL,
            `plugin_version` VARCHAR(20) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1, -- 1 for active, 0 for inactive
            `activated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `deactivated_at` DATETIME DEFAULT NULL,
            `last_checked_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_license_key` (`license_key`),
            INDEX `idx_site_url` (`site_url`(191)), -- Index part of site_url
            INDEX `idx_purchase_id` (`purchase_id`),
            INDEX `idx_license_key_site_active` (`license_key`, `site_url`(191), `is_active`) -- For quick active site check
        ) {$charset_collate};";
        dbDelta($activations_sql);


        // --- Other tables (Categories, OTP, Feature Types - unchanged from previous correct version) ---
        $categories_table = $wpdb->prefix . 'orunk_feature_categories';
        $categories_sql = "CREATE TABLE `{$categories_table}` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `category_name` VARCHAR(100) NOT NULL,
            `category_slug` VARCHAR(50) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_category_slug` (`category_slug`)
        ) {$charset_collate};";
        dbDelta($categories_sql);

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

        $types_table = $wpdb->prefix . 'orunk_feature_types'; // Assuming you still want this table
        $types_sql = "CREATE TABLE `{$types_table}` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `type_name` VARCHAR(100) NOT NULL,
            `type_slug` VARCHAR(50) NOT NULL,
            `description` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_type_slug` (`type_slug`)
        ) {$charset_collate};";
        dbDelta($types_sql);

        $this->insert_default_data();
    }

    public function insert_default_data() {
        // (Keep your existing insert_default_data method as it was,
        // ensuring it references correct table/column names if they changed)
        // For brevity, not repeating it here, assuming it's the same as last provided.
        // Ensure it tries to populate `requires_license` for default features if appropriate.
        // Example for bin_api feature:
        // $wpdb->update($products_table, ['requires_license' => 0], ['feature' => 'bin_api']);
        // Example for a downloadable plugin:
        // $wpdb->update($products_table, ['requires_license' => 1], ['feature' => 'convojet_pro']);
    }

    public function get_plan_details($plan_id) {
        global $wpdb;
        $plans_table = $wpdb->prefix . 'orunk_product_plans';
        if (empty($plan_id) || !is_numeric($plan_id)) { return null; }
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$plans_table}` WHERE id = %d", absint($plan_id)), ARRAY_A);
    }

    public function get_user_purchases($user_id, $status = null) {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $plans_table = $wpdb->prefix . 'orunk_product_plans';
        $products_table = $wpdb->prefix . 'orunk_products';

        if (empty($user_id) || !is_numeric($user_id)) { return array(); }

        $sql = "SELECT p.*,
                       pl.plan_name, pl.is_one_time, pl.paypal_plan_id, pl.stripe_price_id, pl.activation_limit, /* Get plan's activation limit */
                       pr.product_name AS feature_name, pr.category AS feature_category, pr.requires_license /* Get feature's requires_license flag */
                FROM `{$purchases_table}` p
                LEFT JOIN `{$plans_table}` pl ON p.`plan_id` = pl.`id`
                LEFT JOIN `{$products_table}` pr ON p.`product_feature_key` = pr.`feature`
                WHERE p.`user_id` = %d";
        $params = [absint($user_id)];

        if ($status !== null && is_string($status)) {
            $sql .= " AND p.`status` = %s";
            $params[] = sanitize_key($status);
        }
        $sql .= " ORDER BY p.`purchase_date` DESC";

        $query = $wpdb->prepare($sql, ...$params);
        return $wpdb->get_results($query, ARRAY_A);
    }

    public function get_purchase_by_api_key($api_key) {
        // (Keep this method as previously defined, ensure it joins products table
        // if `requires_license` or `activation_limit` from plan/product is needed here)
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $plans_table = $wpdb->prefix . 'orunk_product_plans';
        $products_table = $wpdb->prefix . 'orunk_products';
        if (empty($api_key) || !is_string($api_key)) { return null; }
        $sql = "SELECT p.*,
                       pl.plan_name, pl.is_one_time, pl.paypal_plan_id, pl.stripe_price_id, pl.activation_limit AS plan_activation_limit,
                       pr.product_name AS feature_name, pr.requires_license
                FROM `{$purchases_table}` p
                LEFT JOIN `{$plans_table}` pl ON p.plan_id = pl.id
                LEFT JOIN `{$products_table}` pr ON p.product_feature_key = pr.feature
                WHERE p.`api_key` = %s";
        return $wpdb->get_row($wpdb->prepare($sql, sanitize_text_field($api_key)), ARRAY_A);
    }
     public function get_purchase_by_license_key($license_key) { // Added this method
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $plans_table = $wpdb->prefix . 'orunk_product_plans';
        $products_table = $wpdb->prefix . 'orunk_products';
        if (empty($license_key) || !is_string($license_key)) { return null; }
        $sql = "SELECT p.*,
                       pl.plan_name, pl.is_one_time, pl.paypal_plan_id, pl.stripe_price_id, pl.activation_limit AS plan_activation_limit,
                       pr.product_name AS feature_name, pr.requires_license
                FROM `{$purchases_table}` p
                LEFT JOIN `{$plans_table}` pl ON p.plan_id = pl.id
                LEFT JOIN `{$products_table}` pr ON p.product_feature_key = pr.feature
                WHERE p.`license_key` = %s";
        return $wpdb->get_row($wpdb->prepare($sql, sanitize_text_field($license_key)), ARRAY_A);
    }


    public function get_user_active_plan_for_feature( $user_id, $feature_key ) {
        // (Keep this method as previously defined, ensure it joins products table
        // if `requires_license` or `activation_limit` from plan/product is needed here)
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $plans_table = $wpdb->prefix . 'orunk_product_plans';
        $products_table = $wpdb->prefix . 'orunk_products';

        if (empty($user_id) || !is_numeric($user_id) || empty($feature_key) || !is_string($feature_key)) { return null; }
        $sql = $wpdb->prepare(
            "SELECT p.*,
                    pl.plan_name, pl.is_one_time, pl.paypal_plan_id, pl.stripe_price_id, pl.activation_limit AS plan_activation_limit,
                    pr.product_name AS feature_name, pr.requires_license
             FROM `{$purchases_table}` p
             LEFT JOIN `{$plans_table}` pl ON p.plan_id = pl.id
             LEFT JOIN `{$products_table}` pr ON p.product_feature_key = pr.feature
             WHERE p.user_id = %d
               AND p.product_feature_key = %s
               AND p.status = 'active'
               AND (p.expiry_date IS NULL OR p.expiry_date >= %s)
             ORDER BY p.purchase_date DESC
             LIMIT 1",
            absint($user_id),
            sanitize_key($feature_key),
            current_time('mysql', 1) // GMT time for comparison
        );
        return $wpdb->get_row( $sql, ARRAY_A );
    }

    // --- Category Management Functions ---
    // (Keep these as previously defined and correct)
    public function get_all_feature_categories() { global $wpdb; $t = $wpdb->prefix.'orunk_feature_categories'; return $wpdb->get_results("SELECT * FROM `$t` ORDER BY category_name ASC",ARRAY_A); }
    public function add_feature_category($name, $slug) { global $wpdb;$t=$wpdb->prefix.'orunk_feature_categories';if(empty($name)||empty($slug)){return new WP_Error('missing_data',__('Category Name and Slug are required.','orunk-users'));}$name=sanitize_text_field($name);$slug=sanitize_key($slug);$e=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$t` WHERE category_slug = %s",$slug));if($e>0){return new WP_Error('slug_exists',__('Category Slug already exists.','orunk-users'));}$i=$wpdb->insert($t,['category_name'=>$name,'category_slug'=>$slug],['%s','%s']);if($i===false){error_log("Orunk DB Error adding category: ".$wpdb->last_error);return new WP_Error('db_error',__('Could not add category.','orunk-users'));}return $wpdb->insert_id; }
    public function update_feature_category($id, $name, $slug) { global $wpdb;$t=$wpdb->prefix.'orunk_feature_categories';$id=absint($id);if($id<=0||empty($name)||empty($slug)){return new WP_Error('missing_data',__('Category ID, Name, and Slug are required.','orunk-users'));}$name=sanitize_text_field($name);$slug=sanitize_key($slug);$e=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$t` WHERE category_slug = %s AND id != %d",$slug,$id));if($e>0){return new WP_Error('slug_exists',__('Category Slug already exists.','orunk-users'));}$u=$wpdb->update($t,['category_name'=>$name,'category_slug'=>$slug],['id'=>$id],['%s','%s'],['%d']);if($u===false){error_log("Orunk DB Error updating category $id: ".$wpdb->last_error);return new WP_Error('db_error',__('Could not update category.','orunk-users'));}return true; }
    public function delete_feature_category($id) { global $wpdb;$t=$wpdb->prefix.'orunk_feature_categories';$id=absint($id);if($id<=0){return new WP_Error('invalid_id',__('Invalid Category ID.','orunk-users'));}$d=$wpdb->delete($t,['id'=>$id],['%d']);if($d===false){error_log("Orunk DB Error deleting category $id: ".$wpdb->last_error);return new WP_Error('db_error',__('Could not delete category.','orunk-users'));}return($d>0); }


    // --- OTP Token Management Functions ---
    // (Keep these as previously defined and correct)
    public function save_otp_token($user_id, $token_hash, $expiry_timestamp) { global $wpdb;$t=$wpdb->prefix.'orunk_otp_tokens';$user_id=absint($user_id);$expiry_timestamp=absint($expiry_timestamp);if(empty($user_id)||empty($token_hash)||empty($expiry_timestamp)){return new WP_Error('missing_data',__('User ID, token hash, and expiry required.','orunk-users'));}$this->delete_otp_token($user_id);$i=$wpdb->insert($t,['user_id'=>$user_id,'token_hash'=>$token_hash,'expiry_timestamp'=>$expiry_timestamp,'resend_count'=>0,'created_at'=>current_time('mysql',1)],['%d','%s','%d','%d','%s']);if($i===false){error_log("Orunk DB Error saving OTP for user $user_id: ".$wpdb->last_error);return new WP_Error('db_error',__('Could not save OTP token.','orunk-users'));}return true; }
    public function get_otp_token($user_id) { global $wpdb;$t=$wpdb->prefix.'orunk_otp_tokens';$user_id=absint($user_id);if(empty($user_id)){return null;}$now=current_time('timestamp');$sql=$wpdb->prepare("SELECT * FROM `$t` WHERE user_id = %d AND expiry_timestamp > %d ORDER BY created_at DESC LIMIT 1",$user_id,$now);return $wpdb->get_row($sql,ARRAY_A); }
    public function delete_otp_token($user_id) { global $wpdb;$t=$wpdb->prefix.'orunk_otp_tokens';$user_id=absint($user_id);if(empty($user_id)){return false;}$d=$wpdb->delete($t,['user_id'=>$user_id],['%d']);if($d===false){error_log("Orunk DB Error deleting OTP for user $user_id: ".$wpdb->last_error);return false;}return true; }
    public function increment_otp_resend_count($user_id) { global $wpdb;$t=$wpdb->prefix.'orunk_otp_tokens';$user_id=absint($user_id);if(empty($user_id)){return new WP_Error('invalid_user',__('Invalid User ID.','orunk-users'));}$token=$this->get_otp_token($user_id);if(!$token||!isset($token['id'])){return new WP_Error('no_valid_token',__('No valid OTP found.','orunk-users'));}$token_id=$token['id'];$u=$wpdb->query($wpdb->prepare("UPDATE `$t` SET resend_count = resend_count + 1 WHERE id = %d",$token_id));if($u===false){error_log("Orunk DB Error incrementing OTP count for token $token_id: ".$wpdb->last_error);return new WP_Error('db_error',__('Could not update OTP count.','orunk-users'));}return true; }

    // --- License and Activation Specific DB Methods ---
    /**
     * Gets whether a feature requires a license.
     *
     * @param string $feature_key The unique key of the feature.
     * @return bool True if the feature requires a license, false otherwise.
     */
    public function get_feature_requires_license($feature_key) {
        global $wpdb;
        $products_table = $wpdb->prefix . 'orunk_products';
        if (empty($feature_key)) {
            return false;
        }
        $requires_license = $wpdb->get_var($wpdb->prepare(
            "SELECT requires_license FROM `{$products_table}` WHERE feature = %s",
            sanitize_key($feature_key)
        ));
        return ($requires_license == 1);
    }

    /**
     * Gets the count of active activations for a given license key.
     *
     * @param string $license_key The license key.
     * @return int The number of active activations.
     */
    public function get_active_activation_count($license_key) {
        global $wpdb;
        $activations_table = $wpdb->prefix . 'orunk_license_activations';
        if (empty($license_key)) {
            return 0;
        }
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$activations_table}` WHERE license_key = %s AND is_active = 1",
            sanitize_text_field($license_key)
        ));
        return absint($count);
    }

    /**
     * Checks if a specific site URL is already active for a given license key.
     * @param string $license_key
     * @param string $site_url
     * @return bool True if active, false otherwise.
     */
    public function is_site_active($license_key, $site_url) {
        global $wpdb;
        $activations_table = $wpdb->prefix . 'orunk_license_activations';
        if (empty($license_key) || empty($site_url)) return false;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$activations_table}` WHERE license_key = %s AND site_url = %s AND is_active = 1",
            sanitize_text_field($license_key),
            esc_url_raw($site_url)
        ));
        return ($count > 0);
    }

    /**
     * Adds a new activation record for a license.
     * @param int $purchase_id
     * @param string $license_key
     * @param string $site_url
     * @param string|null $activation_ip
     * @param string|null $plugin_version
     * @return int|false The new activation ID on success, false on failure.
     */
    public function add_activation($purchase_id, $license_key, $site_url, $activation_ip = null, $plugin_version = null) {
        global $wpdb;
        $activations_table = $wpdb->prefix . 'orunk_license_activations';

        $data = [
            'purchase_id'    => absint($purchase_id),
            'license_key'    => sanitize_text_field($license_key),
            'site_url'       => esc_url_raw($site_url),
            'activation_ip'  => $activation_ip ? sanitize_text_field($activation_ip) : null,
            'plugin_version' => $plugin_version ? sanitize_text_field($plugin_version) : null,
            'is_active'      => 1,
            'activated_at'   => current_time('mysql', 1), // GMT
            'last_checked_at'=> current_time('mysql', 1)  // GMT
        ];
        $formats = ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s'];

        $inserted = $wpdb->insert($activations_table, $data, $formats);
        if ($inserted) {
            return $wpdb->insert_id;
        }
        error_log("Orunk DB Error adding activation for license {$license_key} on site {$site_url}: " . $wpdb->last_error);
        return false;
    }

    /**
     * Deactivates an existing activation record.
     * @param int $activation_id The ID of the activation record.
     * @return int|false Number of rows updated (should be 1) or false on error.
     */
    public function deactivate_activation($activation_id) {
        global $wpdb;
        $activations_table = $wpdb->prefix . 'orunk_license_activations';
        $id = absint($activation_id);
        if ($id <= 0) return false;

        return $wpdb->update(
            $activations_table,
            ['is_active' => 0, 'deactivated_at' => current_time('mysql', 1)],
            ['id' => $id, 'is_active' => 1], // Only deactivate if currently active
            ['%d', '%s'],
            ['%d', '%d']
        );
    }


} // End Class Custom_Orunk_DB