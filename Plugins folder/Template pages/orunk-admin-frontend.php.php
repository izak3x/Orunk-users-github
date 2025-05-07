<?php
/**
 * Template Name: Orunk Frontend Admin Interface
 * Template Post Type: page
 *
 * This template provides a custom frontend admin interface for the Orunk Users plugin.
 * Styles and main JavaScript logic are loaded from external files.
 * It uses Tailwind CSS (via CDN) and Font Awesome.
 *
 * @package OrunkUsers
 * @version 1.2.2 // Added static metric fields to feature modal
 */

// Security check: Ensure the user has the appropriate capabilities.
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title( '|', true, 'right' ); ?> <?php bloginfo( 'name' ); ?> - Orunk Admin</title>
    <?php // Using Tailwind CDN with forms plugin ?>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <?php // Using Font Awesome for icons ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php
    wp_head();
    ?>
    <style>
        /* Minimal critical styles */
        .loading-placeholder { display: flex; justify-content: center; align-items: center; padding: 2rem; color: #6b7280; }
        .loading-spinner { border: 3px solid rgba(0, 0, 0, .1); border-left-color: #4f46e5; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .hidden { display: none !important; }
    </style>
</head>
<body <?php body_class('bg-gray-100'); ?>>

<div id="orunk-frontend-admin-panel-wrapper" class="container mx-auto px-4 py-6 md:py-8">

    <header class="admin-panel-main-header flex flex-col md:flex-row justify-between items-start md:items-center mb-8 pb-4 border-b border-gray-200">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
                Orunk Frontend Admin
            </h1>
            <p class="text-sm text-gray-500 mt-1">Manage users, features, plans, and payment gateways.</p>
        </div>
        <?php $current_user_obj = wp_get_current_user(); ?>
        <div class="flex items-center gap-3 mt-4 md:mt-0">
            <?php echo get_avatar($current_user_obj->ID, 36, '', esc_attr($current_user_obj->display_name), ['class' => 'rounded-full shadow-sm']); ?>
            <div>
                <p class="text-sm font-medium text-gray-800"><?php echo esc_html($current_user_obj->display_name); ?></p>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="text-xs text-indigo-600 hover:text-indigo-800"><?php esc_html_e('Log Out', 'orunk-users'); ?></a>
            </div>
        </div>
    </header>

    <nav class="admin-panel-main-nav mb-6 border-b border-gray-200" aria-label="Tabs">
        <div class="flex space-x-1">
            <button data-tab="users-purchases" class="tab-button active">
                <i class="fas fa-users mr-1.5 opacity-75"></i> Users & Purchases
            </button>
            <button data-tab="features-plans" class="tab-button">
                <i class="fas fa-cubes mr-1.5 opacity-75"></i> Features, Plans & Categories
            </button>
            <button data-tab="payment-gateways" class="tab-button">
                <i class="fas fa-credit-card mr-1.5 opacity-75"></i> Payment Gateways
            </button>
        </div>
    </nav>

    <div id="admin-content-area">
        <div id="tab-content-users-purchases" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h2 class="text-lg font-semibold text-gray-800">User Management</h2>
                    <input type="text" id="user-search-input" placeholder="Search users..." class="form-input !text-sm !py-1.5 !w-64 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <div class="card-body">
                    <div id="users-list-container" class="min-h-[200px] relative">
                        <div class="loading-placeholder">Loading users...</div>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-content-features-plans" class="tab-content">
            <div class="card mb-8">
                 <div class="card-header">
                    <h2 class="text-lg font-semibold text-gray-800">Features & Plans</h2>
                    <button id="add-new-feature-btn" class="button button-primary button-sm">
                        <i class="fas fa-plus"></i> Add New Feature
                    </button>
                 </div>
                 <div class="card-body">
                    <div id="features-plans-list-container" class="min-h-[200px] relative">
                        <div class="loading-placeholder">Loading features & plans...</div>
                    </div>
                 </div>
             </div>

             <div class="card">
                 <div class="card-header">
                    <h2 class="text-lg font-semibold text-gray-800">Manage Feature Categories</h2>
                 </div>
                 <div class="card-body">
                    <div id="feature-categories-list-container" class="min-h-[100px] relative">
                         <div class="loading-placeholder">Loading categories...</div>
                    </div>
                    <form id="orunk-add-category-form" class="mt-6 pt-4 border-t border-gray-200">
                         <h4 class="text-md font-medium text-gray-700 mb-3">Add New Category</h4>
                         <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                             <div>
                                 <label for="new-category-name-input" class="form-label">Name <span class="text-red-500">*</span></label>
                                 <input type="text" name="new_category_name" id="new-category-name-input" class="form-input !text-sm" required>
                             </div>
                              <div>
                                 <label for="new-category-slug-input" class="form-label">Slug <span class="text-red-500">*</span></label>
                                 <input type="text" name="new_category_slug" id="new-category-slug-input" class="form-input !text-sm slug-input" required pattern="[a-z0-9-]+" title="Lowercase letters, numbers, and hyphens only.">
                                  <p class="form-description">Unique identifier.</p>
                             </div>
                             <div>
                                  <button type="submit" class="button button-primary w-full sm:w-auto">
                                      <i class="fas fa-plus"></i> <span class="button-text-label">Add Category</span>
                                      <span class="save-spinner hidden"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                                  </button>
                             </div>
                         </div>
                          <div id="add-category-form-feedback" class="text-sm mt-2 h-5"></div>
                     </form>
                 </div>
             </div>
        </div>

        <div id="tab-content-payment-gateways" class="tab-content">
             <div class="card">
                 <div class="card-header">
                    <h2 class="text-lg font-semibold text-gray-800">Payment Gateways</h2>
                 </div>
                 <div class="card-body">
                    <div id="payment-gateways-list-container" class="min-h-[200px] relative">
                        <div class="loading-placeholder">Loading payment gateways...</div>
                    </div>
                 </div>
             </div>
        </div>
    </div>

    <?php // MODALS ?>

    <div id="user-purchases-modal" class="modal-overlay">
        <div class="modal-content !max-w-3xl">
            <button class="modal-close-btn">&times;</button>
            <h3 id="user-purchases-modal-title" class="text-lg font-semibold mb-4 text-gray-700">User Purchases</h3>
            <div id="user-purchases-modal-body" class="text-sm min-h-[150px] relative">
                <div class="loading-placeholder">Loading purchases...</div>
            </div>
            <div id="user-purchases-modal-feedback" class="mt-4 text-sm text-right h-5"></div>
        </div>
    </div>

    <div id="feature-edit-modal" class="modal-overlay">
        <div class="modal-content !max-w-xl"> <?php // Changed from !max-w-xl to !max-w-2xl to fit new fields ?>
            <button class="modal-close-btn">&times;</button>
            <h3 id="feature-edit-modal-title" class="text-lg font-semibold mb-6 text-gray-700">Add/Edit Feature</h3>
            <form id="feature-edit-form">
                <input type="hidden" name="feature_id" id="edit-feature-id" value="0">
                <div class="space-y-4">
                    <?php // Basic Feature Details ?>
                    <div>
                        <label for="edit-feature-key" class="form-label">Feature Key <span class="text-red-500">*</span></label>
                        <input type="text" name="feature_key" id="edit-feature-key" class="form-input !text-sm" required pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only.">
                        <p class="form-description">Unique. Cannot be changed after creation.</p>
                    </div>
                    <div>
                        <label for="edit-product-name" class="form-label">Feature Name <span class="text-red-500">*</span></label>
                        <input type="text" name="product_name" id="edit-product-name" class="form-input !text-sm" required>
                    </div>
                    <div>
                        <label for="edit-feature-category" class="form-label">Category</label>
                        <select name="category" id="edit-feature-category" class="form-select !text-sm">
                            <option value="">-- Select Category --</option>
                            <?php // Options populated by JS ?>
                        </select>
                    </div>
                    <div>
                        <label for="edit-feature-description" class="form-label">Description</label>
                        <textarea name="description" id="edit-feature-description" rows="3" class="form-textarea !text-sm"></textarea>
                    </div>

                    <?php // License Requirement ?>
                    <div class="pt-2">
                        <label class="form-label">License Required?</label>
                        <label class="flex items-center mt-1 cursor-pointer">
                            <input type="checkbox" name="requires_license" id="edit-requires-license" value="1" class="form-checkbox h-4 w-4 text-indigo-600">
                            <span class="ml-2 text-sm text-gray-700">Generate license key on purchase activation</span>
                        </label>
                    </div>

                    <?php // Download Details (if applicable) ?>
                    <div class="pt-2">
                        <label for="edit-feature-download-url" class="form-label">Download URL (Optional)</label>
                        <input type="url" name="download_url" id="edit-feature-download-url" class="form-input !text-sm" placeholder="https://example.com/path/to/your/file.zip">
                        <p class="form-description">Direct URL to the downloadable file. Leave blank if not applicable.</p>
                    </div>
                    <div>
                        <label for="edit-feature-download-limit" class="form-label">Daily Download Limit</label>
                        <input type="number" name="download_limit_daily" id="edit-feature-download-limit" class="form-input !text-sm !w-24" min="0" step="1" value="5">
                        <p class="form-description">Max downloads per user per day (0 for unlimited, default 5).</p>
                    </div>

                    <?php // --- NEW: Static Archive Metrics Section --- ?>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                         <h4 class="text-md font-semibold text-gray-600 mb-3">Static Archive Display Metrics</h4>
                         <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="edit-feature-static-rating" class="form-label">Static Rating (0-5)</label>
                                <input type="number" step="0.1" min="0" max="5" name="static_rating" id="edit-feature-static-rating" class="form-input !text-sm">
                                <p class="form-description">Rating shown on archive page.</p>
                            </div>
                            <div>
                                <label for="edit-feature-static-reviews-count" class="form-label">Static Reviews Count</label>
                                <input type="number" step="1" min="0" name="static_reviews_count" id="edit-feature-static-reviews-count" class="form-input !text-sm">
                                <p class="form-description">Number of reviews (optional display).</p>
                            </div>
                            <div>
                                <label for="edit-feature-static-sales-count" class="form-label">Static Sales Count</label>
                                <input type="number" step="1" min="0" name="static_sales_count" id="edit-feature-static-sales-count" class="form-input !text-sm">
                                <p class="form-description">Sales number for archive display.</p>
                            </div>
                            <div>
                                <label for="edit-feature-static-downloads-count" class="form-label">Static Downloads Count</label>
                                <input type="number" step="1" min="0" name="static_downloads_count" id="edit-feature-static-downloads-count" class="form-input !text-sm">
                                <p class="form-description">Downloads number for archive display.</p>
                            </div>
                         </div>
                    </div>
                    <?php // --- END NEW --- ?>
                </div>

                <div class="mt-6 flex justify-end items-center space-x-3">
                     <div id="feature-edit-modal-feedback" class="text-sm text-right mr-auto h-5"></div>
                     <button type="button" class="button button-secondary button-sm modal-cancel-btn">Cancel</button>
                     <button type="submit" class="button button-primary button-sm">
                         <i class="fas fa-save"></i> <span class="button-text-label">Save Feature</span>
                         <span class="save-spinner hidden"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                     </button>
                </div>
            </form>
        </div>
    </div>

    <div id="plan-edit-modal" class="modal-overlay">
        <div class="modal-content !max-w-2xl">
            <button class="modal-close-btn">&times;</button>
            <h3 id="plan-edit-modal-title" class="text-lg font-semibold mb-6 text-gray-700">Add/Edit Plan</h3>
            <form id="plan-edit-form">
                <input type="hidden" name="plan_id" id="edit-plan-id" value="0">
                <input type="hidden" name="product_feature_key" id="edit-plan-feature-key" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="edit-plan-name" class="form-label">Plan Name <span class="text-red-500">*</span></label>
                        <input type="text" name="plan_name" id="edit-plan-name" class="form-input !text-sm" required>
                    </div>
                    <div>
                        <label for="edit-plan-price" class="form-label">Price ($) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0" name="price" id="edit-plan-price" class="form-input !text-sm" required value="0.00">
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label">Payment Type <span class="text-red-500">*</span></label>
                        <div class="flex items-center space-x-6 mt-1">
                            <label class="inline-flex items-center"><input type="radio" name="is_one_time_radio" value="0" class="form-radio" checked> <span class="ml-2 text-sm">Subscription</span></label>
                            <label class="inline-flex items-center"><input type="radio" name="is_one_time_radio" value="1" class="form-radio"> <span class="ml-2 text-sm">One-Time Payment</span></label>
                        </div>
                        <input type="hidden" name="is_one_time" id="edit-is-one-time" value="0"> <?php // Hidden input stores 0 or 1 ?>
                    </div>
                    <div id="edit-plan-duration-wrapper">
                        <label for="edit-plan-duration-days" class="form-label">Duration (days) <span class="text-red-500">*</span></label>
                        <input type="number" step="1" min="1" name="duration_days" id="edit-plan-duration-days" class="form-input !text-sm" required value="30">
                        <p class="form-description">Set automatically for One-Time Payments.</p>
                    </div>
                     <div>
                        <label for="edit-plan-activation-limit" class="form-label">Activation Limit</label>
                        <input type="number" step="1" min="0" name="activation_limit" id="edit-plan-activation-limit" class="form-input !text-sm" placeholder="Blank or 0 for unlimited">
                        <p class="form-description">Max sites this license can be active on.</p>
                    </div>
                    <div class="md:col-span-2">
                        <label for="edit-plan-description" class="form-label">Description</label>
                        <textarea name="description" id="edit-plan-description" rows="2" class="form-textarea !text-sm"></textarea>
                    </div>
                    <div>
                        <label for="edit-plan-req-day" class="form-label">Requests/Day Limit</label>
                        <input type="number" step="1" min="0" name="requests_per_day" id="edit-plan-req-day" class="form-input !text-sm" placeholder="Blank = unlimited">
                    </div>
                    <div>
                        <label for="edit-plan-req-month" class="form-label">Requests/Month Limit</label>
                        <input type="number" step="1" min="0" name="requests_per_month" id="edit-plan-req-month" class="form-input !text-sm" placeholder="Blank = unlimited">
                    </div>
                     <div class="md:col-span-2 border-t pt-4 mt-2">
                        <label class="form-label">Gateway IDs (Optional for Subscriptions)</label>
                         <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-1">
                            <div><label for="edit-paypal-plan-id" class="text-xs font-medium text-gray-500">PayPal Plan ID</label><input type="text" name="paypal_plan_id" id="edit-paypal-plan-id" class="form-input !text-xs mt-0.5"></div>
                            <div><label for="edit-stripe-price-id" class="text-xs font-medium text-gray-500">Stripe Price ID</label><input type="text" name="stripe_price_id" id="edit-stripe-price-id" class="form-input !text-xs mt-0.5"></div>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label">Status</label>
                        <label class="flex items-center mt-1 cursor-pointer"><input type="checkbox" name="is_active" id="edit-plan-is-active" value="1" class="form-checkbox h-4 w-4 text-indigo-600"><span class="ml-2 text-sm text-gray-700">Plan is Active</span></label>
                     </div>
                </div>
                <div class="mt-6 flex justify-end items-center space-x-3">
                     <div id="plan-edit-modal-feedback" class="text-sm text-right mr-auto h-5"></div>
                     <button type="button" class="button button-secondary button-sm modal-cancel-btn">Cancel</button>
                     <button type="submit" class="button button-primary button-sm">
                         <i class="fas fa-save"></i> <span class="button-text-label">Save Plan</span>
                         <span class="save-spinner hidden"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                     </button>
                </div>
            </form>
        </div>
    </div>

    <div id="category-edit-modal" class="modal-overlay">
        <div class="modal-content !max-w-lg">
            <button class="modal-close-btn">&times;</button>
            <h3 id="category-edit-modal-title" class="text-lg font-semibold mb-6 text-gray-700">Edit Category</h3>
            <form id="category-edit-form">
                <input type="hidden" name="category_id" id="edit-category-id" value="0">
                <div class="space-y-4">
                     <div><label for="edit-category-name" class="form-label">Name <span class="text-red-500">*</span></label><input type="text" name="category_name" id="edit-category-name" class="form-input !text-sm" required></div>
                     <div><label for="edit-category-slug" class="form-label">Slug <span class="text-red-500">*</span></label><input type="text" name="category_slug" id="edit-category-slug" class="form-input !text-sm slug-input" required pattern="[a-z0-9-]+" title="Lowercase, numbers, hyphens."><p class="form-description">Unique identifier.</p></div>
                </div>
                 <div class="mt-6 flex justify-end items-center space-x-3">
                     <div id="category-edit-modal-feedback" class="text-sm text-right mr-auto h-5"></div>
                     <button type="button" class="button button-secondary button-sm modal-cancel-btn">Cancel</button>
                     <button type="submit" class="button button-primary button-sm">
                         <i class="fas fa-save"></i> <span class="button-text-label">Save Category</span>
                         <span class="save-spinner hidden"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                     </button>
                </div>
            </form>
        </div>
    </div>

    <div id="gateway-settings-modal" class="modal-overlay">
        <div class="modal-content !max-w-2xl">
             <button class="modal-close-btn">&times;</button>
             <h3 id="gateway-settings-modal-title" class="text-lg font-semibold mb-6 text-gray-700">Gateway Settings</h3>
             <form id="gateway-settings-form">
                 <input type="hidden" name="gateway_id" id="settings-gateway-id-hidden">
                 <div id="gateway-settings-modal-body" class="space-y-4 min-h-[150px] relative">
                    <div class="loading-placeholder">Loading settings...</div>
                 </div>
                 <div class="mt-6 flex justify-end items-center space-x-3">
                     <div id="gateway-settings-modal-feedback" class="text-sm text-right mr-auto h-5"></div>
                     <button type="button" class="button button-secondary button-sm modal-cancel-btn">Cancel</button>
                     <button type="submit" class="button button-primary button-sm">
                         <i class="fas fa-save"></i> <span class="button-text-label">Save Settings</span>
                         <span class="save-spinner hidden"><div class="loading-spinner !w-4 !h-4 !border-2 spinner-inline"></div></span>
                     </button>
                </div>
             </form>
        </div>
    </div>

</div><?php // WordPress Nonce and AJAX URL for JavaScript ?>
<script type="text/javascript">
    const orunkAdminGlobalData = {
        ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
        nonce: '<?php echo esc_js( wp_create_nonce( 'orunk_admin_interface_nonce' ) ); ?>'
    };
</script>

<?php
wp_footer();
?>
</body>
</html>