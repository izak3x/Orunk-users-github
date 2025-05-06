/**
 * Orunk User Dashboard - Main Script
 * Handles core initialization, event listener setup,
 * modal management, and utility functions.
 */

// Global variables defined in the PHP template (orunkDashboardData, etc.) are accessible here.
// Global 'let' variables like currentBillingAddress are also accessible.

document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard Main DOM Loaded.');

    // Initialize functions from other files if needed on load
    if (typeof initializePurchaseHistoryView === 'function') {
         initializePurchaseHistoryView(); // From history.js
    }
    if (typeof fetchBillingAddress === 'function') {
         fetchBillingAddress(); // From billing.js
    }

    setupEventListeners();
    displayInitialMessages();
});

function setupEventListeners() {
    console.log('Dashboard Main JS: setupEventListeners called.');

    // --- Main Event Listener (Delegation) ---
    document.body.addEventListener('click', function(event) {
        // console.log('Dashboard Main JS: Body click detected. Target:', event.target); // Optional: for debugging clicks

        // --- Delegate to Profile Handlers (profile.js) ---
        if (event.target.closest('#edit-profile-btn') && typeof openProfileModal === 'function') {
            openProfileModal();
        } else if (event.target.closest('#upload-picture-btn') && typeof triggerProfilePictureUpload === 'function') {
             triggerProfilePictureUpload();
        } else if (event.target.closest('#remove-picture-btn') && typeof handleRemovePicture === 'function') {
             handleRemovePicture();
        } else if (event.target.closest('#show-forgot-password-modal') && typeof showForgotPasswordView === 'function') {
             showForgotPasswordView();
        } else if (event.target.closest('#back-to-profile-view') && typeof showProfileView === 'function') {
             showProfileView();
        } else if (event.target.closest('#submit-forgot-email') && typeof handleForgotPasswordSubmit === 'function') {
             handleForgotPasswordSubmit(event.target.closest('#submit-forgot-email'));
        } else if (event.target.closest('#back-to-email-entry') && typeof transitionForgotPasswordStep === 'function') {
             transitionForgotPasswordStep(1);
        } else if (event.target.closest('#submit-verify-otp') && typeof handleVerifyOtpSubmit === 'function') {
             handleVerifyOtpSubmit(event.target.closest('#submit-verify-otp'));
        } else if (event.target.closest('#back-to-otp-entry') && typeof transitionForgotPasswordStep === 'function') {
             transitionForgotPasswordStep(2);
        } else if (event.target.closest('#submit-reset-password-otp') && typeof handleResetPasswordOtpSubmit === 'function') {
             handleResetPasswordOtpSubmit(event.target.closest('#submit-reset-password-otp'));
        }
        // --- End Profile ---

        // --- Delegate to Billing Handlers (billing.js) ---
        else if (event.target.closest('#manage-address-btn') && typeof openBillingModal === 'function') {
            openBillingModal();
        }
        // --- End Billing ---

        // --- Delegate to Ad Experience Handlers (none currently needed beyond basic form post) ---
        // Add here if JS logic is added for the ad card

        // --- Delegate to Services Handlers (services.js) ---
        else if (event.target.closest('.orunk-copy-button') && typeof handleCopyKey === 'function') {
            handleCopyKey(event.target.closest('.orunk-copy-button'));
        } else if (event.target.closest('.orunk-regenerate-key-button') && typeof handleRegenerateKey === 'function') {
            handleRegenerateKey(event.target.closest('.orunk-regenerate-key-button'));
        } else if (event.target.closest('.change-plan-btn') && typeof openPlanChangeModal === 'function') {
            openPlanChangeModal(event.target.closest('.change-plan-btn'));
        } else if (event.target.closest('#plan-modal .plan-card') && typeof selectPlanCard === 'function') {
            selectPlanCard(event.target.closest('#plan-modal .plan-card'));
        } else if (event.target.closest('#confirm-plan-change') && typeof handlePlanChangeConfirm === 'function') {
            handlePlanChangeConfirm(event.target.closest('#confirm-plan-change'));
        } else if (event.target.closest('.download-plugin-btn') && typeof handlePluginDownload === 'function') {
            handlePluginDownload(event.target.closest('.download-plugin-btn'));
        }
        // --- End Services ---

        // --- Delegate to History Handlers (history.js) ---
        else if (event.target.closest('#show-more-history') && typeof handleShowMoreHistory === 'function') {
            handleShowMoreHistory(event.target.closest('#show-more-history'));
        }
        // --- End History ---

    }); // End body click listener

    // --- Attach Form Submit Listeners ---
    const profileForm = document.getElementById('profile-form');
    if (profileForm && typeof handleProfileSave === 'function') {
        profileForm.addEventListener('submit', handleProfileSave);
    } else if (profileForm) { console.warn("Profile form found, but handleProfileSave function is missing."); }

    const billingForm = document.getElementById('billing-form');
    if (billingForm && typeof handleBillingSave === 'function') {
        billingForm.addEventListener('submit', handleBillingSave);
    } else if (billingForm) { console.warn("Billing form found, but handleBillingSave function is missing."); }

    // Attach form submit listeners for plan cancellation and ad cancellation (if they don't have AJAX handlers)
    // No AJAX needed currently, handled by PHP form POST

    // --- Attach Change Listeners ---
    document.body.addEventListener('change', function(event) {
        // Auto-Renew Toggle (services.js)
        if (event.target.classList.contains('auto-renew-toggle') && typeof handleAutoRenewToggle === 'function') {
            handleAutoRenewToggle(event.target);
        }
        // Profile Picture Input (profile.js)
        if (event.target.id === 'profile_picture' && typeof previewProfilePicture === 'function') {
            previewProfilePicture(event.target);
        }
    });

    // --- Attach Modal Listeners (Close Buttons, Overlay) ---
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        if (!modal) return;
        const closeBtn = modal.querySelector('.modal-close-btn');
        const cancelBtn = modal.querySelector('.modal-footer button.orunk-button-outline'); // General cancel
        const modalId = modal.id;

        if (closeBtn) {
            closeBtn.addEventListener('click', () => { closeModal(modalId); }); // Use closeModal from this file
        }
        // Ignore cancel buttons within specific forms handled differently (like plan-modal-cancel-form)
        if (cancelBtn && !cancelBtn.closest('#plan-modal-cancel-form') && !cancelBtn.closest('#forgot-password-section')) {
            cancelBtn.addEventListener('click', () => { closeModal(modalId); }); // Use closeModal from this file
        }
        // Overlay click
        modal.addEventListener('click', (event) => {
            if (event.target === modal) { closeModal(modalId); } // Use closeModal from this file
        });
    });

} // End setupEventListeners

