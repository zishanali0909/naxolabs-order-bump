<?php
/**
 * Tests for check_trigger() — the rules engine.
 *
 * Tests which bumps should show based on cart contents.
 * Uses reflection to test the private method.
 *
 * @package NaxolabsOrderBump
 */
class Test_Rules extends WP_UnitTestCase {

    /** @var Naxoorbu_Frontend */
    private $frontend;

    /** @var ReflectionMethod */
    private $check_trigger;

    /** @var WC_Product_Simple */
    private $product_1;

    /** @var WC_Product_Simple */
    private $product_2;

    public function set_up() {
        parent::set_up();

        // Create the frontend class instance.
        $this->frontend = new Naxoorbu_Frontend();

        // Use reflection to access the private check_trigger() method.
        $this->check_trigger = new ReflectionMethod( Naxoorbu_Frontend::class, 'check_trigger' );
        $this->check_trigger->setAccessible( true );

        // Create test WooCommerce products.
        $this->product_1 = WC_Helper_Product::create_simple_product();
        $this->product_2 = WC_Helper_Product::create_simple_product();

        // Start a fresh WooCommerce session and cart.
        WC()->cart->empty_cart();
    }

    public function tear_down() {
        WC()->cart->empty_cart();
        parent::tear_down();
    }

    // ─── trigger_type = "all" ───────────────────────────

    /** Test: type "all" always returns true, even with empty cart. */
    public function test_trigger_all_returns_true() {
        $meta = [ 'trigger_type' => 'all' ];
        $this->assertTrue( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }

    /** Test: type "all" returns true with items in cart. */
    public function test_trigger_all_with_cart_items() {
        WC()->cart->add_to_cart( $this->product_1->get_id() );
        $meta = [ 'trigger_type' => 'all' ];
        $this->assertTrue( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }

    /** Test: missing trigger_type defaults to "all" (true). */
    public function test_trigger_missing_type_defaults_to_all() {
        $meta = [];
        $this->assertTrue( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }

    // ─── trigger_type = "specific_product" ──────────────

    /** Test: specific product IS in cart → true. */
    public function test_trigger_specific_product_in_cart() {
        WC()->cart->add_to_cart( $this->product_1->get_id() );
        $meta = [
            'trigger_type'     => 'specific_product',
            'trigger_products' => [ $this->product_1->get_id() ],
        ];
        $this->assertTrue( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }

    /** Test: specific product NOT in cart → false. */
    public function test_trigger_specific_product_not_in_cart() {
        WC()->cart->add_to_cart( $this->product_2->get_id() );
        $meta = [
            'trigger_type'     => 'specific_product',
            'trigger_products' => [ $this->product_1->get_id() ],
        ];
        $this->assertFalse( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }

    /** Test: specific product with empty cart → false. */
    public function test_trigger_specific_product_empty_cart() {
        $meta = [
            'trigger_type'     => 'specific_product',
            'trigger_products' => [ $this->product_1->get_id() ],
        ];
        $this->assertFalse( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }

    /** Test: specific product with empty trigger list → false. */
    public function test_trigger_specific_product_empty_list() {
        WC()->cart->add_to_cart( $this->product_1->get_id() );
        $meta = [
            'trigger_type'     => 'specific_product',
            'trigger_products' => [],
        ];
        $this->assertFalse( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }

    // ─── trigger_type = "category" ──────────────────────

    /** Test: product in matching category → true. */
    public function test_trigger_category_match() {
        $cat = wp_insert_term( 'Test Cat', 'product_cat' );
        wp_set_post_terms( $this->product_1->get_id(), [ $cat['term_id'] ], 'product_cat' );
        WC()->cart->add_to_cart( $this->product_1->get_id() );

        $meta = [
            'trigger_type'       => 'category',
            'trigger_categories' => [ $cat['term_id'] ],
        ];
        $this->assertTrue( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }

    /** Test: product NOT in matching category → false. */
    public function test_trigger_category_no_match() {
        $cat_a = wp_insert_term( 'Cat A', 'product_cat' );
        $cat_b = wp_insert_term( 'Cat B', 'product_cat' );
        wp_set_post_terms( $this->product_1->get_id(), [ $cat_a['term_id'] ], 'product_cat' );
        WC()->cart->add_to_cart( $this->product_1->get_id() );

        $meta = [
            'trigger_type'       => 'category',
            'trigger_categories' => [ $cat_b['term_id'] ],
        ];
        $this->assertFalse( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }

    // ─── trigger_type = "minimum_order" ─────────────────

    /** Test: cart total >= minimum → true. */
    public function test_trigger_minimum_order_met() {
        $this->product_1->set_price( 500 );
        $this->product_1->save();
        WC()->cart->add_to_cart( $this->product_1->get_id() );
        WC()->cart->calculate_totals();

        $meta = [
            'trigger_type'         => 'minimum_order',
            'minimum_order_amount' => 400,
        ];
        $this->assertTrue( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }

    /** Test: cart total < minimum → false. */
    public function test_trigger_minimum_order_not_met() {
        $this->product_1->set_price( 200 );
        $this->product_1->save();
        WC()->cart->add_to_cart( $this->product_1->get_id() );
        WC()->cart->calculate_totals();

        $meta = [
            'trigger_type'         => 'minimum_order',
            'minimum_order_amount' => 500,
        ];
        $this->assertFalse( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }

    /** Test: minimum = 0 always passes. */
    public function test_trigger_minimum_order_zero() {
        WC()->cart->add_to_cart( $this->product_1->get_id() );
        WC()->cart->calculate_totals();

        $meta = [
            'trigger_type'         => 'minimum_order',
            'minimum_order_amount' => 0,
        ];
        $this->assertTrue( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }

    // ─── trigger_type = unknown ─────────────────────────

    /** Test: unknown trigger type → false. */
    public function test_trigger_unknown_type() {
        $meta = [ 'trigger_type' => 'magic_unicorn' ];
        $this->assertFalse( $this->check_trigger->invoke( $this->frontend, $meta ) );
    }
}