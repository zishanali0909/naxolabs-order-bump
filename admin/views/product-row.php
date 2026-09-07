<?php
/**
 * Product Row Template — WP Order Bump
 *
 * Variables available: $i, $p, $field_prefix, $is_new
 *
 * @package WPOrderBump
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$stock_product = wc_get_product( $p['id'] );
$is_in_stock   = $stock_product && $stock_product->is_in_stock();
?>
<div class="obp-product-row" data-product-id="<?php echo intval( $p['id'] ); ?>">
    <div class="obp-product-row-header">
        <span class="obp-drag-handle">⠿</span>
        <?php if ( $p['thumb'] ) : ?>
            <img src="<?php echo esc_url( $p['thumb'] ); ?>" class="obp-product-thumb">
        <?php else : ?>
            <div class="obp-product-thumb obp-no-thumb">📦</div>
        <?php endif; ?>
        <div class="obp-product-row-info">
            <strong><?php echo esc_html( $p['name'] ); ?></strong>
            <span class="obp-muted">
                <?php
                /* translators: %s: regular price */
                printf( esc_html__( 'Regular: %s', 'wp-order-bump' ), wc_price( $p['regular_price'] ) );
                ?> |
                <?php
                /* translators: %s: sale price */
                printf( esc_html__( 'Sale: %s', 'wp-order-bump' ), wc_price( $p['price'] ) );
                ?>
            </span>
        </div>
        <div class="obp-product-row-controls">
            <input type="number" name="<?php echo esc_attr( $field_prefix ); ?>[discount]"
                   value="<?php echo esc_attr( $p['discount'] ); ?>"
                   class="obp-input obp-input-sm" min="0" max="100" step="0.01" style="width:70px" placeholder="0">
            <select name="<?php echo esc_attr( $field_prefix ); ?>[discount_type]" class="obp-select obp-select-sm">
                <option value="percentage" <?php selected( $p['discount_type'] ?? 'percentage', 'percentage' ); ?>>
                    <?php esc_html_e( '% Sale', 'wp-order-bump' ); ?>
                </option>
                <option value="flat" <?php selected( $p['discount_type'] ?? '', 'flat' ); ?>>
                    <?php esc_html_e( 'Flat ₹', 'wp-order-bump' ); ?>
                </option>
            </select>
            <input type="number" name="<?php echo esc_attr( $field_prefix ); ?>[qty]"
                   value="<?php echo esc_attr( $p['qty'] ?? 1 ); ?>"
                   class="obp-input obp-input-sm" min="1" max="99" style="width:52px">
            <span class="obp-badge <?php echo $is_in_stock ? 'obp-badge-green' : 'obp-badge-red'; ?>" style="white-space:nowrap">
                <?php echo $is_in_stock ? esc_html__( 'in-stock', 'wp-order-bump' ) : esc_html__( 'out-of-stock', 'wp-order-bump' ); ?>
            </span>
            <button type="button" class="obp-remove-product obp-btn-icon"
                    data-id="<?php echo intval( $p['id'] ); ?>"
                    title="<?php esc_attr_e( 'Remove', 'wp-order-bump' ); ?>">🗑️</button>
        </div>
        <input type="hidden" name="<?php echo esc_attr( $field_prefix ); ?>[id]" value="<?php echo intval( $p['id'] ); ?>">
    </div>
</div>
