<?php
/*
Plugin Name: MailChimp WooCommerce Integration
Description: Specifically created for Zendo Project. Integrates WooCommerce with MailChimp API.
Version: 1.1.0
Author: Jess A.
*/

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Include the Composer autoloader
require_once 'vendor/autoload.php';

// Initialize the MailChimp API
$MailChimpApiKey = get_option('mailchimp_api_key');
if (!$MailChimpApiKey) {
    // Deactivate the plugin
    deactivate_plugins(plugin_basename(__FILE__));
    // Display an admin notice
    add_action('admin_notices', 'mailchimp_missing_api_key_notice');
    function mailchimp_missing_api_key_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('MailChimp API Key is missing. The MailChimp WooCommerce Integration plugin has been deactivated. Please set the API Key in the plugin settings.', 'mailchimp-woocommerce-integration'); ?></p>
        </div>
        <?php
    }
    return;
}
$MailChimp = new \DrewM\MailChimp\MailChimp($MailChimpApiKey);

// Initialize this plugin
add_action('init', 'zendo_mailchimp_init');
function zendo_mailchimp_init() {
    // Your initialization code here
    // This is where you can set up hooks for WooCommerce and MailChimp integration
}

// Hook into WooCommerce order status change
add_action('woocommerce_order_status_changed', 'zendo_mailchimp_process_order', 10, 4);
function zendo_mailchimp_process_order($order_id, $old_status, $new_status, $order) {
    global $MailChimp;
    // Check if the new status is not "failed"
    if ($order->get_status() != 'failed') {
        // Check if the order contains any item from a specific product category
        $items = $order->get_items();
        $has_training = false;
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            if (has_term('Trainings', 'product_cat', $product_id)) {
                $has_training = true;
                break; // Exit the loop if a match is found
            }
        }
        // If the order contains an item from the "Trainings" category
        if ($has_training) {
            // Get the user's email, first name, and last name from the order
            $user_email = $order->get_billing_email();
            $user_first_name = $order->get_billing_first_name();
            $user_last_name = $order->get_billing_last_name();

            // Add the user's email, first name, and last name to the MailChimp mailing list
            $result = $MailChimp->post('lists/995b06f870/members', [
                'email_address' => $user_email,
                'status' => 'subscribed', // or 'pending'
                'merge_fields' => [
                    'FNAME' => $user_first_name,
                    'LNAME' => $user_last_name,
                ],
            ]);
            if (!$MailChimp->success()) {
                // Error handling
                $error_message = $MailChimp->getLastError();
                error_log('MailChimp API Error: ' . $error_message);
            }
        }
    }
}
?>
