<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Naxoorbu_Visibility — decides which bumps the current shopper should see.
 *
 * Single source of truth for classic checkout, the block checkout fallback and
 * the block checkout (via Store API cart data), so the same rules apply everywhere:
 *   1. Free trigger (all / specific product / category / minimum order)
 *   2. `naxoorbu_is_bump_scheduled` filter (e.g. Pro scheduling)
 *   3. `naxoorbu_passes_conditions` filter (e.g. Pro display rules)
 * A bump whose product the shopper already added stays visible, so it can be unticked;
 * whether it is still eligible is re-checked at checkout (see Naxoorbu_Cart::validate_cart_items()).
 * Products the shopper put in the cart themselves, and products that need options chosen,
 * are not offered (see get_offered_product_ids()).
 *
 * Cart-based rules ignore items added from bumps, so a bump can never qualify itself
 * (e.g. its own price pushing the cart over a minimum order amount).
 *
 * @since 1.1.0
 */
class Naxoorbu_Visibility {

    /**
     * Transient holding published bumps with their settings.
     */
    const CACHE_KEY = 'naxoorbu_published_bumps';

    /**
     * All published bumps with their settings (cached for 5 minutes, cleared on save/delete).
     *
     * @return array List of [ 'id' => int, 'meta' => array ].
     */
    public static function get_published_bumps() {
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached ) return $cached;

        $all  = get_posts( [ 'post_type' => 'naxoorbu_order_bump', 'post_status' => 'publish', 'posts_per_page' => -1 ] );
        $data = [];
        foreach ( $all as $bump ) {
            $meta = get_post_meta( $bump->ID, '_naxoorbu_settings', true );
            if ( empty( $meta ) ) continue;
            $data[] = [ 'id' => $bump->ID, 'meta' => $meta ];
        }

