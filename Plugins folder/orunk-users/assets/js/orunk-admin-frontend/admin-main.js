// orunk-users/assets/js/orunk-admin-frontend/admin-main.js

// Ensure orunkAdminGlobalData is available from the PHP template
if (typeof orunkAdminGlobalData === 'undefined') {
    console.error('Orunk Admin Fatal Error: Global data (orunkAdminGlobalData) with ajaxUrl and nonce is not defined. Ensure it is localized in your PHP template.');
    // Stop further script execution if critical data is missing.
    throw new Error("Orunk Admin critical data (orunkAdminGlobalData) missing.");
}

// --- Define Global Namespaces and Core Objects IMMEDIATELY ---
window.orunkAdminAppState = {
    currentFeaturesData: [],
    currentGatewaysData: [],
    currentUsersData: [],
    currentCategoriesData: [],
    activeTab: 'users-purchases', // Default active tab
    modals: {} // To store modal DOM elements
};

window.orunkAdminDOMElements = { // Initial population with getElementById
    userListContainer: document.getElementById('users-list-container'),
    featuresPlansContainer: document.getElementById('features-plans-list-container'),
    paymentGatewaysContainer: document.getElementById('payment-gateways-list-container'),
    featureCategoriesContainer: document.getElementById('feature-categories-list-container'),
    userSearchInput: document.getElementById('user-search-input'),
    addNewFeatureBtn: document.getElementById('add-new-feature-btn'),
    addCategoryForm: document.getElementById('orunk-add-category-form'),
    userPurchasesModal: document.getElementById('user-purchases-modal'),
    featureEditModal: document.getElementById('feature-edit-modal'),
    planEditModal: document.getElementById('plan-edit-modal'),
    categoryEditModal: document.getElementById('category-edit-modal'),
    gatewaySettingsModal: document.getElementById('gateway-settings-modal'),
    featureEditForm: document.getElementById('feature-edit-form'),
    planEditForm: document.getElementById('plan-edit-form'),
    categoryEditForm: document.getElementById('category-edit-form'),
    gatewaySettingsForm: document.getElementById('gateway-settings-form'),
    tabButtons: null, // Will be populated by querySelectorAll on DOMContentLoaded
    tabContents: null // Will be populated by querySelectorAll on DOMContentLoaded
};

// Populate modals into appState (can be done here as IDs are known)
orunkAdminAppState.modals = {
    'user-purchases': orunkAdminDOMElements.userPurchasesModal,
    'feature-edit': orunkAdminDOMElements.featureEditModal,
    'plan-edit': orunkAdminDOMElements.planEditModal,
    'category-edit': orunkAdminDOMElements.categoryEditModal,
    'gateway-settings': orunkAdminDOMElements.gatewaySettingsModal
};

