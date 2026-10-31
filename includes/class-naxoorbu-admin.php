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
     * Register admin menu pages: a menu of its own, which extensions add their screens to.
     */
    public function add_menu() {
        add_menu_page(
            __( 'Order Bumps', 'naxolabs-order-bump' ),
            __( 'Naxo Order Bump', 'naxolabs-order-bump' ),
            'manage_woocommerce',
            'naxolabs-order-bump',
            [ $this, 'render_dashboard' ],
            'dashicons-cart',
            '55.6' // Right below WooCommerce and its Products menu.
        );
        add_submenu_page(
            'naxolabs-order-bump',
            __( 'Order Bumps', 'naxolabs-order-bump' ),
            __( 'All Bumps', 'naxolabs-order-bump' ),
            'manage_woocommerce',
            'naxolabs-order-bump',
            [ $this, 'render_dashboard' ]
        );
        $edit_hook = add_submenu_page(
            'naxolabs-order-bump',
            __( 'Edit Order Bump', 'naxolabs-order-bump' ),
            __( 'Add New', 'naxolabs-order-bump' ),
            'manage_woocommerce',
            'naxoorbu-edit',
            [ $this, 'render_edit_page' ]
        );
        if ( $edit_hook ) {
            add_action( 'load-' . $edit_hook, [ $this, 'set_edit_page_title' ] );
        }
        add_filter( 'submenu_file', [ $this, 'highlight_submenu' ] );
    }

    /**
     * Keep "All Bumps" highlighted while editing a bump ("Add New" is for a new one).
     *
     * @param string|null $submenu_file Submenu file.
     * @return string|null
     */
    public function highlight_submenu( $submenu_file ) {
        global $plugin_page;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing.
        return 'naxoorbu-edit' === $plugin_page && ! empty( $_GET['edit'] ) ? 'naxolabs-order-bump' : $submenu_file;
    }

    /**
     * Title "Add New Order Bump" when creating (the registered title is for editing).
     */
    public function set_edit_page_title() {
        global $title;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing.
        if ( empty( $_GET['edit'] ) ) {
            $title = __( 'Add New Order Bump', 'naxolabs-order-bump' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- core reads $title for the admin <title>.
        }
    }

    /**
     * Enqueue admin CSS/JS assets.
     */
    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'naxolabs-order-bump' ) && false === strpos( $hook, 'naxoorbu-edit' ) ) return;

        $css_path = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/css/admin.css';
        $js_path  = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/js/admin.js';
        wp_enqueue_style( 'naxoorbu-admin', NAXOORBU_URL . 'admin/css/admin.css', [], filemtime( $css_path ) );
        wp_enqueue_script( 'naxoorbu-admin', NAXOORBU_URL . 'admin/js/admin.js', [ 'jquery', 'jquery-ui-sortable' ], filemtime( $js_path ), true );
        // Only load TinyMCE and media on edit page (not dashboard)
        if ( strpos( $hook, 'naxoorbu-edit' ) !== false ) {
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

        wp_localize_script( 'naxoorbu-admin', 'naxoorbuAdmin', [
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'naxoorbu_admin_nonce' ),
            'currentSkin'    => $edit_meta['skin'] ?? 'skin1',
            'skin1BgColor'   => $edit_meta['skin1_bg_color'] ?? '#FFFDE7',
            'skin1TextColor' => $edit_meta['skin1_text_color'] ?? '#155724',
            'skin2BgColor'   => $edit_meta['skin2_bg_color'] ?? '#e8f7f9',
            'skin2TextColor' => $edit_meta['skin2_text_color'] ?? '#155724',
            'skin3BgColor'   => $edit_meta['skin3_bg_color'] ?? '#eef4fe',
            'skin3TextColor' => $edit_meta['skin3_text_color'] ?? '#155724',
            'firstProductImg'=> $first_prod_img,
            'customImageUrl' => $custom_img_url,
            'imageType'      => $image_type,
            'maxProducts'    => self::get_max_products( $edit_id ),
            'i18n'           => [
                'deleteConfirm'  => __( 'Delete this Order Bump?', 'naxolabs-order-bump' ),
                'sampleName'     => __( 'Product name', 'naxolabs-order-bump' ),
                'productAdded'   => __( 'Product added! Go to Design tab to set its description.', 'naxolabs-order-bump' ),
                'alreadyAdded'   => __( 'is already added.', 'naxolabs-order-bump' ),
                'noProducts'     => __( 'No products found.', 'naxolabs-order-bump' ),
                'active'         => __( 'Active', 'naxolabs-order-bump' ),
                'inactive'       => __( 'Inactive', 'naxolabs-order-bump' ),
                'noDescription'  => __( 'No description set.', 'naxolabs-order-bump' ),
                'errorDeleting'  => __( 'Error deleting.', 'naxolabs-order-bump' ),
                'error'          => __( 'Error.', 'naxolabs-order-bump' ),
                'inStock'        => __( 'in-stock', 'naxolabs-order-bump' ),
                'searchFailed'   => __( 'Search failed. Try again.', 'naxolabs-order-bump' ),
                'inRules'        => __( 'Already in Rules', 'naxolabs-order-bump' ),
                'inProducts'     => __( 'Already in Products', 'naxolabs-order-bump' ),
                'alsoInProducts' => __( 'Also in Products', 'naxolabs-order-bump' ),
                /* translators: %1$s: opening HTML tag, %2$s: closing HTML tag */
                'addProductsFirst' => __( 'First add products in the %1$sProducts tab%2$s.', 'naxolabs-order-bump' ),
                'descriptionHelp'=> __( 'Use short promotional text. HTML supported: <b>, <i>, <ul>, <li> etc.', 'naxolabs-order-bump' ),
                'overlapTitle'   => __( 'Overlap detected:', 'naxolabs-order-bump' ),
                'overlapText'    => __( 'Some products are added in both Products and Rules sections. This may cause the bump to show a product the customer already has in cart.', 'naxolabs-order-bump' ),
                'dismiss'        => __( 'Dismiss', 'naxolabs-order-bump' ),
                'delete'         => __( 'Delete', 'naxolabs-order-bump' ),
                'deleting'       => __( 'Deleting…', 'naxolabs-order-bump' ),
                /* translators: %s: formatted price with HTML */
                'regular'        => __( 'Regular: %s', 'naxolabs-order-bump' ),
                /* translators: %s: formatted price with HTML */
                'sale'           => __( 'Sale: %s', 'naxolabs-order-bump' ),
                'percentSale'    => __( '% Sale', 'naxolabs-order-bump' ),
                /* translators: %s: currency symbol */
                'flat'           => sprintf( __( 'Flat %s', 'naxolabs-order-bump' ), html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ),
                'remove'         => __( 'Remove', 'naxolabs-order-bump' ),
                'maxProductsMsg' => sprintf(
                    /* translators: %d: maximum products per bump */
                    __( 'Maximum %d products per bump allowed.', 'naxolabs-order-bump' ),
                    self::get_max_products( $edit_id )
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
     * Total revenue generated from order bump items.
     *
     * Net revenue from paid orders (refunds subtracted), the same figure the
     * analytics report uses — see Naxoorbu_Order_Query.
     *
     * @since 1.0.0
     * @return float Total revenue from bump items.
     */
    private function get_bump_revenue() {
        return Naxoorbu_Order_Query::get_totals()['revenue'];
    }

    /**
     * Maximum products a bump may hold.
     *
     * Never lower than what the bump already has: if the limit goes down (e.g. Pro is
     * deactivated), saving an existing bump keeps all its products; only adding more is blocked.
     *
     * @since 1.1.0
     * @param int $bump_id Bump ID (0 for a new bump).
     * @return int
     */
    public static function get_max_products( $bump_id ) {
        $max = intval( apply_filters( 'naxoorbu_max_products_per_bump', NAXOORBU_MAX_PRODUCTS_PER_BUMP, $bump_id ) );

        $meta     = $bump_id ? get_post_meta( $bump_id, '_naxoorbu_settings', true ) : [];
        $existing = is_array( $meta ) ? count( (array) ( $meta['products'] ?? [] ) ) : 0;

        return max( $max, $existing );
    }

    /**
     * Stop with a message when no more bumps can be created.
     *
     * Existing bumps beyond the limit (e.g. created with Pro) keep working and can be edited.
     *
     * @since 1.1.0
     */
    private function die_if_bump_limit_reached() {
        if ( self::can_create_bump() ) {
            return;
        }

        $max_bumps = apply_filters( 'naxoorbu_max_bumps', NAXOORBU_MAX_BUMPS );
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

    /**
     * Render edit/create bump page — delegates to template.
     */
    public function render_edit_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing
        $edit_id = isset( $_GET['edit'] ) ? intval( $_GET['edit'] ) : 0;

        // Check bump limit for NEW bumps (editing existing is always allowed)
        if ( ! $edit_id ) {
            $this->die_if_bump_limit_reached();
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
            'headline'             => '<mark>Yes!</mark> Add {{product_name}} to my order',
            'highlight_color'      => '#E15334',
            'badge_text'           => 'Special Offer',
            'show_badge'           => 0,
            'show_original_price'  => 1,
            'skin1_bg_color'       => '#FFFDE7',
            'skin1_text_color'     => '#155724',
            'skin2_bg_color'       => '#e8f7f9',
            'skin2_text_color'     => '#155724',
            'skin3_bg_color'       => '#eef4fe',
            'skin3_text_color'     => '#155724',
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
     * Clean a bump headline: plain text on one line, where <mark> highlights words
     * (shown in the bump's highlight colour); every other tag is removed. At most 200 characters.
     *
     * @since 1.2.0
     *
     * @param mixed $text Raw headline.
     * @return string
     */
    public static function sanitize_headline( $text ) {
        $allowed = [ 'mark' => [] ];
        $text    = wp_kses( wp_check_invalid_utf8( (string) $text ), $allowed );
        $text    = mb_substr( trim( preg_replace( '/\s+/u', ' ', $text ) ), 0, 200 );

        // Cutting to length may split a tag ("<ma") or a <mark> pair: drop the broken tag, close the pair.
        $text = preg_replace( '/<[^>]*$/', '', $text );
        return force_balance_tags( wp_kses( $text, $allowed ) );
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

        // Only order bumps can be updated here; any other post ID is rejected.
        if ( $bump_id && 'naxoorbu_order_bump' !== get_post_type( $bump_id ) ) {
            wp_die( esc_html__( 'Invalid order bump.', 'naxolabs-order-bump' ) );
        }

        // The bump limit also applies on save, not only on the "Add New" page.
        if ( ! $bump_id ) {
            $this->die_if_bump_limit_reached();
        } elseif ( ! self::can_edit_bump( $bump_id ) ) {
            wp_die(
                wp_kses_post( self::bump_locked_message( $bump_id ) ),
                esc_html__( 'Order Bump Locked', 'naxolabs-order-bump' ),
                [ 'back_link' => true ]
            );
        }

        $title    = sanitize_text_field( wp_unslash( $_POST['bump_title'] ?? 'Order Bump' ) );
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- deeply sanitized by sanitize_settings()
        $settings = wp_unslash( $_POST['naxoorbu_settings'] ?? [] );

        // Validate title
        if ( empty( trim( $title ) ) ) {
            $title = __( 'Order Bump', 'naxolabs-order-bump' );
        }

        $clean = self::sanitize_settings( $settings, $bump_id );

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

        update_post_meta( $bump_id, '_naxoorbu_settings', wp_slash( $clean ) ); // Meta functions unslash what they save.

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

    /**
     * Sanitize bump settings, as sent by the edit form: a toggle is on when its key is present.
     * Also used by extensions that save bumps from elsewhere (e.g. Pro's import).
     *
     * @since 1.2.0
     * @param mixed $settings Raw settings (unslashed).
     * @param int   $bump_id  Bump ID (0 for a new bump), for its product limit.
     * @return array Clean settings, every field set.
     */
    public static function sanitize_settings( $settings, $bump_id = 0 ) {
        $settings = is_array( $settings ) ? $settings : [];

        // Lists hold single values (products hold field maps); every other field is a single value.
        // A value of the wrong type (e.g. from a tampered form) is dropped, so it falls back to its default.
        foreach ( $settings as $key => $value ) {
            if ( 'products' === $key ) {
                $settings[ $key ] = array_map(
                    function ( $p ) { return is_array( $p ) ? array_filter( $p, 'is_scalar' ) : []; },
                    (array) $value
                );
            } elseif ( in_array( $key, [ 'trigger_products', 'trigger_categories' ], true ) ) {
                $settings[ $key ] = array_filter( (array) $value, 'is_scalar' );
            } elseif ( ! is_scalar( $value ) ) {
                unset( $settings[ $key ] );
            }
        }

        $clean = [];

        // Text fields with length limits
        $clean['headline']   = self::sanitize_headline( $settings['headline'] ?? '' );
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
        $clean['skin3_bg_color']   = sanitize_hex_color( $settings['skin3_bg_color'] ?? '#eef4fe' ) ?: '#eef4fe';
        $clean['skin3_text_color'] = sanitize_hex_color( $settings['skin3_text_color'] ?? '#155724' ) ?: '#155724';
        $clean['highlight_color']  = sanitize_hex_color( $settings['highlight_color'] ?? '#E15334' ) ?: '#E15334';

        // Image settings — clamped values
        $clean['image_type']       = in_array( $settings['image_type'] ?? '', [ 'product', 'custom' ], true ) ? $settings['image_type'] : 'product';
        $clean['image_width']      = max( 50, min( 200, intval( $settings['image_width'] ?? 96 ) ) );
        $clean['image_position']   = in_array( $settings['image_position'] ?? '', [ 'left', 'right' ], true ) ? $settings['image_position'] : 'right';
        $clean['image_custom_url'] = esc_url_raw( $settings['image_custom_url'] ?? '' );

        // Layout & skin — whitelist values
        $clean['position']    = in_array( $settings['position'] ?? '', [ 'before_payment', 'after_payment' ], true ) ? $settings['position'] : 'before_payment';
        $allowed_skins = apply_filters( 'naxoorbu_allowed_skins', array( 'skin1', 'skin2' ) );
		$clean['skin']        = in_array( $settings['skin'] ?? '', $allowed_skins, true ) ? $settings['skin'] : 'skin1';
        $clean['bump_status'] = in_array( $settings['bump_status'] ?? '', [ 'publish', 'draft' ], true ) ? $settings['bump_status'] : 'publish';

        // Trigger rules
        $clean['trigger_type']         = in_array( $settings['trigger_type'] ?? '', [ 'all', 'specific_product', 'category', 'minimum_order' ], true ) ? $settings['trigger_type'] : 'all';
        $clean['trigger_products']     = array_map( 'intval', (array) ( $settings['trigger_products'] ?? [] ) );
        $clean['trigger_categories']   = array_map( 'intval', (array) ( $settings['trigger_categories'] ?? [] ) );
        $clean['minimum_order_amount'] = max( 0, min( 999999, floatval( $settings['minimum_order_amount'] ?? 0 ) ) );
        $clean['behaviour']            = in_array( $settings['behaviour'] ?? '', [ 'add', 'replace' ], true ) ? $settings['behaviour'] : 'add';

        // Products — max 10, each with validated fields
        $clean['products'] = [];
        $max_products = self::get_max_products( $bump_id );
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
                'description'   => mb_substr( wp_kses_post( (string) ( $p['description'] ?? '' ) ), 0, 2000 ),
            ];
        }

        return $clean;
    }

    /**
     * Whether another bump may be created (the limit Free sets, raised by e.g. Pro).
     *
     * @since 1.2.0
     * @return bool
     */
    public static function can_create_bump() {
        return self::count_bumps() < apply_filters( 'naxoorbu_max_bumps', NAXOORBU_MAX_BUMPS );
    }

    /**
     * Whether an existing bump can be edited. Always, unless an extension says otherwise.
     *
     * @since 1.2.0
     * @param int $bump_id Bump ID.
     * @return bool
     */
    public static function can_edit_bump( $bump_id ) {
        /**
         * Filter whether an existing bump can be edited. A bump that can't be edited keeps working.
         *
         * @since 1.2.0
         * @param bool $can_edit Whether it can be edited.
         * @param int  $bump_id  Bump ID.
         */
        return (bool) apply_filters( 'naxoorbu_can_edit_bump', true, (int) $bump_id );
    }

    /**
     * Why a bump can't be edited (HTML, as shown on the edit screen).
     *
     * @since 1.2.0
     * @param int $bump_id Bump ID.
     * @return string
     */
    public static function bump_locked_message( $bump_id ) {
        /**
         * Filter the message shown on a bump that can't be edited.
         *
         * @since 1.2.0
         * @param string $message HTML.
         * @param int    $bump_id Bump ID.
         */
        return (string) apply_filters( 'naxoorbu_bump_locked_message', __( 'This order bump can\'t be edited.', 'naxolabs-order-bump' ), (int) $bump_id );
    }

    /**
     * Bumps counted towards the limit (published and drafts).
     *
     * @return int
     */
    private static function count_bumps() {
        $current_count = wp_count_posts( 'naxoorbu_order_bump' );
        return intval( $current_count->publish ?? 0 ) + intval( $current_count->draft ?? 0 );
    }
}
