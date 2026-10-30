<?php
/**
 * Tests for bumps with a multi-currency plugin that converts prices when they are read
 * (as CURCY and FOX/WOOCS do), simulated by a filter doubling every product price.
 *
 * @package NaxolabsOrderBump
 */
class Test_Currency extends WP_UnitTestCase {

    /** @var int Bump post ID. */
    private $bump_id;

    public function set_up() {
        parent::set_up();
        add_filter( 'woocommerce_product_get_price', [ $this, 'convert' ], 99 );

        $this->bump_id = wp_insert_post( [
            'post_type'   => 'naxoorbu_order_bump',
            'post_title'  => 'Currency',
            'post_status' => 'publish',
        ] );
        WC()->cart->empty_cart();
    }

    public function tear_down() {
        remove_filter( 'woocommerce_product_get_price', [ $this, 'convert' ], 99 );
        remove_filter( 'woocs_exchange_value', [ $this, 'convert' ] );
        unset( $GLOBALS['WOOCS'] );
        WC()->cart->empty_cart();
        parent::tear_down();
    }

    /** Shopper's currency: 2 per 1 of the store currency. */
    public function convert( $price ) {
        return '' === $price ? $price : (float) $price * 2;
    }

    private function product( $price ) {
        $p = WC_Helper_Product::create_simple_product();
        $p->set_regular_price( $price );
        $p->set_price( $price );
        $p->save();
        return wc_get_product( $p->get_id() );
    }

    private function offer( $product, $discount, $type, $extra = [] ) {
        update_post_meta( $this->bump_id, '_naxoorbu_settings', array_merge( [
            'products' => [ [ 'id' => $product->get_id(), 'discount' => $discount, 'discount_type' => $type, 'qty' => 1 ] ],
        ], $extra ) );
        delete_transient( Naxoorbu_Visibility::CACHE_KEY );
    }

    public function data_discounts() {
        return [
            'percentage' => [ 10, 'percentage' ],
            'flat'       => [ 10, 'flat' ],
        ];
    }

    /**
     * The discount applies once, in the store currency; the price is converted once.
     *
     * @dataProvider data_discounts
     */
    public function test_cart_converts_discounted_price_once( $discount, $type ) {
        $product = $this->product( 100 );
        $this->offer( $product, $discount, $type );

        Naxoorbu_Cart::add_bump_product( $product->get_id(), 1, $this->bump_id );
        WC()->cart->calculate_totals();
        WC()->cart->calculate_totals(); // Recalculating must not change it.

        $this->assertEqualsWithDelta( 180.0, (float) WC()->cart->get_subtotal(), 0.001 );
    }

    /**
     * The bump shows the price the cart charges.
     *
     * @dataProvider data_discounts
     */
    public function test_shown_price_matches_cart( $discount, $type ) {
        $product = $this->product( 100 );
        $pm      = [ 'id' => $product->get_id(), 'discount' => $discount, 'discount_type' => $type ];

        $this->assertEqualsWithDelta( 180.0, Naxoorbu_Frontend::bump_price( $product, $pm ), 0.001 );
        $this->assertEqualsWithDelta( 200.0, Naxoorbu_Frontend::bump_price( $product, [ 'discount' => 0 ] ), 0.001, 'No discount: the converted price.' );
    }

    /** A minimum order amount is compared in the shopper's currency. */
    public function test_minimum_order_amount_is_converted() {
        $meta = [ 'trigger_type' => 'minimum_order', 'minimum_order_amount' => 500 ];
        $this->assertEqualsWithDelta( 500.0, Naxoorbu_Discount::convert_amount( 500 ), 0.001, 'Price rules alone (e.g. memberships) never change it.' );

        // FOX Currency Switcher: its conversion, while the cart is in the shopper's currency.
        $GLOBALS['WOOCS'] = (object) [ 'is_multiple_allowed' => 0 ];
        add_filter( 'woocs_exchange_value', [ $this, 'convert' ] );
        $this->assertEqualsWithDelta( 500.0, Naxoorbu_Discount::convert_amount( 500 ), 0.001, 'Paying in other currencies off: the cart stays in the store currency.' );
        $GLOBALS['WOOCS']->is_multiple_allowed = 1;
        $this->assertEqualsWithDelta( 1000.0, Naxoorbu_Discount::convert_amount( 500 ), 0.001 );

        WC()->cart->add_to_cart( $this->product( 300 )->get_id() ); // 600 in the shopper's currency.
        WC()->cart->calculate_totals();
        $this->assertFalse( Naxoorbu_Visibility::check_trigger( $meta ), '600 is below 500 converted (1000).' );

        WC()->cart->add_to_cart( $this->product( 250 )->get_id() ); // 1100 in all.
        WC()->cart->calculate_totals();
        $this->assertTrue( Naxoorbu_Visibility::check_trigger( $meta ) );
    }

    /** The conversion can be replaced for plugins that convert another way. */
    public function test_convert_amount_filter() {
        $filter = function ( $converted, $amount ) { return $amount * 3; };
        add_filter( 'naxoorbu_convert_amount', $filter, 10, 2 );
        $this->assertEqualsWithDelta( 300.0, Naxoorbu_Discount::convert_amount( 100 ), 0.001 );
        remove_filter( 'naxoorbu_convert_amount', $filter, 10 );
    }
}
