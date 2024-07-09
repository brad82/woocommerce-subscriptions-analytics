/**
 * External dependencies
 */
import { __, _x } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

const SUBSCRIPTION_REPORT_CHARTS_FILTER =
	'woocommerce_admin_subscription_report_charts';
const SUBSCRIPTION_REPORT_FILTERS_FILTER =
	'woocommerce_admin_subscription_report_filters';
const SUBSCRIPTION_REPORT_ADVANCED_FILTERS_FILTER =
	'woocommerce_admin_subscription_report_advanced_filters';

/**
 * @typedef {import('../../index.js').chart} chart
 */

/**
 * Subscription Report charts filter.
 *
 * @filter woocommerce_admin_subscription_report_charts
 * @param {Array.<chart>} charts Report charts.
 */
export const charts = applyFilters( SUBSCRIPTION_REPORT_CHARTS_FILTER, [
	{
		key: 'new_subscriptions',
		label: __( 'New Subscriptions', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'new_subscriptions',
		type: 'number',
		isReverseTrend: true,
	},
	{
		key: 'new_trials',
		label: __( 'New Trials', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'new_trials',
		type: 'number',
		isReverseTrend: false,
	},
	{
		key: 'expired_trials',
		label: __( 'Expired Trials', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'expired_trials',
		type: 'number',
		isReverseTrend: false,
	},
	{
		key: 'revenue',
		label: __( 'Revenue', 'woocommerce-admin' ),
		orderby: 'revenue',
		type: 'currency',
		isReverseTrend: false,
	},
	{
		key: 'refunds',
		label: __( 'Refunds', 'woocommerce-admin' ),
		orderby: 'refunds',
		type: 'currency',
		isReverseTrend: false,
	},
	{
		key: 'coupons',
		label: __( 'Coupons', 'woocommerce' ),
		order: 'desc',
		orderby: 'coupons',
		type: 'currency',
		isReverseTrend: false,
	},
	{
		key: 'net_revenue',
		label: __( 'Net sales', 'woocommerce' ),
		orderby: 'net_revenue',
		type: 'currency',
		isReverseTrend: false,
		labelTooltipText: __(
			'Full refunds are not deducted from tax or net sales totals',
			'woocommerce'
		),
	},
	{
		key: 'switches',
		label: __( 'Switches', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'switches',
		type: 'number',
		isReverseTrend: false,
	},
	{
		key: 'cancellations',
		label: __( 'Cancellations', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'cancellations',
		type: 'number',
		isReverseTrend: false,
	},
	{
		key: 'on_hold',
		label: __( 'On Hold', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'on_hold',
		type: 'number',
		isReverseTrend: false,
	},
	{
		key: 'expired',
		label: __( 'Expired', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'expired',
		type: 'count',
		isReverseTrend: false,
	},
	{
		key: 'resubscribed',
		label: __( 'Resubscribes', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'resubscribes',
		type: 'count',
		isReverseTrend: false,
	},
] );

/**
 * Subscription Report Advanced Filters.
 *
 * @filter woocommerce_admin_subscription_report_advanced_filters
 * @param {Object} advancedFilters         Report Advanced Filters.
 * @param {string} advancedFilters.title   Interpolated component string for Advanced Filters title.
 * @param {Object} advancedFilters.filters An object specifying a report's Advanced Filters.
 */
export const advancedFilters = applyFilters(
	SUBSCRIPTION_REPORT_ADVANCED_FILTERS_FILTER,
	{
		filters: {},
		title: _x(
			'Subscription Matches {{select /}} Filters',
			'A sentence describing filters for Subscription. See screen shot for context: https://cloudup.com/cSsUY9VeCVJ',
			'woocommerce-admin'
		),
	}
);

const filterValues = [];

if ( Object.keys( advancedFilters.filters ).length ) {
	filterValues.push( {
		label: __( 'All Subscription', 'woocommerce-admin' ),
		value: 'all',
	} );
	filterValues.push( {
		label: __( 'Advanced Filters', 'woocommerce-admin' ),
		value: 'advanced',
	} );
}

/**
 * @typedef {import('../../index.js').filter} filter
 */

/**
 * Subscription Report Filters.
 *
 * @filter woocommerce_admin_subscription_report_filters
 * @param {Array.<filter>} filters Report filters.
 */
export const filters = applyFilters( SUBSCRIPTION_REPORT_FILTERS_FILTER, [
	{
		label: __( 'Show', 'woocommerce-admin' ),
		staticParams: [ 'chartType', 'paged', 'per_page' ],
		param: 'filter',
		showFilters: () => filterValues.length > 0,
		filters: filterValues,
	},
] );