window.orunkAdminUtils = {
    ajaxUrl: orunkAdminGlobalData.ajaxUrl,
    adminNonce: orunkAdminGlobalData.nonce,

    makeAjaxCall: function (action, params = {}, method = 'POST', body = null) {
        return new Promise((resolve, reject) => {
            this.showGlobalLoading(true);
            let dataToSend;
            let url = this.ajaxUrl;
            const effectiveMethod = method.toUpperCase();

            if (effectiveMethod === 'POST') {
                dataToSend = (body instanceof FormData) ? body : new FormData();
                if (!dataToSend.has('action') && action) dataToSend.append('action', action);
                if (!dataToSend.has('nonce')) dataToSend.append('nonce', this.adminNonce);
                if (!(body instanceof FormData)) {
                    for (const key in params) { dataToSend.append(key, params[key]); }
                }
            } else { // GET
                const urlParams = new URLSearchParams();
                urlParams.append('action', action);
                urlParams.append('nonce', this.adminNonce);
                for (const key in params) { urlParams.append(key, params[key]); }
                url = `${this.ajaxUrl}?${urlParams.toString()}`;
                dataToSend = null;
            }

            fetch(url, { method: effectiveMethod, body: dataToSend })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            try {
                                const errData = JSON.parse(text);
                                throw new Error(errData.data?.message || errData.message || `HTTP error ${response.status}: ${response.statusText}`);
                            } catch (e) {
                                throw new Error(`HTTP error ${response.status}: ${text || response.statusText}`);
                            }
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        resolve(data.data);
                    } else {
                        reject(new Error(data.data?.message || data.message || 'AJAX error: Operation failed.'));
                    }
                })
                .catch(error => {
                    console.error(`AJAX Error for action '${action}':`, error);
                    reject(error);
                })
                .finally(() => {
                    this.showGlobalLoading(false);
                });
        });
    },
    showGlobalLoading: function(isLoading) {
        const globalLoader = document.getElementById('orunk-global-loader'); // You might want to add this element to your PHP template
        if (globalLoader) globalLoader.style.display = isLoading ? 'flex' : 'none';
        // else console.log(isLoading ? "Global loading START..." : "Global loading END.");
    },
    showLoadingPlaceholder: function(containerElement, message = "Loading...") {
        if (containerElement) {
            containerElement.innerHTML = `<div class="loading-placeholder flex justify-center items-center p-8 text-gray-500"><div class="loading-spinner mr-2"></div> ${this.escapeHTML(message)}</div>`;
        }
    },
    showErrorInContainer: function(containerElement, message) {
        if (containerElement) {
            containerElement.innerHTML = `<div class="p-4 text-red-700 bg-red-100 border border-red-300 rounded flex items-start gap-2"><i class="fas fa-exclamation-circle mt-0.5 text-red-500"></i><div>${this.escapeHTML(message)}</div></div>`;
        }
    },
    openModal: function(modalKey) {
        const modalElement = orunkAdminAppState.modals[modalKey];
        if (modalElement) {
            modalElement.classList.add('active');
            const form = modalElement.querySelector('form');
            if (form) form.reset(); // Reset form on open for a clean state

            const feedbackDiv = modalElement.querySelector('[id$="-feedback"]'); // General selector for feedback divs
            if (feedbackDiv) {
                feedbackDiv.textContent = '';
                feedbackDiv.className = 'text-sm text-right mr-auto h-5'; // Reset class
            }
            const firstFocusable = modalElement.querySelector('input:not([type="hidden"]), select, textarea, button:not(.modal-close-btn)');
            if(firstFocusable) setTimeout(() => firstFocusable.focus(), 50); // Slight delay for transition
            return modalElement;
        }
        console.warn("Modal not found for key:", modalKey);
        return null;
    },
    closeModal: function(modalKeyOrElement) {
        const modalElement = typeof modalKeyOrElement === 'string' ? orunkAdminAppState.modals[modalKeyOrElement] : modalKeyOrElement;
        if (modalElement) {
            modalElement.classList.remove('active');
        }
    },
    setModalFeedback: function(modalElement, message, isSuccess) {
        const feedbackDiv = modalElement?.querySelector('[id$="-feedback"]');
        if (feedbackDiv) {
            feedbackDiv.textContent = message;
            feedbackDiv.className = `text-sm text-right mr-auto h-5 ${isSuccess ? 'text-green-600' : 'text-red-600'}`;
            setTimeout(() => {
                if (feedbackDiv.textContent === message) { // Clear only if message hasn't changed
                    feedbackDiv.textContent = '';
                    feedbackDiv.className = 'text-sm text-right mr-auto h-5';
                }
            }, 4000);
        }
    },
    showButtonSpinner: function(button, show = true, loadingText = null) {
        if (!button) return;
        const spinner = button.querySelector('.save-spinner, .update-spinner, .spinner-inline');
        const buttonTextSpan = button.querySelector('.button-text-label');

        if (spinner) {
            spinner.classList.toggle('hidden', !show);
        }

        if (buttonTextSpan) { // If a dedicated span for text exists
            if (show) {
                if (button.dataset.originalText === undefined) { // Store original text only once
                    button.dataset.originalText = buttonTextSpan.textContent;
                }
                buttonTextSpan.textContent = loadingText || ''; // Show loading text or make it empty
            } else {
                buttonTextSpan.textContent = button.dataset.originalText || ''; // Restore original text
            }
        } else { // Fallback for buttons without a specific text span
            if (show) {
                if (button.dataset.originalHTML === undefined) {
                    button.dataset.originalHTML = button.innerHTML;
                }
                let spinnerHTML = '';
                if(spinner) { // If spinner element was found inside the button
                    spinner.classList.remove('hidden'); // Ensure it's visible
                    spinnerHTML = spinner.outerHTML;
                } else { // Create a generic spinner if none was found
                    spinnerHTML = '<span class="save-spinner"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>';
                }
                button.innerHTML = `${loadingText || ''} ${spinnerHTML}`;
            } else {
                if (button.dataset.originalHTML !== undefined) {
                    button.innerHTML = button.dataset.originalHTML;
                    // Re-hide the spinner if it was part of the original HTML and now restored
                    const restoredSpinner = button.querySelector('.save-spinner, .update-spinner, .spinner-inline');
                    if(restoredSpinner) restoredSpinner.classList.add('hidden');
                }
            }
        }
        button.disabled = show;
    },
    escapeHTML: function(str) {
        if (str === null || typeof str === 'undefined') return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    },
    generateSlug: function(text) {
        if (!text) return '';
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^\w-]+/g, '')
            .replace(/--+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    },
    debounce: function(func, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }
};

