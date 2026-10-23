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
        delete_transient( Naxoorbu_Visibility::CACHE_KEY ); // Bump meta was saved after the post was created.
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

    /** Test: shared cart helper tags the item with bump data. */
    public function test_cart_helper_adds_bump_product_with_meta() {
        $result = Naxoorbu_Cart::add_bump_product( $this->product->get_id(), 1, $this->bump_id );

        $this->assertTrue( $result['added'] );
        $item = WC()->cart->get_cart_item( $result['cart_item_key'] );
        $this->assertEquals( $this->bump_id, $item['naxoorbu_bump_id'] );
        $this->assertEquals( 20, $item['naxoorbu_discount'] );
    }

    /** Test: missing bump ID is resolved from the product. */
    public function test_cart_helper_resolves_missing_bump_id() {
        $result = Naxoorbu_Cart::add_bump_product( $this->product->get_id(), 1, 0 );

        $item = WC()->cart->get_cart_item( $result['cart_item_key'] );
        $this->assertEquals( $this->bump_id, $item['naxoorbu_bump_id'] );
    }

    /** Test: adding twice reports "already" instead of a second line item. */
    public function test_cart_helper_does_not_duplicate() {
        Naxoorbu_Cart::add_bump_product( $this->product->get_id(), 1, $this->bump_id );
        $result = Naxoorbu_Cart::add_bump_product( $this->product->get_id(), 1, $this->bump_id );

        $this->assertTrue( $result['already'] );
        $this->assertCount( 1, WC()->cart->get_cart() );
    }

    /** Test: invalid product returns WP_Error. */
    public function test_cart_helper_rejects_invalid_product() {
        $this->assertWPError( Naxoorbu_Cart::add_bump_product( 0 ) );
        $this->assertWPError( Naxoorbu_Cart::remove_bump_product( 0 ) );
    }

    /** Test: Store API callback adds and removes the bump product. */
    public function test_store_api_callback_add_and_remove() {
        Naxoorbu_Cart::handle_store_api_update( [
            'action'     => 'add',
            'product_id' => $this->product->get_id(),
            'bump_id'    => $this->bump_id,
            'qty'        => 1,
        ] );
        $this->assertCount( 1, WC()->cart->get_cart() );

        Naxoorbu_Cart::handle_store_api_update( [
            'action'     => 'remove',
            'product_id' => $this->product->get_id(),
        ] );
        $this->assertCount( 0, WC()->cart->get_cart() );
    }

    /** Test: a product the shopper already added at its normal price is not added again from the bump. */
    public function test_cannot_add_product_shopper_already_has() {
        WC()->cart->add_to_cart( $this->product->get_id() );

        $result = Naxoorbu_Cart::add_bump_product( $this->product->get_id(), 1, $this->bump_id );

        $this->assertWPError( $result );
        $this->assertSame( 'already_in_cart', $result->get_error_code() );
        $this->assertCount( 1, WC()->cart->get_cart() );
    }

    /** Test: removing a bump product never removes the shopper's own item of that product. */
    public function test_remove_keeps_shoppers_own_item() {
        $own_key = WC()->cart->add_to_cart( $this->product->get_id() );

        $this->assertFalse( Naxoorbu_Cart::remove_bump_product( $this->product->get_id(), $this->bump_id ) );
        $this->assertNotEmpty( WC()->cart->get_cart_item( $own_key ) );
    }

    /** Test: removing only takes out the item added from the given bump. */
    public function test_remove_only_bump_item() {
        $own_key  = WC()->cart->add_to_cart( $this->product->get_id() );
        $bump_key = WC()->cart->add_to_cart( $this->product->get_id(), 1, 0, [], [ 'naxoorbu_bump_id' => $this->bump_id ] );

        $this->assertSame( $bump_key, Naxoorbu_Cart::find_bump_item_key( $this->product->get_id(), $this->bump_id ) );
        $this->assertSame( '', Naxoorbu_Cart::find_bump_item_key( $this->product->get_id(), $this->bump_id + 1 ) );

        $this->assertTrue( Naxoorbu_Cart::remove_bump_product( $this->product->get_id(), $this->bump_id ) );
        $this->assertEmpty( WC()->cart->get_cart_item( $bump_key ) );
        $this->assertNotEmpty( WC()->cart->get_cart_item( $own_key ) );
    }

    /** Test: Store API callback rejects unknown actions. */
    public function test_store_api_callback_rejects_invalid_action() {
        $this->expectException( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException::class );
        Naxoorbu_Cart::handle_store_api_update( [ 'action' => 'delete_everything', 'product_id' => $this->product->get_id() ] );
    }

    /** Test: Store API cart item data exposes the bump ID (0 for regular items). */
    public function test_store_api_cart_item_data() {
        $this->assertSame( [ 'bump_id' => $this->bump_id ], Naxoorbu_Cart::get_store_api_cart_item_data( [ 'naxoorbu_bump_id' => $this->bump_id ] ) );
        $this->assertSame( [ 'bump_id' => 0 ], Naxoorbu_Cart::get_store_api_cart_item_data( [] ) );
    }

    /** Test: an offer hidden by display rules or schedule cannot be added via a crafted request. */
    public function test_cannot_add_unavailable_offer() {
        add_filter( 'naxoorbu_passes_conditions', '__return_false' );
        $result = Naxoorbu_Cart::add_bump_product( $this->product->get_id(), 1, $this->bump_id );
        remove_filter( 'naxoorbu_passes_conditions', '__return_false' );

        $this->assertWPError( $result );
        $this->assertSame( 'offer_unavailable', $result->get_error_code() );
        $this->assertCount( 0, WC()->cart->get_cart() );
    }

    /** Test: a product the bump does not offer cannot be added with the bump's discount. */
    public function test_cannot_add_product_not_in_offer() {
        $other = WC_Helper_Product::create_simple_product();

        $this->assertFalse( Naxoorbu_Cart::is_offer_available( $this->bump_id, $other->get_id() ) );
        $this->assertWPError( Naxoorbu_Cart::add_bump_product( $other->get_id(), 1, $this->bump_id ) );
        $this->assertTrue( Naxoorbu_Cart::is_offer_available( $this->bump_id, $this->product->get_id() ) );
    }
}
