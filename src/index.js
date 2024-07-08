/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies
 */
import './index.scss';
import addCharts from './dashboard/charts';

addCharts();

const SubscriptionsReport = lazy( () =>
	import(
		/* webpackChunkName: "analytics-report-subscriptions" */ './analytics/subscriptions'
	)
);
const RecurrentRevenueReport = lazy( () =>
	import(
		/* webpackChunkName: "analytics-report-recurrent-revenue" */ './analytics/recurring-revenue'
	)
);
const RenewalsReport = lazy( () =>
	import(
		/* webpackChunkName: "analytics-report-renewals" */ './analytics/renewals'
	)
);

addFilter(
	'woocommerce_admin_reports_list',
	'analytics_subscriptions',
	( pages ) => [
		...pages,
		{
			report: 'renewals',
			title: 'Renewals',
			component: RenewalsReport,
			navArgs: {
				id: 'analytics-renewals',
			},
		},
		{
			report: 'subscriptions',
			title: 'Subscriptions',
			component: SubscriptionsReport,
			navArgs: {
				id: 'analytics-subscriptions',
			},
		},
		{
			report: 'recurring-revenue',
			title: 'MRR / ARR',
			component: RecurrentRevenueReport,
			navArgs: {
				id: 'analytics-recurrent-revenue',
			},
		},
	]
);
