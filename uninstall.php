<?php
/**
 * Naxolabs Order Bump � Uninstall Script
 *
 * Runs when the plugin is deleted via WordPress admin.
 * By default, plugin data (bumps, settings) is preserved so users
 * don't lose their configuration if they reinstall.
 * Only temporary cache data (transients) is cleaned up.
 *
 * @package NaxolabsOrderBump
 */

// Exit if not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Clean up transients (temporary cache only)
delete_transient( 'obp_published_bumps' );