<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * OBP_Post_Type — Registers the 'obp_order_bump' custom post type.
 *
 * @since 1.0.0
 * @package NaxolabsOrderBump
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
            'name'               => __( 'Order Bumps', 'naxolabs-order-bump' ),
            'singular_name'      => __( 'Order Bump', 'naxolabs-order-bump' ),
            'add_new'            => __( 'Add New Bump', 'naxolabs-order-bump' ),
            'add_new_item'       => __( 'Add New Order Bump', 'naxolabs-order-bump' ),
            'edit_item'          => __( 'Edit Order Bump', 'naxolabs-order-bump' ),
            'new_item'           => __( 'New Order Bump', 'naxolabs-order-bump' ),
            'view_item'          => __( 'View Order Bump', 'naxolabs-order-bump' ),
            'search_items'       => __( 'Search Order Bumps', 'naxolabs-order-bump' ),
            'not_found'          => __( 'No order bumps found', 'naxolabs-order-bump' ),
            'not_found_in_trash' => __( 'No order bumps found in Trash', 'naxolabs-order-bump' ),
            'menu_name'          => __( 'Order Bumps', 'naxolabs-order-bump' ),
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
