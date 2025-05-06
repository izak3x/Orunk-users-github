<?php
/**
 * Orunk Users User Actions Class
 *
 * Handles specific actions related to user accounts, such as registration.
 *
 * @package OrunkUsers\Includes
 * @version 1.0.0 (Phase 3 Refactor)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Orunk_User_Actions {

    /**
     * Constructor.
     * Can be used later if dependencies like DB access are needed for other user actions.
     */
    public function __construct() {
        // Currently no dependencies needed for register_user.
    }

    /**
     * Register a new WordPress user.
     * Performs validation checks before creating the user.
     *
     * @param string $username The desired username.
     * @param string $email    The user's email address.
     * @param string $password The user's chosen password.
     * @return int|WP_Error The new user's ID on success, or a WP_Error object on failure.
     */
    public function register_user($username, $email, $password) {

        // Validate inputs
        if (empty($username) || empty($email) || empty($password)) {
            return new WP_Error('invalid_input', __('Username, email, and password are required.', 'orunk-users'));
        }

        // Validate username format
        if (!validate_username($username)) {
             return new WP_Error('invalid_username', __('Invalid username.', 'orunk-users'));
        }

        // Check if username already exists
        if (username_exists($username)) {
            return new WP_Error('username_exists', __('Username already exists. Please choose another.', 'orunk-users'));
        }

        // Validate email format
        if (!is_email($email)) {
            return new WP_Error('invalid_email', __('Invalid email address provided.', 'orunk-users'));
        }

        // Check if email already exists
        if (email_exists($email)) {
            return new WP_Error('email_exists', __('An account with this email address already exists.', 'orunk-users'));
        }

        // --- Attempt to create the user ---
        $user_id = wp_create_user($username, $password, $email);

        // Check for errors during user creation
        if (is_wp_error($user_id)) {
            // Log the specific error from wp_create_user
            error_log('Orunk User Actions (register_user) Error: Failed to create user. WP_Error: ' . $user_id->get_error_message());
            // Return a user-friendly error
            return new WP_Error('user_creation_failed', __('Could not register user. Please try again later or contact support.', 'orunk-users'), $user_id->get_error_data());
        }

        // Optional: Send the default WordPress new user notification
        // Consider if this is desired or if you have custom notifications.
        // wp_new_user_notification($user_id, null, 'user');

        // Log success
        error_log("Orunk User Actions (register_user): Successfully registered user '{$username}' with ID {$user_id}.");

        // Return the new user ID on success
        return $user_id;
    }

    // --- Add other user-related action methods here in the future ---
    // Example: public function update_user_profile(...) { ... }
    // Example: public function delete_user_account(...) { ... }

} // End Class Orunk_User_Actions