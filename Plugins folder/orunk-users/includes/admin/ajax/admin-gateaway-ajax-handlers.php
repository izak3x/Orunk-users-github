<?php
/**
 * Orunk Users - Admin Payment Gateway AJAX Handlers
 *
 * Handles AJAX requests related to managing payment gateways
 * in the admin interface.
 *
 * @package OrunkUsers\Admin\AJAX
 * @version 1.0.1 - Removed duplicate helper function definition.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure the Abstract Gateway class is loaded, as gateway classes extend it.
// This check adds robustness in case this file is somehow loaded standalone.
if (!defined('ORUNK_USERS_PLUGIN_DIR')) {
    define('ORUNK_USERS_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__, 3))); // Go up 3 levels from includes/admin/ajax/
    error_log('Warning: ORUNK_USERS_PLUGIN_DIR was not defined in gateway-handlers. Using fallback: ' . ORUNK_USERS_PLUGIN_DIR);
}
if (!class_exists('Orunk_Payment_Gateway')) {
    $abstract_path = ORUNK_USERS_PLUGIN_DIR . 'includes/abstract-orunk-payment-gateway.php';
    if (file_exists($abstract_path)) {
        require_once $abstract_path;
    } else {
        // If the abstract class is missing, the gateway handlers likely won't work.
        error_log("Orunk AJAX Gateway FATAL: Cannot load Abstract Payment Gateway class. Path: {$abstract_path}");
    }
}

// Note: The helper function orunk_admin_check_ajax_permissions() is NOT defined here.
// It should be defined ONCE in 'includes/admin/ajax/admin-ajax-helpers.php'
// and included by the main plugin file before this file is included.

/**
 * AJAX Handler: Get available payment gateways and their settings/form fields.
 * Handles the 'orunk_admin_get_gateways' action.
 */
function handle_admin_get_gateways() {
    // Check permissions - Requires helper function defined elsewhere
    if (!function_exists('orunk_admin_check_ajax_permissions')) {
         wp_send_json_error(['message' => 'Admin helper function missing.'], 500);
    }
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce');

    $all_gateways_data = array();
    try {
        // Ensure the Abstract class is loaded before proceeding
        if (!class_exists('Orunk_Payment_Gateway')) {
             throw new Exception('Abstract Payment Gateway class is not loaded.');
        }

        // --- Dynamically Load Gateway Classes ---
        $gateway_files = glob(ORUNK_USERS_PLUGIN_DIR . 'includes/gateways/class-orunk-gateway-*.php');
        if ($gateway_files) {
            foreach ($gateway_files as $gateway_file) {
                if (file_exists($gateway_file)) {
                    require_once $gateway_file;
                }
            }
        } else {
            error_log("Orunk Admin AJAX Warning: No gateway files found in " . ORUNK_USERS_PLUGIN_DIR . 'includes/gateways/');
        }

        // Get the list of registered gateway class names via filter
        $gateway_classes = apply_filters('orunk_payment_gateways', [
            'Orunk_Gateway_Bank',
            'Orunk_Gateway_Stripe',
            'Orunk_Gateway_Paypal'
        ]);

        // Instantiate each gateway and collect its data
        foreach ($gateway_classes as $class) {
            if (class_exists($class)) {
                try {
                    $gateway = new $class();
                    if (isset($gateway->id)) {
                        $all_gateways_data[] = [
                            'id'          => $gateway->id,
                            'title'       => $gateway->method_title ?? $gateway->id,
                            'enabled'     => $gateway->enabled === 'yes',
                            'description' => $gateway->method_description ?? '',
                            'settings'    => $gateway->settings ?? [],
                            'form_fields' => $gateway->form_fields ?? []
                        ];
                    } else {
                         error_log("Orunk Admin AJAX Warning: Gateway class '{$class}' is missing an 'id' property.");
                    }
                } catch (Exception $e) {
                    error_log("Orunk Admin AJAX Error: Failed to instantiate gateway class '{$class}'. Error: " . $e->getMessage());
                }
            } else {
                error_log("Orunk Admin AJAX Error: Gateway class '{$class}' not found after attempting include.");
            }
        }

        // Sort gateways alphabetically by title
        usort($all_gateways_data, function($a, $b) {
            return strcmp($a['title'] ?? '', $b['title'] ?? '');
        });

        wp_send_json_success(['gateways' => $all_gateways_data]);

    } catch (Exception $e) {
        error_log('AJAX Exception in handle_admin_get_gateways: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Server error fetching payment gateways.'], 500);
    }
    // wp_die(); implicit
}


/**
 * AJAX Handler: Save settings for a specific payment gateway.
 * Handles the 'orunk_admin_save_gateway_settings' action.
 */
