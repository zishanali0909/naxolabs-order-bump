=== Naxolabs Order Bump ===
Contributors: zishanali0909
Tags: woocommerce, order bump, checkout upsell, one click upsell, sales boost
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add smart order bumps to your WooCommerce checkout page to boost revenue with one-click upsells.

== Description ==

**Naxolabs Order Bump** lets you create beautiful, conversion-optimized order bumps that display on your WooCommerce checkout page. When customers check a box, the bump product is instantly added to their cart at a special discounted price.

Increase your average order value effortlessly by offering relevant products right before checkout.

= Key Features =

* **2 Beautiful Skins** - Classic yellow header and modern teal border layouts
* **Per-Product Discounts** - Set percentage or flat discounts for each bump product
* **Smart Display Rules** - Show bumps based on cart items, categories, or minimum order total
* **Custom Badges** - Add eye-catching ribbons like "Special Offer" or "Limited Deal"
* **Product Images** - Display product or custom images with configurable width and position (left/right)
* **Live Admin Preview** - See exactly how your bump will look while editing in real-time
* **AJAX Add to Cart** - Smooth, no-reload experience on checkout
* **Per-Product Descriptions** - Rich text descriptions with full WordPress Editor support
* **Revenue Tracking** - Track bump revenue directly in your admin dashboard
* **Overlap Detection** - Smart warnings if bump product matches trigger product
* **Fully Responsive** - Works perfectly on mobile, tablet, and desktop
* **Block and Classic Checkout** - Compatible with both WooCommerce checkout types
* **HPOS Compatible** - Works with WooCommerce High-Performance Order Storage
* **Developer Friendly** - Extendable via filters and action hooks

= Free Version Includes =

* Up to 2 order bumps per store
* Up to 2 products per bump
* All display rules (all orders, cart items, cart categories, minimum order total)
* 2 design skins with full color customization
* Product images with position control
* Custom badges and descriptions
* AJAX checkout integration
* Revenue tracking dashboard

= How It Works =

1. Navigate to **WooCommerce > Order Bumps** in your admin dashboard
2. Click **Add New Order Bump** and give it a name
3. Go to **Products** tab - search and add products with optional discounts
4. Go to **Design** tab - customize headline, description, badge, colors, and image
5. Go to **Rules** tab - choose when to display (always, specific products, categories, or minimum order)
6. Click **Save** - your bump appears on the checkout page instantly!

= Use Cases =

* Offer a warranty or insurance product at checkout
* Upsell complementary products (e.g., phone case with a phone)
* Promote digital downloads alongside physical products
* Offer a discount on a related product to increase cart value

== Installation ==

1. Upload the `naxolabs-order-bump` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Make sure **WooCommerce** is installed and active
4. Navigate to **WooCommerce > Order Bumps** in the admin sidebar
5. Click **Add New Order Bump** to create your first bump

= Minimum Requirements =

* WordPress 5.8 or later
* WooCommerce 6.0 or later
* PHP 7.4 or later

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes, WooCommerce must be installed and active. The plugin will show an admin notice if WooCommerce is not found.

= How many order bumps can I create? =

The free version supports up to 2 order bumps, each containing up to 2 products.

= Can I add multiple products to one bump? =

Yes! Each bump can contain up to 2 products, each with its own discount and description.

= Does the discount actually apply in the cart? =

Yes, discounted prices are correctly applied during cart calculation using WooCommerce standard hooks. The discount is visible in the cart, checkout, and order confirmation.

= Can I control when bumps appear? =

Yes, you can set display rules based on:
* **All orders** - always show the bump
* **Specific products in cart** - show only when certain products are in the cart
* **Product categories in cart** - show based on cart product categories
* **Minimum cart total** - show only when cart value exceeds a threshold

= Does it work with the new WooCommerce Block Checkout? =

Yes! Naxolabs Order Bump is compatible with both the classic shortcode checkout and the newer WooCommerce Block-based checkout.

= Is it compatible with HPOS (High-Performance Order Storage)? =

Yes, the plugin fully declares HPOS compatibility and works seamlessly with WooCommerce High-Performance Order Storage.

= Will it conflict with my theme or other plugins? =

Naxolabs Order Bump is designed with full isolation. All CSS classes use the obp- prefix, all JavaScript is namespaced, and all PHP functions use the obp_ prefix to prevent any conflicts.

= Does it track revenue from order bumps? =

Yes! The admin dashboard shows total bump revenue from completed and processing orders.

= What happens when I deactivate or delete the plugin? =

When deactivated, bumps simply stop showing on checkout. Your bump configurations are preserved. When deleted, only temporary cache data is removed. Your bumps remain in the database in case you reinstall.

== Screenshots ==

1. Admin Dashboard - Manage all your order bumps with stats
2. Edit Bump - Design tab with live preview
3. Products Tab - Add products with discounts
4. Rules Tab - Configure display conditions
5. Checkout - How bumps appear to customers

== Changelog ==

= 1.0.0 =
* Initial public release
* 2 design skins (Classic and Modern) with color customization
* Per-product percentage and flat discounts
* Smart display rules (all orders, cart items, cart categories, minimum order total)
* Custom badges and ribbons
* Product images with configurable width and position (left/right)
* Live admin preview with real-time sync
* AJAX add/remove on checkout (no page reload)
* Per-product rich text descriptions via WordPress Editor
* Revenue tracking on admin dashboard
* Overlap detection warnings
* Block and Classic checkout support
* HPOS (High-Performance Order Storage) compatibility
* Full input validation and security hardening
* WordPress coding standards compliance
* Developer hooks and filters for extensibility

== Upgrade Notice ==

= 1.0.0 =
Initial release. Install and start boosting your checkout revenue!
