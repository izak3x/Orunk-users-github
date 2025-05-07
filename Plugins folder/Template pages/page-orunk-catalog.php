<?php
/**
 * Template Name: Orunk Catalog (Tailwind Card V3 + Filters + Search + FAQ)
 *
 * This template displays product features in a filterable grid layout
 * styled with Tailwind CSS utility classes. Includes refined compact product cards
 * with badge, meta-data (dummy), and footer sections.
 * **Requires Tailwind CSS to be configured in the active theme.**
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Astra
 * @since 1.0.4
 */

// Ensure the Orunk Users core class is available
if (!class_exists('Custom_Orunk_Core')) {
    get_header();
    ?>
    <div id="primary" <?php astra_primary_class(); ?>>
        <main id="main" class="site-main">
            <div class="container mx-auto px-4 py-8">
                 <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                    <p class="font-bold"><?php esc_html_e('Error', 'orunk-users'); ?></p>
                    <p><?php esc_html_e('The required Orunk Users plugin component is not available. Please ensure the plugin is active.', 'orunk-users'); ?></p>
                 </div>
            </div>
        </main>
    </div>
    <?php
    get_footer();
    return; // Stop further execution
}

// Instantiate the core class
$orunk_core = new Custom_Orunk_Core();

// --- Get Data ---
// ** IMPORTANT: Ensure this function provides all necessary data **
$features_with_plans = $orunk_core->get_product_features_with_plans();
$user_id = get_current_user_id();

// --- Static Category List & Map ---
$static_categories = [
    ['category_slug' => 'api-service', 'category_name' => __('API', 'orunk-users')], // Shortened name
    ['category_slug' => 'website-feature', 'category_name' => __('Feature', 'orunk-users')], // Shortened name
    ['category_slug' => 'wordpress-plugin', 'category_name' => __('Plugin', 'orunk-users')], // Shortened name
    ['category_slug' => 'wordpress-theme', 'category_name' => __('Theme', 'orunk-users')], // Shortened name
];
// Filter the static list to only include categories present in the products
$present_category_slugs = array_unique(array_column($features_with_plans ?? [], 'category'));
$filtered_catalog_categories = array_filter($static_categories, function($cat) use ($present_category_slugs) {
    return in_array($cat['category_slug'], $present_category_slugs);
});
// Create a lookup map for category names
$category_name_map = [];
foreach ($filtered_catalog_categories as $cat) {
    $category_name_map[$cat['category_slug']] = $cat['category_name'];
}

get_header();
?>

<?php // --- REMOVED Embedded CSS Block --- ?>

