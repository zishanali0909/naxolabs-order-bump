<?php
/**
 * Tests for Naxoorbu_Order_Query — bump revenue from WooCommerce orders
 * (paid orders only, net of refunds, date ranges and grouping).
 *
 * @package NaxolabsOrderBump
 */
class Test_Order_Query extends WP_UnitTestCase {

    /** @var WC_Product_Simple */
    private $product;

    /** @var WC_Product_Simple */
    private $other_product;

    public function set_up() {
        parent::set_up();
        $this->product       = WC_Helper_Product::create_simple_product();
        $this->other_product = WC_Helper_Product::create_simple_product();
    }

    public function tear_down() {
        remove_all_filters( 'naxoorbu_report_paid_statuses' );
        parent::tear_down();
    }

    /**
     * Helper: an order with one bump item (and optionally a regular item).
     *
     * @return array [ WC_Order, int bump item ID ]
     */
    private function create_order( $status = 'completed', $bump_total = 100, $bump_id = 7, $with_regular_item = true ) {
        $order = wc_create_order();

        $bump_item = new WC_Order_Item_Product();
        $bump_item->set_product( $this->product );
        $bump_item->set_quantity( 1 );
        $bump_item->set_subtotal( $bump_total );
        $bump_item->set_total( $bump_total );
        $bump_item->add_meta_data( '_naxoorbu_bump_id', $bump_id, true );
        $order->add_item( $bump_item );

        if ( $with_regular_item ) {
            $regular = new WC_Order_Item_Product();
            $regular->set_product( $this->other_product );
            $regular->set_quantity( 1 );
            $regular->set_subtotal( 500 );
            $regular->set_total( 500 );
            $order->add_item( $regular );
        }

        $order->calculate_totals( false );
        $order->set_status( $status );
        $order->save();

        return [ $order, $bump_item->get_id() ];
    }

    /** Test: only bump items of paid orders count. */
    public function test_counts_bump_items_of_paid_orders() {
        $this->create_order( 'completed', 100 );
        $this->create_order( 'processing', 50 );
        $this->create_order( 'pending', 1000 );
        $this->create_order( 'cancelled', 1000 );

        $totals = Naxoorbu_Order_Query::get_totals();

        $this->assertEquals( 150, $totals['revenue'] );
        $this->assertSame( 2, $totals['conversions'] );
    }

    /** Test: a partial refund of the bump item is subtracted. */
    public function test_partial_refund_subtracted() {
        list( $order, $item_id ) = $this->create_order( 'completed', 100 );

        wc_create_refund( [
            'order_id'   => $order->get_id(),
            'amount'     => 30,
            'line_items' => [ $item_id => [ 'qty' => 0, 'refund_total' => 30 ] ],
        ] );

        $row = current( Naxoorbu_Order_Query::get_sales() );
        $this->assertEquals( 100, $row->gross );
        $this->assertEquals( -30, $row->refunded );
        $this->assertEquals( 70, $row->revenue );
        $this->assertSame( 1, $row->conversions, 'A refund is not a conversion.' );
    }

    /** Test: refunding a regular item does not touch bump revenue. */
    public function test_refund_of_other_item_ignored() {
        list( $order ) = $this->create_order( 'completed', 100 );
        $regular_id    = 0;
        foreach ( $order->get_items() as $id => $item ) {
            if ( ! $item->get_meta( '_naxoorbu_bump_id' ) ) $regular_id = $id;
        }

        wc_create_refund( [
            'order_id'   => $order->get_id(),
            'amount'     => 200,
            'line_items' => [ $regular_id => [ 'qty' => 0, 'refund_total' => 200 ] ],
        ] );

        $this->assertEquals( 100, Naxoorbu_Order_Query::get_totals()['revenue'] );
    }

    /** Test: a fully refunded order no longer counts. */
    public function test_refunded_order_excluded() {
        list( $order ) = $this->create_order( 'completed', 100 );
        $order->update_status( 'refunded' );

        $this->assertEquals( 0, Naxoorbu_Order_Query::get_totals()['revenue'] );
    }

    /** Test: extra paid statuses can be added. */
    public function test_paid_statuses_filter() {
        $this->create_order( 'on-hold', 80 );
        $this->assertEquals( 0, Naxoorbu_Order_Query::get_totals()['revenue'] );

        add_filter( 'naxoorbu_report_paid_statuses', function ( $statuses ) {
            $statuses[] = 'on-hold';
            return $statuses;
        } );
        $this->assertEquals( 80, Naxoorbu_Order_Query::get_totals()['revenue'] );
    }

    /** Test: date range limits the orders counted. */
    public function test_date_range() {
        list( $old ) = $this->create_order( 'completed', 40 );
        $old->set_date_created( time() - 10 * DAY_IN_SECONDS );
        $old->save();
        $this->create_order( 'completed', 60 );

        $this->assertEquals( 60, Naxoorbu_Order_Query::get_totals( [ 'since' => time() - DAY_IN_SECONDS ] )['revenue'] );
        $this->assertEquals( 40, Naxoorbu_Order_Query::get_totals( [ 'until' => time() - DAY_IN_SECONDS ] )['revenue'] );
    }

    /** Test: grouping by bump, product and day. */
    public function test_grouping() {
        $this->create_order( 'completed', 100, 7 );
        $this->create_order( 'completed', 30, 8 );

        $by_bump = Naxoorbu_Order_Query::get_sales( [ 'group_by' => [ 'bump' ] ] );
        $this->assertSame( [ 7, 8 ], wp_list_pluck( $by_bump, 'bump_id' ), 'Sorted by revenue.' );

        $by_product = Naxoorbu_Order_Query::get_sales( [ 'group_by' => [ 'product' ] ] );
        $this->assertCount( 1, $by_product );
        $this->assertSame( $this->product->get_id(), $by_product[0]->product_id );

        $by_day = Naxoorbu_Order_Query::get_sales( [ 'group_by' => [ 'day' ] ] );
        $this->assertSame( current_datetime()->format( 'Y-m-d' ), $by_day[0]->day );
        $this->assertEquals( 130, $by_day[0]->revenue );
    }

    /** Test: free items (e.g. a BOGO bonus) can be left out. */
    public function test_paid_items_only() {
        $this->create_order( 'completed', 0, 7, false );
        $this->create_order( 'completed', 25, 7, false );

        $this->assertSame( 2, Naxoorbu_Order_Query::get_sales()[0]->items );
        $this->assertSame( 1, Naxoorbu_Order_Query::get_sales( [ 'paid_items' => true ] )[0]->items );
    }
}
