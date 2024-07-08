/**
 * External dependencies
 */
import { __, _x } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

const RECURRENT_REVENUE_REPORT_CHARTS_FILTER =
	'woocommerce_admin_recurrent_revenue_report_charts';
const RECURRENT_REVENUE_REPORT_FILTERS_FILTER =
	'woocommerce_admin_recurrent_revenue_report_filters';
const RECURRENT_REVENUE_REPORT_ADVANCED_FILTERS_FILTER =
	'woocommerce_admin_recurrent_revenue_report_advanced_filters';

/**
 * @typedef {import('../../index.js').chart} chart
 */

/**
 * Recurrent_revenue Report charts filter.
 *
 * @filter woocommerce_admin_recurrent_revenue_report_charts
 * @param {Array.<chart>} charts Report charts.
 */
export const charts = applyFilters( RECURRENT_REVENUE_REPORT_CHARTS_FILTER, [
	{
		key: 'average_revenue_per_customer',
		label: __( 'ARPU/ARPC', 'sos-analytics' ),
		order: 'desc',
		orderby: 'gross_sales',
		type: 'currency',
		isReverseTrend: false,
	},
	{
		key: 'total_customers',
		label: __( 'Total Customers', 'sos-analytics' ),
		order: 'desc',
		orderby: 'total_customers',
		type: 'number',
		isReverseTrend: true,
	},
	{
		key: 'mrr',
		label: __( 'MRR', 'sos-analytics' ),
		order: 'desc',
		orderby: 'coupons',
		type: 'currency',
		isReverseTrend: false,
	},
	{
		key: 'arr',
		label: __( 'ARR', 'sos-analytics' ),
		orderby: 'arr',
		type: 'currency',
		isReverseTrend: false,
	},
] );

/**
 * Recurrent_revenue Report Advanced Filters.
 *
 * @filter woocommerce_admin_recurrent_revenue_report_advanced_filters
 * @param {Object} advancedFilters         Report Advanced Filters.
 * @param {string} advancedFilters.title   Interpolated component string for Advanced Filters title.
 * @param {Object} advancedFilters.filters An object specifying a report's Advanced Filters.
 */
export const advancedFilters = applyFilters(
	RECURRENT_REVENUE_REPORT_ADVANCED_FILTERS_FILTER,
	{
		filters: {},
		title: _x(
			'Recurrent_revenue Matches {{select /}} Filters',
			'A sentence describing filters for Recurrent_revenue. See screen shot for context: https://cloudup.com/cSsUY9VeCVJ',
			'sos-analytics'
		),
	}
);

const filterValues = [];

if ( Object.keys( advancedFilters.filters ).length ) {
	filterValues.push( {
		label: __( 'All Recurrent_revenue', 'sos-analytics' ),
		value: 'all',
	} );
	filterValues.push( {
		label: __( 'Advanced Filters', 'sos-analytics' ),
		value: 'advanced',
	} );
}

/**
 * @typedef {import('../../index.js').filter} filter
 */

/**
 * Recurrent_revenue Report Filters.
 *
 * @filter woocommerce_admin_recurrent_revenue_report_filters
 * @param {Array.<filter>} filters Report filters.
 */
export const filters = applyFilters( RECURRENT_REVENUE_REPORT_FILTERS_FILTER, [
	{
		label: __( 'Show', 'sos-analytics' ),
		staticParams: [ 'chartType', 'paged', 'per_page' ],
		param: 'filter',
		showFilters: () => filterValues.length > 0,
		filters: filterValues,
	},
] );
