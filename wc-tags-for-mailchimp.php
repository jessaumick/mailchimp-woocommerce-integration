<?php
/**
 * Plugin Name: WC Product Tags for Mailchimp
 * Requires Plugins: woocommerce
 * Description: Assign tags to Mailchimp contacts based on specific items purchased from your WooCommerce shop.
 * Version: 1.0.0
 * Author: Jess A.
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mctwc
 *
 * @package MailchimpTagsForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

define( 'MCTWC_PLUGIN_FILE', __FILE__ );
define( 'MCTWC_META_KEY_VARIATION_TAG', '_mctwc_mailchimp_tag' );
define( 'MCTWC_META_KEY_PRODUCT_TAG', '_mctwc_mailchimp_product_tag' );
define( 'MCTWC_SETTINGS_KEY', 'woocommerce_mailchimp-tags_settings' );

/**
 * Get the plugin version from the plugin header.
 *
 * @return string
 */
function mctwc_get_version() {
	static $version = null;
	if ( null === $version ) {
		$plugin_data = get_file_data( MCTWC_PLUGIN_FILE, array( 'Version' => 'Version' ) );
		$version     = $plugin_data['Version'];
	}
	return $version;
}

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
});

$mctwc_autoload_path = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
if ( ! file_exists( $mctwc_autoload_path ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="error"><p><strong>Mailchimp Tags for WooCommerce:</strong> Dependencies not installed. Please run <code>composer install</code> in the plugin directory, or install the plugin from WordPress.org.</p></div>';
		}
	);
	return;
}
require_once $mctwc_autoload_path;
require_once plugin_dir_path( __FILE__ ) . 'class-wc-mailchimp-tags-integration.php';

/**
 * Add settings link to plugin actions.
 *
 * @since 1.0.0
 * @param array $links Existing plugin links.
 * @return array Updated list of links.
 */
function mctwc_add_plugin_settings_link( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'admin.php?page=wc-settings&tab=integration&section=mailchimp-tags' ),
		__( 'Settings', 'mctwc' )
	);

	array_unshift($links, $settings_link);

	return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mctwc_add_plugin_settings_link');

/**
 * Log a message using WooCommerce logger.
 *
 * @since 1.0.0
 * @param string $message The message to log.
 * @param string $level   Log level: emergency|alert|critical|error|warning|notice|info|debug.
 * @return void
 */
function mctwc_log( $message, $level = 'info' ) {
	$always_log_levels = array( 'emergency', 'alert', 'critical', 'error', 'warning' );

	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		if ( ! in_array( $level, $always_log_levels, true ) ) {
			return;
		}
	}

	$logger  = wc_get_logger();
	$context = array( 'source' => 'mailchimp-tags-woocommerce' );
	$logger->log( $level, $message, $context );
}

add_action(
	'woocommerce_order_status_changed',
	'mctwc_mailchimp_process_order',
	15,
	3,
);

/**
 * Sync tags to Mailchimp when an order status changes to "processing".
 *
 * @since 1.0.0
 * @param int    $order_id The ID of the WooCommerce order.
 * @param string $old_status The previous order status.
 * @param string $new_status The new order status.
 * @return void
 */
