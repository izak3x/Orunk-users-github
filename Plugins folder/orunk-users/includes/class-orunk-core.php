<?php
/**
 * Orunk Users Core Class (Simplified)
 *
 * Handles initialization, loading payment gateways, and acts as a wrapper
 * for retrieving common plugin data via the DB handler.
 * Core logic for purchases, user actions, and API keys has been moved to dedicated manager classes.
 *
 * @package OrunkUsers\Includes
 * @version 2.0.0 (Phase 4 Refactor - Simplified Core)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure DB class is available if not already loaded
if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
    define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path(__FILE__) . '../');
    error_log('Warning: ORUNK_USERS_PLUGIN_DIR was not defined. Using fallback: ' . ORUNK_USERS_PLUGIN_DIR);
}
if (!class_exists('Custom_Orunk_DB')) {
    $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
    if (file_exists($db_path)) {
        require_once $db_path;
    } else {
         error_log("Orunk Core CRITICAL ERROR: Cannot load Custom_Orunk_DB. Path checked: " . $db_path);
         // Consider preventing plugin execution if DB is missing
    }
}

class Custom_Orunk_Core {

    /** @var Custom_Orunk_DB Database handler instance */
    private $db;

    /**
     * Constructor. Initializes the database handler.
     */
    public function __construct() {
        if (class_exists('Custom_Orunk_DB')) {
            $this->db = new Custom_Orunk_DB();
        } else {
            error_log('Orunk Core Error: Custom_Orunk_DB class not found during instantiation. Check plugin load order.');
            $this->db = null; // Ensure $this->db is null if class missing
        }
    }

    /**
     * Initialize core actions/filters if needed in the future.
     * This might be used to instantiate manager classes if Core acts as a central registry.
     */
    public function init() {
         // Example: Load manager classes if needed globally (though often they are loaded where used)
         // require_once ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-purchase-manager.php';
         // require_once ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-api-key-manager.php';
         // require_once ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-user-actions.php';
    }

    // --- METHODS REMOVED in Phases 1-3 ---
    // register_user() -> Moved to Orunk_User_Actions
    // initiate_purchase() -> Moved to Orunk_Purchase_Manager
    // activate_purchase() -> Moved to Orunk_Purchase_Manager
    // record_purchase_failure() -> Moved to Orunk_Purchase_Manager
    // approve_manual_switch() -> Moved to Orunk_Purchase_Manager
    // generate_unique_api_key() -> Moved to Orunk_Api_Key_Manager

    /**
     * Retrieves product features along with their active plans.
     * Acts as a wrapper for the DB class method.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     * @return array Array of features, each with an array of plan details.
     */
     public function get_product_features_with_plans() {
        global $wpdb;
        $products_table = $wpdb->prefix . 'orunk_products';
        $plans_table = $wpdb->prefix . 'orunk_product_plans';

        // Check if DB handler is available
        if (!$this->db) {
            error_log('Orunk Core Error (get_product_features_with_plans): DB handler missing.');
            return array();
        }

        // Basic query to get features (ensure table exists maybe?)
        $features = $wpdb->get_results("SELECT * FROM {$products_table} ORDER BY product_name ASC", ARRAY_A);
        if ($features === false) {
             error_log('Orunk Core DB Error (get_product_features_with_plans): Failed to query features table. Error: ' . $wpdb->last_error);
             return array();
        }
        if (empty($features)) {
            return array();
        }

        // Fetch active plans for each feature
        foreach ($features as $key => $feature) {
             if (!empty($feature['feature'])) {
                 $plans = $wpdb->get_results(
                     $wpdb->prepare(
                         "SELECT * FROM {$plans_table} WHERE product_feature_key = %s AND is_active = 1 ORDER BY price ASC",
                         $feature['feature']
                     ),
                     ARRAY_A
                 );
                  if ($plans === false) {
                     error_log("Orunk Core DB Error (get_product_features_with_plans): Failed to query plans for feature '{$feature['feature']}'. Error: " . $wpdb->last_error);
                     $features[$key]['plans'] = array(); // Assign empty array on error
                 } else {
                     $features[$key]['plans'] = $plans;
                 }
             } else {
                 $features[$key]['plans'] = array(); // No feature key to query by
             }
        }
        return $features;
     }

      /**
       * Retrieves all registered and *enabled* payment gateways.
       * Loads gateway classes and instantiates them.
       *
       * @return array Associative array of enabled gateway instances [gateway_id => gateway_object].
       */
     public function get_available_payment_gateways() {
         // Ensure base class is loaded first
         if (!class_exists('Orunk_Payment_Gateway')) {
             $gateway_base_path = ORUNK_USERS_PLUGIN_DIR . 'includes/abstract-orunk-payment-gateway.php';
             if (file_exists($gateway_base_path)) {
                 require_once $gateway_base_path;
             } else {
                 error_log("Orunk Core Error (get_available_payment_gateways): Abstract Payment Gateway class file missing.");
                 return []; // Cannot proceed without the base class
             }
         }

         // Include all gateway files dynamically
         $gateway_files = glob(ORUNK_USERS_PLUGIN_DIR . 'includes/gateways/*.php');
         if ($gateway_files) {
             foreach ($gateway_files as $gateway_file) {
                 if (file_exists($gateway_file)) {
                     require_once $gateway_file;
                 }
             }
         }

         // Filterable list allows adding gateways externally
         $gateway_classes = apply_filters('orunk_payment_gateways', array(
             'Orunk_Gateway_Bank',
             'Orunk_Gateway_Stripe',
             'Orunk_Gateway_Paypal'
             // Add other gateway class names here if created
         ));

         $available_gateways = array();
         foreach ($gateway_classes as $class) {
             if (class_exists($class)) {
                 try {
                      $gateway = new $class();
                      // Check if the gateway instance has an ID and is enabled
                      if (isset($gateway->id) && isset($gateway->enabled) && $gateway->enabled === 'yes') {
                          $available_gateways[$gateway->id] = $gateway;
                      }
                 } catch (Exception $e) {
                     error_log("Orunk Core Error (get_available_payment_gateways): Error instantiating gateway {$class}: " . $e->getMessage());
                 }
             } else {
                 error_log("Orunk Core Warning (get_available_payment_gateways): Gateway class {$class} not found after include attempts.");
             }
         }

         // Optional: Sort gateways for consistent display order
         uasort($available_gateways, function($a, $b) {
             return strcmp($a->method_title ?? '', $b->method_title ?? '');
         });

         return $available_gateways;
     }

     /**
      * Public wrapper method to get the active plan for a user and feature.
      * Delegates to the DB class.
      *
      * @param int $user_id WordPress User ID.
      * @param string $feature_key The unique feature key.
      * @return array|null Active purchase details or null.
      */
     public function get_user_active_plan($user_id, $feature_key) {
        if (!$this->db) {
            error_log('Orunk Core Error (get_user_active_plan): DB handler missing.');
            return null;
        }
        // Delegate the call to the DB class method
        return $this->db->get_user_active_plan_for_feature($user_id, $feature_key);
     }

     /**
      * Public wrapper method to get user purchases.
      * Delegates to the DB class.
      *
      * @param int $user_id The WordPress User ID.
      * @param string|null $status Optional status to filter by.
      * @return array List of purchase records.
      */
     public function get_user_purchases($user_id, $status = null) {
         if (!$this->db) {
             error_log('Orunk Core Error (get_user_purchases): DB handler missing.');
             return array();
         }
         // Basic validation before calling DB
         if (empty($user_id) || !is_numeric($user_id)) {
             return array();
         }
         // Delegate the call to the DB class method
         return $this->db->get_user_purchases(absint($user_id), $status);
     }

     /**
      * Public wrapper method to get plan details by ID.
      * Delegates to the DB class.
      *
      * @param int $plan_id The ID of the plan.
      * @return array|null Plan details or null.
      */
     public function get_plan_details($plan_id) {
        if (!$this->db) {
            error_log('Orunk Core Error (get_plan_details): DB handler missing.');
            return null;
        }
        // Basic validation before calling DB
        if (empty($plan_id) || !is_numeric($plan_id)) {
             return null;
         }
        // Delegate the call to the DB class method
        return $this->db->get_plan_details(absint($plan_id));
     }

     /**
      * Helper function to get client IP for logging/records.
      * Moved back here from Purchase Manager and made static for easier access.
      *
      * @return string|null Client IP address or null.
      */
     public static function get_client_ip_for_record() {
         $ip_keys = [
             'HTTP_CLIENT_IP',        // Standard header proxy users may set
             'HTTP_X_FORWARDED_FOR',  // Most common header for identifying originating IP behind proxy/load balancer
             'HTTP_X_FORWARDED',      // Less common but sometimes used
             'HTTP_FORWARDED_FOR',    // Variants
             'HTTP_FORWARDED',        // RFC 7239 standard header
             'REMOTE_ADDR'            // Direct connection IP (fallback)
         ];

         foreach ($ip_keys as $key) {
             if (isset($_SERVER[$key])) {
                 // Headers like X-Forwarded-For can contain multiple IPs (client, proxy1, proxy2).
                 // The first one is typically the original client IP.
                 $ip_list = explode(',', $_SERVER[$key]);
                 $ip = trim(reset($ip_list)); // Get the first IP in the list

                 // Validate if it's a valid IP address (IPv4 or IPv6)
                 if (filter_var($ip, FILTER_VALIDATE_IP)) {
                     return sanitize_text_field($ip); // Return the first valid IP found
                 }
             }
         }

         // Default to REMOTE_ADDR if no valid IP found in headers
         return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : null;
     }


} // End Class Custom_Orunk_Core (Simplified)