// --- Utility Functions ---
function displayInitialMessages() {
    const messagesContainer = document.getElementById('messages');
    if (!messagesContainer) return;

    try {
        const urlParams = new URLSearchParams(window.location.search);
        const errorMsg = urlParams.get('orunk_error');
        // Get success message from PHP variable if available (assuming it's globally defined)
        const successMsg = typeof orunkInitialSuccessMessage !== 'undefined' ? orunkInitialSuccessMessage : null; // Requires PHP change

        if (errorMsg) displayMessage('error', decodeURIComponent(errorMsg));
        if (successMsg) displayMessage('success', successMsg);

        // Clear the transient message via JS if possible (requires nonce/permission - maybe simpler to let PHP handle it on load)
        // Alternatively, just remove the query param after displaying
        if (errorMsg || successMsg) {
            // Remove params from URL without reload
            if (window.history.replaceState) {
                 const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + window.location.hash;
                 window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
            }
        }
    } catch (e) {
        console.error("Error processing initial messages:", e);
    }
}

function displayMessage(type, text, targetElement = null) {
    const container = targetElement || document.getElementById('messages');
    if (!container) return;

    const ajaxMessageDiv = document.getElementById('ajax-message');
    const ajaxTextSpan = document.getElementById('ajax-text');

    if (ajaxMessageDiv && ajaxTextSpan) {
        ajaxTextSpan.innerHTML = escapeHTML(text); // Use innerHTML to allow potential basic formatting if needed later
        ajaxMessageDiv.className = `alert alert-${type} !text-sm`; // Reset classes
        ajaxMessageDiv.classList.remove('hidden');
        const icon = ajaxMessageDiv.querySelector('i');
        if (icon) icon.className = `fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle')}`;

        // Set timeout to hide the message
        setTimeout(() => {
            ajaxMessageDiv.classList.add('hidden');
        }, 5000);
    } else {
        // Fallback if the dedicated ajax message div doesn't exist
        const alertDiv = document.createElement('div');
        alertDiv.classList.add('alert', `alert-${type}`, '!text-sm');
        alertDiv.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle')}"></i> ${escapeHTML(text)}`;
        container.insertBefore(alertDiv, container.firstChild);
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

function openModal(modalId) {
    console.log('>>> Entering openModal for ID:', modalId);
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.error(`Modal with ID ${modalId} not found.`);
        return;
    }
    console.log(`Modal ${modalId} - Before removing hidden: classes=${modal.className}`);
    modal.classList.remove('hidden');
    void modal.offsetWidth; // Force reflow

    console.log(`Modal ${modalId} - Attempting to add 'active' class...`);
    modal.classList.add('active');

    // Check if 'active' was added
    if (modal.classList.contains('active')) {
        console.log(`Modal ${modalId} - SUCCESSFULLY added 'active' class. Current classes: ${modal.className}`);
    } else {
        console.error(`Modal ${modalId} - FAILED to add 'active' class! Current classes: ${modal.className}`);
        // Fallback to ensure visibility?
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
    }

    const form = modal.querySelector('form');
    const feedback = modal.querySelector('[id^="modal-feedback-"]');

    // Clear previous feedback
    if (feedback) {
        feedback.textContent = '';
        feedback.className = feedback.className.replace(/text-(red|green)-[0-9]+/g, '').replace(/success|error/g, ''); // Clear color/status classes
    }
    // Clear specific forgot password feedback fields too
    ['modal-feedback-forgot', 'modal-feedback-verify', 'modal-feedback-reset'].forEach(id => {
        const fpFeedback = modal.querySelector(`#${id}`);
        if (fpFeedback) fpFeedback.textContent = '';
    });


    // Conditional population / reset
    if (modalId === 'billing-modal' && form && currentBillingAddress && typeof populateBillingForm === 'function') {
        populateBillingForm(form, currentBillingAddress); // Assumes populateBillingForm is in billing.js
    } else if (form && modalId !== 'plan-modal') { // Don't reset plan modal form fields here
        form.reset();
        if (modalId === 'profile-modal' && typeof showProfileView === 'function') {
            showProfileView(); // Reset profile view to details, defined in profile.js
            const previewImg = document.getElementById('profile-picture-preview-img');
            const removeInput = document.getElementById('remove_profile_picture');
             // Use orunkDashboardData if available, otherwise fallback to potentially outdated PHP echo
             const defaultAvatarUrl = typeof orunkDashboardData !== 'undefined' && orunkDashboardData.userAvatarUrl
                ? orunkDashboardData.userAvatarUrl
                : '<?php echo esc_url(get_avatar_url($user_id ?? 0, ["size" => 64])); ?>'; // Fallback needed if PHP is gone

            if (previewImg) previewImg.src = defaultAvatarUrl;
            if (removeInput) removeInput.value = '0';
            const fileInput = document.getElementById('profile_picture');
            if(fileInput) fileInput.value = ''; // Clear file input
        }
    }

    // Focus first input
    const firstInput = modal.querySelector('input:not([type=hidden]), select, textarea');
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 100); // Delay focus slightly
    }
    console.log(`<<< Exiting openModal for ID: ${modalId}`);
}

