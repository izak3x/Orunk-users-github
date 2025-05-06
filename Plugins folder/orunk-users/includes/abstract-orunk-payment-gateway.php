<?php
/**
 * Abstract Orunk Payment Gateway Class
 *
 * Provides the base structure, common methods, and required methods
 * for all payment gateways within the Orunk Users plugin.
 *
 * @package OrunkUsers\Gateways
 * @version 1.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

abstract class Orunk_Payment_Gateway {

    /** @var string Unique identifier for the gateway (e.g., 'bank', 'stripe'). REQUIRED. */
    public $id;

    /** @var string Title displayed in the admin settings area. REQUIRED. */
    public $method_title;

    /** @var string Description displayed in the admin settings area. Optional. */
    public $method_description;

    /** @var string Title displayed to the user on the checkout/payment page. REQUIRED. */
    public $title;

    /** @var string Description displayed to the user below the title on checkout. Optional. */
    public $description;

    /** @var string URL to an icon displayed next to the gateway title on checkout. Optional. */
    public $icon;

    /** @var string Whether the gateway is enabled ('yes' or 'no'). Loaded from settings. */
    public $enabled = 'no';

    /** @var array Holds the gateway's settings loaded from the database. */
    public $settings = array();

    /** @var array Defines the structure of the settings fields for the admin page. Set in init_form_fields(). */
    public $form_fields = array();


    /**
     * Constructor for the gateway.
     * Loads settings and initializes properties.
     * Subclasses MUST call parent::__construct() AFTER setting $this->id.
     */
    public function __construct() {
        // Ensure ID is set by subclass before proceeding
        if (empty($this->id)) {
            trigger_error('Payment gateway ID must be set in the constructor.', E_USER_WARNING);
            return;
        }

        // Load settings from WP options based on gateway ID
        $this->init_settings();

        // Define admin form fields (implementation specific to each gateway)
        $this->init_form_fields();

        // Populate user-facing properties from the loaded settings
        $this->enabled       = $this->get_option('enabled', 'no');
        $this->title         = $this->get_option('title', $this->method_title); // Default to admin title if not set
        $this->description   = $this->get_option('description', '');
        $this->icon          = $this->get_option('icon', ''); // Allow setting icon via options too?
    }

    /**
     * Load the gateway's settings from the WordPress options table.
     * Settings are stored in a single option array: 'orunk_gateway_{id}_settings'.
     */
    public function init_settings() {
        $this->settings = get_option('orunk_gateway_' . $this->id . '_settings', array());
    }

    /**
     * Define the form fields for the gateway's admin settings page.
     * This method MUST be implemented by the extending gateway class.
     * Should populate $this->form_fields with an array defining the fields.
     * Example structure:
     * $this->form_fields = array(
     * 'enabled' => array( 'title' => 'Enable', 'type' => 'checkbox', 'label' => 'Enable Gateway', 'default' => 'no' ),
     * 'api_key' => array( 'title' => 'API Key', 'type' => 'text', 'default' => '' ),
     * ...
     * );
     */
    abstract public function init_form_fields();

    /**
     * Get an option value from the loaded settings array.
     * Provides a safe way to access settings with a default value.
     *
     * @param string $key     The key of the setting to retrieve.
     * @param mixed  $default (Optional) The default value to return if the key isn't found. Default is empty string.
     * @return mixed The value of the setting, or the default value.
     */
    public function get_option($key, $default = '') {
        // Check if settings were loaded and the key exists
        if (is_array($this->settings) && isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        return $default;
    }

    /**
     * Generate the HTML for the admin settings page based on $this->form_fields.
     * Creates table rows for each setting field.
     *
     * @return string The generated HTML string for the settings table body.
     */
    public function generate_settings_html() {
         ob_start(); // Start output buffering to capture HTML
         ?>
         <?php if (empty($this->form_fields)) : ?>
             <tr valign="top">
                 <td colspan="2" style="padding: 20px 0;">
                     <?php esc_html_e('No settings available for this payment gateway.', 'orunk-users'); ?>
                 </td>
             </tr>
         <?php else : ?>
             <?php foreach ($this->form_fields as $key => $field) : ?>
                 <?php
                 // Get field properties with defaults
                 $value       = $this->get_option($key, $field['default'] ?? '');
                 $type        = $field['type'] ?? 'text';
                 $title       = $field['title'] ?? '';
                 $desc        = $field['description'] ?? '';
                 $tip         = $field['desc_tip'] ?? ''; // Tooltip text (optional)
                 $label       = $field['label'] ?? ''; // For checkboxes
                 $css         = $field['css'] ?? ''; // Custom CSS style string
                 $placeholder = $field['placeholder'] ?? '';
                 $options     = $field['options'] ?? array(); // For select fields
                 $custom_attributes = $field['custom_attributes'] ?? array(); // For adding custom attributes like 'required', 'min', 'max'

                 // Generate unique ID and Name for the form field
                 $field_id   = 'orunk_gateway_' . esc_attr($this->id) . '_' . esc_attr($key);
                 // Name format required for options.php saving: option_name[key]
                 $field_name = 'orunk_gateway_' . esc_attr($this->id) . '_settings[' . esc_attr($key) . ']';

                 // Prepare custom attributes string
                 $custom_attr_str = '';
                 foreach ($custom_attributes as $attr_key => $attr_val) {
                     $custom_attr_str .= esc_attr($attr_key) . '="' . esc_attr($attr_val) . '" ';
                 }
                 ?>
                 <tr valign="top">
                     <th scope="row" class="titledesc">
                         <label for="<?php echo $field_id; ?>"><?php echo esc_html($title); ?></label>
                         <?php if ($tip) : ?>
                             <?php // echo wc_help_tip($tip); // If using WooCommerce style tooltips ?>
                             <span class="woocommerce-help-tip" title="<?php echo esc_attr($tip); ?>">?</span>
                         <?php endif; ?>
                     </th>
                     <td class="forminp forminp-<?php echo esc_attr($type); ?>">
                         <?php // Switch based on field type to render correct HTML input ?>
                         <?php switch ($type) :
                             case 'title': // Used for section titles/notices within the form table ?>
                                 <?php if ($desc) : ?>
                                     <p style="padding-top: 0; margin-top: 0;"><strong><?php echo esc_html($title); ?></strong></p>
                                     <p class="description" style="margin-bottom: 1em;"><?php echo wp_kses_post($desc); ?></p>
                                 <?php endif; ?>
                                 <?php break; ?>

                             <?php case 'text': ?>
                             <?php case 'email': ?>
                             <?php case 'password': // Use type="password" for sensitive fields ?>
                             <?php case 'number': ?>
                                 <input
                                     type="<?php echo esc_attr($type); ?>"
                                     name="<?php echo $field_name; ?>"
                                     id="<?php echo $field_id; ?>"
                                     value="<?php echo esc_attr($value); ?>"
                                     class="regular-text"
                                     style="<?php echo esc_attr($css); ?>"
                                     placeholder="<?php echo esc_attr($placeholder); ?>"
                                     <?php echo $custom_attr_str; ?>
                                 />
                                 <?php break; ?>

                             <?php case 'textarea': ?>
                                 <textarea
                                     name="<?php echo $field_name; ?>"
                                     id="<?php echo $field_id; ?>"
                                     class="large-text"
                                     rows="5"
                                     style="<?php echo esc_attr($css); ?>"
                                     placeholder="<?php echo esc_attr($placeholder); ?>"
                                     <?php echo $custom_attr_str; ?>
                                 ><?php echo esc_textarea($value); ?></textarea>
                                 <?php break; ?>

                             <?php case 'checkbox': ?>
                                 <input
                                     type="checkbox"
                                     name="<?php echo $field_name; ?>"
                                     id="<?php echo $field_id; ?>"
                                     value="yes" <?php checked($value, 'yes'); ?>
                                     style="<?php echo esc_attr($css); ?>"
                                     <?php echo $custom_attr_str; ?>
                                 />
                                 <?php if ($label) : ?>
                                     <label for="<?php echo $field_id; ?>"><?php echo esc_html($label); ?></label>
                                 <?php endif; ?>
                                 <?php break; ?>

                             <?php case 'select': ?>
                                 <select
                                     name="<?php echo $field_name; ?>"
                                     id="<?php echo $field_id; ?>"
                                     style="<?php echo esc_attr($css); ?>"
                                     <?php echo $custom_attr_str; ?>
                                     >
                                     <?php foreach ($options as $option_key => $option_value) : ?>
                                         <option value="<?php echo esc_attr($option_key); ?>" <?php selected($value, $option_key); ?>>
                                             <?php echo esc_html($option_value); ?>
                                         </option>
                                     <?php endforeach; ?>
                                 </select>
                                 <?php break; ?>

                             <?php // Add other field types (e.g., 'radio', 'multiselect') if needed ?>

                             <?php default: ?>
                                 <?php do_action('orunk_users_admin_field_' . $type, $key, $field, $value); // Hook for custom field types ?>
                                 <?php break; ?>

                         <?php endswitch; ?>

                         <?php // Display description below the field (if not a title type) ?>
                         <?php if ($type !== 'title' && $desc) : ?>
                             <p class="description"><?php echo wp_kses_post($desc); ?></p>
                         <?php endif; ?>
                     </td>
                 </tr>
             <?php endforeach; ?>
         <?php endif; ?>
         <?php
         return ob_get_clean(); // Return buffered HTML
    }

    /**
     * Validate the settings fields before saving to the database.
     * Provides basic sanitization based on field type.
     * Gateways can override this method for more complex validation logic.
     *
     * @param array $input Raw settings data from the $_POST request.
     * @return array The sanitized settings array ready to be saved.
     */
     public function validate_settings_fields($input) {
         $output = array();
         if (empty($this->form_fields) || !is_array($input)) {
             return $output; // Return empty if no fields defined or input is invalid
         }

         foreach ($this->form_fields as $key => $field) {
             // Get the submitted value for this key, or null if not set
             $value = isset($input[$key]) ? $input[$key] : null;
             $type = $field['type'] ?? 'text';

             // Handle null values (e.g., unchecked checkbox)
             if ($value === null) {
                 if ($type === 'checkbox') {
                       $output[$key] = 'no'; // Unchecked checkbox value
                 } else {
                       $output[$key] = $field['default'] ?? ''; // Use default or empty for other types
                 }
                 continue; // Move to the next field
             }

             // Sanitize based on field type
             switch ($type) {
                 case 'email':
                     $output[$key] = sanitize_email($value);
                     break;
                 case 'textarea':
                     // Allow some basic HTML, adjust kses rules if needed
                     $output[$key] = wp_kses_post(trim($value));
                     break;
                 case 'checkbox':
                     // Checkboxes should submit 'yes' if checked
                     $output[$key] = ($value === 'yes' ? 'yes' : 'no');
                     break;
                 case 'select':
                      $allowed_options = array_keys($field['options'] ?? array());
                      // Ensure the submitted value is one of the allowed options
                      $output[$key] = in_array((string) $value, $allowed_options, true) ? (string) $value : ($field['default'] ?? '');
                      break;
                 case 'number':
                       // Sanitize as float or int depending on needs
                       $output[$key] = is_numeric($value) ? $value : ($field['default'] ?? '');
                       break;
                 case 'text':
                 case 'password': // Note: Passwords are saved as plain text here. Consider encryption for production.
                 default:
                     $output[$key] = sanitize_text_field(trim($value));
                     break;
             }
         }
         return $output;
     }

    /**
     * Process the payment for a given purchase ID.
     * This method MUST be implemented by the extending gateway class.
     * It should interact with the payment provider (if applicable) and
     * return a standardized result array.
     *
     * @param int $purchase_id The ID of the 'pending' purchase record in wp_orunk_user_purchases.
     * @return array Should return an array with the following keys:
     * 'result'   => string ('success' or 'failure')
     * 'redirect' => string|null (URL to redirect user to, e.g., Stripe Checkout, PayPal, or null)
     * 'message'  => string|null (Optional message, e.g., bank details, error message)
     */
    abstract public function process_payment($purchase_id);

} // End Class Orunk_Payment_Gateway
