<?php
/**
 * Plugin Name: Purchase Tagger - Product-Based Mailchimp Tags
 * Requires Plugins: woocommerce
 * Description: Assign tags to Mailchimp contacts based on specific items purchased from your WooCommerce shop.
 * Version: 1.2.0
 * Author: Jess A.
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: purchase-tagger-for-mailchimp
 *
 * @package PurchaseTagger
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

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

$mctwc_autoload_path = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
if ( ! file_exists( $mctwc_autoload_path ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="error"><p><strong>Purchase Tagger - Product-Based Mailchimp Tags:</strong> Dependencies not installed. Please run <code>composer install</code> in the plugin directory, or install the plugin from WordPress.org.</p></div>';
		}
	);
	return;
}
require_once $mctwc_autoload_path;
require_once plugin_dir_path( __FILE__ ) . 'class-mctwc-mailchimp-tags-integration.php';

/**
 * Get a Mailchimp API instance.
 *
 * Centralizes Mailchimp client initialization with consistent error handling.
 *
 * @since 1.1.0
 * @param string $api_key Optional. The API key to use. If empty, uses saved settings.
 * @return \DrewM\MailChimp\MailChimp|WP_Error Mailchimp instance or WP_Error on failure.
 */
function mctwc_get_mailchimp( $api_key = '' ) {
	if ( empty( $api_key ) ) {
		$settings = get_option( MCTWC_SETTINGS_KEY );
		$api_key  = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
	}

	if ( empty( $api_key ) ) {
		return new WP_Error( 'missing_api_key', __( 'Mailchimp API key is not configured.', 'purchase-tagger-for-mailchimp' ) );
	}

	try {
		return new \DrewM\MailChimp\MailChimp( $api_key );
	} catch ( Exception $e ) {
		return new WP_Error( 'mailchimp_init_failed', $e->getMessage() );
	}
}

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
		__( 'Settings', 'purchase-tagger-for-mailchimp' )
	);

	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mctwc_add_plugin_settings_link' );

/**
 * Log a message using WooCommerce logger.
 *
 * @since 1.0.0
 * @param string $message The message to log.
 * @param string $level   Log level: emergency|alert|critical|error|warning|notice|info|debug.
 * @return void
 */
function mctwc_log( $message, $level = 'info' ) {
	$always_log_levels = array( 'emergency', 'alert', 'critical', 'error', 'warning', 'notice' );

	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		if ( ! in_array( $level, $always_log_levels, true ) ) {
			return;
		}
	}

	$logger  = wc_get_logger();
	$context = array( 'source' => 'purchase-tagger-for-mailchimp' );
	$logger->log( $level, $message, $context );
}

add_action(
	'woocommerce_order_status_changed',
	'mctwc_mailchimp_process_order',
	15,
	3,
);

/**
 * Run upgrade routines when plugin version changes.
 *
 * @since 1.1.0
 * @return void
 */
function mctwc_check_version() {
	$installed_version = get_option( 'mctwc_version', '0' );
	$current_version   = mctwc_get_version();

	if ( version_compare( $installed_version, $current_version, '<' ) ) {
		mctwc_run_upgrades( $installed_version );
		update_option( 'mctwc_version', $current_version );
	}
}
add_action( 'plugins_loaded', 'mctwc_check_version', 20 );

/**
 * Run version-specific upgrade routines.
 *
 * @since 1.1.0
 * @param string $installed_version The previously installed version.
 * @return void
 */
function mctwc_run_upgrades( $installed_version ) {
	if ( version_compare( $installed_version, '1.1.0', '<' ) ) {
		if ( ! wp_next_scheduled( 'mctwc_daily_health_check' ) ) {
			wp_schedule_event( time(), 'daily', 'mctwc_daily_health_check' );
		}
		mctwc_log( 'Upgraded to 1.1.0: Scheduled daily health check' );
	}
}

/**
 * Schedule the API key health check cron job on plugin activation.
 *
 * @since 1.1.0
 * @return void
 */
function mctwc_activate() {
	if ( ! wp_next_scheduled( 'mctwc_daily_health_check' ) ) {
		wp_schedule_event( time(), 'daily', 'mctwc_daily_health_check' );
	}
}
register_activation_hook( MCTWC_PLUGIN_FILE, 'mctwc_activate' );

/**
 * Clear scheduled cron jobs on plugin deactivation.
 *
 * @since 1.1.0
 * @return void
 */
