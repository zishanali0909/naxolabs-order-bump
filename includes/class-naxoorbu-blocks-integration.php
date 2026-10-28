<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Naxoorbu_Blocks_Integration — WooCommerce Block Checkout integration.
 *
 * Registers scripts/styles and passes bump data to the block checkout
 * so order bumps render in the new Gutenberg-based checkout.
 *
 * @package NaxolabsOrderBump
 */
class Naxoorbu_Blocks_Integration implements IntegrationInterface {

    /**
     * Inner block that holds the bumps of one position in the checkout form
     * (used when the placement is 'checkout_fields').
     */
    const INNER_BLOCK = 'naxolabs-order-bump/order-bumps';

    /**
     * Integration name identifier.
     */
    public function get_name() {
        return 'naxolabs-order-bump';
    }

    /**
     * Register the block checkout script (WooCommerce enqueues it with the checkout block).
     *
     * WooCommerce may initialize integrations on any page, so nothing is enqueued here;
     * the stylesheet follows the checkout block (see enqueue_style()).
     */
    public function initialize() {
        wp_register_script(
            'naxoorbu-blocks-checkout',
            NAXOORBU_URL . 'frontend/js/blocks-checkout.js',
            [ 'wp-element', 'wp-plugins', 'wp-data', 'wp-hooks', 'wc-blocks-checkout', 'wc-blocks-data-store', 'jquery' ],
            filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'frontend/js/blocks-checkout.js' ),
            true
        );
    }

    /**
     * Bump styles, only on pages that render the checkout block
     * (hooked to woocommerce_blocks_enqueue_checkout_block_scripts_after).
     *
     * @since 1.1.0
     */
    public static function enqueue_style() {
        wp_enqueue_style(
            'naxoorbu-blocks-checkout',
            NAXOORBU_URL . 'frontend/css/frontend.css',
            [],
            filemtime( NAXOORBU_PATH . 'frontend/css/frontend.css' )
        );
    }

    /**
     * Script handles to enqueue on the frontend block checkout.
     */
    public function get_script_handles() {
        return [ 'naxoorbu-blocks-checkout' ];
    }

    /**
     * Script handles to enqueue in the editor (not needed for us).
     */
    public function get_editor_script_handles() {
        return [];
    }

    /**
     * Data passed to JS via wc.wcSettings.getSetting('naxolabs-order-bump_data').
     * Contains all published bumps with their product details.
     */
    public function get_script_data() {
        return $this->get_bumps_data();
    }

    /**
     * Build the complete bumps data array for the block checkout JS.
     */
    private function get_bumps_data() {
        // Same cached list as the classic checkout (cleared when a bump is saved or deleted).
        $published = Naxoorbu_Visibility::get_published_bumps();

        self::prime_product_caches( $published );

        $bumps = [];
        foreach ( $published as $published_bump ) {
            $bump_id = (int) $published_bump['id'];
            $meta    = $published_bump['meta'];
            if ( empty( $meta['products'] ) ) continue;

            // Build products with calculated prices
            $products = [];
            foreach ( $meta['products'] as $pm ) {
                $product = wc_get_product( $pm['id'] ?? 0 );
                if ( ! $product || ! $product->is_in_stock() ) continue;

                $price         = floatval( $product->get_price() );
                $regular       = floatval( $product->get_regular_price() );
                $discount      = floatval( $pm['discount'] ?? 0 );
                $discount_type = $pm['discount_type'] ?? 'percentage';
                $final         = Naxoorbu_Frontend::bump_price( $product, $pm );

                $img_url = '';
                $img_type = $meta['image_type'] ?? 'product';
                if ( ! empty( $meta['show_product_image'] ) ) {
                    if ( $img_type === 'custom' && ! empty( $meta['image_custom_url'] ) ) {
                        $img_url = $meta['image_custom_url'];
                    } else {
                        $img_url = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ?: '';
                    }
                }

                $products[] = [
                    'id'               => $product->get_id(),
                    'name'             => $product->get_name(),
                    'price'            => $price,
                    'regular_price'    => $regular,
                    'final_price'      => round( $final, 2 ),
                    'price_html'       => html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES, 'UTF-8' ),
                    'regular_html'     => html_entity_decode( wp_strip_all_tags( wc_price( $regular ) ), ENT_QUOTES, 'UTF-8' ),
                    'final_price_html' => html_entity_decode( wp_strip_all_tags( Naxoorbu_Frontend::price_html( $product, $final ) ), ENT_QUOTES, 'UTF-8' ),
                    'discount'         => $discount,
                    'discount_type'    => $discount_type,
                    'qty'              => intval( $pm['qty'] ?? 1 ),
                    'description'      => wp_kses_post( $pm['description'] ?? '' ),
                    'image'            => $img_url,
                ];
            }

            if ( empty( $products ) ) continue;

            // Same skin resolution as the classic checkout.
            $skin_style = Naxoorbu_Frontend::get_skin_style( $meta );

            $bump_data = [
                'id'                  => $bump_id,
                'headline'            => wp_kses_post( $meta['headline'] ?? '' ),
                'skin'                => $skin_style['skin'],
                'layout'              => $skin_style['layout'],
                'bg_color'            => $skin_style['bg_color'],
                'text_color'          => $skin_style['text_color'],
                'highlight_color'     => Naxoorbu_Frontend::highlight_color( $meta ),
                'show_badge'          => ! empty( $meta['show_badge'] ),
                'badge_text'          => $meta['badge_text'] ?? '',
                'show_original_price' => ! empty( $meta['show_original_price'] ),
                'show_product_image'  => ! empty( $meta['show_product_image'] ),
                'image_position'      => $meta['image_position'] ?? 'left',
                'position'            => $meta['position'] ?? 'before_payment',
                'placements'          => Naxoorbu_Frontend::get_placements( $published_bump ),
                'image_width'         => intval( $meta['image_width'] ?? 96 ),
                'trigger_type'        => $meta['trigger_type'] ?? 'all',
                'trigger_products'    => array_map( 'intval', (array) ( $meta['trigger_products'] ?? [] ) ),
                'trigger_categories'  => array_map( 'intval', (array) ( $meta['trigger_categories'] ?? [] ) ),
                'minimum_order_amount'=> floatval( $meta['minimum_order_amount'] ?? 0 ),
                'products'            => $products,
            ];

            /**
             * Filter a single bump's data before it is passed to the block checkout.
             *
             * Products may carry per-product overrides (headline, badge_text, show_badge)
             * which blocks-checkout.js prefers over the bump-level values.
             *
             * @since 1.1.0
             * @param array $bump_data Bump data for blocks-checkout.js.
             * @param int   $bump_id   Bump post ID.
             * @param array $meta      Bump settings (_naxoorbu_settings).
             */
            $bumps[] = apply_filters( 'naxoorbu_blocks_bump_data', $bump_data, $bump_id, $meta );
        }

        return [
            'bumps'             => $bumps,
            'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'naxoorbu_frontend_nonce' ),
            'storeApiNamespace' => Naxoorbu_Cart::STORE_API_NAMESPACE,
            'placement'         => self::get_placement(),
            // Only send impression requests when something records them (e.g. Pro Analytics).
            'trackImpressions'  => (bool) has_action( 'naxoorbu_bump_rendered' ),
        ];
    }

    /**
     * Load every bump product, and the thumbnails shown, in a few batched queries
     * instead of several queries per product.
     *
     * @param array $published Bumps from Naxoorbu_Visibility::get_published_bumps().
     */
    private static function prime_product_caches( $published ) {
        $product_ids = [];
        $with_images = [];
        foreach ( $published as $published_bump ) {
            foreach ( (array) ( $published_bump['meta']['products'] ?? [] ) as $pm ) {
                $id = absint( $pm['id'] ?? 0 );
                if ( ! $id ) continue;
                $product_ids[] = $id;
                if ( ! empty( $published_bump['meta']['show_product_image'] ) && 'custom' !== ( $published_bump['meta']['image_type'] ?? 'product' ) ) {
                    $with_images[] = $id;
                }
            }
        }
        if ( ! $product_ids ) return;

        _prime_post_caches( array_unique( $product_ids ) );

        $image_ids = [];
        foreach ( array_unique( $with_images ) as $id ) {
            $image_id = absint( get_post_meta( $id, '_thumbnail_id', true ) );
            if ( $image_id ) $image_ids[] = $image_id;
        }
        if ( $image_ids ) {
            _prime_post_caches( array_unique( $image_ids ), false, true );
        }
    }

    // ─── Placement ─────────────────────────────────────

    /**
     * Where the block checkout shows bumps.
     *
     * - 'order_meta' (default): in the order summary, through WooCommerce's ExperimentalOrderMeta slot.
     * - 'checkout_fields': in the checkout form at each bump's position — before the payment
     *   methods ("before_payment") or just before the Place Order button ("after_payment"),
     *   like the classic checkout.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_placement() {
        /**
         * Filter where the block checkout shows bumps: 'order_meta' or 'checkout_fields'.
         *
         * @since 1.1.0
         * @param string $placement Placement.
         */
        $placement = apply_filters( 'naxoorbu_blocks_bump_placement', 'order_meta' );

        return 'checkout_fields' === $placement ? 'checkout_fields' : 'order_meta';
    }

    /**
     * Placeholder markup for the bumps of one position. WooCommerce's block checkout maps it
     * to the inner block registered in blocks-checkout.js (via data-block-name).
     *
     * @since 1.1.0
     * @param string $position 'before_payment' or 'after_payment'.
     * @return string
     */
    public static function get_placeholder( $position ) {
        return sprintf(
            '<div data-block-name="%1$s" data-position="%2$s" class="wp-block-%3$s"></div>',
            esc_attr( self::INNER_BLOCK ),
            esc_attr( $position ),
            esc_attr( str_replace( '/', '-', self::INNER_BLOCK ) )
        );
    }

    /**
     * render_block filter: bumps shown before the payment methods.
     *
     * @since 1.1.0
     * @param string $block_content Payment block markup.
     * @return string
     */
    public static function render_before_payment( $block_content ) {
        if ( 'checkout_fields' !== self::get_placement() ) return $block_content;
        return self::get_placeholder( 'before_payment' ) . $block_content;
    }

    /**
     * render_block filter: bumps shown just before the Place Order button.
     *
     * @since 1.1.0
     * @param string $block_content Checkout actions block markup.
     * @return string
     */
    public static function render_before_actions( $block_content ) {
        if ( 'checkout_fields' !== self::get_placement() ) return $block_content;
        return self::get_placeholder( 'after_payment' ) . $block_content;
    }
}
