<?php
/**
 * Orunk Users API Key Manager Class
 *
 * Handles the generation and potentially validation/management of API keys.
 *
 * @package OrunkUsers\Includes
 * @version 1.0.0 (Phase 2 Refactor)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Orunk_Api_Key_Manager {

    /**
     * Constructor.
     * Currently empty, can be used for dependencies later if needed.
     */
    public function __construct() {
        // Initialization logic for API Key Manager, if any.
    }

    /**
     * Generates a unique API key, checking the database for collisions.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     * @param int $exclude_purchase_id Purchase ID to exclude from the uniqueness check (useful during activation).
     * @param int $max_attempts Max attempts to find a unique key.
     * @return string|WP_Error Unique API key string on success, WP_Error on failure.
     */
    public function generate_unique_api_key($exclude_purchase_id = 0, $max_attempts = 10) {
        global $wpdb;
        // Ensure the table name prefix is correct
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $attempt = 0;
        $key_exists = true;
        $new_api_key = null;

        do {
            // Generate a secure random key
            try {
                $new_api_key = bin2hex(random_bytes(16)); // 32 characters hex
            } catch (Exception $e) {
                 error_log('Orunk API Key Manager (generate_unique_api_key): random_bytes failed: ' . $e->getMessage() . '. Falling back to wp_generate_password.');
                 $new_api_key = wp_generate_password(32, false, false); // Fallback
            }

            // Check if key exists in DB, excluding the specified ID
            // Ensure the table exists before querying
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $purchases_table)) !== $purchases_table) {
                 error_log("Orunk API Key Manager Error (generate_unique_api_key): Purchases table '{$purchases_table}' not found.");
                 return new WP_Error('db_table_missing', __('Database table missing for API key check.', 'orunk-users'));
            }

            $key_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$purchases_table}` WHERE `api_key` = %s AND `id` != %d",
                $new_api_key,
                absint($exclude_purchase_id)
            ));
            $attempt++;
        } while ($key_exists && $attempt < $max_attempts);

        if ($key_exists) {
            // Failed to generate a unique key within attempts
            error_log("Orunk API Key Manager Error (generate_unique_api_key): Failed to generate UNIQUE API key after $max_attempts attempts (excluding purchase ID $exclude_purchase_id).");
            return new WP_Error('api_key_generation_failed_attempts', __('Could not generate a unique API key after multiple attempts.', 'orunk-users'));
        }

        error_log("Orunk API Key Manager (generate_unique_api_key): Generated unique key successfully (excluding ID {$exclude_purchase_id}).");
        return $new_api_key; // Return the unique key string
    }

    // Add other API key related methods here later if needed (e.g., validate_key_format, get_key_details)

} // End Class Orunk_Api_Key_Manager