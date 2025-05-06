/**
 * Orunk User Dashboard - Services Script
 * Handles interactions for WP Downloads, API Services, and Other Services cards,
 * including API/License key actions, plugin downloads, plan modal, and auto-renew.
 */

// Uses functions from main.js: openModal, closeModal, showButtonSpinner, setModalFeedback, escapeHTML, displayMessage
// Uses global data: orunkDashboardData, orunkAvailablePlans, orunkAllPurchases, orunkCancelNonces, orunkSwitchNonces

function handleCopyKey(button) {
    const fullKey = button.dataset.fullKey;
    if (!fullKey) return;

    navigator.clipboard.writeText(fullKey).then(() => {
        const originalIconHTML = button.innerHTML;
        const iconClass = button.closest('.license-key-display') ? 'fa-key' : 'fa-copy'; // Adjust icon based on context if needed

        button.innerHTML = '<i class="fas fa-check text-green-500 m-0"></i>';
        button.disabled = true;
        button.title = "Copied!";
        setTimeout(() => {
            if (document.body.contains(button)) { // Check if button still exists
                 button.innerHTML = `<i class="far ${iconClass}"></i>`; // Restore original icon based on context
                 button.disabled = false;
                 button.title = button.closest('.license-key-display') ? 'Copy License Key' : 'Copy API Key';
            }
        }, 1500);
    }).catch(err => {
        console.error('Failed to copy key: ', err);
        // Optionally show an error message to the user
        displayMessage('error', 'Could not copy key to clipboard.');
    });
}

function handleRegenerateKey(button) {
    const purchaseId = button.dataset.purchaseId;
    const feedbackDiv = document.getElementById(`regenerate-feedback-${purchaseId}`);
    const spinner = button.querySelector('.regenerate-spinner');

    if (!purchaseId || !confirm('Are you sure? The old API key will stop working immediately.')) return;

    if (feedbackDiv) {
        feedbackDiv.textContent = '';
        feedbackDiv.className = 'text-xs mt-1 h-4'; // Reset feedback class
    }
    showButtonSpinner(button, true); // Show spinner on button itself

    const formData = new FormData();
    formData.append('action', 'orunk_regenerate_api_key');
    formData.append('nonce', orunkDashboardData.regenNonce);
    formData.append('purchase_id', purchaseId);

    fetch(orunkDashboardData.ajaxUrl, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (feedbackDiv) {
                    feedbackDiv.textContent = data.message || 'Key regenerated!';
                    feedbackDiv.classList.add('text-green-600');
                }
                // Update the displayed key and copy button data
                const displayDiv = document.getElementById(`api-key-display-${purchaseId}`);
                const copyButton = button.closest('.flex').querySelector('.orunk-copy-button');
                if (displayDiv && data.data?.masked_key) displayDiv.textContent = data.data.masked_key;
                if (copyButton && data.data?.new_key) copyButton.dataset.fullKey = data.data.new_key;
            } else {
                if (feedbackDiv) {
                    feedbackDiv.textContent = data.data?.message || 'Error regenerating key.';
                    feedbackDiv.classList.add('text-red-600');
                }
            }
        })
        .catch(error => {
            console.error('Regen key error:', error);
            if (feedbackDiv) {
                feedbackDiv.textContent = 'Request failed.';
                feedbackDiv.classList.add('text-red-600');
            }
        })
        .finally(() => {
            showButtonSpinner(button, false); // Hide spinner
            // Clear feedback message after a delay
            setTimeout(() => {
                if (feedbackDiv) {
                    feedbackDiv.textContent = '';
                    feedbackDiv.className = 'text-xs mt-1 h-4'; // Reset class
                }
            }, 4000);
        });
}

