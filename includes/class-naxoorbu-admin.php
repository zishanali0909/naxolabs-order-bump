<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Naxoorbu_Admin — Admin controller for Naxolabs Order Bump.
 *
 * Handles menu registration, asset enqueueing, rendering (via templates),
 * and form save processing. HTML templates are in admin/views/.
 *
 * @package NaxolabsOrderBump
 */
class Naxoorbu_Admin {

    public function __construct() {
        add_action( 'admin_menu',               [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts',    [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_naxoorbu_save_bump', [ $this, 'handle_save' ] );
    }

    /**
     * Register admin menu pages.
     */
    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Order Bumps', 'naxolabs-order-bump' ),
            __( 'Order Bumps', 'naxolabs-order-bump' ),
            'manage_woocommerce',
            'naxolabs-order-bump',
            [ $this, 'render_dashboard' ]
        );
        // Hidden page (no menu item) for editing/creating bumps
        add_submenu_page(
            null,
            __( 'Edit Order Bump', 'naxolabs-order-bump' ),
            '',
            'manage_woocommerce',
            'naxoorbu-edit',
            [ $this, 'render_edit_page' ]
        );
    }

    /**
     * Enqueue admin CSS/JS assets.
     */
    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'naxoorbu' ) && false === strpos( $hook, 'naxoorbu-' ) ) return;

        $css_path = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/css/admin.css';
        $js_path  = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/js/admin.js';
        wp_enqueue_style( 'naxoorbu-admin', NAXOORBU_URL . 'admin/css/admin.css', [], filemtime( $css_path ) );
        wp_enqueue_script( 'naxoorbu-admin', NAXOORBU_URL . 'admin/js/admin.js', [ 'jquery', 'jquery-ui-sortable' ], filemtime( $js_path ), true );
        // Only load TinyMCE and media on edit page (not dashboard)
        if ( strpos( $hook, 'naxoorbu-' ) !== false ) {
            wp_enqueue_editor();
            wp_enqueue_media();
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing
        $edit_id   = isset( $_GET['edit'] ) ? intval( $_GET['edit'] ) : 0;
        $edit_meta = $edit_id ? ( get_post_meta( $edit_id, '_naxoorbu_settings', true ) ?: [] ) : [];

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

        // Pass product descriptions data to JS (instead of inline <script>)
        if ( $edit_id && ! empty( $edit_meta['products'] ) ) {
            $desc_json = [];
            foreach ( $edit_meta['products'] as $p ) {
                $pid = intval( $p['id'] ?? 0 );
                $desc_json[ $pid ] = $p['description'] ?? '';
            }
            wp_add_inline_script( 'naxoorbu-admin', 'var naxoorbuProdDescs = ' . wp_json_encode( $desc_json ) . ';', 'before' );
        }

        wp_localize_script( 'naxoorbu-admin', 'naxoorbuAdmin', [
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'naxoorbu_admin_nonce' ),
            'currentSkin'    => $edit_meta['skin'] ?? 'skin1',
            'skin1BgColor'   => $edit_meta['skin1_bg_color'] ?? '#FFFDE7',
            'skin1TextColor' => $edit_meta['skin1_text_color'] ?? '#155724',
            'skin2BgColor'   => $edit_meta['skin2_bg_color'] ?? '#e8f7f9',
            'skin2TextColor' => $edit_meta['skin2_text_color'] ?? '#155724',
            'firstProductImg'=> $first_prod_img,
            'customImageUrl' => $custom_img_url,
            'imageType'      => $image_type,
            'maxProducts'    => apply_filters( 'naxoorbu_max_products_per_bump', NAXOORBU_MAX_PRODUCTS_PER_BUMP ),
            'i18n'           => [
                'deleteConfirm'  => __( 'Delete this Order Bump?', 'naxolabs-order-bump' ),
                'productAdded'   => __( 'Product added! Go to Design tab to set its description.', 'naxolabs-order-bump' ),
                'alreadyAdded'   => __( 'is already added.', 'naxolabs-order-bump' ),
                'noProducts'     => __( 'No products found.', 'naxolabs-order-bump' ),
                'active'         => __( 'Active', 'naxolabs-order-bump' ),
                'inactive'       => __( 'Inactive', 'naxolabs-order-bump' ),
                'noDescription'  => __( 'No description set.', 'naxolabs-order-bump' ),
                'errorDeleting'  => __( 'Error deleting.', 'naxolabs-order-bump' ),
                'error'          => __( 'Error.', 'naxolabs-order-bump' ),
                'inStock'        => __( 'in-stock', 'naxolabs-order-bump' ),
                'maxProductsMsg' => sprintf(
                    /* translators: %d: maximum products per bump */
                    __( 'Maximum %d products per bump allowed.', 'naxolabs-order-bump' ),
                    apply_filters( 'naxoorbu_max_products_per_bump', NAXOORBU_MAX_PRODUCTS_PER_BUMP )
                ),
            ],
        ] );
    }

    /**
     * Render dashboard page — delegates to template.
     */
    public function render_dashboard() {
        $bumps  = get_posts( [
            'post_type'      => 'naxoorbu_order_bump',
            'posts_per_page' => -1,
            'post_status'    => [ 'publish', 'draft' ],
        ] );
        $active = count( array_filter( $bumps, fn( $b ) => $b->post_status === 'publish' ) );
        $bump_revenue = $this->get_bump_revenue();

        include NAXOORBU_PATH . 'admin/views/dashboard.php';
    }

    /**
     * Calculate total revenue generated from order bump items.
     *
     * Queries completed/processing orders that contain bump line items
     * and sums up their totals.
     *
     * @since 1.0.0
     * @return float Total revenue from bump items.
     */
    private function get_bump_revenue() {
        global $wpdb;

        // HPOS-compatible: use wc_orders table if available, fallback to posts
        $orders_table = $wpdb->prefix . 'wc_orders';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $use_hpos = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) === $orders_table;

        if ( $use_hpos ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE( SUM( oim_total.meta_value ), 0 )
                     FROM {$wpdb->prefix}woocommerce_order_itemmeta AS oim_bump
                     INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS oim_total
                         ON oim_bump.order_item_id = oim_total.order_item_id
                         AND oim_total.meta_key = %s
                     INNER JOIN {$wpdb->prefix}woocommerce_order_items AS oi
                         ON oi.order_item_id = oim_bump.order_item_id
                     INNER JOIN {$wpdb->prefix}wc_orders AS o
                         ON o.id = oi.order_id
                         AND o.status IN ( 'wc-completed', 'wc-processing' )
                     WHERE oim_bump.meta_key = %s",
                    '_line_total',
                    '_naxoorbu_bump_id'
                )
            );
        } else {
            // Legacy: orders in wp_posts
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE( SUM( oim_total.meta_value ), 0 )
                     FROM {$wpdb->prefix}woocommerce_order_itemmeta AS oim_bump
                     INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS oim_total
                         ON oim_bump.order_item_id = oim_total.order_item_id
                         AND oim_total.meta_key = %s
                     INNER JOIN {$wpdb->prefix}woocommerce_order_items AS oi
                         ON oi.order_item_id = oim_bump.order_item_id
                     INNER JOIN {$wpdb->posts} AS p
                         ON p.ID = oi.order_id
                         AND p.post_status IN ( 'wc-completed', 'wc-processing' )
                     WHERE oim_bump.meta_key = %s",
                    '_line_total',
                    '_naxoorbu_bump_id'
                )
            );
        }

        return floatval( $result );
    }

    /**
     * Render edit/create bump page — delegates to template.
     */
    public function render_edit_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing
        $edit_id = isset( $_GET['edit'] ) ? intval( $_GET['edit'] ) : 0;

        // Check bump limit for NEW bumps (editing existing is always allowed)
        if ( ! $edit_id ) {
            $max_bumps = apply_filters( 'naxoorbu_max_bumps', NAXOORBU_MAX_BUMPS );
            $current_count = wp_count_posts( 'naxoorbu_order_bump' );
            $total = intval( $current_count->publish ?? 0 ) + intval( $current_count->draft ?? 0 );
            if ( $total >= $max_bumps ) {
                wp_die(
                    sprintf(
                        /* translators: %d: maximum bumps allowed */
                        esc_html__( 'Maximum %d order bumps allowed. Delete an existing bump to create a new one.', 'naxolabs-order-bump' ),
                        $max_bumps // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- integer, inside esc_html__
                    ),
                    esc_html__( 'Bump Limit Reached', 'naxolabs-order-bump' ),
                    [ 'back_link' => true ]
                );
            }
        }
        $bump    = $edit_id ? get_post( $edit_id ) : null;
        $raw     = $edit_id ? ( get_post_meta( $edit_id, '_naxoorbu_settings', true ) ?: [] ) : [];

        $defaults = [
            'products'             => [],
            'behaviour'            => 'add',
            'trigger_type'         => 'all',
            'trigger_products'     => [],
            'trigger_categories'   => [],
            'minimum_order_amount' => '',
            'headline'             => 'Yes! Add {{product_name}} to my order',
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
            'image_position'       => 'right',
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab routing
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'design';

        include NAXOORBU_PATH . 'admin/views/edit-bump.php';
    }

    /**
     * Handle form save (admin-post.php).
     */
    public function handle_save() {
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['naxoorbu_nonce'] ?? '' ) ), 'naxoorbu_save_bump' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'naxolabs-order-bump' ) );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'naxolabs-order-bump' ) );
        }

        $bump_id  = intval( $_POST['bump_id'] ?? 0 );
        $title    = sanitize_text_field( wp_unslash( $_POST['bump_title'] ?? 'Order Bump' ) );
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- deeply sanitized below
        $settings = wp_unslash( $_POST['naxoorbu_settings'] ?? [] );

        // Validate title
        if ( empty( trim( $title ) ) ) {
            $title = __( 'Order Bump', 'naxolabs-order-bump' );
        }

        $clean = [];

        // Text fields with length limits
        $clean['headline']   = mb_substr( sanitize_text_field( $settings['headline'] ?? '' ), 0, 200 );
        $clean['badge_text'] = mb_substr( sanitize_text_field( $settings['badge_text'] ?? '' ), 0, 50 );

        // Boolean toggles
        $clean['show_badge']          = isset( $settings['show_badge'] ) ? 1 : 0;
        $clean['show_original_price'] = isset( $settings['show_original_price'] ) ? 1 : 0;
        $clean['show_product_image']  = isset( $settings['show_product_image'] ) ? 1 : 0;

        // Colors — with fallback defaults
        $clean['skin1_bg_color']   = sanitize_hex_color( $settings['skin1_bg_color'] ?? '#FFFDE7' ) ?: '#FFFDE7';
        $clean['skin1_text_color'] = sanitize_hex_color( $settings['skin1_text_color'] ?? '#155724' ) ?: '#155724';
        $clean['skin2_bg_color']   = sanitize_hex_color( $settings['skin2_bg_color'] ?? '#e8f7f9' ) ?: '#e8f7f9';
        $clean['skin2_text_color'] = sanitize_hex_color( $settings['skin2_text_color'] ?? '#155724' ) ?: '#155724';

        // Image settings — clamped values
        $clean['image_type']       = in_array( $settings['image_type'] ?? '', [ 'product', 'custom' ], true ) ? $settings['image_type'] : 'product';
        $clean['image_width']      = max( 50, min( 200, intval( $settings['image_width'] ?? 96 ) ) );
        $clean['image_position']   = in_array( $settings['image_position'] ?? '', [ 'left', 'right' ], true ) ? $settings['image_position'] : 'right';
        $clean['image_custom_url'] = esc_url_raw( $settings['image_custom_url'] ?? '' );

        // Layout & skin — whitelist values
        $clean['position']    = in_array( $settings['position'] ?? '', [ 'before_payment', 'after_payment' ], true ) ? $settings['position'] : 'before_payment';
        $clean['skin']        = in_array( $settings['skin'] ?? '', [ 'skin1', 'skin2' ], true ) ? $settings['skin'] : 'skin1';
        $clean['bump_status'] = in_array( $settings['bump_status'] ?? '', [ 'publish', 'draft' ], true ) ? $settings['bump_status'] : 'publish';

        // Trigger rules
        $clean['trigger_type']         = in_array( $settings['trigger_type'] ?? '', [ 'all', 'specific_product', 'category', 'minimum_order' ], true ) ? $settings['trigger_type'] : 'all';
        $clean['trigger_products']     = array_map( 'intval', (array) ( $settings['trigger_products'] ?? [] ) );
        $clean['trigger_categories']   = array_map( 'intval', (array) ( $settings['trigger_categories'] ?? [] ) );
        $clean['minimum_order_amount'] = max( 0, min( 999999, floatval( $settings['minimum_order_amount'] ?? 0 ) ) );
        $clean['behaviour']            = in_array( $settings['behaviour'] ?? '', [ 'add', 'replace' ], true ) ? $settings['behaviour'] : 'add';

        // Products — max 10, each with validated fields
        $clean['products'] = [];
        $max_products = apply_filters( 'naxoorbu_max_products_per_bump', NAXOORBU_MAX_PRODUCTS_PER_BUMP );
        $raw_products = array_slice( (array) ( $settings['products'] ?? [] ), 0, $max_products );
        foreach ( $raw_products as $p ) {
            if ( empty( $p['id'] ) ) continue;
            $dtype    = in_array( $p['discount_type'] ?? '', [ 'percentage', 'flat' ], true ) ? $p['discount_type'] : 'percentage';
            $discount = max( 0, floatval( $p['discount'] ?? 0 ) );

            // Cap discount: percentage max 100%, flat max 999999
            if ( $dtype === 'percentage' ) {
                $discount = min( 100, $discount );
            } else {
                $discount = min( 999999, $discount );
            }

            $clean['products'][] = [
                'id'            => intval( $p['id'] ),
                'discount'      => $discount,
                'discount_type' => $dtype,
                'qty'           => max( 1, min( 99, intval( $p['qty'] ?? 1 ) ) ),
                'description'   => mb_substr( wp_kses_post( stripslashes( $p['description'] ?? '' ) ), 0, 2000 ),
            ];
        }

        // Save post
        $post_data = [
            'post_title'  => $title,
            'post_type'   => 'naxoorbu_order_bump',
            'post_status' => $clean['bump_status'],
        ];
        if ( $bump_id ) {
            $post_data['ID'] = $bump_id;
            wp_update_post( $post_data );
        } else {
            $bump_id = wp_insert_post( $post_data );
        }

        update_post_meta( $bump_id, '_naxoorbu_settings', $clean );

        /**
         * Fires after an order bump is saved.
         *
         * @param int   $bump_id The bump post ID.
         * @param array $clean   The sanitized bump settings.
         */
        do_action( 'naxoorbu_after_bump_saved', $bump_id, $clean );

        // Redirect with status
        $active_tab = sanitize_key( $_POST['active_tab'] ?? 'design' );
        $status     = empty( $clean['products'] ) ? 'saved_no_products' : 'saved';
        wp_safe_redirect( admin_url( 'admin.php?page=naxoorbu-edit&edit=' . $bump_id . '&naxoorbu_status=' . $status . '&tab=' . $active_tab ) );
        exit;
    }
}
