<?php
/**
 * Orunk Users License API Handler Class
 *
 * Handles dynamic REST API requests for validating, activating,
 * and potentially deactivating licenses for various features/plugins.
 * This class contains the callbacks for the dynamic REST API endpoints.
 *
 * @package OrunkUsers\Includes
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// --- Ensure Dependencies are Loaded ---
// Ensure ORUNK_USERS_PLUGIN_DIR is defined (should be by main plugin file)
if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
    // Fallback definition - adjust path if necessary based on final structure
    $plugin_dir_path = plugin_dir_path(dirname(__FILE__, 2)); // Go up two directories from includes/
    if (is_dir($plugin_dir_path)) {
         define('ORUNK_USERS_PLUGIN_DIR', $plugin_dir_path);
         error_log('Warning: ORUNK_USERS_PLUGIN_DIR was not defined in Orunk_License_Api_Handler. Using fallback: ' . ORUNK_USERS_PLUGIN_DIR);
    } else {
        error_log('CRITICAL Orunk License API Handler Error: Could not determine ORUNK_USERS_PLUGIN_DIR.');
        return; // Stop if we can't define the directory
    }
}
// Ensure the DB class is loaded
if (!class_exists('Custom_Orunk_DB')) {
    $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
    if (file_exists($db_path)) {
        require_once $db_path;
    } else {
        error_log("Orunk License API Handler CRITICAL ERROR: Cannot load Custom_Orunk_DB. Path checked: " . $db_path);
        // Prevent class definition if core dependency is missing
        return;
    }
}
// --- End Dependency Check ---


// Define the class only if it doesn't already exist
if (!class_exists('Orunk_License_Api_Handler')) {

    class Orunk_License_Api_Handler {

        /** @var Custom_Orunk_DB Database handler instance */
        private $db;

        /**
         * Constructor.
         */
        public function __construct() {
            // Instantiate the DB handler, check if class exists again just in case
            if (class_exists('Custom_Orunk_DB')) {
                $this->db = new Custom_Orunk_DB();
            } else {
                error_log('Orunk License API Handler Error: Custom_Orunk_DB class not available during instantiation.');
                $this->db = null; // Set to null if instantiation fails
            }
        }

        /**
         * Handles dynamic license validation and activation requests.
         * Callback for the '/orunk/v1/license/validate' REST route.
         *
         * Expects 'license_key', 'site_url', and 'feature_key' in the request.
         * Optionally accepts 'plugin_version'.
         *
         * @param WP_REST_Request $request The incoming request object.
         * @return WP_REST_Response Response object. Errors are returned as WP_REST_Response with appropriate status codes.
         */
        public function handle_dynamic_license_validation(WP_REST_Request $request) {
            // Ensure DB handler is available
            if (!$this->db) {
                error_log('Orunk License API Validation Error: DB handler not available.');
                return new WP_REST_Response(['status' => 'error', 'code' => 'server_misconfiguration', 'message' => __('Server configuration error.', 'orunk-users')], 500);
            }

            // --- 1. Get and Sanitize Parameters ---
            // Use get_param for built-in sanitization based on route definition (assuming sanitize_callback is set there)
            // Fallback to direct sanitization if needed.
            $license_key    = sanitize_text_field($request->get_param('license_key'));
            $site_url       = esc_url_raw($request->get_param('site_url')); // Raw URL for DB storage/comparison
            $feature_key    = sanitize_key($request->get_param('feature_key')); // Key for the specific plugin/feature
            $plugin_version = sanitize_text_field($request->get_param('plugin_version') ?? ''); // Optional, default empty
            $activation_ip  = $request->get_ip_address(); // Get client IP

            error_log("Orunk License API: Validation request received. Key: " . substr($license_key, 0, 5) . "..., Site: {$site_url}, Feature: {$feature_key}, Version: {$plugin_version}");

            // --- 2. Validate Essential Input ---
            if (empty($license_key) || empty($site_url) || empty($feature_key)) {
                error_log("Orunk License API Validation Error: Missing required parameters.");
                return new WP_REST_Response(['status' => 'error', 'code' => 'missing_parameters', 'message' => __('Missing required parameters (license_key, site_url, feature_key).', 'orunk-users')], 400);
            }
            if (filter_var($site_url, FILTER_VALIDATE_URL) === false) {
                error_log("Orunk License API Validation Error: Invalid site_url format '{$site_url}'.");
                return new WP_REST_Response(['status' => 'error', 'code' => 'invalid_site_url', 'message' => __('Invalid site URL format.', 'orunk-users')], 400);
            }

            // --- 3. Fetch Purchase & Plan Details using the Key ---
            // Assumes get_purchase_by_api_key was updated in Phase 1 to join plans/products
            $purchase = $this->db->get_purchase_by_api_key($license_key);

            // --- 4. Perform Basic Validation Checks on the Purchase Record ---
            if (!$purchase) {
                error_log("Orunk License API Validation Error: License key '{$license_key}' not found.");
                return new WP_REST_Response(['status' => 'invalid', 'code' => 'license_not_found', 'message' => __('License key not found or invalid.', 'orunk-users')], 404);
            }

            // Check if the Feature Requires Licensing (using the flag added in Phase 1)
            if (!isset($purchase['requires_license']) || $purchase['requires_license'] != 1) {
                 error_log("Orunk License API Validation Warning: Key '{$license_key}' used for feature '{$purchase['product_feature_key']}' which does not require licensing according to DB flag.");
                 // Decide how to handle this: return valid or an error? Let's return valid but maybe log extensively.
                 // return new WP_REST_Response(['status' => 'not_required', 'code' => 'license_not_required', 'message' => __('This feature does not require license activation.', 'orunk-users')], 200);
                 // OR just proceed, assuming the flag might be wrong or the client plugin is checking anyway. Let's proceed for now.
            }


            // Check Feature Match
            if (!isset($purchase['product_feature_key']) || $purchase['product_feature_key'] !== $feature_key) {
                error_log("Orunk License API Validation Error: Key '{$license_key}' is for feature '{$purchase['product_feature_key']}', requested feature was '{$feature_key}'.");
                return new WP_REST_Response(['status' => 'invalid_product', 'code' => 'feature_mismatch', 'message' => __('License key is not valid for this product.', 'orunk-users')], 403);
            }

            // Check Status
            if ($purchase['status'] !== 'active') {
                error_log("Orunk License API Validation Error: Key '{$license_key}' status is '{$purchase['status']}'.");
                return new WP_REST_Response(['status' => 'inactive', 'code' => 'license_inactive', 'message' => __('License is not active.', 'orunk-users'), 'purchase_status' => $purchase['status']], 403);
            }

            // Check Expiry
            $expires = null; // Will hold 'Y-m-d' formatted expiry date if applicable
            if (!empty($purchase['expiry_date'])) {
                $expiry_timestamp = strtotime($purchase['expiry_date']);
                $current_timestamp = current_time('timestamp', 1); // Use WordPress GMT time
                $expires = gmdate('Y-m-d', $expiry_timestamp); // Format expiry date for response

                if ($expiry_timestamp < $current_timestamp) {
                    error_log("Orunk License API Validation Error: Key '{$license_key}' expired on '{$purchase['expiry_date']}'.");
                    // Consider updating the purchase status to 'expired' here if desired (beware of race conditions)
                    return new WP_REST_Response(['status' => 'expired', 'code' => 'license_expired', 'expires' => $expires, 'message' => __('License has expired.', 'orunk-users')], 403);
                }
            }

            // --- 5. Activation Limit Logic ---
            $purchase_id = absint($purchase['id']);
            // Determine the effective activation limit
            $effective_limit = null; // Assume NULL means unlimited
            if (isset($purchase['override_activation_limit']) && is_numeric($purchase['override_activation_limit']) && $purchase['override_activation_limit'] > 0) {
                $effective_limit = intval($purchase['override_activation_limit']);
                error_log("Orunk License API: Using override limit ({$effective_limit}) for Purchase ID {$purchase_id}.");
            } elseif (isset($purchase['activation_limit']) && is_numeric($purchase['activation_limit']) && $purchase['activation_limit'] > 0) {
                // Use plan limit if override not set or invalid
                $effective_limit = intval($purchase['activation_limit']);
            }
            // If limit is still NULL or <= 0 after checks, it's considered unlimited.

            // Check if this site is already active
            $is_already_active = $this->db->is_site_active($license_key, $site_url);

            if ($is_already_active) {
                // Site is already active, validation is successful.
                error_log("Orunk License API: Site '{$site_url}' already active for key '{$license_key}'. Validation success.");

                // Optional: Update last_checked_at timestamp
                // $activation_id = $this->db->find_activation_id($license_key, $site_url); // Requires this DB method
                // if ($activation_id) { $this->db->update_activation_checkin($activation_id); }

                return new WP_REST_Response([
                    'status'        => 'valid',
                    'code'          => 'already_active',
                    'message'       => __('License is valid and already active on this site.', 'orunk-users'),
                    'expires'       => $expires, // Send expiry date if applicable
                    'productId'     => $feature_key, // Confirm the product ID
                    'limit'         => $effective_limit, // Let client know the limit (null if unlimited)
                    'activations'   => $this->db->get_active_activation_count($license_key) // Send current count
                ], 200);

            } else {
                // Site is not currently active, check if a slot is available.
                $current_activation_count = $this->db->get_active_activation_count($license_key);

                // Check if unlimited (limit is NULL) OR if count is less than limit
                if ($effective_limit === null || $current_activation_count < $effective_limit) {
                    // Activation allowed! Add record to the DB.
                    error_log("Orunk License API: Activation allowed for site '{$site_url}' on key '{$license_key}'. Limit: " . ($effective_limit ?? 'Unlimited') . ", Count: {$current_activation_count}. Adding activation record.");
                    $add_result = $this->db->add_activation(
                        $purchase_id,
                        $license_key,
                        $site_url,
                        $activation_ip,
                        $plugin_version
                    );

                    // Check result of adding activation
                    if (is_wp_error($add_result)) {
                        error_log("Orunk License API Validation Error: Failed to add activation record. WP_Error: " . $add_result->get_error_message());
                        return new WP_REST_Response(['status' => 'error', 'code' => 'activation_db_error', 'message' => __('Failed to record activation (Error: '. $add_result->get_error_code() .').', 'orunk-users')], 500);
                    } elseif ($add_result === false) {
                         error_log("Orunk License API Validation Error: Failed to add activation record (DB insert failed).");
                         return new WP_REST_Response(['status' => 'error', 'code' => 'activation_db_error', 'message' => __('Failed to record activation (DB Error).', 'orunk-users')], 500);
                    }

                    // Activation successful
                    $new_activation_id = $add_result;
                    return new WP_REST_Response([
                        'status'        => 'valid',
                        'code'          => 'activated',
                        'message'       => __('License activated successfully on this site.', 'orunk-users'),
                        'expires'       => $expires,
                        'productId'     => $feature_key,
                        'limit'         => $effective_limit,
                        'activations'   => $current_activation_count + 1, // Send updated count
                        'activation_id' => $new_activation_id // Return the new activation ID
                    ], 200);
                } else {
                    // Activation limit reached
                    error_log("Orunk License API Validation Error: Activation limit reached for key '{$license_key}'. Limit: {$effective_limit}, Count: {$current_activation_count}. Denying activation for '{$site_url}'.");
                    return new WP_REST_Response([
                        'status'        => 'limit_reached',
                        'code'          => 'activation_limit_reached',
                        'message'       => __('Activation limit reached for this license key.', 'orunk-users'),
                        'limit'         => $effective_limit,
                        'count'         => $current_activation_count
                    ], 403); // Use 403 Forbidden or 429 Too Many Requests? 403 seems appropriate.
                }
            }
        } // End handle_dynamic_license_validation


        /**
         * Handles dynamic license deactivation requests.
         * Callback for the '/orunk/v1/license/deactivate' REST route.
         *
         * IMPORTANT: Requires robust permission checks implemented in the
         * REST route definition (`permission_callback`) to ensure only the license
         * owner or an admin can perform this action.
         *
         * Expects 'activation_id' OR ('license_key' AND 'site_url') in request.
         * Using 'activation_id' is preferred if available.
         *
         * @param WP_REST_Request $request The incoming request object.
         * @return WP_REST_Response Response object.
         */
        public function handle_dynamic_license_deactivation(WP_REST_Request $request) {
            if (!$this->db) {
                error_log('Orunk License API Deactivation Error: DB handler not available.');
                return new WP_REST_Response(['status' => 'error', 'code' => 'server_misconfiguration', 'message' => __('Server configuration error.', 'orunk-users')], 500);
            }

            // Parameters
            $activation_id = absint($request->get_param('activation_id'));
            $license_key   = sanitize_text_field($request->get_param('license_key'));
            $site_url      = esc_url_raw($request->get_param('site_url'));

            // Permission Check Placeholder - This MUST be implemented securely in the route registration
            // For example, check if the current user owns the license or has 'manage_options' cap.
            // $user_id = get_current_user_id();
            // if (! $this->check_deactivation_permission($user_id, $activation_id, $license_key, $site_url)) {
            //     return new WP_REST_Response(['status' => 'error', 'code' => 'permission_denied', 'message' => __('You do not have permission to deactivate this license.', 'orunk-users')], 403);
            // }
            error_log("Orunk License API: Deactivation request received. Activation ID: {$activation_id}, Key: " . substr($license_key, 0, 5) . "..., Site: {$site_url}");


            // --- Find the Activation ID if not provided ---
            if ($activation_id <= 0) {
                if (!empty($license_key) && !empty($site_url)) {
                     error_log("Orunk License API Deactivation: Activation ID not provided, attempting lookup via key and URL.");
                     // We need a DB method to find the activation ID based on key and URL
                     // $activation_id = $this->db->find_activation_id_by_key_url($license_key, $site_url); // Example method needed in class-orunk-db.php
                     // Placeholder: Assume lookup failed if not provided initially
                     // Remove the lines below once find_activation_id_by_key_url is implemented
                      error_log("Orunk License API Deactivation Error: Lookup by key/URL not implemented. Please provide activation_id.");
                     return new WP_REST_Response(['status' => 'error', 'code' => 'missing_parameter', 'message' => __('Missing activation ID (Lookup by key/URL not implemented).', 'orunk-users')], 400);
                } else {
                     error_log("Orunk License API Deactivation Error: Missing required parameter (activation_id or license_key+site_url).");
                     return new WP_REST_Response(['status' => 'error', 'code' => 'missing_parameter', 'message' => __('Missing required parameter (activation_id or license_key+site_url).', 'orunk-users')], 400);
                }
            }

            // --- Perform Deactivation ---
            $deactivated = $this->db->deactivate_activation($activation_id);

            if (is_wp_error($deactivated)) {
                 error_log("Orunk License API Deactivation Error: WP_Error - " . $deactivated->get_error_message());
                 return new WP_REST_Response(['status' => 'error', 'code' => $deactivated->get_error_code(), 'message' => $deactivated->get_error_message()], 500);
            } elseif ($deactivated === false) {
                 error_log("Orunk License API Deactivation Error: DB update failed for activation ID {$activation_id}.");
                 return new WP_REST_Response(['status' => 'error', 'code' => 'db_error', 'message' => __('Failed to deactivate license.', 'orunk-users')], 500);
            } elseif ($deactivated === 0) { // Method returns rows affected (0 means not found or already inactive)
                error_log("Orunk License API Deactivation Info: Activation ID {$activation_id} not found or already inactive.");
                // Technically successful from client's perspective if already inactive
                return new WP_REST_Response(['status' => 'success', 'code' => 'already_inactive', 'message' => __('License activation not found or already inactive.', 'orunk-users')], 200);
            } else {
                 error_log("Orunk License API: Successfully deactivated activation ID {$activation_id}.");
                 return new WP_REST_Response(['status' => 'success', 'code' => 'deactivated', 'message' => __('License deactivated successfully.', 'orunk-users')], 200);
            }
        } // End handle_dynamic_license_deactivation


        /**
         * Placeholder for permission checking logic for deactivation.
         * Needs to be implemented securely based on how deactivation is triggered.
         *
         * @param int $user_id Current WP User ID performing the action.
         * @param int $activation_id ID of the activation record to deactivate.
         * @param string|null $license_key License key (if used for lookup).
         * @param string|null $site_url Site URL (if used for lookup).
         * @return bool True if user has permission, false otherwise.
         */
        private function check_deactivation_permission($user_id, $activation_id = 0, $license_key = null, $site_url = null) {
            if (current_user_can('manage_options')) {
                return true; // Admins can always deactivate
            }
            if (!$user_id || $user_id <= 0) {
                return false; // Must be a logged-in user
            }

            // Find the purchase associated with the activation
            $target_activation_id = $activation_id;
            if ($target_activation_id <= 0 && $license_key && $site_url) {
                // $target_activation_id = $this->db->find_activation_id_by_key_url($license_key, $site_url); // Need this DB method
                // Placeholder - requires the DB method
                return false;
            }

            if ($target_activation_id > 0) {
                 $owner_user_id = $this->db->get_activation_owner($target_activation_id); // Need this DB method
                 if ($owner_user_id && $owner_user_id == $user_id) {
                     return true; // User owns the purchase linked to this activation
                 }
            }

            return false; // Default deny
        }


    } // End Class Orunk_License_Api_Handler

} // End if class_exists check