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
 * $report = new  SOS\Analytics\Admin\API\RecurringRevenue\Renewals\Stats\Query( $args );
 * $mydata = $report->get_data();
 */

namespace SOS\Analytics\Admin\API\Reports\RecurringRevenue\Stats;

defined( 'ABSPATH' ) || exit;

use stdClass;
use function wcs_get_subscriptions;
use Automattic\WooCommerce\Admin\API\Reports\Query as ReportsQuery;

/**
 * API\Reports\Renewals\Stats\Query
 */
class Query extends ReportsQuery {

	protected static $defaultModel = array(
		'customers' => array(),
		'revenue'   => 0.0,
		'tax'       => 0.0,
	);

	/**
	 * Valid fields for Renewals report.
	 *
	 * @return array
	 */
	protected function get_default_query_vars() {
		return array(
			'fields' => array(
				'arr',
				'mrr',
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

		$report_data           = new stdClass();
		$report_data->totals   = static::$defaultModel;
		$report_data->segments = array();

		$subscriptions = wcs_get_subscriptions(
			array(
				'posts_per_page' => -1,
			)
		);

		$segments = array();

		// This would be much more efficient as an aggregated SQL query, but the HPOS
		// scheme doesn't have easy access to these sorts of values for historic orders
		//
		// Maybe switch to the subscriptions_history table once thats hydrated with good
		// data
		foreach ( $subscriptions as $order ) {
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

			if ( ! in_array( $order->get_customer_id(), $report_data->totals['customers'] ) ) {
				$report_data->totals['customers'][] = $order->get_customer_id();
			}

			$report_data->totals['revenue'] += $item->get_total();
			$report_data->totals['tax']     += $item->get_total_tax();
		}

		$report_data->segments = $segments;

		$report_data->totals = $this->calculate_totals( $report_data->totals );

		return apply_filters( 'woocommerce_analytics_renewals_stats_select_query', $report_data, $args );
	}

	private function calculate_totals( $totals ) {
		$totals['total_customers'] = count( $totals['customers'] );
		unset( $totals['customers'] );

		$totals['average_revenue_per_customer'] = $totals['total_customers'] > 0 ? ( $totals['revenue'] / $totals['total_customers'] ) : 0.0;

		$totals['mrr'] = $totals['average_revenue_per_customer'] * $totals['total_customers'];

		// This can probably be calculated more accurately given better source data
		$totals['arr'] = $totals['mrr'] * 12;
		return $totals;
	}
}
