<?php
/**
 * Tests for apply_bump_discounts() — discount calculation.
 *
 * Key fix: Remove the WC hook before manually calling the method,
 * so the discount isn't applied multiple times by WC internally.
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

        // Create discount instance but REMOVE the hook to prevent
        // double-application when WC->cart->calculate_totals() runs.
        $this->discount = new Naxoorbu_Discount();
        remove_all_actions( 'woocommerce_before_calculate_totals' );

        // Fresh product each test (₹1000).
        $this->product = WC_Helper_Product::create_simple_product( true, [
            'regular_price' => '1000',
            'price'         => '1000',
        ] );

        WC()->cart->empty_cart();
    }

    public function tear_down() {
        WC()->cart->empty_cart();
        parent::tear_down();
    }

    /**
     * Helper: add product to cart with bump discount data,
     * then call apply_bump_discounts() ONCE manually.
     */
    private function add_and_apply( $discount_amount, $type = 'percentage' ) {
        WC()->cart->add_to_cart( $this->product->get_id(), 1, 0, [], [
            'naxoorbu_bump_id'       => 999,
            'naxoorbu_discount'      => $discount_amount,
            'naxoorbu_discount_type' => $type,
        ] );

        // Call once manually (hook is removed so no double-call).
        $this->discount->apply_bump_discounts( WC()->cart );

        // Return the first cart item.
        $items = WC()->cart->get_cart();
        return reset( $items );
    }

    /** Test: 20% discount on ₹1000 = ₹800. */
    public function test_percentage_20() {
        $item = $this->add_and_apply( 20, 'percentage' );
        $this->assertEquals( 800, $item['data']->get_price() );
    }

    /** Test: 50% discount on ₹1000 = ₹500. */
    public function test_percentage_50() {
        $item = $this->add_and_apply( 50, 'percentage' );
        $this->assertEquals( 500, $item['data']->get_price() );
    }

    /** Test: 100% discount = ₹0 (free). */
    public function test_percentage_100_is_free() {
        $item = $this->add_and_apply( 100, 'percentage' );
        $this->assertEquals( 0, $item['data']->get_price() );
    }

    /** Test: 0% discount = original price. */
    public function test_percentage_zero() {
        $item = $this->add_and_apply( 0, 'percentage' );
        $this->assertEquals( 1000, $item['data']->get_price() );
    }

    /** Test: ₹300 flat discount on ₹1000 = ₹700. */
    public function test_flat_300() {
        $item = $this->add_and_apply( 300, 'flat' );
        $this->assertEquals( 700, $item['data']->get_price() );
    }

    /** Test: flat discount > price = ₹0 (never negative). */
    public function test_flat_exceeds_price_floors_at_zero() {
        $item = $this->add_and_apply( 2000, 'flat' );
        $this->assertEquals( 0, $item['data']->get_price(),
            'Price should be 0, not negative.' );
    }

    /** Test: regular item (no bump) keeps original price. */
    public function test_no_discount_keeps_original() {
        WC()->cart->add_to_cart( $this->product->get_id() );
        $this->discount->apply_bump_discounts( WC()->cart );

        $items = WC()->cart->get_cart();
        $item  = reset( $items );
        $this->assertEquals( 1000, $item['data']->get_price() );
    }

    /** Test: empty cart does not error. */
    public function test_empty_cart_no_error() {
        $this->discount->apply_bump_discounts( WC()->cart );
        $this->assertCount( 0, WC()->cart->get_cart() );
    }

    /** Test: mixed cart — only bump item gets discount. */
    public function test_mixed_cart_only_bump_discounted() {
        // Regular item (no discount).
        $product_2 = WC_Helper_Product::create_simple_product( true, [
            'regular_price' => '500',
            'price'         => '500',
        ] );
        WC()->cart->add_to_cart( $product_2->get_id() );

        // Bump item (20% discount).
        WC()->cart->add_to_cart( $this->product->get_id(), 1, 0, [], [
            'naxoorbu_bump_id'       => 999,
            'naxoorbu_discount'      => 20,
            'naxoorbu_discount_type' => 'percentage',
        ] );

        $this->discount->apply_bump_discounts( WC()->cart );

        foreach ( WC()->cart->get_cart() as $item ) {
            if ( ! empty( $item['naxoorbu_discount'] ) ) {
                $this->assertEquals( 800, $item['data']->get_price(), 'Bump item should be ₹800.' );
            } else {
                $this->assertEquals( 500, $item['data']->get_price(), 'Regular item should stay ₹500.' );
            }
        }
    }
}