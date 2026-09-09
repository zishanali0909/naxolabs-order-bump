<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * OBP_Frontend — Renders order bumps on the WooCommerce checkout page.
 *
 * Handles both classic shortcode-based checkout and Block checkout.
 *
 * @since   1.0.0
 * @package WPOrderBump
 */
class OBP_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Classic checkout hooks
        add_action( 'woocommerce_review_order_before_payment', [ $this, 'render_before_payment' ], 5 );
        add_action( 'woocommerce_review_order_before_submit',  [ $this, 'render_after_payment'  ], 5 );

        // Block checkout fallback: render bumps in wp_footer, JS injects them
        add_action( 'wp_footer', [ $this, 'render_block_checkout_fallback' ], 5 );

        // Invalidate bump cache when a bump is saved/updated/deleted
        add_action( 'save_post_obp_order_bump',   [ $this, 'clear_bump_cache' ] );
        add_action( 'delete_post',            [ $this, 'clear_bump_cache' ] );
    }

    /**
     * Clear the cached bumps transient.
     */
    public function clear_bump_cache() {
        delete_transient( 'obp_published_bumps' );
    }

    /**
     * Enqueue frontend CSS/JS on checkout page.
     *
     * @since 1.0.0
     */
    public function enqueue_assets() {
        if ( ! is_checkout() ) return;
        wp_enqueue_style(  'obp-frontend', OBP_URL . 'frontend/css/frontend.css', [], filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'frontend/css/frontend.css' ) );
        wp_enqueue_script( 'obp-frontend', OBP_URL . 'frontend/js/frontend.js', ['jquery'], filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'frontend/js/frontend.js' ), true );
        wp_localize_script( 'obp-frontend', 'obpFrontend', [
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('obp_frontend_nonce'),
            'isBlockCheckout' => $this->is_block_checkout(),
        ]);
    }

    /**
     * Check if the current checkout page uses WooCommerce Block checkout.
     */
    private function is_block_checkout() {
        $checkout_page_id = wc_get_page_id( 'checkout' );
        if ( ! $checkout_page_id ) return false;
        $post = get_post( $checkout_page_id );
        if ( ! $post ) return false;
        return has_block( 'woocommerce/checkout', $post );
    }

    /**
     * Block checkout fallback: output bumps in a hidden container in wp_footer.
     * frontend.js will detect this and inject into the block checkout via MutationObserver.
     */
    public function render_block_checkout_fallback() {
        if ( ! is_checkout() ) return;
        if ( ! $this->is_block_checkout() ) return;

        // Get ALL published bumps (no position filter — JS will handle)
        $all_bumps = $this->get_all_bumps_cached();
        if ( empty( $all_bumps ) ) return;

        echo '<div id="obp-block-checkout-bumps" style="display:none !important;">';
        foreach ( $all_bumps as $bump_data ) {
            $this->render_single_bump( $bump_data['id'], $bump_data['meta'] );
        }
        echo '</div>';
    }

    /**
     * Get all published bumps with their meta, using transient cache.
     */
    private function get_all_bumps_cached() {
        $cached = get_transient( 'obp_published_bumps' );
        if ( $cached !== false ) return $cached;

        $all  = get_posts(['post_type'=>'obp_order_bump','post_status'=>'publish','posts_per_page'=>-1]);
        $data = [];
        foreach ( $all as $bump ) {
            $meta = get_post_meta( $bump->ID, '_obp_settings', true );
            if ( empty($meta) ) continue;
            $data[] = ['id' => $bump->ID, 'meta' => $meta];
        }

        set_transient( 'obp_published_bumps', $data, 5 * MINUTE_IN_SECONDS );
        return $data;
    }

    private function get_bumps( $position ) {
        $all_bumps = $this->get_all_bumps_cached();
        $out = [];
        foreach ( $all_bumps as $bump_data ) {
            $meta = $bump_data['meta'];
            if ( ($meta['position'] ?? 'before_payment') !== $position ) continue;
            if ( !$this->check_trigger($meta) ) continue;
            $out[] = $bump_data;
        }
        return $out;
    }

    private function check_trigger( $meta ) {
        $type = $meta['trigger_type'] ?? 'all';
        if ( $type === 'all' ) return true;
        $cart_pids  = array_map( fn($i) => $i['product_id'], WC()->cart->get_cart() );
        $cart_total = WC()->cart->get_subtotal();
        if ( $type === 'specific_product' ) {
            return !empty( array_intersect( $cart_pids, (array)($meta['trigger_products'] ?? []) ) );
        }
        if ( $type === 'category' ) {
            foreach ( $cart_pids as $pid ) {
                $terms = wp_get_post_terms( $pid, 'product_cat', ['fields'=>'ids'] );
                if ( !empty( array_intersect( $terms, (array)($meta['trigger_categories'] ?? []) ) ) ) return true;
            }
            return false;
        }
        if ( $type === 'minimum_order' ) {
            return $cart_total >= floatval( $meta['minimum_order_amount'] ?? 0 );
        }
        return false;
    }

    private function is_in_cart( $product_id ) {
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( (int) $item['product_id'] === (int) $product_id ) return true;
        }
        return false;
    }

    public function render_before_payment() { $this->render_bumps('before_payment'); }
    public function render_after_payment()  { $this->render_bumps('after_payment'); }

    private function render_bumps( $position ) {
        foreach ( $this->get_bumps($position) as $b )
            $this->render_single_bump( $b['id'], $b['meta'] );
    }

    private function render_single_bump( $bump_id, $meta ) {
        $products_meta = $meta['products'] ?? [];
        if ( empty($products_meta) ) return;

        $headline        = $meta['headline']  ?? 'Yes! Add {{product_name}} to my order';
        $show_badge      = !empty( $meta['show_badge'] );
        $badge_text      = $meta['badge_text'] ?? 'Special Offer';
        $show_orig       = !empty( $meta['show_original_price'] );
        $skin            = $meta['skin'] ?? 'skin1';
        $header_color      = $skin === 'skin2' ? ($meta['skin2_bg_color'] ?? '#e8f7f9') : ($meta['skin1_bg_color'] ?? '#FFFDE7');
        $header_text_color = $skin === 'skin2' ? ($meta['skin2_text_color'] ?? '#155724') : ($meta['skin1_text_color'] ?? '#155724');
        $show_img        = !empty( $meta['show_product_image'] );
        $img_type        = $meta['image_type'] ?? 'product';
        $img_width       = intval( $meta['image_width'] ?? 96 );
        $img_position    = $meta['image_position'] ?? 'left';
        $img_custom_url  = $meta['image_custom_url'] ?? '';

        foreach ( $products_meta as $idx => $pm ) {
            $product = wc_get_product( $pm['id'] ?? 0 );
            if ( !$product ) continue;

            $regular     = floatval( $product->get_regular_price() );
            $sale        = floatval( $product->get_price() );
            $discount    = floatval( $pm['discount'] ?? 0 );
            $dtype       = $pm['discount_type'] ?? 'percentage';
            $offer_price = $discount > 0
                ? ( $dtype === 'percentage' ? $sale * (1 - $discount/100) : $sale - $discount )
                : $sale;
            $offer_price = max( 0, $offer_price );

            $prod_name    = $product->get_name();
            $description  = mb_substr( $pm['description'] ?? '', 0, 2000 );
            $in_cart      = $this->is_in_cart( $product->get_id() );
            $headline_html = str_replace(
                '{{product_name}}',
                '<strong>' . esc_html($prod_name) . '</strong>',
                wp_kses_post($headline)
            );

            // Build image HTML if enabled
            $img_html = '';
            if ( $show_img ) {
                if ( $img_type === 'custom' && $img_custom_url ) {
                    $img_src = esc_url($img_custom_url);
                } else {
                    $img_src = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
                }
                if ( $img_src ) {
                    $w = max(40, min(300, $img_width));
                    $img_html = '<img src="'.esc_url($img_src).'" alt="'.esc_attr($prod_name).'" style="width:'.$w.'px;height:'.$w.'px;object-fit:cover;border-radius:6px;flex-shrink:0;display:block;">';
                }
            }

            $wrap_id     = 'obp-bump-' . $bump_id . '-' . $idx;
            $check_id    = 'obp-check-' . $bump_id . '-' . $idx;
            $added_class = $in_cart ? 'obp-bump-is-added' : '';

            // Price HTML (wc_price already returns safe HTML)
            ob_start(); ?>
            <div class="obp-bump-prices">
                <?php if ( $show_orig && $regular > $offer_price ): ?>
                    <del class="obp-price-orig"><?php echo wp_kses_post( wc_price( $regular ) ); ?></del>
                <?php endif; ?>
                <span class="obp-price-final"><?php echo wp_kses_post( wc_price( $offer_price ) ); ?></span>
            </div>
            <?php $price_html = ob_get_clean(); ?>

            <div class="obp-bump-wrap obp-skin-<?php echo esc_attr( $skin ); ?> <?php echo esc_attr( $added_class ); ?> <?php echo ( $show_badge && $badge_text ) ? 'obp-has-badge' : ''; ?>"
                 id="<?php echo esc_attr( $wrap_id ); ?>"
                 data-product-id="<?php echo intval( $product->get_id() ); ?>"
                 data-qty="<?php echo intval( $pm['qty'] ?? 1 ); ?>">

                <?php if ( $skin === 'skin2' ): ?>
                    <!-- ═══ SKIN 2: Teal border, badge above, image+text row, CTA bottom ═══ -->

                    <?php if ( $show_badge && $badge_text ): ?>
                        <div class="obp-bump-badge"><?php echo esc_html( $badge_text ); ?></div>
                    <?php endif; ?>

                    <div style="display:flex;gap:14px;align-items:flex-start;padding:12px 16px 10px;">
                        <?php if ( $img_html ): ?>
                            <div style="flex-shrink:0;"><?php echo wp_kses_post( $img_html ); ?></div>
                        <?php endif; ?>
                        <?php if ( $description ): ?>
                            <div style="flex:1;min-width:0;">
                                <div class="obp-bump-description"><?php echo wp_kses_post( $description ); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="border-top:1.5px dashed #2bbfbf;padding:10px 14px;display:flex;align-items:center;gap:8px;background:<?php echo esc_attr( $header_color ); ?>;">
                        <div class="obp-bump-arrow" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;min-width:20px;flex-shrink:0;">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;overflow:visible;">
                                <path d="M1 6H11M11 6L7 2M11 6L7 10" stroke="#E15334" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;flex:1;margin:0;" for="<?php echo esc_attr( $check_id ); ?>">
                            <input type="checkbox" class="obp-bump-checkbox" id="<?php echo esc_attr( $check_id ); ?>" <?php checked( $in_cart ); ?>>
                            <span class="obp-bump-checkmark"></span>
                            <span style="font-size:14px;font-weight:800;color:<?php echo esc_attr( $header_text_color ); ?>;"><?php echo wp_kses_post( $headline_html ); ?></span>
                        </label>
                        <?php echo wp_kses_post( $price_html ); ?>
                    </div>

                <?php else: ?>
                    <!-- ═══ SKIN 1: Classic yellow header ═══ -->
                    <?php if ( $show_badge && $badge_text ): ?>
                        <div class="obp-bump-badge"><?php echo esc_html( $badge_text ); ?></div>
                    <?php endif; ?>
                    <div class="obp-bump-headline-row" style="display:flex !important; align-items:center !important; flex-wrap:nowrap !important; gap:8px; padding:<?php echo esc_attr( ( $show_badge && $badge_text ) ? '28px' : '11px' ); ?> 12px 11px 12px; background:<?php echo esc_attr( $header_color ); ?> !important; color:<?php echo esc_attr( $header_text_color ); ?> !important;">
                        <div class="obp-bump-arrow" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;min-width:20px;flex-shrink:0;align-self:center;">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;overflow:visible;">
                                <path d="M1 6H11M11 6L7 2M11 6L7 10" stroke="#E15334" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <label class="obp-bump-check-label" for="<?php echo esc_attr( $check_id ); ?>" style="display:inline-flex !important;align-items:center !important;gap:8px;flex:1;min-width:0;cursor:pointer;margin:0;padding:0;font-weight:normal;">
                            <input type="checkbox" class="obp-bump-checkbox" id="<?php echo esc_attr( $check_id ); ?>" <?php checked( $in_cart ); ?>>
                            <span class="obp-bump-checkmark"></span>
                            <span class="obp-bump-headline-text"><?php echo wp_kses_post( $headline_html ); ?></span>
                        </label>
                        <?php echo wp_kses_post( $price_html ); ?>
                    </div>
                    <?php if ( $description || $img_html ): ?>
                        <div class="obp-bump-body">
                            <?php if ( $img_html && $img_position === 'left' ): ?>
                                <div style="display:flex;gap:12px;align-items:flex-start;">
                                    <?php echo wp_kses_post( $img_html ); ?>
                                    <?php if ( $description ): ?><div class="obp-bump-description"><?php echo wp_kses_post( $description ); ?></div><?php endif; ?>
                                </div>
                            <?php elseif ( $img_html && $img_position === 'right' ): ?>
                                <div style="display:flex;gap:12px;align-items:flex-start;">
                                    <?php if ( $description ): ?><div class="obp-bump-description" style="flex:1;"><?php echo wp_kses_post( $description ); ?></div><?php endif; ?>
                                    <?php echo wp_kses_post( $img_html ); ?>
                                </div>
                            <?php else: ?>
                                <?php if ( $description ): ?><div class="obp-bump-description"><?php echo wp_kses_post( $description ); ?></div><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
            <?php
        }
    }
}