function mctwc_deactivate() {
	wp_clear_scheduled_hook( 'mctwc_daily_health_check' );
}
register_deactivation_hook( MCTWC_PLUGIN_FILE, 'mctwc_deactivate' );

/**
 * Perform the daily API key health check.
 *
 * Validates the stored API key and caches the result.
 * Runs via WP-Cron to detect revoked keys, database issues, etc.
 *
 * @since 1.1.0
 * @return void
 */
function mctwc_perform_health_check() {
	$mailchimp = mctwc_get_mailchimp();

	if ( is_wp_error( $mailchimp ) ) {
		if ( 'missing_api_key' === $mailchimp->get_error_code() ) {
			delete_transient( 'mctwc_api_status' );
			mctwc_log( 'Health check: No API key configured', 'debug' );
		} else {
			set_transient( 'mctwc_api_status', 'invalid', DAY_IN_SECONDS );
			mctwc_log( 'Health check failed: ' . $mailchimp->get_error_message(), 'error' );
		}
		return;
	}

	$mailchimp->get( 'ping' );

	if ( $mailchimp->success() ) {
		set_transient( 'mctwc_api_status', 'valid', DAY_IN_SECONDS );
		mctwc_log( 'Health check: API key is valid' );
	} else {
		set_transient( 'mctwc_api_status', 'invalid', DAY_IN_SECONDS );
		mctwc_log( 'Health check: API key is invalid - ' . $mailchimp->getLastError(), 'error' );
	}
}
add_action( 'mctwc_daily_health_check', 'mctwc_perform_health_check' );

/**
 * Clear API status cache and notice dismissals when settings are updated.
 *
 * @since 1.1.0
 * @param mixed $old_value The old option value.
 * @param mixed $new_value The new option value.
 * @return void
 */
function mctwc_on_settings_update( $old_value, $new_value ) {
	$old_key  = isset( $old_value['api_key'] ) ? $old_value['api_key'] : '';
	$new_key  = isset( $new_value['api_key'] ) ? $new_value['api_key'] : '';
	$old_list = isset( $old_value['list_id'] ) ? $old_value['list_id'] : '';
	$new_list = isset( $new_value['list_id'] ) ? $new_value['list_id'] : '';

	if ( $old_key !== $new_key ) {
		delete_transient( 'mctwc_api_status' );

		if ( ! empty( $new_key ) ) {
			wp_schedule_single_event( time(), 'mctwc_daily_health_check' );
		}
	}

	if ( $old_key !== $new_key || $old_list !== $new_list ) {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			delete_user_meta( $user_id, 'mctwc_dismissed_config_notice' );
			delete_user_meta( $user_id, 'mctwc_dismissed_invalid_notice' );
		}
	}
}
add_action( 'update_option_' . MCTWC_SETTINGS_KEY, 'mctwc_on_settings_update', 10, 2 );

/**
 * Display admin notices for configuration issues.
 *
 * @since 1.1.0
 * @return void
 */
