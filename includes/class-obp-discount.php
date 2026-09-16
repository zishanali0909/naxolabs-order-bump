<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * OBP_Discount — Apply order bump discounted prices in WooCommerce cart.
 *
 * When a product is added to cart via an order bump, we need to override
 * its price to match the discount configured in the bump settings.
 * This class hooks into WooCommerce cart calculations to do that.
 */
class OBP_Discount {

    public function __construct() {
        // Apply discount prices during cart calculation
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_bump_discounts' ], 20, 1 );

        // Store bump meta when product is added to cart via order bump
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_bump_meta_to_cart' ], 10, 3 );

        // Save bump meta to order line items for revenue tracking
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_bump_meta_to_order_item' ], 10, 4 );
    }

    /**
     * When a product is added via AJAX order bump, attach bump discount info to cart item.
     */
    public function add_bump_meta_to_cart( $cart_item_data, $product_id, $variation_id ) {
        // Only tag items added via our AJAX actions
        if ( ! wp_doing_ajax() ) return $cart_item_data;

        $action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
        if ( $action !== 'obp_add_to_cart' ) return $cart_item_data;

        // Find all published bumps that include this product (use transient cache)
        $cached = get_transient( 'obp_published_bumps' );
        if ( false === $cached ) {
            $bumps_posts = get_posts([
                'post_type'      => 'obp_order_bump',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);
            $cached = [];
            foreach ( $bumps_posts as $bump ) {
                $meta = get_post_meta( $bump->ID, '_obp_settings', true );
                if ( empty( $meta ) ) continue;
                $cached[] = [ 'id' => $bump->ID, 'meta' => $meta ];
            }
            set_transient( 'obp_published_bumps', $cached, 5 * MINUTE_IN_SECONDS );
        }

        foreach ( $cached as $bump_data ) {
            $meta = $bump_data['meta'];
            if ( empty( $meta['products'] ) ) continue;

            foreach ( $meta['products'] as $pm ) {
                if ( intval( $pm['id'] ) === intval( $product_id ) ) {
                    $discount      = floatval( $pm['discount'] ?? 0 );
                    $discount_type = $pm['discount_type'] ?? 'percentage';

                    if ( $discount > 0 ) {
                        $cart_item_data['obp_bump_id']       = $bump_data['id'];
                        $cart_item_data['obp_discount']      = $discount;
                        $cart_item_data['obp_discount_type']  = $discount_type;
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
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) return;

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( empty( $cart_item['obp_discount'] ) ) continue;

            $product       = $cart_item['data'];
            $original_price = floatval( $product->get_price() );
            $discount      = floatval( $cart_item['obp_discount'] );
            $discount_type = $cart_item['obp_discount_type'] ?? 'percentage';

            if ( $discount_type === 'percentage' ) {
                $new_price = $original_price * ( 1 - $discount / 100 );
            } else {
                // flat discount
                $new_price = $original_price - $discount;
            }

            $new_price = max( 0, $new_price );
            $product->set_price( $new_price );
        }
    
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
        if ( ! empty( $values['obp_bump_id'] ) ) {
            $item->add_meta_data( '_obp_bump_id', intval( $values['obp_bump_id'] ), true );
        }
        if ( ! empty( $values['obp_discount'] ) ) {
            $item->add_meta_data( '_obp_discount', floatval( $values['obp_discount'] ), true );
            $item->add_meta_data( '_obp_discount_type', sanitize_text_field( $values['obp_discount_type'] ?? 'percentage' ), true );
        }
    }
}