<div id="primary" <?php astra_primary_class(); ?>>
    <main id="main" class="site-main">
        <div class="orunk-page-wrapper max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-10"> <?php // Reduced py ?>
            <div class="orunk-main-content">

                <div class="text-center mb-8 md:mb-12"> <?php // Reduced mb ?>
                     <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 mb-2"> <?php // Reduced mb ?>
                        <?php esc_html_e('Digital Products', 'orunk-users'); ?>
                    </h1>
                    <p class="text-base text-gray-600 max-w-2xl mx-auto"> <?php // Reduced text size ?>
                        <?php esc_html_e('Quality tools for your digital projects', 'orunk-users'); ?>
                    </p>
                </div>

                <div class="orunk-catalog-container">
                    <div class="entry-content clear" itemprop="text">

                        <?php /* Keep error message display */
                        if (isset($_GET['purchase_error'])) { echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert"><p><strong>' . esc_html__('Purchase Error:', 'orunk-users') . '</strong> ' . esc_html(urldecode($_GET['purchase_error'])) . '</p></div>'; }
                        if (isset($_GET['payment_error'])) { echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert"><p><strong>' . esc_html__('Payment Error:', 'orunk-users') . '</strong> ' . esc_html(urldecode($_GET['payment_error'])) . '</p></div>'; }
                        ?>

                        <?php if (empty($features_with_plans)) : ?>
                             <p class="text-center text-gray-500 py-10"><?php esc_html_e('No products found matching your criteria.', 'orunk-users'); // Updated text ?></p>
                        <?php else : ?>

                            <?php // --- Filter Controls (Slightly more compact) --- ?>
                            <div class="orunk-filter-controls bg-gray-50 p-4 rounded-lg border border-gray-200 mb-8">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center"> <?php // Changed to items-center ?>
                                    <div class="orunk-search-filter md:col-span-1">
                                        <label for="orunk-product-search" class="sr-only"><?php esc_html_e('Search Products', 'orunk-users'); ?></label> <?php // Screen reader only ?>
                                        <input type="search" id="orunk-product-search"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                               placeholder="<?php esc_attr_e('Search...', 'orunk-users'); ?>"> <?php // Shorter placeholder ?>
                                    </div>
                                    <div class="md:col-span-2">
                                        <?php if (!empty($filtered_catalog_categories)): ?>
                                            <ul class="orunk-category-tabs flex flex-wrap justify-center md:justify-end gap-2"> <?php // justify-end ?>
                                                <li><a href="#all"
                                                       class="category-tab px-3 py-1 text-xs font-medium rounded-full border transition duration-150 ease-in-out" <?php // Smaller padding/text ?>
                                                       data-category="all"><?php esc_html_e('All', 'orunk-users'); ?></a></li> <?php // Shortened text ?>
                                                <?php
                                                foreach ($filtered_catalog_categories as $category) {
                                                    echo '<li><a href="#' . esc_attr($category['category_slug']) . '"
                                                               class="category-tab px-3 py-1 text-xs font-medium rounded-full border transition duration-150 ease-in-out" ' . // Smaller padding/text
                                                               ' data-category="' . esc_attr($category['category_slug']) . '">' . esc_html($category['category_name']) . '</a></li>';
                                                }
                                                ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php // --- END Filter Controls --- ?>

                            <?php // --- Product Grid (More columns for compact) --- ?>
                            <div class="orunk-product-grid grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"> <?php // Increased density ?>
                                <?php foreach ($features_with_plans as $feature) : ?>
                                    <?php
                                        // --- Data Preparation ---
                                        $feature_key = $feature['feature'];
                                        $plans = $feature['plans'] ?? [];
                                        $category_slug = esc_attr($feature['category'] ?? 'uncategorized');
                                        $category_name = esc_html($category_name_map[$category_slug] ?? __('Other', 'orunk-users'));
                                        $product_title = esc_html($feature['product_name']);
                                        $product_description = $feature['description'] ?? '';
                                        $product_description_plain = esc_attr(strip_tags(html_entity_decode($product_description)));
                                        $detail_page_url = esc_url(home_url('/product-' . $feature_key . '/'));

                                        // --- Price Calculation ---
                                        $lowest_price = null; $all_free = true; $has_plans = !empty($plans);
                                        if ($has_plans) { foreach ($plans as $plan) { if (isset($plan['price']) && is_numeric($plan['price'])) { $price = floatval($plan['price']); if ($price > 0) { $all_free = false; if ($lowest_price === null || $price < $lowest_price) { $lowest_price = $price; } } } } }
                                        $price_text = '';
                                        if (!$has_plans) { $price_text = __('N/A', 'orunk-users'); }
                                        elseif ($all_free) { $price_text = __('Free', 'orunk-users'); }
                                        elseif ($lowest_price !== null) { $formatted_price = number_format($lowest_price, 2); $price_text = sprintf(__('From %s', 'orunk-users'), '$' . $formatted_price); } // Changed "Starts at"
                                        else { $price_text = __('See Details', 'orunk-users'); }

                                        // --- Dummy Meta Data ---
                                        $reviews_dummy = "4.7"; // Just number
                                        $sales_dummy = "2k+"; // Shorter
                                        $last_updated_dummy = date_i18n('M Y', current_time('timestamp') - (rand(7, 90) * DAY_IN_SECONDS)); // Month Year

                                        // --- Badge Color Mapping (Example) ---
                                        $badge_color_classes = 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-500/10'; // Default subtle ring
                                        switch ($category_slug) {
                                            case 'api-service': $badge_color_classes = 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-700/10'; break;
                                            case 'website-feature': $badge_color_classes = 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-700/10'; break;
                                            case 'wordpress-plugin': $badge_color_classes = 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'; break;
                                            case 'wordpress-theme': $badge_color_classes = 'bg-yellow-50 text-yellow-800 ring-1 ring-inset ring-yellow-600/20'; break;
                                        }
                                    ?>
                                    <article class="orunk-product-card relative bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden flex flex-col h-full transition-shadow duration-150 ease-in-out hover:shadow-md"
                                             data-category="<?php echo $category_slug; ?>"
                                             data-title="<?php echo esc_attr($product_title); ?>"
                                             data-description="<?php echo $product_description_plain; ?>">

                                        <?php // --- Card Header: Badge --- ?>
                                        <span class="absolute top-2.5 right-2.5 text-xs font-medium px-2 py-0.5 rounded-full <?php echo $badge_color_classes; ?>">
                                            <?php echo $category_name; ?>
                                        </span>

                                        <?php // --- Card Main Content --- ?>
                                        <div class="p-4 flex flex-col flex-grow"> <?php // Reduced padding ?>
                                            <h2 class="text-base font-semibold text-gray-900 mb-1 mt-4"> <?php // Smaller text, margin ?>
                                                <a href="<?php echo $detail_page_url; ?>" class="hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 rounded" title="<?php echo esc_attr($product_title); ?>">
                                                     <?php echo $product_title; ?>
                                                </a>
                                            </h2>

                                            <?php if (!empty($product_description)) : ?>
                                                <p class="text-xs text-gray-500 mb-3 flex-grow line-clamp-3"> <?php // Smaller text, margin ?>
                                                    <?php echo wp_kses_post($product_description); ?>
                                                </p>
                                            <?php else: ?>
                                                <div class="flex-grow mb-3"></div> <?php // Spacer ?>
                                            <?php endif; ?>

                                            <?php // --- Card Meta Section (Compact) --- ?>
                                             <div class="text-xs text-gray-500 mt-2 mb-3 border-t border-gray-100 pt-2 flex flex-wrap items-center gap-x-3 gap-y-1"> <?php // Smaller margin, padding, gap ?>
                                                <span class="flex items-center gap-0.5" title="<?php esc_attr_e('Reviews', 'orunk-users'); ?>">
                                                     <svg class="h-3.5 w-3.5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg> <?php // Smaller icon ?>
                                                    <?php echo esc_html($reviews_dummy); ?>
                                                </span>
                                                 <span class="flex items-center gap-0.5" title="<?php esc_attr_e('Sales', 'orunk-users'); ?>">
                                                    <svg class="h-3.5 w-3.5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L10 10.586l2.293-2.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" /><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v6a1 1 0 11-2 0V4a1 1 0 011-1z" clip-rule="evenodd" /></svg> <?php // Smaller icon ?>
                                                    <?php echo esc_html($sales_dummy); ?>
                                                </span>
                                                <span class="flex items-center gap-0.5" title="<?php esc_attr_e('Last Updated', 'orunk-users'); ?>">
                                                    <svg class="h-3.5 w-3.5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" /></svg> <?php // Smaller icon ?>
                                                    <?php echo esc_html($last_updated_dummy); ?>
                                                </span>
                                            </div>

                                            <?php // --- Card Footer (Compact) --- ?>
                                            <div class="mt-auto flex justify-between items-center">
                                                <p class="text-sm font-medium text-gray-800 whitespace-nowrap"> <?php // Slightly less prominent price ?>
                                                    <?php echo esc_html($price_text); ?>
                                                </p>
                                                <a href="<?php echo $detail_page_url; ?>"
                                                   class="orunk-view-details-button inline-flex items-center justify-center px-2.5 py-1 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500 whitespace-nowrap transition ease-in-out duration-150"> <?php // Smaller padding ?>
                                                    <?php esc_html_e('Details', 'orunk-users'); // Shorter text ?>
                                                </a>
                                            </div>
                                        </div>
                                    </article> <?php // .orunk-product-card ?>
                                <?php endforeach; // End loop through features ?>
                            </div> <?php // .orunk-product-grid ?>
                            <?php // --- END Product Grid --- ?>

                             <?php // --- ADD Load More Button Placeholder (Requires JS Implementation) --- ?>
                             <div id="orunk-load-more-container" class="text-center mt-8 hidden"> <?php // Initially hidden ?>
                                 <button id="orunk-load-more-btn" class="inline-flex items-center px-6 py-3 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                     <?php esc_html_e('Load More Products', 'orunk-users'); ?>
                                     <svg class="ml-2 -mr-1 h-4 w-4 animate-spin hidden" id="orunk-load-more-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                         <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                         <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                     </svg>
                                 </button>
                                 <p id="orunk-all-loaded-msg" class="text-sm text-gray-500 mt-2 hidden"><?php esc_html_e('All products loaded.', 'orunk-users'); ?></p>
                             </div>
                             <?php // --- END Load More --- ?>

                        <?php endif; // End check for empty features_with_plans ?>

                        <?php // --- FAQ Section --- ?>
                        <section class="orunk-faq-section mt-12 md:mt-16 pt-8 border-t border-gray-200"> <?php // Reduced margins ?>
                             <h2 class="text-xl sm:text-2xl font-bold text-gray-900 text-center mb-6 md:mb-10"> <?php // Reduced size/margin ?>
                                 <?php esc_html_e('Frequently Asked Questions', 'orunk-users'); ?>
                             </h2>
                            <div class="orunk-faq-grid max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4"> <?php // Reduced gaps ?>
                                <?php /* FAQ content remains the same */
                                $faqs = [ /* Same FAQs as before */
                                    ['q' => __('How do I purchase a plan?', 'orunk-users'), 'a' => __('Click "Details" for the product you are interested in. On the product detail page, you will find available plans. Choose the plan that suits you, log in or create an account if needed, select a payment method (if applicable), and complete the purchase.', 'orunk-users')],
                                    ['q' => __('Can I change my plan later?', 'orunk-users'), 'a' => __('Yes, you can typically upgrade or switch between plans for the same feature directly from your User Dashboard. Downgrading or switching to a free plan might have specific conditions outlined in the dashboard.', 'orunk-users')],
                                    ['q' => __('What payment methods are accepted?', 'orunk-users'), 'a' => __('We currently accept payments via Stripe (Credit/Debit Card) and Direct Bank Transfer. Available options for paid plans are shown on the product detail and checkout pages.', 'orunk-users')],
                                    ['q' => __('Where can I find my API key?', 'orunk-users'), 'a' => __('If your purchased plan includes API access, your unique API key will be visible in your User Dashboard after the purchase is activated.', 'orunk-users')],
                                    ['q' => __('How are API limits counted?', 'orunk-users'), 'a' => __('API limits are typically counted per successful request made using your unique API key. Daily limits reset every 24 hours (UTC), and monthly limits usually reset based on your purchase date or calendar month, depending on the specific plan configuration found on the product detail page.', 'orunk-users')],
                                    ['q' => __('How do I cancel my subscription?', 'orunk-users'), 'a' => __('You can manage your active subscriptions, including cancellation options, from your User Dashboard.', 'orunk-users')],
                                ];
                                foreach ($faqs as $faq): ?>
                                     <div class="orunk-faq-item border border-gray-200 rounded-lg">
                                         <details class="group">
                                             <summary class="flex justify-between items-center p-3 cursor-pointer list-none"> <?php // Reduced padding ?>
                                                 <span class="text-sm font-medium text-gray-900"><?php echo esc_html($faq['q']); ?></span> <?php // Reduced size ?>
                                                 <span class="text-gray-400 group-open:rotate-180 transition-transform duration-200">
                                                     <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg> <?php // Smaller icon ?>
                                                 </span>
                                             </summary>
                                             <div class="faq-answer px-3 pb-3 text-xs text-gray-600 border-t border-gray-100 pt-2"> <?php // Smaller text/padding ?>
                                                 <p><?php echo esc_html($faq['a']); ?></p>
                                             </div>
                                         </details>
                                     </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <?php // --- End FAQ Section --- ?>

                    </div> <?php // .entry-content ?>
                </div> <?php // .orunk-catalog-container ?>
            </div> <?php // .orunk-main-content ?>
        </div> <?php // .orunk-page-wrapper ?>
    </main>
</div> <?php // #primary ?>

<?php // --- JavaScript for Filtering (Needs update for Load More/Pagination if implemented) --- ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('orunk-product-search');
    const tabsContainer = document.querySelector('.orunk-category-tabs');
    const productGrid = document.querySelector('.orunk-product-grid');

    // --- Load More/Pagination Variables (Placeholder - Requires implementation) ---
    const loadMoreContainer = document.getElementById('orunk-load-more-container');
    const loadMoreBtn = document.getElementById('orunk-load-more-btn');
    const loadMoreSpinner = document.getElementById('orunk-load-more-spinner');
    const allLoadedMsg = document.getElementById('orunk-all-loaded-msg');
    const itemsPerPage = 15; // CONFIG: How many items to show per "page" or "load"
    let visibleItemCount = 0;
    let allProductCards = []; // Will hold all card elements
    // --- End Load More ---


    if (!searchInput || !tabsContainer || !productGrid) {
        return; // Exit if elements aren't found
    }

    // Store all cards initially
    allProductCards = Array.from(productGrid.querySelectorAll('.orunk-product-card'));

    const categoryTabs = tabsContainer.querySelectorAll('.category-tab');
    // Define Tailwind classes for active/inactive states (Adjust if your theme/config differs)
    const activeTabClasses = ['bg-indigo-600', 'text-white', 'border-indigo-600'];
    const inactiveTabClasses = ['bg-white', 'text-gray-600', 'border-gray-300', 'hover:bg-gray-50', 'hover:border-gray-400', 'hover:text-gray-700']; // Adjusted inactive style

    let currentCategoryFilter = 'all';
    let currentSearchTerm = '';
    let debounceTimer;
    let currentlyVisibleCards = []; // Track cards matching filters

    // --- Filter Function (Handles Search + Category) ---
    function filterAndPrepareDisplay() {
        currentSearchTerm = searchInput.value.toLowerCase().trim();
        currentlyVisibleCards = []; // Reset the list of cards matching filters

        allProductCards.forEach(card => {
            const cardCategory = card.getAttribute('data-category') || 'uncategorized';
            const cardTitle = (card.getAttribute('data-title') || '').toLowerCase();
            const cardDescription = (card.getAttribute('data-description') || '').toLowerCase();

            const categoryMatch = (currentCategoryFilter === 'all' || cardCategory === currentCategoryFilter);
            const searchMatch = (currentSearchTerm === '' || cardTitle.includes(currentSearchTerm) || cardDescription.includes(currentSearchTerm));

            // Important: Hide ALL cards initially within this function
            card.classList.add('hidden');

            if (categoryMatch && searchMatch) {
                currentlyVisibleCards.push(card); // Add card to the list that matches filters
            }
        });

        // Reset and display the first batch based on filters
        displayItems(0, itemsPerPage, true); // Reset display
    }

    // --- Display Function (For Load More/Pagination) ---
    function displayItems(start, count, isReset = false) {
         if (isReset) {
             visibleItemCount = 0; // Reset count only if it's a new filter/initial load
             // Hide all cards (redundant if filterAndPrepareDisplay hid them, but safe)
             // allProductCards.forEach(card => card.classList.add('hidden'));
         }

         const end = Math.min(start + count, currentlyVisibleCards.length);

         for (let i = start; i < end; i++) {
             if (currentlyVisibleCards[i]) {
                 currentlyVisibleCards[i].classList.remove('hidden');
             }
         }
         visibleItemCount = end; // Update the count of items *now* visible

         // --- Update Load More Button State ---
         updateLoadMoreVisibility();
    }

     // --- Update Load More Button ---
     function updateLoadMoreVisibility() {
        if (!loadMoreContainer || !loadMoreBtn || !allLoadedMsg) return; // Exit if elements missing

         if (currentlyVisibleCards.length === 0) {
              // Optional: Show a "No results" message within the grid area?
              // productGrid.innerHTML = '<p class="col-span-full text-center text-gray-500 py-10">No products match your criteria.</p>'; // Example message
              loadMoreContainer.classList.add('hidden'); // Hide button if no results
         } else {
             // productGrid.querySelector('.no-results')?.remove(); // Remove no results message if present
             if (visibleItemCount >= currentlyVisibleCards.length) {
                 loadMoreBtn.classList.add('hidden'); // Hide button
                 allLoadedMsg.classList.remove('hidden'); // Show "All loaded" message
                 loadMoreContainer.classList.remove('hidden'); // Keep container visible for msg
             } else {
                 loadMoreBtn.classList.remove('hidden'); // Show button
                 allLoadedMsg.classList.add('hidden'); // Hide "All loaded" message
                 loadMoreContainer.classList.remove('hidden'); // Show container
             }
             // Reset spinner state
             loadMoreBtn.disabled = false;
             loadMoreSpinner?.classList.add('hidden');
         }
     }


    // --- Set Active Tab Styling ---
    function setActiveTab(activeTabElement) {
         categoryTabs.forEach(tab => {
             tab.classList.remove(...activeTabClasses);
             tab.classList.add(...inactiveTabClasses);
             tab.classList.remove('category-tab-active');
         });
         activeTabElement.classList.add(...activeTabClasses);
         activeTabElement.classList.remove(...inactiveTabClasses);
         activeTabElement.classList.add('category-tab-active');
         currentCategoryFilter = activeTabElement.getAttribute('data-category') || 'all';
    }

    // --- Event Listeners ---
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            filterAndPrepareDisplay(); // Rerun filter and display first page
        }, 350); // Slightly longer debounce maybe
    });

    categoryTabs.forEach(tab => {
        tab.addEventListener('click', function(event) {
            event.preventDefault();
            if (this.classList.contains('category-tab-active')) return;
            setActiveTab(this);
            filterAndPrepareDisplay(); // Rerun filter and display first page
            if (history.pushState) { history.pushState(null, null, '#' + currentCategoryFilter); } else { location.hash = '#' + currentCategoryFilter; }
        });
    });

     // --- Load More Button Listener ---
     if (loadMoreBtn) {
         loadMoreBtn.addEventListener('click', function() {
             this.disabled = true; // Prevent double clicks
             loadMoreSpinner?.classList.remove('hidden');

             // Simulate loading delay (optional) then load next batch
             setTimeout(() => {
                const nextBatchStart = visibleItemCount;
                displayItems(nextBatchStart, itemsPerPage);
                 // Spinner hidden within updateLoadMoreVisibility called by displayItems
             }, 150); // Short delay
         });
     }

    // --- Initial Filter Logic on Page Load ---
    function applyInitialFilter() {
        let initialCategory = 'all';
        let activeTabElement = document.querySelector('.category-tab[data-category="all"]');

        if (window.location.hash && window.location.hash !== '#') {
             const hashCategory = window.location.hash.substring(1);
             const matchingTab = document.querySelector(`.category-tab[data-category="${hashCategory}"]`);
             if (matchingTab) {
                 initialCategory = hashCategory;
                 activeTabElement = matchingTab;
             }
         }

        if (activeTabElement) {
             setActiveTab(activeTabElement);
        }
        currentCategoryFilter = initialCategory;
        currentSearchTerm = searchInput.value.toLowerCase().trim();

        // Filter and display the *first* page only on initial load
        filterAndPrepareDisplay();
    }

    applyInitialFilter();

});
</script>
<?php // --- END Filter JavaScript --- ?>

<?php
get_footer();
?>