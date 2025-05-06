/**
 * Orunk User Dashboard - Profile Script
 * Handles Account card, Profile modal, picture uploads,
 * and the Forgot Password/OTP flow within the modal.
 */

// Uses functions defined in main.js: openModal, closeModal, showButtonSpinner, setModalFeedback, escapeHTML

function openProfileModal() {
    openModal('profile-modal');
}

function handleProfileSave(event) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');
    showButtonSpinner(button, true);
    setModalFeedback('profile-modal', '', true); // Clear previous feedback
    const formData = new FormData(form);
    formData.append('action', 'orunk_update_profile');
    formData.append('nonce', orunkDashboardData.profileNonce); // Use global data

    fetch(orunkDashboardData.ajaxUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            setModalFeedback('profile-modal', data.message || 'Profile updated!', true);

            // Update avatar preview and account card avatar if URL provided
            const newAvatarUrl = data.data?.new_avatar_url;
            if (newAvatarUrl) {
                const previewImg = document.getElementById('profile-picture-preview-img');
                if (previewImg) previewImg.src = newAvatarUrl;
                const accountCard = document.querySelector('.styled-card .fa-user')?.closest('.styled-card');
                if (accountCard) {
                    const accountCardAvatar = accountCard.querySelector('.styled-card-body .rounded-full');
                    if (accountCardAvatar) accountCardAvatar.src = newAvatarUrl;
                }
                // Update orunkDashboardData if it exists and contains avatar URL
                if(typeof orunkDashboardData !== 'undefined') {
                     orunkDashboardData.userAvatarUrl = newAvatarUrl;
                }
            }

            // Update account card name and email
            const accountCardName = document.querySelector('.styled-card .fa-user')?.closest('.styled-card').querySelector('.text-sm.font-medium');
            if (accountCardName) accountCardName.textContent = formData.get('display_name');
            const accountCardEmail = document.querySelector('.styled-card .fa-user')?.closest('.styled-card').querySelector('.text-xs.text-gray-500');
            if (accountCardEmail) accountCardEmail.textContent = formData.get('email');

            setTimeout(() => { closeModal('profile-modal'); }, 1500);
        } else {
            setModalFeedback('profile-modal', data.data?.message || 'Update failed.', false);
        }
    })
    .catch(error => {
        console.error('Profile Save Error:', error);
        setModalFeedback('profile-modal', 'An error occurred.', false);
    })
    .finally(() => {
        showButtonSpinner(button, false);
        // Clear password fields always
        if(form.elements['current_password']) form.elements['current_password'].value = '';
        if(form.elements['new_password']) form.elements['new_password'].value = '';
        if(form.elements['confirm_password']) form.elements['confirm_password'].value = '';
        // Reset profile picture flags
        const removeInput = document.getElementById('remove_profile_picture');
        if (removeInput) removeInput.value = '0';
        const fileInput = document.getElementById('profile_picture');
        if (fileInput) fileInput.value = '';
    });
}

function triggerProfilePictureUpload() {
    const fileInput = document.getElementById('profile_picture');
    if(fileInput) {
        fileInput.click();
    } else {
        console.error("Profile picture input element not found.");
    }
}

function previewProfilePicture(input) {
    const preview = document.getElementById('profile-picture-preview-img');
    const removeInput = document.getElementById('remove_profile_picture');
    if (input.files && input.files[0] && preview) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            if (removeInput) removeInput.value = '0'; // Mark as not removing if new upload chosen
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function handleRemovePicture() {
    const preview = document.getElementById('profile-picture-preview-img');
    const removeInput = document.getElementById('remove_profile_picture');
    const fileInput = document.getElementById('profile_picture');
    // Get default avatar URL - try from localized data first
    const defaultAvatar = typeof orunkDashboardData !== 'undefined' && orunkDashboardData.userAvatarUrl
        ? orunkDashboardData.userAvatarUrl // Prefer JS data if available
        : '<?php echo esc_url(get_avatar_url($user_id ?? 0, ["size" => 64, "default" => "mystery"])); ?>'; // Fallback

    if (preview) preview.src = defaultAvatar;
    if (removeInput) removeInput.value = '1'; // Mark for removal
    if (fileInput) fileInput.value = ''; // Clear the file input
}


// --- Forgot Password / OTP Flow Functions ---
function transitionForgotPasswordStep(stepToShow) {
    console.log('Transitioning to forgot password step:', stepToShow);
    const forgotSection = document.getElementById('forgot-password-section');
    if (!forgotSection) {
        console.error("Forgot password section not found!");
        return;
    }

    const sections = {
        1: forgotSection.querySelector('#request-otp-section'),
        2: forgotSection.querySelector('#otp-verify-section'),
        3: forgotSection.querySelector('#reset-password-otp-section')
    };

    // Hide all sections first
    Object.values(sections).forEach(section => {
        if (section) section.classList.add('hidden');
    });

    // Show the target section
    if (sections[stepToShow]) {
        console.log(`Showing forgot password section ${stepToShow}`);
        sections[stepToShow].classList.remove('hidden');
        // Focus on the first input of the new step
        const firstInput = sections[stepToShow].querySelector('input:not([type=hidden])');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100); // Delay focus slightly
        }
    } else {
        console.error('Target forgot password step section not found:', stepToShow);
    }
}

