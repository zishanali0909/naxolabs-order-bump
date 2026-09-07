=== WP Order Bump ===
Contributors: zishanali0909
Tags: woocommerce, order bump, checkout upsell, cart bump, upsell
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add smart order bumps to your WooCommerce checkout page — boost revenue with one-click upsells.

== Description ==

**WP Order Bump** lets you create beautiful, conversion-optimized order bumps that display on your WooCommerce checkout page. When customers check a box, the bump product is instantly added to their cart at a special discounted price.

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
* **Block & Classic Checkout** — Compatible with both WooCommerce checkout types
* **HPOS Compatible** — Works with WooCommerce High-Performance Order Storage

= How It Works =

1. Create an Order Bump from the admin dashboard
2. Add one or more products with optional discounts
3. Customize the design, description, and display rules
4. Save and activate — bumps appear on your checkout page instantly!

== Installation ==

1. Upload the `wp-order-bump` folder to `/wp-content/plugins/`
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

= Is it compatible with other plugins and themes? =

Yes! WP Order Bump is designed with full isolation — all CSS classes, JS variables, and PHP functions are prefixed to prevent conflicts with any theme or plugin.

== Screenshots ==

1. Admin Dashboard — Manage all your order bumps
2. Edit Bump — Design tab with live preview
3. Products Tab — Add products with discounts
4. Rules Tab — Configure display conditions
5. Checkout — How bumps appear to customers

== Changelog ==

= 1.0.0 =
* Initial public release
* Multiple skins (Classic & Modern)
* Per-product discounts (percentage & flat)
* Smart display rules (product, category, minimum order)
* Custom badges and ribbons
* Product images with configurable width and position
* Live admin preview
* AJAX add/remove on checkout (no page reload)
* Per-product rich text descriptions
* Drag & drop product reorder
* Block & Classic checkout support
* HPOS compatibility
* Full input validation and security hardening
* WordPress.org coding standards compliance

== Upgrade Notice ==

= 1.0.0 =
Initial release — install and start boosting your checkout revenue!
