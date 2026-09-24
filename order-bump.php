<?php
/**
 * Plugin Name: Naxolabs Order Bump
 * Plugin URI:  https://github.com/zishanali0909/naxolabs-order-bump
 * Description: Add order bumps to WooCommerce checkout page. Boost average order value with one-click upsells.
 * Version:     1.0.0
 * Author:      Zishan Ali
 * Author URI:  https://github.com/zishanali0909
 * Text Domain: naxolabs-order-bump
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package NaxolabsOrderBump
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Check if WooCommerce is active before doing anything.
 *
 * @since 1.0.0
 * @return bool
 */
function naxoorbu_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                wp_kses_post(
                    sprintf(
                        /* translators: %1$s: plugin name, %2$s: dependency name */
                        __( '<strong>%1$s</strong> requires <strong>%2$s</strong> to be installed and active.', 'naxolabs-order-bump' ),
                        'Naxolabs Order Bump',
                        'WooCommerce'
                    )
                )
            );
        });
        return false;
    }
    return true;
}

define( 'NAXOORBU_VERSION', '1.0.0' );
define( 'NAXOORBU_PATH', plugin_dir_path( __FILE__ ) );
define( 'NAXOORBU_URL', plugin_dir_url( __FILE__ ) );
define( 'NAXOORBU_MAX_BUMPS', 2 );
define( 'NAXOORBU_MAX_PRODUCTS_PER_BUMP', 2 );

require_once NAXOORBU_PATH . 'includes/class-naxoorbu-post-type.php';
require_once NAXOORBU_PATH . 'includes/class-naxoorbu-admin.php';
require_once NAXOORBU_PATH . 'includes/class-naxoorbu-frontend.php';
require_once NAXOORBU_PATH . 'includes/class-naxoorbu-ajax.php';
require_once NAXOORBU_PATH . 'includes/class-naxoorbu-discount.php';

/**
 * Initialize plugin after all plugins are loaded.
 *
 * @since 1.0.0
 */
function naxoorbu_init() {

    if ( ! naxoorbu_check_woocommerce() ) return;

    // One-time auto-migration: old post type -> new
    if ( ! get_option( 'naxoorbu_migrated_post_type' ) ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update( $wpdb->posts, [ 'post_type' => 'naxoorbu_order_bump' ], [ 'post_type' => 'order_bump' ] );
        delete_transient( 'naxoorbu_published_bumps' );
        update_option( 'naxoorbu_migrated_post_type', '1', 'no' );
    }

    new Naxoorbu_Post_Type();
    new Naxoorbu_Admin();
    new Naxoorbu_Frontend();
    new Naxoorbu_Ajax();
    new Naxoorbu_Discount();
}
add_action( 'plugins_loaded', 'naxoorbu_init' );

// Declare WooCommerce HPOS compatibility
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * Activation hook — flush rewrite rules.
 *
 * @since 1.0.0
 */
register_activation_hook( __FILE__, 'naxoorbu_activate' );
function naxoorbu_activate() {
    // Migrate old post type 'order_bump' to 'naxoorbu_order_bump' if needed
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->update(
        $wpdb->posts,
        [ 'post_type' => 'naxoorbu_order_bump' ],
        [ 'post_type' => 'order_bump' ]
    );
    flush_rewrite_rules();
}

/**
 * Deactivation hook — flush rewrite rules.
 *
 * @since 1.0.0
 */
register_deactivation_hook( __FILE__, 'naxoorbu_deactivate' );

function naxoorbu_deactivate() {
    flush_rewrite_rules();
}

/**
 * Register WooCommerce Blocks checkout integration.
 *
 * @since 2.0.0
 */
add_action( 'woocommerce_blocks_loaded', function() {
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
        return;
    }

    require_once NAXOORBU_PATH . 'includes/class-naxoorbu-blocks-integration.php';

    add_action(
        'woocommerce_blocks_checkout_block_registration',
        function( $integration_registry ) {
            $integration_registry->register( new Naxoorbu_Blocks_Integration() );
        }
    );
} );
