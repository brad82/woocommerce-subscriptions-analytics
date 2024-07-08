<?php

namespace SOS\Analytics\Admin\API\Reports\Renewals\Stats;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Admin\API\Reports\DataStore as ReportsDataStore;
use Automattic\WooCommerce\Admin\API\Reports\DataStoreInterface;
use Automattic\WooCommerce\Admin\API\Reports\TimeInterval;
use Automattic\WooCommerce\Admin\API\Reports\SqlQuery;
use Automattic\WooCommerce\Admin\API\Reports\Cache as ReportsCache;
use Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore as CustomersDataStore;
use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * API\Reports\Renewals\Stats\DataStore.
 */
class DataStore extends ReportsDataStore implements DataStoreInterface {

	/**
	 * Table used to get the data.
	 *
	 * @var string
	 */
	protected static $table_name = 'wc_subscription_stats_rollup';

	/**
	 * Cron event name.
	 */
	const CRON_EVENT = 'wc_subscription_stats_rollup_update';

	/**
	 * Cache identifier.
	 *
	 * @var string
	 */
	protected $cache_key = 'subscriptions_snapshot_stats';

	/**
	 * Type for each column to cast values correctly later.
	 *
	 * @var array
	 */
	protected $column_types = array(
		'renewals_count'      => 'intval',
		'num_items_sold'      => 'intval',
		'gross_sales'         => 'floatval',
		'total_sales'         => 'floatval',
		'coupons'             => 'floatval',
		'coupons_count'       => 'intval',
		'refunds'             => 'floatval',
		'taxes'               => 'floatval',
		'shipping'            => 'floatval',
		'net_revenue'         => 'floatval',
		'avg_items_per_order' => 'floatval',
		'avg_order_value'     => 'floatval',
		'total_customers'     => 'intval',
		'products'            => 'intval',
		'segment_id'          => 'intval',
	);

	/**
	 * Data store context used to pass to filters.
	 *
	 * @var string
	 */
	protected $context = 'subscriptions_snapshot_stats';

	/**
	 * Dynamically sets the date column name based on configuration
	 */
	public function __construct() {
		$this->date_column_name = get_option( 'woocommerce_date_type', 'date_paid' );
		parent::__construct();
	}

	/**
	 * Assign report columns once full table name has been assigned.
	 */
	protected function assign_report_columns() {
		$table_name = self::get_db_table_name();

		$this->report_columns = array(
			'date'                => 'date',
			'count_active'        => 'count_active',
			'count_active_trials' => 'count_active_trials',
			'calculated_mrr'      => 'calculated_mrr',
			'calculated_arr'      => 'calculated_arr',
		);
	}

	/**
	 * Set up all the hooks for maintaining and populating table data.
	 */
	public static function init() {
		// No lifecycle hooks for this log
	}

	/**
	 * Updates the totals and intervals database queries with parameters used for Renewals report: categories, coupons and order status.
	 *
	 * @param array $query_args      Query arguments supplied by the user.
	 */
	protected function renewals_stats_sql_filter( $query_args ) {
		// phpcs:ignore Generic.Commenting.Todo.TaskFound
		// @todo Performance of all of this?
		global $wpdb;
	}

