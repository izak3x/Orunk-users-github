<?php
/**
 * Orunk Users - User Profile AJAX Handlers
 *
 * Handles AJAX requests related to user profile and billing address management
 * from the frontend dashboard.
 *
 * @package OrunkUsers\Frontend\AJAX
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Helper to check nonce and login status for logged-in user AJAX actions.
 * Sends JSON error and dies if checks fail.
 *
 * @param string $nonce_action The nonce action name.
 */
function orunk_user_check_ajax_permissions($nonce_action) {
    // Verify the nonce passed from the frontend JavaScript
    if (!check_ajax_referer($nonce_action, 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed. Please refresh the page and try again.', 'orunk-users')], 403);
    }
    // Verify the user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('You must be logged in to perform this action.', 'orunk-users')], 401);
    }
    // If checks pass, execution continues
}

/**
 * AJAX Handler: Update User Profile information.
 * Handles 'wp_ajax_orunk_update_profile'.
 */
function handle_update_profile() {
    // Check permissions (just needs logged-in user, uses profile nonce)
    orunk_user_check_ajax_permissions('orunk_update_profile_nonce');

    $user_id = get_current_user_id();

    // Sanitize inputs
    $display_name     = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
    $email            = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $current_password = isset($_POST['current_password']) ? wp_unslash($_POST['current_password']) : ''; // Don't sanitize password itself
    $new_password     = isset($_POST['new_password']) ? wp_unslash($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? wp_unslash($_POST['confirm_password']) : '';
    $remove_picture   = isset($_POST['remove_profile_picture']) && $_POST['remove_profile_picture'] === '1';

    // --- Basic Validation ---
    if (empty($display_name)) {
        wp_send_json_error(['message' => __('Display name cannot be empty.', 'orunk-users')], 400);
    }
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(['message' => __('Please provide a valid email address.', 'orunk-users')], 400);
    }

    $current_user = wp_get_current_user();
    $email_changed = ($email !== $current_user->user_email);

    // Check if email is already in use by another user
    if ($email_changed && email_exists($email)) {
        wp_send_json_error(['message' => __('This email address is already registered by another user.', 'orunk-users')], 400);
    }

    // Prepare user data array for wp_update_user
    $user_data = [
        'ID'           => $user_id,
        'display_name' => $display_name,
        'user_email'   => $email,
    ];

    // --- Password Change Logic ---
    $password_change_attempted = !empty($new_password);
    $requires_current_password = $password_change_attempted || $email_changed;

    // Check current password if needed
    if ($requires_current_password) {
        if (empty($current_password)) {
            wp_send_json_error(['message' => __('Please enter your current password to change email or set a new password.', 'orunk-users')], 400);
        }
        // Verify current password
        if (!wp_check_password($current_password, $current_user->user_pass, $user_id)) {
            wp_send_json_error(['message' => __('Your current password is incorrect.', 'orunk-users')], 403);
        }
    }

    // Validate and add new password if attempting change
    if ($password_change_attempted) {
        if (empty($new_password)) {
            wp_send_json_error(['message' => __('Please enter a new password.', 'orunk-users')], 400);
        }
        if ($new_password !== $confirm_password) {
            wp_send_json_error(['message' => __('New passwords do not match.', 'orunk-users')], 400);
        }
        // Add password to user data (wp_update_user handles hashing)
        $user_data['user_pass'] = $new_password;
    }

    // --- Profile Picture Logic ---
    $new_avatar_url = null; // URL to send back to JS
    $avatar_meta_key = 'orunk_profile_picture_attachment_id';

    // Check if we need WP media functions
    if ($remove_picture || (!empty($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK)) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
    }

    // Handle picture removal
    if ($remove_picture) {
        $old_attachment_id = get_user_meta($user_id, $avatar_meta_key, true);
        if (!empty($old_attachment_id)) {
            wp_delete_attachment(intval($old_attachment_id), true); // Force delete file
            delete_user_meta($user_id, $avatar_meta_key);
        }
        $new_avatar_url = get_avatar_url($user_id); // Get default avatar URL
        error_log("Orunk Profile Update: Removed profile picture for user {$user_id}.");
    }
    // Handle picture upload
    elseif (!empty($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploaded_file = $_FILES['profile_picture'];

        // Validate file type and size
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($uploaded_file['type'], $allowed_mime_types)) {
            wp_send_json_error(['message' => __('Invalid file type. Please upload a JPG, PNG, or GIF.', 'orunk-users')], 400);
        }
        if ($uploaded_file['size'] > 2 * 1024 * 1024) { // 2MB limit
            wp_send_json_error(['message' => __('File size exceeds the 2MB limit.', 'orunk-users')], 400);
        }

        // Handle the upload
        $movefile = wp_handle_upload($uploaded_file, ['test_form' => false]);

        if ($movefile && !isset($movefile['error'])) {
            $filename = $movefile['file'];
            $filetype = wp_check_filetype(basename($filename), null);

            // Prepare attachment data
            $attachment = array(
                'guid'           => $movefile['url'],
                'post_mime_type' => $filetype['type'],
                'post_title'     => preg_replace('/\.[^.]+$/', '', basename($filename)),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );

            // Insert the attachment
            $attach_id = wp_insert_attachment($attachment, $filename);

            if (!is_wp_error($attach_id)) {
                // Generate attachment metadata
                $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
                wp_update_attachment_metadata($attach_id, $attach_data);

                // Delete old attachment if exists
                $old_attachment_id = get_user_meta($user_id, $avatar_meta_key, true);
                if (!empty($old_attachment_id) && $old_attachment_id != $attach_id) {
                    wp_delete_attachment(intval($old_attachment_id), true);
                }

                // Update user meta with new attachment ID
                update_user_meta($user_id, $avatar_meta_key, $attach_id);
                $new_avatar_url = wp_get_attachment_image_url($attach_id, 'thumbnail'); // Get URL of a suitable size
                error_log("Orunk Profile Update: Updated profile picture for user {$user_id} to attachment ID {$attach_id}.");
            } else {
                error_log("Orunk Profile Update Error: wp_insert_attachment failed for user {$user_id}: " . $attach_id->get_error_message());
                wp_send_json_error(['message' => __('Could not save uploaded picture as attachment.', 'orunk-users')], 500);
            }
        } else {
            // Upload failed
            error_log("Orunk Profile Update Error: wp_handle_upload failed for user {$user_id}: " . ($movefile['error'] ?? 'Unknown upload error.'));
            wp_send_json_error(['message' => __('Error uploading profile picture: ', 'orunk-users') . ($movefile['error'] ?? 'Please try again.')], 500);
        }
    } // End profile picture handling

    // --- Update User Data ---
    $result = wp_update_user($user_data);

    if (is_wp_error($result)) {
        // Failed to update user text data
        error_log("Orunk Profile Update Error: wp_update_user failed for user {$user_id}: " . $result->get_error_message());
        wp_send_json_error(['message' => __('Could not update profile details. Please try again.', 'orunk-users') . ' (' . $result->get_error_code() . ')'], 500);
    } else {
        // Success
        error_log("Orunk Profile Update: Successfully updated profile data for user {$user_id}.");
        wp_send_json_success([
            'message' => __('Profile updated successfully.', 'orunk-users'),
            'new_avatar_url' => $new_avatar_url // Send back new avatar URL if changed/removed
        ]);
    }
    // wp_die(); // implicit
 }

