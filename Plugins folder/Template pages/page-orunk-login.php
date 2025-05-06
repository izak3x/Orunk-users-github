<?php
/**
 * Template Name: Orunk Login/Signup (AJAX Signup + Social Direct Links)
 *
 * Displays a custom login/signup page with AJAX registration and direct social login links.
 * Login uses the standard WordPress form. Social login requires plugin configuration.
 * Integrates the design from page-orunk-login-refrence.txt.
 *
 * @package OrunkThemeOrAstra
 * @since 1.0.5 (Updated Forgot Password Link)
 */

// --- Redirect if user is already logged in ---
if (is_user_logged_in()) {
    wp_safe_redirect(home_url('/orunk-dashboard/'));
    exit;
}

// --- Check for Orunk Core ---
$orunk_core_available = class_exists('Custom_Orunk_Core');

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Tailwind config from reference
        tailwind.config = { /* Tailwind config remains the same */
             theme: { extend: { colors: { 'together-blue': '#2563eb', 'together-indigo': '#4f46e5', 'together-purple': '#7c3aed', 'together-dark': '#0f172a', 'together-light': '#f8fafc', }, fontFamily: { 'inter': ['Inter', 'sans-serif'], }, animation: { 'fade-in': 'fadeIn 0.2s ease-out', } } } /* */ /* */ /* */ /* */ /* */ /* */ /* */ /* */
        }
    </script>
    <style>
        /* Styles from reference and previous merge */
        :root { --together-primary: #2563eb; --together-secondary: #7c3aed; } /* */
        body { font-family: 'Inter', sans-serif; color: #0f172a; background-color: #f8fafc; overflow-x: hidden; margin: 0; } /* */
        .auth-pattern { background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 16px 16px; color: rgba(37, 99, 235, 0.05); } /* */
        .auth-container { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(226, 232, 240, 0.5); box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.1); position: relative; overflow: hidden; } /* */
        .auth-container::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #2563eb 0%, #7c3aed 100%); } /* */
        .auth-input { display: block; width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; transition: all 0.2s ease; } /* */
        .auth-input:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2); outline: none; } /* */
        .orunk-merged-login-form .login-username label,
        .orunk-merged-login-form .login-password label,
        .orunk-merged-login-form .login-remember label { display: block; margin-bottom: 0.25rem; font-size: 0.75rem; font-weight: 500; color: #4b5563; }
        .orunk-merged-login-form input[type="text"],
        .orunk-merged-login-form input[type="password"],
        .orunk-merged-login-form input[type="email"] { @apply auth-input; }
        .orunk-merged-login-form .login-remember input[type="checkbox"] { height: 0.75rem; width: 0.75rem; color: #2563eb; border-color: #e2e8f0; border-radius: 0.25rem; margin-right: 0.375rem; } /* */
        .orunk-merged-login-form .login-remember { display: flex; align-items: center; }
        .orunk-merged-login-form .login-submit input[type="submit"],
        .orunk-merged-login-form .wp-submit input[type="submit"],
        .auth-primary-btn { width: 100%; padding: 0.5rem 1rem; font-size: 0.875rem; border-radius: 0.5rem; color: white; font-weight: 500; background: linear-gradient(to right, #2563eb, #7c3aed); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); transition: all 0.3s ease; cursor: pointer; border: none; } /* */
        .orunk-merged-login-form .login-submit input[type="submit"]:hover,
        .orunk-merged-login-form .wp-submit input[type="submit"]:hover,
        .auth-primary-btn:hover:not(:disabled) { background: linear-gradient(to right, #1e40af, #6d28d9); transform: scale(1.01); box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.2); } /* */
        .auth-primary-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .orunk-merged-login-form .login-remember, .orunk-merged-login-form .forgetmenot { margin-top: 0.75rem; margin-bottom: 0.75rem; }
        .orunk-merged-login-form .login-submit, .orunk-merged-login-form .wp-submit { margin-top: 1rem; }
        .orunk-merged-login-form p, .orunk-merged-signup-form .input-group { margin-bottom: 0.75rem; } /* */
        .orunk-merged-login-form p.forgetmenot label { font-size: 0.75rem; color: #374151; margin-left: 0; }
        /* Signup Form Specifics */
        .orunk-merged-signup-form .form-terms { display: flex; align-items: flex-start; font-size: 0.75rem; margin-bottom: 0.75rem; }
        .orunk-merged-signup-form .form-terms input[type="checkbox"] { height: 0.75rem; width: 0.75rem; color: #2563eb; border-color: #e2e8f0; border-radius: 0.25rem; margin-top: 0.125rem; flex-shrink: 0; } /* */
        .orunk-merged-signup-form .form-terms label { margin-left: 0.375rem; color: #374151; }
        .orunk-merged-signup-form .form-terms a { color: #0f172a; } /* */
        .orunk-merged-signup-form .form-terms a:hover { text-decoration: underline; } /* */
        /* Links */
        .orunk-login-links-section { margin-top: 1rem; text-align: center; font-size: 0.75rem; color: #374151; }
        .orunk-login-links-section .auth-toggle { color: #2563eb; cursor: pointer; font-weight: 500; } /* */
        .orunk-login-links-section .auth-toggle:hover { text-decoration: underline; } /* */
        /* Errors & Success */
        .orunk-form-error-message, .orunk-form-success-message { padding: 0.75rem 1rem; margin-bottom: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; border: 1px solid; }
        .orunk-form-error-message { background-color: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .orunk-form-error-message p, .orunk-form-error-message ul { margin: 0; padding: 0; list-style-position: inside; }
        .orunk-form-error-message ul li { margin-top: 0.25rem; }
        .orunk-form-success-message { background-color: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        /* Spinner */
        .auth-spinner { display: inline-block; width: 1em; height: 1em; border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; margin-left: 0.5rem; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }
        /* Animation */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } } /* */
        .auth-animate { animation: fadeIn 0.2s ease-out forwards; }
        .hidden { display: none; }
        /* Divider & Social Buttons */
        .auth-divider { display: flex; align-items: center; text-align: center; color: #6b7280; margin: 1rem 0; font-size: 0.75rem; } /* */
        .auth-divider::before, .auth-divider::after { content: ''; flex: 1; border-bottom: 1px solid #e5e7eb; } /* */
        .auth-divider::before { margin-right: 0.75rem; } /* */
        .auth-divider::after { margin-left: 0.75rem; } /* */
        /* Style for the <a> tag when used as a button */
        a.auth-social-btn { /* */
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none; /* Remove underline from link */
            color: #0f172a; /* Default text color */
        }
        a.auth-social-btn:hover { /* */
            border-color: #d1d5db;
            background: #f1f5f9;
            transform: scale(1.01);
            box-shadow: 0 5px 15px -5px rgba(0, 0, 0, 0.1);
            color: #0f172a; /* Ensure hover text color remains */
        }
        a.auth-social-btn i { /* */
             margin-right: 0.375rem;
             font-size: 0.875rem;
             line-height: 1; /* Prevent icon from affecting line height */
         }
         .hm-gradient-text { background: linear-gradient(90deg, var(--together-primary) 0%, var(--together-secondary) 100%); -webkit-background-clip: text; background-clip: text; color: transparent; } /* */

    </style>
    <?php wp_head(); ?>
</head>
<body <?php body_class('font-inter antialiased text-together-dark bg-together-light flex items-center justify-center min-h-screen p-4'); ?>>
    <?php // Background Pattern ?>
    <div class="fixed inset-0 auth-pattern z-0"></div>

    <?php // Main Auth Container ?>
    <div class="relative z-10 w-full max-w-md">
        <?php // Auth Card ?>
        <div class="auth-container rounded-xl overflow-hidden">
            <?php // Header ?>
            <div class="px-6 pt-6 pb-4">
                <h2 id="auth-title" class="text-xl font-bold text-together-dark">Welcome back</h2>
                <p id="auth-subtitle" class="text-xs text-slate-700 mt-0.5">Sign in to your <span class="hm-gradient-text">Orunk</span> account</p>
            </div>

            <?php // --- Login Form Area --- ?>
            <div id="login-form" class="px-6 pb-6 auth-animate">
                 <?php
                // Display login errors
                $login_error = isset($_GET['login']) ? sanitize_key($_GET['login']) : '';
                $orunk_message = isset($_GET['orunk_message']) ? sanitize_key($_GET['orunk_message']) : '';

                if ($login_error === 'failed') {
                    echo '<div class="orunk-form-error-message"><p><strong>' . esc_html__('Login Failed:', 'orunk-users') . '</strong> ' . esc_html__('Incorrect username or password.', 'orunk-users') . '</p></div>';
                } elseif ($login_error === 'empty') {
                    echo '<div class="orunk-form-error-message"><p><strong>' . esc_html__('Login Failed:', 'orunk-users') . '</strong> ' . esc_html__('Username and password are required.', 'orunk-users') . '</p></div>';
                } elseif ($orunk_message === 'password_reset') {
                    // Display success message after password reset
                    echo '<div class="orunk-form-success-message"><p>' . esc_html__('Your password has been reset successfully. You can now log in.', 'orunk-users') . '</p></div>';
                } elseif ($orunk_message === 'otp_session_invalid') {
                     // Display message if redirected from reset page due to invalid session
                     echo '<div class="orunk-form-error-message"><p>' . esc_html__('Your password reset session was invalid or expired. Please try resetting your password again.', 'orunk-users') . '</p></div>';
                }
                ?>

                <div class="orunk-merged-login-form">
                    <?php
                    // Display the standard WordPress login form
                    wp_login_form(array(
                        'redirect'       => home_url('/orunk-dashboard/'),
                        'label_username' => __('Email address', 'orunk-users'),
                        'label_password' => __('Password', 'orunk-users'),
                        'label_remember' => __('Remember me', 'orunk-users'),
                        'label_log_in'   => __('Sign In', 'orunk-users'),
                        'remember'       => true,
                    ));
                    ?>
                </div>

                <?php // Lost Password link - Ensure this points to the page using page-forgot-password.php.php ?>
                <div class="text-xs text-right mt-1 mb-3">
                    <?php // Assuming the slug for page-forgot-password.php.php is '/forgot-password/' ?>
                    <a href="<?php echo esc_url(home_url('/forgot-password/')); ?>" class="text-together-blue hover:underline"><?php esc_html_e('Forgot password?', 'orunk-users'); ?></a>
                </div>

                <?php // Divider and Social Buttons (Using Direct Links) ?>
                <div class="auth-divider my-4 text-xs text-slate-700">OR CONTINUE WITH</div>
                <div class="grid grid-cols-2 gap-2">
                    <?php
                        // Construct the URLs - Replace with your actual endpoint if different
                        $google_login_url = site_url('/wp-json/wslu-social-login/type/google'); // Example URL structure
                        $github_login_url = site_url('/wp-json/wslu-social-login/type/github'); // Example URL structure
                    ?>
                    <a rel="nofollow" href="<?php echo esc_url($google_login_url); ?>" class="auth-social-btn"> <?php // Use <a> tag with class ?>
                        <i class="fab fa-google text-red-500 mr-1.5 text-sm"></i>
                        <span>Google</span>
                    </a>
                    <a rel="nofollow" href="<?php echo esc_url($github_login_url); ?>" class="auth-social-btn"> <?php // Use <a> tag with class ?>
                        <i class="fab fa-github text-together-dark mr-1.5 text-sm"></i>
                        <span>GitHub</span>
                    </a>
                </div>
                 <p class="text-xs text-center text-gray-400 mt-2">(Ensure Social Login plugin is configured)</p>


                 <?php // Signup Link Section ?>
                <div class="orunk-login-links-section mt-4">
                    <?php esc_html_e('Don\'t have an account?', 'orunk-users'); ?>
                    <span id="show-signup" class="auth-toggle">
                        <?php esc_html_e('Sign up', 'orunk-users'); ?>
                    </span>
                </div>
            </div> <?php // end #login-form ?>

            <?php // --- Signup Form Area (Hidden Initially - Unchanged) --- ?>
            <div id="signup-form" class="px-6 pb-6 hidden auth-animate">
                <div id="signup-messages" class="mb-3"></div>

                <?php if ($orunk_core_available) : ?>
                    <form id="orunk-ajax-signup-form" class="orunk-merged-signup-form" method="post">
                        <?php wp_nonce_field('orunk_ajax_signup_action', 'orunk_ajax_signup_nonce'); ?>
                        <input type="hidden" name="action" value="orunk_ajax_signup">

                        <div class="space-y-3">
                            <div class="input-group">
                                <label for="signup-username" class="block text-xs font-medium text-gray-700 mb-1"><?php esc_html_e('Username', 'orunk-users'); ?> <span class="text-red-500">*</span></label>
                                <input type="text" name="signup_username" id="signup-username" class="auth-input" placeholder="Choose a username" required>
                            </div>
                            <div class="input-group">
                                <label for="signup-email" class="block text-xs font-medium text-gray-700 mb-1"><?php esc_html_e('Email Address', 'orunk-users'); ?> <span class="text-red-500">*</span></label>
                                <input type="email" name="signup_email" id="signup-email" class="auth-input" placeholder="Email address" required>
                            </div>
                            <div class="input-group">
                                <label for="signup-password" class="block text-xs font-medium text-gray-700 mb-1"><?php esc_html_e('Password', 'orunk-users'); ?> <span class="text-red-500">*</span></label>
                                <input type="password" name="signup_password" id="signup-password" class="auth-input" placeholder="Password" required>
                            </div>
                            <div class="input-group">
                                <label for="signup-confirm-password" class="block text-xs font-medium text-gray-700 mb-1"><?php esc_html_e('Confirm Password', 'orunk-users'); ?> <span class="text-red-500">*</span></label>
                                <input type="password" name="signup_confirm_password" id="signup-confirm-password" class="auth-input" placeholder="Confirm password" required>
                            </div>
                            <div class="form-terms">
                                <input type="checkbox" id="terms" name="terms" class="form-checkbox" required>
                                <label for="terms" class="ml-1.5">
                                    I agree to the <a href="#" class="text-together-dark hover:underline">Terms</a> and <a href="#" class="text-together-dark hover:underline">Privacy Policy</a>
                                </label>
                            </div>
                            <button type="submit" id="signup-submit-button" class="auth-primary-btn">
                                <span id="signup-button-text"><?php esc_html_e('Create Account', 'orunk-users'); ?></span>
                                <span id="signup-spinner" class="auth-spinner" style="display: none;"></span>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="orunk-form-error-message">
                        <p><?php esc_html_e('Signup functionality requires the Orunk Users plugin to be active.', 'orunk-users'); ?></p>
                    </div>
                <?php endif; ?>

                <?php // Divider and Social Buttons (Using Direct Links) ?>
                <div class="auth-divider my-4 text-xs text-slate-700">OR CONTINUE WITH</div>
                <div class="grid grid-cols-2 gap-2">
                     <?php
                        // Re-use the URLs generated above
                        $google_login_url = $google_login_url ?? site_url('/wp-json/wslu-social-login/type/google'); // Fallback URL
                        $github_login_url = $github_login_url ?? site_url('/wp-json/wslu-social-login/type/github'); // Fallback URL
                    ?>
                    <a rel="nofollow" href="<?php echo esc_url($google_login_url); ?>" class="auth-social-btn">
                        <i class="fab fa-google text-red-500 mr-1.5 text-sm"></i>
                        <span>Google</span>
                    </a>
                    <a rel="nofollow" href="<?php echo esc_url($github_login_url); ?>" class="auth-social-btn">
                        <i class="fab fa-github text-together-dark mr-1.5 text-sm"></i>
                        <span>GitHub</span>
                    </a>
                </div>
                <p class="text-xs text-center text-gray-400 mt-2">(Ensure Social Login plugin is configured)</p>


                 <?php // Login Link Section ?>
                <div class="orunk-login-links-section mt-4">
                    <?php esc_html_e('Already have an account?', 'orunk-users'); ?>
                    <span id="show-login" class="auth-toggle">
                        <?php esc_html_e('Sign in', 'orunk-users'); ?>
                    </span>
                </div>
            </div> <?php // end #signup-form ?>
        </div> <?php // end .auth-container ?>
    </div> <?php // end .relative ?>

    <?php wp_footer(); // Include WordPress footer hooks ?>

    <script type="text/javascript">
        // --- JavaScript for toggling and AJAX signup (Unchanged) ---
        document.addEventListener('DOMContentLoaded', function() {
            const showSignupBtn = document.getElementById('show-signup');
            const showLoginBtn = document.getElementById('show-login');
            const loginFormDiv = document.getElementById('login-form');
            const signupFormDiv = document.getElementById('signup-form');
            const authTitle = document.getElementById('auth-title');
            const authSubtitle = document.getElementById('auth-subtitle');
            const signupForm = document.getElementById('orunk-ajax-signup-form');
            const signupMessages = document.getElementById('signup-messages');
            const signupSubmitBtn = document.getElementById('signup-submit-button');
            const signupBtnText = document.getElementById('signup-button-text');
            const signupSpinner = document.getElementById('signup-spinner');

            // --- Toggle Forms ---
            if (showSignupBtn && showLoginBtn && loginFormDiv && signupFormDiv && authTitle && authSubtitle) {
                showSignupBtn.addEventListener('click', function() { /* */
                    loginFormDiv.classList.add('hidden');
                    loginFormDiv.classList.remove('auth-animate');
                    signupFormDiv.classList.remove('hidden');
                    signupFormDiv.classList.add('auth-animate');
                    authTitle.textContent = '<?php echo esc_js(__('Create an account', 'orunk-users')); ?>'; /* */
                    authSubtitle.textContent = '<?php echo esc_js(__('Get started with your free Orunk account', 'orunk-users')); ?>'; /* */
                });

                showLoginBtn.addEventListener('click', function() { /* */
                    signupFormDiv.classList.add('hidden');
                    signupFormDiv.classList.remove('auth-animate');
                    loginFormDiv.classList.remove('hidden');
                    loginFormDiv.classList.add('auth-animate');
                    authTitle.textContent = '<?php echo esc_js(__('Welcome back', 'orunk-users')); ?>'; /* */
                    authSubtitle.textContent = '<?php echo esc_js(__('Sign in to your Orunk account', 'orunk-users')); ?>'; /* */
                });
            } else {
                console.error("One or more toggle elements not found.");
            }

             // --- AJAX Signup Handler ---
             if (signupForm && signupMessages && signupSubmitBtn && signupBtnText && signupSpinner) {
                signupForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    signupMessages.innerHTML = '';
                    signupSubmitBtn.disabled = true;
                    signupBtnText.textContent = '<?php echo esc_js(__('Creating Account...', 'orunk-users')); ?>';
                    signupSpinner.style.display = 'inline-block';

                    const formData = new FormData(signupForm);
                    const password = formData.get('signup_password');
                    const confirmPassword = formData.get('signup_confirm_password');

                    if (password !== confirmPassword) {
                        displaySignupMessage('<?php echo esc_js(__('Passwords do not match.', 'orunk-users')); ?>', 'error');
                        resetSignupButton();
                        return;
                    }
                    // The terms checkbox should have value="on" when checked by default in HTML forms
                    if (!formData.has('terms') || formData.get('terms') !== 'on') {
                        displaySignupMessage('<?php echo esc_js(__('You must agree to the terms and conditions.', 'orunk-users')); ?>', 'error');
                        resetSignupButton();
                        return;
                    }


                    fetch('<?php echo esc_url(admin_url("admin-ajax.php")); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        // Check if response is valid JSON before parsing
                        const contentType = response.headers.get("content-type");
                        if (contentType && contentType.indexOf("application/json") !== -1) {
                            return response.json();
                        } else {
                            // Handle non-JSON responses (e.g., PHP errors)
                            return response.text().then(text => {
                                throw new Error("Server returned non-JSON response: " + text);
                            });
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            displaySignupMessage(data.message || '<?php echo esc_js(__('Registration successful! Redirecting...', 'orunk-users')); ?>', 'success');
                            window.location.href = data.redirect_url || '<?php echo esc_url(home_url("/orunk-dashboard/")); ?>';
                        } else {
                            displaySignupMessage(data.message || '<?php echo esc_js(__('Registration failed. Please check the details below.', 'orunk-users')); ?>', 'error', data.errors);
                            resetSignupButton();
                        }
                    })
                    .catch(error => {
                        console.error('Signup AJAX Error:', error);
                        displaySignupMessage('<?php echo esc_js(__('An unexpected error occurred. Please try again.', 'orunk-users')); ?>' + (error.message ? ` (${error.message})` : ''), 'error');
                        resetSignupButton();
                    });
                });
            }

            function displaySignupMessage(message, type = 'error', errors = null) {
                signupMessages.innerHTML = '';
                const messageDiv = document.createElement('div');
                messageDiv.className = `orunk-form-${type}-message`;

                let contentHTML = `<p>${escapeHTML(message)}</p>`;
                if (type === 'error' && errors && Array.isArray(errors) && errors.length > 0) {
                    contentHTML += '<ul>';
                    errors.forEach(errMsg => { contentHTML += `<li>${escapeHTML(errMsg)}</li>`; });
                    contentHTML += '</ul>';
                }
                messageDiv.innerHTML = contentHTML;
                signupMessages.appendChild(messageDiv);
            }

            function resetSignupButton() {
                signupSubmitBtn.disabled = false;
                signupBtnText.textContent = '<?php echo esc_js(__('Create Account', 'orunk-users')); ?>';
                signupSpinner.style.display = 'none';
            }

            function escapeHTML(str) {
                if (str === null || typeof str === 'undefined') return '';
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }

        }); // End DOMContentLoaded
    </script>

</body>
</html>