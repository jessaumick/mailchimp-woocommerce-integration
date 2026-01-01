<?php
/**
 * Uninstall script for Purchase Tagger - Product-Based Mailchimp Tags.
 *
 * Removes all plugin data when the plugin is deleted.
 *
 * @package ProductTagger
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'woocommerce_mailchimp-tags_settings' );
delete_post_meta_by_key( '_mctwc_mailchimp_tag' );
delete_post_meta_by_key( '_mctwc_mailchimp_product_tag' );
