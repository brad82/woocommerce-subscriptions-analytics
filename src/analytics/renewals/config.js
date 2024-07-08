/**
 * External dependencies
 */
import { __, _x } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

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

/**
 * Subscription Report charts filter.
 *
 * @filter woocommerce_admin_subscription_report_charts
 * @param {Array.<chart>} charts Report charts.
 */

export const charts = applyFilters( SUBSCRIPTION_REPORT_CHARTS_FILTER, [
	{
		key: 'renewal_count',
		label: __( 'Renewals', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'renewal_count',
		type: 'number',
		isReverseTrend: false,
	},
	{
		key: 'revenue',
		label: __( 'Projected Revenue', 'woocommerce-admin' ),
		order: 'desc',
		orderby: 'revenue',
		type: 'currency',
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
		label: __( 'Show', 'woocommerce-admin' ),
		staticParams: [ 'chartType', 'paged', 'per_page' ],
		param: 'filter',
		showFilters: () => filterValues.length > 0,
		filters: filterValues,
	},
] );