function handlePluginDownload(button) {
    const purchaseId = button.dataset.purchaseId;
    const nonce = button.dataset.nonce;
    const feedbackDiv = document.getElementById(`download-feedback-${purchaseId}`);
    const buttonTextSpan = button.querySelector('.button-text');
    const spinnerSpan = button.querySelector('.button-spinner'); // The container for the spinner div
    const originalButtonTextHTML = buttonTextSpan ? buttonTextSpan.innerHTML : '<i class="fas fa-download mr-1"></i>Download';

    if (!purchaseId || !nonce) {
        console.error('Missing data for download.');
        if (feedbackDiv) { feedbackDiv.textContent = 'Error: Missing data.'; feedbackDiv.className = 'text-xs px-4 pb-2 text-center h-4 text-red-600'; }
        return;
    }

    showButtonSpinner(button, true); // Handles disabling and showing spinner
    button.classList.remove('is-success', 'is-error'); // Reset state classes
    button.classList.add('is-downloading'); // Add downloading state class (optional)

    if (feedbackDiv) {
        feedbackDiv.textContent = 'Preparing download...';
        feedbackDiv.className = 'text-xs px-4 pb-2 text-center h-4 text-gray-500';
    }

    const formData = new FormData();
    formData.append('action', 'orunk_handle_convojet_download'); // Ensure this matches your AJAX hook
    formData.append('nonce', nonce);
    formData.append('purchase_id', purchaseId);

    fetch(orunkDashboardData.ajaxUrl, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.download_url) {
                button.classList.remove('is-downloading');
                button.classList.add('is-success');
                // No need to manually change button text/spinner, showButtonSpinner handles this if structure is correct
                if (feedbackDiv) {
                    feedbackDiv.textContent = 'Download starting...';
                    feedbackDiv.className = 'text-xs px-4 pb-2 text-center h-4 text-green-600';
                }
                // Initiate download
                window.location.href = data.data.download_url;

                // Reset button after a delay
                setTimeout(() => {
                    if(document.body.contains(button)) {
                         showButtonSpinner(button, false); // Restore original state
                         button.classList.remove('is-success');
                         if (feedbackDiv) feedbackDiv.textContent = '';
                    }
                }, 3000);

            } else {
                // Handle download failure
                let errorMsg = data.data?.message || 'Download failed.';
                button.classList.remove('is-downloading');
                button.classList.add('is-error');
                if (feedbackDiv) {
                    feedbackDiv.textContent = errorMsg;
                    feedbackDiv.className = 'text-xs px-4 pb-2 text-center h-4 text-red-600';
                }
                // Alert if limit reached
                if (data.data?.code === 'limit_reached') {
                    alert(errorMsg);
                }
                console.error('Download Error:', data.data);

                // Reset button after a delay
                 setTimeout(() => {
                     if(document.body.contains(button)) {
                          showButtonSpinner(button, false); // Restore original state
                          button.classList.remove('is-error');
                          if (feedbackDiv) feedbackDiv.textContent = '';
                     }
                 }, 4000);
            }
        })
        .catch(error => {
            console.error('Download AJAX Error:', error);
            button.classList.remove('is-downloading');
            button.classList.add('is-error');
            if (feedbackDiv) {
                feedbackDiv.textContent = 'Error preparing download.';
                feedbackDiv.className = 'text-xs px-4 pb-2 text-center h-4 text-red-600';
            }
             // Reset button after a delay
             setTimeout(() => {
                 if(document.body.contains(button)) {
                     showButtonSpinner(button, false); // Restore original state
                     button.classList.remove('is-error');
                     if (feedbackDiv) feedbackDiv.textContent = '';
                 }
             }, 4000);
        });
}