function mctwc_admin_notices() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$relevant_screens = array(
		'woocommerce_page_wc-settings',
		'product',
		'edit-product',
		'shop_order',
		'woocommerce_page_wc-orders',
		'edit-shop_order',
		'dashboard',
	);

	$is_wc_screen = strpos( $screen->id, 'woocommerce' ) !== false;
	$is_relevant  = in_array( $screen->id, $relevant_screens, true ) || $is_wc_screen;

	if ( ! $is_relevant ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL parameter for display logic only.
	$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
	if ( 'mailchimp-tags' === $section ) {
		return;
	}

	$settings     = get_option( MCTWC_SETTINGS_KEY );
	$api_key      = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
	$list_id      = isset( $settings['list_id'] ) ? $settings['list_id'] : '';
	$settings_url = admin_url( 'admin.php?page=wc-settings&tab=integration&section=mailchimp-tags' );
	$user_id      = get_current_user_id();

	if ( empty( $api_key ) || empty( $list_id ) ) {
		$dismissed_until = get_user_meta( $user_id, 'mctwc_dismissed_config_notice', true );
		if ( $dismissed_until && time() < (int) $dismissed_until ) {
			return;
		}

		if ( empty( $api_key ) && empty( $list_id ) ) {
			$message = __( 'API key and audience are not configured. Mailchimp tags will not sync.', 'purchase-tagger-for-mailchimp' );
		} elseif ( empty( $api_key ) ) {
			$message = __( 'API key is not configured. Mailchimp tags will not sync.', 'purchase-tagger-for-mailchimp' );
		} else {
			$message = __( 'Mailchimp audience is not selected. Tags will not sync.', 'purchase-tagger-for-mailchimp' );
		}
		?>
		<div class="notice notice-warning is-dismissible" data-mctwc-notice="config">
			<p>
				<strong><?php esc_html_e( 'Purchase Tagger:', 'purchase-tagger-for-mailchimp' ); ?></strong>
				<?php echo esc_html( $message ); ?>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Configure settings', 'purchase-tagger-for-mailchimp' ); ?></a>
			</p>
		</div>
		<?php
		return;
	}

	$api_status = get_transient( 'mctwc_api_status' );
	if ( 'invalid' === $api_status ) {
		$dismissed_until = get_user_meta( $user_id, 'mctwc_dismissed_invalid_notice', true );
		if ( $dismissed_until && time() < (int) $dismissed_until ) {
			return;
		}
		?>
		<div class="notice notice-error is-dismissible" data-mctwc-notice="invalid">
			<p>
				<strong><?php esc_html_e( 'Purchase Tagger:', 'purchase-tagger-for-mailchimp' ); ?></strong>
				<?php esc_html_e( 'Your Mailchimp API key appears to be invalid or revoked. Tags will not sync until this is resolved.', 'purchase-tagger-for-mailchimp' ); ?>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Check settings', 'purchase-tagger-for-mailchimp' ); ?></a>
			</p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'mctwc_admin_notices' );

/**
 * Enqueue admin scripts for notice dismissal.
 *
 * @since 1.1.0
 * @return void
 */
function mctwc_enqueue_notice_scripts() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	wp_add_inline_script(
		'jquery',
		"jQuery(document).ready(function($) {
			$(document).on('click', '.notice[data-mctwc-notice] .notice-dismiss', function() {
				var noticeType = $(this).closest('.notice').data('mctwc-notice');
				$.post(ajaxurl, {
					action: 'mctwc_dismiss_notice',
					notice_type: noticeType,
					nonce: '" . wp_create_nonce( 'mctwc_dismiss_notice' ) . "'
				});
			});
		});"
	);
}
add_action( 'admin_enqueue_scripts', 'mctwc_enqueue_notice_scripts' );

/**
 * AJAX handler for dismissing admin notices.
 *
 * @since 1.1.0
 * @return void
 */
