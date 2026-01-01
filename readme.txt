=== Purchase Tagger - Product-Based Mailchimp Tags ===
Contributors: jessaumick
Tags: mailchimp, woocommerce, tags, email marketing, automation
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 10.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Assign Mailchimp tags to contacts based on WooCommerce purchases.

== Description ==

Purchase Tagger is a lightweight, no-bloat plugin for tagging your Mailchimp contacts based on what they purchase from your store. Tagging is supported for product variations, product parents, simple products, and global purchases.

**Features:**

* Assign tags to simple products
* Assign tags to variable products (applies to all variations)
* Assign unique tags to individual variations
* Automatically creates transactional contacts for new customers
* Updates existing Mailchimp contacts without changing their subscription status

**Use Cases:**

* Use global tagging to track all contacts with purchase history
* Segment customers by specific product purchase history
* Trigger automations and add customers to journeys based on purchase behavior
* Track which products a customer has bought

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Settings > Integration > Mailchimp Tags
4. Enter your Mailchimp API key and select your audience
5. Add tags to your products in the product editor

== Frequently Asked Questions ==

= Where do I find my Mailchimp API key? =

In your Mailchimp account, go to Account > Extras > API keys. [Learn more](https://mailchimp.com/help/about-api-keys/)

= Can I add tags to both the product and its variations? =

Yes! When a customer purchases a variation, they receive both the parent product tag and the variation-specific tag.

= What happens if a customer isn't in my Mailchimp audience? =

The plugin creates them as a transactional contact (non-subscribed) and applies the tags. This won't subscribe them to marketing emails.

= Does this change a contact's subscription status? =

No. If a contact is already subscribed, unsubscribed, or cleaned, their status remains unchanged.

== Privacy Policy ==

This plugin sends customer data (email address, first name, last name) to Mailchimp's servers when an order is processed. This data is used to create or update contacts and apply tags in your Mailchimp audience.

Please ensure your store's privacy policy discloses this data sharing. For more information, see [Mailchimp's Privacy Policy](https://mailchimp.com/legal/privacy/).

== Screenshots ==

1. Plugin settings page
2. Product tag field in the General tab
3. Variation tag fields

== Changelog ==

= 1.0.0 =
* Initial release
* Global tagging to assign common tag to all purchases
* Product-level tagging for simple and variable products
* Variation-level tagging
* Automatic transactional contact creation for new customers

== Upgrade Notice ==

= 1.0.0 =
Initial release.