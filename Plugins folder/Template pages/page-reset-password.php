<?php
/**
 * Template Name: Orunk Reset Password (OTP Flow)
 *
 * Handles the final password reset form after successful OTP verification.
 *
 * @package YourThemeName
 * @version 2.0.0 (OTP Implementation)
 */

// --- START: OTP Verification Check ---
// This section ensures the user reached this page legitimately after OTP verification.
// We'll use a session variable or a temporary token passed via query args.
// For now, we'll simulate checking a query parameter 'otp_verified' and 'login'.
// The actual OTP handler (Phase 5/New File) will set this upon successful verification.

session_start(); // Start session if not already started

$is_otp_verified = false;
$user_login_for_reset = '';

// Option 1: Check for a temporary query token (less secure, short expiry needed)
// $temp_token = isset($_GET['temp_token']) ? sanitize_text_field($_GET['temp_token']) : '';
// $user_login_for_reset = isset($_GET['login']) ? sanitize_user(wp_unslash($_GET['login']), true) : '';
// if (!empty($temp_token) && !empty($user_login_for_reset) && verify_temporary_otp_token($user_login_for_reset, $temp_token)) { // verify_temporary_otp_token() needs to be implemented
//     $is_otp_verified = true;
// }

// Option 2: Check a session variable (more common)
if (isset($_SESSION['orunk_otp_verified_user_login']) && !empty($_SESSION['orunk_otp_verified_user_login'])) {
    // Optional: Add timestamp check to session variable if needed
    $user_login_for_reset = $_SESSION['orunk_otp_verified_user_login'];
    $is_otp_verified = true;
    // Clear the session variable immediately after verification to prevent reuse
    // unset($_SESSION['orunk_otp_verified_user_login']); // Or do this after successful password reset
}

// Redirect if OTP was not verified
if (!$is_otp_verified) {
    // Redirect to the login page or forgot password page with an error
    wp_safe_redirect(add_query_arg('orunk_message', 'otp_session_invalid', home_url('/orunk-login/'))); // Adjust target URL and message code as needed
    exit;
}
// --- END: OTP Verification Check ---


get_header(); // Use your theme's header

// --- Define Messages for Reset Page ---
$messages = [
    'nonce_fail'         => __('Security check failed. Please try submitting the form again.', 'orunk-users'),
    'password_blank'     => __('Password fields cannot be blank.', 'orunk-users'),
    'password_mismatch'  => __('The passwords you entered do not match.', 'orunk-users'),
    'password_weak'      => __('Your password is too weak. Please choose a stronger one.', 'orunk-users'), // Optional
    'reset_error'        => __('An error occurred while resetting your password. Please try again.', 'orunk-users'),
    'otp_session_invalid'=> __('Your password reset session is invalid or has expired. Please start the process again.', 'orunk-users'), // Message for redirect if session check fails
];
$message_code = isset($_GET['orunk_message']) ? sanitize_key($_GET['orunk_message']) : '';
$error_message = $messages[$message_code] ?? '';

?>
<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <div class="orunk-auth-container" style="max-width: 450px; margin: 4rem auto; padding: 2rem; background: white; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">

            <h1 class="entry-title" style="text-align: center; margin-bottom: 1.5rem; font-size: 1.5em; font-weight: 600;"><?php esc_html_e('Set Your New Password', 'orunk-users'); ?></h1>

            <?php // Display errors ?>
            <?php if ($error_message) : ?>
                <div class="orunk-error notice notice-error inline" style="margin-bottom: 15px; border-left-width: 4px; padding: 10px 15px; background-color: #fef2f2; border-color: #fecaca; color: #991b1b;">
                    <p><strong><?php esc_html_e('Error:', 'orunk-users'); ?></strong> <?php echo esc_html($error_message); ?></p>
                    <?php // If session was invalid, link back to start ?>
                    <?php if ($message_code === 'otp_session_invalid'): ?>
                         <p style="margin-top: 10px;"><a href="<?php echo esc_url(home_url('/forgot-password/')); // Adjust if needed ?>" style="color: #4f46e5;"><?php esc_html_e('Request a new OTP.', 'orunk-users'); ?></a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php // The form is always shown if the OTP verification check passed ?>
            <p style="text-align: center; color: #6b7280; font-size: 0.9em; margin-bottom: 1.5rem;">
                <?php printf(esc_html__('Enter a new password for %s below.', 'orunk-users'), '<strong>' . esc_html($user_login_for_reset) . '</strong>'); ?>
            </p>
            <form id="orunk-reset-password-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php // IMPORTANT: Change 'action' to your new password reset handler action name ?>
                <input type="hidden" name="action" value="orunk_reset_password_otp">
                <?php // IMPORTANT: Update nonce action name accordingly ?>
                <?php wp_nonce_field('orunk_reset_password_otp_action', 'orunk_reset_password_otp_nonce'); ?>
                <?php // Pass the user login identifier to the handler ?>
                <input type="hidden" name="rp_login" value="<?php echo esc_attr($user_login_for_reset); ?>">
                <?php // REMOVED: Input for rp_key (no longer used) ?>

                <p class="orunk-form-row" style="margin-bottom: 1rem;">
                    <label for="orunk_pass1" style="display: block; margin-bottom: 0.25rem; font-size: 0.8rem; font-weight: 500; color: #4b5563;"><?php esc_html_e('New Password', 'orunk-users'); ?></label>
                    <input type="password" name="pass1" id="orunk_pass1" class="input-text" required aria-required="true" autocomplete="new-password" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #d1d5db; border-radius: 6px;">
                </p>
                 <p class="orunk-form-row" style="margin-bottom: 1rem;">
                    <label for="orunk_pass2" style="display: block; margin-bottom: 0.25rem; font-size: 0.8rem; font-weight: 500; color: #4b5563;"><?php esc_html_e('Confirm New Password', 'orunk-users'); ?></label>
                    <input type="password" name="pass2" id="orunk_pass2" class="input-text" required aria-required="true" autocomplete="new-password" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #d1d5db; border-radius: 6px;">
                </p>

                <?php // Add password strength meter here if desired (WordPress built-in or custom) ?>
                <p class="description indicator-hint" style="font-size: 0.75rem; color: #6b7280; margin-bottom: 1.5rem;"><?php echo wp_get_password_hint(); ?></p>

                <p class="orunk-form-submit">
                    <button type="submit" name="orunk_reset_submit" class="button orunk-button-primary" style="width: 100%; padding: 0.7rem 1rem; background: #4f46e5; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;"><?php esc_html_e('Set New Password', 'orunk-users'); ?></button>
                </p>
            </form>

        </div>
    </main>
</div>
<?php get_footer(); // Use your theme's footer ?>