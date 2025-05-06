<?php
/**
 * Orunk Users - Admin AJAX Helper Functions
 *
 * Contains common helper functions used by admin AJAX handlers.
 *
 * @package OrunkUsers\Admin\AJAX
 * @version 1.0.0
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

?>