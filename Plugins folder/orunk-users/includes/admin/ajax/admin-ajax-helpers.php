<?php
/**
 * Orunk Users - Admin AJAX Helper Functions
 *
 * Contains common helper functions used by admin AJAX handlers.
 * Also includes common utility functions accessible by other AJAX handlers.
 *
 * @package OrunkUsers\Admin\AJAX
 * @version 1.0.2 // Added orunk_update_product_metric and explicit autoload handling
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Helper to check nonce and permissions for logged-in admin AJAX actions.
 * Sends JSON error and dies if checks fail.
 *
 * IMPORTANT: This function should only be defined ONCE.
 *
 * @param string $nonce_action The nonce action name.
 * @param string $capability   The capability required (default: 'manage_options').
 */
if (!function_exists('orunk_admin_check_ajax_permissions')) {
    function orunk_admin_check_ajax_permissions($nonce_action, $capability = 'manage_options') {
        // Verify the nonce passed from the frontend JavaScript
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page and try again.', 'orunk-users')], 403);
        }
        // Verify the current user has the required capability
        if (!current_user_can($capability)) {
             wp_send_json_error(['message' => __('You do not have sufficient permissions to perform this action.', 'orunk-users')], 403);
        }
        // If checks pass, execution continues
    }
}

/**
 * Helper function to update a specific product metric stored in WordPress options.
 *
 * @param int    $product_id The ID of the product (from wp_orunk_products table).
 * @param string $metric_key The key of the metric to update (e.g., 'sales_count', 'downloads_count').
 * @param int    $increment_by The value to increment by (default 1).
 * @param bool   $set_autoload_no Whether to try and set autoload to 'no' for this option.
 * @return bool True on success or if value was unchanged but autoload status was correct/updated, false on failure.
 */
if (!function_exists('orunk_update_product_metric')) {
    function orunk_update_product_metric($product_id, $metric_key, $increment_by = 1, $set_autoload_no = true) {
        if (empty($product_id) || !is_numeric($product_id) || empty($metric_key)) {
            error_log("Orunk Update Metric: Invalid parameters. Product ID: {$product_id}, Metric Key: {$metric_key}");
            return false;
        }
        $product_id = absint($product_id);
        $metrics_option_name = 'orunk_product_metrics_' . $product_id;

        // Get current metrics, provide defaults if not set
        $static_metrics = get_option($metrics_option_name, [
            'rating'          => 0,
            'reviews_count'   => 0,
            'sales_count'     => 0,
            'downloads_count' => 0
        ]);

        // Ensure the metric key exists, initialize if not
        if (!isset($static_metrics[$metric_key])) {
            $static_metrics[$metric_key] = 0;
        }

        $old_value = intval($static_metrics[$metric_key]);
        $static_metrics[$metric_key] = $old_value + intval($increment_by);
        if ($static_metrics[$metric_key] < 0) { // Prevent negative counts
            $static_metrics[$metric_key] = 0;
        }

        // Explicitly set autoload to 'no' if requested
        $autoload_status_for_function = $set_autoload_no ? 'no' : null; // `null` means use WP default for add_option, or keep existing for update_option

        // `update_option` will add the option if it doesn't exist.
        // It also updates the autoload flag if the new autoload value is different from the existing one.
        $updated = update_option($metrics_option_name, $static_metrics, $autoload_status_for_function);

        if ($updated) {
            error_log("Orunk Update Metric: Successfully updated '{$metric_key}' for product ID {$product_id}. New value: {$static_metrics[$metric_key]}. Autoload: " . ($autoload_status_for_function ?? 'default/kept'));
            return true;
        } else {
            // If $updated is false, it means either the value was the same AND autoload status was already correct, or an error occurred.
            $current_value_in_db_array = get_option($metrics_option_name); // Re-fetch to compare
            if ($current_value_in_db_array === $static_metrics) {
                 error_log("Orunk Update Metric: Value for '{$metric_key}' for product ID {$product_id} was already {$static_metrics[$metric_key]}. No value change by update_option(). Autoload attempted: " . ($autoload_status_for_function ?? 'default/kept'));
                // If $set_autoload_no is true, ensure autoload is indeed 'no'.
                if ($set_autoload_no) {
                    global $wpdb;
                    $current_autoload_db = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $metrics_option_name ) );
                    if ($current_autoload_db === 'yes') {
                        $force_autoload_update = $wpdb->update( $wpdb->options, array( 'autoload' => 'no' ), array( 'option_name' => $metrics_option_name ) );
                        if($force_autoload_update !== false){
                             error_log("Orunk Update Metric: Explicitly set autoload to 'no' for option {$metrics_option_name} as value was unchanged but autoload was 'yes'.");
                             return true; // Consider this a success as autoload status was corrected
                        } else {
                             error_log("Orunk Update Metric Warning: Failed to explicitly update autoload status for {$metrics_option_name} when value was unchanged.");
                        }
                    } else {
                        return true; // Value was same, autoload was already 'no' or not managed.
                    }
                } else {
                     return true; // Value was the same, and we weren't trying to manage autoload.
                }
            }
            // If we reach here, it implies a potential DB error, though update_option usually returns false for same value.
            error_log("Orunk Update Metric: Failed to update '{$metric_key}' for product ID {$product_id} using update_option for unknown reason (values might be different or DB issue). Autoload attempted: " . ($autoload_status_for_function ?? 'default/kept'));
            return false;
        }
    }
}
?>