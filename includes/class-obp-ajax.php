<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * OBP_Ajax — Handles all AJAX requests for Order Bump Pro.
 *
 * Admin endpoints: toggle bump status, delete bump, search products.
 * Frontend endpoints: add/remove products from cart.
 *
 * @since 1.0.0
 */
class OBP_Ajax {

    public function __construct() {
        // Admin-only actions
        add_action( 'wp_ajax_obp_toggle_bump',     [ $this, 'toggle_bump' ] );
        add_action( 'wp_ajax_obp_delete_bump',      [ $this, 'delete_bump' ] );
        add_action( 'wp_ajax_obp_search_products',  [ $this, 'search_products' ] );

        // Frontend (logged-in + guest) actions
        add_action( 'wp_ajax_obp_add_to_cart',           [ $this, 'add_to_cart' ] );
        add_action( 'wp_ajax_nopriv_obp_add_to_cart',    [ $this, 'add_to_cart' ] );
        add_action( 'wp_ajax_obp_remove_from_cart',      [ $this, 'remove_from_cart' ] );
        add_action( 'wp_ajax_nopriv_obp_remove_from_cart', [ $this, 'remove_from_cart' ] );
    }

    /**
     * Toggle bump status between publish and draft.
     *
     * @since 1.0.0
     */
    public function toggle_bump() {
        check_ajax_referer( 'obp_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'order-bump-pro' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        $p  = get_post( $id );

        if ( ! $p || $p->post_type !== 'order_bump' ) {
            wp_send_json_error( [ 'message' => __( 'Invalid order bump.', 'order-bump-pro' ) ] );
        }

        $new = $p->post_status === 'publish' ? 'draft' : 'publish';
        wp_update_post( [ 'ID' => $id, 'post_status' => $new ] );
        wp_send_json_success( [ 'status' => $new ] );
    }

    /**
     * Delete an order bump permanently.
     *
     * @since 1.0.0
     */
    public function delete_bump() {
        $id = intval( $_POST['id'] ?? 0 );

        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'obp_delete_' . $id ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'order-bump-pro' ) ] );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'order-bump-pro' ) ] );
        }

        $p = get_post( $id );
        if ( ! $p || $p->post_type !== 'order_bump' ) {
            wp_send_json_error( [ 'message' => __( 'Invalid order bump.', 'order-bump-pro' ) ] );
        }

        wp_delete_post( $id, true );
        wp_send_json_success();
    }

    /**
     * Search WooCommerce products by name (admin only).
     *
     * @since 1.0.0
     */
    public function search_products() {
        check_ajax_referer( 'obp_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'order-bump-pro' ) ] );
        }

        $q = sanitize_text_field( $_POST['q'] ?? '' );
        if ( mb_strlen( $q ) < 2 ) {
            wp_send_json_success( [] );
        }

        $query = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            's'              => $q,
        ] );

        $data = [];
        foreach ( $query->posts as $post ) {
            $p = wc_get_product( $post->ID );
            if ( ! $p ) continue;
            $data[] = [
                'id'            => $p->get_id(),
                'name'          => $p->get_name(),
                'price'         => $p->get_price(),
                'regular_price' => $p->get_regular_price(),
                'thumb'         => wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' ) ?: '',
                'price_html'    => wc_price( $p->get_price() ),
                'regular_html'  => wc_price( $p->get_regular_price() ),
            ];
        }
        wp_send_json_success( $data );
    }

    /**
     * Add a product to the cart via order bump checkbox.
     *
     * @since 1.0.0
     */
    public function add_to_cart() {
        check_ajax_referer( 'obp_frontend_nonce', 'nonce' );

        $pid = intval( $_POST['product_id'] ?? 0 );
        $qty = max( 1, min( 99, intval( $_POST['qty'] ?? 1 ) ) );

        if ( ! $pid ) {
            wp_send_json_error( [ 'message' => __( 'Invalid product.', 'order-bump-pro' ) ] );
        }

        $product = wc_get_product( $pid );
        if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
            wp_send_json_error( [ 'message' => __( 'This product is not available.', 'order-bump-pro' ) ] );
        }

        // Already in cart?
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( (int) $item['product_id'] === $pid ) {
                WC()->cart->calculate_totals();
                wp_send_json_success( [ 'already' => true, 'total' => WC()->cart->get_total() ] );
            }
        }

        if ( WC()->cart->add_to_cart( $pid, $qty ) ) {
            WC()->cart->calculate_totals();
            wp_send_json_success( [ 'added' => true, 'total' => WC()->cart->get_total() ] );
        }

        wp_send_json_error( [ 'message' => __( 'Could not add product to cart.', 'order-bump-pro' ) ] );
    }

    /**
     * Remove a product from the cart via order bump checkbox.
     *
     * @since 1.0.0
     */
    public function remove_from_cart() {
        check_ajax_referer( 'obp_frontend_nonce', 'nonce' );

        $pid = intval( $_POST['product_id'] ?? 0 );
        if ( ! $pid ) {
            wp_send_json_error( [ 'message' => __( 'Invalid product.', 'order-bump-pro' ) ] );
        }

        $removed = false;
        foreach ( WC()->cart->get_cart() as $key => $item ) {
            if ( (int) $item['product_id'] === $pid ) {
                WC()->cart->remove_cart_item( $key );
                $removed = true;
                break;
            }
        }

        WC()->cart->calculate_totals();
        wp_send_json_success( [ 'removed' => $removed, 'total' => WC()->cart->get_total() ] );
    }
}
