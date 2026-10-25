<?php
/**
 * Tests for Naxoorbu_Visibility — which bumps the shopper sees
 * (classic checkout and Store API cart data).
 *
 * @package NaxolabsOrderBump
 */
class Test_Visibility extends WP_UnitTestCase {

    /** @var WC_Product_Simple */
    private $product;

    /** @var int */
    private $bump_id;

    public function set_up() {
        parent::set_up();

        $this->product = WC_Helper_Product::create_simple_product();

        $this->bump_id = wp_insert_post( [
            'post_type'   => 'naxoorbu_order_bump',
            'post_title'  => 'Visibility Bump',
            'post_status' => 'publish',
        ] );
        update_post_meta( $this->bump_id, '_naxoorbu_settings', [
            'trigger_type' => 'all',
            'products'     => [ [ 'id' => $this->product->get_id(), 'discount' => 10, 'discount_type' => 'percentage' ] ],
        ] );

        delete_transient( Naxoorbu_Visibility::CACHE_KEY );
        WC()->cart->empty_cart();
    }

    public function tear_down() {
        remove_all_filters( 'naxoorbu_passes_conditions' );
        remove_all_filters( 'naxoorbu_is_bump_scheduled' );
        WC()->cart->empty_cart();
        delete_transient( Naxoorbu_Visibility::CACHE_KEY );
        parent::tear_down();
    }

    private function bump_data() {
        return [ 'id' => $this->bump_id, 'meta' => get_post_meta( $this->bump_id, '_naxoorbu_settings', true ) ];
    }

    /** Test: a bump with no rules is visible. */
    public function test_visible_by_default() {
        $this->assertTrue( Naxoorbu_Visibility::is_visible( $this->bump_data() ) );
        $this->assertContains( $this->bump_id, array_column( Naxoorbu_Visibility::get_visible_products(), 'bump_id' ) );
    }

    /** Test: display conditions (e.g. Pro rules) can hide a bump. */
    public function test_conditions_filter_hides_bump() {
        add_filter( 'naxoorbu_passes_conditions', '__return_false' );

        $this->assertFalse( Naxoorbu_Visibility::is_visible( $this->bump_data() ) );
        $this->assertNotContains( $this->bump_id, array_column( Naxoorbu_Visibility::get_visible_products(), 'bump_id' ) );
    }

    /** Test: scheduling (e.g. Pro schedule) can hide a bump. */
    public function test_schedule_filter_hides_bump() {
        add_filter( 'naxoorbu_is_bump_scheduled', '__return_false' );

        $this->assertFalse( Naxoorbu_Visibility::is_visible( $this->bump_data() ) );
    }

    /** Test: the Free trigger still applies. */
    public function test_trigger_hides_bump() {
        $meta                         = get_post_meta( $this->bump_id, '_naxoorbu_settings', true );
        $meta['trigger_type']         = 'minimum_order';
        $meta['minimum_order_amount'] = 999999;

        $this->assertFalse( Naxoorbu_Visibility::is_visible( [ 'id' => $this->bump_id, 'meta' => $meta ] ) );
    }

    /** Test: an accepted bump stays visible even when its rules no longer match, so it can be unticked. */
    public function test_accepted_bump_stays_visible() {
        add_filter( 'naxoorbu_passes_conditions', '__return_false' );
        WC()->cart->add_to_cart( $this->product->get_id(), 1, 0, [], [ 'naxoorbu_bump_id' => $this->bump_id ] );

        $this->assertTrue( Naxoorbu_Visibility::is_visible( $this->bump_data() ) );
    }

    /** Test: Store API cart data lists the visible bumps. */
    public function test_store_api_cart_data() {
        $data = Naxoorbu_Cart::get_store_api_cart_data();
        $this->assertContains( $this->bump_id, $data['visible_bumps'] );

        add_filter( 'naxoorbu_passes_conditions', '__return_false' );
        $this->assertSame( [], Naxoorbu_Cart::get_store_api_cart_data()['visible_bumps'] );
    }

    /** Test: Store API cart data lists the products each visible bump offers. */
    public function test_store_api_visible_products() {
        $data = Naxoorbu_Cart::get_store_api_cart_data();

        $this->assertContains(
            [ 'bump_id' => $this->bump_id, 'product_ids' => [ $this->product->get_id() ] ],
            $data['visible_products']
        );
    }

    // ─── Products the shopper already has ──────────────

    /** Test: a product the shopper added at its normal price is not offered; with nothing left the bump hides. */
    public function test_product_shopper_has_is_not_offered() {
        WC()->cart->add_to_cart( $this->product->get_id() );

        $this->assertSame( [], Naxoorbu_Visibility::get_offered_product_ids( $this->bump_data() ) );
        $this->assertFalse( Naxoorbu_Visibility::is_visible( $this->bump_data() ) );
        $this->assertNotContains( $this->bump_id, array_column( Naxoorbu_Visibility::get_visible_products(), 'bump_id' ) );
    }

