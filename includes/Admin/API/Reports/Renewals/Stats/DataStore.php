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
	protected $cache_key = 'renewals_stats';

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
	protected $context = 'renewals_stats';

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
			'interval'         => 'week',
			'fields'           => '*',
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
	 * Add order information to the lookup table when renewals are created or modified.
	 *
	 * @param int $post_id Post ID.
	 * @return int|bool Returns -1 if order won't be processed, or a boolean indicating processing success.
	 */
	public static function sync_order( $post_id ) {
		if ( ! OrderUtil::is_order( $post_id, array( 'shop_order', 'shop_order_refund' ) ) ) {
			return -1;
		}

		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return -1;
		}

		return self::update( $order );
	}

	/**
	 * Update the database with stats data.
	 *
	 * @param WC_Order|WC_Order_Refund $order Order or refund to update row for.
	 * @return int|bool Returns -1 if order won't be processed, or a boolean indicating processing success.
	 */
	public static function update( $order ) {
		global $wpdb;
		$table_name = self::get_db_table_name();

		if ( ! $order->get_id() || ! $order->get_date_created() ) {
			return -1;
		}

		/**
		 * Filters order stats data.
		 *
		 * @param array $data Data written to order stats lookup table.
		 * @param WC_Order $order  Order object.
		 *
		 * @since 4.0.0
		 */
		$data = apply_filters(
			'woocommerce_renewals_analytics_update_order_stats_data',
			array(
				'order_id'           => $order->get_id(),
				'parent_id'          => $order->get_parent_id(),
				'subscription_id'    => -1,
				'date_created'       => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
				'date_paid'          => $order->get_date_paid() ? $order->get_date_paid()->date( 'Y-m-d H:i:s' ) : null,
				'date_completed'     => $order->get_date_completed() ? $order->get_date_completed()->date( 'Y-m-d H:i:s' ) : null,
				'date_created_gmt'   => gmdate( 'Y-m-d H:i:s', $order->get_date_created()->getTimestamp() ),
				'num_items_sold'     => self::get_num_items_sold( $order ),
				'total_sales'        => $order->get_total(),
				'tax_total'          => $order->get_total_tax(),
				'shipping_total'     => $order->get_shipping_total(),
				'net_total'          => self::get_net_total( $order ),
				'status'             => self::normalize_order_status( $order->get_status() ),
				'customer_id'        => $order->get_report_customer_id(),
				'returning_customer' => $order->is_returning_customer(),
			),
			$order
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

		if ( 'shop_order_refund' === $order->get_type() ) {
			$parent_order = wc_get_order( $order->get_parent_id() );
			if ( $parent_order ) {
				$data['parent_id'] = $parent_order->get_id();
				$data['status']    = self::normalize_order_status( $parent_order->get_status() );
			}
			/**
			 * Set date_completed and date_paid the same as date_created to avoid problems
			 * when they are being used to sort the data, as refunds don't have them filled
			*/
			$data['date_completed'] = $data['date_created'];
			$data['date_paid']      = $data['date_created'];
		}

		// Update or add the information to the DB.
		$result = $wpdb->replace( $table_name, $data, $format );

		/**
		 * Fires when order's stats reports are updated.
		 *
		 * @param int $order_id Order ID.
		 *
		 * @since 4.0.0.
		 */
		do_action( 'woocommerce_renewals_analytics_update_order_stats', $order->get_id() );

		// Check the rows affected for success. Using REPLACE can affect 2 rows if the row already exists.
		return ( 1 === $result || 2 === $result );
	}

	/**
	 * Deletes the order stats when an order is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function delete_order( $post_id ) {
		global $wpdb;
		$order_id = (int) $post_id;

		if ( ! OrderUtil::is_order( $post_id, array( 'shop_order', 'shop_order_refund' ) ) ) {
			return;
		}

		// Retrieve customer details before the order is deleted.
		$order       = wc_get_order( $order_id );
		$customer_id = absint( CustomersDataStore::get_existing_customer_id_from_order( $order ) );

		// Delete the order.
		$wpdb->delete( self::get_db_table_name(), array( 'order_id' => $order_id ) );
		/**
		 * Fires when renewals stats are deleted.
		 *
		 * @param int $order_id Order ID.
		 * @param int $customer_id Customer ID.
		 *
		 * @since 4.0.0
		 */
		do_action( 'woocommerce_renewals_analytics_delete_order_stats', $order_id, $customer_id );

		ReportsCache::invalidate();
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