function mctwc_mailchimp_process_order( $order_id, $old_status, $new_status ) {
	if ( 'processing' !== $new_status || 'processing' === $old_status ) {
		mctwc_log("Order $order_id status change ($old_status -> $new_status) doesn't trigger processing");
		return;
	}

	mctwc_log("Processing order $order_id: status changed from $old_status to $new_status");

	$settings          = get_option( MCTWC_SETTINGS_KEY );
	$mailchimp_api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
	$list_id           = isset($settings['list_id']) ? $settings['list_id'] : '';
	$global_tag        = isset($settings['global_tag']) ? $settings['global_tag'] : '';

	if ( empty($mailchimp_api_key) || empty($list_id) ) {
		mctwc_log('Missing API key or list ID - aborting order processing');
		return;
	}

	try {
		$mailchimp = new \DrewM\MailChimp\MailChimp($mailchimp_api_key);
	} catch ( Exception $e ) {
		mctwc_log( 'Failed to initialize Mailchimp: ' . $e->getMessage(), 'error' );
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		mctwc_log( "Could not retrieve order $order_id" );
		return;
	}
	$user_email = $order->get_billing_email();
	if ( empty($user_email) ) {
		mctwc_log("No email address found for order $order_id");
		return;
	}

	$user_first_name = apply_filters(
		'mctwc_subscriber_first_name',
		trim( $order->get_billing_first_name() ),
		$order
	);

	$user_last_name = apply_filters(
		'mctwc_subscriber_last_name',
		trim( $order->get_billing_last_name() ),
		$order
	);

	mctwc_log("Processing tags for: $user_email ($user_first_name $user_last_name)");

	$subscriber_hash = $mailchimp->subscriberHash($user_email);

	$member = $mailchimp->get("lists/$list_id/members/$subscriber_hash");

	$member_exists  = false;
	$current_status = null;

	if ( $mailchimp->success() ) {
		$member_exists  = true;
		$current_status = isset($member['status']) ? $member['status'] : null;
		mctwc_log("Found existing member $user_email with status: " . $current_status);

		$needs_update = false;
		if ( isset($member['merge_fields']) ) {
			if ( $member['merge_fields']['FNAME'] !== $user_first_name ||
				$member['merge_fields']['LNAME'] !== $user_last_name ) {
				$needs_update = true;
			}
		} else {
			$needs_update = true;
		}

		if ( $needs_update ) {
			$update_result = $mailchimp->patch("lists/$list_id/members/$subscriber_hash", array(
				'merge_fields' => array(
					'FNAME' => $user_first_name,
					'LNAME' => $user_last_name,
				),
			));

			if ( ! $mailchimp->success() ) {
				mctwc_log( 'Warning: Could not update merge fields - ' . $mailchimp->getLastError(), 'warning' );
			} else {
				mctwc_log("Successfully updated merge fields for $user_email");
			}
		}
	} else {
		mctwc_log("Member $user_email not found in Mailchimp list");
	}

	$items = $order->get_items();
	mctwc_log('Processing ' . count($items) . " items for order $order_id");

	$tags_to_apply = array();

	foreach ( $items as $item ) {
		$variation_id = $item->get_variation_id();
		$parent_id    = $item->get_product_id();
		$product_name = $item->get_name();

		mctwc_log( "Processing item: $product_name (Product ID: $parent_id, Variation ID: $variation_id)" );

		$product_tag = get_post_meta( $parent_id, MCTWC_META_KEY_PRODUCT_TAG, true );
		if ( ! empty( $product_tag ) ) {
			mctwc_log( "Found product tag '$product_tag' for product ID $parent_id" );
			$tags_to_apply[] = array(
				'name'   => $product_tag,
				'status' => 'active',
			);
		}

		if ( $variation_id ) {
			$variation_tag = get_post_meta( $variation_id, MCTWC_META_KEY_VARIATION_TAG, true );
			if ( ! empty( $variation_tag ) ) {
				mctwc_log( "Found variation tag '$variation_tag' for variation ID $variation_id" );
				$tags_to_apply[] = array(
					'name'   => $variation_tag,
					'status' => 'active',
				);
			}
		}

		if ( empty( $product_tag ) && empty( $variation_tag ) ) {
			mctwc_log( "No tags found for product ID $parent_id", 'debug' );
		}
	}

	// Add global tag if configured.
	if ( ! empty( $global_tag ) ) {
		mctwc_log( "Adding global tag '$global_tag'" );
		$tags_to_apply[] = array(
			'name'   => $global_tag,
			'status' => 'active',
		);
	}

	$tags_to_apply = array_unique( $tags_to_apply, SORT_REGULAR );
	if ( ! empty($tags_to_apply) ) {
		if ( ! $member_exists ) {
			mctwc_log('Creating transactional contact to apply tags');

			$result = $mailchimp->put("lists/$list_id/members/$subscriber_hash", array(
				'email_address' => $user_email,
				'status_if_new' => 'transactional',
				'merge_fields'  => array(
					'FNAME' => $user_first_name,
					'LNAME' => $user_last_name,
				),
			));

			if ( ! $mailchimp->success() ) {
				mctwc_log('Failed to create contact: ' . $mailchimp->getLastError());
				return;
			} else {
				mctwc_log("Created new transactional contact for $user_email");
			}
		}

		try {
			$tag_result = $mailchimp->post("lists/$list_id/members/$subscriber_hash/tags", array(
				'tags' => $tags_to_apply,
			));

			if ( ! $mailchimp->success() ) {
				mctwc_log( 'Mailchimp API Error (Adding Tags): ' . $mailchimp->getLastError(), 'error' );
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

/**
 * Add Mailchimp tag field to WooCommerce product variations.
 *
 * @since 1.0.0
 * @param int     $loop The current loop index.
 * @param array   $variation_data The variation data.
 * @param WP_Post $variation The variation post object.
 * @return void
 */
function mctwc_add_variation_field( $loop, $variation_data, $variation ) {
	$current_value = get_post_meta($variation->ID, MCTWC_META_KEY_VARIATION_TAG, true);

	woocommerce_wp_text_input(
		array(
			'id'          => "_mctwc_mailchimp_tag_{$loop}",
			'name'        => "mctwc_mailchimp_tag[{$variation->ID}]",
			'value'       => $current_value,
			'label'       => __('Mailchimp Tag', 'mctwc'),
			'desc_tip'    => true,
			'description' => __('Enter the Mailchimp tag for this variation.', 'mctwc'),
		)
	);
}
add_action('woocommerce_product_after_variable_attributes', 'mctwc_add_variation_field', 10, 3);

/**
 * Save Mailchimp tag field for product variations.
 *
 * @since 1.0.0
 * @param int $variation_id The ID of this product variant.
 * @param int $loop_index The loop index for the current variation.
 * @return void
 */
function mctwc_save_variation_tag( $variation_id, $loop_index ) {
	if ( ! current_user_can( 'edit_products' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification for variation saves.
	if ( isset( $_POST['mctwc_mailchimp_tag'][ $variation_id ] ) ) {
		$tag = sanitize_text_field( wp_unslash( $_POST['mctwc_mailchimp_tag'][ $variation_id ] ) );
		update_post_meta( $variation_id, MCTWC_META_KEY_VARIATION_TAG, $tag );
	} else {
		delete_post_meta( $variation_id, MCTWC_META_KEY_VARIATION_TAG );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
}
add_action( 'woocommerce_save_product_variation', 'mctwc_save_variation_tag', 10, 2 );

/**
 * Add Mailchimp tag field to WooCommerce products (simple and variable).
 *
 * Displays in the General tab of the product edit screen.
 *
 * @since 1.0.0
 * @return void
 */
function mctwc_add_product_field() {
	global $post;

	$current_value = get_post_meta( $post->ID, MCTWC_META_KEY_PRODUCT_TAG, true );

	woocommerce_wp_text_input(
		array(
			'id'          => '_mctwc_mailchimp_product_tag',
			'name'        => '_mctwc_mailchimp_product_tag',
			'value'       => $current_value,
			'label'       => __( 'Mailchimp Tag', 'mctwc' ),
			'desc_tip'    => true,
			'description' => __( 'Tag applied when this product is purchased. For variable products, this tag applies to all variations.', 'mctwc' ),
		)
	);
}
add_action( 'woocommerce_product_options_general_product_data', 'mctwc_add_product_field' );

/**
 * Save Mailchimp tag field for products.
 *
 * @since 1.0.0
 * @param int $post_id The product post ID.
 * @return void
 */
function mctwc_save_product_tag( $post_id ) {
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['woocommerce_meta_nonce'] ) ||
		! wp_verify_nonce( sanitize_key( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) {
		return;
	}

	if ( isset( $_POST['_mctwc_mailchimp_product_tag'] ) ) {
		$tag = sanitize_text_field( wp_unslash( $_POST['_mctwc_mailchimp_product_tag'] ) );
		update_post_meta( $post_id, MCTWC_META_KEY_PRODUCT_TAG, $tag );
	} else {
		delete_post_meta( $post_id, MCTWC_META_KEY_PRODUCT_TAG );
	}
}
add_action( 'woocommerce_process_product_meta', 'mctwc_save_product_tag' );