function handle_admin_save_gateway_settings() {
    // Check permissions - Requires helper function defined elsewhere
     if (!function_exists('orunk_admin_check_ajax_permissions')) {
         wp_send_json_error(['message' => 'Admin helper function missing.'], 500);
    }
    orunk_admin_check_ajax_permissions('orunk_admin_interface_nonce');

    // Get gateway ID and settings from POST data
    $gateway_id   = isset($_POST['gateway_id']) ? sanitize_key($_POST['gateway_id']) : '';
    $raw_settings = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : null;

    if (empty($gateway_id) || $raw_settings === null) {
        wp_send_json_error(['message' => __('Invalid gateway ID or settings data received.', 'orunk-users')], 400);
    }

    // --- Find and instantiate the correct gateway class ---
    $gateway_class = null;
    $gateway_classes = apply_filters('orunk_payment_gateways', [
        'Orunk_Gateway_Bank',
        'Orunk_Gateway_Stripe',
        'Orunk_Gateway_Paypal'
    ]);

    foreach ($gateway_classes as $class) {
        if (!class_exists($class)) {
             $class_file_slug = str_replace('_', '-', strtolower($class));
             $gateway_file = ORUNK_USERS_PLUGIN_DIR . 'includes/gateways/class-' . $class_file_slug . '.php';
             if (file_exists($gateway_file)) { require_once $gateway_file; }
             else { continue; }
        }
        if (class_exists($class)) {
             try {
                  $temp_gateway = new $class();
                  if (isset($temp_gateway->id) && $temp_gateway->id === $gateway_id) {
                      $gateway_class = $class;
                      break;
                  }
             } catch (Exception $e) { continue; }
        }
    }

    if (!$gateway_class) {
        error_log("Orunk Admin AJAX Error: Could not find gateway class for ID '{$gateway_id}'.");
        wp_send_json_error(['message' => __('Gateway class not found on server.', 'orunk-users')], 404);
    }

    try {
        $gateway = new $gateway_class();
        $validated_settings = [];

        if (!empty($gateway->form_fields) && is_array($gateway->form_fields)) {
            if (method_exists($gateway, 'validate_settings_fields')) {
                $input_for_validation = [];
                foreach ($gateway->form_fields as $key => $field) {
                    if (isset($raw_settings[$key])) {
                        $input_for_validation[$key] = $raw_settings[$key];
                    } elseif (($field['type'] ?? 'text') === 'checkbox') {
                        $input_for_validation[$key] = 'no';
                    }
                }
                $validated_settings = $gateway->validate_settings_fields($input_for_validation);
            } else {
                error_log("Orunk Admin AJAX Warning: Using basic sanitization for gateway '{$gateway_id}'.");
                foreach ($gateway->form_fields as $key => $field) {
                     $field_type = $field['type'] ?? 'text';
                     if (isset($raw_settings[$key])) {
                         $value = $raw_settings[$key];
                         if ($field_type === 'textarea') $validated_settings[$key] = sanitize_textarea_field($value);
                         elseif ($field_type === 'checkbox') $validated_settings[$key] = ($value === 'yes') ? 'yes' : 'no';
                         elseif ($field_type === 'select' && isset($field['options'])) $validated_settings[$key] = array_key_exists($value, $field['options']) ? sanitize_key($value) : ($field['default'] ?? '');
                         else $validated_settings[$key] = sanitize_text_field($value);
                     } elseif ($field_type === 'checkbox') {
                          $validated_settings[$key] = 'no';
                     } else {
                          $validated_settings[$key] = $field['default'] ?? '';
                     }
                }
            }
        } else {
             error_log("Orunk Admin AJAX Warning: No form_fields defined for gateway '{$gateway_id}'.");
             foreach ($raw_settings as $key => $value) {
                 $validated_settings[$key] = is_array($value) ? map_deep($value, 'sanitize_text_field') : sanitize_text_field($value);
             }
             if (!isset($validated_settings['enabled'])) { $validated_settings['enabled'] = 'no'; }
        }

        // --- Save the validated settings ---
        $option_name = 'orunk_gateway_' . $gateway_id . '_settings';
        $settings_updated = update_option($option_name, $validated_settings);

        if ($settings_updated) {
            wp_send_json_success(['message' => sprintf(__('%s settings saved successfully.', 'orunk-users'), esc_html($gateway->method_title))]);
        } else {
            $current_settings = get_option($option_name);
            if ($current_settings == $validated_settings) {
                wp_send_json_success(['message' => sprintf(__('%s settings unchanged.', 'orunk-users'), esc_html($gateway->method_title))]);
            } else {
                global $wpdb;
                error_log("Orunk Admin AJAX Error: update_option failed for '{$option_name}'. DB Error: " . $wpdb->last_error);
                wp_send_json_error(['message' => sprintf(__('Failed to save %s settings. Please check server logs.', 'orunk-users'), esc_html($gateway->method_title))], 500);
            }
        }

    } catch (Exception $e) {
        error_log("AJAX Exception in handle_admin_save_gateway_settings for {$gateway_id}: " . $e->getMessage());
        wp_send_json_error(['message' => 'Server error saving gateway settings.'], 500);
    }
    // wp_die(); implicit
}

?>