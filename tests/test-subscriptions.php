<?php
/**
 * Tests for bumps with WooCommerce Subscriptions, whose cart rules are simulated: a product is a
 * subscription when it has the `_test_subscription` meta.
 *
 * @package NaxolabsOrderBump
 */

if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
    // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- test stand-in for WooCommerce Subscriptions.
    class WC_Subscriptions_Product {
        public static function is_subscription( $product ) {
            $product = is_object( $product ) ? $product : wc_get_product( $product );
            return $product && 'yes' === $product->get_meta( '_test_subscription' );
        }

        public static function get_price_string( $product, $args = [] ) {
            return ( $args['price'] ?? '' ) . ' / month';
        }
    }
}

class Test_Subscriptions extends WP_UnitTestCase {

    public function set_up() {
        parent::set_up();
        delete_option( 'woocommerce_subscriptions_multiple_purchase' );
        WC()->cart->empty_cart();
    }

    public function tear_down() {
        delete_option( 'woocommerce_subscriptions_multiple_purchase' );
        WC()->cart->empty_cart();
        parent::tear_down();
    }

    private function product( $subscription ) {
        $p = WC_Helper_Product::create_simple_product();
        if ( $subscription ) {
            $p->update_meta_data( '_test_subscription', 'yes' );
            $p->save();
        }
        return $p;
    }

    /** "Mixed checkout" off: a subscription must be alone, and nothing joins a subscription. */
    public function test_mixed_checkout_off() {
        $sub    = $this->product( true );
        $simple = $this->product( false );

        $this->assertTrue( Naxoorbu_Cart::fits_cart( $sub ), 'Empty cart.' );

        $key = WC()->cart->add_to_cart( $simple->get_id() );
        $this->assertFalse( Naxoorbu_Cart::fits_cart( $sub ), 'Not next to another product.' );
        $this->assertTrue( Naxoorbu_Cart::fits_cart( $sub, [ $key ] ), 'Unless a Replace bump takes that product out.' );

        WC()->cart->empty_cart();
        $key = WC()->cart->add_to_cart( $this->product( true )->get_id() );
        $this->assertFalse( Naxoorbu_Cart::fits_cart( $simple ), 'Nothing joins a subscription.' );
        $this->assertFalse( Naxoorbu_Cart::fits_cart( $sub ), 'Nor a second subscription.' );
        $this->assertTrue( Naxoorbu_Cart::fits_cart( $sub, [ $key ] ), 'A Replace bump swapping plans (e.g. monthly to yearly).' );
    }

    /** "Mixed checkout" on: subscriptions and other products, several subscriptions too. */
    public function test_mixed_checkout_on() {
        update_option( 'woocommerce_subscriptions_multiple_purchase', 'yes' );
        WC()->cart->add_to_cart( $this->product( true )->get_id() );

        $this->assertTrue( Naxoorbu_Cart::fits_cart( $this->product( true ) ) );
        $this->assertTrue( Naxoorbu_Cart::fits_cart( $this->product( false ) ) );
    }
}
