<?php

namespace SOS\Analytics\Admin\API\Reports\Subscriptions\Stats;

defined( 'ABSPATH' ) || exit;

use WC_Subscription;
use Automattic\WooCommerce\Admin\API\Reports\Cache as ReportsCache;
use Automattic\WooCommerce\Admin\API\Reports\DataStore as ReportsDataStore;
use Automattic\WooCommerce\Admin\API\Reports\DataStoreInterface;
use Automattic\WooCommerce\Admin\API\Reports\TimeInterval;
use Automattic\WooCommerce\Admin\API\Reports\SqlQuery;
use Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore as CustomersDataStore;
use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * API\Reports\Subscriptions\Stats\DataStore.
 */
class DataStore extends ReportsDataStore implements DataStoreInterface {

	/**
	 * Table used to get the data.
	 *
	 * @var string
	 */
	protected static $table_name = 'wc_subscription_stats';

	/**
	 * Cron event name.
	 */
	const CRON_EVENT = 'wc_subscription_stats_update';

	/**
	 * Cache identifier.
	 *
	 * @var string
	 */
	protected $cache_key = 'subscriptions_stats';

	/**
	 * Type for each column to cast values correctly later.
	 *
	 * @var array
	 */
	protected $column_types = array(
		'subscriptions_count'        => 'intval',
		'num_items_sold'             => 'intval',
		'gross_sales'                => 'floatval',
		'total_sales'                => 'floatval',
		'coupons'                    => 'floatval',
		'coupons_count'              => 'intval',
		'refunds'                    => 'floatval',
		'taxes'                      => 'floatval',
		'shipping'                   => 'floatval',
		'net_revenue'                => 'floatval',
		'avg_items_per_subscription' => 'floatval',
		'avg_subscription_value'     => 'floatval',
		'total_customers'            => 'intval',
		'products'                   => 'intval',
		'segment_id'                 => 'intval',
		'total_customers'            => 'intval',
		'expired_trials'             => 'intval',
		'new_trials'                 => 'intval',
		'new_trials'                 => 'intval',
		'cancellations'              => 'intval',
		'on_hold'                    => 'intval',
		'expired'                    => 'intval',
	);

	/**
	 * Data store context used to pass to filters.
	 *
	 * @var string
	 */
	protected $context = 'subscriptions_stats';

	/**
	 * Dynamically sets the date column name based on configuration
	 */
	public function __construct() {
		$this->date_column_name = get_option( 'woocommerce_date_type', 'date_paid' );
		parent::__construct();
	}

	protected static function sql_count_status($table_name, $status, $as = null): string {
		$as = is_null($as) ? $status : $as;
		return "COALESCE( SUM( CASE WHEN {$table_name}.status='{$status}' THEN 1 ELSE 0 END ), 0 ) AS `{$as}`";
	}
	/**
	 * Assign report columns once full table name has been assigned.
	 */
	protected function assign_report_columns() {
		$table_name = self::get_db_table_name();
		// Avoid ambigious columns in SQL query.
		$refunds     = "ABS( SUM( CASE WHEN {$table_name}.net_total < 0 THEN {$table_name}.net_total ELSE 0 END ) )";
		$gross_sales =
			"( SUM({$table_name}.total_sales)" .
			' + COALESCE( SUM(discount_amount), 0 )' . // SUM() all nulls gives null.
			" - SUM({$table_name}.tax_total)" .
			" - SUM({$table_name}.shipping_total)" .
			" + {$refunds}" .
			' ) as gross_sales';

			$this->report_columns = array(
				'subscriptions_count' => "SUM( CASE WHEN {$table_name}.parent_id = 0 THEN 1 ELSE 0 END ) as subscriptions_count",
				'num_items_sold'      => "SUM({$table_name}.num_items_sold) as num_items_sold",
				'gross_sales'         => $gross_sales,
				'total_sales'         => "SUM({$table_name}.total_sales) AS total_sales",
				'coupons'             => 'COALESCE( SUM(discount_amount), 0 ) AS coupons', // SUM() all nulls gives null.
				'coupons_count'       => 'COALESCE( coupons_count, 0 ) as coupons_count',
				'refunds'             => "{$refunds} AS refunds",
				'taxes'               => "SUM({$table_name}.tax_total) AS taxes",
				'shipping'            => "SUM({$table_name}.shipping_total) AS shipping",
				'net_revenue'         => "SUM({$table_name}.net_total) AS net_revenue",

				'total_customers'     => "COUNT( DISTINCT( {$table_name}.customer_id ) ) as total_customers",
				'expired_trials'      => static::sql_count_status($table_name, 'trial-expired', 'expired_trials'),
				'new_trials'          => static::sql_count_status($table_name, 'trial-started', 'new_trials'),
				'new_trials'          => static::sql_count_status($table_name, 'trial-started', 'switches'),
				'cancellations'       => static::sql_count_status($table_name, 'cancelled', 'cancellations'),
				'on_hold'             => static::sql_count_status($table_name, 'on-hold', 'on_hold'),
				'expired'             => static::sql_count_status($table_name, 'expired', 'expired'),
			);
	}

