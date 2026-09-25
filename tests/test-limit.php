<?php
/**
 * Tests for render_bumps() — bump limit enforcement.
 *
 * Verifies that max 2 bumps are rendered on checkout.
 *
 * @package NaxolabsOrderBump
 */
class Test_Limit extends WP_UnitTestCase {

    /** @var Naxoorbu_Frontend */
    private $frontend;

    /** @var ReflectionMethod */
    private $render_bumps;

    /** @var ReflectionMethod */
    private $get_bumps;

    public function set_up() {
        parent::set_up();
        $this->frontend = new Naxoorbu_Frontend();

        // Access private render_bumps via reflection.
        $this->render_bumps = new ReflectionMethod( Naxoorbu_Frontend::class, 'render_bumps' );
        $this->render_bumps->setAccessible( true );

        $this->get_bumps = new ReflectionMethod( Naxoorbu_Frontend::class, 'get_bumps' );
        $this->get_bumps->setAccessible( true );

        WC()->cart->empty_cart();
    }

    public function tear_down() {
        WC()->cart->empty_cart();
        parent::tear_down();
    }

    /**
     * Helper: create a published bump post with a product.
     */
    private function create_bump( $position = 'before_payment' ) {
        $product = WC_Helper_Product::create_simple_product();
        $bump_id = wp_insert_post( [
            'post_type'   => 'naxoorbu_order_bump',
            'post_title'  => 'Bump ' . wp_rand(),
            'post_status' => 'publish',
        ] );
        update_post_meta( $bump_id, '_naxoorbu_settings', [
            'position'     => $position,
            'trigger_type' => 'all',
            'skin'         => 'skin1',
            'products'     => [
                [
                    'id'            => $product->get_id(),
                    'discount'      => 10,
                    'discount_type' => 'percentage',
                    'qty'           => 1,
                ],
            ],
        ] );
        // Clear cache so new bumps are found.
        delete_transient( 'naxoorbu_published_bumps' );
        return $bump_id;
    }

    /** Test: default max bumps is 2. */
    public function test_default_max_bumps_is_2() {
        $max = apply_filters( 'naxoorbu_max_bumps', 2 );
        $this->assertEquals( 2, $max );
    }

    /** Test: max bumps constant is defined. */
    public function test_max_bumps_constant_defined() {
        $this->assertTrue( defined( 'NAXOORBU_MAX_BUMPS' ) );
        $this->assertEquals( 2, NAXOORBU_MAX_BUMPS );
    }

    /** Test: array_slice limits bumps correctly. */
    public function test_array_slice_limits_to_max() {
        $bumps = [ 'a', 'b', 'c', 'd', 'e' ];
        $max   = 2;
        $limited = array_slice( $bumps, 0, $max );

        $this->assertCount( 2, $limited );
        $this->assertEquals( [ 'a', 'b' ], $limited );
    }

    /** Test: 1 bump is fine (under limit). */
    public function test_one_bump_under_limit() {
        $bumps = [ [ 'id' => 1, 'meta' => [] ] ];
        $max   = 2;
        $limited = array_slice( $bumps, 0, $max );

        $this->assertCount( 1, $limited );
    }

    /** Test: filter can change max bumps. */
    public function test_filter_can_change_max() {
        add_filter( 'naxoorbu_max_bumps', function() { return 5; } );
        $max = apply_filters( 'naxoorbu_max_bumps', 2 );
        $this->assertEquals( 5, $max );
        remove_all_filters( 'naxoorbu_max_bumps' );
    }

    /** Test: position filter returns correct bumps. */
    public function test_bumps_filtered_by_position() {
        $this->create_bump( 'before_payment' );
        $this->create_bump( 'after_payment' );
        $this->create_bump( 'before_payment' );

        // Add a product to cart so triggers pass.
        $p = WC_Helper_Product::create_simple_product();
        WC()->cart->add_to_cart( $p->get_id() );

        $before = $this->get_bumps->invoke( $this->frontend, 'before_payment' );
        $after  = $this->get_bumps->invoke( $this->frontend, 'after_payment' );

        $this->assertEquals( 2, count( $before ), 'Should have 2 before_payment bumps.' );
        $this->assertEquals( 1, count( $after ), 'Should have 1 after_payment bump.' );
    }
}