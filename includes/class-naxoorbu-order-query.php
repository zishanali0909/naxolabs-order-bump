<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Naxoorbu_Order_Query — sales made through order bumps, read from WooCommerce orders.
 *
 * Single definition of "bump revenue", shared by the dashboard and reports (e.g. Pro analytics),
 * so every screen shows the same numbers:
 *   - bump items:   order line items carrying the `_naxoorbu_bump_id` meta
 *   - paid orders:  wc_get_is_paid_statuses() (processing, completed and custom paid statuses)
 *   - net revenue:  line totals excluding tax, minus refunded amounts of those items
 *                   (refund line items point back via `_refunded_item_id`), like WooCommerce
 *                   Analytics "Net sales"; refunds count towards the original order's date
 *   - storage:      HPOS or posts, whichever WooCommerce currently uses
 *
 * @since 1.0.1
 */
class Naxoorbu_Order_Query {

    /**
     * Dimensions get_sales() can group by.
     */
    const GROUPS = [ 'bump', 'product', 'day' ];

    /**
     * Whether WooCommerce stores orders in its own tables (HPOS) rather than posts.
     *
     * @return bool
     */
    public static function uses_hpos() {
        return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Order statuses that count as a sale (with the "wc-" prefix used in the database).
     *
     * @return string[]
     */
    public static function paid_statuses() {
        $statuses = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : [ 'processing', 'completed' ];

        /**
         * Filter the order statuses whose bump items count as sales in reports.
         *
         * @since 1.0.1
         * @param string[] $statuses Status slugs without the "wc-" prefix.
         */
        $statuses = (array) apply_filters( 'naxoorbu_report_paid_statuses', $statuses );

        return array_map(
            function ( $status ) {
                return 'wc-' . sanitize_key( preg_replace( '/^wc-/', '', $status ) );
            },
            $statuses
        );
    }

    /**
     * SQL: join a line-item table alias to its paid order.
     *
     * @param string $item_alias  Alias of woocommerce_order_items.
     * @param string $order_alias Alias to give the orders table.
     * @return string
     */
    public static function order_join( $item_alias = 'oi', $order_alias = 'o' ) {
        global $wpdb;
        $statuses = "'" . implode( "','", array_map( 'esc_sql', self::paid_statuses() ) ) . "'";

        if ( self::uses_hpos() ) {
            return "INNER JOIN {$wpdb->prefix}wc_orders AS {$order_alias}
                     ON {$order_alias}.id = {$item_alias}.order_id
                     AND {$order_alias}.type = 'shop_order'
                     AND {$order_alias}.status IN ( {$statuses} )";
        }

        return "INNER JOIN {$wpdb->posts} AS {$order_alias}
                 ON {$order_alias}.ID = {$item_alias}.order_id
                 AND {$order_alias}.post_type = 'shop_order'
                 AND {$order_alias}.post_status IN ( {$statuses} )";
    }

    /**
     * SQL: the order's creation date column (GMT).
     *
     * @param string $order_alias Alias of the orders table.
     * @return string
     */
    public static function order_date_column( $order_alias = 'o' ) {
        return $order_alias . '.' . ( self::uses_hpos() ? 'date_created_gmt' : 'post_date_gmt' );
    }

    /**
     * SQL: left join the refunded amount (negative) of each line item, as `{$alias}.amount`.
     *
     * @param string $item_id_column Column holding the original line item ID.
     * @param string $alias          Alias of the joined refunds.
     * @return string
     */
    public static function refunds_join( $item_id_column, $alias = 'refunds' ) {
        global $wpdb;

        return "LEFT JOIN (
                    SELECT CAST( r_ref.meta_value AS UNSIGNED ) AS item_id,
                           SUM( r_total.meta_value ) AS amount
                    FROM {$wpdb->prefix}woocommerce_order_itemmeta AS r_ref
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS r_total
                        ON r_total.order_item_id = r_ref.order_item_id
                        AND r_total.meta_key = '_line_total'
                    WHERE r_ref.meta_key = '_refunded_item_id'
                    GROUP BY r_ref.meta_value
                ) AS {$alias} ON {$alias}.item_id = {$item_id_column}";
    }