	/**
	 * Set up all the hooks for maintaining and populating table data.
	 */
	public static function init() {
		add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'delete_subscription' ) );
		add_action( 'delete_post', array( __CLASS__, 'delete_subscription' ) );

		add_action( 'sos_analytics_data_store_sync_subscription', array( __CLASS__, 'sync_subscription' ), 50, 2 );
		add_action( 'sos_analytics_data_store_sync_subscription', array( ReportsCache::class, 'invalidate' ), 200) ;
	}

	/**
	 * Updates the totals and intervals database queries with parameters used for Subscriptions report: categories, coupons and subscription status.
	 *
	 * @param array $query_args      Query arguments supplied by the user.
	 */
	protected function subscriptions_stats_sql_filter( $query_args ) {
		// phpcs:ignore Generic.Commenting.Todo.TaskFound
		// @todo Performance of all of this?
		global $wpdb;

		$from_clause               = '';
		$subscriptions_stats_table = self::get_db_table_name();
		$product_lookup            = $wpdb->prefix . 'wc_order_product_lookup';
		$coupon_lookup             = $wpdb->prefix . 'wc_order_coupon_lookup';
		$tax_rate_lookup           = $wpdb->prefix . 'wc_order_tax_lookup';
		$operator                  = $this->get_match_operator( $query_args );

		$where_filters = array();

		// Products filters.
		$where_filters[] = $this->get_object_where_filter(
			$subscriptions_stats_table,
			'order_id',
			$product_lookup,
			'product_id',
			'IN',
			$this->get_included_products( $query_args )
		);
		$where_filters[] = $this->get_object_where_filter(
			$subscriptions_stats_table,
			'order_id',
			$product_lookup,
			'product_id',
			'NOT IN',
			$this->get_excluded_products( $query_args )
		);

		// Variations filters.
		$where_filters[] = $this->get_object_where_filter(
			$subscriptions_stats_table,
			'order_id',
			$product_lookup,
			'variation_id',
			'IN',
			$this->get_included_variations( $query_args )
		);
		$where_filters[] = $this->get_object_where_filter(
			$subscriptions_stats_table,
			'order_id',
			$product_lookup,
			'variation_id',
			'NOT IN',
			$this->get_excluded_variations( $query_args )
		);

		// Coupons filters.
		$where_filters[] = $this->get_object_where_filter(
			$subscriptions_stats_table,
			'order_id',
			$coupon_lookup,
			'coupon_id',
			'IN',
			$this->get_included_coupons( $query_args )
		);
		$where_filters[] = $this->get_object_where_filter(
			$subscriptions_stats_table,
			'order_id',
			$coupon_lookup,
			'coupon_id',
			'NOT IN',
			$this->get_excluded_coupons( $query_args )
		);

		// Tax rate filters.
		$where_filters[] = $this->get_object_where_filter(
			$subscriptions_stats_table,
			'order_id',
			$tax_rate_lookup,
			'tax_rate_id',
			'IN',
			implode( ',', $query_args['tax_rate_includes'] )
		);
		$where_filters[] = $this->get_object_where_filter(
			$subscriptions_stats_table,
			'order_id',
			$tax_rate_lookup,
			'tax_rate_id',
			'NOT IN',
			implode( ',', $query_args['tax_rate_excludes'] )
		);

		// Product attribute filters.
		$attribute_subqueries = $this->get_attribute_subqueries( $query_args );
		if ( $attribute_subqueries['join'] && $attribute_subqueries['where'] ) {
			// Build a subquery for getting order IDs by product attribute(s).
			// Done here since our use case is a little more complicated than get_object_where_filter() can handle.
			$attribute_subquery = new SqlQuery();
			$attribute_subquery->add_sql_clause( 'select', "{$subscriptions_stats_table}.order_id" );
			$attribute_subquery->add_sql_clause( 'from', $subscriptions_stats_table );

			// JOIN on product lookup.
			$attribute_subquery->add_sql_clause( 'join', "JOIN {$product_lookup} ON {$subscriptions_stats_table}.order_id = {$product_lookup}.order_id" );

			// Add JOINs for matching attributes.
			foreach ( $attribute_subqueries['join'] as $attribute_join ) {
				$attribute_subquery->add_sql_clause( 'join', $attribute_join );
			}
			// Add WHEREs for matching attributes.
			$attribute_subquery->add_sql_clause( 'where', 'AND (' . implode( " {$operator} ", $attribute_subqueries['where'] ) . ')' );

			// Generate subquery statement and add to our where filters.
			$where_filters[] = "{$subscriptions_stats_table}.order_id IN (" . $attribute_subquery->get_query_statement() . ')';
		}

		$where_filters[] = $this->get_customer_subquery( $query_args );
		$refund_subquery = $this->get_refund_subquery( $query_args );
		$from_clause    .= $refund_subquery['from_clause'];
		if ( $refund_subquery['where_clause'] ) {
			$where_filters[] = $refund_subquery['where_clause'];
		}

		$where_filters   = array_filter( $where_filters );
		$where_subclause = implode( " $operator ", $where_filters );

		// Append status filter after to avoid matching ANY on default statuses.
		/*
		$order_status_filter = $this->get_status_subquery( $query_args, $operator );
		if ( $order_status_filter ) {
			if ( empty( $query_args['status_is'] ) && empty( $query_args['status_is_not'] ) ) {
				$operator = 'AND';
			}
			$where_subclause = implode( " $operator ", array_filter( array( $where_subclause, $order_status_filter ) ) );
		}
		*/
		// To avoid requesting the subqueries twice, the result is applied to all queries passed to the method.
		if ( $where_subclause ) {
			$this->total_query->add_sql_clause( 'where', "AND ( $where_subclause )" );
			$this->total_query->add_sql_clause( 'join', $from_clause );
			$this->interval_query->add_sql_clause( 'where', "AND ( $where_subclause )" );
			$this->interval_query->add_sql_clause( 'join', $from_clause );
		}
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
		$defaults   = array(
			'per_page'          => get_option( 'posts_per_page' ),
			'page'              => 1,
			'order'             => 'DESC',
			'orderby'           => 'date',
			'before'            => TimeInterval::default_before(),
			'after'             => TimeInterval::default_after(),
			'interval'          => 'week',
			'fields'            => '*',
			'segmentby'         => '',

			'match'             => 'all',
			'status_is'         => array(),
			'status_is_not'     => array(),
			'product_includes'  => array(),
			'product_excludes'  => array(),
			'coupon_includes'   => array(),
			'coupon_excludes'   => array(),
			'tax_rate_includes' => array(),
			'tax_rate_excludes' => array(),
			'customer_type'     => '',
			'category_includes' => array(),
		);
		$query_args = wp_parse_args( $query_args, $defaults );
		$this->normalize_timezones( $query_args, $defaults );

		/*
		 * We need to get the cache key here because
		 * parent::update_intervals_sql_params() modifies $query_args.
		 */
		$cache_key = $this->get_cache_key( $query_args ) . time();
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
						ON order_coupon_lookup.order_id = {$wpdb->prefix}wc_subscription_stats.order_id";

			// Additional filtering for Subscriptions report.
			$this->subscriptions_stats_sql_filter( $query_args );
			$this->total_query->add_sql_clause( 'select', $selections );
			$this->total_query->add_sql_clause( 'left_join', $coupon_join );
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
	 * Add subscription information to the lookup table when subscriptions are created or modified.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $event the event to log.
	 * @return int|bool Returns -1 if subscription won't be processed, or a boolean indicating processing success.
	 */
	public static function sync_subscription( $subscription, string $event ) {
		if ( is_numeric( $subscription ) ) {
			$subscription = new WC_Subscription( $subscription );
		}
		return self::update( $subscription, $event );
	}

	/**
	 * Update the database with stats data.
	 *
	 * @param WC_Subscription $subscription Subscription or refund to update row for.
	 * @return int|bool Returns -1 if subscription won't be processed, or a boolean indicating processing success.
	 */
	public static function update( $subscription, $event ) {
		global $wpdb;
		$table_name = self::get_db_table_name();

		if ( ! $subscription->get_id() || ! $subscription->get_date_created() ) {
			return -1;
		}

		// Possibly record refunds here.
		$record_revenue = in_array( $event, array( 'active' ) );

		/**
		 * Filters subscription stats data.
		 *
		 * @param array $data Data written to subscription stats lookup table.
		 * @param WC_Subscription $subscription  Subscription object.
		 *
		 * @since 4.0.0
		 */
		$data = apply_filters(
			'sos_analytics_update_subscription_stats_data',
			array(
				'order_id'           => $subscription->get_id(),
				'parent_id'          => $subscription->get_parent_id(),
				'subscription_id'    => -1,
				'log_date'					 => date('Y-m-d H:i:s', time()),
				'log_date_gmt'			 => gmdate('Y-m-d H:i:s', time()),
				'date_created'       => $subscription->get_date_created()->date( 'Y-m-d H:i:s' ),
				'date_paid'          => $subscription->get_date_paid() ? $subscription->get_date_paid()->date( 'Y-m-d H:i:s' ) : null,
				'date_completed'     => $subscription->get_date_completed() ? $subscription->get_date_completed()->date( 'Y-m-d H:i:s' ) : null,
				'date_created_gmt'   => gmdate( 'Y-m-d H:i:s', $subscription->get_date_created()->getTimestamp() ),
				'num_items_sold'     => $record_revenue ? self::get_num_items_sold( $subscription ) : 0,
				'total_sales'        => $record_revenue ? $subscription->get_total() : 0,
				'tax_total'          => $record_revenue ? $subscription->get_total_tax() : 0,
				'shipping_total'     => $record_revenue ? $subscription->get_shipping_total() : 0,
				'net_total'          => $record_revenue ? self::get_net_total( $subscription ) : 0,
				'status'             => $subscription->get_status(),
				'customer_id'        => -1,
				'returning_customer' => false,
			),
			$subscription
		);

		$format = array(
			'%d',
			'%d',
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
			'%f',
			'%f',
			'%f',
			'%f',
			'%s',
			'%d',
			'%d',
		);

		if ( 'shop_order_refund' === $subscription->get_type() ) {
			$parent_subscription = wc_get_order( $subscription->get_parent_id() );
			if ( $parent_subscription ) {
				$data['parent_id'] = $parent_subscription->get_id();
				$data['status']    = $parent_order->get_status();
			}
			/**
			 * Set date_completed and date_paid the same as date_created to avoid problems
			 * when they are being used to sort the data, as refunds don't have them filled
			*/
			$data['date_completed'] = $data['date_created'];
			$data['date_paid']      = $data['date_created'];
		}

		// Update or add the information to the DB.
		$result = $wpdb->insert( $table_name, $data, $format );

		/**
		 * Fires when subscription's stats reports are updated.
		 *
		 * @param int $subscription_id Subscription ID.
		 *
		 * @since 4.0.0.
		 */
		do_action( 'sos_analytics_update_subscription_stats', $subscription->get_id() );

		// Check the rows affected for success. Using REPLACE can affect 2 rows if the row already exists.
		return ( 1 === $result || 2 === $result );
	}

	/**
	 * Deletes the subscription stats when an subscription is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function delete_subscription( $post_id ) {
		global $wpdb;
		$subscription_id = (int) $post_id;

		if ( ! OrderUtil::is_order( $post_id, array( 'shop_order', 'shop_order_refund' ) ) ) {
			return;
		}

		// Retrieve customer details before the subscription is deleted.
		$subscription = wc_get_order( $subscription_id );
		$customer_id  = absint( CustomersDataStore::get_existing_customer_id_from_order( $subscription ) );

		// Delete the subscription.
		$wpdb->delete( self::get_db_table_name(), array( 'order_id' => $subscription_id ) );
		/**
		 * Fires when subscriptions stats are deleted.
		 *
		 * @param int $subscription_id Subscription ID.
		 * @param int $customer_id Customer ID.
		 *
		 * @since 4.0.0
		 */
		do_action( 'sos_analytics_delete_subscription_stats', $subscription_id, $customer_id );

		ReportsCache::invalidate();
	}


	/**
	 * Calculation methods.
	 */

	/**
	 * Get number of items sold among all subscriptions.
	 *
	 * @param array $subscription WC_Subscription object.
	 * @return int
	 */
	protected static function get_num_items_sold( $subscription ) {
		$num_items = 0;

		$line_items = $subscription->get_items( 'line_item' );
		foreach ( $line_items as $line_item ) {
			$num_items += $line_item->get_quantity();
		}

		return $num_items;
	}

	/**
	 * Get the net amount from an subscription without shipping, tax, or refunds.
	 *
	 * @param array $subscription WC_Subscription object.
	 * @return float
	 */
	protected static function get_net_total( $subscription ) {
		$net_total = floatval( $subscription->get_total() ) - floatval( $subscription->get_total_tax() ) - floatval( $subscription->get_shipping_total() );
		return (float) $net_total;
	}

	/**
	 * Check to see if an subscription's customer has made previous subscriptions or not
	 *
	 * @param array     $subscription WC_Subscription object.
	 * @param int|false $customer_id Customer ID. Optional.
	 * @return bool
	 */
	public static function is_returning_customer( $subscription, $customer_id = null ) {
		if ( is_null( $customer_id ) ) {
			$customer_id = \Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore::get_existing_customer_id_from_subscription( $subscription );
		}

		if ( ! $customer_id ) {
			return false;
		}

		$oldest_subscriptions = \Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore::get_oldest_subscriptions( $customer_id );

		if ( empty( $oldest_subscriptions ) ) {
			return false;
		}

		$first_subscription  = $oldest_subscriptions[0];
		$second_subscription = isset( $oldest_subscriptions[1] ) ? $oldest_subscriptions[1] : false;
		$excluded_statuses   = self::get_excluded_report_subscription_statuses();

		// Subscription is older than previous first subscription.
		if ( $subscription->get_date_created() < wc_string_to_datetime( $first_subscription->date_created ) &&
			! in_array( $subscription->get_status(), $excluded_statuses, true )
		) {
			self::set_customer_first_subscription( $customer_id, $subscription->get_id() );
			return false;
		}

		// The current subscription is the oldest known subscription.
		$is_first_subscription = (int) $subscription->get_id() === (int) $first_subscription->order_id;
		// Order date has changed and next oldest is now the first order.
		$date_change = $second_subscription &&
			$subscription->get_date_created() > wc_string_to_datetime( $first_subscription->date_created ) &&
			wc_string_to_datetime( $second_subscription->date_created ) < $subscription->get_date_created();
		// Status has changed to an excluded status and next oldest subscription is now the first subscription.
		$status_change = $second_subscription &&
			in_array( $subscription->get_status(), $excluded_statuses, true );
		if ( $is_first_subscription && ( $date_change || $status_change ) ) {
			self::set_customer_first_subscription( $customer_id, $second_subscription->order_id );
			return true;
		}

		return (int) $subscription->get_id() !== (int) $first_subscription->order_id;
	}

	/**
	 * Set a customer's first order and all others to returning.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $subscription_id Subscription ID.
	 */
	protected static function set_customer_first_subscription( $customer_id, $subscription_id ) {
		global $wpdb;
		$subscriptions_stats_table = self::get_db_table_name();

		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore Generic.Commenting.Todo.TaskFound
				// TODO: use the %i placeholder to prepare the table name when available in the minimum required WordPress version.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$subscriptions_stats_table} SET returning_customer = CASE WHEN order_id = %d THEN false ELSE true END WHERE customer_id = %d",
				$subscription_id,
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
