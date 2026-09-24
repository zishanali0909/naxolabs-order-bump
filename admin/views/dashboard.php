<?php
/**
 * Admin Dashboard Template — Naxolabs Order Bump
 *
 * Variables available: $bumps, $active
 *
 * @package NaxolabsOrderBump
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template file included in class method scope
?>
<div class="wrap naxoorbu-wrap">
    <h1 class="wp-heading-inline" style="display:none;"><?php esc_html_e( 'Order Bumps', 'naxolabs-order-bump' ); ?></h1>
    <hr class="wp-header-end">
    <div class="naxoorbu-header">
        <div class="naxoorbu-header-left">
            <div class="naxoorbu-logo">⚡</div>
            <div>
                <h1><?php esc_html_e( 'Naxolabs Order Bump', 'naxolabs-order-bump' ); ?></h1>
                <p><?php esc_html_e( 'Boost revenue with smart checkout upsells', 'naxolabs-order-bump' ); ?></p>
            </div>
        </div>
        <?php
            $max_bumps = apply_filters( 'naxoorbu_max_bumps', NAXOORBU_MAX_BUMPS );
            $current_count = wp_count_posts( 'naxoorbu_order_bump' );
            $total_bumps = intval( $current_count->publish ?? 0 ) + intval( $current_count->draft ?? 0 );
            if ( $total_bumps < $max_bumps ) :
        ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=naxoorbu-edit' ) ); ?>" class="naxoorbu-btn naxoorbu-btn-primary">
            + <?php esc_html_e( 'Add New Order Bump', 'naxolabs-order-bump' ); ?>
        </a>
        <?php else : ?>
        <span class="naxoorbu-btn" style="background:#f3f4f6;color:#6b7280;cursor:not-allowed;" title="<?php /* translators: %d: maximum bumps allowed */
        echo esc_attr( sprintf( __( 'Maximum %d order bumps allowed.', 'naxolabs-order-bump' ), $max_bumps ) ); ?>">
            <?php /* translators: %1$d: current bump count, %2$d: maximum bumps allowed */
            echo esc_html( sprintf( __( 'Limit: %1$d/%2$d Bumps', 'naxolabs-order-bump' ), $total_bumps, $max_bumps ) ); ?>
        </span>
        <?php endif; ?>
    </div>

    <?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <div class="naxoorbu-notice naxoorbu-notice-success">✅ <?php esc_html_e( 'Saved successfully.', 'naxolabs-order-bump' ); ?></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <div class="naxoorbu-notice naxoorbu-notice-success">🗑️ <?php esc_html_e( 'Deleted.', 'naxolabs-order-bump' ); ?></div>
    <?php endif; ?>

    <div class="naxoorbu-stats-row">
        <div class="naxoorbu-stat-card">
            <span class="naxoorbu-stat-number"><?php echo intval( count( $bumps ) ); ?></span>
            <span class="naxoorbu-stat-label"><?php esc_html_e( 'Total Bumps', 'naxolabs-order-bump' ); ?></span>
        </div>
        <div class="naxoorbu-stat-card">
            <span class="naxoorbu-stat-number"><?php echo intval( $active ); ?></span>
            <span class="naxoorbu-stat-label"><?php esc_html_e( 'Active', 'naxolabs-order-bump' ); ?></span>
        </div>
        <div class="naxoorbu-stat-card">
            <span class="naxoorbu-stat-number"><?php echo intval( count( $bumps ) - $active ); ?></span>
            <span class="naxoorbu-stat-label"><?php esc_html_e( 'Inactive', 'naxolabs-order-bump' ); ?></span>
        </div>
        <div class="naxoorbu-stat-card naxoorbu-stat-revenue">
            <span class="naxoorbu-stat-number"><?php echo wp_kses_post( wc_price( $bump_revenue ) ); ?></span>
            <span class="naxoorbu-stat-label"><?php esc_html_e( 'Bump Revenue', 'naxolabs-order-bump' ); ?></span>
        </div>
    </div>

    <?php if ( empty( $bumps ) ) : ?>
        <div class="naxoorbu-empty-state">
            <div class="naxoorbu-empty-icon">🛒</div>
            <h3><?php esc_html_e( 'No Order Bumps Yet', 'naxolabs-order-bump' ); ?></h3>
            <p><?php esc_html_e( 'Create your first bump.', 'naxolabs-order-bump' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=naxoorbu-edit' ) ); ?>" class="naxoorbu-btn naxoorbu-btn-primary">
                <?php esc_html_e( 'Create First Bump', 'naxolabs-order-bump' ); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="naxoorbu-table-wrap">
            <table class="naxoorbu-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'naxolabs-order-bump' ); ?></th>
                        <th><?php esc_html_e( 'Products', 'naxolabs-order-bump' ); ?></th>
                        <th><?php esc_html_e( 'Trigger', 'naxolabs-order-bump' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'naxolabs-order-bump' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'naxolabs-order-bump' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $bumps as $bump ) :
                    $meta    = get_post_meta( $bump->ID, '_naxoorbu_settings', true ) ?: [];
                    $prods   = $meta['products'] ?? [];
                    $trigger = $meta['trigger_type'] ?? 'all';
                    $labels  = [
                        'all'              => __( 'All', 'naxolabs-order-bump' ),
                        'specific_product' => __( 'Specific Product', 'naxolabs-order-bump' ),
                        'category'         => __( 'Category', 'naxolabs-order-bump' ),
                        'minimum_order'    => __( 'Min. Order', 'naxolabs-order-bump' ),
                    ];
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $bump->post_title ); ?></strong></td>
                        <td>
                            <?php
                            /* translators: %d: number of products */
                            /* translators: %d: number of products */
                                printf( esc_html__( '%d product(s)', 'naxolabs-order-bump' ), count( $prods ) );
                            ?>
                        </td>
                        <td><span class="naxoorbu-badge naxoorbu-badge-blue"><?php echo esc_html( $labels[ $trigger ] ?? $trigger ); ?></span></td>
                        <td>
                            <label class="naxoorbu-toggle">
                                <input type="checkbox" class="naxoorbu-status-toggle" data-id="<?php echo intval( $bump->ID ); ?>" <?php checked( $bump->post_status, 'publish' ); ?>>
                                <span class="naxoorbu-toggle-slider"></span>
                            </label>
                        </td>
                        <td class="naxoorbu-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=naxoorbu-edit&edit=' . $bump->ID ) ); ?>" class="naxoorbu-action-btn naxoorbu-action-edit">
                                ✏️ <?php esc_html_e( 'Edit', 'naxolabs-order-bump' ); ?>
                            </a>
                            <a href="#" class="naxoorbu-action-btn naxoorbu-action-delete"
                               data-id="<?php echo intval( $bump->ID ); ?>"
                               data-nonce="<?php echo esc_attr( wp_create_nonce( 'naxoorbu_delete_' . $bump->ID ) ); ?>">
                                🗑️ <?php esc_html_e( 'Delete', 'naxolabs-order-bump' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