function showForgotPasswordView() {
    const profileSection = document.getElementById('profile-details-section');
    const profileFooter = document.getElementById('profile-form-footer');
    const forgotSection = document.getElementById('forgot-password-section');

    if (profileSection) profileSection.classList.add('hidden');
    if (profileFooter) profileFooter.classList.add('hidden');
    if (forgotSection) forgotSection.classList.remove('hidden');

    // Clear feedback messages
    ['modal-feedback-forgot', 'modal-feedback-verify', 'modal-feedback-reset'].forEach(id => {
        const feedbackDiv = document.getElementById(id);
        if (feedbackDiv) feedbackDiv.textContent = '';
    });

    // Pre-fill email field
    const forgotEmailInput = document.getElementById('forgot-email');
    const profileEmailInput = document.getElementById('profile_email');
    if (forgotEmailInput && profileEmailInput) {
        forgotEmailInput.value = profileEmailInput.value; // Use current email from profile form
    }

    forgotPasswordUserIdentifier = ''; // Reset identifier
    transitionForgotPasswordStep(1); // Show the first step (email entry)
}

function showProfileView() {
    const profileSection = document.getElementById('profile-details-section');
    const profileFooter = document.getElementById('profile-form-footer');
    const forgotSection = document.getElementById('forgot-password-section');

    if (forgotSection) forgotSection.classList.add('hidden');
    if (profileSection) profileSection.classList.remove('hidden');
    if (profileFooter) profileFooter.classList.remove('hidden');

    // Clear forgot password feedback
    const forgotFeedback = document.getElementById('modal-feedback-forgot');
    if(forgotFeedback) forgotFeedback.textContent = '';
}

function setForgotFeedback(message, isSuccess, step = 1) {
    let feedbackDivId = 'modal-feedback-forgot';
    if (step === 2) feedbackDivId = 'modal-feedback-verify';
    else if (step === 3) feedbackDivId = 'modal-feedback-reset';

    const feedbackDiv = document.getElementById(feedbackDivId);
    if (feedbackDiv) {
        feedbackDiv.textContent = message;
        // Reset classes first
        feedbackDiv.className = `h-5 mt-2 text-left font-medium`;
        // Add success/error class
        if (message) { // Only add color class if there's a message
            feedbackDiv.classList.add(isSuccess ? 'text-green-600' : 'text-red-600');
            feedbackDiv.classList.add(isSuccess ? 'success' : 'error');
        }
        // Auto-clear feedback
        setTimeout(() => {
            if (feedbackDiv && feedbackDiv.textContent === message) {
                feedbackDiv.textContent = '';
                feedbackDiv.className = `h-5 mt-2`; // Reset class list
            }
        }, 5000);
    } else {
        console.warn('Could not find feedback div for step:', step, 'ID:', feedbackDivId);
    }
}

function handleForgotPasswordSubmit(button) {
    console.log("Step 1: Requesting OTP...");
    const emailInput = document.getElementById('forgot-email');
    const email = emailInput ? emailInput.value.trim() : '';
    setForgotFeedback('', true, 1); // Clear previous step 1 feedback

    if (!email) { setForgotFeedback('Please enter your email address.', false, 1); return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { setForgotFeedback('Please enter a valid email address.', false, 1); return; }

    forgotPasswordUserIdentifier = email; // Store the identifier
    showButtonSpinner(button, true);

    const formData = new FormData();
    formData.append('action', 'orunk_ajax_request_otp'); // Changed AJAX action name
    formData.append('orunk_request_otp_nonce', orunkDashboardData.otpRequestNonce);
    formData.append('user_login', email); // Send email as user_login

    fetch(orunkDashboardData.ajaxUrl, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log("Step 1 Success: OTP Request successful.");
                const otpSentToEmail = document.getElementById('otp-sent-to-email');
                if(otpSentToEmail) otpSentToEmail.textContent = escapeHTML(email);
                transitionForgotPasswordStep(2); // Move to OTP entry step
            } else {
                console.error("Step 1 Error:", data.data?.message || 'Unknown error');
                setForgotFeedback(data.data?.message || 'Failed to send OTP.', false, 1);
            }
        })
        .catch(error => {
            console.error('Forgot Password AJAX Error:', error);
            setForgotFeedback('An error occurred. Please try again.', false, 1);
        })
        .finally(() => {
            showButtonSpinner(button, false);
        });
}

