<?php
/**
 * Template Name: Orunk Forgot Password (Single Page AJAX Flow)
 *
 * Handles the complete password reset flow (Request OTP, Verify OTP, Reset Password)
 * on a single page using AJAX.
 *
 * @package YourThemeName
 * @version 3.0.1 (Refactored for clarity)
 */

// Redirect if user is already logged in
if (is_user_logged_in()) {
    wp_safe_redirect(home_url('/orunk-dashboard/'));
    exit;
}

get_header(); // Use your theme's header

// Message definitions - These should match messages sent by class-orunk-otp-handler.php
// Kept here for reference and potential JS fallbacks (though backend messages are preferred)
$messages = [
    'request_success'    => __('An OTP has been sent to your email address if an account exists. Please check your inbox.', 'orunk-users'),
    'verify_success'     => __('OTP verified successfully. Please enter your new password.', 'orunk-users'),
    'reset_success'      => __('Password changed successfully! Redirecting to login...', 'orunk-users'),
    'nonce_fail'         => __('Security check failed. Please refresh and try again.', 'orunk-users'),
    'email_required'     => __('Please enter your username or email address.', 'orunk-users'),
    'user_not_found'     => __('Error: There is no account with that username or email address.', 'orunk-users'),
    'otp_db_error'       => __('Error: Could not process your request at this time (DB).', 'orunk-users'),
    'otp_send_error'     => __('Error: Could not send the OTP email. Please contact support.', 'orunk-users'),
    'rate_limit'         => __('Error: You have requested too many OTPs recently. Please wait.', 'orunk-users'),
    'missing_data'       => __('Error: Missing required information.', 'orunk-users'),
    'invalid_otp'        => __('Error: The OTP entered is invalid or has expired.', 'orunk-users'),
    'max_resend'         => __('Error: OTP resend limit reached.', 'orunk-users'),
    'password_blank'     => __('Error: Password fields cannot be blank.', 'orunk-users'),
    'password_mismatch'  => __('Error: The new passwords do not match.', 'orunk-users'),
    'password_weak'      => __('Error: Your password is too weak. Please choose a stronger one.', 'orunk-users'),
    'password_policy_fail' => __('Error: Password does not meet policy requirements.', 'orunk-users'),
    'reset_error'        => __('Error: An error occurred while resetting your password.', 'orunk-users'),
];

// Generate nonces needed for the forms
$request_otp_nonce = wp_create_nonce('orunk_request_otp_action');
$verify_otp_nonce = wp_create_nonce('orunk_verify_otp_action');
$reset_password_nonce = wp_create_nonce('orunk_reset_password_otp_action');

