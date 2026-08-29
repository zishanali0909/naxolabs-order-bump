<?php
/**
 * Plugin Name: Order Bump Pro
 * Description: Add order bumps to WooCommerce checkout page like FunnelKit
 * Version: 2.1.0
 * Author: Your Name
 * Text Domain: order-bump-pro
 * Requires Plugins: woocommerce
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Check if WooCommerce is active before doing anything.
 */
function obp_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Order Bump Pro</strong> requires <strong>WooCommerce</strong> to be installed and active.</p></div>';
        });
        return false;
    }
    return true;
}

define( 'OBP_VERSION', '2.1.0' );
define( 'OBP_PATH', plugin_dir_path( __FILE__ ) );
define( 'OBP_URL', plugin_dir_url( __FILE__ ) );

require_once OBP_PATH . 'includes/class-obp-post-type.php';
require_once OBP_PATH . 'includes/class-obp-admin.php';
require_once OBP_PATH . 'includes/class-obp-frontend.php';
require_once OBP_PATH . 'includes/class-obp-ajax.php';
require_once OBP_PATH . 'includes/class-obp-discount.php';

function obp_init() {
    if ( ! obp_check_woocommerce() ) return;

    // Load translations
    load_plugin_textdomain( 'order-bump-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

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

register_activation_hook( __FILE__, 'obp_activate' );
function obp_activate() {
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'obp_deactivate' );
function obp_deactivate() {
    flush_rewrite_rules();
}

/**
 * Register WooCommerce Blocks checkout integration.
 * This enables order bumps in the block-based (Gutenberg) checkout.
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
