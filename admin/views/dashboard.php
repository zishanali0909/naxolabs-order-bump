<?php
/**
 * Admin Dashboard Template — Order Bump Pro
 *
 * Variables available: $bumps, $active
 *
 * @package OrderBumpPro
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap obp-wrap">
    <div class="obp-header">
        <div class="obp-header-left">
            <div class="obp-logo">⚡</div>
            <div>
                <h1><?php esc_html_e( 'Order Bump Pro', 'order-bump-pro' ); ?></h1>
                <p><?php esc_html_e( 'Boost revenue with smart checkout upsells', 'order-bump-pro' ); ?></p>
            </div>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=obp-edit' ) ); ?>" class="obp-btn obp-btn-primary">
            + <?php esc_html_e( 'Add New Order Bump', 'order-bump-pro' ); ?>
        </a>
    </div>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="obp-notice obp-notice-success">✅ <?php esc_html_e( 'Saved successfully.', 'order-bump-pro' ); ?></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="obp-notice obp-notice-success">🗑️ <?php esc_html_e( 'Deleted.', 'order-bump-pro' ); ?></div>
    <?php endif; ?>

    <div class="obp-stats-row">
        <div class="obp-stat-card">
            <span class="obp-stat-number"><?php echo count( $bumps ); ?></span>
            <span class="obp-stat-label"><?php esc_html_e( 'Total Bumps', 'order-bump-pro' ); ?></span>
        </div>
        <div class="obp-stat-card">
            <span class="obp-stat-number"><?php echo intval( $active ); ?></span>
            <span class="obp-stat-label"><?php esc_html_e( 'Active', 'order-bump-pro' ); ?></span>
        </div>
        <div class="obp-stat-card">
            <span class="obp-stat-number"><?php echo count( $bumps ) - $active; ?></span>
            <span class="obp-stat-label"><?php esc_html_e( 'Inactive', 'order-bump-pro' ); ?></span>
        </div>
    </div>

    <?php if ( empty( $bumps ) ) : ?>
        <div class="obp-empty-state">
            <div class="obp-empty-icon">🛒</div>
            <h3><?php esc_html_e( 'No Order Bumps Yet', 'order-bump-pro' ); ?></h3>
            <p><?php esc_html_e( 'Create your first bump.', 'order-bump-pro' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=obp-edit' ) ); ?>" class="obp-btn obp-btn-primary">
                <?php esc_html_e( 'Create First Bump', 'order-bump-pro' ); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="obp-table-wrap">
            <table class="obp-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'order-bump-pro' ); ?></th>
                        <th><?php esc_html_e( 'Products', 'order-bump-pro' ); ?></th>
                        <th><?php esc_html_e( 'Trigger', 'order-bump-pro' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'order-bump-pro' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'order-bump-pro' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $bumps as $bump ) :
                    $meta    = get_post_meta( $bump->ID, '_obp_settings', true ) ?: [];
                    $prods   = $meta['products'] ?? [];
                    $trigger = $meta['trigger_type'] ?? 'all';
                    $labels  = [
                        'all'              => __( 'All', 'order-bump-pro' ),
                        'specific_product' => __( 'Specific Product', 'order-bump-pro' ),
                        'category'         => __( 'Category', 'order-bump-pro' ),
                        'minimum_order'    => __( 'Min. Order', 'order-bump-pro' ),
                    ];
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $bump->post_title ); ?></strong></td>
                        <td>
                            <?php
                            /* translators: %d: number of products */
                            printf( esc_html__( '%d product(s)', 'order-bump-pro' ), count( $prods ) );
                            ?>
                        </td>
                        <td><span class="obp-badge obp-badge-blue"><?php echo esc_html( $labels[ $trigger ] ?? $trigger ); ?></span></td>
                        <td>
                            <label class="obp-toggle">
                                <input type="checkbox" class="obp-status-toggle" data-id="<?php echo intval( $bump->ID ); ?>" <?php checked( $bump->post_status, 'publish' ); ?>>
                                <span class="obp-toggle-slider"></span>
                            </label>
                        </td>
                        <td class="obp-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=obp-edit&edit=' . $bump->ID ) ); ?>" class="obp-action-btn obp-action-edit">
                                ✏️ <?php esc_html_e( 'Edit', 'order-bump-pro' ); ?>
                            </a>
                            <a href="#" class="obp-action-btn obp-action-delete"
                               data-id="<?php echo intval( $bump->ID ); ?>"
                               data-nonce="<?php echo esc_attr( wp_create_nonce( 'obp_delete_' . $bump->ID ) ); ?>">
                                🗑️ <?php esc_html_e( 'Delete', 'order-bump-pro' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
