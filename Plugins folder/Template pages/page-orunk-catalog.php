<?php
/**
 * Template Name: Orunk Catalog (Styled + Responsive + Ads + FAQ)
 *
 * This template displays the available product features and their purchasable plans
 * using data from the Orunk Users plugin, styled with CSS Grid and classes.
 * Includes embedded CSS, responsive layout, ad sidebars, and FAQ section.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Astra
 * @since 1.0.0
 */

// Ensure the Orunk Users core class is available
if (!class_exists('Custom_Orunk_Core')) {
    get_header();
    ?>
    <div id="primary" <?php astra_primary_class(); ?>>
        <main id="main" class="site-main">
            <div class="ast-container">
                <div class="ast-row">
                    <div class="ast-col-lg-12 ast-col-md-12 ast-col-sm-12 ast-col-xs-12">
                        <div class="entry-content clear" itemprop="text">
                            <?php /* Removed the default H1 title display */ ?>
                            <p class="orunk-error notice notice-error">
                                <?php esc_html_e('Error: The required Orunk Users plugin component is not available. Please ensure the plugin is active.', 'orunk-users'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <?php
    get_footer();
    return; // Stop further execution
}

// Instantiate the core class to access its methods
$orunk_core = new Custom_Orunk_Core();

// --- Get Data for Display ---
$features_with_plans = $orunk_core->get_product_features_with_plans(); // Get all features and their active plans
$user_id = get_current_user_id(); // Get current logged-in user ID (0 if not logged in)
$available_gateways = $orunk_core->get_available_payment_gateways(); // Get enabled payment gateways

get_header(); // Include theme header
?>

<?php // --- EMBEDDED CSS --- ?>
<style>
    /* --- Base & Layout --- */
    .orunk-page-wrapper {
        display: grid;
        grid-template-columns: 1fr; /* Default: single column */
        gap: 1.5rem; /* Gap between columns */
        max-width: 1400px; /* Max width for the entire layout */
        margin: 1rem auto; /* Centering and top/bottom margin */
        padding: 0 1rem; /* Padding on smaller screens */
    }

    .orunk-main-content {
        grid-column: 1 / -1; /* Span full width by default */
        min-width: 0; /* Prevent content overflow */
    }

    .orunk-sidebar {
        width: 180px; /* Width for ad sidebars */
        flex-shrink: 0;
        /* Hide sidebars by default, show on larger screens */
        display: none;
    }

    .orunk-sidebar-left { order: -1; } /* Position left sidebar first visually if needed */
    .orunk-sidebar-right { }

    /* Responsive Layout: Show sidebars and adjust grid on larger screens */
    @media (min-width: 1024px) { /* lg breakpoint */
        .orunk-page-wrapper {
            /* 3 columns: Left Ad - Main Content - Right Ad */
            grid-template-columns: 180px 1fr 180px;
            padding: 0; /* Remove padding when sidebars are visible */
        }
        .orunk-main-content {
            grid-column: 2 / 3; /* Place main content in the middle column */
        }
        .orunk-sidebar {
            display: block; /* Show sidebars */
            /* Optional: Add sticky positioning */
            /* position: sticky; */
            /* top: 2rem; */
            /* height: calc(100vh - 4rem); */ /* Adjust height based on header/footer */
            /* overflow-y: auto; */
        }
    }
    @media (min-width: 1280px) { /* xl breakpoint - wider sidebars? */
        .orunk-page-wrapper {
             grid-template-columns: 200px 1fr 200px; /* Slightly wider sidebars */
             gap: 2rem;
        }
         .orunk-sidebar { width: 200px; }
    }


    /* --- Catalog Styling --- */
    .orunk-catalog-container {
       /* Removed max-width/margin from here, handled by orunk-page-wrapper */
       padding: 0; /* Remove padding if handled by wrapper */
    }

    /* Removed entry-title style as title is removed */

    /* Error/Notice Styling */
    .orunk-error, .orunk-purchase-error, .orunk-payment-error {
        border-left-width: 4px !important;
        padding: 1em !important;
        margin-bottom: 1.5rem;
        border-radius: 4px;
        background-color: #fef2f2 !important;
        border-color: #f87171 !important;
        color: #991b1b !important;
    }
    .orunk-error p, .orunk-purchase-error p, .orunk-payment-error p { margin: 0 !important; padding: 0 !important; }
    .orunk-error strong, .orunk-purchase-error strong, .orunk-payment-error strong { color: #7f1d1d !important; }


    /* Feature Section Styling */
    .orunk-feature-section {
        background-color: #fff; /* White background */
        padding: 1.5rem;
        border-radius: 0.5rem;
        margin-bottom: 2.5rem;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .orunk-feature-section h2 {
        font-size: 1.5em;
        margin-top: 0;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #1f2937;
    }

    .orunk-feature-description {
        color: #6b7280;
        margin-bottom: 1rem;
        font-size: 0.95em;
        line-height: 1.6;
    }

    /* Current Plan Info Box */
    .orunk-current-plan-info {
        padding: 10px 15px;
        margin-bottom: 1rem;
        border-left-width: 4px;
        background-color: #eff6ff;
        border-color: #60a5fa;
        color: #1e40af;
        border-radius: 4px;
        font-size: 0.9em;
    }
    .orunk-current-plan-info p { margin: 0; }
    .orunk-current-plan-info strong { color: #1c3d5a; }
    .orunk-current-plan-info a { color: #1d4ed8; text-decoration: underline; margin-left: 10px; }

    /* --- Grid Layout for Plans --- */
    .orunk-pricing-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); /* Slightly larger min width */
        gap: 1.5rem; /* Increased gap */
        margin-top: 1.5rem;
    }
    /* Adjust grid columns on smaller screens if needed */
    @media (max-width: 640px) { /* sm breakpoint */
         .orunk-pricing-grid {
            grid-template-columns: 1fr; /* Stack to single column */
         }
    }


    /* --- Plan Card Styling --- */
    .orunk-plan-card {
        border: 1px solid #e5e7eb;
        background: #fff;
        padding: 1.25rem;
        border-radius: 0.5rem;
        display: flex;
        flex-direction: column;
        transition: all 0.2s ease-in-out;
        position: relative;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }

    .orunk-plan-card:hover {
        border-color: #a5b4fc;
        box-shadow: 0 4px 12px -1px rgba(0, 0, 0, 0.07), 0 2px 8px -1px rgba(0, 0, 0, 0.04);
        transform: translateY(-2px);
    }

    .orunk-plan-card.orunk-plan--active {
        border-color: #10b981;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
    }

    .orunk-plan-card.orunk-plan--active::after {
        content: 'Active'; position: absolute; top: 0.75rem; right: 0.75rem; background-color: #10b981; color: white; font-size: 0.65rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em;
    }

    .orunk-plan-card h3 {
        margin-top: 0; margin-bottom: 0.75rem; font-size: 1.15em; /* Slightly larger */ font-weight: 600; color: #1f2937;
    }

    .orunk-plan-price {
        margin-bottom: 1rem; font-weight: 600; color: #1f2937;
    }
    .orunk-plan-price strong { font-size: 1.5em; /* Larger price */ }
    .orunk-plan-price .duration { font-size: 0.8em; color: #6b7280; font-weight: 400; }

    .orunk-plan-description {
        font-size: 0.9em; color: #6b7280; flex-grow: 1; margin-bottom: 1rem; line-height: 1.5;
    }

    .orunk-plan-features {
        list-style: none; margin: 0 0 1rem 0; padding: 0.75rem 0 0 0; /* Increased padding-top */ font-size: 0.85em; color: #4b5563; flex-grow: 1; border-top: 1px solid #f3f4f6; margin-top: 1rem;
    }
    .orunk-plan-features li {
        display: flex; align-items: center; padding-left: 0; margin-bottom: 0.5rem; position: static;
    }
    .orunk-plan-features .feature-icon {
        position: static; margin-right: 0.5rem; flex-shrink: 0; width: 1em; text-align: center; color: #34d399; font-size: 1em; line-height: 1.4;
    }

    .orunk-plan-action { margin-top: auto; padding-top: 1rem; }

    /* Button Styling */
    .orunk-plan-action .button {
        display: block; width: 100%; text-align: center; padding: 0.7rem 1rem; /* Slightly larger padding */ font-size: 0.9em; font-weight: 600; /* Bolder */ border-radius: 0.375rem; cursor: pointer; transition: all 0.2s ease-in-out; border: 1px solid transparent; line-height: 1.5;
    }
    .orunk-plan-action .button:hover:not(:disabled) {
        transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .orunk-plan-action .button i { margin-right: 0.4em; font-size: 0.9em; }

    .orunk-plan-action .orunk-button-primary { background-color: #4f46e5; color: white; border-color: #4f46e5; }
    .orunk-plan-action .orunk-button-primary:hover:not(:disabled) { background-color: #4338ca; border-color: #4338ca; }

    .orunk-plan-action .orunk-button-active { background: #10b981; color: white; border-color: #10b981; cursor: default; opacity: 1 !important; }
    .orunk-plan-action .orunk-button-active:hover { transform: none; box-shadow: none; }

    .orunk-plan-action .orunk-button-disabled,
    .orunk-plan-action .orunk-button-upgrade[disabled] { background-color: #e5e7eb; color: #9ca3af; border-color: #d1d5db; cursor: not-allowed; opacity: 0.7; }
    .orunk-plan-action .orunk-button-disabled:hover,
    .orunk-plan-action .orunk-button-upgrade[disabled]:hover { transform: none; box-shadow: none; }

    /* Payment Gateway Selection */
    .orunk-payment-gateways label { display: block; margin-bottom: 5px; font-size: 0.85em; color: #4b5563; font-weight: 500; }
    .orunk-payment-gateways select { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.9em; background-color: white; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); appearance: none; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; padding-right: 2.5rem; }
    .orunk-payment-gateways select:focus { border-color: #a5b4fc; outline: 1px solid #a5b4fc; }


    /* --- FAQ Section Styling --- */
    .orunk-faq-section {
        margin-top: 4rem; /* More space above FAQ */
        padding-top: 2rem;
        border-top: 1px solid #e5e7eb;
    }
    .orunk-faq-section h2 {
        font-size: 1.75em;
        font-weight: 700;
        color: #1f2937;
        text-align: center;
        margin-bottom: 2rem;
    }
    .orunk-faq-grid {
        display: grid;
        grid-template-columns: 1fr; /* Stack on small screens */
        gap: 1.5rem;
    }
    @media (min-width: 768px) { /* md breakpoint */
        .orunk-faq-grid {
            grid-template-columns: repeat(2, 1fr); /* Two columns on medium screens */
        }
    }
    .orunk-faq-item details {
        background-color: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        transition: box-shadow 0.2s ease;
    }
    .orunk-faq-item details:hover {
         box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .orunk-faq-item summary {
        padding: 1rem 1.25rem;
        font-weight: 600;
        color: #374151;
        cursor: pointer;
        list-style: none; /* Remove default marker */
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .orunk-faq-item summary::-webkit-details-marker { display: none; } /* Hide marker in Chrome/Safari */
    .orunk-faq-item summary::after { /* Custom dropdown icon */
        content: '+';
        font-size: 1.2em;
        font-weight: bold;
        color: #9ca3af;
        transition: transform 0.2s ease;
    }
    .orunk-faq-item details[open] summary::after {
        content: '-';
        transform: rotate(180deg);
    }
    .orunk-faq-item .faq-answer {
        padding: 0 1.25rem 1.25rem 1.25rem;
        font-size: 0.9em;
        color: #6b7280;
        line-height: 1.6;
        border-top: 1px dashed #e5e7eb; /* Separator */
        margin-top: 0.5rem;
        padding-top: 1rem;
    }

    /* Ad Sidebar Specific Styles */
    .orunk-ad-container {
        padding: 0.5rem; /* Add some padding around ads */
        text-align: center; /* Center ad units if they don't fill width */
    }
    /* Style the ad unit itself if needed - AdSense often controls this */
    .adsbygoogle {
        background-color: #f3f4f6; /* Placeholder background */
        border: 1px dashed #d1d5db;
        min-height: 150px; /* Minimum height to avoid collapse before ad loads */
        display: block; /* Important for AdSense */
        margin-bottom: 1rem; /* Space between ads if stacking */
    }

</style>
<?php // --- END EMBEDDED CSS --- ?>

<div id="primary" <?php astra_primary_class(); ?>>
    <main id="main" class="site-main">
        <?php // Wrap content and sidebars ?>
        <div class="orunk-page-wrapper">

            <?php // Left Sidebar for Ads ?>
            <aside class="orunk-sidebar orunk-sidebar-left">
                <div class="orunk-ad-container">
                    <p style="font-size: 0.7rem; color: #9ca3af; margin-bottom: 5px;">Advertisement</p>
                    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4489112968240256" crossorigin="anonymous"></script>
                    <ins class="adsbygoogle"
                         style="display:block; width:160px; height:600px;" <?php // Example fixed size, adjust as needed ?>
                         data-ad-client="ca-pub-4489112968240256"
                         data-ad-slot="9686135945" <?php // Ensure this slot ID is correct for a sidebar format ?>
                         ></ins> <?php // Removed format=auto, full-width-responsive for fixed size ?>
                    <script>
                         (adsbygoogle = window.adsbygoogle || []).push({});
                    </script>
                     <?php // You can add more ad units here if needed ?>
                </div>
            </aside>

            <?php // Main Content Area ?>
            <div class="orunk-main-content">
                <div class="orunk-catalog-container ast-container"> <?php // Use Astra container if needed inside, else remove ?>
                    <div class="ast-row">
                        <div class="ast-col-lg-12 ast-col-md-12 ast-col-sm-12 ast-col-xs-12"> <?php // Astra column ?>

                            <?php /* Removed the <header> and <h1> title display */ ?>

                            <div class="entry-content clear" itemprop="text">

                                <?php
                                // Display any purchase error messages passed back via query args
                                if (isset($_GET['purchase_error'])) {
                                    echo '<div class="orunk-purchase-error notice notice-error inline"><p><strong>' . esc_html__('Purchase Error:', 'orunk-users') . '</strong> ' . esc_html(urldecode($_GET['purchase_error'])) . '</p></div>';
                                }
                                 if (isset($_GET['payment_error'])) {
                                     echo '<div class="orunk-payment-error notice notice-error inline"><p><strong>' . esc_html__('Payment Error:', 'orunk-users') . '</strong> ' . esc_html(urldecode($_GET['payment_error'])) . '</p></div>';
                                 }
                                ?>

                                <?php if (empty($features_with_plans)) : ?>
                                    <?php // Message if no features or plans are defined/active ?>
                                    <p><?php esc_html_e('No products or plans are currently available for purchase.', 'orunk-users'); ?></p>
                                <?php else : ?>

                                    <?php // Loop through each Feature (e.g., BIN API, Ad Removal) ?>
                                    <?php foreach ($features_with_plans as $feature) : ?>
                                        <?php
                                        $feature_key = $feature['feature'];
                                        $plans = $feature['plans']; // Active plans for this feature
                                        $active_plan_for_feature = null; // User's active plan for THIS feature

                                        // Check if the current user has an active plan for this feature
                                        if ($user_id) {
                                            $active_plan_for_feature = $orunk_core->get_user_active_plan($user_id, $feature_key);
                                        }
                                        ?>
                                        <?php // Use orunk-feature-section class ?>
                                        <section class="orunk-feature-section orunk-feature-<?php echo esc_attr($feature_key); ?>">

                                            <h2><?php echo esc_html($feature['product_name']); ?></h2>
                                            <?php if (!empty($feature['description'])) : ?>
                                                <?php // Use orunk-feature-description class ?>
                                                <p class="orunk-feature-description"><?php echo wp_kses_post($feature['description']); // Allow basic HTML in description ?></p>
                                            <?php endif; ?>

                                            <?php // Display user's current plan status for this feature, if they have one ?>
                                            <?php if ($active_plan_for_feature) : ?>
                                                <?php // Use orunk-current-plan-info class ?>
                                                <div class="orunk-current-plan-info notice notice-info inline">
                                                    <p>
                                                        <strong><?php esc_html_e('Your Current Plan:', 'orunk-users'); ?></strong> <?php echo esc_html($active_plan_for_feature['plan_name']); ?>.
                                                        <?php if ($active_plan_for_feature['expiry_date']) : ?>
                                                            <?php printf(esc_html__('Expires on: %s', 'orunk-users'), esc_html(date_i18n(get_option('date_format'), strtotime($active_plan_for_feature['expiry_date'])))); ?>
                                                        <?php endif; ?>
                                                        <a href="<?php echo esc_url(home_url('/orunk-dashboard/')); ?>"><?php esc_html_e('View Dashboard', 'orunk-users'); ?></a>
                                                    </p>
                                                </div>
                                            <?php endif; ?>

                                            <?php // Display plans only if there are active plans defined for this feature ?>
                                            <?php if (!empty($plans)) : ?>
                                                <?php // Use orunk-pricing-grid class for the grid container ?>
                                                <div class="orunk-pricing-grid">
                                                    <?php foreach ($plans as $plan) : ?>
                                                        <?php // Use orunk-plan-card class. Add 'orunk-plan--active' if it's the user's current plan ?>
                                                        <div class="orunk-plan-card orunk-plan-<?php echo esc_attr($plan['id']); ?> <?php echo ($active_plan_for_feature && $active_plan_for_feature['plan_id'] == $plan['id']) ? 'orunk-plan--active' : ''; ?>">
                                                            <h3><?php echo esc_html($plan['plan_name']); ?></h3>

                                                            <div class="orunk-plan-price">
                                                                <?php if (floatval($plan['price']) == 0) : ?>
                                                                    <strong><?php esc_html_e('Free', 'orunk-users'); ?></strong>
                                                                <?php else : ?>
                                                                    <strong>$<?php echo esc_html(number_format(floatval($plan['price']), 2)); ?></strong>
                                                                    <?php // Add class for duration text ?>
                                                                    <span class="duration"> / <?php echo esc_html($plan['duration_days']); ?> <?php esc_html_e('days', 'orunk-users'); ?></span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <?php if (!empty($plan['description'])) : ?>
                                                                <p class="orunk-plan-description"><?php echo esc_html($plan['description']); ?></p>
                                                            <?php endif; ?>

                                                            <ul class="orunk-plan-features">
                                                                 <li><span class="feature-icon">&#10004;</span><?php printf(esc_html__('%s Days Access', 'orunk-users'), esc_html($plan['duration_days'])); ?></li>
                                                                <?php if (strpos($feature_key, '_api') !== false) : ?>
                                                                    <li><span class="feature-icon">&#10004;</span>
                                                                        <?php echo isset($plan['requests_per_day']) && $plan['requests_per_day'] !== null ? sprintf(esc_html__('%s Requests / Day', 'orunk-users'), esc_html(number_format_i18n($plan['requests_per_day']))) : esc_html__('Unlimited Daily Requests', 'orunk-users'); ?>
                                                                    </li>
                                                                    <li><span class="feature-icon">&#10004;</span>
                                                                        <?php echo isset($plan['requests_per_month']) && $plan['requests_per_month'] !== null ? sprintf(esc_html__('%s Requests / Month', 'orunk-users'), esc_html(number_format_i18n($plan['requests_per_month']))) : esc_html__('Unlimited Monthly Requests', 'orunk-users'); ?>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <?php // Add other plan features dynamically if needed ?>
                                                            </ul>

                                                            <div class="orunk-plan-action">
                                                                <?php // --- Purchase Button Logic (Keep PHP logic, update CSS classes) --- ?>
                                                                <?php if ($active_plan_for_feature && $active_plan_for_feature['plan_id'] == $plan['id']) : // User has this specific plan active ?>
                                                                    <button class="button orunk-button-active" disabled>
                                                                        <i class="fas fa-check-circle"></i> <?php // Optional Icon ?>
                                                                        <?php esc_html_e('Currently Active', 'orunk-users'); ?>
                                                                    </button>
                                                                <?php elseif ($active_plan_for_feature) : // User has a different active plan for this feature ?>
                                                                    <?php $button_text = (floatval($plan['price']) > floatval($active_plan_for_feature['price'] ?? 0)) ? __('Upgrade', 'orunk-users') : __('Switch Plan', 'orunk-users'); ?>
                                                                     <?php if (floatval($plan['price']) > 0) : ?>
                                                                        <button class="button orunk-button-upgrade" disabled><?php echo esc_html($button_text); ?> (N/A)</button>
                                                                     <?php else: ?>
                                                                         <button class="button orunk-button-disabled" disabled><?php esc_html_e('Unavailable', 'orunk-users'); ?></button>
                                                                     <?php endif; ?>
                                                                <?php else : // User has no active plan for this feature ?>
                                                                    <?php if (!is_user_logged_in()) : // User not logged in ?>
                                                                        <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="button orunk-button-primary">
                                                                            <i class="fas fa-sign-in-alt"></i> <?php // Optional Icon ?>
                                                                            <?php esc_html_e('Login to Purchase', 'orunk-users'); ?>
                                                                        </a>
                                                                    <?php elseif (empty($available_gateways) && floatval($plan['price']) > 0) : // Logged in, no payment gateways for paid plan ?>
                                                                         <button class="button orunk-button-disabled" disabled><?php esc_html_e('Purchase Unavailable', 'orunk-users'); ?></button>
                                                                         <p style="text-align: center; font-size: 0.8em; margin-top: 5px;"><small><?php esc_html_e('Payment methods unavailable.', 'orunk-users'); ?></small></p>
                                                                    <?php else : // Logged in, show the purchase form ?>
                                                                        <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="orunk-purchase-form">
                                                                            <input type="hidden" name="action" value="orunk_purchase_plan">
                                                                            <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan['id']); ?>">
                                                                            <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url(wp_get_referer() ? wp_get_referer() : get_permalink()); // Send referring page URL ?>">
                                                                            <?php wp_nonce_field('orunk_purchase_plan_' . $plan['id']); ?>

                                                                            <?php if (floatval($plan['price']) > 0 && !empty($available_gateways)) : ?>
                                                                                <?php // Use orunk-payment-gateways class ?>
                                                                                <div class="orunk-payment-gateways" style="margin-bottom: 10px;">
                                                                                    <label for="payment_gateway_<?php echo esc_attr($plan['id']); ?>"><?php esc_html_e('Pay with:', 'orunk-users'); ?></label>
                                                                                    <select name="payment_gateway" id="payment_gateway_<?php echo esc_attr($plan['id']); ?>" required>
                                                                                        <?php foreach ($available_gateways as $gateway) : ?>
                                                                                            <option value="<?php echo esc_attr($gateway->id); ?>">
                                                                                                <?php echo esc_html($gateway->get_option('title', $gateway->method_title)); ?>
                                                                                            </option>
                                                                                        <?php endforeach; ?>
                                                                                    </select>
                                                                                </div>
                                                                            <?php else : // For free plan or if gateways are somehow missing for paid ?>
                                                                                <input type="hidden" name="payment_gateway" value="<?php echo (floatval($plan['price']) == 0) ? 'free_checkout' : 'bank'; // Default to bank if paid but no gateways? Review logic. ?>">
                                                                            <?php endif; ?>

                                                                            <button type="submit" class="button orunk-button-primary">
                                                                                <?php if(floatval($plan['price']) == 0): ?>
                                                                                    <i class="fas fa-gift"></i> <?php // Optional Icon ?>
                                                                                    <?php esc_html_e('Get Free Plan', 'orunk-users'); ?>
                                                                                <?php else: ?>
                                                                                    <i class="fas fa-shopping-cart"></i> <?php // Optional Icon ?>
                                                                                    <?php esc_html_e('Purchase Plan', 'orunk-users'); ?>
                                                                                <?php endif; ?>
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; // End logged-in check ?>
                                                                <?php endif; // End active plan check ?>
                                                                <?php // --- End Purchase Button Logic --- ?>
                                                            </div> <?php // .orunk-plan-action ?>
                                                        </div> <?php // .orunk-plan-card ?>
                                                    <?php endforeach; // End loop through plans ?>
                                                </div> <?php // .orunk-pricing-grid ?>
                                            <?php else : // No active plans available for this feature ?>
                                                <p><?php esc_html_e('There are currently no active plans available for this feature.', 'orunk-users'); ?></p>
                                            <?php endif; ?>

                                        </section> <?php // .orunk-feature-section ?>
                                    <?php endforeach; // End loop through features ?>

                                <?php endif; // End check for empty features_with_plans ?>

                                <?php // --- FAQ Section --- ?>
                                <section class="orunk-faq-section">
                                    <h2><?php esc_html_e('Frequently Asked Questions', 'orunk-users'); ?></h2>
                                    <div class="orunk-faq-grid">
                                        <?php // Column 1 ?>
                                        <div class="orunk-faq-column">
                                            <div class="orunk-faq-item">
                                                <details>
                                                    <summary><?php esc_html_e('How do I purchase a plan?', 'orunk-users'); ?></summary>
                                                    <div class="faq-answer">
                                                        <p><?php esc_html_e('Simply choose the plan that best suits your needs from the options above. If you\'re not logged in, you\'ll be prompted to log in or create an account. Then, select your preferred payment method (if applicable) and click the purchase button.', 'orunk-users'); ?></p>
                                                    </div>
                                                </details>
                                            </div>
                                            <div class="orunk-faq-item">
                                                <details>
                                                    <summary><?php esc_html_e('What payment methods are accepted?', 'orunk-users'); ?></summary>
                                                    <div class="faq-answer">
                                                        <p><?php esc_html_e('We currently accept payments via Stripe (Credit/Debit Card) and Direct Bank Transfer. Available options for paid plans are shown below the plan details.', 'orunk-users'); ?></p>
                                                    </div>
                                                </details>
                                            </div>
                                             <div class="orunk-faq-item">
                                                <details>
                                                    <summary><?php esc_html_e('How are API limits counted?', 'orunk-users'); ?></summary>
                                                    <div class="faq-answer">
                                                        <p><?php esc_html_e('API limits are typically counted per successful request made using your unique API key. Daily limits reset every 24 hours (UTC), and monthly limits usually reset based on your purchase date or calendar month, depending on the specific plan configuration.', 'orunk-users'); ?></p>
                                                    </div>
                                                </details>
                                            </div>
                                        </div>
                                        <?php // Column 2 ?>
                                        <div class="orunk-faq-column">
                                            <div class="orunk-faq-item">
                                                <details>
                                                    <summary><?php esc_html_e('Can I change my plan later?', 'orunk-users'); ?></summary>
                                                    <div class="faq-answer">
                                                        <p><?php esc_html_e('Yes, you can typically upgrade or switch between plans for the same feature directly from your dashboard. Downgrading or switching to a free plan might have specific conditions.', 'orunk-users'); ?></p>
                                                    </div>
                                                </details>
                                            </div>
                                            <div class="orunk-faq-item">
                                                <details>
                                                    <summary><?php esc_html_e('Where can I find my API key?', 'orunk-users'); ?></summary>
                                                    <div class="faq-answer">
                                                        <p><?php esc_html_e('If your purchased plan includes API access, your unique API key will be visible in your User Dashboard after the purchase is activated.', 'orunk-users'); ?></p>
                                                    </div>
                                                </details>
                                            </div>
                                             <div class="orunk-faq-item">
                                                <details>
                                                    <summary><?php esc_html_e('How do I cancel my subscription?', 'orunk-users'); ?></summary>
                                                    <div class="faq-answer">
                                                        <p><?php esc_html_e('You can manage your active subscriptions, including cancellation options, from your User Dashboard.', 'orunk-users'); ?></p>
                                                    </div>
                                                </details>
                                            </div>
                                        </div>
                                    </div> <?php // end .orunk-faq-grid ?>
                                </section>
                                <?php // --- End FAQ Section --- ?>

                            </div> <?php // .entry-content ?>
                        </div> <?php // Astra theme column ?>
                    </div> <?php // Astra theme row ?>
                </div> <?php // .orunk-catalog-container / Astra theme container ?>
            </div> <?php // .orunk-main-content ?>

            <?php // Right Sidebar for Ads ?>
            <aside class="orunk-sidebar orunk-sidebar-right">
                <div class="orunk-ad-container">
                     <p style="font-size: 0.7rem; color: #9ca3af; margin-bottom: 5px;">Advertisement</p>
                     <?php // Using the same ad unit for both sides - CHANGE SLOT ID if needed ?>
                    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4489112968240256" crossorigin="anonymous"></script>
                    <ins class="adsbygoogle"
                         style="display:block; width:160px; height:600px;" <?php // Example fixed size ?>
                         data-ad-client="ca-pub-4489112968240256"
                         data-ad-slot="9686135945"></ins> <?php // REMOVED format=auto, full-width-responsive ?>
                    <script>
                         (adsbygoogle = window.adsbygoogle || []).push({});
                    </script>
                    <?php // You can add more ad units here if needed ?>
                </div>
            </aside>

        </div> <?php // .orunk-page-wrapper ?>
    </main>
</div> <?php // #primary ?>

<?php
get_footer(); // Include theme footer
?>