        set_transient( self::CACHE_KEY, $data, 5 * MINUTE_IN_SECONDS );
        return $data;
    }

    /**
     * Whether a bump should be shown to the current shopper.
     *
     * @param array $bump_data [ 'id' => int, 'meta' => array ].
     * @return bool
     */
    public static function is_visible( $bump_data ) {
        if ( self::has_bump_item_in_cart( (int) $bump_data['id'] ) ) return true;

        return self::is_eligible( $bump_data ) && ! empty( self::get_offered_product_ids( $bump_data ) );
    }

    /**
     * Products of a bump that should be offered to the current shopper.
     *
     * A product the shopper already added to the cart themselves (at its normal price) is not
     * offered again: it cannot be added twice, and unticking it must never remove their own item.
     * A product already added from this bump stays, so it can be unticked.
     *
     * @param array $bump_data [ 'id' => int, 'meta' => array ].
     * @return int[]
     */
    public static function get_offered_product_ids( $bump_data ) {
        $bump_id    = (int) $bump_data['id'];
        $owned_pids = self::get_cart_product_ids();
        $replaced   = Naxoorbu_Cart::replaced_by( $bump_data['meta'] ?? [] ); // Leave the cart if a "Replace" bump is ticked.

        $ids = [];
        foreach ( (array) ( $bump_data['meta']['products'] ?? [] ) as $bp ) {
            $pid = intval( $bp['id'] ?? 0 );
            if ( ! $pid ) continue;
            $in_cart = self::has_bump_item_in_cart( $bump_id, $pid );
            if ( in_array( $pid, $owned_pids, true ) && ! $in_cart ) continue;
            // A product that needs the shopper to choose options (e.g. a variable product saved
            // before 1.2.0) cannot be added with one click, and one that WooCommerce Subscriptions
            // would not allow in this cart would empty it, so neither is offered.
            if ( ! $in_cart ) {
                $product = wc_get_product( $pid );
                if ( ! $product || ! Naxoorbu_Cart::can_offer( $product ) || ! Naxoorbu_Cart::fits_cart( $product, $replaced ) ) continue;
            }
            $ids[] = $pid;
        }
        return $ids;
    }

    /**
     * Whether the shopper currently qualifies for a bump (trigger, schedule and display rules).
     *
     * @param array $bump_data [ 'id' => int, 'meta' => array ].
     * @return bool
     */
    public static function is_eligible( $bump_data ) {
        if ( ! self::check_trigger( $bump_data['meta'] ?? [] ) ) {
            return false;
        }

        // Extendable via filters — allows scheduling checks.
        if ( ! apply_filters( 'naxoorbu_is_bump_scheduled', true, $bump_data ) ) {
            return false;
        }

        // Extendable via filters — allows advanced conditional logic.
        return (bool) apply_filters( 'naxoorbu_passes_conditions', true, $bump_data );
    }

    /**
     * Visible bumps with the products each one offers to the current shopper.
     *
     * @return array List of [ 'bump_id' => int, 'product_ids' => int[] ].
     */
    public static function get_visible_products() {
        $out = [];
        foreach ( self::get_published_bumps() as $bump_data ) {
            if ( ! self::is_visible( $bump_data ) ) continue;

            $out[] = [
                'bump_id'     => (int) $bump_data['id'],
                'product_ids' => self::get_offered_product_ids( $bump_data ),
            ];
        }
        return $out;
    }

    /**
     * Free trigger rule: does the cart match the bump's "Display Rules"?
     *
     * @param array $meta Bump settings.
     * @return bool
     */
    public static function check_trigger( $meta ) {
        $type = $meta['trigger_type'] ?? 'all';
        if ( $type === 'all' ) return true;
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return false;

        $cart_pids  = self::get_cart_product_ids();
        $cart_total = self::get_cart_subtotal();
        if ( $type === 'specific_product' ) {
            return ! empty( array_intersect( $cart_pids, (array) ( $meta['trigger_products'] ?? [] ) ) );
        }
        if ( $type === 'category' ) {
            foreach ( $cart_pids as $pid ) {
                $terms = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'ids' ] );
                if ( ! empty( array_intersect( $terms, (array) ( $meta['trigger_categories'] ?? [] ) ) ) ) return true;
            }
            return false;
        }
        if ( $type === 'minimum_order' ) {
            // Set in the store currency; the cart is in the shopper's currency.
            return $cart_total >= Naxoorbu_Discount::convert_amount( $meta['minimum_order_amount'] ?? 0 );
        }
        return false;
    }

    /**
     * Product IDs in the cart, excluding items added from bumps.
     *
     * @return int[]
     */
    public static function get_cart_product_ids() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return [];

        $ids = [];
        foreach ( WC()->cart->get_cart() as $item ) {
            // Items a "Replace" bump took the place of still count: the shopper qualified with them.
            foreach ( (array) ( $item['naxoorbu_replaced'] ?? [] ) as $replaced ) {
                $ids[] = (int) ( $replaced['product_id'] ?? 0 );
                if ( ! empty( $replaced['variation_id'] ) ) {
                    $ids[] = (int) $replaced['variation_id'];
                }
            }
            if ( ! empty( $item['naxoorbu_bump_id'] ) ) continue;
            $ids[] = (int) $item['product_id'];
            // A variation counts as itself too: rules and bumps can list a single variation.
            if ( ! empty( $item['variation_id'] ) ) {
                $ids[] = (int) $item['variation_id'];
            }
        }
        return array_values( array_unique( $ids ) );
    }

    /**
     * Cart subtotal (before tax, shipping and coupons), excluding items added from bumps.
     * Use this for any cart-amount rule, so a bump's own price never counts towards it.
     *
     * @return float
     */
    public static function get_cart_subtotal() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return 0.0;

        $subtotal = (float) WC()->cart->get_subtotal();
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( ! empty( $item['naxoorbu_bump_id'] ) ) {
                $subtotal -= (float) ( $item['line_subtotal'] ?? 0 );
            }
            // Items a "Replace" bump took the place of still count, as in get_cart_product_ids().
            foreach ( (array) ( $item['naxoorbu_replaced'] ?? [] ) as $replaced ) {
                $subtotal += (float) ( $replaced['line_subtotal'] ?? 0 );
            }
        }
        return max( 0.0, $subtotal );
    }

    /**
     * Whether the cart holds an item added from this bump (optionally for one product).
     *
     * @param int $bump_id Bump ID.
     * @param int $pid     Product ID (0 = any product).
     * @return bool
     */
    public static function has_bump_item_in_cart( $bump_id, $pid = 0 ) {
        if ( ! $bump_id || ! function_exists( 'WC' ) || ! WC()->cart ) return false;

        foreach ( WC()->cart->get_cart() as $item ) {
            if ( (int) ( $item['naxoorbu_bump_id'] ?? 0 ) !== (int) $bump_id ) continue;
            if ( ! $pid || Naxoorbu_Cart::item_offer_id( $item ) === (int) $pid ) return true;
        }
        return false;
    }
}
