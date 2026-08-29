<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * OBP_Admin — Admin controller for Order Bump Pro.
 *
 * Handles menu registration, asset enqueueing, rendering (via templates),
 * and form save processing. HTML templates are in admin/views/.
 *
 * @package OrderBumpPro
 */
class OBP_Admin {

    public function __construct() {
        add_action( 'admin_menu',               [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts',    [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_obp_save_bump', [ $this, 'handle_save' ] );
    }

    /**
     * Register admin menu pages.
     */
    public function add_menu() {
        add_menu_page(
            __( 'Order Bump Pro', 'order-bump-pro' ),
            __( 'Order Bumps', 'order-bump-pro' ),
            'manage_woocommerce',
            'order-bump-pro',
            [ $this, 'render_dashboard' ],
            'dashicons-cart',
            56
        );
        add_submenu_page(
            'order-bump-pro',
            __( 'All Bumps', 'order-bump-pro' ),
            __( 'All Bumps', 'order-bump-pro' ),
            'manage_woocommerce',
            'order-bump-pro',
            [ $this, 'render_dashboard' ]
        );
        add_submenu_page(
            'order-bump-pro',
            __( 'Add New', 'order-bump-pro' ),
            __( 'Add New', 'order-bump-pro' ),
            'manage_woocommerce',
            'obp-edit',
            [ $this, 'render_edit_page' ]
        );
    }

    /**
     * Enqueue admin CSS/JS assets.
     */
    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'order-bump' ) === false && strpos( $hook, 'obp-' ) === false ) return;

        $css_path = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/css/admin.css';
        $js_path  = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/js/admin.js';
        wp_enqueue_style( 'obp-admin', OBP_URL . 'admin/css/admin.css', [], filemtime( $css_path ) );
        wp_enqueue_script( 'obp-admin', OBP_URL . 'admin/js/admin.js', [ 'jquery', 'jquery-ui-sortable' ], filemtime( $js_path ), true );
        wp_enqueue_editor();
        wp_enqueue_media();

        $edit_id   = isset( $_GET['edit'] ) ? intval( $_GET['edit'] ) : 0;
        $edit_meta = $edit_id ? ( get_post_meta( $edit_id, '_obp_settings', true ) ?: [] ) : [];

        // Get first product image for preview (with strong fallback chain)
        $first_prod_img = '';
        $edit_prods     = $edit_meta['products'] ?? [];
        if ( ! empty( $edit_prods ) ) {
            $fp = wc_get_product( $edit_prods[0]['id'] ?? 0 );
            if ( $fp ) {
                // Try 1: Product featured image via attachment ID
                $img_id = $fp->get_image_id();
                if ( $img_id ) {
                    $first_prod_img = wp_get_attachment_image_url( $img_id, 'thumbnail' );
                }
                // Try 2: Extract from WC's get_image() (handles all WC fallbacks)
                if ( ! $first_prod_img ) {
                    $img_html = $fp->get_image( 'thumbnail' );
                    if ( preg_match( '/src=["\']([^"\']+)/', $img_html, $matches ) ) {
                        $first_prod_img = $matches[1];
                    }
                }
                // Try 3: WooCommerce placeholder
                if ( ! $first_prod_img ) {
                    $first_prod_img = wc_placeholder_img_src( 'thumbnail' );
                }
            }
        }

        // Custom image URL (if set)
        $custom_img_url = $edit_meta['image_custom_url'] ?? '';
        $image_type     = $edit_meta['image_type'] ?? 'product';

        wp_localize_script( 'obp-admin', 'obpAdmin', [
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'obp_admin_nonce' ),
            'currentSkin'    => $edit_meta['skin'] ?? 'skin1',
            'skin1BgColor'   => $edit_meta['skin1_bg_color'] ?? '#FFFDE7',
            'skin1TextColor' => $edit_meta['skin1_text_color'] ?? '#155724',
            'skin2BgColor'   => $edit_meta['skin2_bg_color'] ?? '#e8f7f9',
            'skin2TextColor' => $edit_meta['skin2_text_color'] ?? '#155724',
            'firstProductImg'=> $first_prod_img,
            'customImageUrl' => $custom_img_url,
            'imageType'      => $image_type,
            'i18n'           => [
                'deleteConfirm'  => __( 'Delete this Order Bump?', 'order-bump-pro' ),
                'productAdded'   => __( 'Product added! Go to Design tab to set its description.', 'order-bump-pro' ),
                'alreadyAdded'   => __( 'is already added.', 'order-bump-pro' ),
                'noProducts'     => __( 'No products found.', 'order-bump-pro' ),
                'active'         => __( 'Active', 'order-bump-pro' ),
                'inactive'       => __( 'Inactive', 'order-bump-pro' ),
                'noDescription'  => __( 'No description set.', 'order-bump-pro' ),
                'errorDeleting'  => __( 'Error deleting.', 'order-bump-pro' ),
                'error'          => __( 'Error.', 'order-bump-pro' ),
                'inStock'        => __( 'in-stock', 'order-bump-pro' ),
            ],
        ] );
    }

    /**
     * Render dashboard page — delegates to template.
     */
    public function render_dashboard() {
        $bumps  = get_posts( [
            'post_type'      => 'order_bump',
            'posts_per_page' => -1,
            'post_status'    => [ 'publish', 'draft' ],
        ] );
        $active = count( array_filter( $bumps, fn( $b ) => $b->post_status === 'publish' ) );

        include OBP_PATH . 'admin/views/dashboard.php';
    }

    /**
     * Render edit/create bump page — delegates to template.
     */
    public function render_edit_page() {
        $edit_id = isset( $_GET['edit'] ) ? intval( $_GET['edit'] ) : 0;
        $bump    = $edit_id ? get_post( $edit_id ) : null;
        $raw     = $edit_id ? ( get_post_meta( $edit_id, '_obp_settings', true ) ?: [] ) : [];

        $defaults = [
            'products'             => [],
            'behaviour'            => 'add',
            'trigger_type'         => 'all',
            'trigger_products'     => [],
            'trigger_categories'   => [],
            'minimum_order_amount' => '',
            'headline'             => '<span style="color:#E15334">Yes!</span> Add {{product_name}} to my order',
            'badge_text'           => 'Special Offer',
            'show_badge'           => 0,
            'show_original_price'  => 1,
            'skin1_bg_color'       => '#FFFDE7',
            'skin1_text_color'     => '#155724',
            'skin2_bg_color'       => '#e8f7f9',
            'skin2_text_color'     => '#155724',
            'show_product_image'   => 0,
            'image_type'           => 'product',
            'image_width'          => 96,
            'image_position'       => 'left',
            'image_custom_url'     => '',
            'position'             => 'before_payment',
            'bump_status'          => 'publish',
            'skin'                 => 'skin1',
        ];
        $meta = wp_parse_args( $raw, $defaults );

        // Build saved products array
        $saved_products = [];
        foreach ( ( $meta['products'] ?? [] ) as $p_data ) {
            $prod = wc_get_product( $p_data['id'] ?? 0 );
            if ( ! $prod ) continue;
            $saved_products[] = [
                'id'            => $prod->get_id(),
                'name'          => $prod->get_name(),
                'price'         => $prod->get_price(),
                'regular_price' => $prod->get_regular_price(),
                'discount'      => $p_data['discount'] ?? 0,
                'discount_type' => $p_data['discount_type'] ?? 'percentage',
                'qty'           => $p_data['qty'] ?? 1,
                'description'   => $p_data['description'] ?? '',
                'thumb'         => wp_get_attachment_image_url( $prod->get_image_id(), 'thumbnail' ),
            ];
        }

        $all_categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'design';

        include OBP_PATH . 'admin/views/edit-bump.php';
    }

    /**
     * Handle form save (admin-post.php).
     */
    public function handle_save() {
        if ( ! wp_verify_nonce( $_POST['obp_nonce'] ?? '', 'obp_save_bump' ) ) {
            wp_die( esc_html__( 'Security check failed', 'order-bump-pro' ) );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'order-bump-pro' ) );
        }

        $bump_id  = intval( $_POST['bump_id'] ?? 0 );
        $title    = sanitize_text_field( $_POST['bump_title'] ?? 'Order Bump' );
        $settings = $_POST['obp_settings'] ?? [];

        $clean = [];
        $clean['headline']             = sanitize_text_field( $settings['headline'] ?? '' );
        $clean['badge_text']           = sanitize_text_field( $settings['badge_text'] ?? '' );
        $clean['show_badge']           = isset( $settings['show_badge'] ) ? 1 : 0;
        $clean['show_original_price']  = isset( $settings['show_original_price'] ) ? 1 : 0;
        $clean['skin1_bg_color']       = sanitize_hex_color( $settings['skin1_bg_color'] ?? '#FFFDE7' ) ?: '#FFFDE7';
        $clean['skin1_text_color']     = sanitize_hex_color( $settings['skin1_text_color'] ?? '#155724' ) ?: '#155724';
        $clean['skin2_bg_color']       = sanitize_hex_color( $settings['skin2_bg_color'] ?? '#e8f7f9' ) ?: '#e8f7f9';
        $clean['skin2_text_color']     = sanitize_hex_color( $settings['skin2_text_color'] ?? '#155724' ) ?: '#155724';
        $clean['show_product_image']   = isset( $settings['show_product_image'] ) ? 1 : 0;
        $clean['image_type']           = in_array( $settings['image_type'] ?? '', [ 'product', 'custom' ] ) ? $settings['image_type'] : 'product';
        $clean['image_width']          = intval( $settings['image_width'] ?? 96 );
        $clean['image_position']       = in_array( $settings['image_position'] ?? '', [ 'left', 'right' ] ) ? $settings['image_position'] : 'left';
        $clean['image_custom_url']     = esc_url_raw( $settings['image_custom_url'] ?? '' );
        $clean['position']             = sanitize_key( $settings['position'] ?? 'before_payment' );
        $clean['skin']                 = sanitize_key( $settings['skin'] ?? 'skin1' );
        $clean['bump_status']          = in_array( $settings['bump_status'] ?? '', [ 'publish', 'draft' ] ) ? $settings['bump_status'] : 'publish';
        $clean['trigger_type']         = sanitize_key( $settings['trigger_type'] ?? 'all' );
        $clean['trigger_products']     = array_map( 'intval', (array) ( $settings['trigger_products'] ?? [] ) );
        $clean['trigger_categories']   = array_map( 'intval', (array) ( $settings['trigger_categories'] ?? [] ) );
        $clean['minimum_order_amount'] = floatval( $settings['minimum_order_amount'] ?? 0 );
        $clean['behaviour']            = in_array( $settings['behaviour'] ?? '', [ 'add', 'replace' ] ) ? $settings['behaviour'] : 'add';

        // Products — each with its own description
        $clean['products'] = [];
        foreach ( (array) ( $settings['products'] ?? [] ) as $p ) {
            if ( empty( $p['id'] ) ) continue;
            $clean['products'][] = [
                'id'            => intval( $p['id'] ),
                'discount'      => floatval( $p['discount'] ?? 0 ),
                'discount_type' => in_array( $p['discount_type'] ?? '', [ 'percentage', 'flat' ] ) ? $p['discount_type'] : 'percentage',
                'qty'           => max( 1, intval( $p['qty'] ?? 1 ) ),
                'description'   => wp_kses_post( stripslashes( $p['description'] ?? '' ) ),
            ];
        }

        $post_data = [
            'post_title'  => $title,
            'post_type'   => 'order_bump',
            'post_status' => $clean['bump_status'],
        ];
        if ( $bump_id ) {
            $post_data['ID'] = $bump_id;
            wp_update_post( $post_data );
        } else {
            $bump_id = wp_insert_post( $post_data );
        }

        update_post_meta( $bump_id, '_obp_settings', $clean );

        // Stay on same edit page after save
        $active_tab = sanitize_key( $_POST['active_tab'] ?? 'design' );
        wp_redirect( admin_url( 'admin.php?page=obp-edit&edit=' . $bump_id . '&saved=1&tab=' . $active_tab ) );
        exit;
    }
}