function handleVerifyOtpSubmit(button) {
    console.log("Step 2: Verifying OTP...");
    const otpInput = document.getElementById('otp-code');
    const otpCode = otpInput ? otpInput.value.trim() : '';
    setForgotFeedback('', true, 2); // Clear previous step 2 feedback

    if (!otpCode) { setForgotFeedback('Please enter the OTP code.', false, 2); return; }
    if (!/^\d{6}$/.test(otpCode)) { setForgotFeedback('OTP must be 6 digits.', false, 2); return; }

    if (!forgotPasswordUserIdentifier) {
        console.error("Step 2 Error: User identifier missing.");
        setForgotFeedback('User identifier missing. Please start over.', false, 1);
        transitionForgotPasswordStep(1);
        return;
    }

    showButtonSpinner(button, true);
    const formData = new FormData();
    formData.append('action', 'orunk_ajax_otp_verify'); // Changed AJAX action name
    formData.append('orunk_verify_otp_nonce', orunkDashboardData.otpVerifyNonce);
    formData.append('otp_login', forgotPasswordUserIdentifier); // Send original identifier
    formData.append('otp_code', otpCode);

    fetch(orunkDashboardData.ajaxUrl, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log("Step 2 Success: OTP Verified.");
                transitionForgotPasswordStep(3); // Move to password reset step
            } else {
                console.error("Step 2 Error:", data.data?.message || 'Unknown error');
                setForgotFeedback(data.data?.message || 'Invalid or expired OTP.', false, 2);
                if(otpInput) otpInput.value = ''; // Clear incorrect OTP
            }
        })
        .catch(error => {
            console.error('Verify OTP AJAX Error:', error);
            setForgotFeedback('An error occurred verifying OTP.', false, 2);
        })
        .finally(() => {
            showButtonSpinner(button, false);
        });
}

function handleResetPasswordOtpSubmit(button) {
    console.log("Step 3: Resetting Password...");
    const newPassInput = document.getElementById('reset-new-password');
    const confirmPassInput = document.getElementById('reset-confirm-password');
    const newPass = newPassInput ? newPassInput.value : '';
    const confirmPass = confirmPassInput ? confirmPassInput.value : '';
    setForgotFeedback('', true, 3); // Clear previous step 3 feedback

    if (!newPass || !confirmPass) { setForgotFeedback('Please enter and confirm your new password.', false, 3); return; }
    if (newPass !== confirmPass) { setForgotFeedback('New passwords do not match.', false, 3); return; }

    if (!forgotPasswordUserIdentifier) {
        console.error("Step 3 Error: User identifier missing.");
        setForgotFeedback('User identifier missing. Please start over.', false, 1);
        transitionForgotPasswordStep(1);
        return;
    }

    showButtonSpinner(button, true);
    const formData = new FormData();
    formData.append('action', 'orunk_ajax_reset_password_otp'); // Changed AJAX action name
    formData.append('orunk_reset_password_otp_nonce', orunkDashboardData.otpResetNonce);
    formData.append('rp_login', forgotPasswordUserIdentifier); // Send original identifier
    formData.append('pass1', newPass);
    formData.append('pass2', confirmPass);

    fetch(orunkDashboardData.ajaxUrl, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log("Step 3 Success: Password Reset.");
                // Show success message in the main modal feedback area before closing
                setModalFeedback('profile-modal', data.message || 'Password reset successfully!', true);
                closeModal('profile-modal'); // Close the profile modal entirely
            } else {
                console.error("Step 3 Error:", data.data?.message || 'Unknown error');
                setForgotFeedback(data.data?.message || 'Failed to reset password.', false, 3);
            }
        })
        .catch(error => {
            console.error('Reset Password OTP AJAX Error:', error);
            setForgotFeedback('An error occurred resetting the password.', false, 3);
        })
        .finally(() => {
            showButtonSpinner(button, false);
        });
}