<?php
/**
 * Uninstall script for Purchase Tagger - Product-Based Mailchimp Tags.
 *
 * Removes all plugin data when the plugin is deleted.
 *
 * @package PurchaseTagger
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'woocommerce_mailchimp-tags_settings' );
delete_option( 'mctwc_version' );

delete_transient( 'mctwc_api_status' );

delete_post_meta_by_key( '_mctwc_mailchimp_tag' );
delete_post_meta_by_key( '_mctwc_mailchimp_product_tag' );

delete_metadata( 'user', 0, 'mctwc_dismissed_config_notice', '', true );
delete_metadata( 'user', 0, 'mctwc_dismissed_invalid_notice', '', true );

wp_clear_scheduled_hook( 'mctwc_daily_health_check' );
