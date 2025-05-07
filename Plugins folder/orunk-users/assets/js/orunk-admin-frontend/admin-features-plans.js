// orunk-users/assets/js/orunk-admin-frontend/admin-features-plans.js
(function() {
    const app = window.orunkAdminAppState;
    const dom = window.orunkAdminDOMElements;
    const utils = window.orunkAdminUtils;

    if (!app || !dom || !utils) {
        console.error("Orunk Admin Features & Plans: Core app, DOM, or utils not found on window object.");
        return;
    }

    window.orunkAdminApp = window.orunkAdminApp || {};
    window.orunkAdminApp.features = {
        init: function() {
            dom.addNewFeatureBtn?.addEventListener('click', () => this.openEditModal(0));
            dom.featureEditForm?.addEventListener('submit', (e) => this.handleSaveSubmit(e));

            dom.featuresPlansContainer?.addEventListener('click', (event) => {
                const targetButton = event.target.closest('button');
                if (!targetButton) return;

                if (targetButton.classList.contains('edit-feature-action-btn')) {
                    this.openEditModal(targetButton.dataset.featureId);
                } else if (targetButton.classList.contains('delete-feature-action-btn')) {
                    this.handleDelete(targetButton.dataset.featureId, targetButton.dataset.featureName);
                } else if (targetButton.classList.contains('add-plan-action-btn')) {
                    if (window.orunkAdminApp.plans) window.orunkAdminApp.plans.openEditModal(targetButton.dataset.featureKey, 0);
                } else if (targetButton.classList.contains('edit-plan-action-btn')) {
                    if (window.orunkAdminApp.plans) window.orunkAdminApp.plans.openEditModal(targetButton.dataset.featureKey, targetButton.dataset.planId);
                } else if (targetButton.classList.contains('delete-plan-action-btn')) {
                    if (window.orunkAdminApp.plans) window.orunkAdminApp.plans.handleDelete(targetButton.dataset.planId, targetButton.dataset.planName);
                }
            });
        },

        fetchList: function() {
            if (!dom.featuresPlansContainer) return;
            utils.showLoadingPlaceholder(dom.featuresPlansContainer, 'Loading features & plans...');
            utils.makeAjaxCall('orunk_admin_get_features_plans', {})
                .then(data => {
                    app.currentFeaturesData = data.features || []; // Update global state
                    this.renderTable(app.currentFeaturesData);
                })
                .catch(error => utils.showErrorInContainer(dom.featuresPlansContainer, `Error loading features & plans: ${error.message}.`));
        },

        renderTable: function(features) {
            if (!dom.featuresPlansContainer) return;
            if (!features || features.length === 0) {
                dom.featuresPlansContainer.innerHTML = '<div class="p-4 text-center text-gray-500">No features defined yet. Click "Add New Feature" to begin.</div>';
                return;
            }
            let html = '<div class="space-y-6">';
            features.forEach(feature => {
                const categoryObj = app.currentCategoriesData.find(c => c.category_slug === feature.category);
                const categoryName = categoryObj ? categoryObj.category_name : (feature.category || 'Uncategorized');
                const requiresLicenseBadge = feature.requires_license == '1' ? `<span class="text-xs font-medium bg-green-100 text-green-700 px-2 py-0.5 rounded-full border border-green-200" title="License key generated on purchase"><i class="fas fa-check-circle mr-1"></i>License Required</span>` : `<span class="text-xs font-medium bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full border border-gray-200"><i class="fas fa-times-circle mr-1"></i>No License</span>`;

                html += `
                    <div class="card" id="feature-card-${feature.id}">
                        <div class="card-header !bg-white">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <i class="fas fa-cube text-lg text-indigo-500"></i>
                                    <h3 class="text-lg font-semibold text-gray-800">${utils.escapeHTML(feature.product_name)}</h3>
                                    <code class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded border border-gray-200">${utils.escapeHTML(feature.feature)}</code>
                                    <span class="text-xs font-medium bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full border border-blue-200">${utils.escapeHTML(categoryName)}</span>
                                    ${requiresLicenseBadge}
                                </div>
                                <p class="text-sm text-gray-600 mt-1 ml-8">${utils.escapeHTML(feature.description || 'No description.')}</p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button data-feature-id="${feature.id}" class="edit-feature-action-btn button button-secondary button-sm"><i class="fas fa-pencil-alt"></i> <span class="button-text-label">Edit</span></button>
                                <button data-feature-id="${feature.id}" data-feature-name="${utils.escapeHTML(feature.product_name)}" class="delete-feature-action-btn button button-danger button-sm"><i class="fas fa-trash-alt"></i> <span class="button-text-label">Delete</span></button>
                                <button data-feature-key="${feature.feature}" class="add-plan-action-btn button button-primary button-sm"><i class="fas fa-plus"></i> <span class="button-text-label">Add Plan</span></button>
                            </div>
                        </div>
                        <div class="card-body !pt-0 !px-0">${this.renderPlansForFeatureTable(feature.plans, feature.feature)}</div>
                    </div>`;
            });
            html += '</div>';
            dom.featuresPlansContainer.innerHTML = html;
        },

        renderPlansForFeatureTable: function(plans, featureKey) {
            if (!plans || plans.length === 0) return '<div class="px-6 py-4 text-sm text-center text-gray-500 italic">No plans defined for this feature.</div>';
            let tableHtml = `<div class="overflow-x-auto"><table class="data-table"><thead><tr>
                <th>Name</th><th>Price</th><th>Type</th><th>Duration</th><th>API Limits (D/M)</th><th>Activation Limit</th><th>Status</th><th>Gateway IDs</th><th>Actions</th>
                </tr></thead><tbody class="divide-y divide-gray-200">`;
            plans.forEach(plan => {
                const statusBadge = `<span class="status-badge ${plan.is_active == '1' ? 'active' : 'inactive'}">${plan.is_active == '1' ? 'Active' : 'Inactive'}</span>`;
                const paymentType = plan.is_one_time == '1' ? '<span title="One-Time Payment" class="text-purple-700 font-medium">One-Time</span>' : '<span title="Subscription" class="text-blue-700">Subscription</span>';
                const durationDisplay = plan.is_one_time == '1' ? 'Lifetime' : `${utils.escapeHTML(plan.duration_days)} days`;
                const activationLimit = plan.activation_limit;
                const activationLimitDisplay = (activationLimit === null || activationLimit === '' || parseInt(activationLimit, 10) === 0) ? 'Unlimited' : utils.escapeHTML(activationLimit);
                const paypalId = plan.paypal_plan_id ? `<span class="block text-xs text-gray-500" title="PayPal Plan ID">PP: ${utils.escapeHTML(plan.paypal_plan_id)}</span>` : '';
                const stripeId = plan.stripe_price_id ? `<span class="block text-xs text-gray-500" title="Stripe Price ID">ST: ${utils.escapeHTML(plan.stripe_price_id)}</span>` : '';
                const gatewayIds = (paypalId || stripeId) ? `${paypalId}${paypalId && stripeId ? '' : ''}${stripeId}` : '<span class="text-xs text-gray-400 italic">None</span>';

                tableHtml += `
                    <tr id="plan-row-${plan.id}">
                        <td class="font-medium text-gray-900 py-3 px-4">${utils.escapeHTML(plan.plan_name)}</td>
                        <td class="text-gray-700 py-3 px-4">$${utils.escapeHTML(parseFloat(plan.price).toFixed(2))}</td>
                        <td class="text-sm py-3 px-4">${paymentType}</td>
                        <td class="py-3 px-4">${durationDisplay}</td>
                        <td class="py-3 px-4">${utils.escapeHTML(plan.requests_per_day ?? 'N/A')} / ${utils.escapeHTML(plan.requests_per_month ?? 'N/A')}</td>
                        <td class="py-3 px-4">${activationLimitDisplay}</td>
                        <td class="py-3 px-4">${statusBadge}</td>
                        <td class="py-3 px-4">${gatewayIds}</td>
                        <td class="space-x-1 whitespace-nowrap py-3 px-4">
                            <button data-plan-id="${plan.id}" data-feature-key="${featureKey}" class="edit-plan-action-btn button button-link button-sm !text-xs"><i class="fas fa-pencil-alt mr-1"></i>Edit</button>
                            <button data-plan-id="${plan.id}" data-plan-name="${utils.escapeHTML(plan.plan_name)}" class="delete-plan-action-btn button button-link-danger button-sm !text-xs"><i class="fas fa-trash-alt mr-1"></i>Delete</button>
                        </td>
                    </tr>`;
            });
            tableHtml += `</tbody></table></div>`;
            return tableHtml;
        },

        openEditModal: function(featureId = 0) {
            const modal = utils.openModal('feature-edit');
            if (!modal) return;
            const form = dom.featureEditForm;
            const titleEl = modal.querySelector('#feature-edit-modal-title');
            const keyInput = form.querySelector('#edit-feature-key');
            const categorySelect = form.querySelector('#edit-feature-category');
            const requiresLicenseCheckbox = form.querySelector('#edit-requires-license');
            const downloadUrlInput = form.querySelector('#edit-feature-download-url');
            const downloadLimitInput = form.querySelector('#edit-feature-download-limit');

            // *** NEW: Get references to static metric input fields (ensure these IDs exist in your modal HTML) ***
            const staticRatingInput = form.querySelector('#edit-feature-static-rating');
            const staticReviewsCountInput = form.querySelector('#edit-feature-static-reviews-count');
            const staticSalesCountInput = form.querySelector('#edit-feature-static-sales-count');
            const staticDownloadsCountInput = form.querySelector('#edit-feature-static-downloads-count');
            // *** END NEW ***

            categorySelect.innerHTML = '<option value="">-- Select Category --</option>';
            (app.currentCategoriesData || []).forEach(cat => {
                categorySelect.innerHTML += `<option value="${utils.escapeHTML(cat.category_slug)}">${utils.escapeHTML(cat.category_name)}</option>`;
            });

            if (featureId > 0) {
                titleEl.innerHTML = '<i class="fas fa-cube text-indigo-500 mr-2"></i> Edit Feature';
                const feature = app.currentFeaturesData.find(f => f.id == featureId);
                if (feature) {
                    form['edit-feature-id'].value = feature.id;
                    keyInput.value = feature.feature;
                    keyInput.readOnly = true; keyInput.classList.add('bg-gray-100', 'cursor-not-allowed');
                    form['edit-product-name'].value = feature.product_name;
                    form['edit-feature-description'].value = feature.description || '';
                    categorySelect.value = feature.category || "";
                    requiresLicenseCheckbox.checked = (feature.requires_license == '1');
                    if (downloadUrlInput) downloadUrlInput.value = feature.download_url || '';
                    if (downloadLimitInput) downloadLimitInput.value = feature.download_limit_daily !== null && feature.download_limit_daily !== undefined ? feature.download_limit_daily : '5';

                    // *** NEW: Populate static metric fields ***
                    const metrics = feature.static_metrics || {}; // Ensure static_metrics exists
                    if (staticRatingInput) staticRatingInput.value = metrics.rating || '0';
                    if (staticReviewsCountInput) staticReviewsCountInput.value = metrics.reviews_count || '0';
                    if (staticSalesCountInput) staticSalesCountInput.value = metrics.sales_count || '0';
                    if (staticDownloadsCountInput) staticDownloadsCountInput.value = metrics.downloads_count || '0';
                    // *** END NEW ***

                } else {
                    utils.setModalFeedback(modal, 'Error: Could not find feature data.', false);
                    utils.closeModal(modal); return;
                }
            } else {
                titleEl.innerHTML = '<i class="fas fa-cube text-indigo-500 mr-2"></i> Add New Feature';
                form.reset(); // This should clear most fields
                form['edit-feature-id'].value = '0';
                keyInput.readOnly = false; keyInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
                categorySelect.value = "";
                requiresLicenseCheckbox.checked = false;
                if (downloadUrlInput) downloadUrlInput.value = '';
                if (downloadLimitInput) downloadLimitInput.value = '5';

                // *** NEW: Set default values for static metric fields when adding new ***
                if (staticRatingInput) staticRatingInput.value = '0';
                if (staticReviewsCountInput) staticReviewsCountInput.value = '0';
                if (staticSalesCountInput) staticSalesCountInput.value = '0';
                if (staticDownloadsCountInput) staticDownloadsCountInput.value = '0';
                // *** END NEW ***
            }
        },
        handleSaveSubmit: function(event) {
            event.preventDefault();
            const form = dom.featureEditForm;
            const button = form.querySelector('button[type="submit"]');
            utils.showButtonSpinner(button, true, 'Saving...');
            utils.setModalFeedback(dom.featureEditModal, '', true);

            const formData = new FormData(form);
            // Checkbox for requires_license might not be sent if unchecked, so handle it
            if (!form.querySelector('#edit-requires-license').checked) {
                // FormData doesn't send unchecked checkboxes. PHP will handle this.
                // If PHP expects a value for 'requires_license' even when '0',
                // you might need to append it like: formData.append('requires_license', '0');
                // However, the PHP side was already updated to handle (isset($_POST['requires_license']) && $_POST['requires_license'] == '1') ? 1 : 0;
                // so it defaults to 0 if not present or not '1'.
            }
            // The new static metric fields (static_rating, static_reviews_count, etc.)
            // will be included in formData if they have 'name' attributes and values.
            // The PHP handler `handle_admin_save_feature` was updated to expect these.

            utils.makeAjaxCall('orunk_admin_save_feature', {}, 'POST', formData)
                .then(data => {
                    utils.setModalFeedback(dom.featureEditModal, data.message || 'Feature saved!', true);
                    this.fetchList(); // This will re-fetch and re-render, including new static metrics
                    setTimeout(() => utils.closeModal(dom.featureEditModal), 1500);
                })
                .catch(error => utils.setModalFeedback(dom.featureEditModal, error.message || 'Save failed.', false))
                .finally(() => utils.showButtonSpinner(button, false, 'Save Feature'));
        },
        handleDelete: function(featureId, featureName) {
            if (!confirm(`ARE YOU SURE?\n\nDelete the feature "${featureName}" AND all its associated plans?\n\nThis action cannot be undone.`)) return;
            utils.makeAjaxCall('orunk_admin_delete_feature', { feature_id: featureId }, 'POST')
                .then(data => {
                    const featureCard = document.getElementById(`feature-card-${featureId}`);
                    if (featureCard) {
                        featureCard.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        featureCard.style.opacity = '0';
                        featureCard.style.transform = 'scale(0.95)';
                        setTimeout(() => featureCard.remove(), 300);
                    }
                   app.currentFeaturesData = app.currentFeaturesData.filter(f => f.id != featureId);
                   // Re-render the table. If fetchList() was called, it would overwrite app.currentFeaturesData.
                   // If only removing locally, then call renderTable directly.
                   // Calling fetchList() is safer to ensure consistency with backend.
                   this.fetchList();
                })
                .catch(error => alert(`Error deleting feature: ${error.message}`));
        },
        populateCategoryDropdown: function(categories) {
            const selectElement = dom.featureEditForm?.querySelector('#edit-feature-category');
            if (!selectElement) return;
            const currentValue = selectElement.value; // Preserve current selection if possible
            selectElement.innerHTML = '<option value="">-- Select Category --</option>';
            if (!categories || categories.length === 0) {
                selectElement.innerHTML += '<option value="" disabled>No categories available</option>'; return;
            }
            categories.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat.category_slug;
                option.textContent = cat.category_name;
                if (cat.category_slug === currentValue) option.selected = true;
                selectElement.appendChild(option);
            });
        }
    };

    // Plan-specific logic (nested or as a separate part of app.features if preferred)
    window.orunkAdminApp.plans = {
        openEditModal: function(featureKey, planId = 0) {
            const modal = utils.openModal('plan-edit');
            if (!modal) return;
            const form = dom.planEditForm;
            const titleEl = modal.querySelector('#plan-edit-modal-title');
            form['edit-plan-feature-key'].value = featureKey;

            form.reset();
            form['edit-plan-id'].value = '0';
            form['edit-plan-feature-key'].value = featureKey;
            form.querySelector('input[name="is_one_time_radio"][value="0"]').checked = true; // Default to subscription
            form['edit-is-one-time'].value = '0'; // Hidden input
            form['edit-plan-is-active'].checked = true;
            form['edit-plan-duration-days'].value = '30'; // Default duration
            // Call handler to set initial state of duration field based on payment type
            this.handlePaymentTypeChange(form.querySelector('input[name="is_one_time_radio"][value="0"]'), form);


            if (planId > 0) {
                titleEl.innerHTML = `<i class="fas fa-tags text-indigo-500 mr-2"></i> Edit Plan for ${utils.escapeHTML(featureKey)}`;
                const feature = app.currentFeaturesData.find(f => f.feature === featureKey);
                const plan = feature?.plans?.find(p => p.id == planId);
                if (plan) {
                    form['edit-plan-id'].value = plan.id;
                    form['edit-plan-name'].value = plan.plan_name;
                    form['edit-plan-description'].value = plan.description || '';
                    form['edit-plan-price'].value = parseFloat(plan.price).toFixed(2);
                    form['edit-plan-duration-days'].value = plan.duration_days;
                    form['edit-plan-req-day'].value = plan.requests_per_day ?? '';
                    form['edit-plan-req-month'].value = plan.requests_per_month ?? '';
                    form['edit-plan-activation-limit'].value = plan.activation_limit ?? '';
                    form['edit-plan-is-active'].checked = (plan.is_active == '1');
                    form['edit-paypal-plan-id'].value = plan.paypal_plan_id || '';
                    form['edit-stripe-price-id'].value = plan.stripe_price_id || '';

                    const isOneTime = plan.is_one_time == '1';
                    form.querySelector(`input[name="is_one_time_radio"][value="${isOneTime ? '1' : '0'}"]`).checked = true;
                    form['edit-is-one-time'].value = isOneTime ? '1' : '0'; // Update hidden input
                    // Call handler to set initial state of duration field
                    this.handlePaymentTypeChange(form.querySelector(`input[name="is_one_time_radio"][value="${isOneTime ? '1' : '0'}"]`), form);

                } else {
                    utils.setModalFeedback(modal, 'Error: Could not find plan data.', false);
                    utils.closeModal(modal); return;
                }
            } else {
                titleEl.innerHTML = `<i class="fas fa-tags text-indigo-500 mr-2"></i> Add New Plan for ${utils.escapeHTML(featureKey)}`;
                // Ensure defaults are set for new plan, handled by form.reset() and specific assignments above
            }
        },
        handleSaveSubmit: function(event) {
            event.preventDefault();
            const form = dom.planEditForm;
            const button = form.querySelector('button[type="submit"]');
            utils.showButtonSpinner(button, true, 'Saving...');
            utils.setModalFeedback(dom.planEditModal, '', true);

            const formData = new FormData(form);
            if (!form.querySelector('#edit-plan-is-active').checked) {
                // FormData doesn't send unchecked checkboxes. PHP will handle this.
                // Or: formData.append('is_active', '0');
            }
            formData.delete('is_one_time_radio'); // Remove the radio button value, use the hidden input

            utils.makeAjaxCall('orunk_admin_save_plan', {}, 'POST', formData)
                .then(data => {
                    utils.setModalFeedback(dom.planEditModal, data.message || 'Plan saved!', true);
                    if (app.features) app.features.fetchList(); // Refresh the features and their plans
                    setTimeout(() => utils.closeModal(dom.planEditModal), 1500);
                })
                .catch(error => utils.setModalFeedback(dom.planEditModal, error.message || 'Save failed.', false))
                .finally(() => utils.showButtonSpinner(button, false, 'Save Plan'));
        },
        handleDelete: function(planId, planName) {
            if (!confirm(`Are you sure you want to delete the plan "${planName}"?`)) return;
            utils.makeAjaxCall('orunk_admin_delete_plan', { plan_id: planId }, 'POST')
                .then(data => {
                    if (app.features) app.features.fetchList(); // Refresh features to update plans list
                })
                .catch(error => alert(`Error deleting plan: ${error.message}`));
        },
        handlePaymentTypeChange: function(radioElement, form) {
            const isOneTime = radioElement.value === '1';
            const durationInput = form.querySelector('#edit-plan-duration-days');
            const durationWrapper = form.querySelector('#edit-plan-duration-wrapper'); // Assuming this is the div wrapping the duration input
            const hiddenInputIsOneTime = form.querySelector('#edit-is-one-time'); // Hidden input to store '0' or '1'

            if (durationInput && durationWrapper) {
                if (isOneTime) {
                    // Store current value if not already "lifetime" and input is writable
                    if (durationInput.value !== '9999' && !durationInput.readOnly && durationInput.dataset.originalValue === undefined) {
                        durationInput.dataset.originalValue = durationInput.value;
                    }
                    durationInput.value = '9999';
                    durationInput.readOnly = true;
                    durationWrapper.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    // Restore original value if stored, or default to 30
                    durationInput.value = durationInput.dataset.originalValue || '30';
                    delete durationInput.dataset.originalValue; // Clear after restoring
                    durationInput.readOnly = false;
                    durationWrapper.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }
            if(hiddenInputIsOneTime) hiddenInputIsOneTime.value = isOneTime ? '1' : '0';
        }
    };
    // Initialize payment type change listener for plan modal
    dom.planEditForm?.querySelectorAll('input[name="is_one_time_radio"]').forEach(radio => {
        radio.addEventListener('change', (event) => {
            if (window.orunkAdminApp.plans) {
                window.orunkAdminApp.plans.handlePaymentTypeChange(event.target, dom.planEditForm);
            }
        });
    });
    dom.planEditForm?.addEventListener('submit', (e) => {
        if(window.orunkAdminApp.plans) window.orunkAdminApp.plans.handleSaveSubmit(e);
    });

})();