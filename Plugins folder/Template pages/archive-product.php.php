<?php
/**
 * Template Name: Orunk Product Showcase (Dynamic SPA V3.4.4 - Improved UX for Downloads)
 *
 * @package OrunkUsers
 * @version 1.3.4.4
 */

get_header();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(get_the_title()); ?> - <?php bloginfo('name'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --orunk-primary-600: #2563eb; --orunk-primary-700: #1e40af; --orunk-primary-50: #eff6ff;
            --orunk-gray-50:  #f9fafb; --orunk-gray-100: #F3F4F6; --orunk-gray-200: #E5E7EB;
            --orunk-gray-300: #D1D5DB; --orunk-gray-500: #6B7280; --orunk-gray-600: #4B5563;
            --orunk-gray-700: #374151; --orunk-gray-800: #1F2937; --orunk-text-white: #ffffff;
            --price-tag-free-bg: #10b981; --price-tag-subscription-bg: #8b5cf6;
            --price-tag-onetime-bg: #3b82f6; --price-tag-mixed-bg: #f59e0b;
            --price-tag-freemium-bg: #ef4444; --price-tag-unavailable-bg: var(--orunk-gray-400);
        }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background-color: var(--orunk-gray-100); color: var(--orunk-gray-800); }
        .product-card {
            transition: transform 0.25s ease-out, box-shadow 0.25s ease-out;
            border: 1px solid var(--orunk-gray-200);
            background-color: white; display: flex; flex-direction: column;
        }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 12px 20px -8px rgba(0,0,0,0.07), 0 5px 10px -6px rgba(0,0,0,0.05); }
        .filter-btn {
            transition: all 0.2s ease-in-out; border: 1px solid transparent; background-color: transparent;
            color: var(--orunk-gray-700); padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 500;
        }
        .filter-btn:hover:not(.active) { background-color: var(--orunk-gray-100); color: var(--orunk-primary-600); }
        .filter-btn.active { background-color: var(--orunk-primary-50); color: var(--orunk-primary-600); font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .price-tag { display: inline-block; padding: 0.2rem 0.65rem; font-size: 0.6rem; font-weight: 700; border-radius: 9999px; color: var(--orunk-text-white); line-height: 1.2; text-transform: uppercase; letter-spacing: 0.05em;}
        .price-tag-FREE { background-color: var(--price-tag-free-bg); }
        .price-tag-SUBSCRIPTION { background-color: var(--price-tag-subscription-bg); }
        .price-tag-ONE-TIME { background-color: var(--price-tag-onetime-bg); }
        .price-tag-MIXED { background-color: var(--price-tag-mixed-bg); }
        .price-tag-FREEMIUM { background-color: var(--price-tag-freemium-bg); }
        .price-tag-UNAVAILABLE { background-color: var(--price-tag-unavailable-bg); }
        .category-icon-display { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 0.375rem; font-size: 0.9rem;}
        .cat-icon-api-service { background-color: #dbeafe; color: #1d4ed8; }
        .cat-icon-wordpress-plugin { background-color: #e0e7ff; color: #4338ca; }
        .cat-icon-themes { background-color: #ede9fe; color: #6d28d9; }
        .cat-icon-website-feature { background-color: #ecfdf5; color: #047857; }
        .cat-icon-tools { background-color: #fef3c7; color: #92400e; }
        .cat-icon-default { background-color: var(--orunk-gray-100); color: var(--orunk-gray-500); }
        .loading-spinner-overlay { position: absolute; inset: 0; background-color: rgba(255, 255, 255, 0.85); display: none; align-items: center; justify-content: center; z-index: 20; border-radius: 0.75rem; }
        .product-grid-container.loading .loading-spinner-overlay { display: flex; }
        .loading-spinner { border: 4px solid rgba(0,0,0,0.1); border-top-color: var(--orunk-primary-600); border-radius: 50%; width: 48px; height: 48px; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        #category-tabs-container::-webkit-scrollbar { height: 3px; }
        #category-tabs-container::-webkit-scrollbar-thumb { background: var(--orunk-gray-300); border-radius: 10px; }
        input[type="search"], select {
            border-color: var(--orunk-gray-300);
            background-color: var(--orunk-gray-50);
            padding-left: 2.5rem;
        }
        input[type="search"]:focus, select:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            border-color: var(--orunk-primary-600);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
            background-color: white;
        }
        .btn-cta { background-color: var(--orunk-primary-600); color: white; font-weight: 500; }
        .btn-cta:hover:not(.download-processing) { background-color: var(--orunk-primary-700); }
        .btn-cta.download-processing { background-color: var(--orunk-primary-700); opacity: 0.8; cursor: wait; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .product-card-link:focus-visible { outline: 2px solid var(--orunk-primary-600); outline-offset: 2px; border-radius: 0.75rem; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2); }
        #product-controls { background-color: white; }
        .star-rating { display: inline-flex; color: #facc15; }
        .star-rating i { font-size: 0.75rem; letter-spacing: 0.05em; }
        .button-spinner-inline {
            display: none;
            vertical-align: middle;
            margin-left: 6px;
        }
        .button-spinner-inline .loading-spinner {
            width: 1em; height: 1em; border-width: 2px;
        }
        .global-notification {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-size: 0.9rem;
            font-weight: 500;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            opacity: 0;
            transition: opacity 0.3s ease-in-out, top 0.3s ease-in-out;
            min-width: 280px;
            text-align: center;
        }
        .global-notification.show {
            opacity: 1;
            top: 40px;
        }
        .global-notification.error {
            background-color: #ef4444;
        }
        .global-notification.success {
            background-color: #10b981;
        }
    </style>
    <?php wp_head(); ?>
</head>
<body <?php body_class('antialiased bg-gray-100'); ?>>

<div id="orunk-product-archive-page" class="min-h-screen">
    <header class="py-6 sm:py-8 bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">
                Explore products
            </h1>
            <p class="mt-1.5 text-sm sm:text-base text-gray-500 max-w-2xl mx-auto">
                APIs, plugins, themes and tools to accelerate your workflow
            </p>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <section id="product-controls" class="mb-6 p-3 sm:p-4 bg-white rounded-xl shadow-lg">
            <div class="flex flex-col md:flex-row justify-between items-center gap-3">
                <div class="relative w-full md:flex-grow md:max-w-md">
                    <label for="product-search" class="sr-only">Search products</label>
                    <input type="search" id="product-search" placeholder="Search by name or keyword..."
                           class="w-full py-2 px-4 pl-12 text-sm text-gray-700 bg-gray-50 border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow hover:shadow-sm">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                <div class="w-full md:w-auto md:min-w-[180px]">
                    <label for="product-sort" class="sr-only">Sort by</label>
                    <select id="product-sort" class="w-full form-select py-2 px-4 text-sm border-gray-300 rounded-lg shadow-sm focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow hover:shadow-sm">
                        <option value="default">Sort: Default</option>
                        <option value="name_asc">Name (A-Z)</option>
                        <option value="name_desc">Name (Z-A)</option>
                        <option value="newest">Newest</option>
                    </select>
                </div>
            </div>
            <nav id="category-tabs-container" class="mt-3 flex flex-nowrap space-x-1 overflow-x-auto pb-1 -mx-1 border-t border-gray-200 pt-4" aria-label="Categories">
                <button class="filter-btn category-filter-btn px-3 py-1.5 text-xs rounded-md active" data-filter="all">
                    <i class="fas fa-layer-group mr-1"></i> All
                </button>
                <span id="category-tabs-loading" class="px-3 py-1.5 text-xs text-gray-400 italic flex items-center">
                    <div class="loading-spinner !w-3 !h-3 !border-2 mr-1.5"></div>Loading...
                </span>
            </nav>
        </section>

        <div id="product-grid-section" class="relative">
            <div id="product-grid-container" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-4 min-h-[300px]">
                 <div class="loading-spinner-overlay">
                    <div class="loading-spinner"></div>
                </div>
            </div>
            <div id="product-grid-empty" class="hidden text-center py-20">
                 <i class="fas fa-box-open text-6xl text-gray-300 mb-6"></i>
                <h3 class="mt-2 text-xl font-semibold text-gray-700">No Products Found</h3>
                <p class="mt-2 text-base text-gray-500">Please try adjusting your filters or search terms.</p>
            </div>
        </div>
    </main>
</div>

<div id="global-notification-area" class="global-notification" style="display: none;"></div>

<script type="text/javascript">
    const orunkProductArchiveData = {
        ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
        nonce: '<?php echo esc_js( wp_create_nonce( 'orunk_product_archive_nonce' ) ); ?>',
        downloadNonce: '<?php echo esc_js( wp_create_nonce( 'orunk_product_download_nonce' ) ); ?>',
        text_strings: {
            loading: "<?php echo esc_js(__('Loading...', 'orunk-users')); ?>",
            viewDetails: "<?php echo esc_js(__('View Details', 'orunk-users')); ?>",
            download: "<?php echo esc_js(__('Download', 'orunk-users')); ?>",
            getStartedFree: "<?php echo esc_js(__('Get Started Free', 'orunk-users')); ?>",
            viewPlans: "<?php echo esc_js(__('View Plans', 'orunk-users')); ?>",
            purchase: "<?php echo esc_js(__('Purchase', 'orunk-users')); ?>",
            learnMore: "<?php echo esc_js(__('Learn More', 'orunk-users')); ?>",
            processing: "<?php echo esc_js(__('Processing...', 'orunk-users')); ?>"
        }
    };
</script>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const dom = {
        categoryTabsContainer: document.getElementById('category-tabs-container'),
        categoryTabsLoading: document.getElementById('category-tabs-loading'),
        productGridContainer: document.getElementById('product-grid-container'),
        productGridEmptyMessage: document.getElementById('product-grid-empty'),
        productSearchInput: document.getElementById('product-search'),
        productSortSelect: document.getElementById('product-sort'),
        loadingOverlay: document.querySelector('#product-grid-container .loading-spinner-overlay'),
        globalNotificationArea: document.getElementById('global-notification-area')
    };
    const state = {
        products: [],
        categories: [],
        filters: { category: 'all', search: '', sort: 'default' },
        isLoading: false
    };
    const utils = {
        escapeHTML: (str) => { if (str === null || typeof str === 'undefined') return ''; const div = document.createElement('div'); div.appendChild(document.createTextNode(str)); return div.innerHTML; },
        debounce: (func, wait) => { let timeout; return (...args) => { clearTimeout(timeout); timeout = setTimeout(() => func.apply(this, args), wait); }; },
        makeAjaxCall: function (action, params = {}, method = 'GET', isRawFetch = false) {
            return new Promise((resolve, reject) => {
                let url = orunkProductArchiveData.ajaxUrl;
                const urlParams = new URLSearchParams();
                urlParams.append('action', action);

                if (action === 'orunk_generic_download' || action === 'orunk_handle_convojet_download') {
                    urlParams.append('nonce', orunkProductArchiveData.downloadNonce);
                } else if (action === 'orunk_get_archive_product_categories' || action === 'orunk_get_archive_products') {
                    urlParams.append('archive_nonce', orunkProductArchiveData.nonce);
                } else {
                    urlParams.append('nonce', orunkProductArchiveData.nonce);
                }

                for (const key in params) { urlParams.append(key, params[key]); }
                url = `${url}?${urlParams.toString()}`;

                fetch(url, { method: method.toUpperCase() })
                    .then(response => {
                        if (!response.ok) {
                            if (isRawFetch) return response.json().then(errData => reject(errData));
                            return response.text().then(text => { try { const errData = JSON.parse(text); throw new Error(errData.data?.message || errData.message || `HTTP error ${response.status}`); } catch (e) { throw new Error(`HTTP error ${response.status}: ${text}`); }});
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (isRawFetch) {
                            resolve(data);
                        } else if (data.success) {
                            resolve(data.data);
                        } else {
                            reject(new Error(data.data?.message || data.message || 'AJAX error.'));
                        }
                    })
                    .catch(error => {
                        console.error(`AJAX Error for '${action}':`, error);
                        reject(error);
                    });
            });
        },
        showErrorInContainer: function(containerElement, message) {
            if (containerElement) {
                containerElement.innerHTML = `<div class="p-4 my-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert"><span class="font-medium">Error!</span> ${this.escapeHTML(message)}</div>`;
            }
        },
        showGlobalNotification: function(message, type = 'error', duration = 4000) {
            if (!dom.globalNotificationArea) return;
            dom.globalNotificationArea.textContent = utils.escapeHTML(message);
            dom.globalNotificationArea.className = `global-notification ${type}`;
            dom.globalNotificationArea.style.display = 'block';
            setTimeout(() => {
                dom.globalNotificationArea.classList.add('show');
            }, 10);

            setTimeout(() => {
                dom.globalNotificationArea.classList.remove('show');
                setTimeout(() => {
                    dom.globalNotificationArea.style.display = 'none';
                }, 300);
            }, duration);
        }
    };

    function setLoadingState(isLoading) {
        state.isLoading = isLoading;
        if (dom.loadingOverlay) dom.loadingOverlay.style.display = isLoading ? 'flex' : 'none';
        if (isLoading && dom.productGridEmptyMessage) dom.productGridEmptyMessage.classList.add('hidden');
    }
    function renderCategoryTabs() {
        if (!dom.categoryTabsContainer || !dom.categoryTabsLoading) return;
        dom.categoryTabsLoading.style.display = 'none';
        const existingDynamicTabs = dom.categoryTabsContainer.querySelectorAll('.category-filter-btn:not([data-filter="all"])');
        existingDynamicTabs.forEach(tab => tab.remove());
        if (state.categories && state.categories.length > 0) {
            state.categories.forEach(cat => {
                const tabButton = document.createElement('button');
                tabButton.className = `filter-btn category-filter-btn px-3 py-1.5 text-xs rounded-md ${state.filters.category === cat.slug ? 'active' : ''}`;
                tabButton.dataset.filter = cat.slug;
                tabButton.innerHTML = `<i class="fas ${cat.icon || 'fa-tag'} mr-1"></i> ${utils.escapeHTML(cat.name)}`;
                dom.categoryTabsContainer.appendChild(tabButton);
            });
        }
        addCategoryTabEventListeners();
    }
    function addCategoryTabEventListeners() {
        const tabs = dom.categoryTabsContainer.querySelectorAll('.category-filter-btn');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                if (state.isLoading) return;
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                state.filters.category = this.dataset.filter;
                fetchAndRenderProducts();
            });
        });
    }
    async function fetchCategories() {
        setLoadingState(true);
        try {
            const data = await utils.makeAjaxCall('orunk_get_archive_product_categories', {}, 'GET');
            state.categories = data.categories || [];
            if (state.categories.length === 0 && dom.categoryTabsLoading) { dom.categoryTabsLoading.textContent = 'No categories found.'; }
        } catch (error) {
            console.error('Failed to fetch categories:', error);
            state.categories = [];
            if(dom.categoryTabsLoading && dom.categoryTabsLoading.parentNode) {
                if (typeof utils.showErrorInContainer === 'function') {
                    utils.showErrorInContainer(dom.categoryTabsLoading.parentNode, `Error loading categories: ${error.message}.`);
                    dom.categoryTabsLoading.style.display = 'none';
                } else {
                    dom.categoryTabsLoading.innerHTML = `<span class="text-red-500 text-xs">Error loading categories!</span>`;
                }
            }
        }
    }

    function renderStarRating(rating) {
        const fullStars = Math.floor(rating);
        const halfStar = (rating % 1) >= 0.5;
        const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
        let starsHTML = '';
        for (let i = 0; i < fullStars; i++) starsHTML += '<i class="fas fa-star"></i>';
        if (halfStar) starsHTML += '<i class="fas fa-star-half-alt"></i>';
        for (let i = 0; i < emptyStars; i++) starsHTML += '<i class="far fa-star"></i>';
        return `<span class="star-rating" title="${rating} out of 5 stars">${starsHTML}</span> <span class="ml-1 text-[0.65rem] text-gray-500">(${rating.toFixed(1)})</span>`;
    }

    function updateProductCardMetric(featureKey, metricTypeToUpdate, incrementBy = 1) {
        const productIndex = state.products.findIndex(p => p.feature_key === featureKey);
        if (productIndex === -1) {
            console.warn(`Product with feature_key ${featureKey} not found in state for metric update.`);
            return;
        }
        const product = state.products[productIndex];
        if (product.raw_downloads_count === undefined) {
            const displayDownloads = (product.product_metric_type === 'downloads' && product.product_metric_display) ? product.product_metric_display : "0";
            product.raw_downloads_count = parseInt(displayDownloads.replace(/[kK+]/g, '')) * (displayDownloads.toLowerCase().includes('k') ? 1000 : 1) || 0;
        }
        if (product.raw_sales_count === undefined) {
             const displaySales = (product.product_metric_type === 'sales' && product.product_metric_display) ? product.product_metric_display : "0";
            product.raw_sales_count = parseInt(displaySales.replace(/[kK+]/g, '')) * (displaySales.toLowerCase().includes('k') ? 1000 : 1) || 0;
        }
        let currentMetricValue = 0;
        if (metricTypeToUpdate === 'downloads') {
            product.raw_downloads_count += incrementBy;
            currentMetricValue = product.raw_downloads_count;
            product.product_metric_type = 'downloads';
        } else if (metricTypeToUpdate === 'sales') {
            product.raw_sales_count += incrementBy;
            currentMetricValue = product.raw_sales_count;
            product.product_metric_type = 'sales';
        } else { return; }
        product.product_metric_display = currentMetricValue > 999 ? (currentMetricValue / 1000).toFixed(1).replace('.0','') + 'k+' : currentMetricValue.toString();
        state.products[productIndex] = product;
        const cardLinkElement = dom.productGridContainer.querySelector(`a.product-card-link[data-feature-key="${featureKey}"]`);
        if (cardLinkElement) {
            const metricElement = cardLinkElement.querySelector('.product-metric-display');
            if (metricElement && product.product_metric_type === metricTypeToUpdate) {
                const metricIcon = product.product_metric_type === 'downloads' ? 'fa-download' : 'fa-shopping-cart';
                metricElement.innerHTML = `<i class="fas ${metricIcon} text-gray-400 mr-1"></i> ${utils.escapeHTML(product.product_metric_display)}`;
            }
        }
    }

    function renderProductGrid() {
        if (!dom.productGridContainer || !dom.productGridEmptyMessage) return;
        const overlay = dom.loadingOverlay;
        if (overlay && overlay.parentNode === dom.productGridContainer) dom.productGridContainer.removeChild(overlay);
        dom.productGridContainer.innerHTML = '';
        if (overlay) dom.productGridContainer.appendChild(overlay);

        if (!state.products || state.products.length === 0) {
            dom.productGridEmptyMessage.classList.remove('hidden');
            return;
        }
        dom.productGridEmptyMessage.classList.add('hidden');

        state.products.forEach((product, index) => {
            const productCardLink = document.createElement('a');
            productCardLink.href = product.url || `<?php echo home_url('/product/');?>${utils.escapeHTML(product.feature_key || product.id || 'product')}/`;
            productCardLink.className = 'product-card-link group block focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-indigo-500 rounded-xl';
            productCardLink.dataset.featureKey = product.feature_key;

            productCardLink.addEventListener('click', (event) => {
                event.preventDefault();
            });

            const categoryIconBg = `cat-icon-${product.category_slug}` || 'cat-icon-default';
            const priceTagClass = product.pricing_type_tag ? `price-tag-${product.pricing_type_tag.toUpperCase().replace(' ', '-')}` : 'price-tag-UNAVAILABLE';
            const priceTagHTML = product.pricing_type_tag && product.pricing_type_tag.toUpperCase() !== 'N/A' ? `<span class="price-tag ${priceTagClass}">${utils.escapeHTML(product.pricing_type_tag)}</span>` : '';

            let ctaText = product.call_to_action_text || orunkProductArchiveData.text_strings.viewDetails;
            let ctaIcon = 'fa-arrow-right';
            if (product.call_to_action_type === 'download') ctaIcon = 'fa-download';
            else if (product.call_to_action_type === 'get_started_free') ctaIcon = 'fa-rocket';

            const imageHTML = product.image_url
                ? `<div class="aspect-w-16 aspect-h-9 bg-gray-200 rounded-t-xl overflow-hidden group-hover:opacity-90 transition-opacity duration-300">
                       <img src="${utils.escapeHTML(product.image_url)}" alt="${utils.escapeHTML(product.name)}" class="w-full h-full object-cover">
                   </div>`
                : '';

            const ratingHTML = product.rating ? renderStarRating(parseFloat(product.rating)) : '';
            let metricHTML = '';
            if (product.product_metric_display !== undefined) {
                const metricIcon = product.product_metric_type === 'downloads' ? 'fa-download' : 'fa-shopping-cart';
                const metricLabel = product.product_metric_type === 'downloads' ? 'Downloads' : 'Sales';
                metricHTML = `<span class="flex items-center product-metric-display" title="${metricLabel}"><i class="fas ${metricIcon} text-gray-400 mr-1"></i> ${utils.escapeHTML(product.product_metric_display)}</span>`;
            }

            const cardHTML = `
                <article class="product-card bg-white rounded-xl shadow-lg overflow-hidden flex flex-col h-full">
                    ${imageHTML}
                    <div class="p-4 flex flex-col flex-grow">
                        <div class="flex justify-between items-center mb-3">
                            ${priceTagHTML}
                            <div class="category-icon-display ${categoryIconBg}">
                                <i class="fas ${utils.escapeHTML(product.category_icon_class || 'fa-cube')}"></i>
                            </div>
                        </div>
                        <h3 class="text-sm font-bold text-gray-800 mb-1 line-clamp-2 group-hover:text-primary-600 min-h-[2.25em] transition-colors">
                            ${utils.escapeHTML(product.name || 'Product Name')}
                        </h3>
                        <p class="text-[0.65rem] text-gray-500 mb-2 line-clamp-2 flex-grow min-h-[2.5em]">
                            ${utils.escapeHTML(product.short_description || '')}
                        </p>
                        <div class="flex items-center text-[0.65rem] text-gray-400 mb-3 space-x-3 min-h-[1.2em]">
                            ${ratingHTML}
                            ${metricHTML}
                        </div>
                        <div class="mt-auto">
                            <div class="text-lg font-semibold text-gray-700 mb-3 min-h-[1.75em]">
                                ${product.price_html || ' '}
                            </div>
                            <span class="product-cta-button w-full btn-cta font-medium py-1.5 px-3 rounded-lg transition-all flex items-center justify-center gap-1.5 text-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-primary-600"
                                  role="button" tabindex="0"
                                  data-action-type="${product.call_to_action_type}"
                                  data-feature-key-cta="${product.feature_key || ''}"
                                  data-original-text="${utils.escapeHTML(ctaText)}"
                                  data-redirect-url="${utils.escapeHTML(product.url || productCardLink.href)}">
                                <i class="fas ${ctaIcon} text-xs cta-icon"></i>
                                <span class="button-text-label">${utils.escapeHTML(ctaText)}</span>
                                <span class="button-spinner-inline"><div class="loading-spinner"></div></span>
                            </span>
                        </div>
                    </div>
                </article>
            `;
            productCardLink.innerHTML = cardHTML;
            dom.productGridContainer.appendChild(productCardLink);

            const ctaButtonSpan = productCardLink.querySelector('.product-cta-button');

            if (ctaButtonSpan) {
                ctaButtonSpan.addEventListener('click', async function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    const actionType = this.dataset.actionType;
                    const featureKeyForDownload = this.dataset.featureKeyCta;
                    const redirectUrl = this.dataset.redirectUrl;

                    if (actionType === 'download' && featureKeyForDownload) {
                        if (this.classList.contains('download-processing')) return;

                        this.classList.add('download-processing');
                        const iconEl = this.querySelector('.cta-icon');
                        const textEl = this.querySelector('.button-text-label');
                        const spinnerEl = this.querySelector('.button-spinner-inline');
                        const originalText = this.dataset.originalText || orunkProductArchiveData.text_strings.download;

                        if (iconEl) iconEl.style.display = 'none';
                        if (textEl) textEl.textContent = orunkProductArchiveData.text_strings.processing;
                        if (spinnerEl) spinnerEl.style.display = 'inline-block';

                        const downloadAjaxAction = (featureKeyForDownload === 'convojet_pro') ? 'orunk_handle_convojet_download' : 'orunk_generic_download';
                        const params = (featureKeyForDownload === 'convojet_pro') ? { purchase_id: product.id } : { feature_key: featureKeyForDownload };

                        try {
                            const responseData = await utils.makeAjaxCall(downloadAjaxAction, params, 'GET', true);

                            if (responseData.success && responseData.data && responseData.data.download_url) {
                                updateProductCardMetric(featureKeyForDownload, 'downloads');
                                window.location.href = responseData.data.download_url;
                                setTimeout(() => {
                                    if (iconEl) iconEl.style.display = 'inline-block';
                                    if (textEl) textEl.textContent = originalText;
                                    if (spinnerEl) spinnerEl.style.display = 'none';
                                    this.classList.remove('download-processing');
                                }, 2500);
                            } else {
                                const errorMessage = responseData.data?.message || 'Download failed. Please try again.';
                                utils.showGlobalNotification(errorMessage, 'error');
                                if (iconEl) iconEl.style.display = 'inline-block';
                                if (textEl) textEl.textContent = originalText;
                                if (spinnerEl) spinnerEl.style.display = 'none';
                                this.classList.remove('download-processing');
                            }
                        } catch (errorData) {
                            console.error('Download AJAX fetch error:', errorData);
                            const errorMessage = errorData.data?.message || errorData.message || 'An error occurred during download.';
                            utils.showGlobalNotification(errorMessage, 'error');
                            if (iconEl) iconEl.style.display = 'inline-block';
                            if (textEl) textEl.textContent = originalText;
                            if (spinnerEl) spinnerEl.style.display = 'none';
                            this.classList.remove('download-processing');
                        }
                    } else {
                        if (redirectUrl) {
                            window.location.href = redirectUrl;
                        }
                    }
                });
            }

            productCardLink.style.opacity = '0';
            productCardLink.style.transform = 'translateY(15px)';
            setTimeout(() => {
                productCardLink.style.transition = 'opacity 0.35s ease-out, transform 0.35s ease-out';
                productCardLink.style.opacity = '1';
                productCardLink.style.transform = 'translateY(0)';
            }, 100 + (index * 60));
        });
    }

    async function fetchAndRenderProducts() {
        setLoadingState(true);
        try {
            const data = await utils.makeAjaxCall('orunk_get_archive_products', state.filters, 'GET');
            state.products = (data.products || []).map(p => {
                let rawDownloads = 0;
                let rawSales = 0;
                if (p.product_metric_display !== undefined && p.product_metric_display !== null) {
                    const displayVal = String(p.product_metric_display);
                    const isK = displayVal.toLowerCase().includes('k');
                    const numPart = parseFloat(displayVal.replace(/[kK+]/g, ''));
                    const val = isNaN(numPart) ? 0 : (isK ? numPart * 1000 : numPart);

                    if (p.product_metric_type === 'downloads') {
                        rawDownloads = Math.round(val);
                    } else if (p.product_metric_type === 'sales') {
                        rawSales = Math.round(val);
                    }
                }
                return {
                    ...p,
                    raw_downloads_count: rawDownloads,
                    raw_sales_count: rawSales
                };
            });
        } catch (error) {
            console.error("FP Error:", error);
            state.products = [];
            if(dom.productGridContainer && typeof utils.showErrorInContainer === 'function') {
                utils.showErrorInContainer(dom.productGridContainer, `Failed to load products: ${error.message}.`);
            } else if (dom.productGridContainer) {
                 dom.productGridContainer.innerHTML = `<div class="p-4 my-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">Failed to load products.</div>`;
            }
        }
        finally { renderProductGrid(); setLoadingState(false); }
    }

    dom.productSearchInput?.addEventListener('input', utils.debounce(function(e) {
        if (state.isLoading) return; state.filters.search = e.target.value; fetchAndRenderProducts();
    }, 500));
    dom.productSortSelect?.addEventListener('change', function(e) {
        if (state.isLoading) return; state.filters.sort = e.target.value; fetchAndRenderProducts();
    });

    async function initializeArchive() {
        setLoadingState(true);
        try {
            await fetchCategories();
            renderCategoryTabs();
            await fetchAndRenderProducts();
        }
        catch (error) {
            console.error("Init Error:", error);
        }
        finally { setLoadingState(false); }
    }

    if (dom.categoryTabsContainer && dom.productGridContainer) { initializeArchive(); }
    else { console.error("Archive DOM elements missing. Cannot initialize."); }
});
</script>

<?php
get_footer();
?>
</body>
</html>