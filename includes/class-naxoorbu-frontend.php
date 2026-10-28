<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Naxoorbu_Frontend — Renders order bumps on the WooCommerce checkout page.
 *
 * Handles both classic shortcode-based checkout and Block checkout.
 *
 * @since   1.0.0
 * @package NaxolabsOrderBump
 */
class Naxoorbu_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Classic checkout hooks
        add_action( 'woocommerce_review_order_before_payment', [ $this, 'render_before_payment' ], 5 );
        add_action( 'woocommerce_review_order_before_submit',  [ $this, 'render_after_payment'  ], 5 );

        // Invalidate bump cache when a bump is saved/updated/deleted
        add_action( 'save_post_naxoorbu_order_bump',   [ $this, 'clear_bump_cache' ] );
        add_action( 'delete_post',            [ $this, 'clear_bump_cache' ] );
    }

    /**
     * Clear the cached bumps transient.
     */
    public function clear_bump_cache() {
        delete_transient( Naxoorbu_Visibility::CACHE_KEY );
    }

    /**
     * Enqueue frontend CSS/JS on the classic checkout. The block checkout gets its
     * assets from Naxoorbu_Blocks_Integration.
     *
     * @since 1.0.0
     */
    public function enqueue_assets() {
        if ( ! is_checkout() || $this->is_block_checkout() ) return;
        wp_enqueue_style(  'naxoorbu-frontend', NAXOORBU_URL . 'frontend/css/frontend.css', [], filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'frontend/css/frontend.css' ) );
        wp_enqueue_script( 'naxoorbu-frontend', NAXOORBU_URL . 'frontend/js/frontend.js', ['jquery'], filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'frontend/js/frontend.js' ), true );
        wp_localize_script( 'naxoorbu-frontend', 'naxoorbuFrontend', [
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('naxoorbu_frontend_nonce'),
            'i18n'            => [
                'error'           => __( 'Something went wrong.', 'naxolabs-order-bump' ),
                'connectionError' => __( 'Connection error. Please try again.', 'naxolabs-order-bump' ),
            ],
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

    private function get_bumps( $position ) {
        $out = [];

        foreach ( Naxoorbu_Visibility::get_published_bumps() as $bump_data ) {
            if ( ( $bump_data['meta']['position'] ?? 'before_payment' ) !== $position ) continue;
            if ( ! Naxoorbu_Visibility::is_visible( $bump_data ) ) continue;

            $out[] = $bump_data;
        }

        /**
         * Fires after bumps list is prepared, before rendering.
         *
         * @param array  $out      List of bump data arrays.
         * @param string $position Checkout position.
         */
        do_action( 'naxoorbu_bumps_displayed', $out, $position );

        return $out;
    }


    /**
     * Colour of the highlighted (<mark>) words in a bump's headline — shared by the
     * classic and the block checkout. Bumps saved before 1.2.0 get the default.
     *
     * @since 1.2.0
     * @param array $meta Bump settings.
     * @return string Hex colour.
     */
    public static function highlight_color( $meta ) {
        return sanitize_hex_color( $meta['highlight_color'] ?? '' ) ?: '#E15334';
    }

    /**
     * Skin, base layout and header colors for a bump — shared by the classic
     * checkout and the block checkout so both look the same.
     *
     * A skin that is not available (e.g. a Pro skin while Pro or its module is
     * off) falls back to Skin 1 with Skin 1 colors, instead of a half-styled mix.
     *
     * @since 1.1.0
     * @param array $meta Bump settings.
     * @return array { skin: string, layout: 'skin1'|'skin2', bg_color: string, text_color: string }
     */
    public static function get_skin_style( $meta ) {
        $skin = $meta['skin'] ?? 'skin1';
        if ( ! in_array( $skin, (array) apply_filters( 'naxoorbu_allowed_skins', array( 'skin1', 'skin2' ) ), true ) ) {
            $skin = 'skin1';
        }

        /**
         * Filter the base layout a skin is built on: 'skin1' (header row) or 'skin2'
         * (content first, call-to-action row at the bottom). Skins only add CSS on top.
         *
         * @since 1.1.0
         * @param string $layout Base layout.
         * @param string $skin   Skin slug.
         */
        $layout = apply_filters( 'naxoorbu_skin_layout', in_array( $skin, array( 'skin2', 'skin3' ), true ) ? 'skin2' : 'skin1', $skin );
        $layout = 'skin2' === $layout ? 'skin2' : 'skin1';

        // Resolve header colors per skin (extensible for Pro skins).
        $skin_color_map = apply_filters( 'naxoorbu_skin_color_defaults', array(
            'skin1' => array( 'bg' => '#FFFDE7', 'text' => '#155724' ),
            'skin2' => array( 'bg' => '#e8f7f9', 'text' => '#155724' ),
        ) );
        $skin_key       = isset( $skin_color_map[ $skin ] ) ? $skin : 'skin1';
        $color_defaults = $skin_color_map[ $skin_key ];

        return array(
            'skin'       => $skin,
            'layout'     => $layout,
            'bg_color'   => $meta[ $skin_key . '_bg_color' ] ?? $color_defaults['bg'],
            'text_color' => $meta[ $skin_key . '_text_color' ] ?? $color_defaults['text'],
        );
    }

    public function render_before_payment() {
        if ( $this->is_block_checkout() ) return; // The block checkout renders bumps via blocks-checkout.js.
        $this->render_bumps('before_payment');
    }
    public function render_after_payment() {
        if ( $this->is_block_checkout() ) return; // The block checkout renders bumps via blocks-checkout.js.
        $this->render_bumps('after_payment');
    }

    private function render_bumps( $position ) {
        // No cap here: the bump limit applies when creating bumps, so every published
        // bump keeps showing (e.g. after Pro is deactivated), same as the block checkout.
        $bumps = $this->get_bumps( $position );

        /**
         * Fires before bump rendering on checkout.
         *
         * @param array  $bumps    List of bump data arrays.
         * @param string $position Checkout position (before_payment|after_payment).
         */
        do_action( 'naxoorbu_before_checkout', $bumps, $position );

        foreach ( $bumps as $b ) {
            ob_start();
            $this->render_single_bump( $b['id'], $b['meta'] );
            $html = ob_get_clean();

            /**
             * Filter the rendered HTML of a single bump.
             *
             * @param string $html    The bump HTML output.
             * @param int    $bump_id The bump post ID.
             * @param array  $meta    The bump settings.
             */
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is built safely above, filter allows customization
            echo apply_filters( 'naxoorbu_bump_html', $html, $b['id'], $b['meta'] );
        }
    }

    private function render_single_bump( $bump_id, $meta ) {
        $products_meta = $meta['products'] ?? [];
        if ( empty($products_meta) ) return;

        /**
         * Fires before a single bump is rendered.
         *
         * @param int   $bump_id The bump post ID.
         * @param array $meta    The bump settings.
         */
        do_action( 'naxoorbu_before_bump_render', $bump_id, $meta );

        $headline        = $meta['headline']  ?? 'Yes! Add {{product_name}} to my order';
        $show_badge      = !empty( $meta['show_badge'] );
        $badge_text      = $meta['badge_text'] ?? 'Special Offer';
        $show_orig       = !empty( $meta['show_original_price'] );
        $skin_style         = self::get_skin_style( $meta );
        $skin               = $skin_style['skin'];
        $header_color       = $skin_style['bg_color'];
        $header_text_color  = $skin_style['text_color'];
        $show_img        = !empty( $meta['show_product_image'] );
        $img_type        = $meta['image_type'] ?? 'product';
        $img_width       = intval( $meta['image_width'] ?? 96 );
        $img_position    = $meta['image_position'] ?? 'left';
        $img_custom_url  = $meta['image_custom_url'] ?? '';

        $offered_ids = Naxoorbu_Visibility::get_offered_product_ids( [ 'id' => $bump_id, 'meta' => $meta ] );

        foreach ( $products_meta as $idx => $pm ) {
            if ( ! in_array( intval( $pm['id'] ?? 0 ), $offered_ids, true ) ) continue;

            $product = wc_get_product( $pm['id'] ?? 0 );
            if ( !$product ) continue;

            /**
             * Triggered when a bump product is rendered on checkout.
             * 
             * @param int $bump_id    Bump ID.
             * @param int $product_id Product ID.
             */
            do_action( 'naxoorbu_bump_rendered', $bump_id, $product->get_id() );

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
            $in_cart      = Naxoorbu_Visibility::has_bump_item_in_cart( $bump_id, $product->get_id() );
            $headline_html = str_replace(
                '{{product_name}}',
                esc_html($prod_name),
                wp_kses_post($headline)
            );

            // Build image HTML if enabled
            $img_html = '';
            if ( $show_img ) {
                if ( $img_type === 'custom' && $img_custom_url ) {
                    $img_src = esc_url($img_custom_url);
                } else {
                    $img_src = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_single' );
                }
                if ( $img_src ) {
                    $w = max(50, min(200, $img_width));
                    $img_html = '<img src="'.esc_url($img_src).'" alt="'.esc_attr($prod_name).'" style="width:'.$w.'px;height:'.$w.'px;object-fit:cover;border-radius:6px;flex-shrink:0;display:block;">';
                }
            }

            $wrap_id     = 'naxoorbu-bump-' . $bump_id . '-' . $idx;
            $check_id    = 'naxoorbu-check-' . $bump_id . '-' . $idx;
            $added_class = $in_cart ? 'naxoorbu-bump-is-added' : '';

            // Price HTML (wc_price already returns safe HTML)
            ob_start(); ?>
            <div class="naxoorbu-bump-prices">
                <?php if ( $show_orig && $regular > $offer_price ): ?>
                    <del class="naxoorbu-price-orig"><?php echo wp_kses_post( wc_price( $regular ) ); ?></del>
                <?php endif; ?>
                <span class="naxoorbu-price-final"><?php echo wp_kses_post( wc_price( $offer_price ) ); ?></span>
            </div>
            <?php $price_html = ob_get_clean(); ?>

            <div class="naxoorbu-bump-wrap naxoorbu-skin-<?php echo esc_attr( $skin ); ?> <?php echo esc_attr( $added_class ); ?> <?php echo ( $show_badge && $badge_text ) ? 'naxoorbu-has-badge' : ''; ?>"
                 style="--naxoorbu-header-color:<?php echo esc_attr( $header_color ); ?>;--naxoorbu-header-text:<?php echo esc_attr( $header_text_color ); ?>;--naxoorbu-highlight:<?php echo esc_attr( self::highlight_color( $meta ) ); ?>;"
                 id="<?php echo esc_attr( $wrap_id ); ?>"
                 data-product-id="<?php echo intval( $product->get_id() ); ?>"
                 data-bump-id="<?php echo intval( $bump_id ); ?>"
                 data-qty="<?php echo intval( $pm['qty'] ?? 1 ); ?>">

                <?php if ( $skin_style['layout'] === 'skin2' ): ?>
                    <!-- ═══ SKIN 2: Teal border, badge above, image+text row, CTA bottom ═══ -->

                    <?php if ( $show_badge && $badge_text ): ?>
                        <div class="naxoorbu-bump-badge"><?php echo esc_html( $badge_text ); ?></div>
                    <?php endif; ?>

                    <?php $body_pt = ( $show_badge && $badge_text ) ? 28 : 12; ?>
                    <div style="display:flex;gap:14px;align-items:flex-start;padding:<?php echo absint( $body_pt ); ?>px 16px 10px;">
                        <?php if ( $img_html ): ?>
                            <div style="flex-shrink:0;"><?php echo wp_kses_post( $img_html ); ?></div>
                        <?php endif; ?>
                        <?php if ( $description ): ?>
                            <div style="flex:1;min-width:0;">
                                <div class="naxoorbu-bump-description"><?php echo wp_kses_post( wpautop( $description ) ); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="border-top:1.5px dashed #2bbfbf;padding:10px 14px;display:flex;align-items:center;gap:8px;background:<?php echo esc_attr( $header_color ); ?>;">
                        <div class="naxoorbu-bump-arrow" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;min-width:20px;flex-shrink:0;">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;overflow:visible;">
                                <path d="M1 6H11M11 6L7 2M11 6L7 10" stroke="#E15334" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;flex:1;margin:0;" for="<?php echo esc_attr( $check_id ); ?>">
                            <input type="checkbox" class="naxoorbu-bump-checkbox" id="<?php echo esc_attr( $check_id ); ?>" <?php checked( $in_cart ); ?>>
                            <span class="naxoorbu-bump-checkmark"></span>
                            <span style="font-size:14px;font-weight:700;color:<?php echo esc_attr( $header_text_color ); ?>;"><?php echo wp_kses_post( $headline_html ); ?></span>
                        </label>
                        <?php echo wp_kses_post( $price_html ); ?>
                    </div>

                <?php else: ?>
                    <!-- ═══ SKIN 1: Classic yellow header ═══ -->
                    <?php if ( $show_badge && $badge_text ): ?>
                        <div class="naxoorbu-bump-badge"><?php echo esc_html( $badge_text ); ?></div>
                    <?php endif; ?>
                    <div class="naxoorbu-bump-headline-row" style="display:flex !important; align-items:center !important; flex-wrap:nowrap !important; gap:8px; padding:<?php echo esc_attr( ( $show_badge && $badge_text ) ? '28px' : '11px' ); ?> 12px 11px 12px; background:<?php echo esc_attr( $header_color ); ?> !important; color:<?php echo esc_attr( $header_text_color ); ?> !important;">
                        <div class="naxoorbu-bump-arrow" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;min-width:20px;flex-shrink:0;align-self:center;">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;overflow:visible;">
                                <path d="M1 6H11M11 6L7 2M11 6L7 10" stroke="#E15334" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <label class="naxoorbu-bump-check-label" for="<?php echo esc_attr( $check_id ); ?>" style="display:inline-flex !important;align-items:center !important;gap:8px;flex:1;min-width:0;cursor:pointer;margin:0;padding:0;font-weight:normal;">
                            <input type="checkbox" class="naxoorbu-bump-checkbox" id="<?php echo esc_attr( $check_id ); ?>" <?php checked( $in_cart ); ?>>
                            <span class="naxoorbu-bump-checkmark"></span>
                            <span class="naxoorbu-bump-headline-text"><?php echo wp_kses_post( $headline_html ); ?></span>
                        </label>
                        <?php echo wp_kses_post( $price_html ); ?>
                    </div>
                    <?php if ( $description || $img_html ): ?>
                        <div class="naxoorbu-bump-body">
                            <?php if ( $img_html && $img_position === 'left' ): ?>
                                <div class="naxoorbu-bump-body-inner naxoorbu-img-left naxoorbu-bump-description">
                                    <div class="naxoorbu-bump-body-img naxoorbu-float-left"><?php echo wp_kses_post( $img_html ); ?></div>
                                    <?php if ( $description ): echo wp_kses_post( wpautop( $description ) ); endif; ?>
                                </div>
                            <?php elseif ( $img_html && $img_position === 'right' ): ?>
                                <div class="naxoorbu-bump-body-inner naxoorbu-img-right naxoorbu-bump-description">
                                    <div class="naxoorbu-bump-body-img naxoorbu-float-right"><?php echo wp_kses_post( $img_html ); ?></div>
                                    <?php if ( $description ): echo wp_kses_post( wpautop( $description ) ); endif; ?>
                                </div>
                            <?php else: ?>
                                <?php if ( $description ): ?><div class="naxoorbu-bump-description"><?php echo wp_kses_post( wpautop( $description ) ); ?></div><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
            <?php
        }
    }
}
