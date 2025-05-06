<?php
/**
 * Template Name: Orunk Checkout (Merged Styling from Reference - Dual Terms Checkbox)
 *
 * Handles the checkout process for Orunk Users plans.
 * Displays order summary, billing details, and payment elements.
 * Uses HTML structure and styling from page-checkout-refrence.txt,
 * integrated with the dynamic PHP and functional JS from the original page-checkout.php.
 * Includes two 'Complete Purchase' buttons, sticky mobile button, and dual synchronized terms checkboxes.
 *
 * @package YourThemeName
 * @version 1.5.1 - JS Fixes for PayPal AJAX and Variable Scope
 */

// --- Security & Setup ---
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Redirect non-logged-in users
if (!is_user_logged_in()) {
    $redirect_url = add_query_arg($_GET, get_permalink()); // Preserve query args like plan_id
    wp_safe_redirect(wp_login_url($redirect_url));
    exit;
}

// Check for required plugin components and country function
if (!class_exists('Custom_Orunk_Core') || !class_exists('Custom_Orunk_DB') || !function_exists('orunk_get_countries')) {
    get_header();
    $missing_components = [];
    if (!class_exists('Custom_Orunk_Core')) $missing_components[] = 'Orunk Core';
    if (!class_exists('Custom_Orunk_DB')) $missing_components[] = 'Orunk DB';
    if (!function_exists('orunk_get_countries')) $missing_components[] = 'Country Loader';
    // Basic error display, can be styled better if needed
    echo '<div style="border: 1px solid red; background: #ffe0e0; padding: 15px; margin: 20px;">Required components missing: ' . implode(', ', $missing_components) . '. Please contact support.</div>';
    get_footer();
    exit;
}

// --- Get Data for Checkout (From original page-checkout.php) ---
global $wpdb;
$current_user = wp_get_current_user();
$user_id      = $current_user->ID;
$orunk_core   = new Custom_Orunk_Core();

$plan_id_to_purchase   = isset($_GET['plan_id']) ? absint($_GET['plan_id']) : 0;
$existing_purchase_id = isset($_GET['purchase_id']) ? absint($_GET['purchase_id']) : 0;
$checkout_type        = $existing_purchase_id ? 'switch' : 'new_purchase';

$plan = $plan_id_to_purchase ? $orunk_core->get_plan_details($plan_id_to_purchase) : null;

// --- Plan Validation (From original) ---
if (!$plan) {
    get_header();
    // Use Tailwind classes consistent with reference for error display
    echo '<div class="max-w-4xl mx-auto px-4 py-6"><div class="card p-5 bg-red-50 border-red-200 text-red-700">Invalid plan selected for checkout. Please <a href="' . esc_url(home_url('/orunk-catalog/')) .'" class="font-medium underline text-red-800">return to catalog</a>.</div></div>';
    get_footer();
    exit;
}

$plan_price = floatval($plan['price']); // Store as float

// --- Existing Purchase Validation (From original) ---
if ($checkout_type === 'switch') {
    $existing_purchase = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}orunk_user_purchases WHERE id = %d AND user_id = %d AND status = 'active'",
        $existing_purchase_id, $user_id
    ), ARRAY_A);
    if (!$existing_purchase || empty($existing_purchase['product_feature_key']) || $existing_purchase['product_feature_key'] !== $plan['product_feature_key'] || $existing_purchase['plan_id'] == $plan_id_to_purchase) {
        get_header();
        echo '<div class="max-w-4xl mx-auto px-4 py-6"><div class="card p-5 bg-red-50 border-red-200 text-red-700">Invalid plan switch conditions. Please <a href="' . esc_url(home_url('/orunk-dashboard/')) .'" class="font-medium underline text-red-800">return to dashboard</a>.</div></div>';
        get_footer();
        exit;
    }
}

// --- Get Gateways & Stripe Key (From original) ---
$available_gateways = $orunk_core->get_available_payment_gateways();
$stripe_publishable_key = null;
if (isset($available_gateways['stripe'])) {
    $stripe_gateway = $available_gateways['stripe'];
    $stripe_publishable_key = $stripe_gateway->get_option('publishable_key');
}

// --- Fetch Billing Address (From original) ---
$saved_billing_address = [];
$billing_keys = [
    'billing_first_name', 'billing_last_name', 'billing_company',
    'billing_address_1', 'billing_address_2', 'billing_city',
    'billing_state', 'billing_postcode', 'billing_country',
    'billing_email', 'billing_phone'
];
foreach ($billing_keys as $key) {
    $saved_billing_address[$key] = get_user_meta($user_id, $key, true);
}
$saved_billing_address['billing_first_name'] = $saved_billing_address['billing_first_name'] ?: $current_user->first_name;
$saved_billing_address['billing_last_name'] = $saved_billing_address['billing_last_name'] ?: $current_user->last_name;
$saved_billing_address['billing_email'] = $saved_billing_address['billing_email'] ?: $current_user->user_email;
// Note: The reference form doesn't have a separate company field visually,
// but the name exists in the data, so we keep fetching it. It won't display unless added to HTML.

