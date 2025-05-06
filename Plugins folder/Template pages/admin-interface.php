<?php
/**
 * Template Name: Custom Admin Interface - Orunk Users
 * Template Post Type: page
 *
 * Modifications:
 * v1.1.0: Added Category dropdown, One-Time Payment option.
 * v1.2.0: Added Category Management UI (List, Add, Edit, Delete).
 */

// Ensure user is logged in and has appropriate capabilities
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// Fetch categories for the dropdown (needed for both initial load and AJAX)
// Note: This is now handled dynamically by JS/AJAX, keeping PHP fetch minimal
$feature_categories = [];
/* // Removed PHP pre-fetch, JS will handle it
if (class_exists('Custom_Orunk_DB')) {
    $orunk_db = new Custom_Orunk_DB();
    if (method_exists($orunk_db, 'get_all_feature_categories')) {
        $feature_categories = $orunk_db->get_all_feature_categories();
    }
} */

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title( '|', true, 'right' ); ?> <?php bloginfo( 'name' ); ?> - Orunk Admin</title>
    <?php // Using Tailwind CDN with forms plugin ?>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <?php // Using Font Awesome for icons ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- Custom Variables --- */
        :root {
            --primary-color: #4f46e5; /* Indigo-600 */
            --primary-hover: #4338ca; /* Indigo-700 */
            --secondary-bg: #f8fafc;  /* Cool Gray 50 */
            --secondary-border: #e5e7eb; /* Cool Gray 200 */
            --danger-color: #ef4444;  /* Red-500 */
            --danger-hover: #dc2626; /* Red-600 */
            --success-color: #10b981; /* Emerald 500 */
            --warning-color: #f59e0b; /* Amber 500 */
            --text-main: #1f2937; /* Cool Gray 800 */
            --text-secondary: #6b7280; /* Cool Gray 500 */
            --text-light: #f9fafb; /* Cool Gray 50 */
            --border-color: #e5e7eb; /* Cool Gray 200 */
        }

        /* --- Base & Layout --- */
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"; background-color: #f3f4f6; /* Gray 100 */ }
        .card { background-color: white; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0,0,0,.05), 0 1px 2px -1px rgba(0,0,0,.05); border: 1px solid var(--border-color); }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; background-color: var(--secondary-bg); border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; }
        .card-body { padding: 1.5rem; }
        .table-wrapper { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 0.375rem; }
        .data-table { min-width: 100%; divide-y divide-gray-200; }
        .data-table th { background-color: #f9fafb; padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }
        .data-table td { padding: 0.75rem 1rem; vertical-align: middle; font-size: 0.875rem; color: var(--text-main); border-top: 1px solid var(--border-color); }
        .data-table tbody tr:hover { background-color: #f9fafb; }

        /* Loading Spinner */
        .loading-spinner { border: 3px solid rgba(0, 0, 0, .1); border-left-color: var(--primary-color); border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner-inline { display: inline-block; vertical-align: middle; width: 14px; height: 14px; border-width: 2px; margin-left: 6px; }

        /* Status Badges */
        .status-badge { display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; font-size: 0.7rem; font-weight: 500; border-radius: 9999px; text-transform: capitalize; line-height: 1.2; border: 1px solid transparent; }
        .status-active { background-color: #dcfce7; color: #15803d; border-color: #a7f3d0; } /* Green */
        .status-pending { background-color: #fef9c3; color: #a16207; border-color: #fde68a; } /* Yellow */
        .status-expired, .status-cancelled, .status-failed { background-color: #fee2e2; color: #b91c1c; border-color: #fecaca; } /* Red */
        .status-inactive { background-color: #f3f4f6; color: #4b5563; border-color: #e5e7eb; } /* Gray */

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.65); display: flex; align-items: center; justify-content: center; z-index: 50; visibility: hidden; opacity: 0; transition: visibility 0s linear 0.2s, opacity 0.2s ease-in-out; padding: 1rem; }
        .modal-overlay.active { visibility: visible; opacity: 1; transition-delay: 0s; }
        .modal-content { background-color: white; padding: 1.5rem 2rem; border-radius: 0.5rem; max-width: 95%; width: 700px; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 20px 25px -5px rgba(0,0,0,.1), 0 10px 10px -5px rgba(0,0,0,.04); transform: scale(0.95); opacity: 0; transition: all 0.2s ease-in-out; }
        .modal-overlay.active .modal-content { transform: scale(1); opacity: 1; transition-delay: 0.05s; }
        .modal-close-btn { position: absolute; top: 0.75rem; right: 0.75rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #9ca3af; line-height: 1; padding: 0.25rem; border-radius: 9999px; }
        .modal-close-btn:hover { color: #1f2937; background-color: #f3f4f6; }

        /* Tabs */
        .tab-button { position: relative; padding: 0.75rem 0.5rem; margin-right: 1rem; font-size: 0.875rem; font-weight: 500; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.2s ease; }
        .tab-button:hover { color: var(--primary-color); }
        .tab-button.active { color: var(--primary-color); border-bottom-color: var(--primary-color); font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* Forms */
        .form-label { display: block; margin-bottom: 0.25rem; font-size: 0.75rem; font-weight: 500; color: #4b5563; }
        .form-input, .form-textarea, .form-select { display: block; width: 100%; border-color: #d1d5db; border-radius: 0.375rem; box-shadow: none; transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out; }
        .form-input:focus, .form-textarea:focus, .form-select:focus { border-color: var(--primary-color); ring: 1; ring-color: var(--primary-color); ring-opacity: 0.5; }
        .form-description { font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem; }
        .form-radio-group { margin-top: 0.5rem; margin-bottom: 0.5rem; }
        .form-radio-label { display: flex; align-items: center; margin-right: 1.5rem; cursor: pointer; font-size: 0.875rem; }
        .form-radio { width: 1rem; height: 1rem; margin-right: 0.5rem; color: var(--primary-color); border-color: #d1d5db; }
        .form-radio:focus { ring-color: var(--primary-color); }

        /* Buttons - Refined */
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 0.5rem 1rem; border: 1px solid transparent; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.15s ease-in-out; text-decoration: none !important; line-height: 1.25; box-shadow: 0 1px 2px 0 rgba(0,0,0,.05); }
        .button:focus { outline: 2px solid transparent; outline-offset: 2px; ring: 2; ring-offset-1; ring-color: var(--primary-color); }
        .button-primary { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }
        .button-primary:hover:not(:disabled) { background-color: var(--primary-hover); border-color: var(--primary-hover); }
        .button-secondary { background-color: white; color: var(--text-main); border-color: var(--border-color); }
        .button-secondary:hover:not(:disabled) { background-color: #f9fafb; border-color: #d1d5db; } /* Adjusted hover */
        .button-danger { background-color: var(--danger-color); color: white; border-color: var(--danger-color); }
        .button-danger:hover:not(:disabled) { background-color: var(--danger-hover); border-color: var(--danger-hover); }
        /* Smaller Buttons */
        .button-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; }
        /* Link Style Buttons */
        .button-link { background: none; border: none; padding: 0; color: var(--primary-color); text-decoration: none; cursor: pointer; font-weight: 500; box-shadow: none; }
        .button-link:hover:not(:disabled) { text-decoration: underline; }
        .button-link-danger { color: var(--danger-color); }
        .button-link-danger:hover:not(:disabled) { color: var(--danger-hover); text-decoration: underline; }
        .button i { margin-right: 0.35rem; font-size: 0.9em; }
        .button-sm i { margin-right: 0.25rem; font-size: 0.8em; }
        .button-link i { margin-right: 0.25rem; }
        /* Hide/Show elements */
        .hidden { display: none; }
        /* Slug input style */
        .slug-input { font-family: monospace; font-size: 0.8rem !important; background-color: #f9fafb; }

    </style>
    <?php wp_head(); ?>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800">

    <div class="container mx-auto px-4 py-6 md:py-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 pb-4 border-b border-gray-200">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
                    Orunk Users Admin
                </h1>
                <p class="text-sm text-gray-500 mt-1">Manage users, features, plans, and payment gateways.</p>
            </div>
             <div class="mt-4 md:mt-0">
                 <?php $current_user = wp_get_current_user(); ?>
                <div class="flex items-center gap-3">
                    <?php echo get_avatar($current_user->ID, 36, '', '', ['class' => 'rounded-full shadow-sm']); ?>
                    <div>
                        <p class="text-sm font-medium text-gray-800"><?php echo esc_html($current_user->display_name); ?></p>
                        <p class="text-xs text-gray-500 capitalize"><?php echo esc_html(str_replace('_', ' ', implode(', ', $current_user->roles))); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-6">
            <nav class="flex space-x-1 border-b border-gray-200" aria-label="Tabs">
                <button data-tab="users" class="tab-button active">
                    <i class="fas fa-users mr-1.5 opacity-75"></i> Users & Purchases
                </button>
                <button data-tab="features" class="tab-button">
                    <i class="fas fa-cubes mr-1.5 opacity-75"></i> Features & Plans
                </button>
                <button data-tab="payments" class="tab-button">
                    <i class="fas fa-credit-card mr-1.5 opacity-75"></i> Payment Gateways
                </button>
            </nav>
        </div>

        <div id="admin-content-area">

            <div id="tab-content-users" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-800">User Management</h2>
                    </div>
                    <div class="card-body">
                        <div id="users-list-container">
                            <div class="flex justify-center items-center p-8"><div class="loading-spinner"></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-content-features" class="tab-content">
                <div class="card mb-8"> <?php // Added margin-bottom ?>
                     <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-800">Features & Plans</h2>
                        <button id="add-feature-btn" class="button button-primary button-sm">
                            <i class="fas fa-plus"></i> Add New Feature
                        </button>
                     </div>
                     <div class="card-body">
                        <div id="features-plans-container">
                             <div class="flex justify-center items-center p-8"><div class="loading-spinner"></div></div>
                        </div>
                     </div>
                 </div>

                 <?php // <<< NEW: Category Management Section >>> ?>
                 <div class="card">
                     <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-800">Manage Feature Categories</h2>
                         <?php /* Button moved below table */ ?>
                     </div>
                     <div class="card-body">
                        <div id="feature-categories-container">
                             <div class="flex justify-center items-center p-8"><div class="loading-spinner"></div></div>
                        </div>
                        <?php // Add New Category Form ?>
                         <form id="add-category-form" class="mt-6 pt-4 border-t border-gray-200">
                             <h4 class="text-md font-medium text-gray-700 mb-3">Add New Category</h4>
                             <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                                 <div>
                                     <label for="new_category_name" class="form-label">Category Name <span class="text-red-500">*</span></label>
                                     <input type="text" name="new_category_name" id="new_category_name" class="form-input !text-sm" required>
                                 </div>
                                  <div>
                                     <label for="new_category_slug" class="form-label">Category Slug <span class="text-red-500">*</span></label>
                                     <input type="text" name="new_category_slug" id="new_category_slug" class="form-input !text-sm slug-input" required pattern="[a-z0-9-]+" title="Lowercase letters, numbers, and hyphens only.">
                                      <p class="form-description">Unique identifier (e.g., 'wordpress-plugin').</p>
                                 </div>
                                 <div>
                                      <button type="submit" id="add-category-submit-btn" class="button button-primary w-full sm:w-auto">
                                          <i class="fas fa-plus"></i> Add Category
                                          <span class="save-spinner" style="display: none;"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                                      </button>
                                 </div>
                             </div>
                              <div id="add-category-feedback" class="text-sm mt-2 h-5"></div>
                         </form>
                     </div>
                 </div>
            </div>

            <div id="tab-content-payments" class="tab-content">
                 <div class="card">
                     <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-800">Payment Gateways</h2>
                     </div>
                     <div class="card-body">
                        <div id="payment-gateways-container">
                            <div class="flex justify-center items-center p-8"><div class="loading-spinner"></div></div>
                        </div>
                     </div>
                 </div>
            </div>

        </div> <?php // end #admin-content-area ?>
    </div> <?php // end .container ?>

    <?php // --- Modals --- ?>

    <div id="purchase-modal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close-btn" onclick="closeModal('purchase-modal')">&times;</button>
            <h3 id="modal-title-purchase" class="text-lg font-semibold mb-4 flex items-center gap-2 text-gray-700">
                <i class="fas fa-shopping-cart text-indigo-500"></i> User Purchases
            </h3>
            <div id="modal-body-purchase" class="text-sm">
                 <div class="flex justify-center items-center p-8"><div class="loading-spinner"></div></div>
            </div>
            <div id="modal-feedback-purchase" class="mt-4 text-sm text-right h-5"></div>
        </div>
    </div>

    <div id="feature-modal" class="modal-overlay">
        <div class="modal-content !w-auto max-w-xl">
            <button class="modal-close-btn" onclick="closeModal('feature-modal')">&times;</button>
            <h3 id="modal-title-feature" class="text-lg font-semibold mb-6 flex items-center gap-2 text-gray-700">
                <i class="fas fa-cube text-indigo-500"></i> Add/Edit Feature
            </h3>
            <form id="feature-form">
                <input type="hidden" name="feature_id" id="feature_id" value="0">
                <div class="space-y-4">
                    <div>
                        <label for="feature_key" class="form-label">Feature Key <span class="text-red-500">*</span></label>
                        <input type="text" name="feature_key" id="feature_key" class="form-input !text-sm" required pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only.">
                        <p class="form-description">Unique key (e.g., "bin_api"). Cannot be changed after creation.</p>
                    </div>
                    <div>
                        <label for="product_name" class="form-label">Feature Name <span class="text-red-500">*</span></label>
                        <input type="text" name="product_name" id="product_name" class="form-input !text-sm" required>
                    </div>
                    <div> <?php // Category Dropdown ?>
                        <label for="feature_category" class="form-label">Category</label>
                        <select name="category" id="feature_category" class="form-select !text-sm">
                            <option value="">-- Select Category --</option>
                            <?php // Options populated by JS ?>
                        </select>
                        <p class="form-description">Assign this feature to a category.</p>
                    </div>
                    <div>
                        <label for="feature_description" class="form-label">Description</label>
                        <textarea name="description" id="feature_description" rows="3" class="form-textarea !text-sm"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end items-center space-x-3">
                     <div id="modal-feedback-feature" class="text-sm text-right mr-auto h-5"></div>
                     <button type="button" class="button button-secondary button-sm" onclick="closeModal('feature-modal')">Cancel</button>
                     <button type="submit" id="save-feature-submit-btn" class="button button-primary button-sm">
                         <i class="fas fa-save"></i> Save Feature
                         <span class="save-spinner" style="display: none;"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                     </button>
                </div>
            </form>
        </div>
    </div>

     <div id="plan-modal" class="modal-overlay">
        <div class="modal-content !w-auto max-w-2xl">
            <button class="modal-close-btn" onclick="closeModal('plan-modal')">&times;</button>
            <h3 id="modal-title-plan" class="text-lg font-semibold mb-6 flex items-center gap-2 text-gray-700">
                 <i class="fas fa-tags text-indigo-500"></i> Add/Edit Plan
            </h3>
            <form id="plan-form">
                <input type="hidden" name="plan_id" id="plan_id" value="0">
                <input type="hidden" name="product_feature_key" id="plan_feature_key" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                     <div>
                        <label for="plan_name" class="form-label">Plan Name <span class="text-red-500">*</span></label>
                        <input type="text" name="plan_name" id="plan_name" class="form-input !text-sm" required>
                    </div>
                     <div>
                        <label for="price" class="form-label">Price ($) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0" name="price" id="price" class="form-input !text-sm" required value="0.00">
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label">Payment Type <span class="text-red-500">*</span></label>
                        <div class="flex items-center space-x-6 form-radio-group">
                            <label class="form-radio-label">
                                <input type="radio" name="payment_type" value="subscription" class="form-radio" checked>
                                <span>Subscription</span>
                            </label>
                            <label class="form-radio-label">
                                <input type="radio" name="payment_type" value="one_time" class="form-radio">
                                <span>One-Time Payment</span>
                            </label>
                        </div>
                    </div>
                     <div id="plan-duration-wrapper">
                        <label for="duration_days" class="form-label">Duration (days) <span class="text-red-500">*</span></label>
                        <input type="number" step="1" min="1" name="duration_days" id="duration_days" class="form-input !text-sm" required value="30">
                         <p class="form-description">Set automatically for One-Time Payments.</p>
                    </div>
                     <div>
                        <label class="form-label">Status</label>
                        <label class="flex items-center mt-2 cursor-pointer">
                            <input type="checkbox" name="is_active" id="is_active" value="1" class="form-checkbox h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Plan is Active (purchasable)</span>
                        </label>
                     </div>
                     <div class="md:col-span-2">
                        <label for="plan_description" class="form-label">Description</label>
                        <textarea name="description" id="plan_description" rows="2" class="form-textarea !text-sm"></textarea>
                    </div>
                     <div>
                        <label for="requests_per_day" class="form-label">Requests/Day Limit</label>
                        <input type="number" step="1" min="0" name="requests_per_day" id="requests_per_day" class="form-input !text-sm" placeholder="Blank = unlimited">
                    </div>
                     <div>
                        <label for="requests_per_month" class="form-label">Requests/Month Limit</label>
                        <input type="number" step="1" min="0" name="requests_per_month" id="requests_per_month" class="form-input !text-sm" placeholder="Blank = unlimited">
                    </div>
                </div>
                 <div class="mt-6 flex justify-end items-center space-x-3">
                     <div id="modal-feedback-plan" class="text-sm text-right mr-auto h-5"></div>
                     <button type="button" class="button button-secondary button-sm" onclick="closeModal('plan-modal')">Cancel</button>
                     <button type="submit" id="save-plan-submit-btn" class="button button-primary button-sm">
                         <i class="fas fa-save"></i> Save Plan
                         <span class="save-spinner" style="display: none;"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                     </button>
                </div>
            </form>
        </div>
    </div>

    <div id="gateway-modal" class="modal-overlay">
        <div class="modal-content !w-auto max-w-2xl">
             <button class="modal-close-btn" onclick="closeModal('gateway-modal')">&times;</button>
             <h3 id="modal-title-gateway" class="text-lg font-semibold mb-6 flex items-center gap-2 text-gray-700">
                 <i class="fas fa-cog text-indigo-500"></i> Gateway Settings
             </h3>
             <form id="gateway-form">
                 <input type="hidden" name="gateway_id" id="gateway_id_hidden">
                 <div id="modal-body-gateway" class="space-y-4">
                      <div class="flex justify-center items-center p-8"><div class="loading-spinner"></div></div>
                 </div>
                 <div class="mt-6 flex justify-end items-center space-x-3">
                     <div id="modal-feedback-gateway" class="text-sm text-right mr-auto h-5"></div>
                     <button type="button" class="button button-secondary button-sm" onclick="closeModal('gateway-modal')">Cancel</button>
                     <button type="submit" id="save-gateway-submit-btn" class="button button-primary button-sm">
                         <i class="fas fa-save"></i> Save Settings
                         <span class="save-spinner" style="display: none;"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                     </button>
                </div>
             </form>
        </div>
    </div>

     <?php // <<< NEW: Category Edit Modal >>> ?>
    <div id="category-modal" class="modal-overlay">
        <div class="modal-content !w-auto max-w-lg"> <?php // Smaller width ?>
            <button class="modal-close-btn" onclick="closeModal('category-modal')">&times;</button>
            <h3 id="modal-title-category" class="text-lg font-semibold mb-6 flex items-center gap-2 text-gray-700">
                 <i class="fas fa-tags text-indigo-500"></i> Edit Category
            </h3>
            <form id="category-form">
                <input type="hidden" name="category_id" id="category_id" value="0">
                <div class="space-y-4">
                     <div>
                        <label for="category_name" class="form-label">Category Name <span class="text-red-500">*</span></label>
                        <input type="text" name="category_name" id="category_name" class="form-input !text-sm" required>
                    </div>
                     <div>
                        <label for="category_slug" class="form-label">Category Slug <span class="text-red-500">*</span></label>
                        <input type="text" name="category_slug" id="category_slug" class="form-input !text-sm slug-input" required pattern="[a-z0-9-]+" title="Lowercase letters, numbers, and hyphens only.">
                        <p class="form-description">Unique identifier (e.g., 'wordpress-plugin').</p>
                    </div>
                </div>
                 <div class="mt-6 flex justify-end items-center space-x-3">
                     <div id="modal-feedback-category" class="text-sm text-right mr-auto h-5"></div>
                     <button type="button" class="button button-secondary button-sm" onclick="closeModal('category-modal')">Cancel</button>
                     <button type="submit" id="save-category-submit-btn" class="button button-primary button-sm">
                         <i class="fas fa-save"></i> Save Category
                         <span class="save-spinner" style="display: none;"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                     </button>
                </div>
            </form>
        </div>
    </div>

    <?php // Inject WordPress AJAX URL and nonce for JavaScript ?>
    <script type="text/javascript">
        const orunkAdminData = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('orunk_admin_interface_nonce'); ?>'
        };
    </script>

    <script type="text/javascript">
        // --- Config & State ---
        const ajaxurl = orunkAdminData.ajaxUrl;
        const adminNonce = orunkAdminData.nonce;
        let currentFeaturesData = [];
        let currentGatewaysData = [];
        let currentUsersData = [];
        let currentCategoriesData = [];

        // --- DOM Elements ---
        const userListContainer = document.getElementById('users-list-container');
        const featuresPlansContainer = document.getElementById('features-plans-container');
        const paymentGatewaysContainer = document.getElementById('payment-gateways-container');
        const featureCategoriesContainer = document.getElementById('feature-categories-container'); // <<< NEW
        const addFeatureBtn = document.getElementById('add-feature-btn');
        const addCategoryForm = document.getElementById('add-category-form'); // <<< NEW
        const purchaseModal = document.getElementById('purchase-modal');
        const featureModal = document.getElementById('feature-modal');
        const planModal = document.getElementById('plan-modal');
        const gatewayModal = document.getElementById('gateway-modal');
        const categoryModal = document.getElementById('category-modal'); // <<< NEW
        const modals = [purchaseModal, featureModal, planModal, gatewayModal, categoryModal]; // <<< NEW

        // --- Initial Load ---
        document.addEventListener('DOMContentLoaded', function() {
            setupTabs();
            loadInitialTabData();
            setupEventListeners();
        });

        // --- Event Listener Setup ---
        function setupEventListeners() {
             addFeatureBtn?.addEventListener('click', () => openFeatureModal());
             addCategoryForm?.addEventListener('submit', handleSaveCategory); // <<< NEW: Handle add form submit
             document.getElementById('feature-form')?.addEventListener('submit', handleSaveFeature);
             document.getElementById('plan-form')?.addEventListener('submit', handleSavePlan);
             document.getElementById('gateway-form')?.addEventListener('submit', handleSaveGateway);
             document.getElementById('category-form')?.addEventListener('submit', handleSaveCategory); // <<< NEW: Handle edit form submit
             userListContainer?.addEventListener('click', handleUserListActions);
             featuresPlansContainer?.addEventListener('click', handleFeaturesPlansActions);
             paymentGatewaysContainer?.addEventListener('click', handleGatewaysActions);
             featureCategoriesContainer?.addEventListener('click', handleCategoriesActions); // <<< NEW
             document.getElementById('modal-body-purchase')?.addEventListener('click', handlePurchaseModalActions);
             const userSearch = document.getElementById('user-search');
             if (userSearch) { userSearch.addEventListener('input', debounce(handleUserSearch, 300)); }
             modals.forEach(modal => { if(!modal) return; const closeBtn = modal.querySelector('.modal-close-btn'); const modalId = modal.id; if(closeBtn) closeBtn.addEventListener('click', () => closeModal(modalId)); modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(modalId); }); });
             const paymentTypeRadios = document.querySelectorAll('#plan-form input[name="payment_type"]');
             paymentTypeRadios.forEach(radio => { radio.addEventListener('change', handlePaymentTypeChange); });
             // <<< NEW: Auto-generate slug for category name >>>
             const catNameInput = document.getElementById('new_category_name');
             const catSlugInput = document.getElementById('new_category_slug');
             if (catNameInput && catSlugInput) {
                 catNameInput.addEventListener('input', () => {
                     catSlugInput.value = generateSlug(catNameInput.value);
                 });
             }
             const catEditNameInput = document.getElementById('category_name');
             const catEditSlugInput = document.getElementById('category_slug');
             if (catEditNameInput && catEditSlugInput) {
                  catEditNameInput.addEventListener('input', () => {
                      if(!catEditSlugInput.dataset.editedManually) { // Only auto-update if not manually edited
                         catEditSlugInput.value = generateSlug(catEditNameInput.value);
                      }
                 });
                 // Detect manual slug editing
                 catEditSlugInput.addEventListener('input', () => {
                      catEditSlugInput.dataset.editedManually = 'true';
                 });
             }
        }

        // --- Tab Management ---
        // <<< Modified: Load categories when features tab is active >>>
        function loadTabData(tabId) {
            const container = document.getElementById(`tab-content-${tabId}`)?.querySelector('[id$="-container"]');
            if (container && (container.innerHTML === '' || container.querySelector('.loading-spinner') || container.querySelector('p')?.textContent.includes('will be loaded here'))) {
                console.log(`Loading data for tab: ${tabId}`);
                switch (tabId) {
                    case 'users': fetchUsersList(); break;
                    case 'features':
                        fetchFeaturesAndPlans();
                        fetchCategoriesAdminList(); // <<< Load categories here too
                        break;
                    case 'payments': fetchPaymentGateways(); break;
                }
            } else if (tabId === 'features' && !featureCategoriesContainer.querySelector('table')) {
                 // If feature tab is active but categories haven't loaded (e.g., returning to tab)
                 fetchCategoriesAdminList();
            }
        }
        // ... (setupTabs, switchTab, loadInitialTabData - unchanged) ...
        function setupTabs() { const tabButtons = document.querySelectorAll('.tab-button'); tabButtons.forEach(button => { button.addEventListener('click', () => { const targetTab = button.dataset.tab; switchTab(targetTab); loadTabData(targetTab); }); }); }
        function switchTab(targetTab) { const tabButtons = document.querySelectorAll('.tab-button'); const tabContents = document.querySelectorAll('.tab-content'); tabButtons.forEach(button => button.classList.toggle('active', button.dataset.tab === targetTab)); tabContents.forEach(content => content.classList.toggle('active', content.id === `tab-content-${targetTab}`)); }
        function loadInitialTabData() { const activeTab = document.querySelector('.tab-button.active')?.dataset?.tab; if (activeTab) { loadTabData(activeTab); } }


        // --- Loading & Feedback ---
        // ... (showLoading, showModalLoading, setModalFeedback, showError, showButtonSpinner - unchanged) ...
        function showLoading(element) { if(element) element.innerHTML = '<div class="flex justify-center items-center p-8"><div class="loading-spinner"></div></div>'; }
        function showModalLoading(modalBodyId = 'modal-body-purchase') { const body = document.getElementById(modalBodyId); const feedback = body?.closest('.modal-content')?.querySelector('[id^="modal-feedback-"]'); if (body) body.innerHTML = '<div class="flex justify-center items-center p-8"><div class="loading-spinner"></div></div>'; if (feedback) { feedback.textContent = ''; feedback.className = 'mt-4 text-sm text-right h-5'; } }
        function setModalFeedback(modalId, message, isSuccess) { const feedback = document.getElementById(`modal-feedback-${modalId.replace('-modal','')}`); if(feedback) { feedback.textContent = message; feedback.className = `text-sm text-right mr-auto h-5 ${isSuccess ? 'text-green-700' : 'text-red-700'}`; setTimeout(() => { if(feedback) feedback.textContent = ''; feedback.className = 'text-sm text-right mr-auto h-5'; }, 4000); } }
        function showError(element, message) { if(element) element.innerHTML = `<div class="p-4 text-red-700 bg-red-100 border border-red-300 rounded flex items-start gap-2"><i class="fas fa-exclamation-circle mt-0.5 text-red-500"></i><div>${escapeHTML(message)}</div></div>`; }
        function showButtonSpinner(button, show = true) { const spinner = button?.querySelector('.save-spinner, .update-spinner'); if (spinner) spinner.style.display = show ? 'inline-block' : 'none'; if(button) button.disabled = show; }

        // --- Modal Management ---
        // <<< Modified: Added handling for category modal >>>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.add('active');
            const form = modal.querySelector('form');
            if(form) form.reset();

            if(modalId === 'feature-modal') {
                 const fi = document.getElementById('feature_id'); if(fi) fi.value = '0';
                 const fk = document.getElementById('feature_key'); if(fk) { fk.readOnly = false; fk.classList.remove('bg-gray-100','cursor-not-allowed'); }
                 const categorySelect = document.getElementById('feature_category'); if (categorySelect) categorySelect.value = "";
                 fetchFeatureCategories(); // Fetch categories when opening the modal
            }
            if(modalId === 'plan-modal') {
                const pi = document.getElementById('plan_id'); if(pi) pi.value = '0';
                const ia = document.getElementById('is_active'); if(ia) ia.checked = true;
                const durationWrapper = document.getElementById('plan-duration-wrapper'); if(durationWrapper) durationWrapper.classList.remove('hidden');
                const durationInput = document.getElementById('duration_days'); if(durationInput) { durationInput.value = '30'; durationInput.readOnly = false; }
                const subRadio = modal.querySelector('input[name="payment_type"][value="subscription"]'); if(subRadio) subRadio.checked = true;
            }
            if(modalId === 'category-modal') { // <<< NEW: Reset category modal
                 const catIdInput = document.getElementById('category_id'); if(catIdInput) catIdInput.value = '0';
                 const slugInput = document.getElementById('category_slug'); if(slugInput) delete slugInput.dataset.editedManually; // Reset manual edit flag
            }

            const feedback = modal.querySelector('[id^="modal-feedback-"]');
            if (feedback) { feedback.textContent = ''; feedback.className = 'mt-4 text-sm text-right h-5'; }
        }
        function closeModal(modalId) { const modal = document.getElementById(modalId); if (modal) modal.classList.remove('active'); }

        // --- User & Purchase Functions ---
        // ... (handleUserListActions, handlePurchaseModalActions, fetchUsersList, renderUsersCards, handleUserSearch, openPurchaseModal, renderPurchasesInModal, updatePurchaseStatus - unchanged) ...
        function handleUserListActions(event) { if (event.target.closest('.view-purchases-btn')) { const btn = event.target.closest('.view-purchases-btn'); openPurchaseModal(btn.dataset.userId, btn.dataset.userName); } }
        function handlePurchaseModalActions(event) { const target = event.target; if (target.classList.contains('status-update-select')) { const purchaseId = target.dataset.purchaseId; const updateButton = document.querySelector(`#modal-body-purchase .update-status-btn[data-purchase-id="${purchaseId}"]`); if (updateButton) updateButton.disabled = !target.value; } else if (target.classList.contains('update-status-btn')) { const purchaseId = target.dataset.purchaseId; const selectElement = document.querySelector(`#modal-body-purchase .status-update-select[data-purchase-id="${purchaseId}"]`); const newStatus = selectElement.value; if (purchaseId && newStatus) updatePurchaseStatus(purchaseId, newStatus, target); } }
        function fetchUsersList(searchTerm = '') { showLoading(userListContainer); makeAjaxCall('orunk_admin_get_users_list', { search: searchTerm }) .then(data => { currentUsersData = data.users || []; renderUsersCards(currentUsersData); }) .catch(error => { console.error("Error caught in fetchUsersList:", error); showError(userListContainer, `Error loading users: ${error.message}.`); }); }
        function renderUsersCards(users) { if (!users || users.length === 0) { userListContainer.innerHTML = '<div class="p-4 text-center text-gray-500">No users found.</div>'; return; } let cardsHTML = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">'; users.forEach(user => { const purchaseCount = user.purchase_count !== undefined ? user.purchase_count : 0; cardsHTML += ` <div class="user-card bg-white shadow-md hover:shadow-lg rounded-lg overflow-hidden border border-gray-100 transition-shadow duration-200 flex flex-col"> <div class="user-card-header relative h-20 bg-gradient-to-r from-indigo-50 to-purple-100"> ${purchaseCount > 0 ? `<div class="user-purchase-count absolute top-3 right-3 bg-white/90 backdrop-blur-sm text-indigo-600 text-xs font-semibold w-6 h-6 flex items-center justify-center rounded-full shadow" title="${purchaseCount} Purchases">${purchaseCount}</div>` : ''} <img src="${user.avatar}" alt="${escapeHTML(user.display_name)}" class="user-card-avatar absolute -bottom-6 left-4 w-14 h-14 rounded-full border-4 border-white shadow-lg bg-white"> </div> <div class="user-card-body pt-10 flex-grow"> <h3 class="text-base font-semibold text-gray-800 truncate text-center">${escapeHTML(user.display_name)}</h3> <p class="text-sm text-gray-500 truncate text-center">@${escapeHTML(user.login)}</p> <p class="text-xs text-indigo-600 hover:text-indigo-800 mt-2 text-center truncate"><a href="mailto:${escapeHTML(user.email)}" class="hover:underline">${escapeHTML(user.email)}</a></p> </div> <div class="user-card-footer flex justify-between items-center px-4 py-2 bg-gray-50 border-t border-gray-100"> <a href="${user.edit_link}" target="_blank" title="Edit WP Profile" class="text-xs text-gray-400 hover:text-indigo-600 transition-colors"> <i class="fas fa-user-edit"></i> </a> <button data-user-id="${user.id}" data-user-name="${escapeHTML(user.display_name)}" class="view-purchases-btn button button-primary button-sm !py-1 !px-3 !text-xs"> <i class="fas fa-eye"></i> View Purchases </button> </div> </div>`; }); cardsHTML += '</div>'; userListContainer.innerHTML = cardsHTML; }
        function handleUserSearch(event) { const searchTerm = event.target.value.trim().toLowerCase(); const filteredUsers = currentUsersData.filter(user => user.display_name.toLowerCase().includes(searchTerm) || user.login.toLowerCase().includes(searchTerm) || user.email.toLowerCase().includes(searchTerm)); renderUsersCards(filteredUsers); }
        function openPurchaseModal(userId, userName) { openModal('purchase-modal'); const mc = document.getElementById('purchase-modal').querySelector('.modal-content'); mc.dataset.userId = userId; mc.dataset.userName = userName; document.getElementById('modal-title-purchase').innerHTML = `<i class="fas fa-shopping-cart text-indigo-500 mr-2"></i> Purchases for ${escapeHTML(userName)}`; showModalLoading('modal-body-purchase'); makeAjaxCall('orunk_admin_get_user_purchases', { user_id: userId }) .then(data => renderPurchasesInModal(data.purchases)) .catch(error => showError(document.getElementById('modal-body-purchase'), `Error loading purchases: ${error.message}`)); }
        function renderPurchasesInModal(purchases) { const mbp = document.getElementById('modal-body-purchase'); if (!purchases || purchases.length === 0) { mbp.innerHTML = '<div class="p-4 text-center text-gray-500">No purchases found.</div>'; return; } let listHTML = '<div class="space-y-3 max-h-[65vh] overflow-y-auto pr-2 -mr-1">'; purchases.forEach(p => { const sC = `status-${escapeHTML(p.status)}`; const sB = `<span class="status-badge ${sC}">${escapeHTML(p.status)}</span>`; let psI = ''; if (p.is_switch_pending) { psI = `<div class="my-1 p-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800 flex items-start gap-2"><i class="fas fa-info-circle mt-0.5 text-amber-500"></i><div><strong>Pending Switch To:</strong> ${escapeHTML(p.pending_switch_plan_name || 'N/A')} ${p.can_approve_switch ? '(Bank Transfer - Awaiting Approval)' : ''}</div></div>`; } let sO = `<option value="" disabled selected>-- Change Status --</option>${p.can_approve_switch ? '<option value="approve_switch" class="font-bold text-green-700">Approve Pending Switch</option><option value="" disabled>-----</option>' : ''}<option value="pending" ${p.status === 'pending' ? 'disabled' : ''}>Set Pending</option><option value="active" ${p.status === 'active' ? 'disabled' : ''}>Set Active</option><option value="expired" ${p.status === 'expired' ? 'disabled' : ''}>Set Expired</option><option value="cancelled" ${p.status === 'cancelled' ? 'disabled' : ''}>Set Cancelled</option><option value="failed" ${p.status === 'failed' ? 'disabled' : ''}>Set Failed</option>`; listHTML += `<div class="border rounded-md p-3 shadow-sm bg-white hover:shadow-md transition-shadow duration-150" id="purchase-item-${p.id}"><div class="flex justify-between items-start mb-2"><div class="flex-1 min-w-0"><strong class="text-sm font-semibold text-indigo-700 block">${escapeHTML(p.plan_name)}</strong><div class="text-xs text-gray-500 mt-0.5"><span class="inline-block mr-3" title="Feature Key"><i class="fas fa-cube mr-1 opacity-60"></i>${escapeHTML(p.feature_key)}</span><span class="inline-block" title="Purchase ID"><i class="fas fa-hashtag mr-1 opacity-60"></i>${p.id}</span></div></div><div class="text-right flex-shrink-0 ml-2">${sB}</div></div><div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-600 mb-2"><div class="flex items-center" title="Purchase Date"><i class="fas fa-calendar-plus mr-1.5 w-3 text-center opacity-60"></i>${escapeHTML(p.purchase_date)}</div><div class="flex items-center" title="Expiry Date"><i class="fas fa-calendar-times mr-1.5 w-3 text-center opacity-60"></i><span class="expiry-date">${escapeHTML(p.expiry_date)}</span></div><div class="flex items-center" title="Payment Gateway"><i class="fas fa-credit-card mr-1.5 w-3 text-center opacity-60"></i>${escapeHTML(p.gateway)}</div><div class="flex items-center" title="Transaction ID"><i class="fas fa-receipt mr-1.5 w-3 text-center opacity-60"></i>${escapeHTML(p.transaction_id)}</div></div>${p.api_key_masked ? `<div class="text-xs text-gray-500 mb-2 flex items-center"><i class="fas fa-key mr-1.5 w-3 text-center opacity-60"></i>API Key: <code class="ml-1 bg-gray-100 px-1.5 py-0.5 rounded font-mono text-indigo-800">${escapeHTML(p.api_key_masked)}</code></div>` : ''}${psI}<div class="mt-3 flex items-center space-x-2"><select data-purchase-id="${p.id}" class="status-update-select form-select !text-xs !py-1 !px-2 !max-w-[180px] rounded-md shadow-sm">${sO}</select><button data-purchase-id="${p.id}" class="update-status-btn button button-primary button-sm" disabled>Update <span class="update-spinner" style="display: none;"><div class="loading-spinner !w-4 !h-4 !border-2"></div></span></button></div><div class="update-feedback text-xs mt-1 h-4"></div></div>`; }); listHTML += '</div>'; mbp.innerHTML = listHTML; }
        function updatePurchaseStatus(purchaseId, newStatus, buttonElement) { const listItem = document.getElementById(`purchase-item-${purchaseId}`); const feedbackDiv = listItem.querySelector('.update-feedback'); const spinner = buttonElement.querySelector('.update-spinner'); const selectElement = listItem.querySelector('.status-update-select'); feedbackDiv.textContent = ''; feedbackDiv.className = 'update-feedback text-xs mt-1 h-4'; showButtonSpinner(buttonElement, true); selectElement.disabled = true; makeAjaxCall('orunk_admin_update_purchase_status', { purchase_id: purchaseId, status: newStatus }) .then(data => { setModalFeedback('purchase-modal', data.message || 'Status updated!', true); const statusBadge = listItem.querySelector('.status-badge'); if (statusBadge) { statusBadge.textContent = data.updated_status; statusBadge.className = `status-badge status-${data.updated_status}`; } const expiryDateSpan = listItem.querySelector('.expiry-date'); if (expiryDateSpan) { expiryDateSpan.textContent = data.updated_expiry; } selectElement.value = ""; if (newStatus === 'approve_switch') { const modalContent = document.getElementById('purchase-modal').querySelector('.modal-content'); openPurchaseModal(modalContent.dataset.userId, modalContent.dataset.userName); } }) .catch(error => { console.error('Update Status Error:', error); setModalFeedback('purchase-modal', error.message || 'Update failed.', false); }) .finally(() => { showButtonSpinner(buttonElement, false); selectElement.disabled = false; buttonElement.disabled = true; }); }

        // --- Feature & Plan Functions ---
        function handleFeaturesPlansActions(event) { const target = event.target.closest('button'); if (!target) return; if (target.classList.contains('add-plan-btn')) { openPlanModal(target.dataset.featureKey); } else if (target.classList.contains('edit-feature-btn')) { openFeatureModal(target.dataset.featureId); } else if (target.classList.contains('delete-feature-btn')) { deleteFeature(target.dataset.featureId, target.dataset.featureName); } else if (target.classList.contains('edit-plan-btn')) { openPlanModal(target.dataset.featureKey, target.dataset.planId); } else if (target.classList.contains('delete-plan-btn')) { deletePlan(target.dataset.planId, target.dataset.planName); } }
        function fetchFeaturesAndPlans() { showLoading(featuresPlansContainer); makeAjaxCall('orunk_admin_get_features_plans', {}) .then(data => { currentFeaturesData = data.features || []; renderFeaturesAndPlans(currentFeaturesData); }) .catch(error => showError(featuresPlansContainer, `Error loading features: ${error.message}`)); }
        function renderFeaturesAndPlans(features) { if (!features || features.length === 0) { featuresPlansContainer.innerHTML = '<div class="p-4 text-center text-gray-500">No features defined yet. Click "Add New Feature" above to begin.</div>'; return; } let html = '<div class="space-y-6">'; features.forEach(feature => { const categoryName = currentCategoriesData.find(c => c.category_slug === feature.category)?.category_name || feature.category || 'Uncategorized'; html += `<div class="card" id="feature-section-${feature.id}"><div class="card-header !bg-white"><div class="flex-1 min-w-0"><div class="flex items-center gap-3 flex-wrap"><i class="fas fa-cube text-lg text-indigo-500"></i><h3 class="text-lg font-semibold text-gray-800">${escapeHTML(feature.product_name)}</h3><code class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded border border-gray-200">${escapeHTML(feature.feature)}</code><span class="text-xs font-medium bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full border border-blue-200">${escapeHTML(categoryName)}</span></div><p class="text-sm text-gray-600 mt-1 ml-7">${escapeHTML(feature.description)}</p></div><div class="flex items-center gap-2 flex-shrink-0"><button data-feature-id="${feature.id}" class="edit-feature-btn button button-secondary button-sm"><i class="fas fa-pencil-alt"></i> Edit</button><button data-feature-id="${feature.id}" data-feature-name="${escapeHTML(feature.product_name)}" class="delete-feature-btn button button-danger button-sm"><i class="fas fa-trash-alt"></i> Delete</button><button data-feature-key="${feature.feature}" class="add-plan-btn button button-primary button-sm"><i class="fas fa-plus"></i> Add Plan</button></div></div><div class="card-body !pt-0 !px-0">${renderPlansTable(feature.plans, feature.feature)}</div></div>`; }); html += '</div>'; featuresPlansContainer.innerHTML = html; }
        function renderPlansTable(plans, featureKey) { if (!plans || plans.length === 0) { return '<div class="px-6 py-4 text-sm text-center text-gray-500 italic">No plans defined for this feature.</div>'; } let tableHtml = `<div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Name</th><th>Price</th><th>Type</th><th>Duration</th><th>Limits (D/M)</th><th>Status</th><th>Actions</th></tr></thead><tbody>`; plans.forEach(plan => { const statusBadge = `<span class="status-badge ${plan.is_active == '1' ? 'status-active' : 'status-inactive'}">${plan.is_active == '1' ? 'Active' : 'Inactive'}</span>`; const paymentType = plan.is_one_time == '1' ? '<span title="One-Time Payment" class="text-purple-700 font-medium">One-Time</span>' : '<span title="Subscription" class="text-blue-700">Subscription</span>'; const durationDisplay = plan.is_one_time == '1' ? 'Lifetime' : `${escapeHTML(plan.duration_days)} days`; tableHtml += `<tr id="plan-row-${plan.id}"><td class="font-medium text-gray-900">${escapeHTML(plan.plan_name)}</td><td class="text-gray-700">$${escapeHTML(parseFloat(plan.price).toFixed(2))}</td><td class="text-sm">${paymentType}</td><td class="text-gray-700">${durationDisplay}</td><td class="text-gray-700">${escapeHTML(plan.requests_per_day ?? 'N/A')} / ${escapeHTML(plan.requests_per_month ?? 'N/A')}</td><td>${statusBadge}</td><td class="space-x-2"><button data-plan-id="${plan.id}" data-feature-key="${featureKey}" class="edit-plan-btn button button-link button-sm !text-xs"><i class="fas fa-pencil-alt mr-1"></i>Edit</button><button data-plan-id="${plan.id}" data-plan-name="${escapeHTML(plan.plan_name)}" class="delete-plan-btn button button-link-danger button-sm !text-xs"><i class="fas fa-trash-alt mr-1"></i>Delete</button></td></tr>`; }); tableHtml += `</tbody></table></div>`; return tableHtml; }

        // *** ADDED: openFeatureModal Definition ***
        function openFeatureModal(featureId = 0) {
             openModal('feature-modal');
             const form = document.getElementById('feature-form');
             const title = document.getElementById('modal-title-feature');
             const keyInput = document.getElementById('feature_key');
             const categorySelect = document.getElementById('feature_category');

             fetchFeatureCategories(); // Fetch categories every time

             if (featureId > 0) {
                 title.innerHTML = '<i class="fas fa-cube text-indigo-500 mr-2"></i> Edit Feature';
                 const feature = currentFeaturesData.find(f => f.id == featureId);
                 if (feature) {
                     form.feature_id.value = feature.id;
                     keyInput.value = feature.feature;
                     keyInput.readOnly = true; // Key cannot be changed
                     keyInput.classList.add('bg-gray-100', 'cursor-not-allowed');
                     form.product_name.value = feature.product_name;
                     form.description.value = feature.description;
                     categorySelect.value = feature.category || ""; // Select correct category
                 } else {
                      setModalFeedback('feature-modal', 'Error: Could not find feature data.', false);
                      closeModal('feature-modal');
                 }
             } else {
                 title.innerHTML = '<i class="fas fa-cube text-indigo-500 mr-2"></i> Add New Feature';
                 keyInput.readOnly = false;
                 keyInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
                 form.reset(); // Reset form fields for adding new
                 form.feature_id.value = '0';
                 categorySelect.value = ""; // Reset category dropdown
             }
        }
        // *** END: openFeatureModal Definition ***

        function handleSaveFeature(event) { /* ... (unchanged) ... */ event.preventDefault(); const form = event.target; const button = form.querySelector('button[type="submit"]'); showButtonSpinner(button); setModalFeedback('feature-modal', '', true); const formData = new FormData(form); makeAjaxCall('orunk_admin_save_feature', {}, 'POST', formData) .then(data => { setModalFeedback('feature-modal', data.message || 'Feature saved!', true); fetchFeaturesAndPlans(); setTimeout(() => closeModal('feature-modal'), 1500); }) .catch(error => setModalFeedback('feature-modal', error.message || 'Save failed.', false)) .finally(() => showButtonSpinner(button, false)); }
        function deleteFeature(featureId, featureName) { /* ... (unchanged) ... */ if (!confirm(`ARE YOU SURE?\n\nDelete the feature "${featureName}" AND all its associated plans?\n\nThis action cannot be undone.`)) return; makeAjaxCall('orunk_admin_delete_feature', { feature_id: featureId }) .then(data => { console.log(data.message); const featureSection = document.getElementById(`feature-section-${featureId}`); if (featureSection) { featureSection.style.transition = 'opacity 0.3s ease'; featureSection.style.opacity = '0'; setTimeout(() => featureSection.remove(), 300); } currentFeaturesData = currentFeaturesData.filter(f => f.id != featureId); /* Optionally show global success message */ }) .catch(error => alert(`Error deleting feature: ${error.message}`)); }
        function handlePaymentTypeChange(event) { /* ... (unchanged) ... */ const paymentType = event.target.value; const durationWrapper = document.getElementById('plan-duration-wrapper'); const durationInput = document.getElementById('duration_days'); if (paymentType === 'one_time') { durationInput.value = '9999'; durationInput.readOnly = true; durationWrapper.classList.add('opacity-50', 'cursor-not-allowed'); } else { if (durationInput.value === '9999') { durationInput.value = '30'; } durationInput.readOnly = false; durationWrapper.classList.remove('opacity-50', 'cursor-not-allowed'); } }
        // <<< Modified: Calls handlePaymentTypeChange >>>
        function openPlanModal(featureKey, planId = 0) { openModal('plan-modal'); const form = document.getElementById('plan-form'); const title = document.getElementById('modal-title-plan'); form.plan_feature_key.value = featureKey; if (planId > 0) { title.innerHTML = `<i class="fas fa-tags text-indigo-500 mr-2"></i> Edit Plan for ${escapeHTML(featureKey)}`; let plan = null; const feature = currentFeaturesData.find(f => f.feature === featureKey); if (feature && feature.plans) { plan = feature.plans.find(p => p.id == planId); } if (plan) { form.plan_id.value = plan.id; form.plan_name.value = plan.plan_name; form.description.value = plan.description; form.price.value = parseFloat(plan.price).toFixed(2); form.duration_days.value = plan.duration_days; form.requests_per_day.value = plan.requests_per_day ?? ''; form.requests_per_month.value = plan.requests_per_month ?? ''; form.is_active.checked = (plan.is_active == '1'); const isOneTime = plan.is_one_time == '1'; form.querySelector(`input[name="payment_type"][value="${isOneTime ? 'one_time' : 'subscription'}"]`).checked = true; handlePaymentTypeChange({ target: form.querySelector(`input[name="payment_type"][value="${isOneTime ? 'one_time' : 'subscription'}"]`) }); } else { setModalFeedback('plan-modal', 'Error: Could not find plan data.', false); closeModal('plan-modal'); return; } } else { title.innerHTML = `<i class="fas fa-tags text-indigo-500 mr-2"></i> Add New Plan for ${escapeHTML(featureKey)}`; handlePaymentTypeChange({ target: form.querySelector('input[name="payment_type"][value="subscription"]') }); } }
        function handleSavePlan(event) { /* ... (unchanged) ... */ event.preventDefault(); const form = event.target; const button = form.querySelector('button[type="submit"]'); showButtonSpinner(button); setModalFeedback('plan-modal', '', true); const formData = new FormData(form); const paymentType = form.querySelector('input[name="payment_type"]:checked').value; const isOneTime = (paymentType === 'one_time') ? '1' : '0'; formData.append('is_one_time', isOneTime); if (!formData.has('is_active')) { formData.append('is_active', '0'); } formData.delete('payment_type'); if (isOneTime === '1') { formData.set('duration_days', '9999'); } makeAjaxCall('orunk_admin_save_plan', {}, 'POST', formData) .then(data => { setModalFeedback('plan-modal', data.message || 'Plan saved!', true); fetchFeaturesAndPlans(); setTimeout(() => closeModal('plan-modal'), 1500); }) .catch(error => setModalFeedback('plan-modal', error.message || 'Save failed.', false)) .finally(() => showButtonSpinner(button, false)); }
        function deletePlan(planId, planName) { /* ... (unchanged) ... */ if (!confirm(`Are you sure you want to delete the plan "${planName}"?`)) return; makeAjaxCall('orunk_admin_delete_plan', { plan_id: planId }) .then(data => { console.log(data.message); const planRow = document.getElementById(`plan-row-${planId}`); if (planRow) { planRow.style.transition = 'opacity 0.3s ease'; planRow.style.opacity = '0'; setTimeout(() => planRow.remove(), 300); } fetchFeaturesAndPlans(); }) .catch(error => alert(`Error deleting plan: ${error.message}`)); }

        // --- Category Functions ---
        // <<< NEW: Event listener for category actions >>>
        function handleCategoriesActions(event) {
             const target = event.target.closest('button');
             if (!target) return;
             if (target.classList.contains('edit-category-btn')) {
                 openCategoryModal(target.dataset.categoryId);
             } else if (target.classList.contains('delete-category-btn')) {
                 deleteCategory(target.dataset.categoryId, target.dataset.categoryName);
             }
        }

        // Fetches categories for Feature modal dropdown
        function fetchFeatureCategories() { /* ... (unchanged) ... */ const categorySelect = document.getElementById('feature_category'); if (currentCategoriesData.length > 0 && categorySelect && categorySelect.options.length > 1) { return; } makeAjaxCall('orunk_admin_get_categories', {}) .then(data => { currentCategoriesData = data.categories || []; populateCategoryDropdown(currentCategoriesData); }) .catch(error => { console.error("Error fetching categories:", error); setModalFeedback('feature-modal', 'Error loading categories.', false); populateCategoryDropdown([]); }); }
        // Populates category dropdown in Feature modal
        function populateCategoryDropdown(categories) { /* ... (unchanged) ... */ const selectElement = document.getElementById('feature_category'); if (!selectElement) return; selectElement.innerHTML = '<option value="">-- Select Category --</option>'; if (categories.length === 0) { selectElement.innerHTML += '<option value="" disabled>No categories found</option>'; return; } categories.forEach(cat => { const option = document.createElement('option'); option.value = cat.category_slug; option.textContent = cat.category_name; selectElement.appendChild(option); }); }

        // <<< NEW: Fetch and render category admin list >>>
        function fetchCategoriesAdminList() {
             if (!featureCategoriesContainer) return; // Make sure container exists
             showLoading(featureCategoriesContainer);
             makeAjaxCall('orunk_admin_get_categories', {})
                .then(data => {
                    currentCategoriesData = data.categories || []; // Update cache
                    renderCategoriesTable(currentCategoriesData);
                    // Also update feature modal dropdown if it's open (or pre-populate)
                    populateCategoryDropdown(currentCategoriesData);
                })
                .catch(error => showError(featureCategoriesContainer, `Error loading categories: ${error.message}`));
        }

        // <<< NEW: Render category table >>>
        function renderCategoriesTable(categories) {
             if (!featureCategoriesContainer) return;
             if (!categories || categories.length === 0) {
                 featureCategoriesContainer.innerHTML = '<div class="p-4 text-center text-sm text-gray-500">No categories created yet. Use the form below to add one.</div>';
                 return;
             }
             let tableHTML = `<div class="table-wrapper"><table id="feature-categories-table" class="data-table"><thead><tr><th>Name</th><th>Slug</th><th>Actions</th></tr></thead><tbody>`;
             categories.forEach(cat => {
                tableHTML += `<tr id="category-row-${cat.id}">
                                 <td>${escapeHTML(cat.category_name)}</td>
                                 <td><code>${escapeHTML(cat.category_slug)}</code></td>
                                 <td class="space-x-2">
                                     <button data-category-id="${cat.id}" class="edit-category-btn button button-link button-sm !text-xs"><i class="fas fa-pencil-alt mr-1"></i>Edit</button>
                                     <button data-category-id="${cat.id}" data-category-name="${escapeHTML(cat.category_name)}" class="delete-category-btn button button-link-danger button-sm !text-xs"><i class="fas fa-trash-alt mr-1"></i>Delete</button>
                                 </td>
                             </tr>`;
             });
             tableHTML += `</tbody></table></div>`;
             featureCategoriesContainer.innerHTML = tableHTML;
        }

        // <<< NEW: Open category add/edit modal >>>
        function openCategoryModal(categoryId = 0) {
             openModal('category-modal'); // Resets form, clears ID
             const form = document.getElementById('category-form');
             const title = document.getElementById('modal-title-category');
             const slugInput = document.getElementById('category_slug');
             delete slugInput.dataset.editedManually; // Reset manual edit flag

             if (categoryId > 0) { // Editing
                 title.innerHTML = '<i class="fas fa-tags text-indigo-500 mr-2"></i> Edit Category';
                 const category = currentCategoriesData.find(c => c.id == categoryId);
                 if (category) {
                     form.category_id.value = category.id;
                     form.category_name.value = category.category_name;
                     form.category_slug.value = category.category_slug;
                 } else {
                      setModalFeedback('category-modal', 'Error: Category not found.', false);
                      closeModal('category-modal');
                 }
             } else { // Adding
                 title.innerHTML = '<i class="fas fa-tags text-indigo-500 mr-2"></i> Add New Category';
             }
        }

         // <<< NEW: Handle category save (Add/Edit) >>>
         function handleSaveCategory(event) {
             event.preventDefault();
             const form = event.target.id === 'add-category-form' ? document.getElementById('add-category-form') : document.getElementById('category-form');
             const button = form.querySelector('button[type="submit"]');
             const feedbackId = (form.id === 'add-category-form') ? 'add-category-feedback' : 'modal-feedback-category';
             const modalToClose = (form.id === 'add-category-form') ? null : 'category-modal'; // Only close modal if it's the edit form

             showButtonSpinner(button);
             const feedbackDiv = document.getElementById(feedbackId);
             if(feedbackDiv) feedbackDiv.textContent = '';

             const formData = new FormData(form);
             // Manually get values if using add form (not in modal)
             if (form.id === 'add-category-form') {
                 formData.append('category_id', '0'); // Indicate adding
                 formData.append('category_name', form.new_category_name.value);
                 formData.append('category_slug', form.new_category_slug.value);
             }

             makeAjaxCall('orunk_admin_save_category', {}, 'POST', formData)
                .then(data => {
                    if(feedbackDiv) {
                        feedbackDiv.textContent = data.message || 'Category saved!';
                        feedbackDiv.className = 'text-sm mt-2 h-5 text-green-700';
                    }
                    fetchCategoriesAdminList(); // Refresh the list
                    fetchFeatureCategories(); // Refresh dropdown options
                    if (modalToClose) { setTimeout(() => closeModal(modalToClose), 1500); }
                    else { form.reset(); } // Reset add form
                })
                .catch(error => {
                    if(feedbackDiv) {
                        feedbackDiv.textContent = error.message || 'Save failed.';
                        feedbackDiv.className = 'text-sm mt-2 h-5 text-red-700';
                    }
                })
                .finally(() => {
                     showButtonSpinner(button, false);
                     setTimeout(() => { if(feedbackDiv) feedbackDiv.textContent = ''; }, 4000);
                 });
         }

        // <<< NEW: Handle category delete >>>
        function deleteCategory(categoryId, categoryName) {
            if (!confirm(`Are you sure you want to delete the category "${categoryName}"?\n\nFeatures currently using this category might become uncategorized.`)) return;

            makeAjaxCall('orunk_admin_delete_category', { category_id: categoryId })
                .then(data => {
                    console.log(data.message);
                    const categoryRow = document.getElementById(`category-row-${categoryId}`);
                    if (categoryRow) {
                        categoryRow.style.transition = 'opacity 0.3s ease';
                        categoryRow.style.opacity = '0';
                        setTimeout(() => categoryRow.remove(), 300);
                    }
                    fetchCategoriesAdminList(); // Refresh list
                    fetchFeatureCategories(); // Refresh dropdown
                })
                .catch(error => alert(`Error deleting category: ${error.message}`));
        }

        // --- Payment Gateway Functions ---
        // ... (handleGatewaysActions, fetchPaymentGateways, renderPaymentGateways, openGatewayModal, renderGatewaySettingsForm, handleSaveGateway - unchanged) ...
        function handleGatewaysActions(event) { const target = event.target.closest('button'); if (!target) return; if (target.classList.contains('manage-gateway-btn')) { openGatewayModal(target.dataset.gatewayId); } }
        function fetchPaymentGateways() { showLoading(paymentGatewaysContainer); makeAjaxCall('orunk_admin_get_gateways', {}) .then(data => { currentGatewaysData = data.gateways || []; renderPaymentGateways(currentGatewaysData); }) .catch(error => showError(paymentGatewaysContainer, `Error loading gateways: ${error.message}`)); }
        function renderPaymentGateways(gateways) { if (!gateways || gateways.length === 0) { paymentGatewaysContainer.innerHTML = '<div class="p-4 text-center text-gray-500">No payment gateways found.</div>'; return; } let listHTML = '<div class="space-y-4">'; gateways.forEach(gw => { const statusBadge = `<span class="status-badge ${gw.enabled ? 'status-active' : 'status-inactive'}">${gw.enabled ? 'Enabled' : 'Disabled'}</span>`; listHTML += `<div class="border rounded-lg p-4 shadow-sm bg-white hover:shadow-md transition-shadow duration-150"><div class="flex justify-between items-start flex-wrap gap-4"><div class="flex-1 min-w-0"><div class="flex items-center gap-2 mb-1"><strong class="text-base font-semibold text-gray-800">${escapeHTML(gw.title)}</strong><code class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded border border-gray-200">${escapeHTML(gw.id)}</code></div><p class="text-sm text-gray-600">${escapeHTML(gw.description)}</p></div><div class="flex items-center gap-3 flex-shrink-0">${statusBadge}<button data-gateway-id="${gw.id}" class="manage-gateway-btn button button-secondary button-sm"><i class="fas fa-cog"></i> Manage</button></div></div></div>`; }); listHTML += '</div>'; paymentGatewaysContainer.innerHTML = listHTML; }
        function openGatewayModal(gatewayId) { openModal('gateway-modal'); document.getElementById('modal-title-gateway').textContent = `Settings`; document.getElementById('gateway_id_hidden').value = gatewayId; const modalBodyGateway = document.getElementById('modal-body-gateway'); showModalLoading('modal-body-gateway'); const gateway = currentGatewaysData.find(g => g.id === gatewayId); if (!gateway) { showError(modalBodyGateway, 'Gateway data not found.'); return; } document.getElementById('modal-title-gateway').innerHTML = `<i class="fas fa-cog text-indigo-500 mr-2"></i> ${escapeHTML(gateway.title)} Settings`; renderGatewaySettingsForm(gateway.form_fields, gateway.settings); }
        function renderGatewaySettingsForm(formFields, currentSettings) { const container = document.getElementById('modal-body-gateway'); if (!formFields || Object.keys(formFields).length === 0) { container.innerHTML = '<div class="p-4 text-center text-gray-500">No configurable settings for this gateway.</div>'; return; } let formHTML = ''; container.innerHTML = ''; for (const key in formFields) { const field = formFields[key]; const settingsObj = typeof currentSettings === 'object' && currentSettings !== null ? currentSettings : {}; const value = settingsObj[key] !== undefined ? settingsObj[key] : (field.default !== undefined ? field.default : ''); const type = field.type || 'text'; const fieldId = `gw_setting_${key}`; const fieldName = `settings[${key}]`; const descriptionHTML = field.description ? `<p class="form-description">${field.description}</p>` : ''; let fieldHTML = ''; if (type === 'title') { fieldHTML = `<div class="pt-2 pb-1 border-b border-gray-200 mb-3"><h4 class="text-md font-semibold text-gray-600">${escapeHTML(field.title || '')}</h4>${descriptionHTML}</div>`; container.innerHTML += fieldHTML; continue; } fieldHTML += `<div class="mb-4 setting-field-wrapper">`; fieldHTML += `<label for="${fieldId}" class="form-label">${escapeHTML(field.title || key)}</label>`; switch (type) { case 'text': case 'email': case 'password': case 'number': fieldHTML += `<input type="${type}" name="${fieldName}" id="${fieldId}" value="${escapeHTML(value)}" class="form-input !text-sm">`; break; case 'textarea': fieldHTML += `<textarea name="${fieldName}" id="${fieldId}" rows="4" class="form-textarea !text-sm">${escapeHTML(value)}</textarea>`; break; case 'checkbox': const checked = value === 'yes' ? 'checked' : ''; fieldHTML += `<label class="mt-1 inline-flex items-center"><input type="checkbox" name="${fieldName}" id="${fieldId}" value="yes" ${checked} class="form-checkbox"><span class="ml-2 text-sm text-gray-600">${escapeHTML(field.label || '')}</span></label>`; break; case 'select': fieldHTML += `<select name="${fieldName}" id="${fieldId}" class="form-select !text-sm">`; if (field.options) { for(const optKey in field.options) { const selected = optKey == value ? 'selected' : ''; fieldHTML += `<option value="${escapeHTML(optKey)}" ${selected}>${escapeHTML(field.options[optKey])}</option>`; } } fieldHTML += `</select>`; break; default: fieldHTML += ``; break; } fieldHTML += descriptionHTML; fieldHTML += '</div>'; container.innerHTML += fieldHTML; } }
        function handleSaveGateway(event) { event.preventDefault(); const form = event.target; const button = form.querySelector('button[type="submit"]'); showButtonSpinner(button); setModalFeedback('gateway-modal', '', true); const formData = new FormData(form); makeAjaxCall('orunk_admin_save_gateway_settings', {}, 'POST', formData) .then(data => { setModalFeedback('gateway-modal', data.message || 'Settings saved!', true); fetchPaymentGateways(); setTimeout(() => closeModal('gateway-modal'), 1500); }) .catch(error => setModalFeedback('gateway-modal', error.message || 'Save failed.', false)) .finally(() => showButtonSpinner(button, false)); }

        // --- Utility Functions ---
        // <<< NEW: Generate Slug Function >>>
         function generateSlug(text) {
             return text.toString().toLowerCase()
                .replace(/\s+/g, '-')           // Replace spaces with -
                .replace(/[^\w-]+/g, '')       // Remove all non-word chars except -
                .replace(/--+/g, '-')         // Replace multiple - with single -
                .replace(/^-+/, '')             // Trim - from start of text
                .replace(/-+$/, '');            // Trim - from end of text
        }
        // ... (escapeHTML, debounce, makeAjaxCall - unchanged) ...
        function escapeHTML(str) { if (str === null || typeof str === 'undefined') return ''; const div = document.createElement('div'); div.textContent = str; return div.innerHTML; }
        function debounce(func, wait) { let timeout; return function(...args) { const context = this; clearTimeout(timeout); timeout = setTimeout(() => func.apply(context, args), wait); }; }
        function makeAjaxCall(action, params = {}, method = 'POST', body = null) { return new Promise((resolve, reject) => { let dataToSend; let url = ajaxurl; const effectiveMethod = method.toUpperCase(); if (effectiveMethod === 'POST') { dataToSend = (body instanceof FormData) ? body : new FormData(); if (!dataToSend.has('action') && action) dataToSend.append('action', action); if (!dataToSend.has('nonce')) dataToSend.append('nonce', adminNonce); if (!(body instanceof FormData)) { for (const key in params) { dataToSend.append(key, params[key]); } } } else { const urlParams = new URLSearchParams(); urlParams.append('action', action); urlParams.append('nonce', adminNonce); for (const key in params) { urlParams.append(key, params[key]); } url = `${ajaxurl}?${urlParams.toString()}`; dataToSend = null; } fetch(url, { method: effectiveMethod, body: dataToSend }) .then(response => { if (!response.ok) { return response.text().then(text => { throw new Error(`HTTP error ${response.status}: ${text || response.statusText}`); }); } return response.json(); }) .then(data => { if (data.success) { resolve(data.data); } else { reject(new Error(data.data?.message || 'AJAX error: Operation failed.')); } }) .catch(error => { console.error('AJAX Error:', error); reject(error); }); }); }

    </script>

</body>
</html>