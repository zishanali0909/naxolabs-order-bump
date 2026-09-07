<?php
/**
 * WP Order Bump — Uninstall Script
 *
 * Runs when the plugin is deleted via WordPress admin.
 * Cleans up all plugin data: custom post type posts and associated meta.
 */

// Exit if not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete all order_bump posts and their meta
$bumps = get_posts([
    'post_type'      => 'order_bump',
    'post_status'    => [ 'publish', 'draft', 'trash', 'any' ],
    'posts_per_page' => -1,
    'fields'         => 'ids',
]);

foreach ( $bumps as $bump_id ) {
    delete_post_meta( $bump_id, '_obp_settings' );
    wp_delete_post( $bump_id, true ); // Force delete, skip trash
}

// Clean up transients
delete_transient( 'obp_published_bumps' );
