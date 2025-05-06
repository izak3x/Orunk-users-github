<?php
/**
 * Template for displaying the BIN Lookup API documentation and demo page.
 * This file should be placed in your theme or plugin's template directory.
 *
 * v2.2 - Final Complete Code: JS uses proxy, Docs use direct, API Plans included.
 * v1.9 - Replaced stat cards with compact stats line in Try It section.
 * v1.8 - Applied compact styling to Try It button and Stat Cards.
 * v1.7 - Updated API Documentation section for clarity and generality.
 */

// Define allowed methods (fetch from bin-api-plugin settings if possible, else default)
if (class_exists('Custom_BIN_API')) {
    // Assumes GET is primary method; adjust if Custom_BIN_API provides dynamic list
    $bin_api_temp_instance = new Custom_BIN_API();
    $allowed_methods = method_exists($bin_api_temp_instance, 'get_allowed_methods') ? $bin_api_temp_instance->get_allowed_methods() : ['GET'];
} else {
    $allowed_methods = ['GET']; // Default if other plugin inactive
}

// Define a placeholder for the API Key - Used ONLY in documentation examples
$placeholder_api_key = 'YOUR_API_KEY_HERE'; // Use a placeholder

// API Base URL for WP REST API (used by both proxy JS call and docs examples)
$api_base_url = function_exists('site_url') ? esc_url(site_url('/wp-json')) : 'YOUR_API_BASE_URL'; // Example placeholder

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIN Lookup API | Orunk Developer Tools</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script>
        // Tailwind Config (Original)
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'together-blue': '#2563eb',
                        'together-indigo': '#4f46e5',
                        'together-purple': '#7c3aed',
                        'together-dark': '#0f172a',
                        'together-light': '#f8fafc',
                        'together-green': '#10b981',
                        'together-orange': '#f59e0b',
                    },
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 5s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'fade-in': 'fadeIn 1s ease-in',
                        'gradient-x': 'gradientX 15s ease infinite',
                        'gradient-y': 'gradientY 15s ease infinite',
                        'gradient-xy': 'gradientXY 15s ease infinite',
                    }
                }
            }
        }
    </script>
    <style>
        /* --- All Original CSS styles --- */
        :root {
            --together-primary: #2563eb;
            --together-secondary: #7c3aed;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #0f172a;
            background-color: #f8fafc;
            overflow-x: hidden;
            margin: 0;
            scroll-behavior: smooth;
        }

        .hm-gradient-text {
            background: linear-gradient(90deg, var(--together-primary) 0%, var(--together-secondary) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hm-hero-gradient {
            background: linear-gradient(135deg, rgba(248,250,252,1) 0%, rgba(241,245,249,1) 100%);
            position: relative;
            overflow: hidden;
        }

        .hm-hero-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.03) 0%, transparent 70%);
            z-index: 0;
        }

        .hm-code-card {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226,232,240,0.5);
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .hm-code-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 25px 50px -10px rgba(0,0,0,0.15);
        }

        .hm-code-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2563eb 0%, #7c3aed 100%);
        }

        .hm-code-content {
            max-height: 400px;
            overflow: hidden;
            padding: 1rem 1.5rem;
        }

        .hm-code-header {
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid rgba(226,232,240,0.5);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background-color: rgba(248, 250, 252, 0.8);
        }

        .hm-code-block {
            background: #1e293b; /* Dark background for code */
            border-radius: 0.5rem;
            padding: 1rem;
            overflow-x: auto;
            color: #e2e8f0; /* Light text */
            font-family: 'SF Mono', 'Menlo', 'Consolas', monospace; /* Monospace font */
            font-size: 0.875rem; /* 14px */
            line-height: 1.6;
        }

        .hm-code-block pre {
            margin: 0;
            padding: 0;
        }

        .hm-code-block code {
            display: block;
            white-space: pre;
        }

        /* Syntax Highlighting Styles (Simple Example) */
        .hm-code-block .token.punctuation { color: #cbd5e1; } /* slate-300 */
        .hm-code-block .token.keyword { color: #fda4af; } /* rose-300 */
        .hm-code-block .token.operator { color: #cbd5e1; } /* slate-300 */
        .hm-code-block .token.string { color: #a5b4fc; } /* indigo-300 */
        .hm-code-block .token.number { color: #fcd34d; } /* amber-300 */
        .hm-code-block .token.comment { color: #64748b; font-style: italic; } /* slate-500 */
        .hm-code-block .token.function { color: #818cf8; } /* indigo-400 */
        .hm-code-block .token.property { color: #93c5fd; } /* blue-300 */
        .hm-code-block .token.url { color: #67e8f9; text-decoration: underline;} /* cyan-300 */
        .hm-code-block .token.method { color: #6ee7b7; } /* emerald-300 */
        .hm-code-block .token.parameter { color: #f0abfc; } /* fuchsia-300 */

        /* --- UPDATED Button Styles --- */
        .hm-button { /* Base button class */
            display: block;
            width: 100%;
            text-align: center;
            padding: 0.65rem 1rem; /* Slightly reduced vertical padding */
            border-radius: 0.5rem; /* Slightly rounder corners */
            font-weight: 500; /* font-medium */
            transition: all 0.15s ease-in-out;
            border: 1px solid transparent;
        }
        /* Adjust padding specifically for the hero lookup button */
        #hero-bin-input + button { /* Target the button directly next to the input */
             padding: 0.75rem 1.5rem; /* Keep lookup button padding distinct */
             border-radius: 0 0.5rem 0.5rem 0; /* Match input rounding on left */
        }
        .hm-primary-button {
            background: linear-gradient(to right, #2563eb, #7c3aed);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.3);
        }
         .hm-primary-button.disabled, .hm-primary-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: linear-gradient(to right, #9ca3af, #a78bfa);
            box-shadow: none;
        }
        .hm-primary-button:hover:not(:disabled) {
            background: linear-gradient(to right, #1e40af, #6d28d9);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.4);
        }
         .hm-active-button { /* Style for the "Active" button */
            background-color: #eef2ff; /* indigo-50 */
            color: #4f46e5; /* indigo-700 */
            border-color: #c7d2fe; /* indigo-200 */
            cursor: default;
            box-shadow: none;
            font-weight: 600; /* Slightly bolder */
        }
        .hm-active-button:hover { transform: none; background-color: #eef2ff; } /* No hover effect */

        .hm-secondary-button { /* Used for Downgrade */
            background: #ffffff;
            color: #6b7280; /* gray-500 */
            border-color: #d1d5db; /* gray-300 */
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .hm-secondary-button:hover:not(:disabled) {
            background: #f9fafb; /* gray-50 */
            color: #4b5563; /* gray-600 */
             border-color: #9ca3af; /* gray-400 */
            transform: translateY(-1px);
            box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.06);
        }
        .hm-secondary-button.disabled, .hm-secondary-button:disabled {
             opacity: 0.6;
            cursor: not-allowed;
             background: #f3f4f6; /* gray-100 */
             color: #9ca3af; /* gray-400 */
             box-shadow: none;
             border-color: #e5e7eb; /* gray-200 */
        }
        /* End UPDATED Button Styles */

        .hm-api-param {
            border-left: 3px solid #2563eb;
            padding-left: 1rem;
            margin-bottom: 1.5rem;
            transition: all 0.2s ease;
        }

        .hm-api-param:hover {
            background-color: rgba(241, 245, 249, 0.5);
        }

        .hm-response-field {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 0.75rem;
            border-radius: 0.375rem;
            font-family: 'SF Mono', 'Menlo', monospace;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .hm-tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }

        .hm-tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            font-weight: 500;
            transition: all 0.2s ease;
            color: #64748b;
        }

        .hm-tab:hover {
            color: #2563eb;
            background-color: rgba(241, 245, 249, 0.5);
        }

        .hm-tab.active {
            border-bottom-color: #2563eb;
            color: #2563eb;
            background-color: white;
        }

        .hm-tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .hm-tab-content.active {
            display: block;
        }

        .hm-try-it-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
            z-index: 10;
        }
        /* REMOVED HOVER EFFECT for try-it-card */
        .hm-try-it-card:hover {}

        .hm-input-glow:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.3);
            border-color: #2563eb;
        }

        /* REMOVED Stat Card Styles (no longer used here) */

        .hm-pricing-card {
            transition: all 0.3s ease;
            height: 100%;
            display: flex; /* Added */
            flex-direction: column; /* Added */
            position: relative; /* Needed for absolute positioned badges */
        }
        .hm-pricing-card-content {
             flex-grow: 1; /* Added */
        }
        .hm-pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); /* Added subtle shadow */
        }
        .hm-pricing-card .hm-button-wrapper {
            margin-top: auto; /* Pushes the button to the bottom */
        }

        /* Badge Styles */
        .hm-badge-container {
            position: absolute;
            top: 0.75rem; /* p-3 */
            right: 0.75rem; /* p-3 */
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.25rem; /* space-y-1 */
            z-index: 5; /* Ensure badges are above content */
        }
        .hm-badge {
            display: inline-block;
            padding: 0.2em 0.6em;
            font-size: 0.65rem; /* text-xs */
            font-weight: 600; /* font-semibold */
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 9999px; /* rounded-full */
        }
        .hm-badge-current { background-color: #e0e7ff; color: #3730a3; } /* indigo-200 / indigo-800 */
        .hm-badge-popular { background-color: #fecaca; color: #991b1b; } /* red-200 / red-800 */
        .hm-badge-value { background-color: #d1fae5; color: #065f46; } /* green-200 / green-800 */


        .hm-testimonial-card {
            transition: all 0.3s ease;
        }

        .hm-testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .hm-tabs {
                overflow-x: auto;
                white-space: nowrap;
                scrollbar-width: none;
            }

            .hm-tabs::-webkit-scrollbar {
                display: none;
            }
            /* REMOVED mobile stat card styles */
        }

        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .hm-gradient-bg {
            background: linear-gradient(-45deg, #2563eb, #4f46e5, #7c3aed);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
            position: relative;
            overflow: hidden;
        }

        .hm-gradient-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        }

        .hm-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .hm-floating {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        /* Removed .hm-badge keyframe pulse */

        .hm-tooltip {
            position: relative;
        }

        .hm-tooltip:hover .hm-tooltip-text {
            visibility: visible;
            opacity: 1;
            transform: translateY(0);
        }

        .hm-tooltip-text {
            visibility: hidden;
            opacity: 0;
            position: absolute;
            z-index: 1;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(10px);
            background-color: #1e293b;
            color: white;
            text-align: center;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            white-space: nowrap;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .hm-tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #1e293b transparent transparent transparent;
        }

        .hm-scroll-indicator {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-20px);}
            60% {transform: translateY(-10px);}
        }

        /* --- UPDATED Input Field --- */
        .hm-form-input {
             transition: all 0.2s ease;
             padding: 0.75rem 1rem; /* Ensure consistent padding with button */
             border-radius: 0.5rem 0 0 0.5rem; /* Match button rounding */
             border: 1px solid #cbd5e1; /* Add default border */
             border-right-width: 0; /* Remove right border */
        }

        .hm-form-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
            z-index: 1; /* Ensure focus ring overlaps button */
            position: relative; /* Needed for z-index */
        }
        /* --- End Input Field --- */

        .hm-nav-link {
            position: relative;
        }

        .hm-nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: #2563eb;
            transition: width 0.3s ease;
        }

        .hm-nav-link:hover::after {
            width: 100%;
        }

        .hm-nav-link.active::after {
            width: 100%;
        }

    </style>
</head>
<body class="font-inter antialiased text-together-dark bg-together-light">
    <main class="pt-0">
        <section class="hm-hero-gradient relative overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-20 relative z-10">
                <div class="flex flex-col lg:flex-row items-center gap-8">
                    <div class="w-full lg:w-1/2">
                        <div class="hm-try-it-card p-6 mb-8">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold flex items-center">
                                    <i class="fas fa-search mr-2 text-together-blue"></i>
                                    Try the BIN Lookup API
                                </h3>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                     <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-green-400" fill="currentColor" viewBox="0 0 8 8">
                                        <circle cx="4" cy="4" r="3" />
                                     </svg>
                                    API Online
                                </span>
                            </div>
                            <div class="mb-4">
                                <label for="hero-bin-input" class="block text-sm font-medium text-slate-700 mb-1">Enter a BIN (6-8 digits)</label>
                                <div class="flex items-stretch">
                                    <input type="text" id="hero-bin-input" placeholder="424242"
                                           class="flex-1 focus:ring-together-blue focus:border-together-blue hm-input-glow hm-form-input">
                                    <button onclick="lookupBin('hero')" class="font-medium hm-primary-button flex items-center">
                                        <i class="fas fa-search mr-2"></i> Lookup
                                    </button>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Try: 424242 (VISA), 555555 (Mastercard), 378282 (Amex)</p>
                            </div>

                             <div id="hero-captcha-widget" class="my-4" style="transform:scale(0.9); transform-origin:0 0;"></div>

                            <div id="hero-api-response" class="hidden">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-medium text-slate-600">API Response (from Proxy)</h4>
                                    <div class="flex space-x-2">
                                        <button onclick="copyResponse('hero')" class="text-xs text-slate-500 hover:text-together-blue flex items-center">
                                            <i class="fas fa-copy mr-1"></i> Copy
                                        </button>
                                        <button onclick="clearResponse('hero')" class="text-xs text-slate-500 hover:text-together-blue flex items-center">
                                            <i class="fas fa-times mr-1"></i> Clear
                                        </button>
                                    </div>
                                </div>
                                <div class="hm-code-block rounded-lg">
                                    <pre id="hero-response-content" class="text-xs font-mono max-h-60 overflow-auto"></pre>
                                </div>
                            </div>

                            <div id="hero-loading" class="hidden text-center py-6">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-together-blue mx-auto mb-3"></div>
                                <p class="text-slate-700 text-sm">Looking up BIN...</p>
                            </div>

                            <div id="hero-error-message" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded text-sm mb-4"></div>

                           <div class="mt-4 pt-3 border-t border-slate-100 flex justify-around items-center text-xs text-slate-600 space-x-2 px-2">
                               <span class="flex items-center" title="BINs in Database">
                                   <i class="fas fa-database mr-1 text-slate-400"></i> 500K+ BINs
                               </span>
                               <span class="text-slate-300">|</span>
                               <span class="flex items-center" title="Countries Covered">
                                   <i class="fas fa-globe-americas mr-1 text-slate-400"></i> 200+ Countries
                               </span>
                                <span class="text-slate-300">|</span>
                               <span class="flex items-center" title="Average Response Time">
                                   <i class="far fa-clock mr-1 text-slate-400"></i> ~50ms Time
                               </span>
                           </div>
                           <p class="text-xs text-slate-500 mt-2 text-center">Coverage and response times depend on the underlying data source and server configuration.</p>
                        </div>
                    </div>

                    <div class="w-full lg:w-1/2">
                        <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold text-white bg-gradient-to-r from-together-blue to-together-purple mb-4 hm-badge">
                            <i class="fas fa-bolt mr-1"></i> Fast & Reliable
                        </div>
                        <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold leading-tight mb-6">
                            BIN Lookup <span class="hm-gradient-text">API</span>
                        </h1>
                        <p class="text-lg md:text-xl text-slate-700 mb-8 max-w-lg">
                             Validate and get detailed information about any credit card BIN (Bank Identification Number) in real-time via a simple REST API. Access is managed via API key validation (typically linked to a purchased plan).
                        </p>
                        <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4">
                            <a href="#docs" class="px-6 py-3 rounded-lg font-medium hm-primary-button text-center shadow-lg hover:shadow-xl flex items-center justify-center">
                                <i class="fas fa-book mr-2"></i> Read Docs
                            </a>
                            <a href="#pricing" class="px-6 py-3 rounded-lg font-medium hm-secondary-button text-center shadow hover:shadow-md flex items-center justify-center">
                                <i class="fas fa-tag mr-2"></i> View Plans
                            </a>
                        </div>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <div class="flex items-center text-sm text-slate-600 bg-white px-3 py-1 rounded-full border border-slate-200">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                <span>High Availability*</span>
                            </div>
                            <div class="flex items-center text-sm text-slate-600 bg-white px-3 py-1 rounded-full border border-slate-200">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                <span>Regularly Updated Data*</span>
                            </div>
                            <div class="flex items-center text-sm text-slate-600 bg-white px-3 py-1 rounded-full border border-slate-200">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                <span>Easy Integration</span>
                            </div>
                        </div>
                         <p class="text-xs text-slate-500 mt-2">*Availability depends on server hosting. Data update frequency depends on the underlying data source.</p>
                    </div>
                </div>
            </div>
            <div class="hm-scroll-indicator hidden md:block">
                 <a href="#use-cases" class="text-slate-400 hover:text-together-blue">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                    </svg>
                </a>
            </div>
        </section>

        <section class="py-16 bg-gradient-to-r from-blue-50 to-purple-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="text-center"><div class="text-5xl font-bold text-together-blue mb-2">Reliable</div><p class="text-lg text-slate-700">API Performance*</p></div>
                    <div class="text-center"><div class="text-5xl font-bold text-together-purple mb-2">Flexible</div><p class="text-lg text-slate-700">Plan Options</p></div>
                    <div class="text-center"><div class="text-5xl font-bold text-together-green mb-2">Scalable</div><p class="text-lg text-slate-700">Request Handling*</p></div>
                </div>
                 <p class="text-center text-sm text-slate-600 mt-4">* Depends on server hosting and configuration.</p>
            </div>
        </section>

        <section id="docs" class="py-16 bg-slate-50">
             <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                 <div class="text-center mb-12">
                     <h2 class="text-3xl md:text-4xl font-bold mb-4">API Documentation (For Direct Integration)</h2>
                     <p class="text-lg text-slate-700 max-w-2xl mx-auto">How developers with an API key can integrate and use the service directly.</p>
                 </div>

                 <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                     <div class="hm-tabs">
                         <div class="hm-tab active" onclick="changeTab('endpoint')">Endpoint &amp; Auth</div>
                         <div class="hm-tab" onclick="changeTab('parameters')">Parameters</div>
                         <div class="hm-tab" onclick="changeTab('headers')">Headers</div>
                         <div class="hm-tab" onclick="changeTab('responses')">Responses</div>
                         <div class="hm-tab" onclick="changeTab('errors')">Error Codes</div>
                     </div>

                     <div class="p-6">
                         <div id="endpoint" class="hm-tab-content active">
                             <h3 class="text-2xl font-bold mb-4">API Endpoint & Authentication</h3>
                             <p class="text-slate-700 mb-4">The API is accessed via a standard REST endpoint. Authentication is required using an API key provided as a query parameter.</p>
                             <h4 class="text-xl font-semibold mb-3 mt-6">Endpoint Structure</h4>
                              <p class="text-slate-600 mb-2">Allowed Methods:
                                  <?php foreach ($allowed_methods as $method): ?>
                                      <span class="bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded"><?php echo esc_html($method); ?></span>
                                  <?php endforeach; ?>
                              </p>
                             <div class="hm-code-block p-4 mb-6">
                                 <pre><code><?php echo esc_html($api_base_url); ?>/bin/v1/lookup/{bin}</code></pre>
                             </div>
                             <p class="text-slate-700 mb-4">
                                 Replace <code><?php echo esc_html($api_base_url); ?></code> with the actual base URL for your WordPress REST API (e.g., <code>https://yoursite.com/wp-json</code>). <br>
                                 Replace <code>{bin}</code> with the 6 to 8 digit Bank Identification Number you want to look up (this is part of the URL path).
                             </p>
                             <h4 class="text-xl font-semibold mb-3 mt-6">Authentication</h4>
                             <p class="text-slate-700 mb-4">
                                 Authentication requires an active API key, typically obtained after purchasing a plan. Include the key as a query string parameter named <code>api_key</code>:
                             </p>
                             <div class="hm-code-block p-4 mb-6">
                                 <pre><code>?api_key=<?php echo esc_html($placeholder_api_key); ?></code></pre>
                             </div>
                             <p class="text-xs text-slate-500 mb-4">Replace <?php echo esc_html($placeholder_api_key); ?> with your personal API key found in your account or purchase confirmation details.</p>
                             <h4 class="text-xl font-semibold mb-3">Example Request (cURL)</h4>
                             <div class="hm-code-block p-4">
                                  <pre><code><span class="token comment"># Basic GET request for BIN 424242 using your key</span>
<span class="token keyword">curl</span> <span class="token parameter">-X</span> GET \<br>  <span class="token url">"<?php echo esc_html($api_base_url); ?>/bin/v1/lookup/424242?api_key=<?php echo esc_html($placeholder_api_key); ?>"</span></code></pre>
                             </div>
                             <p class="text-xs text-slate-500 mt-2">Remember to replace placeholders with your actual API base URL and API key.</p>
                             <p class="mt-6 text-sm text-slate-600 border-t pt-4">
                                 <strong>Note:</strong> The interactive "Try It" feature on this page uses a separate, rate-limited proxy endpoint (`.../orunk/v1/bin-lookup-proxy/...`) for demonstration purposes and does not require you to enter your API key there. The endpoint described above (`.../bin/v1/lookup/...`) is for direct integration into your applications using your purchased key.
                             </p>
                         </div>
                         <div id="parameters" class="hm-tab-content">
                              <h3 class="text-2xl font-bold mb-4">Request Parameters</h3>
                             <p class="text-slate-700 mb-6">The direct API uses URL path and query string parameters:</p>
                             <div class="overflow-x-auto">
                                 <table class="min-w-full divide-y divide-slate-200">
                                     <thead class="bg-slate-50">
                                         <tr>
                                             <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Parameter</th>
                                             <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Location</th>
                                             <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Type</th>
                                             <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Required</th>
                                             <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th>
                                         </tr>
                                     </thead>
                                     <tbody class="bg-white divide-y divide-slate-200">
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">{bin}</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">URL Path</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">string (digits)</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">Yes</td><td class="px-6 py-4 text-sm text-slate-500">The first 6 to 8 digits of a payment card number (BIN/IIN).</td></tr>
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">api_key</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">Query String</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">string</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">Yes</td><td class="px-6 py-4 text-sm text-slate-500">Your personal API key, obtained upon plan activation/purchase.</td></tr>
                                     </tbody>
                                 </table>
                             </div>
                         </div>
                         <div id="headers" class="hm-tab-content">
                              <h3 class="text-2xl font-bold mb-4">HTTP Headers</h3>
                              <p class="text-slate-700 mb-6">While not strictly required for basic GET requests, you can include standard HTTP headers:</p>
                              <div class="overflow-x-auto">
                                 <table class="min-w-full divide-y divide-slate-200">
                                      <thead class="bg-slate-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Header</th><th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Required</th><th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th></tr></thead>
                                      <tbody class="bg-white divide-y divide-slate-200"><tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">Accept</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">No</td><td class="px-6 py-4 text-sm text-slate-500">Specify desired response format (e.g., <code>application/json</code> or <code>application/xml</code>). If omitted, the API default (set in admin settings, usually JSON) is used.</td></tr><tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">Content-Type</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">If sending data (POST/PUT)</td><td class="px-6 py-4 text-sm text-slate-500">Specifies the format of data being sent in the request body (e.g., <code>application/json</code>). Not applicable for standard GET requests.</td></tr></tbody>
                                 </table>
                              </div>
                               <h4 class="text-xl font-semibold mb-3 mt-6">Example Request with Header (cURL)</h4>
                               <div class="hm-code-block p-4">
                                   <pre><code><span class="token comment"># Request JSON response explicitly</span>
<span class="token keyword">curl</span> <span class="token parameter">-X</span> GET \<br>  <span class="token parameter">-H</span> <span class="token string">"Accept: application/json"</span> \<br>  <span class="token url">"<?php echo esc_html($api_base_url); ?>/bin/v1/lookup/555555?api_key=<?php echo esc_html($placeholder_api_key); ?>"</span></code></pre>
                               </div>
                         </div>
                         <div id="responses" class="hm-tab-content">
                              <h3 class="text-2xl font-bold mb-4">Success Response (200 OK)</h3>
                              <p class="text-slate-700 mb-6">Successful lookups return an HTTP <code>200 OK</code> status code. The response body format is determined by the API settings (default: JSON) or the <code>Accept</code> header and includes the following fields:</p>
                              <div class="space-y-4">
                                   <div class="hm-api-param pl-4"><div class="flex items-baseline"><span class="text-sm font-mono text-blue-600 mr-2">bin</span><span class="text-xs text-slate-500">string</span></div><p class="text-sm text-slate-700 mt-1">The actual BIN number matched in the database (may be shorter than the queried BIN if a prefix matched) or the queried BIN if not found.</p></div>
                                   <div class="hm-api-param pl-4"><div class="flex items-baseline"><span class="text-sm font-mono text-blue-600 mr-2">bank_name</span><span class="text-xs text-slate-500">string</span></div><p class="text-sm text-slate-700 mt-1">Name of the issuing bank or financial institution. Returns 'Unknown' if not found.</p></div>
                                   <div class="hm-api-param pl-4"><div class="flex items-baseline"><span class="text-sm font-mono text-blue-600 mr-2">card_brand</span><span class="text-xs text-slate-500">string</span></div><p class="text-sm text-slate-700 mt-1">Card brand (e.g., VISA, MASTERCARD, AMEX). Returns 'Unknown' if not found.</p></div>
                                   <div class="hm-api-param pl-4"><div class="flex items-baseline"><span class="text-sm font-mono text-blue-600 mr-2">card_type</span><span class="text-xs text-slate-500">string</span></div><p class="text-sm text-slate-700 mt-1">Card type (e.g., DEBIT, CREDIT). Returns 'Unknown' if not found.</p></div>
                                   <div class="hm-api-param pl-4"><div class="flex items-baseline"><span class="text-sm font-mono text-blue-600 mr-2">card_level</span><span class="text-xs text-slate-500">string</span></div><p class="text-sm text-slate-700 mt-1">Card level or product tier (e.g., CLASSIC, PLATINUM, BUSINESS). Returns 'Unknown' if not found.</p></div>
                                   <div class="hm-api-param pl-4"><div class="flex items-baseline"><span class="text-sm font-mono text-blue-600 mr-2">country_iso</span><span class="text-xs text-slate-500">string</span></div><p class="text-sm text-slate-700 mt-1">ISO 3166-1 alpha-2 country code of the issuing bank (e.g., 'US', 'GB'). Returns 'Unknown' if not found.</p></div>
                                   <div class="hm-api-param pl-4"><div class="flex items-baseline"><span class="text-sm font-mono text-blue-600 mr-2">country_name</span><span class="text-xs text-slate-500">string</span></div><p class="text-sm text-slate-700 mt-1">Full country name of the issuing bank (e.g., 'United States', 'United Kingdom'). Returns 'Unknown' if not found.</p></div>
                                   <div class="hm-api-param pl-4"><div class="flex items-baseline"><span class="text-sm font-mono text-blue-600 mr-2">source</span><span class="text-xs text-slate-500">string</span></div><p class="text-sm text-slate-700 mt-1">Indicates the source of the data: 'database' (live lookup), 'cache' (retrieved from temporary cache for performance), or 'not_found'. <i>(Note: This field is excluded from the proxy endpoint used in the "Try It" demo)</i>.</p></div>
                               </div>
                               <h4 class="text-xl font-semibold mb-3 mt-6">Example JSON Response (Success)</h4>
                               <div class="hm-code-block p-4">
                                   <pre><code class="language-json">{
  <span class="token property">"bin"</span>: <span class="token string">"457173"</span>,
  <span class="token property">"bank_name"</span>: <span class="token string">"JPMORGAN CHASE BANK, N.A."</span>,
  <span class="token property">"card_brand"</span>: <span class="token string">"VISA"</span>,
  <span class="token property">"card_type"</span>: <span class="token string">"DEBIT"</span>,
  <span class="token property">"card_level"</span>: <span class="token string">"CLASSIC"</span>,
  <span class="token property">"country_iso"</span>: <span class="token string">"US"</span>,
  <span class="token property">"country_name"</span>: <span class="token string">"United States"</span>,
  <span class="token property">"source"</span>: <span class="token string">"database"</span>
}</code></pre>
                               </div>
                                <h4 class="text-xl font-semibold mb-3 mt-6">Example XML Response (Success)</h4>
                                <div class="hm-code-block p-4">
                                   <pre><code class="language-xml"><span class="token tag"><<span class="token tag">bin_lookup</span>></span>
  <span class="token tag"><<span class="token tag">bin</span>></span>457173<span class="token tag"></<span class="token tag">bin</span>></span>
  <span class="token tag"><<span class="token tag">bank_name</span>></span>JPMORGAN CHASE BANK, N.A.<span class="token tag"></<span class="token tag">bank_name</span>></span>
  <span class="token tag"><<span class="token tag">card_brand</span>></span>VISA<span class="token tag"></<span class="token tag">card_brand</span>></span>
  <span class="token tag"><<span class="token tag">card_type</span>></span>DEBIT<span class="token tag"></<span class="token tag">card_type</span>></span>
  <span class="token tag"><<span class="token tag">card_level</span>></span>CLASSIC<span class="token tag"></<span class="token tag">card_level</span>></span>
  <span class="token tag"><<span class="token tag">country_iso</span>></span>US<span class="token tag"></<span class="token tag">country_iso</span>></span>
  <span class="token tag"><<span class="token tag">country_name</span>></span>United States<span class="token tag"></<span class="token tag">country_name</span>></span>
  <span class="token tag"><<span class="token tag">source</span>></span>database<span class="token tag"></<span class="token tag">source</span>></span>
<span class="token tag"></<span class="token tag">bin_lookup</span>></span></code></pre>
                                </div>
                         </div>
                         <div id="errors" class="hm-tab-content">
                             <h3 class="text-2xl font-bold mb-4">Error Responses</h3>
                             <p class="text-slate-700 mb-6">If an error occurs, the API returns an appropriate HTTP status code (e.g., 4xx, 5xx) and a response body (usually JSON, see <a href="https://developer.wordpress.org/rest-api/using-the-rest-api/error-handling/" target="_blank" class="text-together-blue hover:underline">WP REST API Error Format</a> for structure) containing a specific error <code>code</code> and descriptive <code>message</code>.</p>
                             <div class="overflow-x-auto">
                                 <table class="min-w-full divide-y divide-slate-200">
                                     <thead class="bg-slate-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">HTTP Status</th><th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Code</th><th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th></tr></thead>
                                     <tbody class="bg-white divide-y divide-slate-200">
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">400 Bad Request</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><code>rest_invalid_param</code></td><td class="px-6 py-4 text-sm text-slate-500">Invalid BIN format (not 6-8 digits). Check the <code>{bin}</code> path parameter.</td></tr>
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">401 Unauthorized</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><code>no_api_key</code></td><td class="px-6 py-4 text-sm text-slate-500">Missing <code>api_key</code> query parameter. Authentication is required.</td></tr>
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">403 Forbidden</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><code>invalid_api_key</code></td><td class="px-6 py-4 text-sm text-slate-500">API key not found, invalid, or does not match an active purchase record. (Message configurable in settings)</td></tr>
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">403 Forbidden</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><code>key_not_active</code></td><td class="px-6 py-4 text-sm text-slate-500">API key is valid but associated subscription/purchase is not 'active' (e.g., expired, cancelled). (Message configurable)</td></tr>
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">403 Forbidden</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><code>feature_mismatch</code></td><td class="px-6 py-4 text-sm text-slate-500">API key is not valid for the 'bin_api' feature.</td></tr>
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">403 Forbidden</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><code>key_expired</code></td><td class="px-6 py-4 text-sm text-slate-500">The API key's subscription has passed its expiry date.</td></tr>
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">403 Forbidden</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><code>ip_not_allowed</code></td><td class="px-6 py-4 text-sm text-slate-500">Request IP address is not in the allowed list configured in the plugin settings.</td></tr>
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">404 Not Found</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><code>rest_no_route</code></td><td class="px-6 py-4 text-sm text-slate-500">Incorrect API endpoint URL or API is disabled in settings.</td></tr>
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">429 Too Many Requests</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><code>rate_limit_exceeded_day</code> / <code>rate_limit_exceeded_month</code></td><td class="px-6 py-4 text-sm text-slate-500">Daily or monthly request limit associated with the API key's plan has been exceeded. (Message configurable)</td></tr>
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">500 Server Error</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><code>db_table_missing</code></td><td class="px-6 py-4 text-sm text-slate-500">Required BIN data table (e.g., wp_bsp_bins) not found in the database. Check plugin setup.</td></tr>
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">500 Server Error</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><code>xml_format_error</code> / <code>internal_data_error</code></td><td class="px-6 py-4 text-sm text-slate-500">Internal error during response formatting or data handling. Check server logs.</td></tr>
                                         <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">503 Service Unavailable</td><td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><code>plugin_dependency_missing</code></td><td class="px-6 py-4 text-sm text-slate-500">Required dependency (like Orunk Users for key validation) is not active or missing.</td></tr>
                                     </tbody>
                                 </table>
                             </div>
                             <h4 class="text-xl font-semibold mb-3 mt-6">Example JSON Error Response (403 Forbidden)</h4>
                              <div class="hm-code-block p-4">
                                   <pre><code class="language-json">{
  <span class="token property">"code"</span>: <span class="token string">"key_not_active"</span>,
  <span class="token property">"message"</span>: <span class="token string">"Access denied."</span>, <span class="token comment">// Example message from settings</span>
  <span class="token property">"data"</span>: {
    <span class="token property">"status"</span>: <span class="token number">403</span>
  }
}</code></pre>
                               </div>
                         </div> </div> </div> </div> </section>

        <section id="use-cases" class="py-16 bg-white">
             <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                 <div class="text-center mb-16"><h2 class="text-3xl md:text-4xl font-bold mb-4">Powerful Use Cases</h2><p class="text-lg text-slate-700 max-w-2xl mx-auto">How businesses can use the BIN Lookup API</p></div>
                 <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                     <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl p-6 border border-blue-100"><div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mb-4"><i class="fas fa-shopping-cart text-blue-600 text-xl"></i></div><h3 class="text-xl font-bold mb-2">E-commerce</h3><p class="text-slate-700 mb-4">Automatically detect card types (`card_brand`, `card_type`) to show appropriate payment icons and optimize checkout flows.</p><ul class="space-y-2 text-sm text-slate-600"><li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i><span>Show correct card logos during checkout</span></li><li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i><span>Understand customer payment preferences</span></li><li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i><span>Tailor payment options based on card type</span></li></ul></div>
                     <div class="bg-gradient-to-br from-green-50 to-blue-50 rounded-xl p-6 border border-green-100"><div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mb-4"><i class="fas fa-shield-alt text-green-600 text-xl"></i></div><h3 class="text-xl font-bold mb-2">Fraud Prevention</h3><p class="text-slate-700 mb-4">Detect mismatches between card country (`country_name`, `country_iso`) and user location to flag potential fraud.</p><ul class="space-y-2 text-sm text-slate-600"><li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i><span>Identify high-risk geographic patterns</span></li><li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i><span>Verify card origin country</span></li><li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i><span>Add data points to risk scoring models</span></li></ul></div>
                     <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 border border-purple-100"><div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center mb-4"><i class="fas fa-plane text-purple-600 text-xl"></i></div><h3 class="text-xl font-bold mb-2">Travel & Hospitality</h3><p class="text-slate-700 mb-4">Verify cards match the traveler's expected country to prevent booking issues and reduce declines.</p><ul class="space-y-2 text-sm text-slate-600"><li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i><span>Ensure cards are appropriate for the region</span></li><li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i><span>Check card type (credit/debit) for holds</span></li><li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i><span>Improve authorization success rates</span></li></ul></div>
                 </div>
             </div>
        </section>

        <section id="pricing" class="py-16 bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                 <div class="text-center mb-12">
                     <h2 class="text-3xl md:text-4xl font-bold mb-4">API Plans</h2>
                     <p class="text-lg text-slate-700 max-w-2xl mx-auto">Choose the plan that fits your API usage needs. Access requires an API key obtained via purchase.</p>
                 </div>
                <?php
                    // --- PHP Logic for Dynamic URLs and Active Plan ---
                    // Ensure dependencies are available before executing complex logic
                    global $wpdb;
                    $plans_available = isset($wpdb) && class_exists('Custom_Orunk_Core') && class_exists('Custom_Orunk_DB');
                    $checkout_page_slug = 'checkout';
                    $feature_key = 'bin_api'; // IMPORTANT: Ensure this matches the feature key in your products table

                    $is_logged_in = $plans_available && function_exists('is_user_logged_in') && is_user_logged_in();
                    $current_user_id = $is_logged_in ? get_current_user_id() : 0;
                    $active_plan_details = null;
                    $free_plan_details = null; $pro_plan_details = null; $business_plan_details = null;
                    $checkout_url_base = '';

                    if ($plans_available) {
                        try {
                             $orunk_core = new Custom_Orunk_Core(); // Assumes constructor doesn't error
                             if ($is_logged_in) {
                                 $active_plan_details = $orunk_core->get_user_active_plan($current_user_id, $feature_key);
                             }

                             $plans_table_name = $wpdb->prefix . 'orunk_product_plans'; // Define table name
                             if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $plans_table_name)) === $plans_table_name) {
                                 $free_plan_details = $wpdb->get_row($wpdb->prepare( "SELECT * FROM $plans_table_name WHERE product_feature_key = %s AND plan_name = %s AND is_active = 1", $feature_key, 'Free'), ARRAY_A);
                                 $pro_plan_details = $wpdb->get_row($wpdb->prepare( "SELECT * FROM $plans_table_name WHERE product_feature_key = %s AND plan_name = %s AND is_active = 1", $feature_key, 'Pro'), ARRAY_A);
                                 $business_plan_details = $wpdb->get_row($wpdb->prepare( "SELECT * FROM $plans_table_name WHERE product_feature_key = %s AND plan_name = %s AND is_active = 1", $feature_key, 'Business'), ARRAY_A);
                             } else { error_log("Orunk BIN API Template Warning: Plans table '$plans_table_name' not found."); }

                             $checkout_page = null;
                             if(function_exists('get_page_by_path')) { $checkout_page = get_page_by_path($checkout_page_slug); }
                             if ($checkout_page && function_exists('get_permalink')) { $checkout_url_base = get_permalink($checkout_page->ID); }
                             else { $checkout_url_base = function_exists('site_url') ? site_url('/' . $checkout_page_slug . '/') : ''; }
                        } catch (Exception $e) {
                             error_log("Orunk BIN API Template Error Fetching Plans/Core: " . $e->getMessage());
                             $plans_available = false; // Disable plan display if core components failed
                        }

                    } else {
                         error_log("Orunk BIN API Template Warning: Core dependencies (WPDB, Custom_Orunk_Core, Custom_Orunk_DB) not met for fetching plans.");
                    }

                    $checkout_param_name = 'plan_id';
                    $can_get_login_url = function_exists('wp_login_url');

                    // --- Button Helper Function (ensure it's defined only once) ---
                    if (!function_exists('get_plan_button_details')) {
                         function get_plan_button_details( $target_plan_details, $active_plan_details, $checkout_base_url, $checkout_param, $can_get_login_url, $is_logged_in ) {
                             $details = [ 'url' => '#', 'text' => 'Unavailable', 'disabled' => true, 'class' => 'hm-button hm-primary-button disabled', 'onclick' => 'event.preventDefault(); alert(\'This plan is currently unavailable.\');', 'title' => 'This plan is currently unavailable.', 'badge' => '' ];
                             if (!$target_plan_details || !isset($target_plan_details['id']) || !isset($target_plan_details['price']) || !isset($target_plan_details['plan_name'])) { return $details; }
                             $target_plan_id = absint($target_plan_details['id']); $target_price = floatval($target_plan_details['price']); $target_name = $target_plan_details['plan_name']; $is_target_free = (strtolower($target_name) === 'free');
                             $details['badge'] = ''; if ($target_name === 'Pro') $details['badge'] .= '<span class="hm-badge hm-badge-popular">Most Popular</span>';
                             $can_checkout = !empty($checkout_base_url); $target_checkout_url = $can_checkout ? add_query_arg($checkout_param, $target_plan_id, $checkout_base_url) : '#';
                             $login_url_base = ($can_checkout && $can_get_login_url) ? wp_login_url($target_checkout_url) : '#';
                              if (!$is_logged_in) { $details['text'] = $is_target_free ? 'Get Started Free' : 'Get Started'; $details['disabled'] = ($login_url_base === '#'); $details['class'] = $details['disabled'] ? 'hm-button hm-primary-button disabled' : 'hm-button hm-primary-button'; $details['url'] = $details['disabled'] ? '#' : $login_url_base; $details['title'] = $details['disabled'] ? 'Login/Checkout URL unavailable.' : 'Login required to get started.'; $details['onclick'] = $details['disabled'] ? 'event.preventDefault(); alert(\'Login/Checkout URL unavailable.\');' : ''; }
                              else { $active_plan_id = $active_plan_details ? absint($active_plan_details['plan_id']) : null; $active_price = ($active_plan_details && isset($active_plan_details['price'])) ? floatval($active_plan_details['price']) : null; if (!$can_checkout) { $details['text'] = 'Checkout N/A'; $details['disabled'] = true; $details['class'] = 'hm-button hm-primary-button disabled'; $details['onclick'] = 'event.preventDefault(); alert(\'Checkout page not configured.\');'; $details['title'] = 'Checkout page not configured.'; if ($target_plan_id === $active_plan_id) $details['badge'] = '<span class="hm-badge hm-badge-current">Current Plan</span>'; } elseif ($target_plan_id === $active_plan_id) { $details['text'] = 'Active Plan'; $details['url'] = '#'; $details['disabled'] = true; $details['class'] = 'hm-button hm-active-button'; $details['onclick'] = 'event.preventDefault();'; $details['title'] = 'You are currently on this plan.'; $details['badge'] = '<span class="hm-badge hm-badge-current">Current Plan</span>'; } elseif ($active_plan_details === null) { $details['text'] = $is_target_free ? 'Get Started Free' : 'Get Started'; $details['url'] = $target_checkout_url; $details['disabled'] = false; $details['class'] = 'hm-button hm-primary-button'; $details['onclick'] = ''; $details['title'] = 'Get started with this plan.'; } elseif ($active_price !== null && $target_price < $active_price) { $details['text'] = 'Downgrade'; $details['url'] = $target_checkout_url; $details['disabled'] = false; $details['class'] = 'hm-button hm-secondary-button'; $details['onclick'] = 'return confirm(\'Are you sure you want to downgrade? This change may take effect on your next billing cycle.\');'; $details['title'] = 'Downgrade to this plan (features may be reduced).'; } elseif ($active_price !== null && $target_price > $active_price) { $details['text'] = 'Upgrade'; $details['url'] = $target_checkout_url; $details['disabled'] = false; $details['class'] = 'hm-button hm-primary-button'; $details['onclick'] = ''; $details['title'] = 'Upgrade to unlock more features.'; } elseif ($active_price !== null && $target_price == $active_price) { $details['text'] = 'Switch Plan'; $details['url'] = $target_checkout_url; $details['disabled'] = false; $details['class'] = 'hm-button hm-primary-button'; $details['onclick'] = ''; $details['title'] = 'Switch to this plan.'; } else { $details['text'] = 'Unavailable'; $details['disabled'] = true; $details['class'] = 'hm-button hm-primary-button disabled'; $details['onclick'] = 'event.preventDefault(); alert(\'This plan option is currently unavailable. Please cancel your current plan first to proceed.\');'; $details['title'] = 'This plan option is currently unavailable. Please cancel your current plan first to proceed.'; } }
                             return $details;
                         }
                    }
                    // Get button details safely
                     $default_button = ['url'=>'#','text'=>'Unavailable','disabled'=>true,'class'=>'hm-button hm-primary-button disabled','onclick'=>'event.preventDefault(); alert(\'Plan data missing.\');','title'=>'Plan data missing.','badge'=>''];
                    $free_button = ($plans_available && $free_plan_details) ? get_plan_button_details($free_plan_details, $active_plan_details, $checkout_url_base, $checkout_param_name, $can_get_login_url, $is_logged_in) : $default_button;
                    $pro_button = ($plans_available && $pro_plan_details) ? get_plan_button_details($pro_plan_details, $active_plan_details, $checkout_url_base, $checkout_param_name, $can_get_login_url, $is_logged_in) : $default_button;
                    $business_button = ($plans_available && $business_plan_details) ? get_plan_button_details($business_plan_details, $active_plan_details, $checkout_url_base, $checkout_param_name, $can_get_login_url, $is_logged_in) : $default_button;
                ?>
                 <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-7xl mx-auto">
                     <div class="hm-pricing-card bg-white rounded-xl shadow-sm overflow-hidden border border-slate-200 p-6">
                         <div class="hm-badge-container"><?php echo $free_button['badge']; // Already safe HTML ?></div>
                         <div class="hm-pricing-card-content">
                            <h3 class="text-xl font-bold mb-2 text-slate-800"><?php echo esc_html($free_plan_details['plan_name'] ?? 'Free'); ?></h3>
                            <p class="text-slate-600 mb-6"><?php echo esc_html($free_plan_details['description'] ?? 'Basic access for testing and low usage'); ?></p>
                            <div class="text-4xl font-bold mb-1">Free</div>
                            <div class="text-sm text-slate-500 mb-6">&nbsp;</div>
                            <ul class="space-y-3 mb-8 text-slate-700">
                               <li class="flex items-center"><i class="fas fa-check-circle text-green-500 w-5 mr-2"></i><span><?php echo isset($free_plan_details['requests_per_day']) ? number_format_i18n($free_plan_details['requests_per_day']) : '100'; ?> Requests / Day</span></li>
                               <li class="flex items-center"><i class="fas fa-check-circle text-green-500 w-5 mr-2"></i><span><?php echo isset($free_plan_details['requests_per_month']) ? number_format_i18n($free_plan_details['requests_per_month']) : '3,000'; ?> Requests / Month</span></li>
                               <li class="flex items-center"><i class="fas fa-times-circle text-red-500 w-5 mr-2"></i><span>Limited data fields</span></li>
                               <li class="flex items-center text-transparent"><i class="fas fa-check-circle w-5 mr-2"></i><span>&nbsp;</span></li>
                            </ul>
                            <p class="text-xs text-slate-500 mt-2 mb-4">No credit card required.</p>
                         </div>
                         <div class="hm-button-wrapper mt-auto">
                            <a href="<?php echo esc_url($free_button['url']); ?>" title="<?php echo esc_attr($free_button['title']); ?>" class="<?php echo esc_attr($free_button['class']); ?>" <?php if ($free_button['disabled']) { echo ' aria-disabled="true"'; } if (!empty($free_button['onclick'])) { echo ' onclick="' . esc_attr($free_button['onclick']) . '"'; } ?>>
                               <?php echo esc_html($free_button['text']); ?>
                            </a>
                         </div>
                     </div>
                     <div class="hm-pricing-card bg-white rounded-xl shadow-lg overflow-hidden border-2 border-together-blue p-6 relative">
                          <div class="hm-badge-container"><?php echo $pro_button['badge']; ?></div>
                         <div class="hm-pricing-card-content">
                            <h3 class="text-xl font-bold mb-2 text-together-blue"><?php echo esc_html($pro_plan_details['plan_name'] ?? 'Pro'); ?></h3>
                            <p class="text-slate-600 mb-6"><?php echo esc_html($pro_plan_details['description'] ?? 'Ideal for growing applications'); ?></p>
                            <div class="text-4xl font-bold mb-1">$<?php echo isset($pro_plan_details['price']) ? number_format_i18n($pro_plan_details['price'], 2) : '9.00'; ?></div>
                            <div class="text-sm text-slate-500 mb-6">per month</div>
                            <ul class="space-y-3 mb-8 text-slate-700">
                               <li class="flex items-center"><i class="fas fa-check-circle text-green-500 w-5 mr-2"></i><span><?php echo (isset($pro_plan_details['requests_per_day']) && $pro_plan_details['requests_per_day'] > 0) ? number_format_i18n($pro_plan_details['requests_per_day']).' / Day' : 'Unlimited Daily Requests'; ?> <span class="text-xs text-slate-500">(Fair Use*)</span></span></li>
                               <li class="flex items-center"><i class="fas fa-check-circle text-green-500 w-5 mr-2"></i><span><?php echo isset($pro_plan_details['requests_per_month']) ? number_format_i18n($pro_plan_details['requests_per_month']) : '50,000'; ?> Requests / Month</span></li>
                               <li class="flex items-center"><i class="fas fa-check-circle text-green-500 w-5 mr-2"></i><span>Full data fields included</span></li>
                               <li class="flex items-center text-transparent"><i class="fas fa-check-circle w-5 mr-2"></i><span>&nbsp;</span></li>
                            </ul>
                         </div>
                         <div class="hm-button-wrapper mt-auto">
                           <a href="<?php echo esc_url($pro_button['url']); ?>" title="<?php echo esc_attr($pro_button['title']); ?>" class="<?php echo esc_attr($pro_button['class']); ?>" <?php if ($pro_button['disabled']) { echo ' aria-disabled="true"'; } if (!empty($pro_button['onclick'])) { echo ' onclick="' . esc_attr($pro_button['onclick']) . '"'; } ?>>
                              <?php echo esc_html($pro_button['text']); ?>
                           </a>
                         </div>
                     </div>
                     <div class="hm-pricing-card bg-white rounded-xl shadow-sm overflow-hidden border border-slate-200 p-6 relative">
                           <div class="hm-badge-container"><?php echo $business_button['badge']; ?></div>
                         <div class="hm-pricing-card-content">
                            <h3 class="text-xl font-bold mb-2 text-slate-800"><?php echo esc_html($business_plan_details['plan_name'] ?? 'Business'); ?></h3>
                            <p class="text-slate-600 mb-6"><?php echo esc_html($business_plan_details['description'] ?? 'For high-volume requirements'); ?></p>
                             <div class="text-4xl font-bold mb-1">$<?php echo isset($business_plan_details['price']) ? number_format_i18n($business_plan_details['price'], 2) : '29.00'; ?></div>
                            <div class="text-sm text-slate-500 mb-6">per month</div>
                            <ul class="space-y-3 mb-8 text-slate-700">
                               <li class="flex items-center"><i class="fas fa-check-circle text-green-500 w-5 mr-2"></i><span><?php echo (isset($business_plan_details['requests_per_day']) && $business_plan_details['requests_per_day'] > 0) ? number_format_i18n($business_plan_details['requests_per_day']).' / Day' : 'Unlimited Daily Requests'; ?> <span class="text-xs text-slate-500">(Fair Use*)</span></span></li>
                               <li class="flex items-center"><i class="fas fa-check-circle text-green-500 w-5 mr-2"></i><span><?php echo isset($business_plan_details['requests_per_month']) ? number_format_i18n($business_plan_details['requests_per_month']) : '200,000'; ?> Requests / Month</span></li>
                               <li class="flex items-center"><i class="fas fa-check-circle text-green-500 w-5 mr-2"></i><span>Includes all Pro features</span></li>
                               <li class="flex items-center"><i class="fas fa-check-circle text-green-500 w-5 mr-2"></i><span>Priority Support (via Plan)</span></li>
                            </ul>
                         </div>
                         <div class="hm-button-wrapper mt-auto">
                             <a href="<?php echo esc_url($business_button['url']); ?>" title="<?php echo esc_attr($business_button['title']); ?>" class="<?php echo esc_attr($business_button['class']); ?>" <?php if ($business_button['disabled']) { echo ' aria-disabled="true"'; } if (!empty($business_button['onclick'])) { echo ' onclick="' . esc_attr($business_button['onclick']) . '"'; } ?>>
                                <?php echo esc_html($business_button['text']); ?>
                            </a>
                         </div>
                     </div>
                 </div> <?php if (!$plans_available): ?>
                     <p class="text-center text-red-600 mt-8"><?php esc_html_e('Plan information could not be loaded at this time. Please ensure the required plugins are active and configured correctly.', 'bin-lookup-api'); ?></p>
                 <?php endif; ?>
                 <p class="text-center text-sm text-slate-500 mt-8">Plan limits and features are enforced by the API key validation system. Ensure you have the correct key for your chosen plan. *Fair use policies may apply to unlimited usage.</p>
            </div>
        </section>

        <section class="py-16 bg-white">
             <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                  <div class="text-center mb-12"><h2 class="text-3xl md:text-4xl font-bold mb-4">Frequently Asked Questions</h2><p class="text-lg text-slate-700 max-w-2xl mx-auto">Get answers to common questions about the BIN Lookup API</p></div>
                  <div class="space-y-4">
                      <div class="border border-slate-200 rounded-lg overflow-hidden"><button class="w-full text-left p-4 focus:outline-none flex justify-between items-center bg-slate-50 hover:bg-slate-100" onclick="toggleFAQ(1)"><span class="font-medium">What is a BIN/IIN number?</span><i class="fas fa-chevron-down text-slate-500 transition-transform duration-200" id="faq-icon-1"></i></button><div class="p-4 hidden faq-content-item" id="faq-content-1"><p class="text-slate-700">A Bank Identification Number (BIN) or Issuer Identification Number (IIN) is typically the first 6-8 digits of a payment card number. It identifies the issuing bank or financial institution. This API uses this number to retrieve details about the card issuer.</p></div></div>
                      <div class="border border-slate-200 rounded-lg overflow-hidden"><button class="w-full text-left p-4 focus:outline-none flex justify-between items-center bg-slate-50 hover:bg-slate-100" onclick="toggleFAQ(2)"><span class="font-medium">How accurate is the BIN data?</span><i class="fas fa-chevron-down text-slate-500 transition-transform duration-200" id="faq-icon-2"></i></button><div class="p-4 hidden faq-content-item" id="faq-content-2"><p class="text-slate-700">The accuracy depends entirely on the external service being called by the proxy (for the demo) or the underlying database used by the direct API endpoint (`/bin/v1/lookup/`). Ensure a reliable data source is configured in the backend.</p></div></div>
                      <div class="border border-slate-200 rounded-lg overflow-hidden"><button class="w-full text-left p-4 focus:outline-none flex justify-between items-center bg-slate-50 hover:bg-slate-100" onclick="toggleFAQ(3)"><span class="font-medium">How are API Plans and Limits managed?</span><i class="fas fa-chevron-down text-slate-500 transition-transform duration-200" id="faq-icon-3"></i></button><div class="p-4 hidden faq-content-item" id="faq-content-3"><p class="text-slate-700">API keys, subscription plans, daily/monthly request limits, and access control for the direct endpoint (`/bin/v1/lookup/`) are managed by the Orunk Users plugin based on user purchases. The "Try It" proxy endpoint (`/orunk/v1/bin-lookup-proxy/`) has its own separate, fixed rate limit configured in its server-side code.</p></div></div>
                      <div class="border border-slate-200 rounded-lg overflow-hidden"><button class="w-full text-left p-4 focus:outline-none flex justify-between items-center bg-slate-50 hover:bg-slate-100" onclick="toggleFAQ(4)"><span class="font-medium">How do I handle API rate limits?</span><i class="fas fa-chevron-down text-slate-500 transition-transform duration-200" id="faq-icon-4"></i></button><div class="p-4 hidden faq-content-item" id="faq-content-4"><p class="text-slate-700">If the proxy's rate limit is hit (in the "Try It" demo), it returns an HTTP 429 error with code <code>captcha_required</code>. If the direct endpoint's rate limit (based on your plan) is hit, it will return an HTTP 429 error with code <code>rate_limit_exceeded_day</code> or <code>rate_limit_exceeded_month</code>. Your application should handle these 429 responses appropriately (e.g., by pausing requests).</p></div></div>
                      <div class="border border-slate-200 rounded-lg overflow-hidden"><button class="w-full text-left p-4 focus:outline-none flex justify-between items-center bg-slate-50 hover:bg-slate-100" onclick="toggleFAQ(5)"><span class="font-medium">Can I use this API for fraud detection?</span><i class="fas fa-chevron-down text-slate-500 transition-transform duration-200" id="faq-icon-5"></i></button><div class="p-4 hidden faq-content-item" id="faq-content-5"><p class="text-slate-700">Yes, the data provided by this API (like card brand, type, and country of origin) can be a valuable input for fraud detection systems. By comparing the card's issuing country (`country_name`) with the user's location or IP address, you can identify potential risks. However, it should be used as one component within a broader fraud prevention strategy.</p></div></div>
                  </div>
             </div>
        </section>
    </main>

     <script>
        // --- API Base URL (Points to WP REST API base) ---
        const apiBaseUrl = '<?php echo esc_js($api_base_url); ?>'; // Should resolve to something like 'https://yoursite.com/wp-json'

        // --- Global variable to hold CAPTCHA token if needed ---
        let currentCaptchaToken = null;

        // Tab switching functionality
        function changeTab(tabId) {
            document.querySelectorAll('.hm-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.hm-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // FAQ toggle functionality
        function toggleFAQ(id) {
            const content = document.getElementById(`faq-content-${id}`);
            const icon = document.getElementById(`faq-icon-${id}`);
            const isHidden = content.classList.contains('hidden');

            if (isHidden) {
                content.classList.remove('hidden');
                content.classList.add('faq-open');
                icon.classList.add('transform', 'rotate-180');
            } else {
                content.classList.add('hidden');
                content.classList.remove('faq-open');
                icon.classList.remove('transform', 'rotate-180');
            }
             content.classList.add('faq-content-item');
        }


        // BIN lookup functionality (Uses PROXY Endpoint for "Try It")
        async function lookupBin(section) {
            const input = document.getElementById(`${section}-bin-input`);
            const bin = input.value.trim();
            const responseContainer = document.getElementById(`${section}-api-response`);
            const loadingIndicator = document.getElementById(`${section}-loading`);
            const errorMessage = document.getElementById(`${section}-error-message`);
            const responseContent = document.getElementById(`${section}-response-content`);
            const captchaContainer = document.getElementById(`${section}-captcha-widget`); // CAPTCHA placeholder

            // Basic URL check
             if (!apiBaseUrl || apiBaseUrl === 'YOUR_API_BASE_URL' || !apiBaseUrl.includes('/wp-json')) {
                 errorMessage.textContent = 'Error: API Base URL not correctly configured. Cannot make API call.';
                 errorMessage.classList.remove('hidden');
                 return;
             }

            // Validate BIN format
            if (!bin || !/^\d{6,8}$/.test(bin)) {
                errorMessage.textContent = 'Please enter a valid BIN (6-8 digits)';
                errorMessage.classList.remove('hidden');
                responseContainer.classList.add('hidden');
                loadingIndicator.classList.add('hidden');
                return;
            }

            // Clear previous state
            errorMessage.classList.add('hidden');
            errorMessage.textContent = '';
            responseContainer.classList.add('hidden');
            responseContent.textContent = '';
            if (captchaContainer) captchaContainer.innerHTML = ''; // Clear old CAPTCHA if present

            // Show loading
            loadingIndicator.classList.remove('hidden');

            // Construct the URL to call the PROXY endpoint
            let apiUrl = `${apiBaseUrl}/orunk/v1/bin-lookup-proxy/${bin}`;

            // Append CAPTCHA token if available from a previous challenge
             if (currentCaptchaToken) {
                 apiUrl += `?captcha_token=${encodeURIComponent(currentCaptchaToken)}`;
                 console.log('Retrying request with CAPTCHA token.');
             }

            try {
                // Make the fetch call to the PROXY (NO API KEY SENT FROM HERE)
                const response = await fetch(apiUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                    }
                });

                // Hide loading indicator
                loadingIndicator.classList.add('hidden');
                // Reset CAPTCHA token after attempt
                currentCaptchaToken = null;

                const data = await response.json(); // Try to parse JSON body

                if (!response.ok) {
                    // Handle errors from the proxy
                    let errorMsg = `Error ${response.status}: ${response.statusText}`; // Default
                    if (data && data.message) { errorMsg = data.message; } // Use message from response if available
                    else if (response.status === 404) { errorMsg = "API Endpoint not found."; }
                    else if (response.status === 429) { errorMsg = data.message || "Rate limit likely exceeded."; }
                    else if (response.status === 500) { errorMsg = data.message || "Internal server error."; }

                     // Check if CAPTCHA is now required
                     if (data && data.data && data.data.captcha_needed) {
                         errorMsg = data.message || 'Too many requests. Please complete the CAPTCHA below and try again.';
                         errorMessage.textContent = `Action Required: ${errorMsg}`;
                         console.warn("CAPTCHA required! Implement frontend display logic.");
                         // !!! RENDER THE CAPTCHA WIDGET HERE !!!
                         if (captchaContainer) {
                              captchaContainer.innerHTML = '<p style="color: red; font-size: small;">CAPTCHA display logic needed here.</p>'; // Placeholder
                         }
                     } else {
                         // General error display
                         errorMessage.textContent = `API Error: ${errorMsg}`;
                     }
                     errorMessage.classList.remove('hidden');
                     console.error('API Error:', response.status, data);

                } else {
                     // Success
                     errorMessage.classList.add('hidden');
                     errorMessage.textContent = '';
                    responseContent.textContent = JSON.stringify(data, null, 2); // Display successful data
                    responseContainer.classList.remove('hidden');
                    if (captchaContainer) captchaContainer.innerHTML = ''; // Ensure CAPTCHA hidden on success
                }

            } catch (error) {
                // Handle network errors or JSON parsing errors
                loadingIndicator.classList.add('hidden');
                errorMessage.textContent = `Request Failed: ${error.message}`;
                errorMessage.classList.remove('hidden');
                console.error('Fetch/Network Error:', error);
                currentCaptchaToken = null; // Reset token on network error
                if (captchaContainer) captchaContainer.innerHTML = '';
            }
        }

        // Copy response to clipboard
        function copyResponse(section) {
            const responseContent = document.getElementById(`${section}-response-content`);
            const text = responseContent.textContent;
            const button = event.currentTarget;
            if (!text || !button) return;
            navigator.clipboard.writeText(text).then(() => {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check mr-1"></i> Copied';
                button.disabled = true;
                setTimeout(() => { if (document.body.contains(button)) { button.innerHTML = originalText; button.disabled = false; } }, 2000);
            }).catch(err => {
                console.error('Failed to copy text: ', err);
                 const errorMessage = document.getElementById(`${section}-error-message`);
                 if(errorMessage) { errorMessage.textContent = 'Failed to copy response.'; errorMessage.classList.remove('hidden'); }
            });
        }

        // Clear response area
        function clearResponse(section) {
            const responseContainer = document.getElementById(`${section}-api-response`);
            const responseContent = document.getElementById(`${section}-response-content`);
            const binInput = document.getElementById(`${section}-bin-input`);
            const errorMessage = document.getElementById(`${section}-error-message`);
            const loadingIndicator = document.getElementById(`${section}-loading`);
            const captchaContainer = document.getElementById(`${section}-captcha-widget`);

             if(responseContainer) responseContainer.classList.add('hidden');
             if(responseContent) responseContent.textContent = '';
             if(binInput) binInput.value = '';
             if(errorMessage) { errorMessage.textContent = ''; errorMessage.classList.add('hidden'); }
             if(loadingIndicator) loadingIndicator.classList.add('hidden');
             // Also clear CAPTCHA on manual clear
              currentCaptchaToken = null;
             if (captchaContainer) captchaContainer.innerHTML = '';
        }

        // --- Add CAPTCHA rendering/callback functions here if needed ---
        // function renderRecaptcha(elementId, callback) { ... }

    </script>
    </body>
</html>