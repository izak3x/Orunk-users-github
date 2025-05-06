/**
 * Orunk User Dashboard - Billing Script
 * Handles Billing card display and Billing modal interactions.
 */

// Uses functions from main.js: openModal, closeModal, showButtonSpinner, setModalFeedback, escapeHTML
// Uses global variable: currentBillingAddress (let), orunkDashboardData

function openBillingModal() {
    // Only open if button is not disabled
    const billingBtn = document.getElementById('manage-address-btn');
    if (billingBtn && !billingBtn.disabled) {
        openModal('billing-modal');
    }
}

function fetchBillingAddress() {
    const displayDiv = document.getElementById('billing-address-display');
    const button = document.getElementById('manage-address-btn');
    if (!displayDiv || !button) {
        console.error("Billing display or button not found");
        return;
    }

    // Show skeleton loader
    displayDiv.innerHTML = '<div class="space-y-1 animate-pulse"><div class="h-3 bg-gray-200 rounded w-3/4 skeleton"></div><div class="h-3 bg-gray-200 rounded w-full skeleton"></div><div class="h-3 bg-gray-200 rounded w-1/2 skeleton"></div></div>';
    button.disabled = true;

    const formData = new FormData();
    formData.append('action', 'orunk_get_billing_address');
    formData.append('nonce', orunkDashboardData.billingNonce); // Use global data

    fetch(orunkDashboardData.ajaxUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data.address) {
            currentBillingAddress = data.data.address; // Store globally
            displayFormattedAddress(displayDiv, currentBillingAddress);
            button.disabled = false; // Enable button after successful fetch
        } else {
            currentBillingAddress = null;
            console.error('Failed to fetch billing address:', data.data?.message || 'Unknown error');
            displayDiv.innerHTML = '<p class="text-gray-500 text-xs italic">Could not load billing address.</p>';
            button.disabled = false; // Enable button even on failure
        }
    })
    .catch(error => {
        currentBillingAddress = null;
        console.error('Error fetching billing address:', error);
        displayDiv.innerHTML = '<p class="text-red-500 text-xs italic">Error loading address.</p>';
        button.disabled = false; // Enable button on error
    });
}

function displayFormattedAddress(element, address) {
    if (!element) return;
    if (!address || typeof address !== 'object' || Object.keys(address).every(k => !address[k])) {
        element.innerHTML = '<p class="text-gray-500 text-xs italic">No billing address on file.</p>';
        return;
    }

    let displayHTML = '';
    let hasAddress = false;

    // Name
    if (address.billing_first_name || address.billing_last_name) {
        displayHTML += `<p><strong>${escapeHTML(address.billing_first_name || '')} ${escapeHTML(address.billing_last_name || '')}</strong></p>`;
        hasAddress = true;
    }
    // Address Lines
    if (address.billing_address_1) {
        displayHTML += `<p>${escapeHTML(address.billing_address_1)}</p>`;
        hasAddress = true;
    }
    if (address.billing_address_2) {
        displayHTML += `<p>${escapeHTML(address.billing_address_2)}</p>`;
        hasAddress = true;
    }
    // City, State, Zip line
    let cityStateZip = '';
    if (address.billing_city) cityStateZip += escapeHTML(address.billing_city);
    if (address.billing_city && address.billing_state) cityStateZip += ', ';
    if (address.billing_state) cityStateZip += escapeHTML(address.billing_state);
    if (cityStateZip && address.billing_postcode) cityStateZip += ' ';
    if (address.billing_postcode) cityStateZip += escapeHTML(address.billing_postcode);
    if (cityStateZip) {
        displayHTML += `<p>${cityStateZip}</p>`;
        hasAddress = true;
    }
    // Country
    if (address.billing_country) {
        displayHTML += `<p>${escapeHTML(address.billing_country)}</p>`;
        hasAddress = true;
    }
    // Phone
    if (address.billing_phone) {
        displayHTML += `<p><i class="fas fa-phone-alt text-xs opacity-60 mr-1"></i>${escapeHTML(address.billing_phone)}</p>`;
        hasAddress = true;
    }

    // Update display area
    element.innerHTML = hasAddress ? displayHTML : '<p class="text-gray-500 text-xs italic">No billing address on file.</p>';
}

function populateBillingForm(form, address) {
    if (!form || !address || typeof address !== 'object') return;
    const keys = [
        'first_name', 'last_name', 'company', 'address_1', 'address_2',
        'city', 'state', 'postcode', 'country', 'phone', 'email' // Include email if it's part of the form
    ];
    keys.forEach(key => {
        const inputName = `billing_${key}`;
        if (form.elements[inputName]) {
            form.elements[inputName].value = address[inputName] || '';
        }
    });
}

function handleBillingSave(event) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');
    showButtonSpinner(button, true);
    setModalFeedback('billing-modal', '', true); // Clear previous feedback

    const formData = new FormData(form);
    formData.append('action', 'orunk_save_billing_address');
    formData.append('nonce', orunkDashboardData.billingNonce);

    fetch(orunkDashboardData.ajaxUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            setModalFeedback('billing-modal', data.message || 'Address saved!', true);
            fetchBillingAddress(); // Refresh display in the card
            setTimeout(() => closeModal('billing-modal'), 1500);
        } else {
            setModalFeedback('billing-modal', data.data?.message || 'Save failed.', false);
        }
    })
    .catch(error => {
        console.error('Billing Save Error:', error);
        setModalFeedback('billing-modal', 'An error occurred saving the address.', false);
    })
    .finally(() => {
        showButtonSpinner(button, false);
    });
}