// Get Country list
$countries = function_exists('orunk_get_countries') ? orunk_get_countries() : ['US' => 'United States']; // Fallback
// --- Get Checkout Field Settings ---
$checkout_field_settings = get_option('orunk_checkout_fields_settings', []);
// Helper variables for easier checking (default to 'yes' = enabled if not set)
$is_first_name_enabled = ($checkout_field_settings['enable_billing_first_name'] ?? 'yes') === 'yes';
$is_last_name_enabled  = ($checkout_field_settings['enable_billing_last_name'] ?? 'yes') === 'yes';
$is_email_enabled      = ($checkout_field_settings['enable_billing_email'] ?? 'yes') === 'yes';
// Company field doesn't have an input in the form, so no check needed here
$is_address_1_enabled  = ($checkout_field_settings['enable_billing_address_1'] ?? 'yes') === 'yes';
$is_address_2_enabled  = ($checkout_field_settings['enable_billing_address_2'] ?? 'yes') === 'yes'; // Separate check for line 2
$is_city_enabled       = ($checkout_field_settings['enable_billing_city'] ?? 'yes') === 'yes';
$is_state_enabled      = ($checkout_field_settings['enable_billing_state'] ?? 'yes') === 'yes';
$is_postcode_enabled   = ($checkout_field_settings['enable_billing_postcode'] ?? 'yes') === 'yes';
$is_country_enabled    = ($checkout_field_settings['enable_billing_country'] ?? 'yes') === 'yes';
$is_phone_enabled      = ($checkout_field_settings['enable_billing_phone'] ?? 'yes') === 'yes';
// --- End Checkout Field Settings ---

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?> Checkout - Orunk Developer Tools & APIs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Tailwind config from reference
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'together-blue': '#2563eb',
                        'together-indigo': '#4f46e5',
                        'together-purple': '#7c3aed',
                        'together-dark': '#0f172a',
                        'together-light': '#f8fafc',
                    },
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        /* --- Copied styles from page-checkout-refrence.txt --- */
        :root {
            --together-primary: #2563eb;
            --together-secondary: #7c3aed;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #0f172a;
            background-color: #f8fafc;
        }

        .gradient-text {
            background: linear-gradient(90deg, var(--together-primary) 0%, var(--together-secondary) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .primary-btn {
            background: linear-gradient(to right, #2563eb, #7c3aed);
            transition: all 0.3s ease;
        }

        /* Add :not(:disabled) to hover effect */
        .primary-btn:hover:not(:disabled) {
            background: linear-gradient(to right, #1e40af, #6d28d9);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
         /* Style for disabled button */
        .primary-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .input-field {
            /* Combined styles from both files */
            display: block;
            width: 100%;
            padding: 0.6rem 0.8rem; /* Consistent padding */
            font-size: 0.875rem; /* 14px */
            line-height: 1.5;
            color: #1f2937;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #e2e8f0;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            border-radius: 0.5rem;
            box-shadow: inset 0 1px 2px rgba(0,0,0,.075);
            transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
        }
         /* Added placeholder styling */
        .input-field::placeholder {
             color: #9ca3af; /* gray-400 */
             opacity: 1;
        }

        .input-field:focus {
            border-color: #2563eb; /* together-blue */
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: 0;
        }

        .badge-premium {
            background: linear-gradient(90deg, #8b5cf6 0%, #ec4899 100%);
        }

        /* Keep original progress steps styling if needed, else remove */
        .progress-step.active {
            background: linear-gradient(to right, #2563eb, #7c3aed);
            color: white;
        }
        /* Added form label styling from original */
         label.form-label {
             display: block;
             margin-bottom: 0.25rem;
             font-size: 0.75rem; /* text-xs */
             font-weight: 500; /* font-medium */
             color: #4b5563; /* text-gray-600 */
         }

        /* Terms checkbox styling */
        .terms-checkbox {
            padding: 12px 16px;
            background-color: #f8fafc;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            margin-bottom: 1rem; /* Added margin-bottom */
        }
        .terms-checkbox:hover {
            background-color: #f1f5f9;
        }
        /* Ensure correct selector from original for checkbox */
        .form-checkbox {
            height: 1rem;
            width: 1rem;
            border-color: #d1d5db; /* gray-300 */
            border-radius: 0.25rem;
            color: #4f46e5; /* indigo-600 */
            transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            flex-shrink: 0; /* Prevent checkbox from shrinking */
        }
         .form-checkbox:checked {
            background-color: #4f46e5; /* indigo-600 */
            border-color: #4f46e5;
        }
        .form-checkbox:focus {
             border-color: #a5b4fc; /* indigo-300 */
             outline: 0;
             box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.25); /* Ring */
         }
        /* Error state for checkbox container */
        .terms-checkbox.input-error {
            border: 1px solid #ef4444; /* Red border for the container */
            background-color: #fee2e2; /* Light red background */
        }

        /* Loader animation (merged) */
        .loader, .orunk-spinner { /* Apply to both classes */
            display: inline-block; /* Changed from block for use inside button */
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            vertical-align: middle; /* Align nicely in buttons */
        }
        .orunk-spinner {
            /* Specific overrides from original if needed */
             border: 3px solid rgba(99, 102, 241, 0.2); /* Lighter primary */
             border-top-color: var(--together-primary); /* Match button gradient start */
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Error message styling */
        .error-message, #orunk-card-errors, #promo-error-message { /* Apply to multiple error displays */
            color: #ef4444; /* text-red-500 */
            font-size: 0.75rem; /* text-xs */
            margin-top: 0.25rem;
            display: block; /* Ensure it shows */
            min-height: 1rem; /* Ensure space is reserved */
        }
         #orunk-card-errors { margin-top: 8px; } /* Specific margin for card errors */
         /* Hide if empty */
         #orunk-card-errors:empty,
         #promo-error-message.hidden,
         div.error-message:not(:has(span:not(:empty))) { /* Hide if span inside is empty */
            display: none;
         }


        .input-error, .input-field.input-error { /* Apply error style to input */
            border-color: #ef4444 !important; /* Tailwind !important might be needed */
        }
        .input-error:focus, .input-field.input-error:focus {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
        }
        /* General Orunk error box styling from original */
        .orunk-error {
             padding: 1rem; /* p-4 */
             margin-bottom: 1rem; /* mb-4 */
             border: 1px solid #fecaca; /* border-red-300 */
             background-color: #fef2f2; /* bg-red-100 */
             color: #991b1b; /* text-red-800 */
             border-radius: 0.5rem; /* rounded-lg */
             font-size: 0.875rem; /* text-sm */
         }
         /* Global Payment Message Area */
         #orunk-payment-message { min-height: 40px; } /* Reserve space */
         #orunk-payment-message:empty { display: none; } /* Hide if no text */

        /* Stripe Element container styling */
        #orunk-card-element {
             border: 1px solid #e2e8f0;
             border-radius: 0.5rem;
             padding: 10px 12px; /* Adjust padding as needed */
             background-color: white;
        }
        #orunk-card-element.StripeElement--focus {
              border-color: #2563eb;
             box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        #orunk-card-element.StripeElement--invalid,
        #orunk-card-element.input-error { /* Apply error style for Stripe element */
             border-color: #ef4444 !important;
        }

         /* Payment Method Selection Styling (from original) */
         .payment-method-option label { /* Style the label container */
            transition: border-color 0.2s ease-in-out;
         }
         /* Target the label's border when the hidden radio inside is checked */
         .payment-method-option input[type="radio"]:checked + label {
             border-color: #4f46e5 !important; /* indigo-600 */
             background-color: #eef2ff; /* indigo-50 */
         }
         /* Target the label's border when the hidden radio inside is focused */
         .payment-method-option input[type="radio"]:focus-visible + label {
             box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.25);
         }

         /* Show payment details when radio is checked (Updated Selector) */
         .payment-details {
             display: none; /* Hide by default */
             margin-top: 0.75rem; /* mt-3 */
             padding-left: 2.5rem; /* pl-10 - Indent content */
             padding-right: 0.75rem; /* pr-3 */
         }
         .payment-method-option input[type="radio"]:checked ~ .payment-details {
             display: block; /* Show when checked */
         }


         /* Bank Details Styling (from original) */
         .bank-details {
            background-color: #f9fafb; /* gray-50 */
            padding: 0.75rem; /* p-3 */
            border-radius: 0.375rem; /* rounded-md */
            border: 1px dashed #e5e7eb; /* border-gray-200 */
            font-size: 0.75rem; /* text-xs */
            white-space: pre-wrap;
            line-height: 1.5;
            color: #4b5563; /* gray-600 */
            margin-top: 0.5rem; /* mt-2 */
        }


        /* Responsive and Sticky Button */
        @media (max-width: 768px) {
            .checkout-grid {
                grid-template-columns: 1fr !important;
            }
            /* Hide the non-sticky buttons on mobile */
            #complete-purchase-left-container, #complete-purchase-right-container { /* Target containers */
                display: none;
            }
             /* Pad bottom of main content so sticky button doesn't overlap */
             main.max-w-4xl { padding-bottom: 100px; }
        }
        .sticky-purchase-btn {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 50;
            padding: 16px;
            background: white;
            box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1);
            display: none; /* Hidden by default */
        }
        @media (max-width: 768px) {
            .sticky-purchase-btn {
                display: block; /* Show only on mobile */
            }
        }

        /* Ensure spinner hides when button is not processing (Updated logic) */
        button .button-text-content { display: inline; } /* Show text by default */
        button .button-spinner-content { display: none; } /* Hide spinner by default */

        button.is-processing .button-text-content { display: none; } /* Hide text when processing */
        button.is-processing .button-spinner-content { display: inline-block; } /* Show spinner when processing */

        /* Style for the submit spinner specifically */
        .submit-spinner {
            /* Using .loader styles from reference */
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            display: inline-block; /* Needed for spinner */
            vertical-align: middle;
        }

        .hidden { display: none !important; } /* Utility class */

    </style>
    <?php wp_head(); ?>
</head>
<body <?php body_class('font-inter antialiased text-together-dark bg-together-light min-h-screen'); ?>>

    <?php // Optional Header Placeholder (can use theme's header) ?>

    <main class="max-w-4xl mx-auto px-4 py-6 md:py-10">
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-semibold progress-step active shadow-md">1</div>
                <div class="ml-2 text-sm font-medium text-indigo-600">Billing & Payment</div>
            </div>
            <div class="flex-1 h-0.5 mx-3 bg-gray-200"></div>
            <div class="flex items-center opacity-50">
                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-semibold progress-step">2</div>
                <div class="ml-2 text-sm font-medium text-gray-500">Complete</div>
            </div>
        </div>

         <?php // --- Global Message Area --- ?>
         <div id="orunk-payment-message" class="hidden p-3 mb-4 text-sm border rounded-lg" role="alert"></div>

        <?php // --- Main Checkout Form --- ?>
        <form id="orunk-payment-form" method="POST" novalidate>

            <div class="grid checkout-grid grid-cols-1 lg:grid-cols-3 gap-6">

                <?php // --- Left Column - Billing & Payment --- ?>
                <div class="lg:col-span-2 space-y-4">

                        <?php // --- BILLING CARD --- ?>
                        <div class="card p-5">
                            <h2 class="text-lg font-bold mb-4">Billing Information</h2>
                            <div id="full-billing-form" class="space-y-4">
                                 <?php // Billing fields with PHP values & error divs ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <?php if ($is_first_name_enabled): ?>
                                    <div> <?php // Opening Div for First Name ?>
                                        <label for="billing_first_name" class="form-label">First Name <span class="text-red-500">*</span></label>
                                        <input type="text" id="billing_first_name" name="billing_first_name" class="input-field w-full px-4 py-2 text-sm" placeholder="Enter first name" value="<?php echo esc_attr($saved_billing_address['billing_first_name']); ?>" required>
                                        <div id="billing_first_name-error" class="error-message"><span></span></div>
                                    </div> <?php // Closing Div for First Name ?>
                                    <?php endif; ?> <?php // <-- CORRECT: endif is AFTER the closing div ?>

                                    <?php // --- Start of Last Name block --- ?>
                                    <?php if ($is_last_name_enabled): ?>
                                    <div>
                                        <label for="billing_last_name" class="form-label">Last Name <span class="text-red-500">*</span></label>
                                        <input type="text" id="billing_last_name" name="billing_last_name" class="input-field w-full px-4 py-2 text-sm" placeholder="Enter last name" value="<?php echo esc_attr($saved_billing_address['billing_last_name']); ?>" required>
                                        <div id="billing_last_name-error" class="error-message"><span></span></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php // --- End of Last Name block --- ?>

                                </div> <?php // End grid for First/Last Name ?>

                                <?php // --- Start of Email block --- ?>
                                <?php if ($is_email_enabled): ?>
                                <div class="mb-4">
                                    <label for="billing_email" class="form-label">Email Address <span class="text-red-500">*</span></label>
                                    <input type="email" id="billing_email" name="billing_email" class="input-field w-full px-4 py-2 text-sm" placeholder="Enter email address" value="<?php echo esc_attr($saved_billing_address['billing_email']); ?>" required>
                                    <div id="billing_email-error" class="error-message"><span></span></div>
                                </div>
                                <?php endif; ?>
                                <?php // --- End of Email block --- ?>

                                <?php // --- Start of Address block --- ?>
                                <?php if ($is_address_1_enabled): ?>
                                <div class="mb-4">
                                    <label for="billing_address_1" class="form-label">Street Address <span class="text-red-500">*</span></label>
                                    <input type="text" id="billing_address_1" name="billing_address_1" class="input-field w-full px-4 py-2 text-sm mb-2" placeholder="Street Address Line 1" value="<?php echo esc_attr($saved_billing_address['billing_address_1'] ?? ''); ?>" required>
                                    <?php if ($is_address_2_enabled): // Optional inner check for Address 2 ?>
                                    <input type="text" id="billing_address_2" name="billing_address_2" class="input-field w-full px-4 py-2 text-sm" placeholder="Apartment, suite, etc. (optional)" value="<?php echo esc_attr($saved_billing_address['billing_address_2'] ?? ''); ?>">
                                    <?php endif; ?>
                                    <div id="billing_address_1-error" class="error-message"><span></span></div>
                                </div>
                                <?php endif; ?>
                                <?php // --- End of Address block --- ?>

                                <?php // --- Start of City/State grid --- ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <?php if ($is_city_enabled): ?>
                                    <div>
                                        <label for="billing_city" class="form-label">City <span class="text-red-500">*</span></label>
                                        <input type="text" id="billing_city" name="billing_city" class="input-field w-full px-4 py-2 text-sm" placeholder="Enter city" value="<?php echo esc_attr($saved_billing_address['billing_city'] ?? ''); ?>" required>
                                        <div id="billing_city-error" class="error-message"><span></span></div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($is_state_enabled): ?>
                                    <div>
                                        <label for="billing_state" class="form-label">State / Province</label>
                                        <input type="text" id="billing_state" name="billing_state" class="input-field w-full px-4 py-2 text-sm" placeholder="Enter state/province" value="<?php echo esc_attr($saved_billing_address['billing_state'] ?? ''); ?>">
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php // --- End of City/State grid --- ?>

                                <?php // --- Start of ZIP/Country grid --- ?>
                                 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <?php if ($is_postcode_enabled): ?>
                                    <div>
                                        <label for="billing_postcode" class="form-label">ZIP / Postal Code</label>
                                        <input type="text" id="billing_postcode" name="billing_postcode" class="input-field w-full px-4 py-2 text-sm" placeholder="Enter ZIP/Postal code" value="<?php echo esc_attr($saved_billing_address['billing_postcode'] ?? ''); ?>">
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($is_country_enabled): ?>
                                    <div>
                                        <label for="billing_country" class="form-label">Country <span class="text-red-500">*</span></label>
                                        <select id="billing_country" name="billing_country" class="input-field w-full px-4 py-2 text-sm" required>
                                            <?php
                                            $current_country = $saved_billing_address['billing_country'] ?? '';
                                            printf( '<option value="" %s>%s</option>', ($current_country === '' ? 'selected' : ''), esc_html__('Select country...', 'orunk-users'));
                                            foreach ($countries as $code => $name) {
                                                printf( '<option value="%s" %s>%s</option>', esc_attr($code), selected($current_country, $code, false), esc_html($name));
                                            }
                                            ?>
                                        </select>
                                        <div id="billing_country-error" class="error-message"><span></span></div>
                                    </div>
                                    <?php endif; ?>
                                 </div>
                                 <?php // --- End of ZIP/Country grid --- ?>

                                 <?php // --- Start of Phone block --- ?>
                                 <?php if ($is_phone_enabled): ?>
                                 <div>
                                    <label for="billing_phone" class="form-label">Phone (Optional)</label>
                                    <input type="tel" id="billing_phone" name="billing_phone" class="input-field w-full px-4 py-2 text-sm" value="<?php echo esc_attr($saved_billing_address['billing_phone'] ?? ''); ?>" placeholder="Enter phone number">
                                 </div>
                                 <?php endif; ?>
                                 <?php // --- End of Phone block --- ?>

                            </div> <?php // End #full-billing-form ?>
                        </div> <?php // End Billing Card ?>

                    <?php // --- Payment Method Card --- ?>
                    <div id="payment-method-card" class="card p-5 <?php echo ($plan_price <= 0) ? 'hidden' : ''; ?>">
                        <h2 class="text-lg font-bold mb-4">Payment Method</h2>
                        <div class="space-y-3">
                            <?php if (empty($available_gateways)): ?>
                                <div class="p-3 text-sm text-red-700 bg-red-100 border border-red-200 rounded-md">
                                    <?php esc_html_e('No payment methods are currently available.', 'orunk-users'); ?>
                                </div>
                            <?php else: ?>
                                <?php $first_gateway = true; ?>
                                <?php foreach ($available_gateways as $gateway_id => $gateway): ?>
                                    <?php // --- Skip Bank transfer if disabled in gateway settings ---
                                    if ($gateway_id === 'bank' && $gateway->enabled !== 'yes') continue;
                                    // --- Skip Stripe if publishable key is missing ---
                                    if ($gateway_id === 'stripe' && empty($stripe_publishable_key)) continue;
                                    ?>
                                    <?php
                                    $gateway_title = esc_html($gateway->get_option('title', $gateway->method_title));
                                    $gateway_icon_html = '';
                                    if ($gateway_id === 'stripe') $gateway_icon_html = '<i class="far fa-credit-card mr-3 text-lg"></i>';
                                    elseif ($gateway_id === 'paypal') $gateway_icon_html = '<i class="fab fa-paypal mr-3 text-lg text-blue-600"></i>'; // Added PayPal icon
                                    elseif ($gateway_id === 'bank') $gateway_icon_html = '<i class="fas fa-university mr-3 text-lg text-gray-500"></i>';
                                    ?>
                                    <div class="payment-method-option">
                                        <input id="payment_method_<?php echo esc_attr($gateway_id); ?>" name="payment_method" type="radio" value="<?php echo esc_attr($gateway_id); ?>" class="sr-only" <?php checked($first_gateway); ?> data-gateway-id="<?php echo esc_attr($gateway_id); ?>">
                                        <label for="payment_method_<?php echo esc_attr($gateway_id); ?>" class="flex items-center p-3 border border-gray-200 rounded-lg hover:border-blue-500 transition-colors cursor-pointer has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                                            <span class="flex items-center text-sm font-medium text-gray-700">
                                                 <?php echo $gateway_icon_html; ?>
                                                 <span><?php echo $gateway_title; ?></span>
                                            </span>
                                        </label>
                                        <div class="payment-details hidden" id="payment_details_<?php echo esc_attr($gateway_id); ?>">
                                            <?php if(!empty($gateway->get_option('description'))): ?>
                                             <p class="text-sm text-gray-600 mb-3"><?php echo esc_html($gateway->get_option('description')); ?></p>
                                            <?php endif; ?>
                                            <?php if ($gateway_id === 'stripe' && $stripe_publishable_key): ?>
                                                <div id="card-details" class="space-y-3">
                                                     <div>
                                                        <label for="orunk-card-element" class="form-label !mb-1.5">Card Details</label>
                                                        <div id="orunk-card-element" class="input-field p-3"></div> <?php // Stripe Element Container ?>
                                                        <div id="orunk-card-errors" role="alert" class="error-message"></div> <?php // Stripe Error Display ?>
                                                    </div>
                                                </div>
                                            <?php elseif ($gateway_id === 'paypal'): ?>
                                                <?php // PayPal doesn't need extra fields here; user will be redirected ?>
                                                <p class="text-sm text-gray-500 italic"><?php esc_html_e('You will be redirected to PayPal to complete your payment securely.', 'orunk-users'); ?></p>
                                            <?php elseif ($gateway_id === 'bank'): ?>
                                                <?php // Bank instructions ?>
                                                <?php
                                                $instructions = $gateway->get_option('instructions') . "\n\n--- Account Details ---\n" .
                                                                "Bank Name: " . $gateway->get_option('bank_name') . "\n" .
                                                                "Account Number: " . $gateway->get_option('account_number') . "\n" .
                                                                "Sort Code/Routing: " . $gateway->get_option('routing_number');
                                                if ($contact = $gateway->get_option('bank_contact_email')) { $instructions .= "\nContact: " . $contact; }
                                                $instructions .= "\n---------------------\n" . "Please use your Purchase ID (shown after checkout) as the payment reference.";
                                                ?>
                                                <div class="bank-details"><?php echo nl2br(esc_html($instructions)); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php $first_gateway = false; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div> <?php // End Payment Method Card ?>

                    <?php // --- Left Side Purchase Button Container --- ?>
                    <div id="complete-purchase-left-container" class="<?php echo ($plan_price <= 0) ? 'hidden' : ''; // Hide if free ?>">
                         <div class="card p-5">
                            <?php // --- ADDED: Left Side Terms Checkbox --- ?>
                            <div class="terms-checkbox" id="terms-left-container"> <?php // Container with unique ID ?>
                                <div class="flex items-start">
                                    <?php // Use same name 'terms', unique id 'terms-left' ?>
                                    <input id="terms-left" name="terms" type="checkbox" class="form-checkbox h-4 w-4 mt-0.5" required>
                                    <label for="terms-left" class="ml-3 block text-sm text-gray-700">
                                        I agree to the <a href="#" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">Terms</a> and <a href="#" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">Privacy Policy</a>
                                    </label>
                                </div>
                                <div id="terms-left-error" class="error-message ml-7"><span></span></div> <?php // Error div with unique ID ?>
                            </div>
                            <?php // --- End Left Side Terms Checkbox --- ?>

                            <div id="form-error-left" class="hidden mb-4 p-3 bg-red-50 text-red-600 rounded-lg text-sm">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    <span id="form-error-text-left">Please fix the errors above</span>
                                </div>
                            </div>
                            <?php
                            $button_text_left = ($checkout_type === 'switch') ? __('Confirm Plan Switch', 'orunk-users') : __('Complete Purchase', 'orunk-users');
                            $button_id_left = 'complete-purchase-left';
                            ?>
                             <button type="submit" id="<?php echo esc_attr($button_id_left); ?>" class="w-full px-4 py-3 rounded-lg font-semibold text-white primary-btn shadow-md hover:shadow-lg transition-all text-sm relative">
                                <span class="button-text-content" id="button-text-left"><?php echo esc_html($button_text_left); ?> <span class="font-bold">$<?php echo esc_html(number_format($plan_price, 2)); ?></span></span>
                                <span class="button-spinner-content" id="submit-spinner-left"><span class="submit-spinner loader"></span></span>
                             </button>
                         </div>
                    </div>

                </div> <?php // End Left Column ?>

                <?php // --- Right Column - Order Summary --- ?>
                <div class="space-y-4">
                    <div class="card p-5">
                        <h2 class="text-lg font-bold mb-4">Order Summary</h2>
                        <div class="flex justify-between items-center mb-3 pb-3 border-b border-gray-100">
                            <div>
                                <h3 class="font-bold text-sm"><?php echo esc_html($plan['plan_name']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo esc_html($plan['product_feature_key']); ?> - <?php echo esc_html($plan['duration_days']); ?> <?php esc_html_e('days', 'orunk-users'); ?></p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold text-white badge-premium">PLAN</span>
                        </div>
                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="font-medium">$<?php echo esc_html(number_format($plan_price, 2)); ?></span>
                            </div>
                            <?php /* Tax Line Placeholder */ ?>
                        </div>
                        <div class="flex justify-between text-base font-bold pt-3 border-t border-gray-200 mb-4">
                            <span>Total</span>
                            <span>$<?php echo esc_html(number_format($plan_price, 2)); ?></span>
                        </div>

                        <?php // --- Original Right Side Terms Checkbox --- ?>
                        <div class="terms-checkbox" id="terms-right-container"> <?php // Container with unique ID ?>
                            <div class="flex items-start">
                                <?php // Original ID 'terms', same name 'terms' ?>
                                <input id="terms" name="terms" type="checkbox" class="form-checkbox h-4 w-4 mt-0.5" required>
                                <label for="terms" class="ml-3 block text-sm text-gray-700">
                                    I agree to the <a href="#" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">Terms</a> and <a href="#" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">Privacy Policy</a>
                                </label>
                            </div>
                            <div id="terms-error" class="error-message ml-7"><span></span></div> <?php // Original error div ?>
                        </div>
                        <?php // --- End Right Side Terms Checkbox --- ?>

                        <?php // General Form Error ?>
                        <div id="form-error" class="hidden mb-4 p-3 bg-red-50 text-red-600 rounded-lg text-sm">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <span id="form-error-text">Please fix the errors above</span>
                            </div>
                        </div>

                        <?php // Right Side Purchase Button Container ?>
                        <div id="complete-purchase-right-container">
                            <?php
                            $button_text_right = ($checkout_type === 'switch') ? __('Confirm Plan Switch', 'orunk-users') : (($plan_price > 0) ? __('Complete Purchase', 'orunk-users') : __('Get Free Plan', 'orunk-users'));
                            $button_id_right = 'complete-purchase';
                            ?>
                            <button type="submit" id="<?php echo esc_attr($button_id_right); ?>" class="w-full px-4 py-3 rounded-lg font-semibold text-white primary-btn shadow-md hover:shadow-lg transition-all text-sm relative">
                                <span class="button-text-content" id="button-text"><?php echo esc_html($button_text_right); ?> <?php if ($plan_price > 0): ?><span class="font-bold">$<?php echo esc_html(number_format($plan_price, 2)); ?></span><?php endif; ?></span>
                                <span class="button-spinner-content" id="submit-spinner"><span class="submit-spinner loader"></span></span>
                            </button>
                        </div>

                        <?php // Security Badges ?>
                        <div class="flex flex-wrap justify-center gap-4 mt-4">
                            <div class="flex items-center text-xs text-gray-500"><i class="fas fa-lock mr-1"></i> 256-bit SSL</div>
                            <div class="flex items-center text-xs text-gray-500"><i class="fas fa-shield-alt mr-1"></i> PCI Compliant</div>
                        </div>
                    </div> <?php // End Summary Card ?>

                    <?php // Promo Code Section ?>
                     <div class="card p-4">
                        <h3 class="text-sm font-semibold mb-2">Promo Code</h3>
                        <div class="flex">
                            <input type="text" id="promo-code" name="promo-code" placeholder="Enter promo code" class="input-field w-full px-3 py-2 text-sm rounded-r-none border-r-0">
                            <button type="button" id="apply-promo-code" class="px-4 py-2 bg-gray-100 border border-gray-300 rounded-r-md text-sm font-medium text-gray-700 hover:bg-gray-200 transition-colors">
                                Apply
                            </button>
                        </div>
                        <div id="promo-error-message" class="error-message hidden"><span></span></div>
                        <div id="promo-success-message" class="text-green-600 text-xs mt-1 hidden"><span></span></div>
                    </div>

                    <?php // Guarantee Box ?>
                    <div class="card p-4 bg-blue-50 border-blue-100">
                        <div class="flex items-start">
                             <div class="flex-shrink-0"><i class="fas fa-shield-alt text-blue-500 text-lg mt-0.5"></i></div>
                             <div class="ml-3">
                                <h3 class="text-sm font-semibold text-blue-800">30-Day Money Back Guarantee</h3>
                                <p class="text-sm text-blue-600 mt-1">If you're not satisfied, we'll refund your payment.</p>
                            </div>
                        </div>
                    </div>

                    <?php // Support Box ?>
                    <div class="card p-4 bg-gray-50 border-gray-200">
                        <div class="flex items-start">
                            <div class="flex-shrink-0"><i class="fas fa-headset text-gray-500 text-lg mt-0.5"></i></div>
                            <div class="ml-3">
                                <h3 class="text-sm font-semibold text-gray-800">24/7 Customer Support</h3>
                                <p class="text-sm text-gray-600 mt-1">Contact us anytime for help with your purchase.</p>
                            </div>
                        </div>
                    </div>

                     <?php // Hidden fields ?>
                     <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan_id_to_purchase); ?>">
                     <input type="hidden" name="checkout_type" value="<?php echo esc_attr($checkout_type); ?>">
                     <?php if ($checkout_type === 'switch'): ?>
                         <input type="hidden" name="existing_purchase_id" value="<?php echo esc_attr($existing_purchase_id); ?>">
                     <?php endif; ?>
                     <?php wp_nonce_field('orunk_process_payment_nonce', 'orunk_payment_nonce'); ?>

                </div> <?php // End Right Column ?>

            </div> <?php // End Grid ?>

        </form> <?php // End Form ?>

         <?php // --- Sticky purchase button for mobile --- ?>
         <div class="sticky-purchase-btn">
            <?php $button_id_sticky = 'complete-purchase-sticky'; ?>
            <button type="submit" form="orunk-payment-form" id="<?php echo esc_attr($button_id_sticky); ?>" class="w-full px-4 py-3 rounded-lg font-semibold text-white primary-btn shadow-md hover:shadow-lg transition-all text-sm relative">
                <span class="button-text-content" id="button-text-sticky"><?php echo esc_html($button_text_right); ?> <?php if ($plan_price > 0): ?><span class="font-bold">$<?php echo esc_html(number_format($plan_price, 2)); ?></span><?php endif; ?></span>
                <span class="button-spinner-content" id="submit-spinner-sticky"><span class="submit-spinner loader"></span></span>
            </button>
        </div>

    </main>

    <?php // Optional Footer Placeholder (can use theme's footer) ?>

    <?php // --- JavaScript Includes --- ?>
    <script type="text/javascript">
        const orunkCheckoutData = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            planPrice: <?php echo esc_js($plan_price); ?>,
            isFreePlan: <?php echo esc_js($plan_price <= 0 ? 'true' : 'false'); ?>,
            processPaymentNonce: '<?php echo wp_create_nonce('orunk_process_payment_nonce'); ?>',
            createIntentNonce: '<?php echo wp_create_nonce('orunk_process_payment_nonce'); ?>', // Re-using same nonce for simplicity
        };
    </script>
    <?php if ($stripe_publishable_key && $plan_price > 0) : ?>
        <script src="https://js.stripe.com/v3/"></script>
    <?php endif; ?>

    <?php // --- MAIN JAVASCRIPT (with fixes) --- ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
             // --- Element Selectors ---
             const form = document.getElementById('orunk-payment-form');
             const submitButtons = [
                 document.getElementById('complete-purchase-left'),
                 document.getElementById('complete-purchase'), // Right side button
                 document.getElementById('complete-purchase-sticky')
             ].filter(btn => btn !== null);

             const paymentMessage = document.getElementById('orunk-payment-message');
             const formErrorDivLeft = document.getElementById('form-error-left');
             const formErrorTextLeft = document.getElementById('form-error-text-left');
             const formErrorDivRight = document.getElementById('form-error');
             const formErrorTextRight = document.getElementById('form-error-text');
             const promoErrorDiv = document.getElementById('promo-error-message');
             const promoSuccessDiv = document.getElementById('promo-success-message');

             const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
             const cardErrorsDiv = document.getElementById('orunk-card-errors');

             // --- Terms Checkbox Elements ---
             const termsCheckbox = document.getElementById('terms'); // Right side
             const termsCheckboxLeft = document.getElementById('terms-left'); // Left side
             const termsErrorDiv = document.getElementById('terms-error');
             const termsErrorDivLeft = document.getElementById('terms-left-error');
             const termsContainerRight = document.getElementById('terms-right-container');
             const termsContainerLeft = document.getElementById('terms-left-container');


             // --- Stripe Variables ---
             let stripe = null;
             let cardElement = null;
             const stripePublishableKey = '<?php echo esc_js($stripe_publishable_key ?? ''); ?>';
             const needsStripe = !orunkCheckoutData.isFreePlan && stripePublishableKey && document.getElementById('orunk-card-element');

             // --- State Variables ---
             let paymentIntentClientSecret = null;
             let paymentIntentId = null;
             let purchaseId = null;

             // --- Initialize Stripe ---
             if (needsStripe) {
                 try {
                     stripe = Stripe(stripePublishableKey);
                     const elements = stripe.elements();
                     const style = { /* Base & Invalid styles */
                        base: { iconColor: '#6b7280', color: '#1f2937', fontWeight: '500', fontFamily: 'Inter, sans-serif', fontSize: '14px', fontSmoothing: 'antialiased', '::placeholder': { color: '#9ca3af' } },
                        invalid: { iconColor: '#ef4444', color: '#ef4444' }
                     };
                     cardElement = elements.create('card', { style: style });
                     cardElement.mount('#orunk-card-element');

                     if (cardErrorsDiv) {
                         cardElement.on('change', function(event) {
                             cardErrorsDiv.textContent = event.error ? event.error.message : '';
                             cardErrorsDiv.style.display = event.error ? 'block' : 'none';
                             const cardElementWrapper = document.getElementById('orunk-card-element');
                             if (cardElementWrapper) {
                                 cardElementWrapper.classList.toggle('input-error', !!event.error);
                                 // Ensure error div visibility matches error state
                                 cardErrorsDiv.style.display = event.error ? 'block' : 'none';
                                 if(event.error) cardErrorsDiv.setAttribute('role', 'alert');
                                 else cardErrorsDiv.removeAttribute('role');
                             }
                         });
                     }
                     // console.log('Stripe initialized.'); // Keep commented unless debugging Stripe init
                 } catch(e) {
                     console.error("Stripe initialization failed: ", e);
                     showPaymentMessage('Payment gateway initialization error.', true);
                     submitButtons.forEach(btn => btn.disabled = true);
                 }
             } else if (!orunkCheckoutData.isFreePlan && !document.querySelector('input[name="payment_method"]')) {
                 showPaymentMessage('No payment methods available for this plan.', true);
                 submitButtons.forEach(btn => btn.disabled = true);
             }

             // --- Event Listeners ---
             if (form) {
                 form.addEventListener('submit', handleFormSubmit);
             } else {
                 console.error("Checkout form not found!");
             }

             paymentMethodRadios.forEach(radio => {
                 radio.addEventListener('change', function() {
                     document.querySelectorAll('.payment-details').forEach(detail => detail.classList.add('hidden'));
                     const detailToShow = document.getElementById('payment_details_'.concat(this.value));
                     if(detailToShow) { detailToShow.classList.remove('hidden'); }
                 });
                 // Trigger change on initial load for the checked one
                 if(radio.checked) {
                      radio.dispatchEvent(new Event('change', { bubbles: true }));
                 }
             });

             const applyPromoBtn = document.getElementById('apply-promo-code');
             if(applyPromoBtn) {
                 applyPromoBtn.addEventListener('click', handleApplyPromoCode);
             }

             // --- Terms Checkbox Synchronization Logic ---
             function syncCheckboxes(sourceCheckbox) {
                 // ... (Function unchanged) ...
                 const isChecked = sourceCheckbox.checked;
                 if (termsCheckbox && termsCheckbox !== sourceCheckbox) termsCheckbox.checked = isChecked;
                 if (termsCheckboxLeft && termsCheckboxLeft !== sourceCheckbox) termsCheckboxLeft.checked = isChecked;
                 const isValid = isChecked;
                 const message = 'You must agree to the terms';
                 const termsErrorSpan = termsErrorDiv ? termsErrorDiv.querySelector('span') : null;
                 const termsErrorSpanLeft = termsErrorDivLeft ? termsErrorDivLeft.querySelector('span') : null;
                 [termsCheckbox, termsCheckboxLeft].forEach((cb, index) => {
                     const container = index === 0 ? termsContainerRight : termsContainerLeft;
                     const errorDiv = index === 0 ? termsErrorDiv : termsErrorDivLeft;
                     const errorSpan = index === 0 ? termsErrorSpan : termsErrorSpanLeft;
                     if (cb) {
                         cb.setAttribute('aria-invalid', !isValid);
                         if (!isValid) { cb.setAttribute('aria-describedby', errorDiv ? errorDiv.id : ''); }
                         else { cb.removeAttribute('aria-describedby'); }
                     }
                     if(container) container.classList.toggle('input-error', !isValid);
                     if (errorDiv && errorSpan) {
                         if (!isValid) { errorSpan.textContent = message; errorDiv.style.display = 'block'; errorDiv.setAttribute('role', 'alert'); }
                         else { errorSpan.textContent = ''; errorDiv.style.display = 'none'; errorDiv.removeAttribute('role'); }
                     }
                 });
             }

             // Add event listeners for synchronization
             if (termsCheckbox) {
                 termsCheckbox.addEventListener('change', () => syncCheckboxes(termsCheckbox));
             }
             if (termsCheckboxLeft) {
                 termsCheckboxLeft.addEventListener('change', () => syncCheckboxes(termsCheckboxLeft));
             }

             // --- Helper Functions ---
             function setButtonProcessing(processing) {
                // ... (Function unchanged) ...
                 submitButtons.forEach(button => {
                     if (button) {
                         button.disabled = processing;
                         button.classList.toggle('is-processing', processing);
                     }
                 });
             }

             function showPaymentMessage(message, isError = true, clearAfter = 5000) {
                 // ... (Function unchanged) ...
                 if (!paymentMessage) return;
                 paymentMessage.textContent = escapeHTML(message);
                 const baseClasses = 'p-3 mb-4 text-sm border rounded-lg';
                 const errorClasses = 'text-red-700 bg-red-100 border-red-300';
                 const successClasses = 'text-green-700 bg-green-100 border-green-300';
                 paymentMessage.className = `${baseClasses} ${isError ? errorClasses : successClasses}`;
                 paymentMessage.classList.remove('hidden');
                 if (clearAfter > 0) { /* Set timeout to clear */
                    setTimeout(() => { if (paymentMessage.textContent === message) { paymentMessage.textContent = ''; paymentMessage.classList.add('hidden'); paymentMessage.className = 'hidden p-3 mb-4 text-sm border rounded-lg'; } }, clearAfter);
                 }
             }

             function escapeHTML(str) {
                // ... (Function unchanged) ...
                 const div = document.createElement('div'); div.textContent = str; return div.innerHTML;
             }

             // --- Validation Functions ---
             function validateForm() {
                 // ... (Function largely unchanged, ensure terms check uses syncCheckboxes) ...
                let isValid = true;
                resetValidationErrors();
                // console.log('--- Starting Checkout Validation ---');

                // Validate Billing Fields
                const requiredBillingFields = form.querySelectorAll('#full-billing-form [required]');
                // console.log(`Found ${requiredBillingFields.length} required billing fields.`);
                requiredBillingFields.forEach(field => {
                    const value = field.value.trim();
                    let fieldLabel = field.labels?.[0]?.textContent?.replace('*','').trim() || field.id || 'field';
                    if (field.tagName === 'SELECT') {
                         fieldLabel = 'country';
                         if (value === '') {
                            // console.log(`Validation FAIL: Billing field "${field.id}" is empty.`);
                            showValidationError(field.id, `Please select your ${fieldLabel}`); isValid = false;
                         }
                     } else if (!value) {
                         // console.log(`Validation FAIL: Billing field "${field.id}" is empty.`);
                         showValidationError(field.id, `Please enter your ${fieldLabel}`); isValid = false;
                     }
                });

                const emailField = document.getElementById('billing_email');
                if (emailField && emailField.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value.trim())) {
                    // console.log('Validation FAIL: Email format is invalid.');
                    showValidationError('billing_email', 'Please enter a valid email address'); isValid = false;
                }

                // Validate Terms Checkbox (uses synced state)
                 const termsChecked = termsCheckbox ? termsCheckbox.checked : false;
                // console.log(`Terms checkbox checked: ${termsChecked}`);
                if (termsCheckbox && !termsChecked) {
                    // console.log('Validation FAIL: Terms not checked.');
                    syncCheckboxes(termsCheckbox); // This shows the error messages
                    isValid = false;
                } else if (termsCheckbox && termsChecked) {
                     syncCheckboxes(termsCheckbox); // Ensures errors are cleared if corrected
                }

                // Validate Stripe Card Element if visible
                const selectedGatewayId = document.querySelector('input[name="payment_method"]:checked')?.value;
                // console.log(`Selected Gateway for validation: ${selectedGatewayId}`);
                const stripeDetailsDiv = document.getElementById('payment_details_stripe');
                if (selectedGatewayId === 'stripe' && needsStripe && cardElement && stripeDetailsDiv && !stripeDetailsDiv.classList.contains('hidden')) {
                     // console.log('Running Stripe element validation...');
                      if (cardErrorsDiv && cardErrorsDiv.textContent.trim()) {
                         // console.log('Validation FAIL: Stripe card element has errors.');
                         isValid = false;
                         const cardElementWrapper = document.getElementById('orunk-card-element');
                         if (cardElementWrapper) cardElementWrapper.classList.add('input-error');
                         cardErrorsDiv.style.display = 'block';
                         cardErrorsDiv.setAttribute('role', 'alert'); // Add role on error
                     }
                } else {
                    // console.log('Skipping Stripe element validation.');
                }


                // Show general error message if form is invalid
                if (!isValid) {
                    // console.log('Form is INVALID. Stopping submission.');
                    const errorText = 'Please fix the errors marked below.';
                     if (formErrorTextLeft) { formErrorTextLeft.textContent = errorText; formErrorDivLeft?.classList.remove('hidden'); }
                     if (formErrorTextRight) { formErrorTextRight.textContent = errorText; formErrorDivRight?.classList.remove('hidden'); }
                     // Optionally show global message too, or rely on field errors
                     // showPaymentMessage(errorText, true);
                } else {
                    // console.log('Form is VALID.');
                }

                return isValid;
             }

            function showValidationError(fieldId, message) {
                 // ... (Function unchanged) ...
                 const field = document.getElementById(fieldId);
                 const errorElementContainer = document.getElementById(`${fieldId}-error`);
                 const errorElementSpan = errorElementContainer ? errorElementContainer.querySelector('span') : null;

                 if (field) {
                     field.classList.add('input-error');
                     field.setAttribute('aria-invalid', 'true');
                     if (errorElementContainer) { field.setAttribute('aria-describedby', `${fieldId}-error`); } // Use the container ID
                 }
                 if (errorElementContainer && errorElementSpan) {
                     errorElementSpan.textContent = message;
                     errorElementContainer.style.display = 'block'; // Make sure it's visible
                     errorElementContainer.setAttribute('role', 'alert');
                 }
            }

             function resetValidationErrors() {
                 // ... (Function unchanged) ...
                if (formErrorDivLeft) formErrorDivLeft.classList.add('hidden'); if (formErrorTextLeft) formErrorTextLeft.textContent = '';
                if (formErrorDivRight) formErrorDivRight.classList.add('hidden'); if (formErrorTextRight) formErrorTextRight.textContent = '';
                if (paymentMessage && !paymentMessage.classList.contains('hidden') && paymentMessage.classList.contains('text-red-700')) { showPaymentMessage('', false, 1); }
                form.querySelectorAll('.input-field').forEach(field => { field.classList.remove('input-error'); field.removeAttribute('aria-invalid'); field.removeAttribute('aria-describedby'); const errorContainer = document.getElementById(`${field.id}-error`); const errorSpan = errorContainer ? errorContainer.querySelector('span') : null; if (errorContainer && errorSpan) { errorSpan.textContent = ''; errorContainer.style.display = 'none'; errorContainer.removeAttribute('role'); } });
                const cardElementWrapper = document.getElementById('orunk-card-element'); if (cardElementWrapper) { cardElementWrapper.classList.remove('input-error'); } if (cardErrorsDiv) { cardErrorsDiv.textContent = ''; cardErrorsDiv.style.display = 'none'; cardErrorsDiv.removeAttribute('role'); }
                if(termsCheckbox) { const tempChecked = termsCheckbox.checked; termsCheckbox.checked = true; syncCheckboxes(termsCheckbox); termsCheckbox.checked = tempChecked; }
                const promoInput = document.getElementById('promo-code'); const promoErrorSpan = promoErrorDiv ? promoErrorDiv.querySelector('span') : null; if(promoInput) promoInput.classList.remove('input-error'); if(promoErrorDiv) promoErrorDiv.classList.add('hidden'); if(promoErrorSpan) promoErrorSpan.textContent = ''; if(promoSuccessDiv) promoSuccessDiv.classList.add('hidden');
             }

            // --- processServerConfirmation (AJAX Helper) ---
             async function processServerConfirmation(ajaxData) {
                 // ... (Function unchanged) ...
                  try {
                    showPaymentMessage('Processing your order...', false, 0);
                    const response = await fetch(orunkCheckoutData.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(ajaxData) });
                    const data = await response.json();
                    if (!response.ok || !data.success) { throw new Error(data.data?.message || `Server processing failed (Status: ${response.status})`); }
                    if (data.data?.redirect_url) { showPaymentMessage('Processing successful! Redirecting...', false, 0); window.location.href = data.data.redirect_url; }
                    else { showPaymentMessage('Order placed! Redirecting to dashboard...', false, 0); console.warn("No redirect URL received.", data); setTimeout(() => { window.location.href = '<?php echo esc_url(home_url('/orunk-dashboard/')); ?>'; }, 1500); }
                } catch (error) { console.error('Server Confirmation Error:', error); showPaymentMessage(error.message || 'An unexpected server error occurred during final processing.', true); setButtonProcessing(false); }
             }

             // --- handleFormSubmit (Main Handler - Corrected) ---
             async function handleFormSubmit(event) {
                event.preventDefault();
                // --- FIX: Declare variables at the top ---
                let selectedGatewayId;
                let formData;
                // --- End Fix ---

                setButtonProcessing(true);
                showPaymentMessage('', false);
                resetValidationErrors();

                if (!validateForm()) {
                    showPaymentMessage('Please correct the errors marked below.', true);
                    setButtonProcessing(false);
                    const firstErrorField = form.querySelector('.input-error, .form-checkbox[aria-invalid="true"]');
                    if(firstErrorField) {
                        if(firstErrorField.id === 'orunk-card-element' && cardElement) {
                            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                         } else if (firstErrorField.type === 'checkbox') {
                              firstErrorField.closest('.terms-checkbox')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                              // Optionally focus label or container
                              firstErrorField.closest('.terms-checkbox')?.querySelector('label')?.focus();
                         }
                        else {
                            firstErrorField.focus();
                            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    } else {
                        paymentMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }
                // --- Validation Passed ---
                // console.log('--- Validation PASSED. Proceeding in handleFormSubmit ---');

                // --- FIX: Assign to existing variables (remove let/const) ---
                selectedGatewayId = orunkCheckoutData.isFreePlan ? 'free_checkout' : document.querySelector('input[name="payment_method"]:checked')?.value;
                formData = new FormData(form); // Use the form element reference
                // --- End Fix ---

                // console.log(`Selected Gateway ID: ${selectedGatewayId}`);

                if (!orunkCheckoutData.isFreePlan && !selectedGatewayId) {
                    // console.log('Error condition: No payment gateway selected (but validation passed somehow?)');
                    showPaymentMessage('Please select a payment method.', true); // Show user message
                    setButtonProcessing(false);
                    document.querySelector('input[name="payment_method"]')?.focus(); // Focus first payment method
                    return;
                }

                // --- Process Payment ---
                if (selectedGatewayId === 'stripe') {
                    // console.log('Entering Stripe processing block...');
                       if (!stripe || !cardElement) { showPaymentMessage('Stripe is not ready. Please refresh.', true); setButtonProcessing(false); return; }
                       try {
                            // 1. Create Payment Intent
                            showPaymentMessage('Initializing secure payment...', false, 0);
                            const intentResponse = await fetch(orunkCheckoutData.ajaxUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    action: 'orunk_create_payment_intent',
                                    nonce: orunkCheckoutData.createIntentNonce, // Changed from processPaymentNonce
                                    plan_id: formData.get('plan_id'),
                                    checkout_type: formData.get('checkout_type'),
                                    existing_purchase_id: formData.get('existing_purchase_id') || ''
                                })
                            });
                            const intentData = await intentResponse.json();
                            if (!intentResponse.ok || !intentData.success || !intentData.data.client_secret || !intentData.data.purchase_id) { throw new Error(intentData.data?.message || 'Could not initialize payment.'); }
                            paymentIntentClientSecret = intentData.data.client_secret;
                            paymentIntentId = intentData.data.payment_intent_id;
                            purchaseId = intentData.data.purchase_id;
                            // console.log('PI Created. Purchase ID:', purchaseId, 'PI ID:', paymentIntentId);

                            // 2. Confirm Card Payment
                            showPaymentMessage('Processing payment...', false, 0);
                            const billingDetails = {
                                name: `${formData.get('billing_first_name') || ''} ${formData.get('billing_last_name') || ''}`.trim(),
                                email: formData.get('billing_email') || '',
                                phone: formData.get('billing_phone') || '',
                                address: {
                                    line1: formData.get('billing_address_1') || '',
                                    line2: formData.get('billing_address_2') || '',
                                    city: formData.get('billing_city') || '',
                                    state: formData.get('billing_state') || '',
                                    postal_code: formData.get('billing_postcode') || '',
                                    country: formData.get('billing_country') || ''
                                }
                            };
                            // Clean empty billing address fields
                            for (const key in billingDetails.address) { if (!billingDetails.address[key]) { delete billingDetails.address[key]; }};
                            if (Object.keys(billingDetails.address).length === 0) { delete billingDetails.address; }

                            const { paymentIntent, error: stripeError } = await stripe.confirmCardPayment(
                                paymentIntentClientSecret,
                                { payment_method: { card: cardElement, billing_details: billingDetails } },
                                { handleActions: true } // Let Stripe handle 3DS etc.
                            );

                            if (stripeError) { throw new Error(stripeError.message || 'Payment failed.'); }

                            // 3. Confirm with Server
                            if (paymentIntent && (paymentIntent.status === 'succeeded' || paymentIntent.status === 'processing')) {
                                // console.log(`Stripe Status: ${paymentIntent.status}. Confirming...`);
                                showPaymentMessage('Payment processed! Finalizing...', false, 0);
                                const confirmAjaxData = {
                                     action: 'orunk_process_payment',
                                     nonce: orunkCheckoutData.processPaymentNonce,
                                     plan_id: formData.get('plan_id'),
                                     checkout_type: formData.get('checkout_type'),
                                     existing_purchase_id: formData.get('existing_purchase_id') || '',
                                     payment_method: 'stripe',
                                     purchase_id: purchaseId, // Use ID from intent creation
                                     payment_intent_id: paymentIntent.id
                                };
                                await processServerConfirmation(confirmAjaxData);
                            } else {
                                throw new Error('Unexpected payment status: ' + (paymentIntent ? paymentIntent.status : 'Unknown'));
                            }
                       } catch (error) {
                            console.error('Stripe Checkout Error:', error);
                            let displayError = error.message || 'An error occurred during payment.';
                            // Display error near card element AND general message areas
                             if (cardErrorsDiv && error.message) {
                                cardErrorsDiv.textContent = displayError;
                                cardErrorsDiv.style.display = 'block';
                                document.getElementById('orunk-card-element')?.classList.add('input-error');
                             }
                             if (formErrorTextLeft) { formErrorTextLeft.textContent = displayError; formErrorDivLeft?.classList.remove('hidden'); }
                             if (formErrorTextRight) { formErrorTextRight.textContent = displayError; formErrorDivRight?.classList.remove('hidden'); }
                             showPaymentMessage(displayError, true);
                            setButtonProcessing(false);
                       }
                } else if (selectedGatewayId === 'bank' || selectedGatewayId === 'free_checkout') {
                    // console.log(`Entering Bank/Free processing block for gateway: ${selectedGatewayId}`);
                    const ajaxData = {
                        action: 'orunk_process_payment',
                        nonce: orunkCheckoutData.processPaymentNonce,
                        plan_id: formData.get('plan_id'),
                        checkout_type: formData.get('checkout_type'),
                        existing_purchase_id: formData.get('existing_purchase_id') || '',
                        payment_method: selectedGatewayId,
                        // Include billing data for server-side storage if needed
                        billing_first_name: formData.get('billing_first_name') || '',
                        billing_last_name: formData.get('billing_last_name') || '',
                        billing_email: formData.get('billing_email') || '',
                        billing_address_1: formData.get('billing_address_1') || '',
                        billing_address_2: formData.get('billing_address_2') || '',
                        billing_city: formData.get('billing_city') || '',
                        billing_state: formData.get('billing_state') || '',
                        billing_postcode: formData.get('billing_postcode') || '',
                        billing_country: formData.get('billing_country') || '',
                        billing_phone: formData.get('billing_phone') || ''
                    };
                    // console.log('Prepared ajaxData for Bank/Free:', ajaxData);
                    await processServerConfirmation(ajaxData);
                    // console.log('Finished processServerConfirmation for Bank/Free.');
                }
                // --- START: PayPal Block ---
                else if (selectedGatewayId === 'paypal') {
                    // console.log('Entering PayPal processing block...');
                    const ajaxDataPayPal = {
                         action: 'orunk_process_payment',
                         nonce: orunkCheckoutData.processPaymentNonce,
                         plan_id: formData.get('plan_id'),
                         checkout_type: formData.get('checkout_type'),
                         existing_purchase_id: formData.get('existing_purchase_id') || '',
                         payment_method: 'paypal',
                         // Include billing data
                         billing_first_name: formData.get('billing_first_name') || '',
                         billing_last_name: formData.get('billing_last_name') || '',
                         billing_email: formData.get('billing_email') || '',
                         billing_address_1: formData.get('billing_address_1') || '',
                         billing_address_2: formData.get('billing_address_2') || '',
                         billing_city: formData.get('billing_city') || '',
                         billing_state: formData.get('billing_state') || '',
                         billing_postcode: formData.get('billing_postcode') || '',
                         billing_country: formData.get('billing_country') || '',
                         billing_phone: formData.get('billing_phone') || ''
                    };
                    // console.log('Prepared ajaxData for PayPal (BEFORE sending):', ajaxDataPayPal);

                    // --- FIX: Call processServerConfirmation for PayPal ---
                    await processServerConfirmation(ajaxDataPayPal);
                    // console.log('Finished processServerConfirmation for PayPal.');

                }
                // --- END: PayPal Block ---
                else {
                    // console.log(`Error condition: Unexpected selectedGatewayId: ${selectedGatewayId}`);
                    showPaymentMessage('Invalid payment method selected (JS Check).', true);
                    setButtonProcessing(false);
                }
             } // End handleFormSubmit

             // --- Promo Code Handler ---
             function handleApplyPromoCode() {
                 // ... (Function unchanged) ...
                const promoInput = document.getElementById('promo-code'); const promoCode = promoInput.value.trim(); const promoErrorSpan = promoErrorDiv ? promoErrorDiv.querySelector('span') : null; const promoSuccessSpan = promoSuccessDiv ? promoSuccessDiv.querySelector('span') : null;
                if(promoErrorDiv) promoErrorDiv.classList.add('hidden'); if(promoErrorSpan) promoErrorSpan.textContent = ''; if(promoSuccessDiv) promoSuccessDiv.classList.add('hidden'); if(promoSuccessSpan) promoSuccessSpan.textContent = ''; promoInput.classList.remove('input-error');
                if (!promoCode) { promoInput.classList.add('input-error'); if(promoErrorSpan) promoErrorSpan.textContent = 'Please enter a promo code.'; if(promoErrorDiv) promoErrorDiv.classList.remove('hidden'); return; }
                // console.log(`Applying promo code: ${promoCode}`); // Keep commented unless debugging promo
                applyPromoBtn.disabled = true; applyPromoBtn.textContent = 'Applying...';
                // --- Placeholder Promo Logic ---
                setTimeout(() => { applyPromoBtn.disabled = false; applyPromoBtn.textContent = 'Apply';
                    if (promoCode.toUpperCase() === 'SAVE10') { if(promoSuccessSpan) promoSuccessSpan.textContent = 'Promo code "SAVE10" applied! 10% off.'; if(promoSuccessDiv) promoSuccessDiv.classList.remove('hidden'); /* TODO: Update price display and potentially pass code to backend */ }
                    else { promoInput.classList.add('input-error'); if(promoErrorSpan) promoErrorSpan.textContent = 'Invalid or expired promo code.'; if(promoErrorDiv) promoErrorDiv.classList.remove('hidden'); }
                }, 1000);
                // --- End Placeholder ---
             }

        }); // End DOMContentLoaded
    </script>

    <?php wp_footer(); ?>

</body>
</html>