<?php
/**
 * Orunk Users Settings Admin Class
 *
 * Handles the admin interface for configuring Payment Gateways, General Settings,
 * and Checkout Field Visibility.
 *
 * @package OrunkUsers\Admin
 * @version 1.3.0 // Added Checkout Field Settings
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Custom_Orunk_Settings {
    public const BIN_API_KEY_OPTION = 'orunk_bin_api_key';

    /** @var array Holds instances of loaded gateway classes */
    private $gateways = array();

    /** @var string The option group name for general settings */
    private const GENERAL_SETTINGS_GROUP = 'orunk_general_settings_group';

    /** @var string The option name for checkout field settings */
    private const CHECKOUT_FIELDS_OPTION = 'orunk_checkout_fields_settings'; // <-- New constant for checkout fields

    /**
     * Initialize settings page actions and load gateways.
     */
    public function init() {
        // Load available gateways early to make them available
        $this->load_gateways();

        // Add the submenu page under "Orunk Users"
        add_action('admin_menu', array($this, 'add_settings_menu'));

        // Register settings *before* the page might try to save them
        add_action('admin_init', array($this, 'register_gateway_settings_conditionally'));
        add_action('admin_init', array($this, 'register_general_settings'));
    }

    /**
     * Load available payment gateway classes from the includes/gateways directory.
     * Instantiates each gateway and stores it in the $gateways property.
     * (Existing Function - Unchanged)
     */
    private function load_gateways() {
         // Ensure base class is loaded first
          if (!class_exists('Orunk_Payment_Gateway')) {
             // Ensure the constant ORUNK_USERS_PLUGIN_DIR is defined, otherwise fallback or error
             $plugin_dir = defined('ORUNK_USERS_PLUGIN_DIR') ? ORUNK_USERS_PLUGIN_DIR : trailingslashit(plugin_dir_path(__FILE__) . '../');
             $base_gateway_file = $plugin_dir . 'includes/abstract-orunk-payment-gateway.php';
             if (file_exists($base_gateway_file)) {
                 require_once $base_gateway_file;
             } else {
                  error_log('Orunk Users Error: Abstract Payment Gateway class file not found.');
                  return; // Cannot proceed without the base class
             }
          }

          $plugin_dir = defined('ORUNK_USERS_PLUGIN_DIR') ? ORUNK_USERS_PLUGIN_DIR : trailingslashit(plugin_dir_path(__FILE__) . '../');
          // Include gateway files dynamically
          $gateway_dir = $plugin_dir . 'includes/gateways/';
          $gateway_files = glob($gateway_dir . 'class-orunk-gateway-*.php');

          if ($gateway_files) {
              foreach ($gateway_files as $gateway_file) {
                  require_once $gateway_file;
              }
          }

         // Filterable list allows other plugins/themes to add gateways
         // The class names should match the included files.
         $gateway_classes = apply_filters('orunk_payment_gateways', array(
             'Orunk_Gateway_Bank',
             'Orunk_Gateway_Stripe',
             'Orunk_Gateway_Paypal'
             // Add other gateway class names here if created
         ));

         $loaded_gateways = array();
         foreach ($gateway_classes as $class) {
             if (class_exists($class)) {
                 $gateway = new $class();
                 // Use the gateway's unique ID as the key
                 if (isset($gateway->id)) {
                    $loaded_gateways[$gateway->id] = $gateway;
                 }
             }
         }

         // Optional: Sort gateways alphabetically by method title
         uasort($loaded_gateways, function($a, $b) {
             return strcmp($a->method_title ?? '', $b->method_title ?? '');
         });

         $this->gateways = $loaded_gateways;
    }

    /**
     * Add the submenu page for payment gateways under "Orunk Users".
     * (Existing Function - Unchanged)
     */
    public function add_settings_menu() {
        add_submenu_page(
            'orunk-users-manager',                   // Parent menu slug
            __('Settings', 'orunk-users'),           // Page title
            __('Settings', 'orunk-users'),           // Menu title
            'manage_options',                       // Capability required
            'orunk-users-settings',                 // Menu slug
            array($this, 'settings_page_html')      // Function to render the page HTML
        );
    }

    /**
     * Register settings using the WordPress Settings API *only for the specific gateway being saved*.
     * This function runs on admin_init.
     * (Existing Function - Unchanged)
     */
     public function register_gateway_settings_conditionally() {
         // Check if the current request is saving settings for one of our gateways
         // The 'option_page' hidden field tells us which settings group is being saved.
         $option_page = isset($_POST['option_page']) ? sanitize_key($_POST['option_page']) : '';

         // Only proceed if it's a gateway settings group being saved
         if (strpos($option_page, 'orunk_gateway_') === 0 && strpos($option_page, '_settings_group') !== false) {
              // Extract the gateway ID from the option_page value
              $gateway_id_to_save = str_replace(['orunk_gateway_', '_settings_group'], '', $option_page);

              // Check if this gateway ID exists in our loaded gateways
              if ($gateway_id_to_save && isset($this->gateways[$gateway_id_to_save])) {
                   $gateway = $this->gateways[$gateway_id_to_save];

                   // Register the setting for this specific gateway
                   register_setting(
                        $option_page, // The settings group name (must match hidden field)
                        'orunk_gateway_' . $gateway->id . '_settings', // The option name stored in wp_options
                        array(
                             'sanitize_callback' => array($gateway, 'validate_settings_fields'), // Use the gateway's validation method
                             'default' => array(), // Default value
                             'show_in_rest' => false, // Keep gateway settings out of REST API unless needed
                        )
                   );
              }
         }
     }

    /**
     * Register General settings using the WordPress Settings API.
     * Runs on admin_init.
     * **MODIFIED** to include Checkout Field settings registration.
     */
    public function register_general_settings() {
        // Register the main group for general settings
        // (BIN API Key registration - Existing Code)
        register_setting(
            self::GENERAL_SETTINGS_GROUP,
            self::BIN_API_KEY_OPTION,
            array( /* ... existing args ... */
                'type'              => 'string',
                'sanitize_callback' => array($this, 'sanitize_api_key_field'),
                'default'           => '',
                'show_in_rest'      => false,
            )
        );
        // (API Keys Section - Existing Code)
        add_settings_section(
            'orunk_api_keys_section',
            __('API Keys', 'orunk-users'),
            array($this, 'render_api_keys_section_info'),
            self::GENERAL_SETTINGS_GROUP
        );
        // (BIN API Key Field - Existing Code)
        add_settings_field(
            self::BIN_API_KEY_OPTION,
            __('BIN Lookup API Key', 'orunk-users'),
            array($this, 'render_bin_api_key_field'),
            self::GENERAL_SETTINGS_GROUP,
            'orunk_api_keys_section'
        );

        // --- **NEW**: Register Checkout Field Settings ---
        register_setting(
            self::GENERAL_SETTINGS_GROUP,           // Use the same group as other general settings
            self::CHECKOUT_FIELDS_OPTION,           // The name of the option where all checkout field settings are stored as an array
            array($this, 'sanitize_checkout_fields_settings') // Custom sanitation callback for the array
        );

        add_settings_section(
            'orunk_checkout_fields_section',        // Unique ID for the section
            __('Checkout Billing Fields', 'orunk-users'), // Title displayed for the section
            array($this, 'render_checkout_fields_section_info'), // Callback function for section description (optional)
            self::GENERAL_SETTINGS_GROUP            // Page slug where this section appears (same as other general settings)
        );

        // Define the billing fields to make configurable
        $billing_fields = [
            'billing_first_name' => __('First Name', 'orunk-users'),
            'billing_last_name'  => __('Last Name', 'orunk-users'),
            'billing_email'      => __('Email Address', 'orunk-users'),
            'billing_company'    => __('Company Name', 'orunk-users'), // Included this based on page-checkout.php
            'billing_address_1'  => __('Street Address (Line 1)', 'orunk-users'),
            'billing_address_2'  => __('Street Address (Line 2)', 'orunk-users'),
            'billing_city'       => __('City', 'orunk-users'),
            'billing_state'      => __('State / Province', 'orunk-users'),
            'billing_postcode'   => __('ZIP / Postal Code', 'orunk-users'),
            'billing_country'    => __('Country', 'orunk-users'),
            'billing_phone'      => __('Phone', 'orunk-users'),
        ];

        // Loop through the fields and add a setting field (checkbox) for each
        foreach ($billing_fields as $field_key => $field_label) {
            $option_key = 'enable_' . $field_key; // e.g., 'enable_billing_first_name'
            add_settings_field(
                $option_key,                             // Unique ID for the field (use the key within the option array)
                sprintf(__('Enable %s', 'orunk-users'), $field_label), // Label for the setting field
                array($this, 'render_checkout_field_checkbox'), // Callback to render the checkbox HTML
                self::GENERAL_SETTINGS_GROUP,            // Page slug
                'orunk_checkout_fields_section',         // Section ID
                array(                                   // $args array passed to the callback
                    'option_key' => $option_key,         // The key within the main option array
                    'label_for' => $option_key,         // Associates label with input (optional but good practice)
                    'description' => sprintf(__('Show the "%s" field on the checkout form.', 'orunk-users'), $field_label)
                )
            );
        }
        // --- End NEW: Register Checkout Field Settings ---
    }

    /**
     * Callback function to render optional description for the API Keys section.
     * (Existing Function - Unchanged)
     */
    public function render_api_keys_section_info() {
        echo '<p>' . esc_html__('Enter API keys for external services used by the plugin.', 'orunk-users') . '</p>';
    }

    /**
     * Callback function to render the input field for the BIN API Key.
     * (Existing Function - Unchanged)
     */
    public function render_bin_api_key_field() {
        $option_value = get_option(self::BIN_API_KEY_OPTION, '');
        ?>
        <input type='text'
               name='<?php echo esc_attr(self::BIN_API_KEY_OPTION); ?>'
               value='<?php echo esc_attr($option_value); ?>'
               class='regular-text'
               placeholder='<?php esc_attr_e('Enter your BIN Lookup API Key', 'orunk-users'); ?>' />
        <p class="description">
            <?php esc_html_e('The API key required to use the BIN lookup feature/proxy.', 'orunk-users'); ?>
        </p>
        <?php
    }

    /**
     * Sanitize the API Key field before saving.
     * (Existing Function - Unchanged)
     */
    public function sanitize_api_key_field($input) {
        return sanitize_text_field(trim($input));
    }

    // --- **NEW**: Callback functions for Checkout Field Settings ---

    /**
     * Callback function to render optional description for the Checkout Fields section.
     */
    public function render_checkout_fields_section_info() {
        echo '<p>' . esc_html__('Control which billing fields appear on the checkout page. If disabled, the field will be hidden from the user.', 'orunk-users') . '</p>';
    }

    /**
     * Callback function to render a checkbox for enabling/disabling a checkout field.
     *
     * @param array $args Arguments passed from add_settings_field(), including 'option_key' and 'description'.
     */
    public function render_checkout_field_checkbox($args) {
        // Get the main array of checkout field settings, default to an empty array
        $all_checkout_settings = get_option(self::CHECKOUT_FIELDS_OPTION, array());

        // Get the specific setting for this checkbox (e.g., 'enable_billing_first_name')
        // Default to 'yes' (enabled) if the setting hasn't been saved yet
        $option_key = $args['option_key'];
        $current_value = isset($all_checkout_settings[$option_key]) ? $all_checkout_settings[$option_key] : 'yes'; // Default to enabled

        // Construct the input field name: option_name[option_key]
        $field_name = esc_attr(self::CHECKOUT_FIELDS_OPTION . '[' . $option_key . ']');
        $field_id = esc_attr($option_key); // Use the option key as the ID
        ?>
        <input type='checkbox'
               id='<?php echo $field_id; ?>'
               name='<?php echo $field_name; ?>'
               value='yes' <?php checked($current_value, 'yes'); ?>
        />
        <?php if (!empty($args['description'])) : ?>
        <p class="description" style="display: inline; margin-left: 5px;"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Sanitize the entire array of checkout field settings.
     * Ensures that only 'yes' or 'no' are saved for each field.
     *
     * @param array $input The raw input array from the form submission.
     * @return array The sanitized array of settings.
     */
    public function sanitize_checkout_fields_settings($input) {
        $sanitized_output = array();
        $expected_keys = [ // Define all possible keys corresponding to the checkboxes
            'enable_billing_first_name',
            'enable_billing_last_name',
            'enable_billing_email',
            'enable_billing_company',
            'enable_billing_address_1',
            'enable_billing_address_2',
            'enable_billing_city',
            'enable_billing_state',
            'enable_billing_postcode',
            'enable_billing_country',
            'enable_billing_phone',
        ];

        foreach ($expected_keys as $key) {
            // If the checkbox key exists in the input (meaning it was checked), save 'yes'.
            // Otherwise (unchecked), save 'no'.
            $sanitized_output[$key] = (isset($input[$key]) && $input[$key] === 'yes') ? 'yes' : 'no';
        }

        return $sanitized_output;
    }
    // --- End NEW Callbacks ---


    /**
     * Display the HTML content for the Settings page.
     * Shows tabs for General Settings and Payment Gateways.
     * **MODIFIED** to correctly render the General settings section.
     */
    public function settings_page_html() {
        // Determine the current tab. Default to 'general'.
        $default_tab = 'general';
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : $default_tab;

        // Check if the tab corresponds to a loaded gateway
        $current_gateway = ($current_tab !== $default_tab && isset($this->gateways[$current_tab])) ? $this->gateways[$current_tab] : null;

        ?>
        <div class="wrap orunk-users-settings-wrap">
            <h1><?php esc_html_e('Orunk Users Settings', 'orunk-users'); ?></h1>

            <nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom: 20px;">
                <?php // General Settings Tab ?>
                <a href="?page=orunk-users-settings&tab=general" class="nav-tab <?php echo ($current_tab === 'general') ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('General', 'orunk-users'); ?>
                </a>
                <?php // Payment Gateways Tab (acts as link to list) ?>
                 <a href="?page=orunk-users-settings&tab=gateways" class="nav-tab <?php echo ($current_tab === 'gateways' || $current_gateway) ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Payment Gateways', 'orunk-users'); ?>
                </a>
                 <?php // If viewing a specific gateway, show its title as an inactive pseudo-tab ?>
                 <?php if ($current_gateway): ?>
                     <span class="nav-tab" style="background: #f0f0f1; border-bottom-color: #f0f0f1;"><?php echo esc_html($current_gateway->method_title); ?></span>
                 <?php endif; ?>
            </nav>

            <?php
            // Display WordPress settings errors
            // Use the correct settings group based on the current tab
            if ($current_tab === 'general') {
                settings_errors(self::GENERAL_SETTINGS_GROUP); // <<< Use the correct group name
            } elseif ($current_gateway) {
                settings_errors('orunk_gateway_' . $current_gateway->id . '_settings_group');
            } else {
                settings_errors(); // Display any other general errors
            }
            ?>

            <?php if ($current_tab === 'general') : // --- Displaying General Settings --- ?>

                <form method="post" action="options.php">
                    <?php
                    // Output hidden fields required by options.php for the 'general' settings group
                    settings_fields(self::GENERAL_SETTINGS_GROUP); // <<< Use the correct group name

                    // Output the settings sections and fields for the 'general' group
                    // This will now render API Keys AND Checkout Fields sections
                    do_settings_sections(self::GENERAL_SETTINGS_GROUP); // <<< Use the correct group name

                    // Submit button for general settings
                    submit_button(__('Save General Settings', 'orunk-users'));
                    ?>
                </form>

            <?php elseif ($current_gateway) : // --- Displaying settings form for a specific gateway --- ?>

                 <h2><?php echo esc_html($current_gateway->method_title); ?> <?php esc_html_e('Settings', 'orunk-users'); ?></h2>
                 <p><?php echo wp_kses_post($current_gateway->method_description); ?></p>

                 <form method="post" action="options.php">
                     <?php settings_fields('orunk_gateway_' . $current_gateway->id . '_settings_group'); ?>
                     <table class="form-table">
                          <?php echo $current_gateway->generate_settings_html(); ?>
                     </table>
                     <?php submit_button(__('Save Gateway Settings', 'orunk-users')); ?>
                 </form>


            <?php else : // --- Displaying the main list of available gateways (default if tab=gateways or invalid gateway tab) --- ?>

                <h2><?php esc_html_e('Available Payment Gateways', 'orunk-users'); ?></h2>
                <p><?php esc_html_e('Manage available payment methods for plan purchases.', 'orunk-users'); ?></p>
                <table class="wp-list-table widefat fixed striped orunk-payment-gateways-table">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-title"><?php esc_html_e('Gateway', 'orunk-users'); ?></th>
                            <th scope="col" class="manage-column column-status"><?php esc_html_e('Status', 'orunk-users'); ?></th>
                            <th scope="col" class="manage-column column-manage"><?php esc_html_e('Manage', 'orunk-users'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="the-list">
                        <?php if (!empty($this->gateways)) : ?>
                            <?php foreach ($this->gateways as $gateway) : ?>
                                <?php $manage_url = admin_url('admin.php?page=orunk-users-settings&tab=' . esc_attr($gateway->id)); ?>
                                <tr>
                                    <td class="column-title column-primary has-row-actions">
                                        <strong><a href="<?php echo esc_url($manage_url); ?>" aria-label="<?php printf(esc_attr__('Manage settings for %s', 'orunk-users'), $gateway->method_title); ?>"><?php echo esc_html($gateway->method_title); ?></a></strong>
                                        <div class="row-actions">
                                             <span class="edit"><a href="<?php echo esc_url($manage_url); ?>"><?php esc_html_e('Manage', 'orunk-users'); ?></a></span>
                                        </div>
                                         <button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e('Show more details'); ?></span></button>
                                    </td>
                                    <td class="column-status" data-colname="<?php esc_attr_e('Status'); ?>">
                                        <?php if ($gateway->enabled === 'yes') : ?>
                                            <span style="color: #00a32a; font-weight: bold;"><?php esc_html_e('Enabled', 'orunk-users'); ?></span>
                                        <?php else : ?>
                                            <span style="color: #dc3232;"><?php esc_html_e('Disabled', 'orunk-users'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                     <td class="column-manage" data-colname="<?php esc_attr_e('Manage'); ?>">
                                         <a href="<?php echo esc_url($manage_url); ?>" class="button button-secondary">
                                            <?php esc_html_e('Manage', 'orunk-users'); ?>
                                        </a>
                                     </td>
                                </tr>
                            <?php endforeach; ?>
                         <?php else : ?>
                            <tr class="no-items">
                                <td class="colspanchange" colspan="3"><?php esc_html_e('No payment gateways have been loaded or detected.', 'orunk-users'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                     <tfoot>
                        <tr>
                            <th scope="col" class="manage-column column-title"><?php esc_html_e('Gateway', 'orunk-users'); ?></th>
                            <th scope="col" class="manage-column column-status"><?php esc_html_e('Status', 'orunk-users'); ?></th>
                            <th scope="col" class="manage-column column-manage"><?php esc_html_e('Manage', 'orunk-users'); ?></th>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div> <?php // End wrap ?>
        <?php
    } // end settings_page_html

} // End Class Custom_Orunk_Settings