    /**
     * Bump sales, optionally grouped and limited to a date range.
     *
     * @param array $args {
     *     @type string[] $group_by   Any of 'bump', 'product', 'day' (site timezone). Default none (one total row).
     *     @type int      $since      Orders created at or after this Unix time. Default no limit.
     *     @type int      $until      Orders created before this Unix time. Default no limit.
     *     @type int      $bump_id    Only this bump. Default all.
     *     @type bool     $paid_items Skip items sold for free (e.g. a BOGO bonus). Default false.
     * }
     * @return object[] Rows with the group columns (bump_id, product_id, day) plus
     *                  conversions (orders × bumps), items, gross, refunded (≤ 0) and revenue (net).
     */
    public static function get_sales( $args = [] ) {
        global $wpdb;

        $args     = wp_parse_args( $args, [ 'group_by' => [], 'since' => 0, 'until' => 0, 'bump_id' => 0, 'paid_items' => false ] );
        $group_by = array_values( array_intersect( self::GROUPS, (array) $args['group_by'] ) );
        $date_col = self::order_date_column( 'o' );

        $select = [];
        $joins  = [];
        $where  = [ "oim_bump.meta_key = '_naxoorbu_bump_id'" ];
        $params = [];

        if ( in_array( 'bump', $group_by, true ) ) {
            $select[] = 'CAST( oim_bump.meta_value AS UNSIGNED ) AS bump_id';
        }
        if ( in_array( 'product', $group_by, true ) ) {
            $select[] = 'CAST( oim_product.meta_value AS UNSIGNED ) AS product_id';
            $joins[]  = "INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS oim_product
                          ON oim_product.order_item_id = oim_bump.order_item_id
                          AND oim_product.meta_key = '_product_id'";
        }
        if ( in_array( 'day', $group_by, true ) ) {
            // Day in the site timezone, using its current UTC offset.
            $offset   = (int) wp_timezone()->getOffset( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) );
            $select[] = "DATE( DATE_ADD( {$date_col}, INTERVAL {$offset} SECOND ) ) AS day";
        }

        if ( $args['since'] ) {
            $where[]  = "{$date_col} >= %s";
            $params[] = gmdate( 'Y-m-d H:i:s', (int) $args['since'] );
        }
        if ( $args['until'] ) {
            $where[]  = "{$date_col} < %s";
            $params[] = gmdate( 'Y-m-d H:i:s', (int) $args['until'] );
        }
        if ( $args['bump_id'] ) {
            $where[]  = 'oim_bump.meta_value = %d';
            $params[] = (int) $args['bump_id'];
        }
        if ( $args['paid_items'] ) {
            $where[] = 'CAST( oim_total.meta_value AS DECIMAL(19,4) ) > 0';
        }

        $group_cols = array_map(
            function ( $group ) {
                return [ 'bump' => 'bump_id', 'product' => 'product_id', 'day' => 'day' ][ $group ];
            },
            $group_by
        );

        $sql = 'SELECT ' . implode( ', ', array_merge( $select, [
                'COUNT( DISTINCT oi.order_id, oim_bump.meta_value ) AS conversions',
                'COUNT( * ) AS items',
                'COALESCE( SUM( oim_total.meta_value ), 0 ) AS gross',
                'COALESCE( SUM( refunds.amount ), 0 ) AS refunded',
            ] ) ) . "
             FROM {$wpdb->prefix}woocommerce_order_itemmeta AS oim_bump
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS oim_total
                 ON oim_total.order_item_id = oim_bump.order_item_id
                 AND oim_total.meta_key = '_line_total'
             INNER JOIN {$wpdb->prefix}woocommerce_order_items AS oi
                 ON oi.order_item_id = oim_bump.order_item_id
             " . self::order_join( 'oi', 'o' ) . '
             ' . implode( "\n", $joins ) . '
             ' . self::refunds_join( 'oim_bump.order_item_id' ) . '
             WHERE ' . implode( ' AND ', $where )
            . ( $group_cols ? ' GROUP BY ' . implode( ', ', $group_cols ) : '' )
            . ( in_array( 'day', $group_by, true ) ? ' ORDER BY day ASC' : ( $group_cols ? ' ORDER BY gross DESC' : '' ) );

        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from fixed fragments above.
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $rows = (array) $wpdb->get_results( $sql );

        foreach ( $rows as $row ) {
            foreach ( [ 'bump_id', 'product_id' ] as $col ) {
                if ( isset( $row->$col ) ) $row->$col = (int) $row->$col;
            }
            $row->conversions = (int) $row->conversions;
            $row->items       = (int) $row->items;
            // Meta values are summed as floats; round away binary noise (e.g. 3692.9999999).
            $row->gross       = round( (float) $row->gross, 4 );
            $row->refunded    = round( (float) $row->refunded, 4 );
            $row->revenue     = round( $row->gross + $row->refunded, 4 );
        }

        return $rows;
    }

    /**
     * Net bump revenue and conversions in total.
     *
     * @param array $args See get_sales() (without group_by).
     * @return array { revenue: float, conversions: int }
     */
    public static function get_totals( $args = [] ) {
        $args['group_by'] = [];
        $row = current( self::get_sales( $args ) );

        return [
            'revenue'     => $row ? $row->revenue : 0.0,
            'conversions' => $row ? $row->conversions : 0,
        ];
    }
}
