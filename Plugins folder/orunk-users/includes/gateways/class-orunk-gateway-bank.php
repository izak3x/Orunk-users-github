<?php
// File: orunk-users/includes/gateways/class-orunk-gateway-bank.php
if (!defined('ABSPATH')) exit;

/**
 * Bank Transfer Payment Gateway Class
 */
class Orunk_Gateway_Bank extends Orunk_Payment_Gateway {

    public function __construct() {
        $this->id = 'bank'; // Unique ID for this gateway
        $this->method_title = __('Direct Bank Transfer', 'orunk-users');
        $this->method_description = __('Allow users to pay via bank transfer. Requires manual activation of purchases.', 'orunk-users');
        $this->icon = ''; // Optional: URL to an icon for bank transfer

        // Define supports - maybe not needed for this simple plugin
        // $this->supports = array( 'products' );

        // Call parent constructor AFTER setting $this->id
        parent::__construct();
    }

    /**
     * Define the admin settings form fields for Bank Transfer.
     */
    public function init_form_fields() {
        // These fields match the old settings page structure, now organized
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'orunk-users'),
                'type' => 'checkbox',
                'label' => __('Enable Bank Transfer', 'orunk-users'),
                'default' => 'no',
                'description' => __('Allow customers to use direct bank transfer.', 'orunk-users'),
            ),
            'title' => array(
                'title' => __('Title', 'orunk-users'),
                'type' => 'text',
                'description' => __('Payment method title that the customer will see on your checkout.', 'orunk-users'),
                'default' => __('Direct Bank Transfer', 'orunk-users'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'orunk-users'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'orunk-users'),
                'default' => __('Make your payment directly into our bank account. Please use your Purchase ID as the payment reference.', 'orunk-users')
            ),
             'instructions' => array(
                 'title' => __('Instructions', 'orunk-users'),
                 'type' => 'textarea',
                 'description' => __('Instructions that will be added to the thank you page and potentially emails.', 'orunk-users'),
                 'default' => __('Your purchase is pending until we confirm payment has been received. Our bank details are below:'),
             ),
             // Bank account details section
              'account_details_title' => array(
                 'title' => __( 'Account Details', 'orunk-users' ),
                 'type'  => 'title', // Use 'title' type for a heading
                 'description' => __('Enter your bank account details below. These will be shown in the instructions.', 'orunk-users'),
             ),
             'bank_name' => array(
                 'title' => __('Bank Name', 'orunk-users'),
                 'type' => 'text',
                 'default' => ''
             ),
             'account_number' => array(
                 'title' => __('Account Number', 'orunk-users'),
                 'type' => 'text',
                 'default' => ''
             ),
             'routing_number' => array(
                 'title' => __('Routing Number / Sort Code', 'orunk-users'),
                 'type' => 'text',
                 'default' => ''
             ),
             // Add IBAN, SWIFT/BIC etc. if needed
              'bank_contact_email' => array( // Renamed from 'email' to avoid conflict
                 'title' => __('Contact Email (Optional)', 'orunk-users'),
                 'type' => 'email',
                 'description' => __('Optional contact email related ONLY to bank payments, shown with instructions.', 'orunk-users'),
                 'default' => ''
             ),
        );
    }

    /**
     * Process the payment for Bank Transfer.
     * Since it's manual, this mainly involves setting up confirmation.
     *
     * @param int $purchase_id The ID of the pending purchase record.
     * @return array Result array.
     */
    public function process_payment($purchase_id) {
        global $wpdb;
        // The purchase status should already be 'pending' from initiate_purchase

        // Optional: Trigger an email notification to the admin about the new pending order.
        // wp_mail(get_option('admin_email'), 'New Pending Bank Transfer Purchase', 'Purchase ID: ' . $purchase_id . ' requires manual payment confirmation.');

        // Build instructions to show user, including bank details
        $instructions = $this->get_option('instructions');
        $instructions .= "\n\n--- Account Details ---";
        $instructions .= "\nBank Name: " . $this->get_option('bank_name');
        $instructions .= "\nAccount Number: " . $this->get_option('account_number');
        $instructions .= "\nSort Code/Routing: " . $this->get_option('routing_number');
        $contact_email = $this->get_option('bank_contact_email');
        if ($contact_email) {
             $instructions .= "\nContact: " . $contact_email;
        }
        $instructions .= "\n---------------------";
         $instructions .= "\nPlease use Purchase ID: " . $purchase_id . " as the payment reference.";


        // Return success (purchase initiated, pending manual verification)
        // We'll pass the instructions back to be stored in a transient and shown on the confirmation page.
        return array(
            'result' => 'success',
            'redirect' => null, // No redirect needed for bank transfer
            'message' => nl2br(esc_textarea($instructions)) // Pass formatted instructions
        );
    }
} // End Class Orunk_Gateway_Bank