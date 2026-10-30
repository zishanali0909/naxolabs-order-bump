<?php
/**
 * Tests for the "Replace Cart Items with Order Bump" behaviour.
 *
 * @package NaxolabsOrderBump
 */
class Test_Replace extends WP_UnitTestCase {

    /** @var WC_Product_Simple Product that triggers the bump. */
    private $main;

    /** @var WC_Product_Simple Unrelated product in the cart. */
    private $other;

    /** @var WC_Product_Simple Product the bump offers. */
    private $offer;

    /** @var int Bump post ID. */
    private $bump_id;

    public function set_up() {
        parent::set_up();

        $this->main  = $this->product( 1000 );
        $this->other = $this->product( 300 );
        $this->offer = $this->product( 1500 );

        $this->bump_id = wp_insert_post( [
            'post_type'   => 'naxoorbu_order_bump',
            'post_title'  => 'Upgrade',
            'post_status' => 'publish',
        ] );
        $this->settings( [ 'behaviour' => 'replace', 'trigger_type' => 'specific_product', 'trigger_products' => [ $this->main->get_id() ] ] );

        WC()->cart->empty_cart();
    }

    public function tear_down() {
        WC()->cart->empty_cart();
        parent::tear_down();
    }

    private function product( $price ) {
        $p = WC_Helper_Product::create_simple_product();
        $p->set_regular_price( $price );
        $p->set_price( $price );
        $p->save();
        return $p;
    }

    private function settings( $extra ) {
        update_post_meta( $this->bump_id, '_naxoorbu_settings', array_merge( [
            'products' => [ [ 'id' => $this->offer->get_id(), 'discount' => 10, 'discount_type' => 'percentage', 'qty' => 1 ] ],
        ], $extra ) );
        delete_transient( Naxoorbu_Visibility::CACHE_KEY );
    }

    private function cart_product_ids() {
        return array_map( function ( $item ) { return (int) $item['product_id']; }, array_values( WC()->cart->get_cart() ) );
    }

    /** Ticking a Replace bump swaps out the trigger product only; unticking puts it back. */
    public function test_replace_swaps_trigger_item_and_untick_restores_it() {
        WC()->cart->add_to_cart( $this->main->get_id(), 2 );
        WC()->cart->add_to_cart( $this->other->get_id() );

        $result = Naxoorbu_Cart::add_bump_product( $this->offer->get_id(), 1, $this->bump_id );
        $this->assertTrue( $result['added'] );
        $this->assertEqualsCanonicalizing( [ $this->other->get_id(), $this->offer->get_id() ], $this->cart_product_ids(), 'Trigger product replaced, unrelated product kept.' );

        Naxoorbu_Cart::remove_bump_product( $this->offer->get_id(), $this->bump_id );
        $this->assertEqualsCanonicalizing( [ $this->other->get_id(), $this->main->get_id() ], $this->cart_product_ids() );
        $restored = array_values( array_filter( WC()->cart->get_cart(), function ( $i ) { return (int) $i['product_id'] === $this->main->get_id(); } ) );
        $this->assertSame( 2, (int) $restored[0]['quantity'], 'Restored with its quantity.' );
    }

    /** A replaced item comes back with what other plugins stored on it (e.g. add-ons, a gift note). */
    public function test_untick_restores_item_data() {
        WC()->cart->add_to_cart( $this->main->get_id(), 1, 0, [], [ 'gift_note' => 'Happy birthday' ] );

        Naxoorbu_Cart::add_bump_product( $this->offer->get_id(), 1, $this->bump_id );
        Naxoorbu_Cart::remove_bump_product( $this->offer->get_id(), $this->bump_id );

        $restored = array_values( WC()->cart->get_cart() );
        $this->assertCount( 1, $restored );
        $this->assertSame( $this->main->get_id(), (int) $restored[0]['product_id'] );
        $this->assertSame( 'Happy birthday', $restored[0]['gift_note'] ?? null );
    }

