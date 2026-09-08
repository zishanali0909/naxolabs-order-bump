<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * OBP_Post_Type — Registers the 'obp_order_bump' custom post type.
 *
 * @since 1.0.0
 * @package WPOrderBump
 */
class OBP_Post_Type {

    /**
     * Hook into WordPress init to register the post type.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    /**
     * Register the 'obp_order_bump' custom post type.
     *
     * @since 1.0.0
     */
    public function register_post_type() {
        $labels = [
            'name'               => __( 'Order Bumps', 'wp-order-bump' ),
            'singular_name'      => __( 'Order Bump', 'wp-order-bump' ),
            'add_new'            => __( 'Add New Bump', 'wp-order-bump' ),
            'add_new_item'       => __( 'Add New Order Bump', 'wp-order-bump' ),
            'edit_item'          => __( 'Edit Order Bump', 'wp-order-bump' ),
            'new_item'           => __( 'New Order Bump', 'wp-order-bump' ),
            'view_item'          => __( 'View Order Bump', 'wp-order-bump' ),
            'search_items'       => __( 'Search Order Bumps', 'wp-order-bump' ),
            'not_found'          => __( 'No order bumps found', 'wp-order-bump' ),
            'not_found_in_trash' => __( 'No order bumps found in Trash', 'wp-order-bump' ),
            'menu_name'          => __( 'Order Bumps', 'wp-order-bump' ),
        ];

        register_post_type( 'obp_order_bump', [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'supports'           => [ 'title' ],
        ] );
    }
}
