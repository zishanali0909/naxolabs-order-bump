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
}