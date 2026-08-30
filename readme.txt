=== Order Bump Pro ===
Contributors: zishanali0909
Tags: woocommerce, order bump, checkout upsell, cart bump, upsell
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add smart order bumps to your WooCommerce checkout page — boost revenue with one-click upsells like FunnelKit.

== Description ==

**Order Bump Pro** lets you create beautiful, conversion-optimized order bumps that display on your WooCommerce checkout page. When customers check a box, the bump product is instantly added to their cart at a special discounted price.

= Key Features =

* **Multiple Skins** — Choose between classic yellow header or modern teal border layouts
* **Per-Product Discounts** — Set percentage or flat discounts for each bump product
* **Smart Display Rules** — Show bumps based on cart items, categories, or minimum order total
* **Custom Badges** — Add eye-catching ribbons like "Special Offer" or "Limited Deal"
* **Product Images** — Display product or custom images with configurable width and position
* **Live Preview** — See exactly how your bump will look while editing
* **AJAX Add to Cart** — Smooth, no-reload experience on checkout
* **Per-Product Descriptions** — Rich text descriptions with full WP Editor support
* **Drag & Drop Reorder** — Easily reorder products within a bump
* **Fully Responsive** — Works perfectly on mobile and desktop

= How It Works =

1. Create an Order Bump from the admin dashboard
2. Add one or more products with optional discounts
3. Customize the design, description, and display rules
4. Save and activate — bumps appear on your checkout page instantly!

== Installation ==

1. Upload the `order-bump-pro` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Make sure WooCommerce is installed and active
4. Navigate to **Order Bumps** in the admin sidebar to create your first bump

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes, WooCommerce must be installed and active. The plugin will show an admin notice if WooCommerce is not found.

= Can I add multiple products to one bump? =

Yes! Each bump can contain multiple products, each with its own discount and description.

= Does the discount actually apply in the cart? =

Yes, discounted prices are correctly applied using WooCommerce's `woocommerce_before_calculate_totals` hook.

= Can I control when bumps appear? =

Yes, you can set display rules based on:
* All orders (always show)
* Specific products in cart
* Product categories in cart
* Minimum cart total

== Screenshots ==

1. Admin Dashboard — Manage all your order bumps
2. Edit Bump — Design tab with live preview
3. Products Tab — Add products with discounts
4. Rules Tab — Configure display conditions
5. Checkout — How bumps appear to customers

== Changelog ==

= 2.1.0 =
* Improved: Admin class split into separate template files
* Improved: All strings now translatable (i18n ready)
* Improved: Inline JavaScript moved to external file
* Added: Frontend query caching with transients
* Added: readme.txt for WordPress.org standards
* Added: Drag & drop product reorder support

= 2.0.1 =
* Fixed: Discount prices now correctly apply in WooCommerce cart
* Fixed: Custom image URL field name mismatch
* Fixed: Duplicate AJAX handler conflict
* Added: Badge/Ribbon UI controls in Design tab
* Added: WooCommerce dependency runtime check
* Added: Dynamic stock status checking
* Added: Behaviour radio with Replace option
* Added: uninstall.php for data cleanup
* Added: Deactivation hook

= 2.0.0 =
* Initial release

== Upgrade Notice ==

= 2.1.0 =
Major quality update: i18n support, cleaner code structure, performance improvements.

= 2.0.1 =
Critical fix: Discount prices now correctly apply in cart. Please update immediately.
