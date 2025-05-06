<?php
/**
 * Orunk Users - Admin Feature Category AJAX Handlers
 *
 * Handles AJAX requests related to managing feature categories
 * in the admin interface.
 *
 * @package OrunkUsers\Admin\AJAX
 * @version 1.0.1 - Removed duplicate helper function definition.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure the DB class is loaded as it's used by these handlers
// This check adds robustness in case this file is somehow loaded standalone.
if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
    define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__, 3))); // Go up 3 levels from includes/admin/ajax/
    error_log('Warning: ORUNK_USERS_PLUGIN_DIR was not defined in category-handlers. Using fallback: ' . ORUNK_USERS_PLUGIN_DIR);
}
if (!class_exists('Custom_Orunk_DB')) {
    $db_path = ORUNK_USERS_PLUGIN_DIR . 'includes/class-orunk-db.php';
    if (file_exists($db_path)) {
        require_once $db_path;
    } else {
        error_log("Orunk AJAX Category FATAL: Cannot load Custom_Orunk_DB class. Path: {$db_path}");
        // If DB class is missing, these handlers will fail.
    }
}

// Note: The helper function orunk_admin_check_ajax_permissions() is NOT defined here.
// It should be defined ONCE in 'includes/admin/ajax/admin-ajax-helpers.php'
// and included by the main plugin file before this file is included.


/**
 * AJAX Handler: Get all Feature Categories for Admin Interface.
 * Handles the 'orunk_admin_get_categories' action.
 */
function handle_admin_get_categories() {
    // Check permissions - Requires helper function defined elsewhere
    if (!function_exists('orunk_admin_check_ajax_permissions')) {
         wp_send_json_error(['message' => 'Admin helper function missing.'], 500);
    }
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce');

    // Ensure DB class is loaded before using it
    if (!class_exists('Custom_Orunk_DB')) {
        wp_send_json_error(['message' => 'Database class missing. Cannot load categories.'], 500);
    }

    try {
        $orunk_db = new Custom_Orunk_DB();

        // Check if the method exists before calling it
        if (!method_exists($orunk_db, 'get_all_feature_categories')) {
            throw new Exception('Database method get_all_feature_categories not found.');
        }

        $categories = $orunk_db->get_all_feature_categories();

        // Check if the DB method returned false (indicating a query error)
        if ($categories === false) {
            global $wpdb; // Access wpdb for error info
            throw new Exception('Database query failed fetching categories. Error: ' . $wpdb->last_error);
        }

        // Send successful response with the categories array (or empty array)
        wp_send_json_success(['categories' => is_array($categories) ? $categories : []]);

    } catch (Exception $e) {
        error_log('Orunk Admin AJAX Error (get_categories): ' . $e->getMessage());
        wp_send_json_error(['message' => 'Error loading categories. Please check server logs.'], 500);
    }
    // wp_die(); implicit
}

/**
 * AJAX Handler: Save (Add or Update) a Feature Category.
 * Handles the 'orunk_admin_save_category' action.
 */
