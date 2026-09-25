<?php
/**
 * PHPUnit bootstrap file for Naxolabs Order Bump.
 *
 * Loads WordPress + WooCommerce test framework,
 * creates WooCommerce tables, then loads our plugin.
 *
 * @package NaxolabsOrderBump
 */

// Path to WordPress test library.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Path to WordPress source.
$_core_dir = getenv( 'WP_CORE_DIR' );
if ( ! $_core_dir ) {
    $_core_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress';
}

// Check test framework exists.
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "Could not find WordPress test library at: {$_tests_dir}\n";
    echo "Run: bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
    exit( 1 );
}

// Load test framework functions.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Load WooCommerce and our plugin before tests run.
 */
function _manually_load_plugins() {
    // Define WC constants needed for testing.
    define( 'WC_TAX_ROUNDING_MODE', 'auto' );

    // Load WooCommerce.
    $wc_dir = WP_PLUGIN_DIR . '/woocommerce';
    if ( ! file_exists( $wc_dir . '/woocommerce.php' ) ) {
        echo "WooCommerce not found at: {$wc_dir}\n";
        exit( 1 );
    }
    require_once $wc_dir . '/woocommerce.php';

    // Load our plugin.
    require_once dirname( __DIR__ ) . '/order-bump.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugins' );

/**
 * After WordPress boots, install WooCommerce tables + helpers.
 */
function _install_woocommerce() {
    echo "Installing WooCommerce tables...\n";

    // Mark WooCommerce as active plugin.
    update_option( 'active_plugins', array(
        'woocommerce/woocommerce.php',
        'naxolabs-order-bump/order-bump.php',
    ) );

    // Run the WooCommerce installer (creates all DB tables).
    if ( class_exists( 'WC_Install' ) ) {
        WC_Install::install();
        echo "WC_Install::install() done.\n";
    }

    // Initialize WooCommerce.
    if ( function_exists( 'WC' ) ) {
        WC()->init();
        echo "WC()->init() done.\n";
    }

    // Flush rewrite rules.
    flush_rewrite_rules();

    // Load WC test helpers (WC_Helper_Product, etc.).
    $wc_tests_dir = WP_PLUGIN_DIR . '/woocommerce/tests';
    $wc_helpers   = WP_PLUGIN_DIR . '/woocommerce/tests/legacy/helpers';

    // Try different WC helper paths (varies by WC version).
    $helper_paths = array(
        $wc_helpers . '/class-wc-helper-product.php',
        WP_PLUGIN_DIR . '/woocommerce/tests/helpers/class-wc-helper-product.php',
        WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-helper-product.php',
    );

    $loaded = false;
    foreach ( $helper_paths as $path ) {
        if ( file_exists( $path ) ) {
            require_once $path;
            $loaded = true;
            echo "WC_Helper_Product loaded from: {$path}\n";
            break;
        }
    }

    // If no helper found, define a minimal one for tests.
    if ( ! $loaded && ! class_exists( 'WC_Helper_Product' ) ) {
        echo "WC_Helper_Product not found, creating minimal helper.\n";

        class WC_Helper_Product {
            public static function create_simple_product( $save = true, $props = array() ) {
                $defaults = array(
                    'name'          => 'Test Product ' . wp_rand(),
                    'regular_price' => '10',
                    'price'         => '10',
                    'sku'           => 'test-sku-' . wp_rand(),
                    'manage_stock'  => false,
                    'tax_status'    => 'taxable',
                    'downloadable'  => false,
                    'virtual'       => false,
                    'stock_status'  => 'instock',
                    'weight'        => '1.1',
                );

                $props   = wp_parse_args( $props, $defaults );
                $product = new WC_Product_Simple();

                foreach ( $props as $key => $value ) {
                    $setter = "set_{$key}";
                    if ( is_callable( array( $product, $setter ) ) ) {
                        $product->$setter( $value );
                    }
                }

                if ( $save ) {
                    $product->save();
                }

                return $product;
            }
        }
    }

    // Verify tables exist.
    global $wpdb;
    $table = $wpdb->prefix . 'wc_webhooks';
    $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
    if ( $exists ) {
        echo "WooCommerce tables verified OK.\n";
    } else {
        echo "WARNING: {$table} not found, running create_tables()...\n";
        if ( class_exists( 'WC_Install' ) ) {
            WC_Install::create_tables();
            echo "create_tables() done.\n";
        }
    }
}
tests_add_filter( 'setup_theme', '_install_woocommerce' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Ensure WC session is started for cart operations.
if ( function_exists( 'WC' ) && is_null( WC()->session ) ) {
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
}
if ( function_exists( 'WC' ) && is_null( WC()->cart ) ) {
    WC()->cart = new WC_Cart();
}
if ( function_exists( 'WC' ) && is_null( WC()->customer ) ) {
    WC()->customer = new WC_Customer( 0, true );
}

echo "\n=== Bootstrap complete ===\n";