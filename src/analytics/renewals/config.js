/**
 * External dependencies
 */
import { __, _x } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import * as dayjs from 'dayjs';
/**
 * Internal dependencies
 */
import { getProductLabels, getVariationLabels } from '../../lib/async-requests';

const SUBSCRIPTION_REPORT_CHARTS_FILTER =
	'woocommerce_admin_subscription_report_charts';
const SUBSCRIPTION_REPORT_FILTERS_FILTER =
	'woocommerce_admin_subscription_report_filters';
const SUBSCRIPTION_REPORT_ADVANCED_FILTERS_FILTER =
	'woocommerce_admin_subscription_report_advanced_filters';

/**
 * @typedef {import('../../index.js').chart} chart
 */

function before( months ) {
	const now = dayjs();
	const then = now.add( months, 'month' );
	return then.toISOString();
}
/**
 * Subscription Report charts filter.
 *
 * @filter woocommerce_admin_subscription_report_charts
 * @param {Array.<chart>} charts Report charts.
 */

export const charts = applyFilters( SUBSCRIPTION_REPORT_CHARTS_FILTER, [
	{
		key: 'net_revenue',
		label: __( 'Projected Revenue', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'net_revenue',
		type: 'currency',
		isReverseTrend: false,
	},
	{
		key: 'renewal_count',
		label: __( 'Renewals', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'renewal_count',
		type: 'number',
		isReverseTrend: false,
	},
	{
		key: 'renewal_items',
		label: __( 'Renewal Items', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'renewal_items',
		type: 'number',
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
		filters: {
			product: {
				labels: {
					add: __( 'Product', 'woocommerce' ),
					placeholder: __( 'Search products', 'woocommerce' ),
					remove: __( 'Remove product filter', 'woocommerce' ),
					rule: __( 'Select a product filter match', 'woocommerce' ),
					/* translators: A sentence describing a Product filter. See screen shot for context: https://cloudup.com/cSsUY9VeCVJ */
					title: __(
						'<title>Product</title> <rule/> <filter/>',
						'woocommerce'
					),
					filter: __( 'Select products', 'woocommerce' ),
				},
				rules: [
					{
						value: 'includes',
						/* translators: Sentence fragment, logical, "Includes" refers to orders including a given product(s). Screenshot for context: https://cloudup.com/cSsUY9VeCVJ */
						label: _x( 'Includes', 'products', 'woocommerce' ),
					},
					{
						value: 'excludes',
						/* translators: Sentence fragment, logical, "Excludes" refers to orders excluding a given product(s). Screenshot for context: https://cloudup.com/cSsUY9VeCVJ */
						label: _x( 'Excludes', 'products', 'woocommerce' ),
					},
				],
				input: {
					component: 'Search',
					type: 'products',
					getLabels: getProductLabels,
				},
			},
			variation: {
				labels: {
					add: __( 'Product variation', 'woocommerce' ),
					placeholder: __(
						'Search product variations',
						'woocommerce'
					),
					remove: __(
						'Remove product variation filter',
						'woocommerce'
					),
					rule: __(
						'Select a product variation filter match',
						'woocommerce'
					),
					/* translators: A sentence describing a Variation filter. See screen shot for context: https://cloudup.com/cSsUY9VeCVJ */
					title: __(
						'<title>Product variation</title> <rule/> <filter/>',
						'woocommerce'
					),
					filter: __( 'Select variation', 'woocommerce' ),
				},
				rules: [
					{
						value: 'includes',
						/* translators: Sentence fragment, logical, "Includes" refers to orders including a given variation(s). Screenshot for context: https://cloudup.com/cSsUY9VeCVJ */
						label: _x( 'Includes', 'variations', 'woocommerce' ),
					},
					{
						value: 'excludes',
						/* translators: Sentence fragment, logical, "Excludes" refers to orders excluding a given variation(s). Screenshot for context: https://cloudup.com/cSsUY9VeCVJ */
						label: _x( 'Excludes', 'variations', 'woocommerce' ),
					},
				],
				input: {
					component: 'Search',
					type: 'variations',
					getLabels: getVariationLabels,
				},
			},
		},
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
		label: __( 'Period End', 'woocommerce-admin' ),
		staticParams: [ 'chartType', 'paged', 'per_page' ],
		param: 'until',
		showFilters: () => filterValues.length > 0,
		defaultValue: before( 3 ),
		filters: [
			{ label: 'Next 3 Months', value: before( 3 ) },
			{ label: 'Next 6 Months', value: before( 6 ) },
			{ label: 'Next 12 Months', value: before( 12 ) },
		],
	},
	{
		label: __( 'Show', 'woocommerce-admin' ),
		staticParams: [ 'chartType', 'paged', 'per_page' ],
		param: 'filter',
		showFilters: () => filterValues.length > 0,
		filters: filterValues,
	},
] );
