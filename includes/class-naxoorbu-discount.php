<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Naxoorbu_Discount — Apply order bump discounted prices in WooCommerce cart.
 *
 * When a product is added to cart via an order bump, we need to override
 * its price to match the discount configured in the bump settings.
 * This class hooks into WooCommerce cart calculations to do that.
 */
class Naxoorbu_Discount {

    /**
     * Original (pre-discount) price of each cart product object, for this request.
     *
     * @var SplObjectStorage|null
     */
    private static $base_prices = null;

    public function __construct() {
        // Apply discount prices during cart calculation
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_bump_discounts' ], 20, 1 );

        // Removed: add_bump_meta_to_cart filter — AJAX handler already passes discount
        // data via cart_item_data. Having both caused duplicate cart item keys.

        // Save bump meta to order line items for revenue tracking
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_bump_meta_to_order_item' ], 10, 4 );
        
        // Fire action when order is placed to track conversions (Pro analytics)
        // Legacy checkout
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'fire_order_completed_action' ], 10, 1 );
        // Block checkout
        add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'fire_order_completed_action_blocks' ], 10, 1 );
    }

    /**
     * When a product is added via AJAX order bump, attach bump discount info to cart item.
     */
    public function add_bump_meta_to_cart( $cart_item_data, $product_id, $variation_id ) {
        // Only tag items added via our AJAX actions
        if ( ! wp_doing_ajax() ) return $cart_item_data;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in Naxoorbu_Ajax handler
        $action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
        if ( $action !== 'naxoorbu_add_to_cart' ) return $cart_item_data;

        // Find all published bumps that include this product (use transient cache)
        $cached = get_transient( 'naxoorbu_published_bumps' );
        if ( false === $cached ) {
            $bumps_posts = get_posts([
                'post_type'      => 'naxoorbu_order_bump',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);
            $cached = [];
            foreach ( $bumps_posts as $bump ) {
                $meta = get_post_meta( $bump->ID, '_naxoorbu_settings', true );
                if ( empty( $meta ) ) continue;
                $cached[] = [ 'id' => $bump->ID, 'meta' => $meta ];
            }
            set_transient( 'naxoorbu_published_bumps', $cached, 5 * MINUTE_IN_SECONDS );
        }

        foreach ( $cached as $bump_data ) {
            $meta = $bump_data['meta'];
            if ( empty( $meta['products'] ) ) continue;

            foreach ( $meta['products'] as $pm ) {
                if ( intval( $pm['id'] ) === intval( $variation_id ? $variation_id : $product_id ) ) { // Bumps list a variation by its own ID.
                    $discount      = floatval( $pm['discount'] ?? 0 );
                    $discount_type = $pm['discount_type'] ?? 'percentage';

                    if ( $discount > 0 ) {
                        $cart_item_data['naxoorbu_bump_id']       = $bump_data['id'];
                        $cart_item_data['naxoorbu_discount']      = $discount;
                        $cart_item_data['naxoorbu_discount_type']  = $discount_type;
                    }
                    break 2;
                }
            }
        }

        return $cart_item_data;
    }

    /**
     * Apply discounted prices to cart items that were added via order bumps.
     */
    public function apply_bump_discounts( $cart ) {
        if ( is_admin() && ! wp_doing_ajax() ) return;

        if ( null === self::$base_prices ) {
            self::$base_prices = new SplObjectStorage();
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( empty( $cart_item['naxoorbu_discount'] ) ) continue;

            $product = $cart_item['data'];

            // Totals can be calculated several times per request (e.g. Store API add + response),
            // and items can be added in between. Always discount from the original price so the
            // result is the same on every run, instead of skipping later runs.
            // The stored price is used (store currency, before price filters): multi-currency plugins
            // convert the price and WooCommerce Subscriptions adds sign-up fees when it is read, so
            // they apply once on top of the discounted price, as for any other product.
            if ( ! self::$base_prices->contains( $product ) ) {
                self::$base_prices[ $product ] = floatval( $product->get_price( 'edit' ) );
            }
            $original_price = self::$base_prices[ $product ];
            $new_price      = self::offer_price( $original_price, $cart_item['naxoorbu_discount'], $cart_item['naxoorbu_discount_type'] ?? 'percentage' );
            /**
             * Filter the discounted price before applying.
             *
             * @param float $new_price      Calculated discount price.
             * @param float $original_price  Original product price.
             * @param array $cart_item       Cart item data.
             */
            $new_price = apply_filters( 'naxoorbu_discount_price', $new_price, $original_price, $cart_item );
            $product->set_price( $new_price );
        }
    
    }

    /**
     * Price after a bump discount. Prices and flat discounts are in the store currency.
     *
     * @since 1.2.0
     * @param float  $price    Price.
     * @param float  $discount Discount (percent or flat amount).
     * @param string $type     'percentage' or 'flat'.
     * @return float
     */
    public static function offer_price( $price, $discount, $type ) {
        $price    = (float) $price;
        $discount = (float) $discount;
        if ( $discount > 0 ) {
            $price = 'percentage' === $type ? $price * ( 1 - $discount / 100 ) : $price - $discount;
        }
        return max( 0.0, $price );
    }

    /**
     * What the shopper pays for a product sold at an amount in the store currency: the product's
     * price filters run on it as in the cart (e.g. a multi-currency plugin converting it).
     *
     * @since 1.2.0
     * @param WC_Product $product Product.
     * @param float      $amount  Amount in the store currency.
     * @return float
     */
    public static function shown_price( $product, $amount ) {
        $copy = clone $product;
        $copy->set_price( $amount );
        return (float) $copy->get_price();
    }

    /**
     * An amount set in the store currency (e.g. a minimum order amount), in the currency the
     * shopper's cart is in, through the multi-currency plugin's own conversion. Only the currency
     * changes: price rules of other plugins (memberships, wholesale) don't apply to it. Another
     * plugin can convert it through the `naxoorbu_convert_amount` filter.
     *
     * @since 1.2.0
     * @param float $amount Amount in the store currency.
     * @return float
     */
    public static function convert_amount( $amount ) {
        global $WOOCS; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- FOX's own global.

        $amount    = (float) $amount;
        $converted = $amount;
        if ( $amount > 0 ) {
            // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the multi-currency plugins' own hooks.
            if ( function_exists( 'wmc_get_price' ) ) {
                // CURCY (WooCommerce Multi Currency); it leaves the amount as is where the cart stays
                // in the store currency (paying in other currencies off).
                $converted = (float) wmc_get_price( $amount );
            } elseif ( is_object( $WOOCS ) && has_filter( 'woocs_exchange_value' ) ) {
                // FOX Currency Switcher: the cart is in the shopper's currency only with "Is multiple allowed" on.
                // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- FOX's property.
                $converted = ! empty( $WOOCS->is_multiple_allowed ) ? (float) apply_filters( 'woocs_exchange_value', $amount ) : $amount;
            } elseif ( function_exists( 'WC_Payments_Multi_Currency' ) && is_object( WC_Payments_Multi_Currency() ) && method_exists( WC_Payments_Multi_Currency(), 'get_price' ) ) {
                $converted = (float) WC_Payments_Multi_Currency()->get_price( $amount, 'product' ); // WooPayments Multi-Currency.
            } elseif ( has_filter( 'wc_aelia_cs_convert' ) ) {
                // Aelia Currency Switcher.
                $converted = (float) apply_filters( 'wc_aelia_cs_convert', $amount, get_option( 'woocommerce_currency' ), get_woocommerce_currency() );
            } elseif ( has_filter( 'wcml_raw_price_amount' ) ) {
                $converted = (float) apply_filters( 'wcml_raw_price_amount', $amount ); // WPML WooCommerce Multilingual.
            }
            // phpcs:enable
        }

        /**
         * Filter an amount converted from the store currency to the shopper's currency.
         *
         * @since 1.2.0
         * @param float $converted Converted amount.
         * @param float $amount    Amount in the store currency.
         */
        return (float) apply_filters( 'naxoorbu_convert_amount', $converted, $amount );
    }

    /**
     * Save bump metadata to order line items for revenue tracking.
     *
     * @since 1.0.0
     * @param \WC_Order_Item_Product $item  Order line item.
     * @param string                 $cart_item_key Cart item key.
     * @param array                  $values Cart item data.
     * @param \WC_Order              $order  The order object.
     */
    public function save_bump_meta_to_order_item( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values['naxoorbu_bump_id'] ) ) {
            $item->add_meta_data( '_naxoorbu_bump_id', intval( $values['naxoorbu_bump_id'] ), true );
        }
        if ( ! empty( $values['naxoorbu_discount'] ) ) {
            $item->add_meta_data( '_naxoorbu_discount', floatval( $values['naxoorbu_discount'] ), true );
            $item->add_meta_data( '_naxoorbu_discount_type', sanitize_text_field( $values['naxoorbu_discount_type'] ?? 'percentage' ), true );
        }
    }

    /**
     * Fire action when an order is processed, so Pro can track it as a conversion.
     *
     * @since 1.1.0
     * @param int $order_id The order ID.
     */
    public function fire_order_completed_action( $order_id ) {
        $order = wc_get_order( $order_id );
        $this->process_order_for_bumps( $order );
    }

    /**
     * Fire action for block checkout.
     *
     * @param \WC_Order $order The order object.
     */
    public function fire_order_completed_action_blocks( $order ) {
        $this->process_order_for_bumps( $order );
    }

    /**
     * Helper to process order and fire conversion action.
     */
    private function process_order_for_bumps( $order ) {
        if ( ! $order ) return;

        foreach ( $order->get_items() as $item ) {
            $bump_id = $item->get_meta( '_naxoorbu_bump_id' );
            if ( $bump_id ) {
                $product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id(); // The offered product (a variation by its own ID).
                $revenue    = floatval( $item->get_total() );

                /**
                 * Triggered when an order containing a bump is placed.
                 *
                 * @param int   $bump_id    Bump ID.
                 * @param int   $product_id Product ID.
                 * @param int   $order_id   Order ID.
                 * @param float $revenue    Revenue from the bump product.
                 */
                do_action( 'naxoorbu_bump_order_completed', intval( $bump_id ), $product_id, $order->get_id(), $revenue );
            }
        }
    }
}