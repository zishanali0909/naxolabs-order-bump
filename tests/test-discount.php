<?php
/**
 * Tests for apply_bump_discounts() — discount calculation.
 *
 * Verifies percentage and flat discounts are calculated correctly.
 *
 * @package NaxolabsOrderBump
 */
class Test_Discount extends WP_UnitTestCase {

    /** @var Naxoorbu_Discount */
    private $discount;

    /** @var WC_Product_Simple */
    private $product;

    public function set_up() {
        parent::set_up();
        $this->discount = new Naxoorbu_Discount();
        $this->product  = WC_Helper_Product::create_simple_product();
        $this->product->set_regular_price( 1000 );
        $this->product->set_price( 1000 );
        $this->product->save();
        WC()->cart->empty_cart();
    }

    public function tear_down() {
        WC()->cart->empty_cart();
        parent::tear_down();
    }

    /**
     * Helper: add product to cart with bump discount data.
     */
    private function add_with_discount( $discount, $type = 'percentage' ) {
        WC()->cart->add_to_cart( $this->product->get_id(), 1, 0, [], [
            'naxoorbu_bump_id'       => 999,
            'naxoorbu_discount'      => $discount,
            'naxoorbu_discount_type' => $type,
        ] );
    }

    // ─── Percentage discount ────────────────────────────

    /** Test: 20% discount on ₹1000 product = ₹800. */
    public function test_percentage_discount_20() {
        $this->add_with_discount( 20, 'percentage' );
        $this->discount->apply_bump_discounts( WC()->cart );

        $items = WC()->cart->get_cart();
        $item  = reset( $items );
        $this->assertEquals( 800, $item['data']->get_price() );
    }

    /** Test: 50% discount on ₹1000 product = ₹500. */
    public function test_percentage_discount_50() {
        $this->add_with_discount( 50, 'percentage' );
        $this->discount->apply_bump_discounts( WC()->cart );

        $items = WC()->cart->get_cart();
        $item  = reset( $items );
        $this->assertEquals( 500, $item['data']->get_price() );
    }

    /** Test: 100% discount = ₹0 (free). */
    public function test_percentage_discount_100() {
        $this->add_with_discount( 100, 'percentage' );
        $this->discount->apply_bump_discounts( WC()->cart );

        $items = WC()->cart->get_cart();
        $item  = reset( $items );
        $this->assertEquals( 0, $item['data']->get_price() );
    }

    /** Test: 0% discount = original price. */
    public function test_percentage_discount_zero() {
        $this->add_with_discount( 0, 'percentage' );
        $this->discount->apply_bump_discounts( WC()->cart );

        $items = WC()->cart->get_cart();
        $item  = reset( $items );
        $this->assertEquals( 1000, $item['data']->get_price() );
    }

    // ─── Flat discount ──────────────────────────────────

    /** Test: ₹300 flat discount on ₹1000 product = ₹700. */
    public function test_flat_discount_300() {
        $this->add_with_discount( 300, 'flat' );
        $this->discount->apply_bump_discounts( WC()->cart );

        $items = WC()->cart->get_cart();
        $item  = reset( $items );
        $this->assertEquals( 700, $item['data']->get_price() );
    }

    /** Test: flat discount > price = ₹0 (never negative). */
    public function test_flat_discount_exceeds_price() {
        $this->add_with_discount( 2000, 'flat' );
        $this->discount->apply_bump_discounts( WC()->cart );

        $items = WC()->cart->get_cart();
        $item  = reset( $items );
        $this->assertEquals( 0, $item['data']->get_price(),
            'Price should be 0, not negative, when discount exceeds price.' );
    }

    // ─── No discount (regular cart item) ────────────────

    /** Test: item without bump discount keeps original price. */
    public function test_no_discount_keeps_original_price() {
        WC()->cart->add_to_cart( $this->product->get_id() );
        $this->discount->apply_bump_discounts( WC()->cart );

        $items = WC()->cart->get_cart();
        $item  = reset( $items );
        $this->assertEquals( 1000, $item['data']->get_price() );
    }

    // ─── Edge cases ─────────────────────────────────────

    /** Test: empty cart does not error. */
    public function test_empty_cart_no_error() {
        $this->discount->apply_bump_discounts( WC()->cart );
        $this->assertCount( 0, WC()->cart->get_cart() );
    }

    /** Test: multiple items, only bump item gets discount. */
    public function test_only_bump_item_discounted() {
        // Regular item (no discount).
        $product_2 = WC_Helper_Product::create_simple_product();
        $product_2->set_price( 500 );
        $product_2->save();
        WC()->cart->add_to_cart( $product_2->get_id() );

        // Bump item (with discount).
        $this->add_with_discount( 20, 'percentage' );

        $this->discount->apply_bump_discounts( WC()->cart );

        foreach ( WC()->cart->get_cart() as $item ) {
            if ( ! empty( $item['naxoorbu_discount'] ) ) {
                $this->assertEquals( 800, $item['data']->get_price(), 'Bump item should be discounted.' );
            } else {
                $this->assertEquals( 500, $item['data']->get_price(), 'Regular item should keep original price.' );
            }
        }
    }
}