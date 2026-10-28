=== Naxolabs Order Bump ===
Contributors: zishanali0909
Tags: woocommerce, order bump, checkout upsell, one click upsell, sales boost
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
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
* **Highlighted Headline Words** - Make words like "Yes!" stand out in a colour you choose
* **AJAX Add to Cart** - Smooth, no-reload experience on checkout
* **Per-Product Descriptions** - Rich text descriptions with full WordPress Editor support
* **Revenue Tracking** - Track bump revenue directly in your admin dashboard
* **Overlap Detection** - Smart warnings if bump product matches trigger product
* **Fully Responsive** - Works perfectly on mobile, tablet, and desktop
* **Block and Classic Checkout** - Compatible with both WooCommerce checkout types
* **HPOS Compatible** - Works with WooCommerce High-Performance Order Storage
* **Lightweight** - Styles and scripts load only on the checkout page; no external requests
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

Naxolabs Order Bump is designed with full isolation. All CSS classes use the `naxoorbu-` prefix, all JavaScript is namespaced, and all PHP functions, classes, options and hooks use the `naxoorbu_` prefix to prevent conflicts. Its styles and scripts load only on the checkout page.

= Does it track revenue from order bumps? =

Yes! The admin dashboard shows the net revenue of bump products from paid orders (processing and completed by default), excluding tax and with refunds subtracted.

= Does the plugin collect data or contact external services? =

No. The plugin does not send any data to external services and does not track visitors. It only stores, in the order item meta, which bump an item came from and the discount it got, so bump revenue can be reported.

= What happens when I deactivate or delete the plugin? =

When deactivated, bumps simply stop showing on checkout. Your bump configurations are preserved. When deleted, only temporary cache data is removed. Your bumps remain in the database in case you reinstall.

= Can developers extend it? =

Yes. Commonly used hooks include:
* `naxoorbu_max_bumps` and `naxoorbu_max_products_per_bump` (filters) - change the limits
* `naxoorbu_passes_conditions` (filter) - add your own display conditions
* `naxoorbu_discount_price` (filter) - adjust the bump price
* `naxoorbu_blocks_bump_placement` (filter) - show block checkout bumps in the order summary or in the checkout form
* `naxoorbu_after_bump_saved`, `naxoorbu_bump_rendered` and `naxoorbu_bump_added_to_cart` (actions) - react to saves, views and accepted bumps

== Screenshots ==

1. Admin Dashboard - Manage all your order bumps with stats
2. Edit Bump - Design tab with live preview
3. Products Tab - Add products with discounts
4. Rules Tab - Configure display conditions
5. Checkout - How bumps appear to customers

== Changelog ==

= 1.2.0 =
* New: Highlight words in the bump headline by wrapping them in `<mark>…</mark>`, in a colour you choose. New bumps start with a highlighted "Yes!"
* New: Offer a single variation of a variable product (e.g. "T-shirt – Large"). The Products search lists variations; a variable product, or a variation with an "Any…" option, needs the shopper to choose and is not offered
* New: Works with multi-currency plugins that convert prices (e.g. CURCY, FOX): the bump discount applies once, in your store currency, and the shopper sees and pays the converted price. Minimum order amounts are compared in the shopper's currency
* New: Works with WooCommerce Subscriptions: subscription products (also variable ones) show their billing details (e.g. "/ month"), sign-up fees are charged once, and a bump that would make WooCommerce Subscriptions empty the cart is not offered
* Improved: The edit screen preview shows the product's name in place of {{product_name}}, as the checkout does
* Improved: Bumps use your theme's font with a lighter headline, so they match the rest of the checkout
* Developers: new `naxoorbu_bump_placements` filter, `naxoorbu_admin_after_position` action and `Naxoorbu_Frontend::render_bump_list()`, so extensions can show bumps in more places (e.g. the cart page), a `naxoorbu_bump_toggled` browser event when a bump is added or removed, and `Naxoorbu_Admin::sanitize_settings()` / `can_create_bump()` for saving bumps from elsewhere (e.g. an import)
* Fixed: The block checkout could show bumps not offered to the shopper for a split second while loading, and count impressions for them
* Fixed: With Skin 2, editing the headline in the preview could replace the price
* Fixed: "Replace Cart Items with Order Bump" now works: ticking the bump swaps out the items that made it show (e.g. to upgrade to a bundle), and unticking it puts them back

= 1.1.0 =
* Improved: Rules tab search now shows product image and price
* New: Live "Already in Products/Rules" indicator in search results
* Improved: Search shows top 10 recent products on focus (click to browse)
* Improved: Escape key closes search dropdowns
* Fixed: Dashboard "unsaved changes" popup on status toggle
* Fixed: Security — the save request could turn any post into an order bump
* Fixed: Offers are re-checked at checkout; an offer that is no longer available is removed with a notice, like an expired coupon
* Fixed: A bump's own price no longer counts towards its display rules
* Fixed: Products the customer already has in the cart are not offered again, and unticking a bump never removes the customer's own item
* Fixed: Bump revenue counts all paid order statuses, subtracts refunds and reads the right order storage (HPOS or posts)
* Fixed: Saving a bump never drops products it already has; the bump limit is also checked on save
* Improved: Classic and block checkout use the same skin and visibility rules
* Improved: Faster checkout — far fewer database queries, and bump styles and scripts load only on the checkout page
* Improved: Block checkout renders bumps once (no hidden duplicate) and sends no tracking requests unless an add-on records them
* Improved: Cleaner, consistent admin screens (buttons, colors, spacing) with proper browser tab titles
* Improved: All admin and checkout messages can be translated
* Improved: Declares compatibility with the WooCommerce Cart and Checkout blocks
* Fixed: Products with an apostrophe in their name could not be added from search
* Fixed: A flat discount above 100 could stop the bump from saving later; decimal discounts are accepted
* Fixed: New product rows showed the rupee sign instead of the store currency
* Fixed: An unexpected value in the bump form no longer causes an error on save

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

= 1.2.0 =
Highlight words in your bump headline, offer single variations of variable products, and a working "Replace" option. Existing bumps keep their text and colours.

= 1.1.0 =
Faster checkout, a cleaner admin and several fixes (security, offer checks, revenue totals, saving). Recommended for all users.

= 1.0.0 =
Initial release. Install and start boosting your checkout revenue!
