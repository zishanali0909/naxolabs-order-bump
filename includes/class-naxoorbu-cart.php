<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Naxoorbu_Cart — Shared cart logic for adding/removing bump products.
 *
 * Used by the classic checkout AJAX handlers and by the Store API
 * (cart/extensions) callback that powers the block checkout, so both
 * flows tag cart items and fire hooks exactly the same way.
 *
 * @since 1.0.1
 */
class Naxoorbu_Cart {

    /**
     * Store API namespace used with extensionCartUpdate() in blocks-checkout.js.
     */
    const STORE_API_NAMESPACE = 'naxolabs-order-bump';

    /**
     * Add a bump product to the cart.
     *
     * @since 1.0.1
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

        $product = wc_get_product( $pid );
        if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
            return new WP_Error( 'product_unavailable', __( 'This product is not available.', 'naxolabs-order-bump' ) );
        }

        // Already in cart?
        foreach ( WC()->cart->get_cart() as $key => $item ) {
            if ( (int) $item['product_id'] === $pid ) {
                return [ 'added' => false, 'already' => true, 'cart_item_key' => $key ];
            }
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
     * @since 1.0.1
     * @param int $pid Product ID.
     * @return bool|WP_Error True if removed, false if it was not in the cart, or error.
     */
    public static function remove_bump_product( $pid ) {
        $pid = absint( $pid );
        if ( ! $pid ) {
            return new WP_Error( 'invalid_product', __( 'Invalid product.', 'naxolabs-order-bump' ) );
        }

        foreach ( WC()->cart->get_cart() as $key => $item ) {
            if ( (int) $item['product_id'] === $pid ) {
                WC()->cart->remove_cart_item( $key );
                return true;
            }
        }
        return false;
    }

    /**
     * Find the published bump that offers a given product.
     *
     * @since 1.0.1
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
     *
     * @since 1.0.1
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
    }

    /**
     * Store API cart item data: the bump this item was added from (0 = not a bump item).
     *
     * @since 1.0.1
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
     * @since 1.0.1
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
     * @since 1.0.1
     * @param array $data Data sent by extensionCartUpdate().
     * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException On invalid request.
     */
    public static function handle_store_api_update( $data ) {
        $action = sanitize_key( $data['action'] ?? '' );
        $pid    = absint( $data['product_id'] ?? 0 );

        if ( 'add' === $action ) {
            $result = self::add_bump_product( $pid, $data['qty'] ?? 1, $data['bump_id'] ?? 0 );
        } elseif ( 'remove' === $action ) {
            $result = self::remove_bump_product( $pid );
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