?>
<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <div class="orunk-auth-container" style="max-width: 450px; margin: 4rem auto; padding: 2rem; background: white; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">

            <h1 id="form-title" class="entry-title" style="text-align: center; margin-bottom: 1.5rem; font-size: 1.5em; font-weight: 600;"><?php esc_html_e('Forgot Your Password?', 'orunk-users'); ?></h1>
            <p id="form-instruction" style="text-align: center; color: #6b7280; font-size: 0.9em; margin-bottom: 1.5rem;">
                <?php esc_html_e('Enter your username or email to receive an OTP.', 'orunk-users'); ?>
            </p>

            <?php // Div to display AJAX messages for all steps ?>
            <div id="orunk-forgot-message-area" style="margin-bottom: 15px; min-height: 40px;" role="alert" aria-live="assertive"></div>

            <?php // --- Step 1: Request OTP Form --- ?>
            <form id="orunk-request-otp-form-ajax" method="post" class="orunk-step-form" data-step="1" style="transition: opacity 0.3s ease-out;">
                <input type="hidden" name="orunk_request_otp_nonce" value="<?php echo esc_attr($request_otp_nonce); ?>">

                <p class="orunk-form-row" style="margin-bottom: 1rem;">
                    <label for="orunk_user_login_ajax" style="display: block; margin-bottom: 0.25rem; font-size: 0.8rem; font-weight: 500; color: #4b5563;"><?php esc_html_e('Username or Email Address', 'orunk-users'); ?></label>
                    <input type="text" name="user_login" id="orunk_user_login_ajax" class="input-text" required aria-required="true" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #d1d5db; border-radius: 6px;">
                </p>

                <p class="orunk-form-submit" style="margin-top: 1.5rem;">
                    <button type="submit" class="button orunk-button-primary ajax-submit-btn" style="width: 100%; padding: 0.7rem 1rem; background: #4f46e5; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                        <span class="button-text"><?php esc_html_e('Send OTP', 'orunk-users'); ?></span>
                        <span class="spinner" style="display: none;"></span>
                    </button>
                </p>
            </form>

            <?php // --- Step 2: Verify OTP Form (Initially Hidden) --- ?>
            <form id="orunk-verify-otp-form-ajax" method="post" class="orunk-step-form hidden" data-step="2" style="transition: opacity 0.3s ease-out;">
                 <input type="hidden" name="orunk_verify_otp_nonce" value="<?php echo esc_attr($verify_otp_nonce); ?>">
                 <input type="hidden" name="otp_login" id="otp_login_hidden" value=""> <?php // User login/email will be stored here by JS ?>

                <p class="orunk-form-row" style="margin-bottom: 1rem;">
                    <label for="orunk_otp_code" style="display: block; margin-bottom: 0.25rem; font-size: 0.8rem; font-weight: 500; color: #4b5563;"><?php esc_html_e('Enter OTP', 'orunk-users'); ?></label>
                    <input type="text" inputmode="numeric" pattern="[0-9]*" name="otp_code" id="orunk_otp_code" class="input-text" required aria-required="true" autocomplete="one-time-code" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #d1d5db; border-radius: 6px; text-align: center; font-size: 1.2em; letter-spacing: 0.3em;">
                    <?php // Optional: Add Resend OTP link/button here later ?>
                </p>

                <p class="orunk-form-submit" style="margin-top: 1.5rem;">
                    <button type="submit" class="button orunk-button-primary ajax-submit-btn" style="width: 100%; padding: 0.7rem 1rem; background: #4f46e5; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                        <span class="button-text"><?php esc_html_e('Verify OTP', 'orunk-users'); ?></span>
                        <span class="spinner" style="display: none;"></span>
                    </button>
                </p>
            </form>

             <?php // --- Step 3: Reset Password Form (Initially Hidden) --- ?>
            <form id="orunk-reset-password-form-ajax" method="post" class="orunk-step-form hidden" data-step="3" style="transition: opacity 0.3s ease-out;">
                <input type="hidden" name="orunk_reset_password_otp_nonce" value="<?php echo esc_attr($reset_password_nonce); ?>">
                <input type="hidden" name="rp_login" id="rp_login_hidden" value=""> <?php // User login/email will be stored here by JS ?>

                <p class="orunk-form-row" style="margin-bottom: 1rem;">
                    <label for="orunk_pass1" style="display: block; margin-bottom: 0.25rem; font-size: 0.8rem; font-weight: 500; color: #4b5563;"><?php esc_html_e('New Password', 'orunk-users'); ?></label>
                    <input type="password" name="pass1" id="orunk_pass1" class="input-text" required aria-required="true" autocomplete="new-password" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #d1d5db; border-radius: 6px;">
                </p>
                 <p class="orunk-form-row" style="margin-bottom: 1rem;">
                    <label for="orunk_pass2" style="display: block; margin-bottom: 0.25rem; font-size: 0.8rem; font-weight: 500; color: #4b5563;"><?php esc_html_e('Confirm New Password', 'orunk-users'); ?></label>
                    <input type="password" name="pass2" id="orunk_pass2" class="input-text" required aria-required="true" autocomplete="new-password" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #d1d5db; border-radius: 6px;">
                </p>
                <p class="description indicator-hint" style="font-size: 0.75rem; color: #6b7280; margin-bottom: 1.5rem;"><?php echo wp_get_password_hint(); ?></p>

                <p class="orunk-form-submit">
                    <button type="submit" class="button orunk-button-primary ajax-submit-btn" style="width: 100%; padding: 0.7rem 1rem; background: #4f46e5; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                        <span class="button-text"><?php esc_html_e('Set New Password', 'orunk-users'); ?></span>
                        <span class="spinner" style="display: none;"></span>
                    </button>
                </p>
            </form>

            <p class="orunk-back-link" style="text-align: center; margin-top: 1.5rem; font-size: 0.85em;">
                <a href="<?php echo esc_url(home_url('/orunk-login/')); // Adjust if needed ?>" style="color: #4f46e5; text-decoration: none;">&larr; <?php esc_html_e('Back to Login', 'orunk-users'); ?></a>
            </p>

        </div> <?php // .orunk-auth-container ?>
    </main>
