<?php
/**
 * Plugin Name: MailChimp Tags for WooCommerce
 * Requires Plugins: woocommerce
 * Description: Assign tags to MailChimp contacts based on specific items purchased from your WooCommerce shop.
 * Version: 0.3.6
 * Author: Jess A.
 *
 * @package MailChimpTagsForWooCommerce
 */

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

// Include the Composer autoloader.
require_once 'vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . 'sit-settings.php';

// Add settings link to plugin page.
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mctwc_add_plugin_settings_link');

function mctwc_add_plugin_settings_link( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url('admin.php?page=wc-settings&tab=integration&section=mailchimp-tags'),
		__('Settings', 'mctwc')
	);

	// Add the settings link to the beginning of the links array.
	array_unshift($links, $settings_link);

	return $links;
}

// Custom logging function.
function mctwc_log( $message ) {
	if ( defined('WP_DEBUG') && WP_DEBUG ) {
		$log_file = plugin_dir_path(__FILE__) . 'mctwc_log.txt';
		$log_message = gmdate('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
		file_put_contents($log_file, $log_message, FILE_APPEND);
	}
}

// Initialize this plugin.
add_action('init', 'mctwc_mailchimp_init');
function mctwc_mailchimp_init() {

	mctwc_log('Plugin initialized');
}

// Hook into WooCommerce order status change.
add_action(
	'woocommerce_order_status_changed',
	'mctwc_mailchimp_process_order',
	15,
	3,
);

function mctwc_mailchimp_process_order( $order_id, $old_status, $new_status ) {
	// Check if the new status is "processing" and the old status is not "processing".
	if ( $new_status != 'processing' || $old_status == 'processing' ) {
		mctwc_log("Order $order_id status change ($old_status → $new_status) doesn't trigger processing");
		return; // Exit the function if the conditions are not met.
	}

	mctwc_log("Processing order $order_id: status changed from $old_status to $new_status");

	// Get API key and list ID from WooCommerce integration settings.
	$settings = get_option('woocommerce_mailchimp-tags_settings');
	$mailchimp_api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
	$list_id = isset($settings['list_id']) ? $settings['list_id'] : '';

	// Validate API key and list ID.
	if ( empty($mailchimp_api_key) || empty($list_id) ) {
		mctwc_log('Missing API key or list ID - aborting order processing');
		return;
	}

	// Initialize MailChimp API client.
	try {
		$mailchimp = new \DrewM\MailChimp\MailChimp($mailchimp_api_key);
	} catch ( Exception $e ) {
		mctwc_log('Failed to initialize MailChimp: ' . $e->getMessage());
		return;
	}

	// Get the order object and user email.
	$order = wc_get_order($order_id);
	$user_email = $order->get_billing_email();

	if ( empty($user_email) ) {
		mctwc_log("No email address found for order $order_id");
		return;
	}

	// Get the enrollee's first name and last name from order meta.
	$enrollee_first_name = trim(get_post_meta($order_id, 'enrollee_first_name', true));
	$enrollee_last_name = trim(get_post_meta($order_id, 'enrollee_last_name', true));

	// If enrollee names are not provided, use billing names as fallback.
	$user_first_name = $enrollee_first_name ? $enrollee_first_name : trim($order->get_billing_first_name());
	$user_last_name = $enrollee_last_name ? $enrollee_last_name : trim($order->get_billing_last_name());

	mctwc_log("Processing tags for: $user_email ($user_first_name $user_last_name)");

	// Calculate subscriber hash.
	$subscriber_hash = $mailchimp->subscriberHash($user_email);

	// Check if the member exists in MailChimp.
	$member = $mailchimp->get("lists/$list_id/members/$subscriber_hash");

	$member_exists = false;
	$current_status = null;

	if ( $mailchimp->success() ) {
		$member_exists = true;
		$current_status = isset($member['status']) ? $member['status'] : null;
		mctwc_log("Found existing member $user_email with status: " . $current_status);

		// Only update merge fields if they're different.
		$needs_update = false;
		if ( isset($member['merge_fields']) ) {
			if ( $member['merge_fields']['FNAME'] != $user_first_name ||
				$member['merge_fields']['LNAME'] != $user_last_name ) {
				$needs_update = true;
			}
		} else {
			$needs_update = true;
		}

		if ( $needs_update ) {
			// Use PATCH to only update merge fields, never change status.
			$update_result = $mailchimp->patch("lists/$list_id/members/$subscriber_hash", [
				'merge_fields' => [
					'FNAME' => $user_first_name,
					'LNAME' => $user_last_name,
				],
			]);

			if ( ! $mailchimp->success() ) {
				mctwc_log('Warning: Could not update merge fields - ' . $mailchimp->getLastError());
			} else {
				mctwc_log("Successfully updated merge fields for $user_email");
			}
		}
	} else {
		mctwc_log("Member $user_email not found in MailChimp list");
	}

	// ----------------------------------------------------------
	// Process items and collect tags
	// ----------------------------------------------------------
	$items = $order->get_items();
	mctwc_log('Processing ' . count($items) . " items for order $order_id");

	$tags_to_apply = array();

	foreach ( $items as $item ) {
		// Identify the variation ID.
		$variation_id = $item->get_variation_id();
		$product_id = $variation_id ? $variation_id : $item->get_product_id();
		$product_name = $item->get_name();

		mctwc_log("Processing item: $product_name (ID: $product_id, Variation ID: $variation_id)");

		// Read the variation's MailChimp tag from post meta.
		$tag_name = get_post_meta($product_id, '_mctwc_mailchimp_tag', true);

		// If the tag isn't empty, add it to our list.
		if ( ! empty($tag_name) ) {
			mctwc_log("Found tag '$tag_name' for product ID $product_id");
			$tags_to_apply[] = [
				'name' => $tag_name,
				'status' => 'active',
			];
		} else {
			mctwc_log("No tag found for product ID $product_id");
		}
	}

	// Apply tags or create contact if needed.
	if ( ! empty($tags_to_apply) ) {
		// If member doesn't exist and we have tags to apply, create as transactional.
		if ( ! $member_exists ) {
			mctwc_log('Creating transactional contact to apply tags');

			$result = $mailchimp->put("lists/$list_id/members/$subscriber_hash", [
				'email_address' => $user_email,
				'status_if_new' => 'transactional',
				'merge_fields' => [
					'FNAME' => $user_first_name,
					'LNAME' => $user_last_name,
				],
			]);

			if ( ! $mailchimp->success() ) {
				mctwc_log('Failed to create contact: ' . $mailchimp->getLastError());
				return;
			} else {
				mctwc_log("Created new transactional contact for $user_email");
			}
		}

		// Apply tags.
		try {
			$tag_result = $mailchimp->post("lists/$list_id/members/$subscriber_hash/tags", [
				'tags' => $tags_to_apply,
			]);

			if ( ! $mailchimp->success() ) {
				mctwc_log('MailChimp API Error (Adding Tags): ' . $mailchimp->getLastError());
			} else {
				$tag_names = array_column($tags_to_apply, 'name');
				mctwc_log("Successfully added tags to $user_email: " . implode(', ', $tag_names));
			}
		} catch ( Exception $e ) {
			mctwc_log('Exception when adding tags: ' . $e->getMessage());
		}
	} else {
		mctwc_log("No tags to apply for order $order_id");
	}
}

add_action('woocommerce_product_after_variable_attributes', 'mctwc_add_mailchimp_tag_field_to_variations', 10, 3);
function mctwc_add_mailchimp_tag_field_to_variations( $loop, $variation_data, $variation ) {
	$current_value = get_post_meta($variation->ID, '_mctwc_mailchimp_tag', true);

	woocommerce_wp_text_input([
		'id'          => "_mctwc_mailchimp_tag_{$loop}",
		'name'        => "mctwc_mailchimp_tag[{$variation->ID}]",
		'value'       => $current_value,
		'label'       => __('MailChimp Tag', 'mctwc'),
		'desc_tip'    => true,
		'description' => __('Enter the MailChimp tag for this variation', 'mctwc'),
	]);
}

add_action('woocommerce_save_product_variation', 'mctwc_save_mailchimp_tag_field_for_variations', 10, 2);
function mctwc_save_mailchimp_tag_field_for_variations( $variation_id, $loop_index ) {
	if ( isset($_POST['mctwc_mailchimp_tag'][ $variation_id ]) ) {
		$tag = sanitize_text_field( $_POST['mctwc_mailchimp_tag'][ $variation_id ] );
		update_post_meta($variation_id, '_mctwc_mailchimp_tag', $tag);
	} else {
		delete_post_meta($variation_id, '_mctwc_mailchimp_tag');
	}
}
