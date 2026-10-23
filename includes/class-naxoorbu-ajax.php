<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Naxoorbu_Ajax — Handles all AJAX requests for Naxolabs Order Bump.
 *
 * Admin endpoints: toggle bump status, delete bump, search products.
 * Frontend endpoints: add/remove products from cart.
 *
 * @since 1.0.0
 */
class Naxoorbu_Ajax {

    public function __construct() {
        // Admin-only actions
        add_action( 'wp_ajax_naxoorbu_toggle_bump',     [ $this, 'toggle_bump' ] );
        add_action( 'wp_ajax_naxoorbu_delete_bump',      [ $this, 'delete_bump' ] );
        add_action( 'wp_ajax_naxoorbu_search_products',  [ $this, 'search_products' ] );

        // Frontend (logged-in + guest) actions
        add_action( 'wp_ajax_naxoorbu_add_to_cart',           [ $this, 'add_to_cart' ] );
        add_action( 'wp_ajax_nopriv_naxoorbu_add_to_cart',    [ $this, 'add_to_cart' ] );
        add_action( 'wp_ajax_naxoorbu_remove_from_cart',      [ $this, 'remove_from_cart' ] );
        add_action( 'wp_ajax_nopriv_naxoorbu_remove_from_cart', [ $this, 'remove_from_cart' ] );
        add_action( 'wp_ajax_naxoorbu_track_impression',      [ $this, 'track_impression' ] );
        add_action( 'wp_ajax_nopriv_naxoorbu_track_impression', [ $this, 'track_impression' ] );
    }

    /**
     * Toggle bump status between publish and draft.
     *
     * @since 1.0.0
     */
    public function toggle_bump() {
        check_ajax_referer( 'naxoorbu_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'naxolabs-order-bump' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        $p  = get_post( $id );

        if ( ! $p || $p->post_type !== 'naxoorbu_order_bump' ) {
            wp_send_json_error( [ 'message' => __( 'Invalid order bump.', 'naxolabs-order-bump' ) ] );
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

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'naxoorbu_delete_' . $id ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'naxolabs-order-bump' ) ] );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'naxolabs-order-bump' ) ] );
        }

        $p = get_post( $id );
        if ( ! $p || $p->post_type !== 'naxoorbu_order_bump' ) {
            wp_send_json_error( [ 'message' => __( 'Invalid order bump.', 'naxolabs-order-bump' ) ] );
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
        check_ajax_referer( 'naxoorbu_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'naxolabs-order-bump' ) ] );
        }

        $q = ( isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '' );

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
        ];

        // Empty/short query = browse recent products; 2+ chars = search.
        if ( mb_strlen( $q ) >= 2 ) {
            $args['s'] = $q;
        } else {
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
        }

        $query = new WP_Query( $args );

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
        check_ajax_referer( 'naxoorbu_frontend_nonce', 'nonce' );

        $result = Naxoorbu_Cart::add_bump_product(
            intval( $_POST['product_id'] ?? 0 ),
            intval( $_POST['qty'] ?? 1 ),
            intval( $_POST['bump_id'] ?? 0 )
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        WC()->cart->calculate_totals();
        $key = $result['already'] ? 'already' : 'added';
        wp_send_json_success( [ $key => true, 'total' => WC()->cart->get_total() ] );
    }

    /**
     * Remove a product from the cart via order bump checkbox.
     *
     * @since 1.0.0
     */
    public function remove_from_cart() {
        check_ajax_referer( 'naxoorbu_frontend_nonce', 'nonce' );

        $removed = Naxoorbu_Cart::remove_bump_product(
            intval( $_POST['product_id'] ?? 0 ),
            intval( $_POST['bump_id'] ?? 0 )
        );
        if ( is_wp_error( $removed ) ) {
            wp_send_json_error( [ 'message' => $removed->get_error_message() ] );
        }

        WC()->cart->calculate_totals();
        wp_send_json_success( [ 'removed' => $removed, 'total' => WC()->cart->get_total() ] );
    }

    /**
     * Track bump impression from frontend (e.g. Block Checkout).
     *
     * @since 1.0.0
     */
    public function track_impression() {
        check_ajax_referer( 'naxoorbu_frontend_nonce', 'nonce' );
        $bump_id = intval( $_POST['bump_id'] ?? 0 );
        $pid     = intval( $_POST['product_id'] ?? 0 );
        if ( $bump_id && $pid ) {
            do_action( 'naxoorbu_bump_rendered', $bump_id, $pid );
        }
        wp_send_json_success();
    }
}
