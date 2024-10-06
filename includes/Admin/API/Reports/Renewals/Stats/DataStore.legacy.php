<?php

namespace SOS\Analytics\Admin\API\Reports\Renewals\Stats;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Admin\API\Reports\DataStore as ReportsDataStore;
use Automattic\WooCommerce\Admin\API\Reports\DataStoreInterface;
use Automattic\WooCommerce\Admin\API\Reports\TimeInterval;
use Automattic\WooCommerce\Admin\API\Reports\SqlQuery;

/**
 * API\Reports\Renewals\Stats\DataStore.
 */
class DataStore extends ReportsDataStore implements DataStoreInterface {

	/**
	 * Table used to get the data.
	 *
	 * @var string
	 */
	protected static $table_name = '(SELECT 
			o.id
			,o.total_amount
			,CAST(rd.meta_value as DATE) as renewal_date 

			FROM wp_wc_orders o
			JOIN wp_wc_orders_meta rd
					ON rd.order_id = o.id AND rd.meta_key = "_schedule_next_payment"
			LEFT JOIN wp_wc_order_product_lookup p
					on p.order_id = o.id

			WHERE
					o.type = "shop_subscription"
					AND rd.meta_value != 0
		) as wp_wc_orders';

	/**
	 * Cron event name.
	 */
	const CRON_EVENT = 'wc_subscription_stats_update';

	/**
	 * Cache identifier.
	 *
	 * @var string
	 */
	protected $cache_key = 'renewals_stats';

	/**
	 * Type for each column to cast values correctly later.
	 *
	 * @var array
	 */
	protected $column_types = array(
		'total_orders' => 'intval',
		'revenue'			 => 'floatval'
	);

	/**
	 * Data store context used to pass to filters.
	 *
	 * @var string
	 */
	protected $context = 'renewals_stats';

	public function __construct() {
		$this->date_column_name = 'renewal_date';
		parent::__construct();
	}
	
	/**
	 * Assign report columns once full table name has been assigned.
	 */
	protected function assign_report_columns() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_orders';

		$this->report_columns = array(
			'total_orders' => "COUNT(distinct({$table_name}.`id`)) as total_orders",
			'renewal_date' => "renewal_date",
			'revenue'			 => "SUM({$table_name}.total_amount) as revenue"
		);
	}

	protected function renewals_stats_sql_filter( $query_args ) {
		global $wpdb;
		$order_stats_table = self::get_db_table_name();
		$orders_meta = $wpdb->prefix . 'wc_orders_meta';
		$product_lookup = $wpdb->prefix . 'wc_order_product_lookup';
		/*
		$where_filters = array();
		$where_filters[] = $this->get_object_where_filter(
			$order_stats_table,
			'order_id',
			$product_lookup,
			'product_id',
			'IN',
			array(334) //$this->get_included_products( $query_args )
		);
		*/
		/*
		$where_filters[] = $this->get_object_where_filter(
			$order_stats_table,
			'order_id',
			$orders_meta,
			'meta_key',
			'=',
			'_scheduled_next_payment'
		);
		$where_filters[] = $this->get_object_where_filter(
			$order_stats_table,
			'order_id',
			$orders_meta,
			'meta_value',
			'<',
			$query_args['after']
		);
		$where_filters[] = $this->get_object_where_filter(
			$order_stats_table,
			'order_id',
			$orders_meta,
			'meta_value',
			'<',
			$query_args['before']
		);
		*/
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
			'per_page'         => get_option( 'posts_per_page' ),
			'page'             => 1,
			'order'            => 'DESC',
			'orderby'          => 'date',
			'after'            => date( 'Y-m-d H:i:s' ),
			'before'           => TimeInterval::default_before(),
			'interval'         => 'day',
			'fields'           => array('total_orders', 'revenue', 'renewal_date'),
			'segmentby'        => '',
			'product_includes' => array(),
			'product_excludes' => array(),
		);

		$query_args = wp_parse_args( $query_args, $defaults );
		$this->normalize_timezones( $query_args, $defaults );

		/*
		 * We need to get the cache key here because
		 * parent::update_intervals_sql_params() modifies $query_args.
		 */
		$cache_key = $this->get_cache_key( $query_args );
		$data      = $this->get_cached_data( $cache_key );

		if ( false === $data ) {
			$this->initialize_queries();

			$data = (object) array(
				'totals'    => (object) array(),
				'intervals' => (object) array(),
				'total'     => 0,
				'pages'     => 0,
				'page_no'   => 0,
			);

			$selections = $this->selected_columns( array(
				'fields' => array('total_orders', 'revenue', 'renewal_date')
			));

			var_dump($selections);
			$this->add_time_period_sql_params( $query_args, $table_name );
			$this->add_intervals_sql_params( $query_args, $table_name );
			$this->add_order_by_sql_params( $query_args );
			$where_time  = $this->get_sql_clause( 'where_time' );

			var_dump($where_time);

			$params      = $this->get_limit_sql_params( $query_args );
	
			// Additional filtering for Subscriptions report.
			$this->renewals_stats_sql_filter( $query_args );
			$this->total_query->add_sql_clause( 'select', $selections );
			$this->total_query->add_sql_clause( 'where_time', $where_time );


			$totals = $wpdb->get_results(
				$this->total_query->get_query_statement(),
				ARRAY_A
			); // phpcs:ignore cache ok, DB call ok, unprepared SQL ok.
			if ( null === $totals ) {
				return new \WP_Error( 'sos_analytics_revenue_result_failed', __( 'Sorry, fetching revenue data failed.', 'woocommerce' ) );
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

			//$unique_products            = $this->get_unique_product_count( $totals_query['from_clause'], $totals_query['where_time_clause'], $totals_query['where_clause'] );
			//$totals[0]['products']      = $unique_products;
			//$segmenter                  = new Segmenter( $query_args, $this->report_columns );
			//$totals[0]['segments']      = $segmenter->get_totals_segments( $totals_query, $table_name );
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
				return new \WP_Error( 'sos_analytics_revenue_result_failed', __( 'Sorry, fetching revenue data failed.', 'woocommerce' ) );
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

	public function legacy_storage_query()
	{
		$data            = new stdClass();
		$data->totals    = static::$defaultModel;
		$data->intervals = array();

		$subscriptions = wcs_get_orders_with_meta_query(
			array(
				'type'       => 'shop_subscription',
				'meta_query' => array(
					array(
						'key'  => wcs_get_date_meta_key( 'next_payment' ),
						'type' => 'EXISTS',
					),
					array(
						'key'     => wcs_get_date_meta_key( 'next_payment' ),
						'value'   => $query_args['before'],
						'compare' => '>',
						'type'    => 'DATETIME',
					),
					array(
						'key'     => wcs_get_date_meta_key( 'next_payment' ),
						'value'   => $query_args['after'],
						'compare' => '<',
						'type'    => 'DATETIME',
					),
				),
			)
		);

		foreach ( $subscriptions as $order ) {
			$subscription = new WC_Subscription( $order );

			++$data->totals['renewal_count'];
			$data->totals['revenue'] += $order->get_total();

			$next_payment_date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $subscription->get_date( 'next_payment' ) );
			$interval_key      = $next_payment_date->format( 'Y-m-d' );

			if ( ! array_key_exists( $interval_key, $data->intervals ) ) {
				$data->intervals[ $interval_key ] = static::$defaultModel;
			}

			++$data->intervals[ $interval_key ]['renewal_count'];
			$data->intervals[ $interval_key ]['revenue'] += $order->get_total();
		}

		return $data;
	}
	/**
	 * Get unique products based on user time query
	 *
	 * @param string $from_clause       From clause with date query.
	 * @param string $where_time_clause Where clause with date query.
	 * @param string $where_clause      Where clause with date query.
	 * @return integer Unique product count.
	 */
	public function get_unique_product_count( $from_clause, $where_time_clause, $where_clause ) {
		global $wpdb;

		$table_name = self::get_db_table_name();
		return $wpdb->get_var(
			"SELECT
					COUNT( DISTINCT {$wpdb->prefix}wc_order_product_lookup.product_id )
				FROM
					{$wpdb->prefix}wc_order_product_lookup JOIN {$table_name} ON {$wpdb->prefix}wc_order_product_lookup.order_id = {$table_name}.order_id
					{$from_clause}
				WHERE
					1=1
					{$where_time_clause}
					{$where_clause}"
		); // phpcs:ignore cache ok, DB call ok, unprepared SQL ok.
	}

	/**
	 * Get unique coupons based on user time query
	 *
	 * @param string $from_clause       From clause with date query.
	 * @param string $where_time_clause Where clause with date query.
	 * @param string $where_clause      Where clause with date query.
	 * @return integer Unique product count.
	 */
	public function get_unique_coupon_count( $from_clause, $where_time_clause, $where_clause ) {
		global $wpdb;

		$table_name = self::get_db_table_name();
		return $wpdb->get_var(
			"SELECT
					COUNT(DISTINCT coupon_id)
				FROM
					{$wpdb->prefix}wc_order_coupon_lookup JOIN {$table_name} ON {$wpdb->prefix}wc_order_coupon_lookup.order_id = {$table_name}.order_id
					{$from_clause}
				WHERE
					1=1
					{$where_time_clause}
					{$where_clause}"
		); // phpcs:ignore cache ok, DB call ok, unprepared SQL ok.
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
	 * Fills WHERE clause of SQL request with date-related constraints.
	 *
	 * @param array  $query_args Parameters supplied by the user.
	 * @param string $table_name Name of the db table relevant for the date constraint.
	 */
	protected function add_time_period_sql_params( $query_args, $table_name ) {
		$this->clear_sql_clause( array( 'from', 'where_time', 'where' ) );
		if ( isset( $this->subquery ) ) {
			$this->subquery->clear_sql_clause( 'where_time' );
		}

		if ( isset( $query_args['before'] ) && '' !== $query_args['before'] ) {
			if ( is_a( $query_args['before'], 'WC_DateTime' ) ) {
				$datetime_str = $query_args['before']->date( TimeInterval::$sql_datetime_format );
			} else {
				$datetime_str = $query_args['before']->format( TimeInterval::$sql_datetime_format );
			}
			if ( isset( $this->subquery ) ) {
				$this->subquery->add_sql_clause( 'where_time', "AND {$this->date_column_name} <= '$datetime_str'" );
			} else {
				$this->add_sql_clause( 'where_time', "AND {$this->date_column_name} <= '$datetime_str'" );
			}
		}

		if ( isset( $query_args['after'] ) && '' !== $query_args['after'] ) {
			if ( is_a( $query_args['after'], 'WC_DateTime' ) ) {
				$datetime_str = $query_args['after']->date( TimeInterval::$sql_datetime_format );
			} else {
				$datetime_str = $query_args['after']->format( TimeInterval::$sql_datetime_format );
			}
			if ( isset( $this->subquery ) ) {
				$this->subquery->add_sql_clause( 'where_time', "AND {$this->date_column_name} >= '$datetime_str'" );
			} else {
				$this->add_sql_clause( 'where_time', "AND {$this->date_column_name} >= '$datetime_str'" );
			}
		}
	}

		/**
	 * Get table name from database class.
	 */
	public static function get_db_table_name() {
		global $wpdb;
		return static::$table_name;
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
