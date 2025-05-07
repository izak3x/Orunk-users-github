// orunk-users/assets/js/orunk-admin-frontend/admin-categories.js
(function() {
    const app = window.orunkAdminAppState;
    const dom = window.orunkAdminDOMElements;
    const utils = window.orunkAdminUtils;

    if (!app || !dom || !utils) {
        console.error("Orunk Admin Categories: Core app, DOM, or utils not found on window object.");
        return;
    }

    window.orunkAdminApp = window.orunkAdminApp || {};
    window.orunkAdminApp.categories = {
        init: function() {
            dom.addCategoryForm?.addEventListener('submit', (e) => this.handleSaveSubmit(e));
            dom.categoryEditForm?.addEventListener('submit', (e) => this.handleSaveSubmit(e));

            dom.featureCategoriesContainer?.addEventListener('click', (event) => {
                const targetButton = event.target.closest('button');
                if (!targetButton) return;

                if (targetButton.classList.contains('edit-category-action-btn')) {
                    this.openEditModal(targetButton.dataset.categoryId);
                } else if (targetButton.classList.contains('delete-category-action-btn')) {
                    this.handleDelete(targetButton.dataset.categoryId, targetButton.dataset.categoryName);
                }
            });

            const newCatNameInput = dom.addCategoryForm?.querySelector('#new-category-name-input');
            const newCatSlugInput = dom.addCategoryForm?.querySelector('#new-category-slug-input');
            if (newCatNameInput && newCatSlugInput) {
                newCatNameInput.addEventListener('input', () => { newCatSlugInput.value = utils.generateSlug(newCatNameInput.value); });
            }
            const editCatNameInput = dom.categoryEditForm?.querySelector('#edit-category-name');
            const editCatSlugInput = dom.categoryEditForm?.querySelector('#edit-category-slug');
            if (editCatNameInput && editCatSlugInput) {
                editCatNameInput.addEventListener('input', () => { if(!editCatSlugInput.dataset.editedManually) editCatSlugInput.value = utils.generateSlug(editCatNameInput.value); });
                editCatSlugInput.addEventListener('input', () => { editCatSlugInput.dataset.editedManually = 'true'; });
            }
        },

        fetchList: function() {
            if (!dom.featureCategoriesContainer) return;
            utils.showLoadingPlaceholder(dom.featureCategoriesContainer, 'Loading categories...');
            utils.makeAjaxCall('orunk_admin_get_categories', {})
                .then(data => {
                    app.currentCategoriesData = data.categories || [];
                    this.renderTable(app.currentCategoriesData);
                    // Update dropdown in feature modal if features module exists and is initialized
                    if (window.orunkAdminApp.features?.populateCategoryDropdown) {
                        window.orunkAdminApp.features.populateCategoryDropdown(app.currentCategoriesData);
                    }
                })
                .catch(error => utils.showErrorInContainer(dom.featureCategoriesContainer, `Error loading categories: ${error.message}.`));
        },

        renderTable: function(categories) {
            if (!dom.featureCategoriesContainer) return;
            if (!categories || categories.length === 0) {
                dom.featureCategoriesContainer.innerHTML = '<div class="p-4 text-center text-sm text-gray-500">No categories created yet. Use the form below to add one.</div>';
                return;
            }
            let tableHTML = `<div class="table-wrapper"><table class="data-table"><thead><tr>
                <th>Name</th><th>Slug</th><th>Actions</th>
                </tr></thead><tbody class="divide-y divide-gray-200">`;
            categories.forEach(cat => {
                tableHTML += `
                    <tr id="category-admin-row-${cat.id}">
                        <td class="font-medium py-3 px-4">${utils.escapeHTML(cat.category_name)}</td>
                        <td class="py-3 px-4"><code>${utils.escapeHTML(cat.category_slug)}</code></td>
                        <td class="space-x-1 whitespace-nowrap py-3 px-4">
                            <button data-category-id="${cat.id}" class="edit-category-action-btn button button-link button-sm !text-xs"><i class="fas fa-pencil-alt mr-1"></i>Edit</button>
                            <button data-category-id="${cat.id}" data-category-name="${utils.escapeHTML(cat.category_name)}" class="delete-category-action-btn button button-link-danger button-sm !text-xs"><i class="fas fa-trash-alt mr-1"></i>Delete</button>
                        </td>
                    </tr>`;
            });
            tableHTML += `</tbody></table></div>`;
            dom.featureCategoriesContainer.innerHTML = tableHTML;
        },

        openEditModal: function(categoryId = 0) {
            const modal = utils.openModal('category-edit');
            if(!modal) return;
            const form = dom.categoryEditForm;
            const titleEl = modal.querySelector('#category-edit-modal-title');
            const slugInput = form.querySelector('#edit-category-slug');
            delete slugInput.dataset.editedManually;

            if (categoryId > 0) {
                titleEl.innerHTML = '<i class="fas fa-tags text-indigo-500 mr-2"></i> Edit Category';
                const category = app.currentCategoriesData.find(c => c.id == categoryId);
                if (category) {
                    form['edit-category-id'].value = category.id;
                    form['edit-category-name'].value = category.category_name;
                    form['edit-category-slug'].value = category.category_slug;
                } else {
                    utils.setModalFeedback(modal, 'Error: Category not found.', false); utils.closeModal(modal);
                }
            } else {
                titleEl.innerHTML = '<i class="fas fa-tags text-indigo-500 mr-2"></i> Add New Category';
                 form.reset(); form['edit-category-id'].value = '0';
            }
        },

        handleSaveSubmit: function(event) {
            event.preventDefault();
            const form = event.target.closest('form');
            const button = form.querySelector('button[type="submit"]');
            const isAddForm = form.id === 'orunk-add-category-form';
            const modalElement = isAddForm ? null : dom.categoryEditModal;
            const feedbackDivId = isAddForm ? 'add-category-form-feedback' : 'category-edit-modal-feedback';

            utils.showButtonSpinner(button, true, 'Saving...');
            const feedbackDiv = document.getElementById(feedbackDivId);
            if(feedbackDiv) { feedbackDiv.textContent = ''; feedbackDiv.className = 'text-sm mt-2 h-5'; }

            const formData = new FormData(form);
            if (isAddForm) { formData.append('category_id', '0'); }

            utils.makeAjaxCall('orunk_admin_save_category', {}, 'POST', formData)
                .then(data => {
                    if (feedbackDiv) {
                        feedbackDiv.textContent = data.message || 'Category saved!';
                        feedbackDiv.className = `text-sm mt-2 h-5 ${data.category ? 'text-green-600' : 'text-red-600' }`;
                    }
                    this.fetchList();
                    if (isAddForm) { form.reset(); const slugInput = form.querySelector('#new-category-slug-input'); if(slugInput) slugInput.value=''; }
                    else { setTimeout(() => utils.closeModal(modalElement), 1500); }
                })
                .catch(error => {
                    if (feedbackDiv) {
                        feedbackDiv.textContent = error.message || 'Save failed.';
                        feedbackDiv.className = 'text-sm mt-2 h-5 text-red-600';
                    }
                })
                .finally(() => {
                    utils.showButtonSpinner(button, false, isAddForm ? 'Add Category' : 'Save Category' );
                    setTimeout(() => { if(feedbackDiv) feedbackDiv.textContent = ''; }, 4000);
                });
        },

        handleDelete: function(categoryId, categoryName) {
            if (!confirm(`Are you sure you want to delete the category "${categoryName}"?\nFeatures using this category might become uncategorized.`)) return;
            utils.makeAjaxCall('orunk_admin_delete_category', { category_id: categoryId }, 'POST')
                .then(data => {
                    const categoryRow = document.getElementById(`category-admin-row-${categoryId}`);
                    if (categoryRow) {
                        categoryRow.style.transition = 'opacity 0.3s ease'; categoryRow.style.opacity = '0';
                        setTimeout(() => categoryRow.remove(), 300);
                    }
                   // Instead of full refetch, remove from appState and re-render (more efficient)
                   app.currentCategoriesData = app.currentCategoriesData.filter(c => c.id != categoryId);
                   this.renderTable(app.currentCategoriesData);
                   if (window.orunkAdminApp.features?.populateCategoryDropdown) {
                       window.orunkAdminApp.features.populateCategoryDropdown(app.currentCategoriesData);
                   }
                })
                .catch(error => alert(`Error deleting category: ${error.message}`));
        }
    };
})();