	/**
	 * Returns the report data based on parameters supplied by the user.
	 *
	 * @param array $query_args  Query parameters.
	 * @return stdClass|WP_Error Data.
	 */
	public function get_data( $query_args ) {
		global $wpdb;

		$table_name = self::get_db_table_name();

		// These defaults are only applied when not using REST API, as the API has its own defaults that overwrite these for most values (except before, after, etc).
		$defaults = array(
			'per_page'  => get_option( 'posts_per_page' ),
			'page'      => 1,
			'order'     => 'DESC',
			'orderby'   => 'date',
			'before'    => TimeInterval::default_before(),
			'after'     => TimeInterval::default_after(),
			'interval'  => 'week',
			'fields'    => '*',
			'segmentby' => '',
			'match'     => 'all',
		);

		$query_args = wp_parse_args( $query_args, $defaults );
		$this->normalize_timezones( $query_args, $defaults );

		/*
		 * We need to get the cache key here because
		 * parent::update_intervals_sql_params() modifies $query_args.
		 */
		$cache_key = $this->get_cache_key( $query_args );
		$data      = $this->get_cached_data( $cache_key );

		if ( isset( $query_args['date_type'] ) ) {
			$this->date_column_name = $query_args['date_type'];
		}

		if ( false === $data ) {
			$this->initialize_queries();

			$data = (object) array(
				'totals'    => (object) array(),
				'intervals' => (object) array(),
				'total'     => 0,
				'pages'     => 0,
				'page_no'   => 0,
			);

			$selections = $this->selected_columns( $query_args );
			$this->add_time_period_sql_params( $query_args, $table_name );
			$this->add_intervals_sql_params( $query_args, $table_name );
			$this->add_order_by_sql_params( $query_args );
			$where_time  = $this->get_sql_clause( 'where_time' );
			$params      = $this->get_limit_sql_params( $query_args );
			$coupon_join = "LEFT JOIN (
						SELECT
							order_id,
							SUM(discount_amount) AS discount_amount,
							COUNT(DISTINCT coupon_id) AS coupons_count
						FROM
							{$wpdb->prefix}wc_order_coupon_lookup
						GROUP BY
							order_id
						) order_coupon_lookup
						ON order_coupon_lookup.order_id = {$wpdb->prefix}wc_order_stats.order_id";

			// Additional filtering for Renewals report.
			$this->renewals_stats_sql_filter( $query_args );
			$this->total_query->add_sql_clause( 'select', $selections );
			$this->total_query->add_sql_clause( 'left_join', $coupon_join );
			$this->total_query->add_sql_clause( 'where_time', $where_time );
			$totals = $wpdb->get_results(
				$this->total_query->get_query_statement(),
				ARRAY_A
			); // phpcs:ignore cache ok, DB call ok, unprepared SQL ok.
			if ( null === $totals ) {
				return new \WP_Error( 'woocommerce_renewals_analytics_revenue_result_failed', __( 'Sorry, fetching revenue data failed.', 'woocommerce' ) );
			}

			// phpcs:ignore Generic.Commenting.Todo.TaskFound
			// @todo Remove these assignements when refactoring segmenter classes to use query objects.
			$totals_query    = array(
				'from_clause'       => $this->total_query->get_sql_clause( 'join' ),
				'where_time_clause' => $where_time,
				'where_clause'      => $this->total_query->get_sql_clause( 'where' ),
			);
			$intervals_query = array(
				'select_clause'     => $this->get_sql_clause( 'select' ),
				'from_clause'       => $this->interval_query->get_sql_clause( 'join' ),
				'where_time_clause' => $where_time,
				'where_clause'      => $this->interval_query->get_sql_clause( 'where' ),
				'limit'             => $this->get_sql_clause( 'limit' ),
			);

			$unique_products            = $this->get_unique_product_count( $totals_query['from_clause'], $totals_query['where_time_clause'], $totals_query['where_clause'] );
			$totals[0]['products']      = $unique_products;
			$segmenter                  = new Segmenter( $query_args, $this->report_columns );
			$unique_coupons             = $this->get_unique_coupon_count( $totals_query['from_clause'], $totals_query['where_time_clause'], $totals_query['where_clause'] );
			$totals[0]['coupons_count'] = $unique_coupons;
			$totals[0]['segments']      = $segmenter->get_totals_segments( $totals_query, $table_name );
			$totals                     = (object) $this->cast_numbers( $totals[0] );

			$this->interval_query->add_sql_clause( 'select', $this->get_sql_clause( 'select' ) . ' AS time_interval' );
			$this->interval_query->add_sql_clause( 'left_join', $coupon_join );
			$this->interval_query->add_sql_clause( 'where_time', $where_time );
			$db_intervals = $wpdb->get_col(
				$this->interval_query->get_query_statement()
			); // phpcs:ignore cache ok, DB call ok, , unprepared SQL ok.

			$db_interval_count       = count( $db_intervals );
			$expected_interval_count = TimeInterval::intervals_between( $query_args['after'], $query_args['before'], $query_args['interval'] );
			$total_pages             = (int) ceil( $expected_interval_count / $params['per_page'] );

			if ( $query_args['page'] < 1 || $query_args['page'] > $total_pages ) {
				return $data;
			}

			$this->update_intervals_sql_params( $query_args, $db_interval_count, $expected_interval_count, $table_name );
			$this->interval_query->add_sql_clause( 'order_by', $this->get_sql_clause( 'order_by' ) );
			$this->interval_query->add_sql_clause( 'limit', $this->get_sql_clause( 'limit' ) );
			$this->interval_query->add_sql_clause( 'select', ", MAX({$table_name}.date_created) AS datetime_anchor" );
			if ( '' !== $selections ) {
				$this->interval_query->add_sql_clause( 'select', ', ' . $selections );
			}
			$intervals = $wpdb->get_results(
				$this->interval_query->get_query_statement(),
				ARRAY_A
			); // phpcs:ignore cache ok, DB call ok, unprepared SQL ok.

			if ( null === $intervals ) {
				return new \WP_Error( 'woocommerce_renewals_analytics_revenue_result_failed', __( 'Sorry, fetching revenue data failed.', 'woocommerce' ) );
			}

			if ( isset( $intervals[0] ) ) {
				$unique_coupons                = $this->get_unique_coupon_count( $intervals_query['from_clause'], $intervals_query['where_time_clause'], $intervals_query['where_clause'], true );
				$intervals[0]['coupons_count'] = $unique_coupons;
			}

			$data = (object) array(
				'totals'    => $totals,
				'intervals' => $intervals,
				'total'     => $expected_interval_count,
				'pages'     => $total_pages,
				'page_no'   => (int) $query_args['page'],
			);

			if ( TimeInterval::intervals_missing( $expected_interval_count, $db_interval_count, $params['per_page'], $query_args['page'], $query_args['order'], $query_args['orderby'], count( $intervals ) ) ) {
				$this->fill_in_missing_intervals( $db_intervals, $query_args['adj_after'], $query_args['adj_before'], $query_args['interval'], $data );
				$this->sort_intervals( $data, $query_args['orderby'], $query_args['order'] );
				$this->remove_extra_records( $data, $query_args['page'], $params['per_page'], $db_interval_count, $expected_interval_count, $query_args['orderby'], $query_args['order'] );
			} else {
				$this->update_interval_boundary_dates( $query_args['after'], $query_args['before'], $query_args['interval'], $data->intervals );
			}
			$segmenter->add_intervals_segments( $data, $intervals_query, $table_name );
			$this->create_interval_subtotals( $data->intervals );

			$this->set_cached_data( $cache_key, $data );
		}

