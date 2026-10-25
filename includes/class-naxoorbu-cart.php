<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Naxoorbu_Cart — Shared cart logic for adding/removing bump products.
 *
 * Used by the classic checkout AJAX handlers and by the Store API
 * (cart/extensions) callback that powers the block checkout, so both
 * flows tag cart items and fire hooks exactly the same way.
 *
 * @since 1.1.0
 */
class Naxoorbu_Cart {

    /**
     * Store API namespace used with extensionCartUpdate() in blocks-checkout.js.
     */
    const STORE_API_NAMESPACE = 'naxolabs-order-bump';

    /**
     * Add a bump product to the cart.
     *
     * @since 1.1.0
     * @param int $pid     Product ID.
     * @param int $qty     Quantity (clamped to 1–99).
     * @param int $bump_id Bump post ID (0 = resolve from product).
     * @return array|WP_Error { added: bool, already: bool, cart_item_key: string } or error.
     */
    public static function add_bump_product( $pid, $qty = 1, $bump_id = 0 ) {
        $pid     = absint( $pid );
        $qty     = max( 1, min( 99, intval( $qty ) ) );
        $bump_id = absint( $bump_id );

        if ( ! $pid ) {
            return new WP_Error( 'invalid_product', __( 'Invalid product.', 'naxolabs-order-bump' ) );
        }

        // Requests without a bump ID (e.g. cached older scripts) — resolve it from the product.
        if ( ! $bump_id ) {
            $bump_id = self::find_bump_for_product( $pid );
        }

        // Never trust the request: the offer must exist, include this product and be
        // available to this shopper right now (trigger, schedule and display rules).
        if ( $bump_id && ! self::is_offer_available( $bump_id, $pid ) ) {
            return new WP_Error( 'offer_unavailable', __( 'This offer is no longer available.', 'naxolabs-order-bump' ) );
        }

        $product = wc_get_product( $pid );
        if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
            return new WP_Error( 'product_unavailable', __( 'This product is not available.', 'naxolabs-order-bump' ) );
        }

        // Already added from a bump?
        $existing_key = self::find_bump_item_key( $pid );
        if ( $existing_key ) {
            return [ 'added' => false, 'already' => true, 'cart_item_key' => $existing_key ];
        }

        // Already in the cart at its normal price? Offering it again would only add a second line.
        if ( in_array( $pid, Naxoorbu_Visibility::get_cart_product_ids(), true ) ) {
            return new WP_Error( 'already_in_cart', __( 'This product is already in your cart.', 'naxolabs-order-bump' ) );
        }

        $cart_item_data = [];
        if ( $bump_id ) {
            $cart_item_data['naxoorbu_bump_id'] = $bump_id;
            // Get discount info from bump settings
            $bump_meta = get_post_meta( $bump_id, '_naxoorbu_settings', true );
            if ( is_array( $bump_meta ) && ! empty( $bump_meta['products'] ) ) {
                foreach ( $bump_meta['products'] as $bp ) {
                    if ( intval( $bp['id'] ) === $pid ) {
                        $cart_item_data['naxoorbu_discount']      = floatval( $bp['discount'] ?? 0 );
                        $cart_item_data['naxoorbu_discount_type'] = sanitize_text_field( $bp['discount_type'] ?? 'percentage' );
                        break;
                    }
                }
            }
        }

        /**
         * Filter cart item data before adding bump product to cart.
         * Extendable via filters.
         *
         * @param array $cart_item_data Cart item data with bump info.
         * @param int   $pid           Product ID being added.
         * @param int   $bump_id       Bump post ID.
         */
        $cart_item_data = apply_filters( 'naxoorbu_cart_item_data', $cart_item_data, $pid, $bump_id );

        $cart_item_key = WC()->cart->add_to_cart( $pid, $qty, 0, [], $cart_item_data );
        if ( ! $cart_item_key ) {
            return new WP_Error( 'add_failed', __( 'Could not add product to cart.', 'naxolabs-order-bump' ) );
        }

        do_action( 'naxoorbu_bump_added_to_cart', $bump_id, $pid, $cart_item_key );