function closeModal(modalId) {
    console.log('Dashboard Main JS: closeModal called for ID:', modalId);
    const modal = document.getElementById(modalId);

    if (modal && modal.classList.contains('active')) {
        console.log(`Modal ${modalId} - Before closing: classes=${modal.className}`);
        modal.classList.remove('active');

        // Listener for transition end to add 'hidden'
        const handleTransitionEnd = (event) => {
            // Ensure the transition is for the modal overlay itself and for opacity/transform
            if (event.target === modal && (event.propertyName === 'opacity' || event.propertyName === 'transform')) {
                modal.classList.add('hidden');
                console.log(`Modal ${modalId} - AFTER adding 'hidden' (on transitionend): classes=${modal.className}`);
                modal.removeEventListener('transitionend', handleTransitionEnd);
            }
        };
        modal.addEventListener('transitionend', handleTransitionEnd);

        // Fallback timeout in case transitionend doesn't fire (e.g., no transition defined)
        setTimeout(() => {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                modal.removeEventListener('transitionend', handleTransitionEnd); // Clean up listener
                console.warn(`Modal ${modalId} - Added 'hidden' via fallback timeout.`);
            }
        }, 300); // Slightly longer than transition duration (0.2s)

    } else if (modal) {
        // If called but modal wasn't active, ensure it's hidden
        console.log(`Modal ${modalId} - closeModal called, but modal was not active. Current classes: ${modal.className}`);
        modal.classList.add('hidden');
        modal.classList.remove('active'); // Ensure active is removed
    } else {
        console.error(`Modal ${modalId} not found in closeModal.`);
    }

    // If closing profile modal, ensure we reset to the main profile view
    if (modalId === 'profile-modal' && typeof showProfileView === 'function') {
        showProfileView(); // Defined in profile.js
    }
}