// --- App Initialization and Tab Logic (Inside DOMContentLoaded) ---
document.addEventListener('DOMContentLoaded', function () {
    // Populate DOM elements that require querySelectorAll
    orunkAdminDOMElements.tabButtons = document.querySelectorAll('.tab-button');
    orunkAdminDOMElements.tabContents = document.querySelectorAll('.tab-content');

    // Create a global namespace for our app modules if it doesn't exist
    window.orunkAdminApp = window.orunkAdminApp || {};

    function switchTab(targetTabId) {
        if (!orunkAdminDOMElements.tabButtons || !orunkAdminDOMElements.tabContents) return;
        orunkAdminDOMElements.tabButtons.forEach(button => {
            button.classList.toggle('active', button.dataset.tab === targetTabId);
        });
        orunkAdminDOMElements.tabContents.forEach(content => {
            content.classList.toggle('active', content.id === `tab-content-${targetTabId}`);
        });
        orunkAdminAppState.activeTab = targetTabId;
        loadDataForTab(targetTabId);
    }

    function loadDataForTab(tabId) {
        // These functions are expected to be defined in their respective module files
        // and attached to the window.orunkAdminApp object.
        switch (tabId) {
            case 'users-purchases':
                if (window.orunkAdminApp?.users?.fetchList) window.orunkAdminApp.users.fetchList();
                else console.warn("User module or fetchList not ready for tab:", tabId);
                break;
            case 'features-plans':
                if (window.orunkAdminApp?.features?.fetchList) window.orunkAdminApp.features.fetchList();
                else console.warn("Features module or fetchList not ready for tab:", tabId);

                if (window.orunkAdminApp?.categories?.fetchList) window.orunkAdminApp.categories.fetchList();
                else console.warn("Categories module or fetchList not ready for tab:", tabId);
                break;
            case 'payment-gateways':
                if (window.orunkAdminApp?.gateways?.fetchList) window.orunkAdminApp.gateways.fetchList();
                else console.warn("Gateways module or fetchList not ready for tab:", tabId);
                break;
        }
    }

    function initializeGlobalEventListeners() {
        if (!orunkAdminDOMElements.tabButtons) return;
        orunkAdminDOMElements.tabButtons.forEach(button => {
            button.addEventListener('click', (e) => switchTab(e.currentTarget.dataset.tab));
        });

        // Modal close buttons (generic)
        Object.values(orunkAdminAppState.modals).forEach(modal => {
            if (!modal) return;
            modal.querySelector('.modal-close-btn')?.addEventListener('click', () => orunkAdminUtils.closeModal(modal));
            const cancelBtn = modal.querySelector('.modal-footer .button-secondary, .modal-cancel-btn');
            if(cancelBtn) cancelBtn.addEventListener('click', () => orunkAdminUtils.closeModal(modal));
            modal.addEventListener('click', (event) => { if (event.target === modal) orunkAdminUtils.closeModal(modal); });
        });

        // Initialize modules - they will attach their own specific listeners
        if (window.orunkAdminApp?.users?.init) window.orunkAdminApp.users.init();
        else console.warn("Users module not found or init method missing.");

        if (window.orunkAdminApp?.features?.init) window.orunkAdminApp.features.init();
        else console.warn("Features module not found or init method missing.");
        // Plans are part of features module, or initialize separately if app.plans.init exists
        if (window.orunkAdminApp?.plans?.init) window.orunkAdminApp.plans.init();


        if (window.orunkAdminApp?.categories?.init) window.orunkAdminApp.categories.init();
        else console.warn("Categories module not found or init method missing.");

        if (window.orunkAdminApp?.gateways?.init) window.orunkAdminApp.gateways.init();
        else console.warn("Gateways module not found or init method missing.");
    }

    // --- Main Initialization Call ---
    if (orunkAdminDOMElements.tabButtons && orunkAdminDOMElements.tabButtons.length > 0) {
        initializeGlobalEventListeners();
        loadDataForTab(orunkAdminAppState.activeTab); // Load data for the default active tab
    } else {
        console.warn("Orunk Admin: Tab buttons not found on DOMContentLoaded. Main event listeners not fully initialized.");
    }
});