function handle_admin_save_category() {
    // Check permissions - Requires helper function defined elsewhere
     if (!function_exists('orunk_admin_check_ajax_permissions')) {
         wp_send_json_error(['message' => 'Admin helper function missing.'], 500);
    }
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce');

    // Ensure DB class is loaded
    if (!class_exists('Custom_Orunk_DB')) {
        wp_send_json_error(['message' => 'Database class missing. Cannot save category.'], 500);
    }
    $orunk_db = new Custom_Orunk_DB();
    global $wpdb; // Needed for fetching the saved category at the end

    // Sanitize POST data
    $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
    $name = isset($_POST['category_name']) ? sanitize_text_field(wp_unslash($_POST['category_name'])) : '';
    $slug = isset($_POST['category_slug']) ? sanitize_key(wp_unslash($_POST['category_slug'])) : '';

    // Validate required fields
    if (empty($name) || empty($slug)) {
        wp_send_json_error(['message' => 'Category Name and Slug are required.'], 400);
    }

    // Validate slug format (lowercase letters, numbers, hyphens)
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        wp_send_json_error(['message' => 'Slug can only contain lowercase letters, numbers, and hyphens.'], 400);
    }

    $result = null;
    try {
        // Call the appropriate DB method based on whether it's an add or update
        if ($category_id > 0) {
            if (!method_exists($orunk_db, 'update_feature_category')) throw new Exception('DB update_feature_category method missing.');
            $result = $orunk_db->update_feature_category($category_id, $name, $slug);
        } else {
            if (!method_exists($orunk_db, 'add_feature_category')) throw new Exception('DB add_feature_category method missing.');
            $result = $orunk_db->add_feature_category($name, $slug);
        }

        // --- Handle the result ---
        if (is_wp_error($result)) {
            // If the DB method returned a WP_Error (e.g., slug exists)
            wp_send_json_error(
                ['message' => $result->get_error_message()],
                ($result->get_error_code() === 'slug_exists' ? 409 : 500) // Use 409 Conflict for existing slug
            );
        } elseif ($result === false) {
            // If the DB method returned false (generic DB error)
             error_log("Orunk Admin AJAX Error saving category (ID: {$category_id}): DB method returned false. Last DB error: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Database error saving category.'], 500);
        } else {
            // Success! $result is either true (for update) or the new ID (for insert)
            $saved_id = ($category_id > 0) ? $category_id : $result; // Get the ID of the saved category

            // Fetch the saved category data to return to the frontend
            $categories_table = $wpdb->prefix . 'orunk_feature_categories'; // Define table name
            $saved_category = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM `{$categories_table}` WHERE id = %d", $saved_id),
                ARRAY_A
            );

            wp_send_json_success([
                'message' => 'Category saved successfully.',
                'category' => $saved_category // Send back the saved data
            ]);
        }

    } catch (Exception $e) {
         error_log('AJAX Exception in handle_admin_save_category: ' . $e->getMessage());
         wp_send_json_error(['message' => 'Server error saving category.'], 500);
    }
    // wp_die(); implicit
}


/**
 * AJAX Handler: Delete a Feature Category.
 * Handles the 'orunk_admin_delete_category' action.
 */
function handle_admin_delete_category() {
    // Check permissions - Requires helper function defined elsewhere
     if (!function_exists('orunk_admin_check_ajax_permissions')) {
         wp_send_json_error(['message' => 'Admin helper function missing.'], 500);
    }
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce');

    // Ensure DB class is loaded
    if (!class_exists('Custom_Orunk_DB')) {
        wp_send_json_error(['message' => 'Database class missing. Cannot delete category.'], 500);
    }
    $orunk_db = new Custom_Orunk_DB();
    global $wpdb; // For error logging

    // Validate Category ID input
    $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
    if ($category_id <= 0) {
        wp_send_json_error(['message' => 'Invalid Category ID.'], 400);
    }

    try {
        // Check if the delete method exists
         if (!method_exists($orunk_db, 'delete_feature_category')) {
            throw new Exception('DB delete_feature_category method missing.');
        }

        $result = $orunk_db->delete_feature_category($category_id);

        // Handle the result from the DB method
        if (is_wp_error($result)) {
             wp_send_json_error(['message' => $result->get_error_message()], 500); // Assume 500 for DB errors from WP_Error
        } elseif ($result === false) {
             // DB method returned false, indicating a direct DB error
              error_log("Orunk Admin AJAX Error deleting category {$category_id}: DB method returned false. Last DB error: " . $wpdb->last_error);
             wp_send_json_error(['message' => 'Database error deleting category.'], 500);
        } elseif ($result === 0) {
             // Delete method returned 0 rows affected (not found)
             wp_send_json_error(['message' => 'Category not found or already deleted.'], 404); // Use 404 Not Found
        } else {
             // Successfully deleted (result > 0)
             wp_send_json_success([
                'message' => 'Category deleted successfully.',
                'deleted_category_id' => $category_id
             ]);
        }

    } catch (Exception $e) {
         error_log('AJAX Exception in handle_admin_delete_category: ' . $e->getMessage());
         wp_send_json_error(['message' => 'Server error deleting category.'], 500);
    }
     // wp_die(); implicit
}

?>