/**
 * AJAX Handler: Get User's Billing Address.
 * Handles 'wp_ajax_orunk_get_billing_address'.
 */
 function handle_get_billing_address() {
    // Check permissions (just needs logged-in user)
    orunk_user_check_ajax_permissions('orunk_billing_address_nonce');

    $user_id = get_current_user_id();
    $billing_keys = [
        'billing_first_name', 'billing_last_name', 'billing_company',
        'billing_address_1', 'billing_address_2', 'billing_city',
        'billing_state', 'billing_postcode', 'billing_country',
        'billing_email', 'billing_phone'
    ];
    $address_data = [];
    // Loop through keys and get user meta
    foreach ($billing_keys as $key) {
        $address_data[$key] = get_user_meta($user_id, $key, true); // Get saved value
    }
    // Send successful JSON response
    wp_send_json_success(['address' => $address_data]);
    // wp_die(); // implicit
 }

/**
 * AJAX Handler: Save User's Billing Address.
 * Handles 'wp_ajax_orunk_save_billing_address'.
 */
 function handle_save_billing_address() {
    // Check permissions
    orunk_user_check_ajax_permissions('orunk_billing_address_nonce');

    $user_id = get_current_user_id();
    $billing_keys = [
        'billing_first_name', 'billing_last_name', 'billing_company',
        'billing_address_1', 'billing_address_2', 'billing_city',
        'billing_state', 'billing_postcode', 'billing_country',
        'billing_email', 'billing_phone'
    ];
    $errors = [];

    // Loop through keys, sanitize, validate email, and update user meta
    foreach ($billing_keys as $key) {
        if (isset($_POST[$key])) {
            // Sanitize based on expected content
            if ($key === 'billing_email') {
                 $value = sanitize_email(wp_unslash($_POST[$key]));
                 // Validate email format only if not empty
                 if (!empty($value) && !is_email($value)) {
                      $errors[] = __('Invalid Billing Email provided.', 'orunk-users');
                 } else {
                      update_user_meta($user_id, $key, $value);
                 }
            } else {
                 // Sanitize other fields as text
                 $value = sanitize_text_field(wp_unslash($_POST[$key]));
                 update_user_meta($user_id, $key, $value);
            }
        }
    }

    // Send response based on validation result
    if (!empty($errors)) {
        wp_send_json_error(['message' => implode(' ', $errors)], 400);
    } else {
        wp_send_json_success(['message' => __('Billing address updated successfully.', 'orunk-users')]);
    }
    // wp_die(); // implicit
 }

?>