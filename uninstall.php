<?php
/**
 * Uninstall script for MailChimp Tags for WooCommerce.
 *
 * Removes all plugin data when the plugin is deleted.
 *
 * @package MailChimpTagsForWooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'woocommerce_mailchimp-tags_settings' );
