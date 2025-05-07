// orunk-users/assets/js/orunk-admin-frontend/admin-users-purchases.js
(function() {
    const app = window.orunkAdminAppState;
    const dom = window.orunkAdminDOMElements;
    const utils = window.orunkAdminUtils;

    if (!app || !dom || !utils) {
        console.error("Orunk Admin Users & Purchases: Core app, DOM, or utils not found on window object.");
        return;
    }

    window.orunkAdminApp = window.orunkAdminApp || {};
    window.orunkAdminApp.users = {
        init: function() {
            dom.userSearchInput?.addEventListener('input', utils.debounce((e) => this.fetchList(e.target.value.trim()), 350));

            dom.userListContainer?.addEventListener('click', (event) => {
                const targetButton = event.target.closest('button.view-user-purchases-btn');
                if (targetButton) {
                    this.openModal(targetButton.dataset.userId, targetButton.dataset.userName);
                }
            });

            app.modals['user-purchases']?.addEventListener('click', (event) => {
                const targetButton = event.target.closest('button.update-status-btn');
                if (targetButton && !targetButton.disabled) {
                    const purchaseId = targetButton.dataset.purchaseId;
                    const selectElement = app.modals['user-purchases'].querySelector(`.status-update-select[data-purchase-id="${purchaseId}"]`);
                    const newStatus = selectElement?.value;
                    if (purchaseId && newStatus) {
                        this.handleStatusUpdate(purchaseId, newStatus, targetButton);
                    }
                }
            });
            app.modals['user-purchases']?.addEventListener('change', (event) => {
                 const targetSelect = event.target.closest('select.status-update-select');
                 if(targetSelect){
                    const purchaseId = targetSelect.dataset.purchaseId;
                    const updateButton = app.modals['user-purchases'].querySelector(`.update-status-btn[data-purchase-id="${purchaseId}"]`);
                    if(updateButton) updateButton.disabled = !targetSelect.value;
                 }
            });
        },

        fetchList: function(searchTerm = '') {
            if (!dom.userListContainer) return;
            utils.showLoadingPlaceholder(dom.userListContainer, 'Loading users...');
            utils.makeAjaxCall('orunk_admin_get_users_list', { search: searchTerm })
                .then(data => {
                    app.currentUsersData = data.users || [];
                    this.renderTable(app.currentUsersData);
                })
                .catch(error => {
                    utils.showErrorInContainer(dom.userListContainer, `Error loading users: ${error.message}. Please try again.`);
                });
        },

        renderTable: function(users) {
            if (!dom.userListContainer) return;
            if (!users || users.length === 0) {
                dom.userListContainer.innerHTML = '<div class="p-4 text-center text-gray-500">No users found.</div>';
                return;
            }
            let tableHTML = `<div class="table-wrapper"><table class="data-table"><thead><tr>
                <th>User</th><th>Email</th><th>Role(s)</th><th>Purchases</th><th>Actions</th>
                </tr></thead><tbody class="divide-y divide-gray-200">`;
            users.forEach(user => {
                const roles = user.roles && user.roles.length > 0 ? user.roles.map(r => utils.escapeHTML(r.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()))).join(', ') : 'N/A';
                tableHTML += `
                    <tr id="user-row-${user.id}">
                        <td class="flex items-center py-3 px-4">
                            <img src="${user.avatar}" alt="${utils.escapeHTML(user.display_name)}" class="w-8 h-8 rounded-full mr-3">
                            <div>
                                <div class="font-medium text-gray-900">${utils.escapeHTML(user.display_name)}</div>
                                <div class="text-xs text-gray-500">@${utils.escapeHTML(user.login)}</div>
                            </div>
                        </td>
                        <td><a href="mailto:${utils.escapeHTML(user.email)}" class="text-indigo-600 hover:underline">${utils.escapeHTML(user.email)}</a></td>
                        <td class="text-xs">${roles}</td>
                        <td class="text-center">${user.purchase_count || 0}</td>
                        <td class="whitespace-nowrap">
                            <button data-user-id="${user.id}" data-user-name="${utils.escapeHTML(user.display_name)}" class="view-user-purchases-btn button button-secondary button-sm !text-xs">
                                <i class="fas fa-shopping-cart"></i> <span class="button-text-label">Purchases</span>
                            </button>
                            <a href="${user.edit_link}" target="_blank" class="button button-link button-sm !text-xs ml-1" title="Edit WP Profile">
                                <i class="fas fa-user-edit"></i> <span class="button-text-label">Edit</span>
                            </a>
                        </td>
                    </tr>`;
            });
            tableHTML += `</tbody></table></div>`;
            dom.userListContainer.innerHTML = tableHTML;
        },

        openModal: function(userId, userName) {
            const modal = utils.openModal('user-purchases');
            if (!modal) return;
            modal.querySelector('#user-purchases-modal-title').innerHTML = `<i class="fas fa-shopping-cart text-indigo-500 mr-2"></i> Purchases for ${utils.escapeHTML(userName)}`;
            const modalBody = modal.querySelector('#user-purchases-modal-body');
            utils.showLoadingPlaceholder(modalBody, 'Loading purchases...');
            modal.dataset.currentUserId = userId;

            utils.makeAjaxCall('orunk_admin_get_user_purchases', { user_id: userId })
                .then(data => this.renderPurchasesInModal(data.purchases, modalBody))
                .catch(error => utils.showErrorInContainer(modalBody, `Error loading purchases: ${error.message}`));
        },

        renderPurchasesInModal: function(purchases, container) {
            if (!purchases || purchases.length === 0) {
                container.innerHTML = '<div class="p-4 text-center text-gray-500">No purchases found for this user.</div>';
                return;
            }
            let listHTML = '<div class="space-y-3 max-h-[60vh] overflow-y-auto pr-2 -mr-1">';
            purchases.forEach(p => {
                const statusClass = `status-badge ${utils.escapeHTML(p.status.toLowerCase().replace(/\s+/g, '-'))}`;
                const statusBadge = `<span class="${statusClass}">${utils.escapeHTML(p.status)}</span>`;
                let pendingSwitchInfo = '';
                if (p.is_switch_pending) {
                    pendingSwitchInfo = `<div class="my-1 p-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800 flex items-start gap-2"><i class="fas fa-info-circle mt-0.5 text-amber-500"></i><div><strong>Pending Switch To:</strong> ${utils.escapeHTML(p.pending_switch_plan_name || 'N/A')} ${p.can_approve_switch ? '(Awaiting Approval)' : ''}</div></div>`;
                }
                let activationInfo = '';
                if (p.supports_activation_management) {
                    activationInfo = `<div class="text-xs text-gray-500 mt-1">Activations: ${p.activation_summary || 'N/A'}</div>`;
                }

                let statusOptions = `<option value="" disabled selected>-- Change Status --</option>`;
                if (p.can_approve_switch) {
                    statusOptions += `<option value="approve_switch" class="font-bold text-green-700">Approve Pending Switch</option><option value="" disabled>-----</option>`;
                }
                ['pending', 'active', 'expired', 'cancelled', 'failed'].forEach(s => {
                    const currentStatusNormalized = p.status.toLowerCase().replace('pending payment', 'pending');
                    statusOptions += `<option value="${s}" ${currentStatusNormalized === s ? 'disabled' : ''}>Set ${s.charAt(0).toUpperCase() + s.slice(1)}</option>`;
                });

                listHTML += `
                    <div class="border rounded-md p-3 shadow-sm bg-white" id="purchase-item-${p.id}">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1 min-w-0">
                                <strong class="text-sm font-semibold text-indigo-700 block">${utils.escapeHTML(p.plan_name)}</strong>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    <span title="Feature"><i class="fas fa-cube mr-1 opacity-60"></i>${utils.escapeHTML(p.feature_name)}</span> |
                                    <span title="Purchase ID"><i class="fas fa-hashtag mr-1 opacity-60"></i>${p.id}</span>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0 ml-2">${statusBadge}</div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-600 mb-2">
                            <div><i class="fas fa-calendar-plus mr-1.5 w-3 text-center opacity-60"></i>Purchased: ${utils.escapeHTML(p.purchase_date)}</div>
                            <div class="purchase-expiry-date"><i class="fas fa-calendar-times mr-1.5 w-3 text-center opacity-60"></i>Expires: ${utils.escapeHTML(p.expiry_date)}</div>
                            <div><i class="fas fa-credit-card mr-1.5 w-3 text-center opacity-60"></i>Gateway: ${utils.escapeHTML(p.gateway)}</div>
                            <div><i class="fas fa-receipt mr-1.5 w-3 text-center opacity-60"></i>Trans ID: ${utils.escapeHTML(p.transaction_id || 'N/A')}</div>
                        </div>
                        ${p.api_key_masked ? `<div class="text-xs text-gray-500 mb-1 flex items-center"><i class="fas fa-key mr-1.5 w-3 text-center opacity-60"></i>API Key: <code class="ml-1 bg-gray-100 px-1.5 py-0.5 rounded font-mono text-indigo-800">${utils.escapeHTML(p.api_key_masked)}</code></div>` : ''}
                        ${p.license_key_masked ? `<div class="text-xs text-gray-500 mb-2 flex items-center"><i class="fas fa-id-badge mr-1.5 w-3 text-center opacity-60"></i>License Key: <code class="ml-1 bg-gray-100 px-1.5 py-0.5 rounded font-mono text-indigo-800">${utils.escapeHTML(p.license_key_masked)}</code></div>` : ''}
                        ${activationInfo}
                        ${pendingSwitchInfo}
                        ${p.failure_reason ? `<div class="my-1 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700"><i class="fas fa-exclamation-triangle mr-1"></i><strong>Failed:</strong> ${utils.escapeHTML(p.failure_reason)} (${utils.escapeHTML(p.failure_timestamp || '')})</div>` : ''}
                        <div class="mt-3 flex items-center space-x-2">
                            <select data-purchase-id="${p.id}" class="status-update-select form-select !text-xs !py-1 !px-2 !max-w-[180px] rounded-md shadow-sm">${statusOptions}</select>
                            <button data-purchase-id="${p.id}" class="update-status-btn button button-primary button-sm" disabled>
                                <span class="button-text-label">Update</span><span class="update-spinner hidden"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                            </button>
                        </div>
                        <div class="purchase-update-feedback text-xs mt-1 h-4"></div>
                    </div>`;
            });
            listHTML += '</div>';
            container.innerHTML = listHTML;
        },

        handleStatusUpdate: function(purchaseId, newStatus, buttonElement) {
            const modal = app.modals['user-purchases']; // Use app state
            const listItem = modal.querySelector(`#purchase-item-${purchaseId}`);
            const feedbackDiv = listItem?.querySelector('.purchase-update-feedback');
            const selectElement = listItem?.querySelector('.status-update-select');

            if (feedbackDiv) { feedbackDiv.textContent = ''; feedbackDiv.className = 'purchase-update-feedback text-xs mt-1 h-4'; }
            utils.showButtonSpinner(buttonElement, true, 'Updating...');
            if (selectElement) selectElement.disabled = true;

            utils.makeAjaxCall('orunk_admin_update_purchase_status', { purchase_id: purchaseId, status: newStatus }, 'POST')
                .then(data => {
                    utils.setModalFeedback(modal, data.message || 'Status updated!', true);
                    const statusBadge = listItem?.querySelector('.status-badge');
                    if (statusBadge) {
                        statusBadge.textContent = data.updated_status;
                        statusBadge.className = `status-badge ${utils.escapeHTML(data.updated_status.toLowerCase().replace(/\s+/g, '-'))}`;
                    }
                    const expiryDateSpan = listItem?.querySelector('.purchase-expiry-date');
                    if (expiryDateSpan) { expiryDateSpan.innerHTML = `<i class="fas fa-calendar-times mr-1.5 w-3 text-center opacity-60"></i>Expires: ${utils.escapeHTML(data.updated_expiry)}`; }

                    if (selectElement) selectElement.value = "";
                    if (newStatus === 'approve_switch' || data.is_switch_pending === false || (data.updated_status && data.updated_status.toLowerCase() !== 'pending payment' && data.updated_status.toLowerCase() !== 'pending')) {
                        if (modal.dataset.currentUserId) {
                            this.openModal(modal.dataset.currentUserId, modal.querySelector('#user-purchases-modal-title').textContent.replace('Purchases for ', ''));
                        }
                    }
                })
                .catch(error => {
                    utils.setModalFeedback(modal, error.message || 'Update failed.', false);
                })
                .finally(() => {
                    utils.showButtonSpinner(buttonElement, false, 'Update');
                    if (selectElement) selectElement.disabled = false;
                    if (buttonElement) buttonElement.disabled = true;
                });
        }
    };
})();