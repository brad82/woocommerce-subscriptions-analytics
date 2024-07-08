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
 * $report = new \Automattic\WooCommerce\Admin\API\Reports\Subscriptions\Stats\Query( $args );
 * $mydata = $report->get_data();
 */

namespace SOS\Analytics\Admin\API\Reports\Subscriptions\Stats;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Admin\API\Reports\Query as ReportsQuery;

/**
 * API\Reports\Subscriptions\Stats\Query
 */
class Query extends ReportsQuery {

	/**
	 * Valid fields for Subscriptions report.
	 *
	 * @return array
	 */
	protected function get_default_query_vars() {
		return array(
			'fields' => array(
				'new_subscriptions',
				'new_trials',
				'expired_trials',
				'revenue',
				'switches',
				'cancellations',
				'on_hold',
				'expired',
				'resubscribed',
			),
		);
	}

	/**
	 * Get revenue data based on the current query vars.
	 *
	 * @return array
	 */
	public function get_data() {
		$args = apply_filters( 'sos_analytics_stats_query_args', $this->get_query_vars() );

		$data_store = \WC_Data_Store::load( 'report-subscriptions-stats' );
		$results    = $data_store->get_data( $args );
		return apply_filters( 'sos_analytics_stats_select_query', $results, $args );
	}
}
