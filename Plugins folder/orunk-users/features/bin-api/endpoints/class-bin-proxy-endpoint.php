<?php
/**
 * Handles the backend proxy endpoint for the public BIN Lookup API "Try It" feature.
 * Includes rate limiting and placeholder for CAPTCHA integration.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Orunk_Bin_Proxy_Endpoint {

    /** @var string WordPress REST API namespace */
    private $namespace = 'orunk/v1';

    /** @var string REST API route for the proxy */
    private $route = '/bin-lookup-proxy';

    /** @var string Transient prefix for rate limiting */
    private $rate_limit_transient_prefix = 'orunk_bin_rl_';

    /** @var int Max requests allowed before CAPTCHA */
    private $rate_limit_requests = 5;

    /** @var int Rate limit duration in seconds (e.g., 1 hour) */
    private $rate_limit_duration = HOUR_IN_SECONDS; // 3600

    /**
     * Registers the necessary hooks for the endpoint.
     * This method is called by Custom_Feature_Bin_API::init().
     */
    public function register_hooks() {
        add_action('rest_api_init', array($this, 'register_rest_route'));
    }

    /**
     * Registers the WordPress REST API route.
     */
    public function register_rest_route() {
        register_rest_route($this->namespace, $this->route . '/(?P<bin>\d{6,8})', array( // Capture BIN in URL
            'methods'             => WP_REST_Server::READABLE, // Corresponds to GET request
            'callback'            => array($this, 'handle_lookup_request'),
            'permission_callback' => '__return_true', // Public endpoint, permissions checked internally if needed
            'args'                => array(
                'bin' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_string($param) && preg_match('/^\d{6,8}$/', $param);
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                    'required'          => true,
                    'description'       => __('The 6 to 8 digit BIN to look up.', 'orunk-users'),
                ),
                // Add argument for CAPTCHA response if needed (depends on CAPTCHA type)
                'captcha_token' => array( // Example argument name - adjust as needed
                     'validate_callback' => function($param, $request, $key) {
                        // Validation depends heavily on CAPTCHA type
                        return is_string($param); 
                    },
                    'sanitize_callback' => 'sanitize_text_field', 
                    'required'          => false, // Only required when limit is hit
                    'description'       => __('CAPTCHA verification token, required after rate limit.', 'orunk-users'),
                ),
            ),
        ));
    }

    /**
     * Handles the incoming lookup request.
     *
     * @param WP_REST_Request $request The incoming request object.
     * @return WP_REST_Response|WP_Error The response object or error.
     */
    public function handle_lookup_request(WP_REST_Request $request) {
        $bin = $request->get_param('bin');
        $captcha_token = $request->get_param('captcha_token'); // Get potential CAPTCHA token
        $ip_address = $this->get_client_ip();

        // --- 1. Rate Limiting Check ---
        $rate_limit_info = $this->check_rate_limit($ip_address);

        if ($rate_limit_info['exceeded'] && !$this->validate_captcha($captcha_token, $ip_address)) {
             // Limit exceeded AND CAPTCHA is missing or invalid
            return new WP_REST_Response(array(
                'code' => 'captcha_required',
                'message' => __('Too many requests. Please complete the CAPTCHA.', 'orunk-users'),
                'data' => array('status' => 429, 'captcha_needed' => true) // Send flag to frontend
            ), 429); // 429 Too Many Requests
        }

        // If CAPTCHA was valid, proceed (and maybe reset limit counter below)
        
        // --- 2. Get API Key ---
        // Assumes the key is stored using the constant defined in Custom_Orunk_Settings
        $api_key = get_option(Custom_Orunk_Settings::BIN_API_KEY_OPTION);

        if (empty($api_key)) {
            error_log('Orunk BIN Lookup Error: API Key is not configured in settings.');
            return new WP_Error(
                'api_key_missing',
                __('Service configuration error. Please contact the administrator.', 'orunk-users'),
                array('status' => 500)
            );
        }

        // --- 3. Prepare External API Call ---
        // !!! IMPORTANT: Replace with the ACTUAL URL of the external BIN lookup service !!!
        $external_api_url = 'https://orunk.xyz/wp-json/bin/v1/lookup/'; // <-- REPLACE THIS

        // Construct the full URL (assuming BIN is part of path - adjust if needed)
        $request_url = trailingslashit($external_api_url) . $bin;

        // Add API key (assuming it's a query parameter - adjust if it's a header)
        $request_url = add_query_arg('api_key', $api_key, $request_url); // <-- Adjust 'apiKey' parameter name if needed

        // --- 4. Make External API Call ---
        $response = wp_remote_get($request_url, array(
            'timeout' => 15, // Set a reasonable timeout
            'headers' => array(
                'Accept' => 'application/json',
                // Add other headers if required by the external API (e.g., 'Authorization: Bearer '.$api_key)
            ),
        ));

        // --- 5. Process Response ---
        if (is_wp_error($response)) {
            error_log('Orunk BIN Lookup Error: WP HTTP API Error - ' . $response->get_error_message());
            return new WP_Error(
                'external_api_error',
                __('Could not connect to the lookup service.', 'orunk-users'),
                array('status' => 503) // 503 Service Unavailable
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $result_data = json_decode($response_body, true);

        if ($response_code >= 200 && $response_code < 300) {
            // Success from external API
            
            // Increment rate limit counter *after* successful non-CAPTCHA request OR reset counter if CAPTCHA was passed
            if ($rate_limit_info['exceeded']) {
                $this->reset_rate_limit($ip_address); // Reset limit after successful CAPTCHA'd request
            } else {
                $this->increment_rate_limit($ip_address); // Increment limit for normal request
            }
            
            unset($result_data['source']); // <-- ADD THIS LINE
            return new WP_REST_Response($result_data, 200);

        } else {
            // Error from external API
            $error_message = __('An error occurred during the lookup.', 'orunk-users');
            if (isset($result_data['message'])) {
                $error_message = $result_data['message']; // Use message from external API if available
            } elseif(isset($result_data['error'])) {
                 $error_message = $result_data['error'];
            }
             error_log("Orunk BIN Lookup Error: External API responded with code $response_code. Body: $response_body");

            // Forward the status code if it's a client error (4xx)
            $status_code = ($response_code >= 400 && $response_code < 500) ? $response_code : 502; // 502 Bad Gateway otherwise

            return new WP_Error(
                'external_api_failed',
                $error_message,
                array('status' => $status_code)
            );
        }
    }

    /**
     * Gets the client's IP address, considering proxies.
     * @return string|null IP address or null if not found.
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                // Extract the first IP if multiple are present (common in X-Forwarded-For)
                $ip_list = explode(',', $_SERVER[$key]);
                $ip = trim(reset($ip_list));
                 if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    /**
     * Checks the rate limit for a given IP address.
     * @param string $ip_address The IP address to check.
     * @return array ['count' => int, 'exceeded' => bool]
     */
    private function check_rate_limit($ip_address) {
        if (!$ip_address) return ['count' => 0, 'exceeded' => false]; // Cannot rate limit without IP

        $transient_key = $this->rate_limit_transient_prefix . md5($ip_address);
        $request_count = get_transient($transient_key);

        if ($request_count === false) {
            $request_count = 0; // No record yet
        }

        return array(
            'count' => (int)$request_count,
            'exceeded' => (int)$request_count >= $this->rate_limit_requests
        );
    }

    /**
     * Increments the rate limit counter for a given IP.
     * @param string $ip_address
     */
    private function increment_rate_limit($ip_address) {
        if (!$ip_address) return;

        $transient_key = $this->rate_limit_transient_prefix . md5($ip_address);
        $current_count = get_transient($transient_key);

        if ($current_count === false) {
            // First request in the window
            set_transient($transient_key, 1, $this->rate_limit_duration);
        } else {
             // Transient exists, increment it
             $new_count = (int)$current_count + 1;
             // Update transient with the same expiration time
             set_transient($transient_key, $new_count, $this->rate_limit_duration); 
        }
    }
    
    /**
     * Resets (deletes) the rate limit counter for a given IP.
     * Typically called after a successful CAPTCHA validation.
     * @param string $ip_address
     */
    private function reset_rate_limit($ip_address) {
        if (!$ip_address) return;
        $transient_key = $this->rate_limit_transient_prefix . md5($ip_address);
        delete_transient($transient_key);
    }


    /**
     * Validates the CAPTCHA response.
     * !!! THIS IS A PLACEHOLDER - REQUIRES ACTUAL IMPLEMENTATION !!!
     *
     * @param string|null $captcha_token The token received from the frontend.
     * @param string $ip_address Client IP (may be required by CAPTCHA service).
     * @return bool True if valid, false otherwise.
     */
    private function validate_captcha($captcha_token, $ip_address) {
        if (empty($captcha_token)) {
            return false; // No token provided
        }

        // --- Implementation Depends Heavily on CAPTCHA Choice ---
        // Example for Google reCAPTCHA v2 Checkbox:
        // 1. Get your reCAPTCHA secret key (store securely, e.g., WP options)
        // 2. Make a POST request to https://www.google.com/recaptcha/api/siteverify
        //    with 'secret' = your_secret_key and 'response' = $captcha_token
        //    and optionally 'remoteip' = $ip_address
        // 3. Check the 'success' field in the JSON response from Google.

        /* --- Placeholder Logic --- */
         error_log("CAPTCHA Validation Needed: Token received: $captcha_token. IP: $ip_address. Implement actual validation logic!");
         // return false; // Assume invalid until implemented
         
         // --- TEMPORARY: Return true ONLY for testing WITHOUT CAPTCHA ---
         // REMOVE THIS LINE after implementing real CAPTCHA validation
         // return true; 
         /* ----------------------- */

         // Replace placeholder with actual validation call
         $is_valid = false; // Replace with result of actual validation

         return $is_valid;
    }

} // End Class Orunk_Bin_Proxy_Endpoint