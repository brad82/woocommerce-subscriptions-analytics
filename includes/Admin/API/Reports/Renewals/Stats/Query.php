<?php
/**
 * Class for parameter-based Order Stats Reports querying
 *
 * Example usage:
 * $args = array(
 *          'before'       => '2018-07-19 00:00:00',
 *          'after'        => '2018-07-05 00:00:00',
 *          'interval'     => 'week',
 *          'categories'   => array(15, 18),
 *          'coupons'      => array(138),
 *          'status_in'    => array('completed'),
 *         );
 * $report = new \Automattic\WooCommerce\Admin\API\Reports\Renewals\Stats\Query( $args );
 * $mydata = $report->get_data();
 */

namespace SOS\Analytics\Admin\API\Reports\Renewals\Stats;

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use stdClass;
use WC_Subscription;
use function wcs_get_subscriptions;
use Automattic\WooCommerce\Admin\API\Reports\Query as ReportsQuery;

/**
 * API\Reports\Renewals\Stats\Query
 */
class Query extends ReportsQuery {

	private static $defaultModel = array(
		'revenue'       => 0,
		'renewal_count' => 0.0,
	);

	/**
	 * Valid fields for Renewals report.
	 *
	 * @return array
	 */
	protected function get_default_query_vars() {
		return array(
			'fields' => array(
				'date',
				'revenue',
				'renewal_count',
				'product_name',
				'product_id',
			),
		);
	}

	/**
	 * Get revenue data based on the current query vars.
	 *
	 * @return stdClass
	 */
	public function get_data() {
		$args = apply_filters( 'woocommerce_analytics_renewals_stats_query_args', $this->get_query_vars() );

		$report_data            = new StdClass();
		$report_data->totals    = static::$defaultModel;
		$report_data->intervals = array();

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
						'value'   => '2024-02-01 00:00:00',
						'compare' => '>',
						'type'    => 'DATETIME',
					),
				),
			)
		);

		foreach ( $subscriptions as $order ) {
			$subscription = new WC_Subscription( $order );

			++$report_data->totals['renewal_count'];
			$report_data->totals['revenue'] += $order->get_total();

			$next_payment_date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $subscription->get_date( 'next_payment' ) );
			$interval_key      = $next_payment_date->format( 'Y-m-d' );

			if ( ! array_key_exists( $interval_key, $report_data->intervals ) ) {
				$report_data->intervals[ $interval_key ] = static::$defaultModel;
			}

			++$report_data->intervals[ $interval_key ]['renewal_count'];
			$report_data->intervals[ $interval_key ]['revenue'] += $order->get_total();
		}

		return apply_filters( 'woocommerce_analytics_renewals_stats_select_query', $report_data, $args );
	}
}
