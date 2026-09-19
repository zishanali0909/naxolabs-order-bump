<?php
/**
 * Plugin Name: WP Order Bump
 * Plugin URI:  https://github.com/zishanali0909/wp-order-bump
 * Description: Add order bumps to WooCommerce checkout page. Boost average order value with one-click upsells.
 * Version:     1.0.0
 * Author:      Zishan Ali
 * Author URI:  https://github.com/zishanali0909
 * Text Domain: wp-order-bump
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WPOrderBump
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Check if WooCommerce is active before doing anything.
 *
 * @since 1.0.0
 * @return bool
 */
function obp_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                wp_kses_post(
                    sprintf(
                        /* translators: %1$s: plugin name, %2$s: dependency name */
                        __( '<strong>%1$s</strong> requires <strong>%2$s</strong> to be installed and active.', 'wp-order-bump' ),
                        'WP Order Bump',
                        'WooCommerce'
                    )
                )
            );
        });
        return false;
    }
    return true;
}

define( 'OBP_VERSION', '1.0.0' );
define( 'OBP_PATH', plugin_dir_path( __FILE__ ) );
define( 'OBP_URL', plugin_dir_url( __FILE__ ) );
define( 'OBP_MAX_BUMPS', 2 );
define( 'OBP_MAX_PRODUCTS_PER_BUMP', 2 );

require_once OBP_PATH . 'includes/class-obp-post-type.php';
require_once OBP_PATH . 'includes/class-obp-admin.php';
require_once OBP_PATH . 'includes/class-obp-frontend.php';
require_once OBP_PATH . 'includes/class-obp-ajax.php';
require_once OBP_PATH . 'includes/class-obp-discount.php';

/**
 * Initialize plugin after all plugins are loaded.
 *
 * @since 1.0.0
 */
function obp_init() {

    if ( ! obp_check_woocommerce() ) return;

    load_plugin_textdomain( 'wp-order-bump', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // One-time auto-migration: old post type -> new
    if ( ! get_option( 'obp_migrated_post_type' ) ) {
        global $wpdb;
        $wpdb->update( $wpdb->posts, [ 'post_type' => 'obp_order_bump' ], [ 'post_type' => 'order_bump' ] );
        delete_transient( 'obp_published_bumps' );
        update_option( 'obp_migrated_post_type', '1', 'no' );
    }

    new OBP_Post_Type();
    new OBP_Admin();
    new OBP_Frontend();
    new OBP_Ajax();
    new OBP_Discount();
}
add_action( 'plugins_loaded', 'obp_init' );

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
register_activation_hook( __FILE__, 'obp_activate' );
function obp_activate() {
    // Migrate old post type 'order_bump' to 'obp_order_bump' if needed
    global $wpdb;
    $wpdb->update(
        $wpdb->posts,
        [ 'post_type' => 'obp_order_bump' ],
        [ 'post_type' => 'order_bump' ]
    );
    flush_rewrite_rules();
}

/**
 * Deactivation hook — flush rewrite rules.
 *
 * @since 1.0.0
 */
register_deactivation_hook( __FILE__, 'obp_deactivate' );

function obp_deactivate() {
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

    require_once OBP_PATH . 'includes/class-obp-blocks-integration.php';

    add_action(
        'woocommerce_blocks_checkout_block_registration',
        function( $integration_registry ) {
            $integration_registry->register( new OBP_Blocks_Integration() );
        }
    );
} );
