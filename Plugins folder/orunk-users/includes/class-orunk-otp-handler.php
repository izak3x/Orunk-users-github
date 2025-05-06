<?php
/**
 * Orunk Users OTP Handler Class
 *
 * Handles the logic for OTP-based password resets.
 * Includes both standard admin-post handlers and AJAX handlers for single-page flow.
 *
 * @package OrunkUsers\Includes
 * @version 1.2.3 // Fixed user lookup in AJAX verify
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure DB class is available before class definition if not autoloaded reliably
if (!class_exists('Custom_Orunk_DB')) {
    // Define ORUNK_USERS_PLUGIN_DIR if it's not already set (e.g., if accessed directly in some edge case)
    if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
        // Attempt to determine plugin directory path relative to this file
        // This assumes the standard plugin structure: plugin-folder/includes/class-file.php
        $plugin_dir_path = plugin_dir_path(dirname(__FILE__, 2)); // Go up two directories from includes/
        if (is_dir($plugin_dir_path)) {
             define('ORUNK_USERS_PLUGIN_DIR', $plugin_dir_path);
        } else {
            // Fallback or log error if path cannot be determined
            error_log('CRITICAL Orunk OTP Handler Error: Could not determine ORUNK_USERS_PLUGIN_DIR.');
            return; // Stop if we can't define the directory
        }
    }
    $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
    if (file_exists($db_path)) {
        require_once $db_path;
    } else {
        // Log a critical error if the DB class cannot be loaded
        error_log('CRITICAL Orunk OTP Handler Error: Cannot find DB class file at ' . $db_path);
        // Prevent proceeding without the DB class
        return;
    }
}

class Orunk_OTP_Handler {

    /** @var Custom_Orunk_DB Database handler instance */
    private $db;

    /** @var int Length of the OTP */
    private const OTP_LENGTH = 6;

    /** @var int Validity duration of the OTP in minutes */
    private const OTP_VALIDITY_MINUTES = 10; // e.g., 10 minutes

    /** @var int Maximum number of OTP resend attempts allowed */
    private const MAX_RESEND_ATTEMPTS = 3;

    /**
     * Constructor. Initializes DB handler and hooks.
     */
    public function __construct() {
        // Ensure DB class is available
        if (!class_exists('Custom_Orunk_DB')) {
            error_log('Orunk OTP Handler Error: Custom_Orunk_DB class still not available after include attempt.');
            // Optionally add an admin notice or disable functionality
            return;
        }
        $this->db = new Custom_Orunk_DB();

        // --- Register hooks for NON-AJAX form submissions ---
        add_action('admin_post_nopriv_orunk_request_otp', [$this, 'handle_otp_request']);
        add_action('admin_post_orunk_request_otp', [$this, 'handle_otp_request']);
        add_action('admin_post_nopriv_orunk_verify_otp', [$this, 'handle_otp_verification']);
        add_action('admin_post_orunk_verify_otp', [$this, 'handle_otp_verification']);
        add_action('admin_post_nopriv_orunk_resend_otp', [$this, 'handle_otp_resend']);
        add_action('admin_post_orunk_resend_otp', [$this, 'handle_otp_resend']);
        add_action('admin_post_nopriv_orunk_reset_password_otp', [$this, 'handle_password_reset_after_otp']);
        add_action('admin_post_orunk_reset_password_otp', [$this, 'handle_password_reset_after_otp']);

        // --- Register AJAX hooks ---
        // Step 1: Request OTP
        add_action('wp_ajax_nopriv_orunk_ajax_request_otp', [$this, 'handle_ajax_otp_request']);
        add_action('wp_ajax_orunk_ajax_request_otp', [$this, 'handle_ajax_otp_request']);
        // Step 2: Verify OTP
        add_action('wp_ajax_nopriv_orunk_ajax_otp_verify', [$this, 'handle_ajax_otp_verify']);
        add_action('wp_ajax_orunk_ajax_otp_verify', [$this, 'handle_ajax_otp_verify']);
        // Step 3: Reset Password
        add_action('wp_ajax_nopriv_orunk_ajax_reset_password_otp', [$this, 'handle_ajax_password_reset_after_otp']);
        add_action('wp_ajax_orunk_ajax_reset_password_otp', [$this, 'handle_ajax_password_reset_after_otp']);

        // Hook to start session (might not be strictly needed for pure AJAX flow without session reliance)
        add_action('init', [$this, 'start_session'], 1);

        // Add hook for wp_mail failure details
        add_action('wp_mail_failed', [$this, 'log_wp_mail_failure'], 10, 1);
    }

    /**
     * Logs detailed error information when wp_mail fails.
     *
     * @param WP_Error $wp_error The WP_Error object passed on failure.
     */
    public function log_wp_mail_failure($wp_error) {
        if ($wp_error instanceof WP_Error) {
            $error_data = $wp_error->get_error_data();
            $log_message = "Orunk OTP: wp_mail failed. Error Code: " . $wp_error->get_error_code() .
                           ". Error Message: " . $wp_error->get_error_message() .
                           ". Error Data: " . print_r($error_data, true);
            error_log($log_message);
        } else {
            error_log("Orunk OTP: wp_mail_failed action triggered, but no WP_Error object received.");
        }
    }

    /**
     * Start PHP session if not already started.
     */
    public function start_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }

    /**
     * Handles the NON-AJAX request for an OTP (original method).
     * Uses redirects.
     */
    public function handle_otp_request() {
        if (!isset($_POST['orunk_request_otp_nonce']) || !wp_verify_nonce(sanitize_key($_POST['orunk_request_otp_nonce']), 'orunk_request_otp_action')) { $this->_redirect_back('forgot', 'nonce_fail'); }
        if (empty($_POST['user_login'])) { $this->_redirect_back('forgot', 'email_required'); }
        $username_or_email = sanitize_text_field(wp_unslash($_POST['user_login']));
        $user_data = get_user_by('email', $username_or_email); if (!$user_data) { $user_data = get_user_by('login', $username_or_email); }
        if (!$user_data) { $this->_redirect_back('forgot', 'otp_sent', ['login' => $username_or_email]); exit; } // Generic success message for non-existing users
        $user_id = $user_data->ID; $user_email = $user_data->user_email; $user_login = $user_data->user_login;
        $existing_token = $this->db->get_otp_token($user_id); $resend_count = $existing_token ? (int) $existing_token['resend_count'] : 0;
        if ($resend_count >= self::MAX_RESEND_ATTEMPTS) { error_log("OTP Request Limit Reached for User ID: $user_id"); $this->_redirect_back('forgot', 'rate_limit', ['login' => $user_login]); }
        $otp = $this->_generate_otp(); $otp_hash = wp_hash_password($otp); $expiry_timestamp = current_time('timestamp') + (self::OTP_VALIDITY_MINUTES * MINUTE_IN_SECONDS);
        $saved = $this->db->save_otp_token($user_id, $otp_hash, $expiry_timestamp); if (is_wp_error($saved)) { error_log("OTP DB Save Error for User ID: $user_id - " . $saved->get_error_message()); $this->_redirect_back('forgot', 'otp_db_error', ['login' => $user_login]); }
        $email_sent = $this->_send_otp_email($user_email, $otp, $user_login); if (!$email_sent) { error_log("OTP Email Send Error for User ID: $user_id to $user_email"); $this->_redirect_back('forgot', 'otp_send_error', ['login' => $user_login]); }
        $redirect_url = add_query_arg(['login' => rawurlencode($user_login), 'orunk_message' => 'otp_sent'], home_url('/otp-verify/')); wp_safe_redirect($redirect_url); exit;
    }

    /**
     * Handles the AJAX request for an OTP.
     */
    public function handle_ajax_otp_request() {
        if (!isset($_POST['orunk_request_otp_nonce']) || !wp_verify_nonce(sanitize_key($_POST['orunk_request_otp_nonce']), 'orunk_request_otp_action')) { wp_send_json_error(['message' => __('Security check failed.', 'orunk-users')], 403); }
        if (empty($_POST['user_login'])) { wp_send_json_error(['message' => __('Please enter your username or email.', 'orunk-users')], 400); }
        $username_or_email = sanitize_text_field(wp_unslash($_POST['user_login']));
        $user_data = get_user_by('email', $username_or_email); if (!$user_data) { $user_data = get_user_by('login', $username_or_email); }
        if (!$user_data) { wp_send_json_success(['message' => __('If an account exists, an OTP has been sent. Please check your inbox.', 'orunk-users')]); } // Generic success to prevent user enumeration

        $user_id = $user_data->ID; $user_email = $user_data->user_email; $user_login = $user_data->user_login;

        if (!is_email($user_email)) {
             error_log("Orunk OTP AJAX: Invalid email format found for user {$user_login} (ID: {$user_id}): {$user_email}");
             wp_send_json_error(['message' => __('Invalid user email format.', 'orunk-users')], 500);
        }

        $existing_token = $this->db->get_otp_token($user_id); $resend_count = $existing_token ? (int) $existing_token['resend_count'] : 0; if ($resend_count >= self::MAX_RESEND_ATTEMPTS) { error_log("Orunk OTP AJAX: Limit Reached for {$user_login}"); wp_send_json_error(['message' => __('OTP limit reached.', 'orunk-users')], 429); }
        $otp = $this->_generate_otp(); $otp_hash = wp_hash_password($otp); $expiry_timestamp = current_time('timestamp') + (self::OTP_VALIDITY_MINUTES * MINUTE_IN_SECONDS);
        $saved = $this->db->save_otp_token($user_id, $otp_hash, $expiry_timestamp); if (is_wp_error($saved)) { error_log("Orunk OTP AJAX: DB Save Error for {$user_login}: " . $saved->get_error_message()); wp_send_json_error(['message' => __('DB error saving token.', 'orunk-users')], 500); }

        error_log("Orunk OTP AJAX: Attempting to send OTP email to {$user_email} for user {$user_login} (ID: {$user_id})");
        $email_sent = $this->_send_otp_email($user_email, $otp, $user_login);

        if (!$email_sent) {
            error_log("Orunk OTP AJAX: wp_mail() returned false for {$user_email}. Check debug.log for wp_mail_failed hook details.");
            wp_send_json_error(['message' => __('Failed to send the OTP email. Please check server logs or contact support.', 'orunk-users')], 500);
        }

        error_log("Orunk OTP AJAX: Email reported as sent for {$user_login}. Preparing success JSON.");
        $response_data = [
            'message' => __('An OTP has been sent to your email address. Please check your inbox.', 'orunk-users'),
        ];
        wp_send_json_success($response_data);
        die();
    }

    /**
     * Handles the NON-AJAX OTP verification submission.
     */
    public function handle_otp_verification() {
        if (!isset($_POST['orunk_verify_otp_nonce']) || !wp_verify_nonce(sanitize_key($_POST['orunk_verify_otp_nonce']), 'orunk_verify_otp_action')) { $this->_redirect_back('otp_verify', 'nonce_fail', ['login' => $_POST['otp_login'] ?? '']); }
        $submitted_otp = isset($_POST['otp_code']) ? sanitize_text_field(wp_unslash($_POST['otp_code'])) : '';
        $user_login_or_email = isset($_POST['otp_login']) ? sanitize_text_field(wp_unslash($_POST['otp_login'])) : ''; // Accept email or login
        if (empty($submitted_otp) || empty($user_login_or_email)) { $this->_redirect_back('otp_verify', 'missing_data', ['login' => $user_login_or_email]); }

        $user_data = get_user_by('email', $user_login_or_email); if (!$user_data) { $user_data = get_user_by('login', $user_login_or_email); } // Try both email and login
        if (!$user_data) { $this->_redirect_back('forgot', 'user_not_found'); } $user_id = $user_data->ID; $user_login = $user_data->user_login; // Use the actual login from now on

        $token_record = $this->db->get_otp_token($user_id); if (!$token_record) { $this->_redirect_back('otp_verify', 'invalid_otp', ['login' => $user_login]); }
        if (!wp_check_password($submitted_otp, $token_record['token_hash'], $user_id)) { $this->_redirect_back('otp_verify', 'invalid_otp', ['login' => $user_login]); }
        $this->start_session(); $_SESSION['orunk_otp_verified_user_login'] = $user_login; $_SESSION['orunk_otp_verified_timestamp'] = current_time('timestamp');
        $redirect_url = home_url('/reset-password/'); wp_safe_redirect($redirect_url); exit;
    }

    /**
     * Handles the AJAX request for OTP verification.
     */
    public function handle_ajax_otp_verify() {
        error_log("Orunk OTP AJAX Verify: Received POST data: " . print_r($_POST, true)); // Log POST data

        if (!isset($_POST['orunk_verify_otp_nonce']) || !wp_verify_nonce(sanitize_key($_POST['orunk_verify_otp_nonce']), 'orunk_verify_otp_action')) { wp_send_json_error(['message' => __('Security check failed.', 'orunk-users')], 403); }
        $submitted_otp = isset($_POST['otp_code']) ? sanitize_text_field(wp_unslash($_POST['otp_code'])) : '';
        // This field contains the username OR email submitted in step 1
        $user_identifier = isset($_POST['otp_login']) ? sanitize_text_field(wp_unslash($_POST['otp_login'])) : '';
        error_log("Orunk OTP AJAX Verify: Extracted user identifier: '{$user_identifier}'"); // Log extracted identifier

        if (empty($submitted_otp) || empty($user_identifier)) { error_log("Orunk OTP AJAX Verify: Error - Missing OTP or identifier."); wp_send_json_error(['message' => __('Please enter the OTP.', 'orunk-users')], 400); }

        // --- START FIX: Try finding user by email first, then by login ---
        error_log("Orunk OTP AJAX Verify: Attempting get_user_by('email', '{$user_identifier}')");
        $user_data = get_user_by('email', $user_identifier);
        if (!$user_data) {
            error_log("Orunk OTP AJAX Verify: get_user_by email failed. Attempting get_user_by('login', '{$user_identifier}')");
            $user_data = get_user_by('login', $user_identifier);
        }
        // --- END FIX ---

        if (!$user_data) { error_log("Orunk OTP AJAX Verify: Error - User not found for identifier '{$user_identifier}'."); wp_send_json_error(['message' => __('User not found.', 'orunk-users')], 404); }

        $user_id = $user_data->ID;
        $user_login = $user_data->user_login; // Get the actual login for logging
        error_log("Orunk OTP AJAX Verify: Found User ID: {$user_id} for identifier '{$user_identifier}' (login: '{$user_login}')");
        $token_record = $this->db->get_otp_token($user_id);
        if (!$token_record) { error_log("Orunk OTP AJAX Verify: Error - No valid token found for User ID: {$user_id}"); wp_send_json_error(['message' => __('The OTP is invalid or has expired.', 'orunk-users')], 400); }
        if (!wp_check_password($submitted_otp, $token_record['token_hash'], $user_id)) { error_log("Orunk OTP AJAX Verify: Error - wp_check_password failed for User ID: {$user_id}"); wp_send_json_error(['message' => __('The OTP entered is incorrect.', 'orunk-users')], 400); }

        error_log("Orunk OTP AJAX Verify: OTP Verified successfully for User ID: {$user_id}");
        wp_send_json_success(['message' => __('OTP verified successfully.', 'orunk-users')]);
        die(); // Explicitly die
    }

    /**
     * Handles NON-AJAX OTP resend requests.
     */
    public function handle_otp_resend() {
        // Note: This function still assumes 'otp_login' is the actual username.
        // If the /otp-verify/ page needs modification to handle email/username, this function
        // might need a similar lookup fix as handle_ajax_otp_verify.
        if (!isset($_POST['orunk_resend_otp_nonce']) || !wp_verify_nonce(sanitize_key($_POST['orunk_resend_otp_nonce']), 'orunk_resend_otp_action')) { $this->_redirect_back('otp_verify', 'nonce_fail', ['login' => $_POST['otp_login'] ?? '']); }
        $user_login = isset($_POST['otp_login']) ? sanitize_user(wp_unslash($_POST['otp_login']), true) : ''; if (empty($user_login)) { $this->_redirect_back('forgot', 'user_not_found'); }
        $user_data = get_user_by('login', $user_login); if (!$user_data) { $this->_redirect_back('forgot', 'user_not_found'); } $user_id = $user_data->ID; $user_email = $user_data->user_email;
        $token_record = $this->db->get_otp_token($user_id); $resend_count = $token_record ? (int) $token_record['resend_count'] : 0; if ($resend_count >= self::MAX_RESEND_ATTEMPTS) { error_log("OTP Resend Limit Reached for User ID: $user_id"); $this->_redirect_back('otp_verify', 'max_resend', ['login' => $user_login]); }
        if ($token_record) { $incremented = $this->db->increment_otp_resend_count($user_id); if (is_wp_error($incremented)) { error_log("OTP Resend Count Increment Error for User ID: $user_id - " . $incremented->get_error_message()); $this->_redirect_back('otp_verify', 'otp_db_error', ['login' => $user_login]); } } else { $this->_redirect_back('otp_verify', 'invalid_otp', ['login' => $user_login]); }
        $otp = $this->_generate_otp(); $otp_hash = wp_hash_password($otp); $expiry_timestamp = current_time('timestamp') + (self::OTP_VALIDITY_MINUTES * MINUTE_IN_SECONDS);
        $saved = $this->db->save_otp_token($user_id, $otp_hash, $expiry_timestamp); if (is_wp_error($saved)) { error_log("OTP DB Save Error (Resend) for User ID: $user_id - " . $saved->get_error_message()); $this->_redirect_back('otp_verify', 'otp_db_error', ['login' => $user_login]); }
        $email_sent = $this->_send_otp_email($user_email, $otp, $user_login); if (!$email_sent) { error_log("OTP Email Resend Error for User ID: $user_id to $user_email"); $this->_redirect_back('otp_verify', 'otp_send_error', ['login' => $user_login]); }
        $this->_redirect_back('otp_verify', 'otp_resent', ['login' => $user_login]);
    }

    /**
     * Handles the NON-AJAX final password reset submission AFTER OTP verification.
     */
    public function handle_password_reset_after_otp() {
        if (!isset($_POST['orunk_reset_password_otp_nonce']) || !wp_verify_nonce(sanitize_key($_POST['orunk_reset_password_otp_nonce']), 'orunk_reset_password_otp_action')) { $this->_redirect_back('login', 'nonce_fail'); }
        $this->start_session(); if (!isset($_SESSION['orunk_otp_verified_user_login']) || empty($_SESSION['orunk_otp_verified_user_login'])) { $this->_redirect_back('login', 'otp_session_invalid'); } $user_login = $_SESSION['orunk_otp_verified_user_login'];
        $pass1 = isset($_POST['pass1']) ? wp_unslash($_POST['pass1']) : ''; $pass2 = isset($_POST['pass2']) ? wp_unslash($_POST['pass2']) : ''; $rp_login = isset($_POST['rp_login']) ? sanitize_user(wp_unslash($_POST['rp_login']), true) : '';
        if (empty($user_login) || $user_login !== $rp_login) { unset($_SESSION['orunk_otp_verified_user_login']); $this->_redirect_back('login', 'otp_session_invalid'); }
        $user = get_user_by('login', $user_login); if (!$user) { unset($_SESSION['orunk_otp_verified_user_login']); $this->_redirect_back('login', 'otp_session_invalid'); }
        $reset_page_url = home_url('/reset-password/'); if (empty($pass1) || empty($pass2)) { $this->_redirect_with_error($reset_page_url, 'password_blank'); } if ($pass1 !== $pass2) { $this->_redirect_with_error($reset_page_url, 'password_mismatch'); }
        $errors = new WP_Error(); apply_filters( 'wp_validate_password_policy', $errors, $pass1, $user ); if ( $errors->has_errors() ) { $error_code = $errors->get_error_code(); $message_code = ($error_code === 'password_weak') ? 'password_weak' : 'password_policy_fail'; error_log("Password Policy Error for user {$user->ID}: " . $errors->get_error_message()); $this->_redirect_with_error($reset_page_url, $message_code); }
        reset_password($user, $pass1); $this->db->delete_otp_token($user->ID); unset($_SESSION['orunk_otp_verified_user_login']); unset($_SESSION['orunk_otp_verified_timestamp']);
        $this->_redirect_back('login', 'password_reset');
    }

    /**
     * Handles the AJAX final password reset submission AFTER OTP verification.
     */
    public function handle_ajax_password_reset_after_otp() {
        if (!isset($_POST['orunk_reset_password_otp_nonce']) || !wp_verify_nonce(sanitize_key($_POST['orunk_reset_password_otp_nonce']), 'orunk_reset_password_otp_action')) { wp_send_json_error(['message' => __('Security check failed.', 'orunk-users')], 403); }
        $pass1 = isset($_POST['pass1']) ? wp_unslash($_POST['pass1']) : ''; $pass2 = isset($_POST['pass2']) ? wp_unslash($_POST['pass2']) : '';
        // This field contains the username OR email submitted in step 1, passed via JS
        $user_identifier = isset($_POST['rp_login']) ? sanitize_text_field(wp_unslash($_POST['rp_login'])) : '';

        if (empty($user_identifier)) { wp_send_json_error(['message' => __('User identifier is missing.', 'orunk-users')], 400); } if (empty($pass1) || empty($pass2)) { wp_send_json_error(['message' => __('Password fields cannot be blank.', 'orunk-users')], 400); } if ($pass1 !== $pass2) { wp_send_json_error(['message' => __('The passwords you entered do not match.', 'orunk-users')], 400); }

        // --- START FIX: Try finding user by email first, then by login ---
        $user = get_user_by('email', $user_identifier);
        if (!$user) {
            $user = get_user_by('login', $user_identifier);
        }
        // --- END FIX ---

        if (!$user) { wp_send_json_error(['message' => __('User not found.', 'orunk-users')], 404); }

        $errors = new WP_Error(); apply_filters( 'wp_validate_password_policy', $errors, $pass1, $user ); if ( $errors->has_errors() ) { $error_message = $errors->get_error_message(); error_log("Password Policy Error (AJAX) for user {$user->ID}: " . $error_message); wp_send_json_error(['message' => $error_message], 400); }
        reset_password($user, $pass1); $this->db->delete_otp_token($user->ID);
        wp_send_json_success(['message' => __('Password changed successfully! Redirecting to login...', 'orunk-users'), 'redirect_url' => home_url('/orunk-login/?orunk_message=password_reset')]);
        die(); // Explicitly die
    }

    // --- Helper Methods ---
    private function _generate_otp() {
        try { $otp = ''; for ($i = 0; $i < self::OTP_LENGTH; $i++) { $otp .= random_int(0, 9); } return $otp; }
        catch (Exception $e) { error_log('Orunk OTP: random_int failed: ' . $e->getMessage()); return (string) wp_rand(pow(10, self::OTP_LENGTH - 1), pow(10, self::OTP_LENGTH) - 1); }
    }

    private function _send_otp_email($email, $otp, $user_login) {
        error_log("Orunk OTP _send_otp_email: Params - To: {$email}, OTP: [hidden], Login: {$user_login}");
        $site_name = get_bloginfo('name');
        $subject = sprintf(__('[%s] Your Password Reset OTP', 'orunk-users'), $site_name);
        $message_lines = [ sprintf(__('Hi %s,', 'orunk-users'), $user_login), '', __('Someone requested a password reset for your account.', 'orunk-users'), __('Your One-Time Password (OTP) is:', 'orunk-users'), '', "<strong style='font-size: 1.2em; letter-spacing: 2px;'>" . $otp . "</strong>", '', sprintf(__('This OTP is valid for %d minutes.', 'orunk-users'), self::OTP_VALIDITY_MINUTES), __('If you did not request this, please ignore this email.', 'orunk-users'), '', __('Thanks,', 'orunk-users'), $site_name ];
        $message = implode("\r\n", $message_lines);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($email, $subject, nl2br($message), $headers);
        if ($sent) { error_log("Orunk OTP _send_otp_email: wp_mail() returned TRUE for {$email}"); }
        else { error_log("Orunk OTP _send_otp_email: wp_mail() returned FALSE for {$email}. Check wp_mail_failed hook log."); }
        return $sent;
    }

    private function _redirect_back($page_type, $message_code, $extra_args = []) {
        $url_slug = '/orunk-login/';
        switch ($page_type) { case 'forgot': $url_slug = '/forgot-password/'; break; case 'otp_verify': $url_slug = '/otp-verify/'; break; case 'reset': $url_slug = '/reset-password/'; break; }
        $args = array_merge(['orunk_message' => $message_code], $extra_args); if (isset($args['login'])) { $args['login'] = rawurlencode($args['login']); }
        $redirect_url = add_query_arg($args, home_url($url_slug)); wp_safe_redirect($redirect_url); exit;
    }

     private function _redirect_with_error($url, $message_code) {
        wp_safe_redirect(add_query_arg('orunk_message', $message_code, $url)); exit;
    }

} // End Class Orunk_OTP_Handler

?>