    /** A replaced variation still counts as owned, so no other bump offers it again. */
    public function test_replaced_variation_still_owned() {
        $variable  = new WC_Product_Variable();
        $attribute = new WC_Product_Attribute();
        $attribute->set_name( 'size' );
        $attribute->set_options( [ 'Small' ] );
        $attribute->set_visible( true );
        $attribute->set_variation( true );
        $variable->set_name( 'Shirt' );
        $variable->set_attributes( [ $attribute ] );
        $variable->save();
        $variation = new WC_Product_Variation();
        $variation->set_parent_id( $variable->get_id() );
        $variation->set_attributes( [ 'size' => 'Small' ] );
        $variation->set_regular_price( 500 );
        $variation->set_status( 'publish' );
        $variation->save();
        $variation = wc_get_product( $variation->get_id() );
        $this->settings( [ 'behaviour' => 'replace', 'trigger_type' => 'specific_product', 'trigger_products' => [ $variation->get_id() ] ] ); // A trigger can list a variation.

        WC()->cart->add_to_cart( $variable->get_id(), 1, $variation->get_id(), $variation->get_variation_attributes() );
        Naxoorbu_Cart::add_bump_product( $this->offer->get_id(), 1, $this->bump_id );

        $this->assertEqualsCanonicalizing( [ $this->offer->get_id() ], $this->cart_product_ids(), 'The variation was replaced.' );
        $this->assertContains( $variation->get_id(), Naxoorbu_Visibility::get_cart_product_ids(), 'And still counts as owned.' );
    }

    /** The replaced trigger still counts for the rules, so the bump is not dropped at checkout. */
    public function test_replaced_item_keeps_bump_valid() {
        WC()->cart->add_to_cart( $this->main->get_id() );
        Naxoorbu_Cart::add_bump_product( $this->offer->get_id(), 1, $this->bump_id );

        Naxoorbu_Cart::validate_cart_items();

        $this->assertContains( $this->offer->get_id(), $this->cart_product_ids() );
        $this->assertSame( 0, wc_notice_count( 'error' ) );
        wc_clear_notices();
    }

    /** "Add" (the default) never removes anything. */
    public function test_add_behaviour_unchanged() {
        $this->settings( [ 'trigger_type' => 'specific_product', 'trigger_products' => [ $this->main->get_id() ] ] );
        WC()->cart->add_to_cart( $this->main->get_id() );

        Naxoorbu_Cart::add_bump_product( $this->offer->get_id(), 1, $this->bump_id );

        $this->assertEqualsCanonicalizing( [ $this->main->get_id(), $this->offer->get_id() ], $this->cart_product_ids() );
    }

    /** A Replace bump shown on every order (or above a minimum) replaces the whole cart, and the rule still holds. */
    public function test_replace_all_and_minimum_order() {
        $this->settings( [ 'behaviour' => 'replace', 'trigger_type' => 'minimum_order', 'minimum_order_amount' => 1200 ] );
        WC()->cart->add_to_cart( $this->main->get_id() );
        WC()->cart->add_to_cart( $this->other->get_id() );
        WC()->cart->calculate_totals();

        Naxoorbu_Cart::add_bump_product( $this->offer->get_id(), 1, $this->bump_id );
        WC()->cart->calculate_totals();

        $this->assertSame( [ $this->offer->get_id() ], $this->cart_product_ids() );
        $this->assertGreaterThanOrEqual( 1200, Naxoorbu_Visibility::get_cart_subtotal(), 'Replaced items still count towards the minimum.' );
    }

    /** Removing the bump on the cart page and pressing "Undo" swaps the items out again. */
    public function test_undo_reapplies_replacement() {
        WC()->cart->add_to_cart( $this->main->get_id() );
        $key = Naxoorbu_Cart::add_bump_product( $this->offer->get_id(), 1, $this->bump_id )['cart_item_key'];

        WC()->cart->remove_cart_item( $key );
        $this->assertSame( [ $this->main->get_id() ], $this->cart_product_ids() );

        WC()->cart->restore_cart_item( $key );
        $this->assertSame( [ $this->offer->get_id() ], $this->cart_product_ids() );
    }

    /** Items added from other bumps are never replaced. */
    public function test_other_bump_items_not_replaced() {
        $this->settings( [ 'behaviour' => 'replace', 'trigger_type' => 'all' ] );
        WC()->cart->add_to_cart( $this->other->get_id(), 1, 0, [], [ 'naxoorbu_bump_id' => 999999 ] );

        Naxoorbu_Cart::add_bump_product( $this->offer->get_id(), 1, $this->bump_id );

        $this->assertEqualsCanonicalizing( [ $this->other->get_id(), $this->offer->get_id() ], $this->cart_product_ids() );
    }
}
