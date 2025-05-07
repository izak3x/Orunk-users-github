<?php
/**
 * Orunk Users - Statistics Tracking Hooks
 *
 * Handles hooks for automatically updating product statistics like sales count.
 *
 * @package OrunkUsers\Includes
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Increment the sales count for a product when its purchase is activated.
 * This function relies on the helper 'orunk_update_product_metric' being available.
 *
 * @param int    $purchase_id The ID of the activated purchase record.
 * @param int    $user_id     The ID of the user.
 * @param array  $details     Details of the activated purchase (should include 'product_feature_key').
 */
function orunk_increment_product_sales_count_on_activation($purchase_id, $user_id, $details) {
    global $wpdb;
    $products_table = $wpdb->prefix . 'orunk_products'; // Make sure to use the correct table name

    $product_feature_key = $details['product_feature_key'] ?? null;

    if (empty($product_feature_key)) {
        error_log("Orunk Sales Count Hook: Missing product_feature_key for purchase ID {$purchase_id}. Cannot increment sales count.");
        return;
    }

    // Get the actual product ID from the wp_orunk_products table using the feature key
    $product_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `{$products_table}` WHERE feature = %s",
        $product_feature_key
    ));

    if (empty($product_id)) {
        error_log("Orunk Sales Count Hook: Product ID not found for feature key '{$product_feature_key}' (Purchase ID {$purchase_id}). Cannot increment sales count.");
        return;
    }

    // Ensure the helper function is available before calling
    if (function_exists('orunk_update_product_metric')) {
        // Increment 'sales_count' and ensure the option's autoload is set to 'no'
        orunk_update_product_metric($product_id, 'sales_count', 1, true);
    } else {
        error_log("Orunk Sales Count Hook Error: Helper function orunk_update_product_metric() not found for product ID {$product_id}.");
    }
}
// Hook this function to the action that fires when a purchase is successfully activated
add_action('orunk_purchase_activated', 'orunk_increment_product_sales_count_on_activation', 10, 3);

?>