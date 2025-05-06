    <?php
    /**
     * Template Name: Orunk Signup
     *
     * This template displays a custom user registration form and handles submission
     * using the Orunk Users plugin's registration function.
     *
     * @package Astra
     * @since 1.0.0
     */

    // --- Redirect if user is already logged in ---
    if (is_user_logged_in()) {
        // Redirect logged-in users to the dashboard or homepage
        wp_safe_redirect(home_url('/orunk-dashboard/')); // Redirect to dashboard
        exit;
    }

    // Ensure the Orunk Users core class is available
    if (!class_exists('Custom_Orunk_Core')) {
        get_header();
        ?>
        <div id="primary" <?php astra_primary_class(); ?>>
            <main id="main" class="site-main">
                 <div class="ast-container">
                     <div class="ast-row">
                         <div class="ast-col-lg-12 ast-col-md-12 ast-col-sm-12 ast-col-xs-12">
                            <div class="entry-content clear" itemprop="text">
                                <h1><?php the_title(); ?></h1>
                                <p class="orunk-error notice notice-error">
                                    <?php esc_html_e('Error: The required Orunk Users plugin component is not available. Registration cannot proceed.', 'orunk-users'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        <?php
        get_footer();
        return; // Stop further execution
    }

    $orunk_core = new Custom_Orunk_Core();
    $registration_error = null; // Variable to hold error messages

    // --- Handle Form Submission ---
    if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['orunk_signup_submit'])) {
        // Verify nonce for security
        if (!isset($_POST['orunk_signup_nonce']) || !wp_verify_nonce(sanitize_key($_POST['orunk_signup_nonce']), 'orunk_signup_action')) {
            $registration_error = new WP_Error('nonce_fail', __('Security check failed. Please try again.', 'orunk-users'));
        } else {
            // Sanitize and retrieve form data
            $username = isset($_POST['orunk_username']) ? sanitize_user($_POST['orunk_username'], true) : ''; // Strict sanitization
            $email    = isset($_POST['orunk_email']) ? sanitize_email($_POST['orunk_email']) : '';
            $password = isset($_POST['orunk_password']) ? $_POST['orunk_password'] : ''; // Password itself isn't typically sanitized here, WP handles hashing
            $password_confirm = isset($_POST['orunk_password_confirm']) ? $_POST['orunk_password_confirm'] : '';

            // Basic validation (more specific validation is inside register_user)
            if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
                 $registration_error = new WP_Error('required_fields', __('All fields are required.', 'orunk-users'));
            } elseif ($password !== $password_confirm) {
                 $registration_error = new WP_Error('password_mismatch', __('Passwords do not match.', 'orunk-users'));
            } else {
                // Attempt registration using the plugin's core method
                $result = $orunk_core->register_user($username, $email, $password);

                if (is_wp_error($result)) {
                    // Registration failed, store the WP_Error object
                    $registration_error = $result;
                } else {
                    // Registration successful!
                    // Log the user in automatically
                    $user_id = $result;
                    wp_set_current_user($user_id, $username);
                    wp_set_auth_cookie($user_id);
                    do_action('wp_login', $username, get_user_by('id', $user_id));

                    // Redirect to the dashboard or catalog page
                    wp_safe_redirect(home_url('/orunk-dashboard/')); // Redirect to dashboard after successful signup
                    exit;
                }
            }
        }
    } // End form submission handling

    get_header(); // Include theme header
    ?>

    <div id="primary" <?php astra_primary_class(); ?>>
        <main id="main" class="site-main">
             <div class="ast-container"> <?php // Astra theme container ?>
                <div class="ast-row">
                    <div class="ast-col-lg-6 ast-col-md-8 ast-col-sm-12 ast-col-xs-12 ast-col-centered"> <?php // Center column ?>

                        <header class="entry-header ast-header-without-markup">
                            <h1 class="entry-title" itemprop="headline"><?php the_title(); // Page title e.g., "Sign Up" ?></h1>
                        </header><div class="entry-content clear orunk-signup-form-wrap" itemprop="text">

                            <?php // Display registration errors, if any ?>
                            <?php if (is_wp_error($registration_error)) : ?>
                                <div class="orunk-error notice notice-error inline" style="margin-bottom: 15px;">
                                    <p><strong><?php esc_html_e('Registration Failed:', 'orunk-users'); ?></strong></p>
                                    <ul>
                                        <?php foreach ($registration_error->get_error_messages() as $error) : ?>
                                            <li><?php echo esc_html($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php // Registration Form ?>
                            <form id="orunk-signup-form" method="post" action="<?php echo esc_url(get_permalink()); // Submit to the same page ?>">
                                <?php wp_nonce_field('orunk_signup_action', 'orunk_signup_nonce'); // Security nonce ?>

                                <p class="orunk-form-row">
                                    <label for="orunk_username"><?php esc_html_e('Username', 'orunk-users'); ?> <span class="required">*</span></label>
                                    <input type="text" name="orunk_username" id="orunk_username" class="input-text" required aria-required="true" value="<?php echo isset($_POST['orunk_username']) ? esc_attr($_POST['orunk_username']) : ''; ?>">
                                </p>
                                <p class="orunk-form-row">
                                    <label for="orunk_email"><?php esc_html_e('Email Address', 'orunk-users'); ?> <span class="required">*</span></label>
                                    <input type="email" name="orunk_email" id="orunk_email" class="input-text" required aria-required="true" value="<?php echo isset($_POST['orunk_email']) ? esc_attr($_POST['orunk_email']) : ''; ?>">
                                </p>
                                <p class="orunk-form-row">
                                    <label for="orunk_password"><?php esc_html_e('Password', 'orunk-users'); ?> <span class="required">*</span></label>
                                    <input type="password" name="orunk_password" id="orunk_password" class="input-text" required aria-required="true">
                                    <?php // Add password strength meter here if desired ?>
                                </p>
                                 <p class="orunk-form-row">
                                    <label for="orunk_password_confirm"><?php esc_html_e('Confirm Password', 'orunk-users'); ?> <span class="required">*</span></label>
                                    <input type="password" name="orunk_password_confirm" id="orunk_password_confirm" class="input-text" required aria-required="true">
                                </p>

                                <?php // Optional: Add terms & conditions checkbox, CAPTCHA, etc. ?>

                                <p class="orunk-form-submit">
                                    <button type="submit" name="orunk_signup_submit" class="button orunk-button-primary"><?php esc_html_e('Register', 'orunk-users'); ?></button>
                                </p>

                                <p class="orunk-login-link">
                                    <?php esc_html_e('Already have an account?', 'orunk-users'); ?>
                                    <a href="<?php echo esc_url(home_url('/orunk-login/')); // Link to your login page ?>"><?php esc_html_e('Log in here', 'orunk-users'); ?></a>
                                </p>
                            </form>

                        </div></div> <?php // Astra theme column ?>
                </div> <?php // Astra theme row ?>
            </div> <?php // Astra theme container ?>
        </main></div><?php
    get_footer(); // Include theme footer
    ?>
    