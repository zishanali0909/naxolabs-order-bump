<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OBP_Post_Type {

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    public function register_post_type() {
        $labels = [
            'name'               => __( 'Order Bumps', 'order-bump-pro' ),
            'singular_name'      => __( 'Order Bump', 'order-bump-pro' ),
            'add_new'            => __( 'Add New Bump', 'order-bump-pro' ),
            'add_new_item'       => __( 'Add New Order Bump', 'order-bump-pro' ),
            'edit_item'          => __( 'Edit Order Bump', 'order-bump-pro' ),
            'new_item'           => __( 'New Order Bump', 'order-bump-pro' ),
            'view_item'          => __( 'View Order Bump', 'order-bump-pro' ),
            'search_items'       => __( 'Search Order Bumps', 'order-bump-pro' ),
            'not_found'          => __( 'No order bumps found', 'order-bump-pro' ),
            'not_found_in_trash' => __( 'No order bumps found in Trash', 'order-bump-pro' ),
            'menu_name'          => __( 'Order Bumps', 'order-bump-pro' ),
        ];

        register_post_type( 'order_bump', [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'capability_type'    => 'post',
            'supports'           => [ 'title' ],
        ] );
    }
}
