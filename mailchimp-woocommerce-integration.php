<?php
/*
Plugin Name: Zendo SIT
Description: When a user enrolls in the Zendo SIT course, add them to the corresponding MailChimp list.
Version: 2.3.1
Author: Jess Aumick
*/

// Prevent direct access to this file
if (!defined("ABSPATH")) {
    exit();
}
// Include the Composer autoloader
require_once "vendor/autoload.php";
require_once plugin_dir_path(__FILE__) . 'sit-settings.php';

// Custom logging function
function zendo_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $log_file = plugin_dir_path(__FILE__) . 'zendo_sit_log.txt'; // Define the log file
        $log_message = date("Y-m-d H:i:s") . " - " . $message . PHP_EOL;
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}

// Initialize the MailChimp API
$MailChimpApiKey = get_option("mailchimp_api_key");
if (!$MailChimpApiKey) {
    // Deactivate the plugin
    deactivate_plugins(plugin_basename(__FILE__));
    // Display an admin notice
    add_action("admin_notices", "mailchimp_missing_api_key_notice");
    function mailchimp_missing_api_key_notice()
    {
        ?>
        <div class="notice notice-error">
            <p><?php _e(
                "MailChimp API Key is missing. The MailChimp WooCommerce Integration plugin has been deactivated. Please set the API Key in the plugin settings.",
                "mailchimp-woocommerce-integration",
            ); ?></p>
        </div>
        <?php
    }
    return;
}
$MailChimp = new \DrewM\MailChimp\MailChimp($MailChimpApiKey);

// Initialize this plugin
add_action("init", "zendo_mailchimp_init");
function zendo_mailchimp_init()
{
    // Your initialization code here
}

// Hook into WooCommerce order status change
add_action(
    "woocommerce_order_status_changed",
    "zendo_mailchimp_process_order",
    10,
    3,
);
function zendo_mailchimp_process_order($order_id, $old_status, $new_status)
{
    global $MailChimp;

    // Check if the new status is "processing" and the old status is not "processing"
    if ($new_status != "processing" || $old_status == "processing") {
        return; // Exit the function if the conditions are not met
    }

    // Get the order object
    $order = wc_get_order($order_id);

    // Get the user's email
    $user_email = $order->get_billing_email();

    // Get the enrollee's first name and last name from order meta
    $enrollee_first_name = trim(get_post_meta($order_id, 'enrollee_first_name', true));
    $enrollee_last_name = trim(get_post_meta($order_id, 'enrollee_last_name', true));

    // If enrollee names are not provided, use billing names as fallback
    $user_first_name = $enrollee_first_name ? $enrollee_first_name : trim($order->get_billing_first_name());
    $user_last_name = $enrollee_last_name ? $enrollee_last_name : trim($order->get_billing_last_name());

    // Add the user's email, first name, and last name to the MailChimp mailing list
    $result = $MailChimp->post("lists/08ecdffceb/members", [
        "email_address" => $user_email,
        "status" => "subscribed",
        "update_existing"  => true,
        "merge_fields" => [
            "FNAME" => $user_first_name,
            "LNAME" => $user_last_name,
        ],
    ]);

    if (!$MailChimp->success()) {
        // Error handling
        error_log("MailChimp API Error (Adding/Updating Subscriber): " . $MailChimp->getLastError());
    }

    // ----------------------------------------------------------
    // 1) Create a map from product IDs to their desired tag names
    // ----------------------------------------------------------
    $tag_mapping = [
        232786 => 'February 2025 SIT Registrants',
        233515 => 'April 2025 SIT Registrants',
        // Add more product-to-tag mappings here if needed
    ];

    // ----------------------------------------------------------
    // 2) Loop over each item, check if it appears in the map, and tag accordingly
    // ----------------------------------------------------------
    $items = $order->get_items();
    foreach ($items as $item) {
        // Variation ID or product ID
        $variation_id = $item->get_variation_id();
        $product_id   = $variation_id ? $variation_id : $item->get_product_id();

        if (isset($tag_mapping[$product_id])) {
            $tag_name        = $tag_mapping[$product_id];
            $subscriber_hash = $MailChimp->subscriberHash($user_email);

            $tag_result = $MailChimp->post("lists/08ecdffceb/members/$subscriber_hash/tags", [
                "tags" => [
                    [
                        "name"   => $tag_name,
                        "status" => "active",
                    ],
                ],
            ]);

            if (!$MailChimp->success()) {
                error_log("MailChimp API Error (Adding Tag: $tag_name): " . $MailChimp->getLastError());
            }
        }
    }
}

add_action('woocommerce_product_after_variable_attributes', 'zendo_add_mailchimp_tag_field_to_variations', 10, 3);
function zendo_add_mailchimp_tag_field_to_variations($loop, $variation_data, $variation) {
    $current_value = get_post_meta($variation->ID, '_zendo_sit_mailchimp_tag', true);

    woocommerce_wp_text_input([
        'id'          => "_zendo_sit_mailchimp_tag_{$loop}",
        'name'        => "zendo_sit_mailchimp_tag[{$variation->ID}]",
        'value'       => $current_value,
        'label'       => __('MailChimp Tag', 'zendo-sit'),
        'desc_tip'    => true,
        'description' => __('Enter the MailChimp tag for this variation', 'zendo-sit'),
    ]);
}

add_action('woocommerce_save_product_variation', 'zendo_save_mailchimp_tag_field_for_variations', 10, 2);
function zendo_save_mailchimp_tag_field_for_variations($variation_id, $loop_index) {
    if (isset($_POST['zendo_sit_mailchimp_tag'][$variation_id])) {
        $tag = sanitize_text_field($_POST['zendo_sit_mailchimp_tag'][$variation_id]);
        update_post_meta($variation_id, '_zendo_sit_mailchimp_tag', $tag);
    } else {
        delete_post_meta($variation_id, '_zendo_sit_mailchimp_tag');
    }
}
?>
