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

use Automattic\WooCommerce\Admin\API\Reports\Query as ReportsQuery;

/**
 * API\Reports\Renewals\Stats\Query
 */
class Query extends ReportsQuery {


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
	public function get_data( $args = array() ) {
		$args = apply_filters( 'woocommerce_analytics_renewals_stats_query_args', $this->get_query_vars() );

		$data_store = \WC_Data_Store::load( 'report-subscriptions-stats-recurring-revenue' );
		$results    = $data_store->get_data( $args );

		return apply_filters( 'woocommerce_analytics_renewals_stats_select_query', $results, $args );
	}
}