		return $data;
	}

	/**
	 * Calculation methods.
	 */

	/**
	 * Get number of items sold among all renewals.
	 *
	 * @param array $order WC_Order object.
	 * @return int
	 */
	protected static function get_num_items_sold( $order ) {
		$num_items = 0;

		$line_items = $order->get_items( 'line_item' );
		foreach ( $line_items as $line_item ) {
			$num_items += $line_item->get_quantity();
		}

		return $num_items;
	}

	/**
	 * Get the net amount from an order without shipping, tax, or refunds.
	 *
	 * @param array $order WC_Order object.
	 * @return float
	 */
	protected static function get_net_total( $order ) {
		$net_total = floatval( $order->get_total() ) - floatval( $order->get_total_tax() ) - floatval( $order->get_shipping_total() );
		return (float) $net_total;
	}

	/**
	 * Check to see if an order's customer has made previous renewals or not
	 *
	 * @param array     $order WC_Order object.
	 * @param int|false $customer_id Customer ID. Optional.
	 * @return bool
	 */
	public static function is_returning_customer( $order, $customer_id = null ) {
		if ( is_null( $customer_id ) ) {
			$customer_id = \Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore::get_existing_customer_id_from_order( $order );
		}

		if ( ! $customer_id ) {
			return false;
		}

		$oldest_renewals = \Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore::get_oldest_renewals( $customer_id );

		if ( empty( $oldest_renewals ) ) {
			return false;
		}

		$first_order       = $oldest_renewals[0];
		$second_order      = isset( $oldest_renewals[1] ) ? $oldest_renewals[1] : false;
		$excluded_statuses = self::get_excluded_report_order_statuses();

		// Order is older than previous first order.
		if ( $order->get_date_created() < wc_string_to_datetime( $first_order->date_created ) &&
			! in_array( $order->get_status(), $excluded_statuses, true )
		) {
			self::set_customer_first_order( $customer_id, $order->get_id() );
			return false;
		}

		// The current order is the oldest known order.
		$is_first_order = (int) $order->get_id() === (int) $first_order->order_id;
		// Order date has changed and next oldest is now the first order.
		$date_change = $second_order &&
			$order->get_date_created() > wc_string_to_datetime( $first_order->date_created ) &&
			wc_string_to_datetime( $second_order->date_created ) < $order->get_date_created();
		// Status has changed to an excluded status and next oldest order is now the first order.
		$status_change = $second_order &&
			in_array( $order->get_status(), $excluded_statuses, true );
		if ( $is_first_order && ( $date_change || $status_change ) ) {
			self::set_customer_first_order( $customer_id, $second_order->order_id );
			return true;
		}

		return (int) $order->get_id() !== (int) $first_order->order_id;
	}

	/**
	 * Set a customer's first order and all others to returning.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $order_id Order ID.
	 */
	protected static function set_customer_first_order( $customer_id, $order_id ) {
		global $wpdb;
		$renewals_stats_table = self::get_db_table_name();

		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore Generic.Commenting.Todo.TaskFound
				// TODO: use the %i placeholder to prepare the table name when available in the minimum required WordPress version.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$renewals_stats_table} SET returning_customer = CASE WHEN order_id = %d THEN false ELSE true END WHERE customer_id = %d",
				$order_id,
				$customer_id
			)
		);
	}

	/**
	 * Initialize query objects.
	 */
	protected function initialize_queries() {
		$this->clear_all_clauses();
		unset( $this->subquery );
		$this->total_query = new SqlQuery( $this->context . '_total' );
		$this->total_query->add_sql_clause( 'from', self::get_db_table_name() );

		$this->interval_query = new SqlQuery( $this->context . '_interval' );
		$this->interval_query->add_sql_clause( 'from', self::get_db_table_name() );
		$this->interval_query->add_sql_clause( 'group_by', 'time_interval' );
	}
}
