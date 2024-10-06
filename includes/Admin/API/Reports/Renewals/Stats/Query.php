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

use stdClass;
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
				'renewal_count',
				'net_revenue',
				'total_customers',
				'renewal_items',
			),
		);
	}

	/**
	 * Get revenue data based on the current query vars.
	 *
	 * @return stdClass
	 */
	public function get_data() {
		$args = $this->get_query_vars();

		$args['after'] = gmdate('c', time());
		$args['before'] = gmdate('c', strtotime("+3 months", time()));

		if ( isset ( $args['until'] ) ) {
			$args['before'] = $args['until'];
		}

		// $args = apply_filters( 'sos_analytics_stats_renewals_query_args', $this->get_query_vars() );

		$data_store = \WC_Data_Store::load( 'report-renewals-stats' );
		$results    = $data_store->get_data( $args );
		return apply_filters( 'sos_analytics_stats_renewals_select_query', $results, $args );
	}
}
