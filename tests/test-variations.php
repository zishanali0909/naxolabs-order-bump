<?php
/**
 * Tests for offering single variations of variable products in a bump.
 *
 * @package NaxolabsOrderBump
 */
class Test_Variations extends WP_UnitTestCase {

    /** @var WC_Product_Variable Variable product (Size: Small / Large). */
    private $parent;

    /** @var WC_Product_Variation Size: Small. */
    private $small;

    /** @var WC_Product_Variation Size: Large. */
    private $large;

    /** @var WC_Product_Variation Size: Any (shopper has to choose). */
    private $any;

    /** @var int Bump post ID. */
    private $bump_id;

    public function set_up() {
        parent::set_up();

        $attribute = new WC_Product_Attribute();
        $attribute->set_name( 'Size' );
        $attribute->set_options( [ 'Small', 'Large' ] );
        $attribute->set_visible( true );
        $attribute->set_variation( true );

        $this->parent = new WC_Product_Variable();
        $this->parent->set_name( 'T-shirt' );
        $this->parent->set_attributes( [ $attribute ] );
        $this->parent->save();

        $this->small = $this->variation( 'Small', 500 );
        $this->large = $this->variation( 'Large', 700 );
        $this->any   = $this->variation( '', 600 );

        $this->bump_id = wp_insert_post( [
            'post_type'   => 'naxoorbu_order_bump',
            'post_title'  => 'Add a T-shirt',
            'post_status' => 'publish',
        ] );
        $this->settings( [ $this->small->get_id() ] );

        WC()->cart->empty_cart();
    }

    public function tear_down() {
        WC()->cart->empty_cart();
        parent::tear_down();
    }

    private function variation( $size, $price ) {
        $v = new WC_Product_Variation();
        $v->set_parent_id( $this->parent->get_id() );
        $v->set_attributes( [ 'size' => $size ] );
        $v->set_regular_price( $price );
        $v->set_status( 'publish' );
        $v->save();
        return wc_get_product( $v->get_id() );
    }

    private function settings( $product_ids, $extra = [] ) {
        $products = [];
        foreach ( $product_ids as $id ) {
            $products[] = [ 'id' => $id, 'discount' => 10, 'discount_type' => 'percentage', 'qty' => 1 ];
        }
        update_post_meta( $this->bump_id, '_naxoorbu_settings', array_merge( [ 'products' => $products ], $extra ) );
        delete_transient( Naxoorbu_Visibility::CACHE_KEY );
    }

    /** Only products that need no choices can be offered. */
    public function test_can_offer() {
        $this->assertTrue( Naxoorbu_Cart::can_offer( $this->small ) );
        $this->assertTrue( Naxoorbu_Cart::can_offer( WC_Helper_Product::create_simple_product() ) );
        $this->assertFalse( Naxoorbu_Cart::can_offer( $this->parent ), 'Variable product needs options.' );
        $this->assertFalse( Naxoorbu_Cart::can_offer( $this->any ), '"Any" attribute needs a choice.' );
    }

    /** Ticking adds the variation itself, with its attributes and the bump discount. */
    public function test_variation_is_added_with_attributes_and_discount() {
        $result = Naxoorbu_Cart::add_bump_product( $this->small->get_id(), 1, $this->bump_id );
        $this->assertTrue( $result['added'] );

        $item = WC()->cart->get_cart_item( $result['cart_item_key'] );
        $this->assertSame( $this->parent->get_id(), (int) $item['product_id'] );
        $this->assertSame( $this->small->get_id(), (int) $item['variation_id'] );
        $this->assertSame( 'Small', $item['variation']['attribute_size'] );
        $this->assertSame( $this->bump_id, (int) $item['naxoorbu_bump_id'] );

        WC()->cart->calculate_totals();
        $this->assertEqualsWithDelta( 450.0, (float) WC()->cart->get_subtotal(), 0.001 );

        $this->assertTrue( Naxoorbu_Visibility::has_bump_item_in_cart( $this->bump_id, $this->small->get_id() ) );
        $again = Naxoorbu_Cart::add_bump_product( $this->small->get_id(), 1, $this->bump_id );
        $this->assertTrue( $again['already'], 'Second tick finds the same line.' );
    }

    /** Unticking removes the variation line. */
    public function test_untick_removes_variation() {
        Naxoorbu_Cart::add_bump_product( $this->small->get_id(), 1, $this->bump_id );
        Naxoorbu_Cart::remove_bump_product( $this->small->get_id(), $this->bump_id );
        $this->assertTrue( WC()->cart->is_empty() );
    }

    /** A variable product saved in a bump (before 1.2.0) is not offered and cannot be added. */
    public function test_variable_parent_is_not_offered() {
        $this->settings( [ $this->parent->get_id(), $this->any->get_id() ] );
        $bump = [ 'id' => $this->bump_id, 'meta' => get_post_meta( $this->bump_id, '_naxoorbu_settings', true ) ];

        $this->assertSame( [], Naxoorbu_Visibility::get_offered_product_ids( $bump ) );
        $this->assertWPError( Naxoorbu_Cart::add_bump_product( $this->parent->get_id(), 1, $this->bump_id ) );
        $this->assertWPError( Naxoorbu_Cart::add_bump_product( $this->any->get_id(), 1, $this->bump_id ) );
    }

    /** A rule on the variable product matches any of its variations in the cart. */
    public function test_rule_on_parent_matches_variation_in_cart() {
        $this->settings( [ $this->small->get_id() ], [ 'trigger_type' => 'specific_product', 'trigger_products' => [ $this->parent->get_id() ] ] );
        $meta = get_post_meta( $this->bump_id, '_naxoorbu_settings', true );

        $this->assertFalse( Naxoorbu_Visibility::check_trigger( $meta ) );
        WC()->cart->add_to_cart( $this->parent->get_id(), 1, $this->large->get_id(), [ 'attribute_size' => 'Large' ] );
        $this->assertTrue( Naxoorbu_Visibility::check_trigger( $meta ) );
    }

    /** A variation the shopper already has at full price is not offered again. */
    public function test_variation_already_in_cart_is_not_offered() {
        WC()->cart->add_to_cart( $this->parent->get_id(), 1, $this->small->get_id(), [ 'attribute_size' => 'Small' ] );
        $bump = [ 'id' => $this->bump_id, 'meta' => get_post_meta( $this->bump_id, '_naxoorbu_settings', true ) ];

        $this->assertSame( [], Naxoorbu_Visibility::get_offered_product_ids( $bump ) );
        $this->assertWPError( Naxoorbu_Cart::add_bump_product( $this->small->get_id(), 1, $this->bump_id ) );
    }
}
