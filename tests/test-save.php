<?php
/**
 * Tests for handle_save() — bump save validation.
 *
 * Tests sanitization, limits, whitelisting, and edge cases.
 *
 * @package NaxolabsOrderBump
 */
class Test_Save extends WP_UnitTestCase {

    /** @var Naxoorbu_Admin */
    private $admin;

    public function set_up() {
        parent::set_up();
        $this->admin = new Naxoorbu_Admin();

        // Set admin user.
        $user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );
    }

    /**
     * Helper: simulate saving a bump by calling handle_save().
     * Returns the saved meta from DB.
     */
    private function simulate_save( $settings, $title = 'Test Bump', $bump_id = 0 ) {
        // Create nonce.
        $_POST['naxoorbu_nonce'] = wp_create_nonce( 'naxoorbu_save_bump' );
        $_POST['bump_id']        = $bump_id;
        $_POST['bump_title']     = $title;
        $_POST['naxoorbu_settings'] = $settings;
        $_POST['active_tab']     = 'design';

        // Catch the redirect to prevent exit.
        add_filter( 'wp_redirect', function( $url ) {
            throw new Exception( 'redirect:' . $url );
        } );

        try {
            $this->admin->handle_save();
        } catch ( Exception $e ) {
            // Expected redirect exception.
        }

        // Find the saved bump.
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

    // ─── Title sanitization ─────────────────────────────

    /** Test: empty title defaults to "Order Bump". */
    public function test_empty_title_gets_default() {
        $this->simulate_save( [ 'bump_status' => 'draft' ], '' );

        $bumps = get_posts( [
            'post_type'   => 'naxoorbu_order_bump',
            'post_status' => 'draft',
            'numberposts' => 1,
        ] );
        $this->assertEquals( 'Order Bump', $bumps[0]->post_title );
    }

    // ─── Headline length limit ──────────────────────────

    /** Test: headline truncated to 200 chars. */
    public function test_headline_max_200_chars() {
        $long = str_repeat( 'A', 300 );
        $meta = $this->simulate_save( [ 'headline' => $long, 'bump_status' => 'draft' ] );
        $this->assertEquals( 200, mb_strlen( $meta['headline'] ) );
    }

    // ─── Badge text limit ───────────────────────────────

    /** Test: badge_text truncated to 50 chars. */
    public function test_badge_text_max_50_chars() {
        $long = str_repeat( 'B', 100 );
        $meta = $this->simulate_save( [ 'badge_text' => $long, 'bump_status' => 'draft' ] );
        $this->assertEquals( 50, mb_strlen( $meta['badge_text'] ) );
    }

    // ─── Boolean toggles ────────────────────────────────

    /** Test: show_badge = 1 when set, 0 when not set. */
    public function test_boolean_toggles() {
        $meta_on  = $this->simulate_save( [ 'show_badge' => '1', 'bump_status' => 'draft' ] );
        $this->assertEquals( 1, $meta_on['show_badge'] );

        $meta_off = $this->simulate_save( [ 'bump_status' => 'draft' ] );
        $this->assertEquals( 0, $meta_off['show_badge'] );
    }

    // ─── Color validation ───────────────────────────────

    /** Test: invalid color falls back to default. */
    public function test_invalid_color_defaults() {
        $meta = $this->simulate_save( [
            'skin1_bg_color' => 'not-a-color',
            'bump_status'    => 'draft',
        ] );
        $this->assertEquals( '#FFFDE7', $meta['skin1_bg_color'] );
    }

    /** Test: valid hex color is saved. */
    public function test_valid_color_saved() {
        $meta = $this->simulate_save( [
            'skin1_bg_color' => '#FF5733',
            'bump_status'    => 'draft',
        ] );
        $this->assertEquals( '#FF5733', $meta['skin1_bg_color'] );
    }

    // ─── Image width clamping ───────────────────────────

    /** Test: image_width clamped between 50 and 200. */
    public function test_image_width_clamped() {
        $meta_low = $this->simulate_save( [ 'image_width' => '10', 'bump_status' => 'draft' ] );
        $this->assertEquals( 50, $meta_low['image_width'] );

        $meta_high = $this->simulate_save( [ 'image_width' => '500', 'bump_status' => 'draft' ] );
        $this->assertEquals( 200, $meta_high['image_width'] );
    }

    // ─── Whitelisted values ─────────────────────────────

    /** Test: invalid position defaults to before_payment. */
    public function test_invalid_position_defaults() {
        $meta = $this->simulate_save( [ 'position' => 'hacked', 'bump_status' => 'draft' ] );
        $this->assertEquals( 'before_payment', $meta['position'] );
    }

    /** Test: invalid skin defaults to skin1. */
    public function test_invalid_skin_defaults() {
        $meta = $this->simulate_save( [ 'skin' => 'skin99', 'bump_status' => 'draft' ] );
        $this->assertEquals( 'skin1', $meta['skin'] );
    }

    /** Test: invalid trigger_type defaults to all. */
    public function test_invalid_trigger_type_defaults() {
        $meta = $this->simulate_save( [ 'trigger_type' => 'hacked', 'bump_status' => 'draft' ] );
        $this->assertEquals( 'all', $meta['trigger_type'] );
    }

    // ─── Product discount capping ───────────────────────

    /** Test: percentage discount capped at 100%. */
    public function test_percentage_capped_at_100() {
        $meta = $this->simulate_save( [
            'bump_status' => 'draft',
            'products'    => [
                [
                    'id'            => 1,
                    'discount'      => 150,
                    'discount_type' => 'percentage',
                    'qty'           => 1,
                ],
            ],
        ] );
        $this->assertEquals( 100, $meta['products'][0]['discount'] );
    }

    /** Test: negative discount becomes 0. */
    public function test_negative_discount_becomes_zero() {
        $meta = $this->simulate_save( [
            'bump_status' => 'draft',
            'products'    => [
                [
                    'id'            => 1,
                    'discount'      => -50,
                    'discount_type' => 'percentage',
                    'qty'           => 1,
                ],
            ],
        ] );
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
        $this->assertEquals( 1, $meta['products'][0]['qty'] );
        $this->assertEquals( 99, $meta['products'][1]['qty'] );
    }

    // ─── Products limit ─────────────────────────────────

    /** Test: max products per bump is enforced (NAXOORBU_MAX_PRODUCTS_PER_BUMP). */
    public function test_max_products_enforced() {
        $products = [];
        for ( $i = 1; $i <= 10; $i++ ) {
            $products[] = [ 'id' => $i, 'discount' => 0, 'discount_type' => 'percentage', 'qty' => 1 ];
        }
        $meta = $this->simulate_save( [ 'bump_status' => 'draft', 'products' => $products ] );
        $this->assertLessThanOrEqual( NAXOORBU_MAX_PRODUCTS_PER_BUMP, count( $meta['products'] ) );
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
        $this->assertCount( 1, $meta['products'] );
        $this->assertEquals( 5, $meta['products'][0]['id'] );
    }
}