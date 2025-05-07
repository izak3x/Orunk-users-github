// orunk-users/assets/js/orunk-admin-frontend/admin-payment-gateways.js
(function() {
    const app = window.orunkAdminAppState;
    const dom = window.orunkAdminDOMElements;
    const utils = window.orunkAdminUtils;

    if (!app || !dom || !utils) {
        console.error("Orunk Admin Payment Gateways: Core app, DOM, or utils not found on window object.");
        return;
    }

    window.orunkAdminApp = window.orunkAdminApp || {};
    window.orunkAdminApp.gateways = {
        init: function() {
            dom.gatewaySettingsForm?.addEventListener('submit', (e) => this.handleSaveSettingsSubmit(e));

            dom.paymentGatewaysContainer?.addEventListener('click', (event) => {
                const targetButton = event.target.closest('button.manage-gateway-settings-btn');
                if (targetButton) {
                    this.openSettingsModal(targetButton.dataset.gatewayId);
                }
            });
        },

        fetchList: function() {
            if (!dom.paymentGatewaysContainer) return;
            utils.showLoadingPlaceholder(dom.paymentGatewaysContainer, 'Loading payment gateways...');
            utils.makeAjaxCall('orunk_admin_get_gateways', {})
                .then(data => {
                    app.currentGatewaysData = data.gateways || [];
                    this.renderList(app.currentGatewaysData);
                })
                .catch(error => utils.showErrorInContainer(dom.paymentGatewaysContainer, `Error loading gateways: ${error.message}.`));
        },

        renderList: function(gateways) {
            if (!dom.paymentGatewaysContainer) return;
            if (!gateways || gateways.length === 0) {
                dom.paymentGatewaysContainer.innerHTML = '<div class="p-4 text-center text-gray-500">No payment gateways found or configured.</div>';
                return;
            }
            let listHTML = '<div class="space-y-4">';
            gateways.forEach(gw => {
                const statusBadge = `<span class="status-badge ${gw.enabled ? 'active' : 'disabled'}">${gw.enabled ? 'Enabled' : 'Disabled'}</span>`;
                listHTML += `
                    <div class="card !rounded-md">
                        <div class="card-header !py-3 !px-4 !bg-gray-50">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <strong class="text-base font-semibold text-gray-700">${utils.escapeHTML(gw.title)}</strong>
                                    <code class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">${utils.escapeHTML(gw.id)}</code>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                ${statusBadge}
                                <button data-gateway-id="${gw.id}" class="manage-gateway-settings-btn button button-secondary button-sm !text-xs">
                                    <i class="fas fa-cog"></i> <span class="button-text-label">Manage Settings</span>
                                </button>
                            </div>
                        </div>
                        ${gw.description ? `<div class="px-4 py-2 text-xs text-gray-500 border-t border-gray-100 bg-gray-50/50 rounded-b-md">${utils.escapeHTML(gw.description)}</div>` : ''}
                    </div>`;
            });
            listHTML += '</div>';
            dom.paymentGatewaysContainer.innerHTML = listHTML;
        },

        openSettingsModal: function(gatewayId) {
            const modal = utils.openModal('gateway-settings');
            if (!modal) return;
            const form = dom.gatewaySettingsForm;
            const titleEl = modal.querySelector('#gateway-settings-modal-title');
            const modalBody = modal.querySelector('#gateway-settings-modal-body');

            form['settings-gateway-id-hidden'].value = gatewayId;
            utils.showLoadingPlaceholder(modalBody, 'Loading settings form...');

            const gateway = app.currentGatewaysData.find(g => g.id === gatewayId);
            if (!gateway) {
                utils.showErrorInContainer(modalBody, 'Gateway data not found.');
                titleEl.textContent = 'Error';
                return;
            }
            titleEl.innerHTML = `<i class="fas fa-cog text-indigo-500 mr-2"></i> ${utils.escapeHTML(gateway.title)} Settings`;
            this.renderSettingsFormFields(gateway.form_fields, gateway.settings, modalBody, gateway.id);
        },

        renderSettingsFormFields: function(formFields, currentSettings, container, gatewayIdForUniqueIds) {
            if (!formFields || Object.keys(formFields).length === 0) {
                container.innerHTML = '<div class="p-4 text-center text-gray-500">No configurable settings for this gateway.</div>';
                return;
            }
            let formHTML = '';
            for (const key in formFields) {
                if (!formFields.hasOwnProperty(key)) continue;
                const field = formFields[key];
                const settingsObj = typeof currentSettings === 'object' && currentSettings !== null ? currentSettings : {};
                const value = settingsObj[key] !== undefined ? settingsObj[key] : (field.default !== undefined ? field.default : '');
                const type = field.type || 'text';
                const fieldId = `gw_setting_${gatewayIdForUniqueIds}_${key}`;
                const fieldName = `settings[${key}]`;
                const descriptionHTML = field.description ? `<p class="form-description">${field.description.replace(/<code>(.*?)<\/code>/g, '<code class="text-xs bg-gray-100 p-1 rounded border border-gray-200">$1</code>')}</p>` : '';

                formHTML += `<div class="mb-4 setting-field-wrapper">`;
                if (type === 'title') {
                    formHTML += `<div class="pt-2 pb-1 border-b border-gray-200 mb-3"><h4 class="text-md font-semibold text-gray-600">${utils.escapeHTML(field.title || '')}</h4>${descriptionHTML}</div>`;
                } else {
                    formHTML += `<label for="${fieldId}" class="form-label">${utils.escapeHTML(field.title || key)}</label>`;
                    switch (type) {
                        case 'text': case 'email': case 'password': case 'number':
                            formHTML += `<input type="${type}" name="${fieldName}" id="${fieldId}" value="${utils.escapeHTML(value)}" class="form-input !text-sm" ${field.custom_attributes?.required ? 'required' : ''}>`;
                            break;
                        case 'textarea':
                            formHTML += `<textarea name="${fieldName}" id="${fieldId}" rows="4" class="form-textarea !text-sm">${utils.escapeHTML(value)}</textarea>`;
                            break;
                        case 'checkbox':
                            const checked = value === 'yes' ? 'checked' : '';
                            formHTML += `<label class="mt-1 inline-flex items-center"><input type="checkbox" name="${fieldName}" id="${fieldId}" value="yes" ${checked} class="form-checkbox h-4 w-4 text-indigo-600"><span class="ml-2 text-sm text-gray-600">${utils.escapeHTML(field.label || '')}</span></label>`;
                            break;
                        case 'select':
                            formHTML += `<select name="${fieldName}" id="${fieldId}" class="form-select !text-sm">`;
                            if (field.options && typeof field.options === 'object') {
                                for(const optKey in field.options) {
                                    const selected = optKey == value ? 'selected' : '';
                                    formHTML += `<option value="${utils.escapeHTML(optKey)}" ${selected}>${utils.escapeHTML(field.options[optKey])}</option>`;
                                }
                            }
                            formHTML += `</select>`;
                            break;
                        default: formHTML += `<p class="text-xs text-red-500">Unsupported field type: ${type}</p>`; break;
                    }
                    formHTML += descriptionHTML;
                }
                formHTML += '</div>';
            }
            container.innerHTML = formHTML;
        },

        handleSaveSettingsSubmit: function(event) {
            event.preventDefault();
            const form = dom.gatewaySettingsForm;
            const button = form.querySelector('button[type="submit"]');
            utils.showButtonSpinner(button, true, 'Saving...');
            utils.setModalFeedback(dom.gatewaySettingsModal, '', true);

            const formData = new FormData(form);
            const gatewayId = form.querySelector('#settings-gateway-id-hidden').value;
            formData.append('gateway_id', gatewayId);

            const gatewayData = app.currentGatewaysData.find(g => g.id === gatewayId);
            if (gatewayData && gatewayData.form_fields) {
                for (const key in gatewayData.form_fields) {
                    if (gatewayData.form_fields[key].type === 'checkbox' && !formData.has(`settings[${key}]`)) {
                        formData.set(`settings[${key}]`, 'no');
                    }
                }
            }

            utils.makeAjaxCall('orunk_admin_save_gateway_settings', {}, 'POST', formData)
                .then(data => {
                    utils.setModalFeedback(dom.gatewaySettingsModal, data.message || 'Settings saved!', true);
                    this.fetchList();
                    setTimeout(() => utils.closeModal(dom.gatewaySettingsModal), 1500);
                })
                .catch(error => utils.setModalFeedback(dom.gatewaySettingsModal, error.message || 'Save failed.', false))
                .finally(() => utils.showButtonSpinner(button, false, 'Save Settings'));
        }
    };
})();