function mctwc_ajax_dismiss_notice() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mctwc_dismiss_notice' ) ) {
		wp_send_json_error( 'Invalid nonce', 403 );
	}

	$notice_type = isset( $_POST['notice_type'] ) ? sanitize_key( $_POST['notice_type'] ) : '';
	$user_id     = get_current_user_id();

	if ( ! $user_id || ! $notice_type ) {
		wp_send_json_error( 'Invalid request' );
	}

	// Dismiss for 7 days.
	$dismiss_until = time() + ( 7 * DAY_IN_SECONDS );

	switch ( $notice_type ) {
		case 'config':
			update_user_meta( $user_id, 'mctwc_dismissed_config_notice', $dismiss_until );
			break;
		case 'invalid':
			update_user_meta( $user_id, 'mctwc_dismissed_invalid_notice', $dismiss_until );
			break;
	}

	wp_send_json_success();
}
add_action( 'wp_ajax_mctwc_dismiss_notice', 'mctwc_ajax_dismiss_notice' );

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
		mctwc_log( "Order $order_id status change ($old_status -> $new_status) doesn't trigger processing" );
		return;
	}

	mctwc_log( "Processing order $order_id: status changed from $old_status to $new_status" );

	$settings   = get_option( MCTWC_SETTINGS_KEY );
	$list_id    = isset( $settings['list_id'] ) ? $settings['list_id'] : '';
	$global_tag = isset( $settings['global_tag'] ) ? $settings['global_tag'] : '';

	if ( empty( $list_id ) ) {
		mctwc_log( 'Missing list ID - aborting order processing' );
		return;
	}

	$mailchimp = mctwc_get_mailchimp();

	if ( is_wp_error( $mailchimp ) ) {
		mctwc_log( 'Failed to initialize Mailchimp: ' . $mailchimp->get_error_message(), 'error' );
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		mctwc_log( "Could not retrieve order $order_id" );
		return;
	}
	$user_email = apply_filters(
		'mctwc_subscriber_email',
		$order->get_billing_email(),
		$order
	);
	if ( empty( $user_email ) ) {
		mctwc_log( "No email address found for order $order_id" );
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

	mctwc_log( "Processing tags for: $user_email ($user_first_name $user_last_name)" );

	$subscriber_hash = $mailchimp->subscriberHash( $user_email );

	$member = $mailchimp->get( "lists/$list_id/members/$subscriber_hash" );

	$member_exists  = false;
	$current_status = null;

	if ( $mailchimp->success() ) {
		$member_exists  = true;
		$current_status = isset( $member['status'] ) ? $member['status'] : null;
		mctwc_log( "Found existing member $user_email with status: " . $current_status );

		$needs_update = false;
		if ( isset( $member['merge_fields'] ) ) {
			if ( $member['merge_fields']['FNAME'] !== $user_first_name ||
				$member['merge_fields']['LNAME'] !== $user_last_name ) {
				$needs_update = true;
			}
		} else {
			$needs_update = true;
		}

		if ( $needs_update ) {
			$update_result = $mailchimp->patch(
				"lists/$list_id/members/$subscriber_hash",
				array(
					'merge_fields' => array(
						'FNAME' => $user_first_name,
						'LNAME' => $user_last_name,
					),
				)
			);

			if ( ! $mailchimp->success() ) {
				mctwc_log( 'Warning: Could not update merge fields - ' . $mailchimp->getLastError(), 'warning' );
			} else {
				mctwc_log( "Successfully updated merge fields for $user_email" );
			}
		}
	} else {
		mctwc_log( "Member $user_email not found in Mailchimp list" );
	}

	$items = $order->get_items();
	mctwc_log( 'Processing ' . count( $items ) . " items for order $order_id" );

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

	if ( ! empty( $global_tag ) ) {
		mctwc_log( "Adding global tag '$global_tag'" );
		$tags_to_apply[] = array(
			'name'   => $global_tag,
			'status' => 'active',
		);
	}

	$tags_to_apply = array_unique( $tags_to_apply, SORT_REGULAR );
	if ( ! empty( $tags_to_apply ) ) {
		if ( ! $member_exists ) {
			mctwc_log( 'Creating transactional contact to apply tags' );

			$result = $mailchimp->put(
				"lists/$list_id/members/$subscriber_hash",
				array(
					'email_address' => $user_email,
					'status_if_new' => 'transactional',
					'merge_fields'  => array(
						'FNAME' => $user_first_name,
						'LNAME' => $user_last_name,
					),
				)
			);

			if ( ! $mailchimp->success() ) {
				mctwc_log( 'Failed to create contact: ' . $mailchimp->getLastError() );
				return;
			} else {
				mctwc_log( "Created new transactional contact for $user_email", 'notice' );
			}
		}

		try {
			$tag_result = $mailchimp->post(
				"lists/$list_id/members/$subscriber_hash/tags",
				array(
					'tags' => $tags_to_apply,
				)
			);

			if ( ! $mailchimp->success() ) {
				mctwc_log( 'Mailchimp API Error (Adding Tags): ' . $mailchimp->getLastError(), 'error' );
			} else {
				$tag_names = array_column( $tags_to_apply, 'name' );
				mctwc_log( "Successfully added tags to $user_email: " . implode( ', ', $tag_names ), 'notice' );
			}
		} catch ( Exception $e ) {
			mctwc_log( 'Exception when adding tags: ' . $e->getMessage() );
		}
	} else {
		mctwc_log( "No tags to apply for order $order_id" );
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
	$current_value = get_post_meta( $variation->ID, MCTWC_META_KEY_VARIATION_TAG, true );

	woocommerce_wp_text_input(
		array(
			'id'          => "_mctwc_mailchimp_tag_{$loop}",
			'name'        => "mctwc_mailchimp_tag[{$variation->ID}]",
			'value'       => $current_value,
			'label'       => __( 'Mailchimp Tag', 'purchase-tagger-for-mailchimp' ),
			'desc_tip'    => true,
			'description' => __( 'Enter the Mailchimp tag for this variation.', 'purchase-tagger-for-mailchimp' ),
		)
	);
}
add_action( 'woocommerce_product_after_variable_attributes', 'mctwc_add_variation_field', 10, 3 );

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
			'label'       => __( 'Mailchimp Tag', 'purchase-tagger-for-mailchimp' ),
			'desc_tip'    => true,
			'description' => __( 'Tag applied when this product is purchased. For variable products, this tag applies to all variations.', 'purchase-tagger-for-mailchimp' ),
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