// --- Plan Modal Functions ---
function openPlanChangeModal(button) {
    console.log('Dashboard Services JS: openPlanChangeModal called.');
    const purchaseId = button.dataset.purchaseId;
    const featureKey = button.dataset.featureKey;
    const currentPlanId = button.dataset.currentPlanId;
    const serviceName = button.dataset.serviceName || 'this service';
    const expiryDateDisplay = button.dataset.expiryDateDisplay || 'N/A';
    const autoRenewEnabled = button.dataset.autoRenewEnabled == '1';

    console.log(`Opening Plan Modal - Purchase ID: ${purchaseId}, Feature: ${featureKey}, Current Plan: ${currentPlanId}, Expires: ${expiryDateDisplay}, Renew: ${autoRenewEnabled}`);

    const modal = document.getElementById('plan-modal');
    if (!modal) { console.error("Plan modal element not found!"); return; }

    // Get modal elements
    const modalOptionsContainer = document.getElementById('plan-modal-options');
    const modalForm = document.getElementById('plan-modal-form');
    const confirmBtn = document.getElementById('confirm-plan-change');
    const nonceField = document.getElementById('plan-modal-nonce');
    const currentPlanInfoDiv = document.getElementById('current-plan-info');
    const currentPlanDetailsP = document.getElementById('current-plan-details');
    const cancelPurchaseIdField = document.getElementById('plan-modal-cancel-purchase-id');
    const cancelNonceField = document.getElementById('plan-modal-cancel-nonce');
    const renewalSection = document.getElementById('plan-renewal-section');
    const expiryDateSpan = document.getElementById('plan-modal-expiry-date');
    const autoRenewToggle = document.getElementById('plan-modal-auto-renew-toggle');

    // --- Validate Data ---
    console.log('Global orunkAvailablePlans data:', orunkAvailablePlans); // Use global data
    if (!featureKey || typeof orunkAvailablePlans[featureKey] === 'undefined') {
        console.error(`Error: No feature key provided or no plans found for the key "${featureKey}" in orunkAvailablePlans.`);
        alert('Could not find available plans for this service.');
        return;
    }
    console.log(`Plans found for feature key "${featureKey}":`, orunkAvailablePlans[featureKey]);

    // --- Find current purchase and available plans ---
    const currentPurchase = orunkAllPurchases.find(p => p.id == purchaseId); // Use global data
    // Ensure available plans are filtered correctly (active and not the current one)
    const availablePlans = orunkAvailablePlans[featureKey]
                            ? orunkAvailablePlans[featureKey].filter(p => p.id != currentPlanId && p.is_active == 1)
                            : [];
    const currentPlan = currentPurchase
                            ? (orunkAvailablePlans[featureKey] ? orunkAvailablePlans[featureKey].find(p => p.id == currentPlanId) : null)
                            : null;
    const currentPrice = currentPlan ? parseFloat(currentPlan.price) : 0;

    console.log(`Current Plan Details (if found):`, currentPlan);
    console.log(`Filtered available plans (excluding current plan ID ${currentPlanId}):`, availablePlans);

    // --- Populate Modal ---
    document.getElementById('plan-modal-title').textContent = `Upgrade / Manage Plan`;
    document.getElementById('plan-modal-service-name').textContent = serviceName;
    modalForm.elements['current_purchase_id'].value = purchaseId;
    modalForm.elements['new_plan_id'].value = ''; // Reset selected plan
    nonceField.value = orunkSwitchNonces[purchaseId] || ''; // Use global data
    if (!nonceField.value) console.error(`Switch plan nonce for purchase ${purchaseId} is missing!`);
    cancelPurchaseIdField.value = purchaseId;
    cancelNonceField.value = orunkCancelNonces[purchaseId] || ''; // Use global data
    if (!cancelNonceField.value) console.error(`Cancel nonce for purchase ${purchaseId} is missing!`);
    confirmBtn.disabled = true;

    // Display Current Plan Info
    if (currentPlan && currentPlanDetailsP) {
        const reqDay = currentPlan.requests_per_day ?? 'Unltd';
        const reqMonth = currentPlan.requests_per_month ?? 'Unltd';
        currentPlanDetailsP.innerHTML = `<span class="font-medium">${escapeHTML(currentPlan.plan_name)}</span> - $${currentPrice.toFixed(2)}/mo <span class="text-gray-500 ml-2">(${reqDay}/${reqMonth} req.)</span>`;
        currentPlanInfoDiv.style.display = 'block';
    } else {
        currentPlanInfoDiv.style.display = 'none';
        if(currentPlanDetailsP) currentPlanDetailsP.innerHTML = '';
    }

    // Display Renewal Info
    if (renewalSection && expiryDateSpan && autoRenewToggle) {
        expiryDateSpan.textContent = escapeHTML(expiryDateDisplay);
        autoRenewToggle.checked = autoRenewEnabled;
        autoRenewToggle.dataset.purchaseId = purchaseId; // Ensure toggle has purchase ID
        renewalSection.classList.remove('hidden');
    } else {
        console.error('Could not find renewal section elements in plan modal.');
        if(renewalSection) renewalSection.classList.add('hidden');
    }

    // Build Plan Options HTML
    let optionsHTML = '';
    if (availablePlans.length > 0) {
        optionsHTML = '';
        availablePlans.forEach(plan => {
            const price = parseFloat(plan.price).toFixed(2);
            const priceDiff = parseFloat(plan.price) - currentPrice;
            let priceDiffHtml = '';
            if (priceDiff > 0) priceDiffHtml = `<span class="text-xs text-green-600 ml-1 price-diff">(+$${priceDiff.toFixed(2)})</span>`;
            else if (priceDiff < 0) priceDiffHtml = `<span class="text-xs text-red-600 ml-1 price-diff">(-$${Math.abs(priceDiff).toFixed(2)})</span>`;

            const reqDay = plan.requests_per_day ?? 'Unlimited';
            const reqMonth = plan.requests_per_month ?? 'Unlimited';
            const isOneTime = plan.is_one_time == '1';
            const durationText = isOneTime ? 'Lifetime Access' : `${plan.duration_days} days`;

            let featuresList = '';
            if (featureKey === 'convojet_pro') {
                 featuresList += `<li><i class="fas fa-check"></i> Pro Features</li>`;
                 featuresList += `<li><i class="fas fa-check"></i> ${durationText}</li>`;
             } else if (featureKey.includes('_api') || featureKey.includes('bin')) {
                 featuresList += `<li><i class="fas fa-check"></i> ${reqDay} daily req.</li>`;
                 featuresList += `<li><i class="fas fa-check"></i> ${reqMonth} monthly req.</li>`;
                 featuresList += `<li><i class="fas fa-check"></i> ${durationText}</li>`;
             } else {
                 // Default features for other types
                 featuresList += `<li><i class="fas fa-check"></i> Standard Access</li>`;
                 featuresList += `<li><i class="fas fa-check"></i> ${durationText}</li>`;
             }

            optionsHTML += `<div class="plan-card" data-plan-id="${plan.id}">
                                <div class="plan-header">
                                    <div>
                                        <h4 class="plan-name">${escapeHTML(plan.plan_name)}</h4>
                                        <p class="plan-desc">${escapeHTML(plan.description || '')}</p>
                                    </div>
                                    <div class="plan-pricing">
                                        <span class="price">$${escapeHTML(price)}</span>
                                        <span class="period">${isOneTime ? '/one-time' : '/mo'}</span>
                                        ${priceDiffHtml}
                                    </div>
                                </div>
                                <ul class="plan-features">${featuresList}</ul>
                            </div>`;
        });
    } else {
        optionsHTML = '<p class="text-center text-gray-500 text-sm md:col-span-2 lg:col-span-3">No other plans available for this service.</p>';
    }
    console.log(`Generated optionsHTML:`, optionsHTML); // Debug generated HTML
    modalOptionsContainer.innerHTML = optionsHTML;

    // Finally, open the modal
    openModal('plan-modal'); // Use function from main.js
}

