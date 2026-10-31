<?php
/**
 * Tests for handle_save() validation.
 * Tests sanitization, limits, whitelisting, edge cases.
 *
 * @package NaxolabsOrderBump
 */
class Test_Save extends WP_UnitTestCase {

    /** @var Naxoorbu_Admin */
    private $admin;

    public function set_up() {
        parent::set_up();
        $this->admin = new Naxoorbu_Admin();

        // Admin user with manage_woocommerce.
        $user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );
        $user = wp_get_current_user();
        $user->add_cap( 'manage_woocommerce' );

        // Clean up posts from previous tests.
        foreach ( get_posts( [ 'post_type' => 'naxoorbu_order_bump', 'numberposts' => -1, 'post_status' => 'any' ] ) as $p ) {
            wp_delete_post( $p->ID, true );
        }
    }

    public function tear_down() {
        // Remove redirect filters.
        remove_all_filters( 'wp_redirect' );
        // Clean $_POST.
        $_POST = [];
        parent::tear_down();
    }

    /**
     * Helper: call handle_save() and return saved meta.
     */
    private function simulate_save( $settings, $title = 'Test Bump', $bump_id = 0 ) {
        $_POST = [
            'naxoorbu_nonce'    => wp_create_nonce( 'naxoorbu_save_bump' ),
            'bump_id'           => $bump_id,
            'bump_title'        => $title,
            'naxoorbu_settings' => $settings,
            'active_tab'        => 'design',
        ];

        // Catch wp_redirect (called by wp_safe_redirect) to prevent exit.
        $redirect_url = '';
        add_filter( 'wp_redirect', function( $url ) use ( &$redirect_url ) {
            $redirect_url = $url;
            // Return false to prevent header() call, then throw to prevent exit.
            throw new Exception( 'redirect_caught' );
        }, 1 );

        try {
            $this->admin->handle_save();
        } catch ( Exception $e ) {
            // Expected — we caught the redirect.
        }

        remove_all_filters( 'wp_redirect' );

        // Parse bump_id from redirect URL if available.
        $saved_id = 0;
        if ( $redirect_url && preg_match( '/edit=(\d+)/', $redirect_url, $m ) ) {
            $saved_id = intval( $m[1] );
        }

        // Try to find saved bump by ID from redirect, or latest.
        if ( $saved_id ) {
            $meta = get_post_meta( $saved_id, '_naxoorbu_settings', true );
            if ( $meta ) return $meta;
        }

        // Fallback: find latest bump.
        $bumps = get_posts( [
            'post_type'   => 'naxoorbu_order_bump',
            'post_status' => [ 'publish', 'draft' ],
            'orderby'     => 'ID',
            'order'       => 'DESC',
            'numberposts' => 1,
        ] );

        if ( empty( $bumps ) ) return null;
        return get_post_meta( $bumps[0]->ID, '_naxoorbu_settings', true );
    }

    /** Test: empty title defaults to "Order Bump". */
    public function test_empty_title_gets_default() {
        $this->simulate_save( [ 'bump_status' => 'draft' ], '   ' );

        $bumps = get_posts( [
            'post_type'   => 'naxoorbu_order_bump',
            'post_status' => 'any',
            'numberposts' => 1,
            'orderby'     => 'ID',
            'order'       => 'DESC',
        ] );
        $this->assertNotEmpty( $bumps, 'Bump should be created.' );
        $this->assertEquals( 'Order Bump', $bumps[0]->post_title );
    }

    /** Test: headline truncated to 200 chars. */
    public function test_headline_max_200_chars() {
        $meta = $this->simulate_save( [ 'headline' => str_repeat( 'A', 300 ), 'bump_status' => 'draft' ] );
        $this->assertNotNull( $meta, 'Meta should be saved.' );
        $this->assertEquals( 200, mb_strlen( $meta['headline'] ) );
    }

    /** Test: the headline keeps <mark> highlights; every other tag goes, and it stays on one line. */
    public function test_headline_keeps_mark_only() {
        $meta = $this->simulate_save( [ 'headline' => "<mark>Yes!</mark> Add <b>{{product_name}}</b>\n<script>alert(1)</script>now", 'bump_status' => 'draft' ] );
        $this->assertNotNull( $meta );
        $this->assertSame( '<mark>Yes!</mark> Add {{product_name}} alert(1)now', $meta['headline'] );
    }

    /** Test: cutting to 200 characters never leaves a broken tag or an open <mark>. */
    public function test_headline_cut_keeps_tags_whole() {
        $this->assertSame( str_repeat( 'A', 196 ), Naxoorbu_Admin::sanitize_headline( str_repeat( 'A', 196 ) . '<mark>Yes!</mark>' ), 'A tag cut in half ("<mar") is dropped.' );
        $this->assertSame( str_repeat( 'A', 190 ) . '<mark>Yes!</mark>', Naxoorbu_Admin::sanitize_headline( str_repeat( 'A', 190 ) . '<mark>Yes!</mark>' ) );
        $this->assertSame( str_repeat( 'A', 180 ) . '<mark>Yes! Yes! Yes!</mark>', Naxoorbu_Admin::sanitize_headline( str_repeat( 'A', 180 ) . '<mark>Yes! Yes! Yes! Yes!</mark>' ), 'A <mark> cut open is closed.' );
    }

    /** Test: highlight colour saved; invalid or missing falls back to the default red. */
    public function test_highlight_color() {
        $meta = $this->simulate_save( [ 'highlight_color' => '#0055ff', 'bump_status' => 'draft' ] );
        $this->assertSame( '#0055ff', $meta['highlight_color'] );

        $meta = $this->simulate_save( [ 'highlight_color' => 'red; background:url(x)', 'bump_status' => 'draft' ] );
        $this->assertSame( '#E15334', $meta['highlight_color'] );

        $this->assertSame( '#E15334', Naxoorbu_Frontend::highlight_color( [] ), 'Bumps saved before 1.2.0 get the default.' );
    }

    /** Test: badge_text truncated to 50 chars. */
    public function test_badge_text_max_50_chars() {
        $meta = $this->simulate_save( [ 'badge_text' => str_repeat( 'B', 100 ), 'bump_status' => 'draft' ] );
        $this->assertNotNull( $meta, 'Meta should be saved.' );
        $this->assertEquals( 50, mb_strlen( $meta['badge_text'] ) );
    }

    /** Test: show_badge = 1 when set. */
    public function test_boolean_toggle_on() {
        $meta = $this->simulate_save( [ 'show_badge' => '1', 'bump_status' => 'draft' ] );
        $this->assertNotNull( $meta );
        $this->assertEquals( 1, $meta['show_badge'] );
    }

    /** Test: show_badge = 0 when not set. */
    public function test_boolean_toggle_off() {
        $meta = $this->simulate_save( [ 'bump_status' => 'draft' ] );
        $this->assertNotNull( $meta );
        $this->assertEquals( 0, $meta['show_badge'] );
    }

    /** Test: invalid color falls back to default. */
    public function test_invalid_color_defaults() {
        $meta = $this->simulate_save( [ 'skin1_bg_color' => 'not-a-color', 'bump_status' => 'draft' ] );
        $this->assertNotNull( $meta );
        $this->assertEquals( '#FFFDE7', $meta['skin1_bg_color'] );
    }

    /** Test: valid hex color is saved. */
    public function test_valid_color_saved() {
        $meta = $this->simulate_save( [ 'skin1_bg_color' => '#FF5733', 'bump_status' => 'draft' ] );
        $this->assertNotNull( $meta );
        $this->assertEquals( '#FF5733', $meta['skin1_bg_color'] );
    }

    /** Test: image_width clamped to min 50. */
    public function test_image_width_min_50() {
        $meta = $this->simulate_save( [ 'image_width' => '10', 'bump_status' => 'draft' ] );
        $this->assertNotNull( $meta );
        $this->assertEquals( 50, $meta['image_width'] );
    }

    /** Test: image_width clamped to max 200. */
    public function test_image_width_max_200() {
        $meta = $this->simulate_save( [ 'image_width' => '500', 'bump_status' => 'draft' ] );
        $this->assertNotNull( $meta );
        $this->assertEquals( 200, $meta['image_width'] );
    }

    /** Test: invalid position defaults to before_payment. */
    public function test_invalid_position_defaults() {
        $meta = $this->simulate_save( [ 'position' => 'hacked', 'bump_status' => 'draft' ] );
        $this->assertNotNull( $meta );
        $this->assertEquals( 'before_payment', $meta['position'] );
    }

    /** Test: invalid skin defaults to skin1. */
    public function test_invalid_skin_defaults() {
        $meta = $this->simulate_save( [ 'skin' => 'skin99', 'bump_status' => 'draft' ] );
        $this->assertNotNull( $meta );
        $this->assertEquals( 'skin1', $meta['skin'] );
    }

    /** Test: invalid trigger_type defaults to all. */
    public function test_invalid_trigger_type_defaults() {
        $meta = $this->simulate_save( [ 'trigger_type' => 'hacked', 'bump_status' => 'draft' ] );
        $this->assertNotNull( $meta );
        $this->assertEquals( 'all', $meta['trigger_type'] );
    }

    /** Test: percentage discount capped at 100. */
    public function test_percentage_capped_at_100() {
        $meta = $this->simulate_save( [
            'bump_status' => 'draft',
            'products'    => [ [ 'id' => 1, 'discount' => 150, 'discount_type' => 'percentage', 'qty' => 1 ] ],
        ] );
        $this->assertNotNull( $meta );
        $this->assertEquals( 100, $meta['products'][0]['discount'] );
    }

    /** Test: negative discount becomes 0. */
    public function test_negative_discount_becomes_zero() {
        $meta = $this->simulate_save( [
            'bump_status' => 'draft',
            'products'    => [ [ 'id' => 1, 'discount' => -50, 'discount_type' => 'percentage', 'qty' => 1 ] ],
        ] );
        $this->assertNotNull( $meta );
        $this->assertEquals( 0, $meta['products'][0]['discount'] );
    }

    /** Test: product qty clamped between 1 and 99. */
    public function test_product_qty_clamped() {
        $meta = $this->simulate_save( [
            'bump_status' => 'draft',
            'products'    => [
                [ 'id' => 1, 'discount' => 0, 'discount_type' => 'percentage', 'qty' => 0 ],
                [ 'id' => 2, 'discount' => 0, 'discount_type' => 'percentage', 'qty' => 999 ],
            ],
        ] );
        $this->assertNotNull( $meta );
        $this->assertEquals( 1, $meta['products'][0]['qty'] );
        $this->assertEquals( 99, $meta['products'][1]['qty'] );
    }

    /** Test: a description keeps its backslashes and quotes (posted slashed, unslashed once). */
    public function test_product_description_keeps_backslashes() {
        $meta = $this->simulate_save( wp_slash( [
            'bump_status' => 'draft',
            'products'    => [
                [ 'id' => 1, 'discount' => 0, 'discount_type' => 'percentage', 'qty' => 1, 'description' => 'Save to C:\\Docs "now"' ],
            ],
        ] ) );
        $this->assertSame( 'Save to C:\\Docs "now"', $meta['products'][0]['description'] );
    }

    /** Test: a bump an extension locks is left as it is on save (and keeps working). */
    public function test_locked_bump_not_saved() {
        $bump_id = wp_insert_post( [ 'post_type' => 'naxoorbu_order_bump', 'post_title' => 'Locked', 'post_status' => 'publish' ] );
        add_filter( 'naxoorbu_can_edit_bump', '__return_false' );

        $this->simulate_save( [ 'bump_status' => 'draft' ], 'Changed', $bump_id );

        remove_filter( 'naxoorbu_can_edit_bump', '__return_false' );
        $this->assertSame( 'Locked', get_the_title( $bump_id ) );
        $this->assertSame( 'publish', get_post_status( $bump_id ) );
    }

    /** Test: product without ID is skipped. */
    public function test_product_without_id_skipped() {
        $meta = $this->simulate_save( [
            'bump_status' => 'draft',
            'products'    => [
                [ 'id' => '', 'discount' => 10, 'discount_type' => 'percentage', 'qty' => 1 ],
                [ 'id' => 5, 'discount' => 10, 'discount_type' => 'percentage', 'qty' => 1 ],
            ],
        ] );
        $this->assertNotNull( $meta );
        $this->assertCount( 1, $meta['products'] );
        $this->assertEquals( 5, $meta['products'][0]['id'] );
    }

    // ─── Limits ────────────────────────────────────────

    /**
     * Helper: products array with N entries.
     */
    private function products( $count ) {
        $out = [];
        for ( $i = 1; $i <= $count; $i++ ) {
            $out[] = [ 'id' => $i, 'discount' => 0, 'discount_type' => 'percentage', 'qty' => 1 ];
        }
        return $out;
    }

    /** Test: a new bump keeps only the allowed number of products. */
    public function test_new_bump_limited_to_max_products() {
        $meta = $this->simulate_save( [ 'bump_status' => 'draft', 'products' => $this->products( 4 ) ] );

        $this->assertCount( NAXOORBU_MAX_PRODUCTS_PER_BUMP, $meta['products'] );
    }

    /** Test: saving a bump that already has more products than the limit (e.g. made with Pro) keeps them all. */
    public function test_existing_products_kept_when_limit_is_lower() {
        $bump_id = wp_insert_post( [ 'post_type' => 'naxoorbu_order_bump', 'post_title' => 'Pro bump', 'post_status' => 'publish' ] );
        update_post_meta( $bump_id, '_naxoorbu_settings', [ 'products' => $this->products( 4 ) ] );

        $this->assertSame( 4, Naxoorbu_Admin::get_max_products( $bump_id ) );

        $meta = $this->simulate_save( [ 'bump_status' => 'publish', 'products' => $this->products( 5 ) ], 'Pro bump', $bump_id );
        $this->assertCount( 4, $meta['products'], 'Existing products are kept, but no more can be added.' );
    }

    /** Test: no new bump can be saved once the limit is reached, even without the "Add New" page. */
    public function test_new_bump_blocked_at_limit() {
        for ( $i = 0; $i < NAXOORBU_MAX_BUMPS; $i++ ) {
            wp_insert_post( [ 'post_type' => 'naxoorbu_order_bump', 'post_title' => 'Bump ' . $i, 'post_status' => 'publish' ] );
        }

        $this->simulate_save( [ 'bump_status' => 'publish' ], 'One too many' );

        $this->assertSame( NAXOORBU_MAX_BUMPS, (int) wp_count_posts( 'naxoorbu_order_bump' )->publish );
    }

    /** Test: an existing bump can still be edited when there are more bumps than the limit. */
    public function test_existing_bump_editable_over_limit() {
        for ( $i = 0; $i < NAXOORBU_MAX_BUMPS + 1; $i++ ) {
            $bump_id = wp_insert_post( [ 'post_type' => 'naxoorbu_order_bump', 'post_title' => 'Bump ' . $i, 'post_status' => 'publish' ] );
        }

        $this->simulate_save( [ 'bump_status' => 'publish' ], 'Renamed', $bump_id );

        $this->assertSame( 'Renamed', get_the_title( $bump_id ) );
    }

    /** Test: a non-bump post ID is rejected and the post is left untouched. */
    public function test_cannot_overwrite_other_post_types() {
        $page_id = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'About', 'post_status' => 'publish' ] );

        $this->simulate_save( [ 'bump_status' => 'publish' ], 'Hijacked', $page_id );

        $this->assertSame( 'page', get_post_type( $page_id ) );
        $this->assertSame( 'About', get_the_title( $page_id ) );
    }
}