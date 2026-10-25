<?php
/**
 * Tests for where the block checkout shows bumps (Naxoorbu_Blocks_Integration placement).
 *
 * @package NaxolabsOrderBump
 */
class Test_Blocks_Placement extends WP_UnitTestCase {

    public function set_up() {
        parent::set_up();
        if ( ! class_exists( 'Naxoorbu_Blocks_Integration' ) ) {
            $this->markTestSkipped( 'WooCommerce Blocks integration is not loaded.' );
        }
    }

    public function tear_down() {
        remove_all_filters( 'naxoorbu_blocks_bump_placement' );
        parent::tear_down();
    }

    /** Test: by default bumps stay in the order summary and the checkout markup is untouched. */
    public function test_default_placement_leaves_markup_alone() {
        $this->assertSame( 'order_meta', Naxoorbu_Blocks_Integration::get_placement() );

        $payment = '<div data-block-name="woocommerce/checkout-payment-block"></div>';
        $this->assertSame( $payment, Naxoorbu_Blocks_Integration::render_before_payment( $payment ) );
        $this->assertSame( $payment, Naxoorbu_Blocks_Integration::render_before_actions( $payment ) );
    }

    /** Test: in the checkout form, each position gets its own inner block placeholder. */
    public function test_checkout_fields_placement() {
        add_filter( 'naxoorbu_blocks_bump_placement', function () {
            return 'checkout_fields';
        } );

        $payment = Naxoorbu_Blocks_Integration::render_before_payment( '<div>payment</div>' );
        $this->assertStringStartsWith( '<div data-block-name="naxolabs-order-bump/order-bumps" data-position="before_payment"', $payment );

        $actions = Naxoorbu_Blocks_Integration::render_before_actions( '<div>actions</div>' );
        $this->assertStringContainsString( 'data-position="after_payment"', $actions );
        $this->assertStringEndsWith( '<div>actions</div>', $actions );
    }

    /** Test: unknown placements fall back to the order summary. */
    public function test_unknown_placement_falls_back() {
        add_filter( 'naxoorbu_blocks_bump_placement', function () {
            return 'somewhere_else';
        } );
        $this->assertSame( 'order_meta', Naxoorbu_Blocks_Integration::get_placement() );
    }
}