function escapeHTML(str) {
    if (str === null || typeof str === 'undefined') return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showButtonSpinner(button, show = true) {
    if (!button) return;
    // Find spinner within button OR potentially next to it (like bulk action buttons)
    const spinner = button.querySelector('.save-spinner, .spinner, .forgot-spinner, .verify-spinner, .reset-spinner, .button-spinner .spinner, .regenerate-spinner, .update-spinner') || button.nextElementSibling?.querySelector('.spinner');
    const textElement = button.querySelector('.button-text') || button; // Fallback to button itself for text

    if (spinner) {
        const spinnerContainer = spinner.closest('.button-spinner, .spinner'); // Find the container to show/hide
        if (spinnerContainer) {
            spinnerContainer.style.display = show ? 'inline-block' : 'none';
        } else {
            spinner.style.display = show ? 'inline-block' : 'none'; // Fallback if no specific container
        }
    }

    // Hide/Show text content within the button if structure allows
    // Assumes text is wrapped or is the main button content
    if (textElement && spinner) { // Only hide text if a spinner is found
         // Find direct text node children or specific span
         let textNode = Array.from(button.childNodes).find(node => node.nodeType === 3 && node.textContent.trim() !== '');
         let buttonTextSpan = button.querySelector('.button-text');

         if (buttonTextSpan) {
             buttonTextSpan.style.display = show ? 'none' : 'inline-flex'; // Hide span if spinner shown
         } else if (textNode) {
             // This is trickier, maybe wrap text node temporarily? Or just rely on spinner covering it.
             // Simplest: don't hide raw text node, rely on spinner appearing next to it.
         }
    }

    button.disabled = show;
}

function setModalFeedback(modalId, message, isSuccess) {
    const feedback = document.getElementById(`modal-feedback-${modalId.replace('-modal', '')}`);
    if (feedback) {
        feedback.textContent = message;
        // Reset classes first
        feedback.className = 'text-xs text-right mr-auto h-4';
        // Add success/error class
        if (message) { // Only add color class if there's a message
            feedback.classList.add(isSuccess ? 'text-green-600' : 'text-red-600');
        }
        // Clear after timeout
        setTimeout(() => {
            if (feedback && feedback.textContent === message) { // Check if message is still the same
                 feedback.textContent = '';
                 feedback.className = 'text-xs text-right mr-auto h-4'; // Reset class list
            }
        }, 4000);
    } else {
        // console.warn(`Feedback element not found for modal ${modalId}`);
    }
}