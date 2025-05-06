<?php
/**
 * Orunk Users Access Class
 *
 * Provides methods to check if a user has active access to a specific feature
 * based on their purchases.
 *
 * @package OrunkUsers
 * @version 1.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Custom_Orunk_Access {

    /** @var Custom_Orunk_DB Database handler instance */
    private $db;

    /**
     * Constructor. Initializes the database handler.
     */
    public function __construct() {
        // Ensure DB class is loaded if not already
        if (!class_exists('Custom_Orunk_DB')) {
            require_once ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
        }
        $this->db = new Custom_Orunk_DB();
    }

    /**
     * Initialize access control actions/filters if needed.
     * (Currently not used, checks are performed directly where needed).
     */
    public function init() {
        // No initialization actions needed for now.
    }

    /**
     * Checks if a user has active access to a specific feature based on their API key.
     * This is primarily used by external services (like the BIN API plugin) that authenticate via API key.
     *
     * @param string $api_key The API key to check.
     * @param string $feature_key The unique feature key required (e.g., 'bin_api').
     * @return bool True if access is granted, false otherwise.
     */
    public function has_feature_access($api_key, $feature_key) {
        // 1. Basic validation
        if (empty($api_key) || empty($feature_key)) {
            return false;
        }

        // 2. Get purchase details associated with the API key
        $purchase = $this->db->get_purchase_by_api_key($api_key);

        // 3. Validate the purchase record
        if (!$purchase) {
            // error_log("Orunk Access Check: API Key '$api_key' not found.");
            return false; // API key doesn't exist
        }

        // 4. Check if the purchase status is 'active'
        if ($purchase['status'] !== 'active') {
             // error_log("Orunk Access Check: API Key '$api_key' status is '{$purchase['status']}'.");
            return false; // Purchase is not active (pending, expired, etc.)
        }

        // 5. Check if the purchase has expired
        // Compare current GMT time with the stored GMT expiry date
        if (empty($purchase['expiry_date']) || current_time('timestamp', 1) > strtotime($purchase['expiry_date'])) {
             // error_log("Orunk Access Check: API Key '$api_key' expired on '{$purchase['expiry_date']}'.");
            // Optional: Could trigger a status update to 'expired' here if desired
            return false; // Purchase has expired
        }

        // 6. Check if the purchase is for the correct feature
        // Assumes 'product_feature_key' is correctly retrieved by get_purchase_by_api_key
        if (!isset($purchase['product_feature_key']) || $purchase['product_feature_key'] !== $feature_key) {
            // error_log("Orunk Access Check: API Key '$api_key' is for feature '{$purchase['product_feature_key']}', not required feature '$feature_key'.");
            return false; // API key is valid but for a different feature
        }

        // 7. If all checks pass, grant access
        return true;
    }

    /**
     * Checks if a logged-in user has active access to a specific feature based on their User ID.
     * This is used internally within the website (e.g., for ad removal, showing dashboard content).
     *
     * @param int $user_id The WordPress User ID.
     * @param string $feature_key The unique feature key required (e.g., 'ad_removal').
     * @return bool True if access is granted, false otherwise.
     */
    public function has_feature_access_by_user_id($user_id, $feature_key) {
        // 1. Basic validation
        if (empty($user_id) || !is_numeric($user_id) || empty($feature_key)) {
            return false;
        }
        $user_id = absint($user_id);

        // 2. Use the optimized DB query to find an active plan for this feature
        $active_purchase = $this->db->get_user_active_plan_for_feature($user_id, $feature_key);

        // 3. If an active purchase for the feature exists, access is granted.
        if ($active_purchase) {
            return true;
        }

        // 4. If no active purchase found for that specific feature
        return false;
    }

} // End Class Custom_Orunk_Access