        return [ 'added' => true, 'already' => false, 'cart_item_key' => $cart_item_key ];
    }

    /**
     * Remove a bump product from the cart.
     *
     * Only the item added from a bump is removed; the same product added by the
     * shopper at its normal price is never touched.
     *
     * @since 1.1.0
     * @param int $pid     Product ID.
     * @param int $bump_id Bump post ID (0 = any bump).
     * @return bool|WP_Error True if removed, false if it was not in the cart, or error.
     */
    public static function remove_bump_product( $pid, $bump_id = 0 ) {
        $pid = absint( $pid );
        if ( ! $pid ) {
            return new WP_Error( 'invalid_product', __( 'Invalid product.', 'naxolabs-order-bump' ) );
        }

        $key = self::find_bump_item_key( $pid, absint( $bump_id ) );
        if ( ! $key ) {
            return false;
        }

        WC()->cart->remove_cart_item( $key );
        return true;
    }

    /**
     * Cart item key of a product added from a bump.
     *
     * Returns the first matching item in cart order, i.e. the item the shopper ticked;
     * items other modules add under the same bump afterwards (e.g. a BOGO bonus) follow it.
     *
     * @since 1.1.0
     * @param int $pid     Product ID.
     * @param int $bump_id Bump post ID (0 = any bump).
     * @return string Cart item key, or '' if none.
     */
    public static function find_bump_item_key( $pid, $bump_id = 0 ) {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return '';

        foreach ( WC()->cart->get_cart() as $key => $item ) {
            $item_bump = (int) ( $item['naxoorbu_bump_id'] ?? 0 );
            if ( ! $item_bump || (int) $item['product_id'] !== (int) $pid ) continue;
            if ( $bump_id && $item_bump !== (int) $bump_id ) continue;
            return $key;
        }
        return '';
    }

    /**
     * Whether a published bump offers this product and is visible to the current shopper.
     *
     * @since 1.1.0
     * @param int $bump_id Bump ID.
     * @param int $pid     Product ID.
     * @return bool
     */
    public static function is_offer_available( $bump_id, $pid ) {
        foreach ( Naxoorbu_Visibility::get_published_bumps() as $bump_data ) {
            if ( (int) $bump_data['id'] !== (int) $bump_id ) continue;

            $offers_product = false;
            foreach ( (array) ( $bump_data['meta']['products'] ?? [] ) as $bp ) {
                if ( intval( $bp['id'] ?? 0 ) === (int) $pid ) {
                    $offers_product = true;
                    break;
                }
            }

            return $offers_product && Naxoorbu_Visibility::is_eligible( $bump_data );
        }
        return false;
    }

    /**
     * Register cart hooks.
     *
     * @since 1.1.0
     */
    public static function init() {
        // Runs on cart/checkout load and before an order is placed (classic and Store API),
        // the same place WooCommerce re-validates coupons.
        add_action( 'woocommerce_check_cart_items', [ __CLASS__, 'validate_cart_items' ] );
    }

    /**
     * Remove bump items whose offer the shopper no longer qualifies for
     * (offer ended, unpublished or deleted; schedule over; display rules or trigger no longer met).
     *
     * Mirrors WooCommerce's handling of invalid coupons: the discounted item is removed
     * and an error notice stops the order, so the shopper sees the new total before paying.
     * Items another module added under a bump (e.g. a BOGO bonus product) are left to that
     * module; they follow their parent item.
     *
     * @since 1.1.0
     */
    public static function validate_cart_items() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;

        // Decide first, then remove: eligibility must be judged on the cart as the shopper built it.
        $invalid = [];
        foreach ( WC()->cart->get_cart() as $key => $item ) {
            $bump_id = (int) ( $item['naxoorbu_bump_id'] ?? 0 );
            if ( ! $bump_id ) continue;

            $pid = (int) $item['product_id'];
            if ( ! self::bump_lists_product( $bump_id, $pid ) && self::is_bump_published( $bump_id ) ) {
                continue; // Not one of the offer's products: owned by another module.
            }

            if ( ! self::is_offer_available( $bump_id, $pid ) ) {
                $invalid[ $key ] = $item['data'] ? $item['data']->get_name() : '';
            }
        }

        foreach ( $invalid as $key => $name ) {
            if ( ! WC()->cart->get_cart_item( $key ) ) continue; // Already removed with its parent.

            WC()->cart->remove_cart_item( $key );
            wc_add_notice(
                sprintf(
                    /* translators: %s: product name. */
                    __( 'The special offer for "%s" is no longer available, so it has been removed from your order. Please review your order total.', 'naxolabs-order-bump' ),
                    $name
                ),
                'error'
            );
        }
    }

    /**
     * Whether a bump's saved settings list a product (regardless of publish status).
     *
     * @param int $bump_id Bump ID.
     * @param int $pid     Product ID.
     * @return bool
     */
    private static function bump_lists_product( $bump_id, $pid ) {
        $meta = get_post_meta( $bump_id, '_naxoorbu_settings', true );
        foreach ( (array) ( $meta['products'] ?? [] ) as $bp ) {
            if ( intval( $bp['id'] ?? 0 ) === (int) $pid ) return true;
        }
        return false;
    }

    /**
     * Whether a bump exists and is published.
     *
     * @param int $bump_id Bump ID.
     * @return bool
     */
    private static function is_bump_published( $bump_id ) {
        return 'publish' === get_post_status( $bump_id ) && 'naxoorbu_order_bump' === get_post_type( $bump_id );
    }

    /**
     * Find the published bump that offers a given product.
     *
     * @since 1.1.0
     * @param int $pid Product ID.
     * @return int Bump post ID, or 0 if none.
     */
    public static function find_bump_for_product( $pid ) {
        $bump_ids = get_posts( [
            'post_type'      => 'naxoorbu_order_bump',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $bump_ids as $bid ) {
            $meta = get_post_meta( $bid, '_naxoorbu_settings', true );
            if ( ! is_array( $meta ) || empty( $meta['products'] ) ) continue;
            foreach ( $meta['products'] as $bp ) {
                if ( intval( $bp['id'] ?? 0 ) === (int) $pid ) {
                    return (int) $bid;
                }
            }
        }
        return 0;
    }

    /**
     * Register Store API extensions used by the block checkout:
     * - cart/extensions callback for extensionCartUpdate()
     * - cart item data (`extensions['naxolabs-order-bump'].bump_id`) so the
     *   block checkout can tell which cart items are order bumps.
     * - cart data (`extensions['naxolabs-order-bump'].visible_bumps` / `visible_products`): bumps
     *   and products the shopper should see, re-evaluated on every cart/address update like the
     *   classic checkout.
     *
     * @since 1.1.0
     */
    public static function register_store_api() {
        if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
            woocommerce_store_api_register_update_callback( [
                'namespace' => self::STORE_API_NAMESPACE,
                'callback'  => [ __CLASS__, 'handle_store_api_update' ],
            ] );
        }

        if ( function_exists( 'woocommerce_store_api_register_endpoint_data' )
            && class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema' )
        ) {
            woocommerce_store_api_register_endpoint_data( [
                'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
                'namespace'       => self::STORE_API_NAMESPACE,
                'data_callback'   => [ __CLASS__, 'get_store_api_cart_item_data' ],
                'schema_callback' => [ __CLASS__, 'get_store_api_cart_item_schema' ],
                'schema_type'     => ARRAY_A,
            ] );
        }

        if ( function_exists( 'woocommerce_store_api_register_endpoint_data' )
            && class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema' )
        ) {
            woocommerce_store_api_register_endpoint_data( [
                'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
                'namespace'       => self::STORE_API_NAMESPACE,
                'data_callback'   => [ __CLASS__, 'get_store_api_cart_data' ],
                'schema_callback' => [ __CLASS__, 'get_store_api_cart_schema' ],
                'schema_type'     => ARRAY_A,
            ] );
        }
    }

    /**
     * Store API cart data: bumps the current shopper should see.
     *
     * @since 1.1.0
     * @return array
     */
    public static function get_store_api_cart_data() {
        $visible = Naxoorbu_Visibility::get_visible_products();

        return [
            'visible_bumps'    => array_column( $visible, 'bump_id' ),
            'visible_products' => $visible,
        ];
    }

    /**
     * Store API cart schema for get_store_api_cart_data().
     *
     * @since 1.1.0
     * @return array
     */
    public static function get_store_api_cart_schema() {
        return [
            'visible_bumps' => [
                'description' => __( 'IDs of the order bumps the shopper should see.', 'naxolabs-order-bump' ),
                'type'        => 'array',
                'items'       => [ 'type' => 'integer' ],
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
            ],
            'visible_products' => [
                'description' => __( 'Products each visible order bump offers to the shopper.', 'naxolabs-order-bump' ),
                'type'        => 'array',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'bump_id'     => [ 'type' => 'integer' ],
                        'product_ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                    ],
                ],
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
            ],
        ];
    }

    /**
     * Store API cart item data: the bump this item was added from (0 = not a bump item).
     *
     * @since 1.1.0
     * @param array $cart_item Cart item.
     * @return array
     */
    public static function get_store_api_cart_item_data( $cart_item ) {
        return [
            'bump_id' => absint( $cart_item['naxoorbu_bump_id'] ?? 0 ),
        ];
    }

    /**
     * Store API cart item schema for get_store_api_cart_item_data().
     *
     * @since 1.1.0
     * @return array
     */
    public static function get_store_api_cart_item_schema() {
        return [
            'bump_id' => [
                'description' => __( 'Order bump this item was added from (0 if none).', 'naxolabs-order-bump' ),
                'type'        => 'integer',
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
            ],
        ];
    }

    /**
     * Store API callback: add/remove a bump product. The Store API recalculates
     * totals and returns the updated cart to the block checkout itself.
     *
     * @since 1.1.0
     * @param array $data Data sent by extensionCartUpdate().
     * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException On invalid request.
     */
    public static function handle_store_api_update( $data ) {
        $action = sanitize_key( $data['action'] ?? '' );
        $pid    = absint( $data['product_id'] ?? 0 );

        if ( 'add' === $action ) {
            $result = self::add_bump_product( $pid, $data['qty'] ?? 1, $data['bump_id'] ?? 0 );
        } elseif ( 'remove' === $action ) {
            $result = self::remove_bump_product( $pid, $data['bump_id'] ?? 0 );
        } else {
            $result = new WP_Error( 'invalid_action', __( 'Invalid request.', 'naxolabs-order-bump' ) );
        }

        if ( is_wp_error( $result ) ) {
            throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                'naxoorbu_' . esc_attr( $result->get_error_code() ),
                esc_html( $result->get_error_message() ),
                400
            );
        }
    }
}