    /** Test: other products of the bump are still offered. */
    public function test_other_products_still_offered() {
        $second = WC_Helper_Product::create_simple_product();
        $meta   = get_post_meta( $this->bump_id, '_naxoorbu_settings', true );
        $meta['products'][] = [ 'id' => $second->get_id(), 'discount' => 0, 'discount_type' => 'percentage' ];
        update_post_meta( $this->bump_id, '_naxoorbu_settings', $meta );

        WC()->cart->add_to_cart( $this->product->get_id() );

        $this->assertSame( [ $second->get_id() ], Naxoorbu_Visibility::get_offered_product_ids( $this->bump_data() ) );
        $this->assertTrue( Naxoorbu_Visibility::is_visible( $this->bump_data() ) );
    }

    /** Test: a product added from this bump stays offered (ticked), so it can be unticked. */
    public function test_product_added_from_bump_still_offered() {
        WC()->cart->add_to_cart( $this->product->get_id(), 1, 0, [], [ 'naxoorbu_bump_id' => $this->bump_id ] );

        $this->assertSame( [ $this->product->get_id() ], Naxoorbu_Visibility::get_offered_product_ids( $this->bump_data() ) );
        $this->assertTrue( Naxoorbu_Visibility::has_bump_item_in_cart( $this->bump_id, $this->product->get_id() ) );
        $this->assertFalse( Naxoorbu_Visibility::has_bump_item_in_cart( $this->bump_id, $this->product->get_id() + 1 ) );
    }

    // ─── Bump items never qualify themselves ───────────

    /** Test: a bump item's own price does not count towards a minimum order amount. */
    public function test_bump_items_excluded_from_cart_subtotal() {
        WC()->cart->add_to_cart( $this->product->get_id(), 1, 0, [], [ 'naxoorbu_bump_id' => $this->bump_id ] );
        WC()->cart->calculate_totals();

        $this->assertEquals( 0, Naxoorbu_Visibility::get_cart_subtotal() );
        $this->assertSame( [], Naxoorbu_Visibility::get_cart_product_ids() );

        $meta = [ 'trigger_type' => 'minimum_order', 'minimum_order_amount' => 1 ];
        $this->assertFalse( Naxoorbu_Visibility::check_trigger( $meta ) );
    }

    // ─── Checkout re-validation (like WooCommerce coupons) ─

    private function add_bump_item() {
        return WC()->cart->add_to_cart( $this->product->get_id(), 1, 0, [], [
            'naxoorbu_bump_id'       => $this->bump_id,
            'naxoorbu_discount'      => 10,
            'naxoorbu_discount_type' => 'percentage',
        ] );
    }

    /** Test: a bump item whose offer is still valid stays in the cart. */
    public function test_valid_bump_item_kept() {
        $key = $this->add_bump_item();
        Naxoorbu_Cart::validate_cart_items();

        $this->assertNotEmpty( WC()->cart->get_cart_item( $key ) );
        $this->assertSame( 0, wc_notice_count( 'error' ) );
    }

    /** Test: when the shopper no longer qualifies, the item is removed with an error notice. */
    public function test_ineligible_bump_item_removed_with_notice() {
        $key = $this->add_bump_item();
        add_filter( 'naxoorbu_is_bump_scheduled', '__return_false' );

        Naxoorbu_Cart::validate_cart_items();

        $this->assertEmpty( WC()->cart->get_cart_item( $key ) );
        $this->assertSame( 1, wc_notice_count( 'error' ) );
        wc_clear_notices();
    }

    /** Test: an item from an unpublished bump is removed. */
    public function test_item_from_unpublished_bump_removed() {
        $key = $this->add_bump_item();
        wp_update_post( [ 'ID' => $this->bump_id, 'post_status' => 'draft' ] );
        delete_transient( Naxoorbu_Visibility::CACHE_KEY );

        Naxoorbu_Cart::validate_cart_items();

        $this->assertEmpty( WC()->cart->get_cart_item( $key ) );
        wc_clear_notices();
    }

    /** Test: items another module added under a bump (e.g. a BOGO bonus product) are left alone. */
    public function test_other_module_items_left_alone() {
        $bonus = WC_Helper_Product::create_simple_product();
        $key   = WC()->cart->add_to_cart( $bonus->get_id(), 1, 0, [], [ 'naxoorbu_bump_id' => $this->bump_id ] );
        add_filter( 'naxoorbu_passes_conditions', '__return_false' );

        Naxoorbu_Cart::validate_cart_items();

        $this->assertNotEmpty( WC()->cart->get_cart_item( $key ) );
    }
}
