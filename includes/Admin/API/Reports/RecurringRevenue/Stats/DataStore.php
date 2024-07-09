<?php

namespace SOS\Analytics\Admin\API\Reports\RecurringRevenue\Stats;

defined( 'ABSPATH' ) || exit;

use stdClass;
use function wcs_get_subscriptions;

use Automattic\WooCommerce\Admin\API\Reports\DataStore as ReportsDataStore;
use Automattic\WooCommerce\Admin\API\Reports\DataStoreInterface;
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
	protected static $table_name = 'wc_subscription_stats_rollup';

	/**
	 * Cron event name.
	 */
	const CRON_EVENT = 'wc_subscription_stats_rollup_update_recurring_revenue';

	/**
	 * Cache identifier.
	 *
	 * @var string
	 */
	protected $cache_key = 'renewals_stats_rollup_recurring_revenue';


	protected static $defaultModel = array(
		'customers' => array(),
		'revenue'   => 0.0,
		'tax'       => 0.0,
	);

	/**
	 * Type for each column to cast values correctly later.
	 *
	 * @var array
	 */
	protected $column_types = array(
		'arr' => 'floatval',
		'mrr' => 'floatval',
	);

	/**
	 * Data store context used to pass to filters.
	 *
	 * @var string
	 */
	protected $context = 'renewals_stats';

	/**
	 * Dynamically sets the date column name based on configuration
	 */
	public function __construct() {
		$this->date_column_name = get_option( 'woocommerce_date_type', 'period' );
		parent::__construct();
	}

	/**
	 * Assign report columns once full table name has been assigned.
	 */
	protected function assign_report_columns() {
	}

	/**
	 * Set up all the hooks for maintaining and populating table data.
	 */
	public static function init() {
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
			// 'order'             => 'DESC',
			// 'orderby'           => 'date',
			// 'before'            => TimeInterval::default_before(),
			// 'after'             => TimeInterval::default_after(),
			'interval'  => 'week',
			'fields'    => '*',
			'segmentby' => '',
		);

		$query_args = wp_parse_args( $query_args, $defaults );
		$this->normalize_timezones( $query_args, $defaults );

		$cache_key = $this->get_cache_key( $query_args );
		$data      = $this->get_cached_data( $cache_key );

		if ( false === $data ) {
			$this->set_cached_data( $cache_key, $data );
		}

		return $data;
	}

	/**
	 * Calculation methods.
	 */
	public static function calculate_current_data() {
		global $wpdb;

		$data           = new stdClass();
		$data->totals   = static::$defaultModel;
		$data->segments = array();

		$rollup = array(
			'count_active'          => 0,
			'count_total_customers' => 0,
			'calculated_arpu'       => 0.0,
			'calculated_mrr'        => 0.0,
			'calculated_arr'        => 0.0,
		);

		$segments = array();

		$subscriptions = wcs_get_subscriptions(
			array(
				'subscription_status' => array( 'wc-active' ),
				'posts_per_page'      => -1,
			)
		);

		/**
		 * This would be much more efficient as an aggregated SQL query, but the HPOS
		 * scheme doesn't have easy access to these sorts of values for historic orders
		 *
		 * Maybe switch to the subscriptions_history table once thats hydrated with a
		 * good sample size
		 */
		foreach ( $subscriptions as $order ) {
			++$rollup['count_active'];

			foreach ( $order->get_items() as $item ) {
				$product    = $item->get_product();
				$product_id = $product->get_id();

				if ( ! array_key_exists( $product_id, $segments ) ) {
					$segments[ $product_id ] = array(
						'customers' => array(),
						'revenue'   => 0.0,
						'tax'       => 0.0,
					);
				}

				if ( ! in_array( $order->get_customer_id(), $segments[ $product_id ]['customers'] ) ) {
					$segments[ $product_id ]['customers'][] = $order->get_customer_id();
				}

				$segments[ $product_id ]['revenue'] += $item->get_total();
				$segments[ $product_id ]['tax']     += $item->get_total_tax();
			}

			if ( ! in_array( $order->get_customer_id(), $data->totals['customers'] ) ) {
				$data->totals['customers'][] = $order->get_customer_id();
			}

			$data->totals['revenue'] += $item->get_total();
			$data->totals['tax']     += $item->get_total_tax();
		}

		$data->segments = $segments;
		$data->totals   = self::calculate_totals( $data->totals );

		$rollup['date'] = date( 'Y-m-d' );
		$rollup['updated_at'] = date( 'Y-m-d H:i:s' );

		$rollup['count_active']          = count( $subscriptions );
		$rollup['count_total_customers'] = $data->totals['total_customers'];
		$rollup['calculated_arpu']       = $data->totals['average_revenue_per_customer'];
		$rollup['calculated_mrr']        = $data->totals['mrr'];
		$rollup['calculated_arr']        = $data->totals['arr'];

		$wpdb->replace(
			self::get_db_table_name(),
			$rollup
		);
	}
	/**
	 * Item Reducer
	 *
	 * @param array $totals
	 * @return array
	 */
	private static function calculate_totals( array $totals ) {
		$totals['total_customers'] = count( $totals['customers'] );

		unset( $totals['customers'] );

		$totals['average_revenue_per_customer'] = $totals['total_customers'] > 0 ? ( $totals['revenue'] / $totals['total_customers'] ) : 0.0;

		$totals['mrr'] = $totals['average_revenue_per_customer'] * $totals['total_customers'];

		// This can probably be calculated more accurately given better source data
		$totals['arr'] = $totals['mrr'] * 12;

		return $totals;
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