function selectPlanCard(selectedCard) {
    const modalOptionsContainer = document.getElementById('plan-modal-options');
    if(!modalOptionsContainer) return;

    // Remove 'selected' class from all cards
    modalOptionsContainer.querySelectorAll('.plan-card').forEach(card => card.classList.remove('selected'));

    // Add 'selected' class to the clicked card
    selectedCard.classList.add('selected');

    // Update hidden input and enable confirm button
    const selectedPlanId = selectedCard.dataset.planId;
    document.getElementById('plan-modal-selected-plan-id').value = selectedPlanId;
    document.getElementById('confirm-plan-change').disabled = false;
}

function handlePlanChangeConfirm(button) {
    const modalForm = document.getElementById('plan-modal-form');
    if (!modalForm) return;

    const selectedPlanId = modalForm.elements['new_plan_id'].value;
    const currentPurchaseId = modalForm.elements['current_purchase_id'].value;
    const nonceField = modalForm.elements['_wpnonce'];

    if (!selectedPlanId) {
        setModalFeedback('plan-modal', 'Please select a plan.', false); // Use function from main.js
        return;
    }

    if (!nonceField || !nonceField.value) {
        console.error("Plan change nonce is missing!");
        setModalFeedback('plan-modal', 'Security error. Cannot submit. Please refresh and try again.', false); // Use function from main.js
        return;
    }

    console.log(`Submitting plan change form for Purchase ID: ${currentPurchaseId} to Plan ID: ${selectedPlanId}`);
    showButtonSpinner(button, true); // Use function from main.js
    modalForm.submit(); // Submit the form via standard POST
}

// --- Auto Renew Toggle ---
function handleAutoRenewToggle(toggleInput) {
    const purchaseId = toggleInput.dataset.purchaseId;
    const isEnabled = toggleInput.checked ? '1' : '0';
    console.log(`Toggling auto-renew for Purchase ${purchaseId} to ${isEnabled}`);
    toggleInput.disabled = true;

    const formData = new FormData();
    formData.append('action', 'orunk_toggle_auto_renew');
    formData.append('nonce', orunkDashboardData.autoRenewNonce); // Use global data
    formData.append('purchase_id', purchaseId);
    formData.append('enabled', isEnabled);

    fetch(orunkDashboardData.ajaxUrl, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMessage('success', data.data.message || 'Auto-renew status updated.'); // Use function from main.js
                // Optionally update the data in the orunkAllPurchases array if needed
                const purchaseIndex = orunkAllPurchases.findIndex(p => p.id == purchaseId);
                if (purchaseIndex > -1) {
                    orunkAllPurchases[purchaseIndex].auto_renew = isEnabled;
                }
            } else {
                displayMessage('error', data.data?.message || 'Failed to update auto-renew status.'); // Use function from main.js
                toggleInput.checked = !toggleInput.checked; // Revert UI on failure
            }
        })
        .catch(error => {
            console.error('Auto Renew Error:', error);
            displayMessage('error', 'An error occurred while updating auto-renew.'); // Use function from main.js
            toggleInput.checked = !toggleInput.checked; // Revert UI on error
        })
        .finally(() => {
            toggleInput.disabled = false;
        });
}