</div> <?php // #primary ?>

<style>
    @keyframes spin { to { transform: rotate(360deg); } }
    .hidden { display: none !important; }
    .spinner { display: inline-block; width: 1em; height: 1em; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s linear infinite; margin-left: 8px; }
    .orunk-message { border-left-width: 4px; padding: 10px 15px; display: block; width: 100%; border-radius: 4px; }
    .orunk-message p { margin:0; }
    .notice-success { background-color: #f0fdf4; border-color: #bbf7d0; color: #166534; }
    .notice-error { background-color: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .orunk-step-form { opacity: 1; }
    .orunk-step-form.hidden { opacity: 0; height: 0; overflow: hidden; /* Ensures no layout space is taken */ }
</style>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // --- DOM Elements ---
    const requestForm = document.getElementById('orunk-request-otp-form-ajax');
    const verifyForm = document.getElementById('orunk-verify-otp-form-ajax');
    const resetForm = document.getElementById('orunk-reset-password-form-ajax');
    const messageArea = document.getElementById('orunk-forgot-message-area');
    const formTitle = document.getElementById('form-title');
    const formInstruction = document.getElementById('form-instruction');
    const allForms = [requestForm, verifyForm, resetForm]; // Keep track of all forms

    // --- State ---
    let userLoginIdentifier = ''; // To store username/email for verify/reset steps

    // --- Event Listeners ---
    allForms.forEach(form => {
        if (form) {
            form.addEventListener('submit', handleFormSubmit);
        }
    });

    // --- Main Handler ---
    function handleFormSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const step = parseInt(form.dataset.step || '1', 10);
        const submitButton = form.querySelector('.ajax-submit-btn');
        const buttonText = submitButton?.querySelector('.button-text');
        const spinner = submitButton?.querySelector('.spinner');

        // Show loading state
        if (submitButton && buttonText && spinner) {
            submitButton.disabled = true;
            buttonText.textContent = '<?php echo esc_js(__('Processing...', 'orunk-users')); ?>';
            spinner.style.display = 'inline-block';
            messageArea.innerHTML = ''; // Clear previous step's messages
        } else {
            console.error("Could not find button elements for step", step);
            return; // Stop if button elements are missing
        }

        const formData = new FormData(form);
        let ajaxAction = '';

        // Determine AJAX action and add necessary data
        if (step === 1) {
            ajaxAction = 'orunk_ajax_request_otp';
            userLoginIdentifier = formData.get('user_login') || ''; // Store for next steps
            if (!userLoginIdentifier) {
                displayAjaxMessage('<?php echo esc_js($messages['email_required']); ?>', false);
                resetButtonState(submitButton, form);
                return;
            }
            formData.append('action', ajaxAction); // Append WP AJAX action
        } else if (step === 2) {
            ajaxAction = 'orunk_ajax_otp_verify';
            // Ensure the hidden field was populated correctly
            const otpLogin = document.getElementById('otp_login_hidden')?.value || userLoginIdentifier;
            if (!otpLogin) {
                 displayAjaxMessage('<?php echo esc_js($messages['missing_data']); ?> User identifier missing. Please start over.', false);
                 resetButtonState(submitButton, form);
                 transitionToStep(1); // Go back to step 1
                 return;
            }
            // formData already has 'otp_login' from the hidden input
            formData.append('action', ajaxAction); // Append WP AJAX action
        } else if (step === 3) {
            ajaxAction = 'orunk_ajax_reset_password_otp';
             // Ensure the hidden field was populated correctly
            const rpLogin = document.getElementById('rp_login_hidden')?.value || userLoginIdentifier;
             if (!rpLogin) {
                 displayAjaxMessage('<?php echo esc_js($messages['missing_data']); ?> User identifier missing. Please start over.', false);
                 resetButtonState(submitButton, form);
                 transitionToStep(1); // Go back to step 1
                 return;
            }
            // formData already has 'rp_login' from the hidden input
            formData.append('action', ajaxAction); // Append WP AJAX action
        } else {
            console.error('Invalid form step:', step);
            displayAjaxMessage('<?php echo esc_js(__('An internal error occurred.', 'orunk-users')); ?>', false);
            resetButtonState(submitButton, form);
            return;
        }

        // Perform AJAX request
        fetch('<?php echo esc_url(admin_url("admin-ajax.php")); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Check if response is OK (status 200-299)
            if (!response.ok) {
                // Attempt to read the response text for better error details
                return response.text().then(text => {
                    let errorMsg = `<?php echo esc_js(__('Server error', 'orunk-users')); ?> ${response.status}.`;
                    // Try to parse as JSON, maybe WP sent a structured error
                    try {
                        const errorData = JSON.parse(text);
                        // Use the message from the JSON error data if available
                        if (errorData && errorData.data && errorData.data.message) {
                            errorMsg = errorData.data.message;
                        } else {
                             errorMsg += ` Response: ${text.substring(0, 100)}...`;
                        }
                    } catch (e) {
                        // If not JSON, append the beginning of the text response
                        errorMsg += ` Response: ${text.substring(0, 100)}...`;
                    }
                    throw new Error(errorMsg); // Throw the refined error message
                });
            }
            // If response is OK, parse JSON
            return response.json();
        })
        .then(data => {
            // Check for success flag from wp_send_json_success/error
            if (data.success) {
                 // Use message from backend, fallback only if absolutely necessary
                if (data.data && data.data.message) {
                    displayAjaxMessage(data.data.message, true); // Use the message from the backend
                } else {
                    // Fallback if backend succeeded but sent no message (should ideally not happen)
                    console.warn(`AJAX Success (Step ${step}): Backend response missing 'data.message'.`);
                    displayAjaxMessage(`<?php echo esc_js(__('Operation successful.', 'orunk-users')); ?>`, true); // A generic but better fallback
                }

                // --- Handle Success for Each Step ---
                if (step === 1) {
                    transitionToStep(2);
                    const hiddenLogin = document.getElementById('otp_login_hidden');
                    if(hiddenLogin) hiddenLogin.value = userLoginIdentifier;
                } else if (step === 2) {
                    transitionToStep(3);
                    const hiddenRpLogin = document.getElementById('rp_login_hidden');
                     if(hiddenRpLogin) hiddenRpLogin.value = userLoginIdentifier;
                } else if (step === 3) {
                    // Password reset success - Redirect to login
                    if(buttonText) buttonText.textContent = '<?php echo esc_js(__('Success!', 'orunk-users')); ?>'; // Keep button disabled
                    if(spinner) spinner.style.display = 'none';
                    setTimeout(() => {
                        // Use redirect URL from backend if provided, otherwise default
                        window.location.href = data.redirect_url || '<?php echo esc_url(home_url("/orunk-login/?orunk_message=password_reset")); ?>';
                    }, 2000); // Redirect after 2 seconds
                }
            } else {
                // Handle Error reported by wp_send_json_error
                 // Use message from backend's error data, fallback to a generic error
                 displayAjaxMessage(data.data?.message || `<?php echo esc_js(__('Step ${step} failed. Please try again.', 'orunk-users')); ?>`, false);
                 resetButtonState(submitButton, form);
            }
        })
        .catch(error => {
            console.error(`AJAX Error (Step ${step}):`, error);
            // Display error message caught from network/parsing/manual throw
            displayAjaxMessage(error.message || `<?php echo esc_js(__('An unexpected error occurred during step ${step}. Please check console or try again.', 'orunk-users')); ?>`, false);
            resetButtonState(submitButton, form);
        });
    } // end handleFormSubmit

    // --- UI Management Functions ---
    function transitionToStep(nextStep) {
        allForms.forEach(f => {
            if(f) {
                const currentStepNum = parseInt(f.dataset.step || '0', 10);
                if (currentStepNum === nextStep) {
                    f.classList.remove('hidden');
                    f.style.opacity = '0'; // Start faded out
                    setTimeout(() => { f.style.opacity = '1'; }, 50); // Fade in
                } else {
                    f.classList.add('hidden');
                     f.style.opacity = '1'; // Reset opacity if needed
                }
            }
        });

        const nextForm = document.querySelector(`.orunk-step-form[data-step="${nextStep}"]`);
        if (nextForm) {
             // Update title and instructions
             if (nextStep === 2) {
                 formTitle.textContent = '<?php echo esc_js(__('Verify Your Identity', 'orunk-users')); ?>';
                 formInstruction.textContent = '<?php echo esc_js(__('Please enter the OTP sent to your email.', 'orunk-users')); ?>';
             } else if (nextStep === 3) {
                 formTitle.textContent = '<?php echo esc_js(__('Set New Password', 'orunk-users')); ?>';
                 formInstruction.textContent = '<?php echo esc_js(__('Enter and confirm your new password below.', 'orunk-users')); ?>';
             } else { // Back to step 1 (e.g., on error)
                  formTitle.textContent = '<?php echo esc_js(__('Forgot Your Password?', 'orunk-users')); ?>';
                  formInstruction.textContent = '<?php echo esc_js(__('Enter your username or email to receive an OTP.', 'orunk-users')); ?>';
             }
             // Focus on the first input of the new step
             const firstInput = nextForm.querySelector('input[type="text"], input[type="password"]');
             if(firstInput) {
                setTimeout(() => firstInput.focus(), 100); // Delay focus slightly
             }
        } else {
            console.error('Could not find form for step:', nextStep);
        }
    }

    function resetButtonState(button, form) {
        if (!button) return;
        const buttonText = button.querySelector('.button-text');
        const spinner = button.querySelector('.spinner');
        const step = parseInt(form?.dataset.step || '1', 10);
        let originalText = 'Submit';

        // Determine original text based on step
        if (step === 1) originalText = '<?php echo esc_js(__('Send OTP', 'orunk-users')); ?>';
        else if (step === 2) originalText = '<?php echo esc_js(__('Verify OTP', 'orunk-users')); ?>';
        else if (step === 3) originalText = '<?php echo esc_js(__('Set New Password', 'orunk-users')); ?>';

        button.disabled = false;
        if (buttonText) buttonText.textContent = originalText;
        if (spinner) spinner.style.display = 'none';
    }

    function displayAjaxMessage(message, isSuccess) {
        messageArea.innerHTML = ''; // Clear previous
        const messageDiv = document.createElement('div');
        const messageClass = isSuccess ? 'notice-success' : 'notice-error';
        messageDiv.className = `orunk-message ${messageClass}`;
        messageDiv.innerHTML = `<p>${escapeHTML(message)}</p>`; // Display the exact message received/constructed
        messageArea.appendChild(messageDiv);
    }

    function escapeHTML(str) {
        if (typeof str !== 'string') return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Ensure initial state is correct (show only step 1)
    transitionToStep(1);

});
</script>

<?php get_footer(); // Use your theme's footer ?>