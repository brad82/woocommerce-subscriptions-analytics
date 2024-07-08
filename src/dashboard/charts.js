/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

export default function addCharts() {
	addFilter(
		'woocommerce_admin_dashboard_charts_filter',
		'woocommerce_subscriptions_analytics',
		( charts ) => [
			...charts,
			{
				label: __( 'Active Subscriptions', 'sos-analytics' ),
				report: 'subscriptions',
				key: 'active_subscriptions',
			},
			{
				label: __( 'New Subscriptions', 'sos-analytics' ),
				report: 'subscriptions',
				key: 'new_subscriptions',
			},
			{
				label: __( 'MRR', 'sos-analytics' ),
				report: 'subscriptions',
				key: 'monthly_recurrent_revenue',
			},
			{
				label: __( 'ARR', 'sos-analytics' ),
				report: 'subscriptions',
				key: 'annual_recurrent_revenue',
			},
		]
	);
}
