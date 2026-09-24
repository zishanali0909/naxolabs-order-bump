<?php
/**
 * Product Row Template — Naxolabs Order Bump
 *
 * Variables available: $i, $p, $field_prefix, $is_new
 *
 * @package NaxolabsOrderBump
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$stock_product = wc_get_product( $p['id'] );
$is_in_stock   = $stock_product && $stock_product->is_in_stock();
?>
<div class="naxoorbu-product-row" data-product-id="<?php echo intval( $p['id'] ); ?>">
    <div class="naxoorbu-product-row-header">
        <span class="naxoorbu-drag-handle">⠿</span>
        <?php if ( $p['thumb'] ) : ?>
            <img src="<?php echo esc_url( $p['thumb'] ); ?>" class="naxoorbu-product-thumb">
        <?php else : ?>
            <div class="naxoorbu-product-thumb naxoorbu-no-thumb">📦</div>
        <?php endif; ?>
        <div class="naxoorbu-product-row-info">
            <strong><?php echo esc_html( $p['name'] ); ?></strong>
            <span class="naxoorbu-muted">
                <?php
                /* translators: %s: regular price */
                /* translators: %s: formatted price with HTML */
                echo wp_kses_post( sprintf( __( 'Regular: %s', 'naxolabs-order-bump' ), wc_price( $p['regular_price'] ) ) );
                ?> |
                <?php
                /* translators: %s: sale price */
                /* translators: %s: formatted price with HTML */
                echo wp_kses_post( sprintf( __( 'Sale: %s', 'naxolabs-order-bump' ), wc_price( $p['price'] ) ) );
                ?>
            </span>
        </div>
        <div class="naxoorbu-product-row-controls">
            <input type="number" name="<?php echo esc_attr( $field_prefix ); ?>[discount]"
                   value="<?php echo esc_attr( $p['discount'] ); ?>"
                   class="naxoorbu-input naxoorbu-input-sm" min="0" max="100" step="0.01" style="width:70px" placeholder="0">
            <select name="<?php echo esc_attr( $field_prefix ); ?>[discount_type]" class="naxoorbu-select naxoorbu-select-sm">
                <option value="percentage" <?php selected( $p['discount_type'] ?? 'percentage', 'percentage' ); ?>>
                    <?php esc_html_e( '% Sale', 'naxolabs-order-bump' ); ?>
                </option>
                <option value="flat" <?php selected( $p['discount_type'] ?? '', 'flat' ); ?>>
                    <?php
                    /* translators: %s: currency symbol */
                    /* translators: %s: currency symbol */
                        printf( esc_html__( 'Flat %s', 'naxolabs-order-bump' ), esc_html( get_woocommerce_currency_symbol() ) );
                    ?>
                </option>
            </select>
            <input type="number" name="<?php echo esc_attr( $field_prefix ); ?>[qty]"
                   value="<?php echo esc_attr( $p['qty'] ?? 1 ); ?>"
                   class="naxoorbu-input naxoorbu-input-sm" min="1" max="99" style="width:52px">
            <span class="naxoorbu-badge <?php echo $is_in_stock ? 'naxoorbu-badge-green' : 'naxoorbu-badge-red'; ?>" style="white-space:nowrap">
                <?php echo $is_in_stock ? esc_html__( 'in-stock', 'naxolabs-order-bump' ) : esc_html__( 'out-of-stock', 'naxolabs-order-bump' ); ?>
            </span>
            <button type="button" class="naxoorbu-remove-product naxoorbu-btn-icon"
                    data-id="<?php echo intval( $p['id'] ); ?>"
                    title="<?php esc_attr_e( 'Remove', 'naxolabs-order-bump' ); ?>">🗑️</button>
        </div>
        <input type="hidden" name="<?php echo esc_attr( $field_prefix ); ?>[id]" value="<?php echo intval( $p['id'] ); ?>">
    </div>
</div>
