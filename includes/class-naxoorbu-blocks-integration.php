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
     * Integration name identifier.
     */
    public function get_name() {
        return 'naxolabs-order-bump';
    }

    /**
     * Register scripts and styles for the block checkout.
     */
    public function initialize() {
        wp_register_script(
            'naxoorbu-blocks-checkout',
            NAXOORBU_URL . 'frontend/js/blocks-checkout.js',
            [ 'wp-element', 'wp-plugins', 'wp-data', 'wp-hooks', 'wc-blocks-checkout', 'wc-blocks-data-store', 'jquery' ],
            filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'frontend/js/blocks-checkout.js' ),
            true
        );

        wp_register_style(
            'naxoorbu-blocks-checkout',
            NAXOORBU_URL . 'frontend/css/frontend.css',
            [],
            filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'frontend/css/frontend.css' )
        );

        // Enqueue styles alongside the script
        wp_enqueue_style( 'naxoorbu-blocks-checkout' );
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
        $bumps_posts = get_posts( [
            'post_type'      => 'naxoorbu_order_bump',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ] );

        $bumps = [];
        foreach ( $bumps_posts as $bump ) {
            $meta = get_post_meta( $bump->ID, '_naxoorbu_settings', true );
            if ( empty( $meta ) || empty( $meta['products'] ) ) continue;

            // Build products with calculated prices
            $products = [];
            foreach ( $meta['products'] as $pm ) {
                $product = wc_get_product( $pm['id'] ?? 0 );
                if ( ! $product || ! $product->is_in_stock() ) continue;

                $price         = floatval( $product->get_price() );
                $regular       = floatval( $product->get_regular_price() );
                $discount      = floatval( $pm['discount'] ?? 0 );
                $discount_type = $pm['discount_type'] ?? 'percentage';

                if ( $discount > 0 ) {
                    $final = $discount_type === 'percentage'
                        ? $price * ( 1 - $discount / 100 )
                        : $price - $discount;
                } else {
                    $final = $price;
                }
                $final = max( 0, $final );

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
                    'final_price_html' => html_entity_decode( wp_strip_all_tags( wc_price( $final ) ), ENT_QUOTES, 'UTF-8' ),
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
                'id'                  => $bump->ID,
                'headline'            => wp_kses_post( $meta['headline'] ?? '' ),
                'skin'                => $skin_style['skin'],
                'layout'              => $skin_style['layout'],
                'bg_color'            => $skin_style['bg_color'],
                'text_color'          => $skin_style['text_color'],
                'show_badge'          => ! empty( $meta['show_badge'] ),
                'badge_text'          => $meta['badge_text'] ?? '',
                'show_original_price' => ! empty( $meta['show_original_price'] ),
                'show_product_image'  => ! empty( $meta['show_product_image'] ),
                'image_position'      => $meta['image_position'] ?? 'left',
                'position'            => $meta['position'] ?? 'before_payment',
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
             * @since 1.0.1
             * @param array $bump_data Bump data for blocks-checkout.js.
             * @param int   $bump_id   Bump post ID.
             * @param array $meta      Bump settings (_naxoorbu_settings).
             */
            $bumps[] = apply_filters( 'naxoorbu_blocks_bump_data', $bump_data, $bump->ID, $meta );
        }

        return [
            'bumps'             => $bumps,
            'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'naxoorbu_frontend_nonce' ),
            'storeApiNamespace' => Naxoorbu_Cart::STORE_API_NAMESPACE,
        ];
    }
}
