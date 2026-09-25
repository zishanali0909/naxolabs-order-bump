<?php
/**
 * PHPUnit bootstrap file for Naxolabs Order Bump.
 *
 * Loads WordPress + WooCommerce test framework,
 * then loads our plugin so all classes are available.
 *
 * @package NaxolabsOrderBump
 */

// Path to WordPress test library (set by install-wp-tests.sh).
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
    // Load WooCommerce first.
    $wc_path = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
    if ( file_exists( $wc_path ) ) {
        require $wc_path;
    }

    // Load our plugin.
    require dirname( __DIR__ ) . '/order-bump.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugins' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';