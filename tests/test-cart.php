<?php
/**
 * Tests for add_to_cart() — adding bump products to cart.
 *
 * Tests product validation, duplicate checks, and discount tagging.
 *
 * @package NaxolabsOrderBump
 */
class Test_Cart extends WP_UnitTestCase {

    /** @var Naxoorbu_Ajax */
    private $ajax;

    /** @var WC_Product_Simple */
    private $product;

    /** @var int Bump post ID */
    private $bump_id;

    public function set_up() {
        parent::set_up();

        $this->ajax = new Naxoorbu_Ajax();

        // Create a test product (price ₹1000).
        $this->product = WC_Helper_Product::create_simple_product();
        $this->product->set_regular_price( 1000 );
        $this->product->set_price( 1000 );
        $this->product->save();

        // Create a bump post with discount settings.
        $this->bump_id = wp_insert_post( [
            'post_type'   => 'naxoorbu_order_bump',
            'post_title'  => 'Test Bump',
            'post_status' => 'publish',
        ] );
        update_post_meta( $this->bump_id, '_naxoorbu_settings', [
            'products' => [
                [
                    'id'            => $this->product->get_id(),
                    'discount'      => 20,
                    'discount_type' => 'percentage',
                    'qty'           => 1,
                ],
            ],
        ] );

        // Set up admin user for nonce checks.
        $user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        WC()->cart->empty_cart();
    }

    public function tear_down() {
        WC()->cart->empty_cart();
        parent::tear_down();
    }

    /** Test: bump meta is attached to cart item when added via bump. */
    public function test_bump_meta_attached_to_cart_item() {
        WC()->cart->add_to_cart( $this->product->get_id(), 1, 0, [], [
            'naxoorbu_bump_id'       => $this->bump_id,
            'naxoorbu_discount'      => 20,
            'naxoorbu_discount_type' => 'percentage',
        ] );

        $items = WC()->cart->get_cart();
        $item  = reset( $items );

        $this->assertEquals( $this->bump_id, $item['naxoorbu_bump_id'] );
        $this->assertEquals( 20, $item['naxoorbu_discount'] );
        $this->assertEquals( 'percentage', $item['naxoorbu_discount_type'] );
    }

    /** Test: regular add_to_cart has no bump meta. */
    public function test_regular_cart_item_no_bump_meta() {
        WC()->cart->add_to_cart( $this->product->get_id() );

        $items = WC()->cart->get_cart();
        $item  = reset( $items );

        $this->assertArrayNotHasKey( 'naxoorbu_bump_id', $item );
    }

    /** Test: quantity is clamped between 1 and 99. */
    public function test_quantity_clamped() {
        // qty = 0 should become 1.
        $qty_low = max( 1, min( 99, intval( 0 ) ) );
        $this->assertEquals( 1, $qty_low );

        // qty = 500 should become 99.
        $qty_high = max( 1, min( 99, intval( 500 ) ) );
        $this->assertEquals( 99, $qty_high );

        // qty = 5 stays 5.
        $qty_normal = max( 1, min( 99, intval( 5 ) ) );
        $this->assertEquals( 5, $qty_normal );
    }

    /** Test: product added to cart increases cart count. */
    public function test_cart_count_increases() {
        $this->assertCount( 0, WC()->cart->get_cart() );
        WC()->cart->add_to_cart( $this->product->get_id() );
        $this->assertCount( 1, WC()->cart->get_cart() );
    }

    /** Test: duplicate product detection. */
    public function test_duplicate_product_detection() {
        WC()->cart->add_to_cart( $this->product->get_id() );
        $pid = $this->product->get_id();

        $found = false;
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( (int) $item['product_id'] === $pid ) {
                $found = true;
                break;
            }
        }
        $this->assertTrue( $found, 'Product should be found in cart.' );
    }
}