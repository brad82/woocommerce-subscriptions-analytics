/**
 * External dependencies
 */
import { __, _n } from '@wordpress/i18n';
import { Component } from '@wordpress/element';
import { format as formatDate } from '@wordpress/date';
import { withSelect } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { get, memoize } from 'lodash';
import { Date, Link } from '@woocommerce/components';
import { formatValue } from '@woocommerce/number';
import {
	getReportTableQuery,
	REPORTS_STORE_NAME,
	SETTINGS_STORE_NAME,
	QUERY_DEFAULTS,
} from '@woocommerce/data';
import {
	appendTimestamp,
	defaultTableDateFormat,
	getCurrentDates,
} from '@woocommerce/date';
import { stringify } from 'qs';

/**
 * Internal dependencies
 */
import ReportTable from '../../components/report-table';
import { CurrencyContext } from '../../lib/currency-context';

const EMPTY_ARRAY = [];

const summaryFields = [
	'orders_count',
	'gross_sales',
	'total_sales',
	'refunds',
	'coupons',
	'taxes',
	'shipping',
	'net_revenue',
];

class SubscriptionReportTable extends Component {
	constructor() {
		super();

		this.getHeadersContent = this.getHeadersContent.bind( this );
		this.getRowsContent = this.getRowsContent.bind( this );
		this.getSummary = this.getSummary.bind( this );
	}

	getHeadersContent() {
		return [
			{
				label: __( 'Date', 'sos-analytics' ),
				key: 'date',
				required: true,
				defaultSort: true,
				isLeftAligned: true,
				isSortable: true,
			},
			{
				label: __( 'New Subscriptions', 'sos-analytics' ),
				key: 'new_subscriptions',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'New Trials', 'sos-analytics' ),
				key: 'new_trials',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'Gross Sales', 'sos-analytics' ),
				key: 'gross_sales',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'Refunds', 'sos-analytics' ),
				key: 'refunds',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'Coupons', 'sos-analytics' ),
				key: 'coupons',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'Net Revenue', 'sos-analytics' ),
				key: 'net_revenue',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'Taxes', 'sos-analytics' ),
				key: 'taxes',
				required: false,
				isSortable: true,
				isNumeric: true,
			},

			{
				label: __( 'Switches', 'sos-analytics' ),
				key: 'switches',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'Cancellations', 'sos-analytics' ),
				key: 'cancellations',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'On Hold', 'sos-analytics' ),
				key: 'on_hold',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'Expired', 'sos-analytics' ),
				key: 'expired',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'Resubscribes', 'sos-analytics' ),
				key: 'resubscribes',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
		];
	}

	getRowsContent( data = [] ) {
		const dateFormat = defaultTableDateFormat;
		const {
			formatAmount,
			render: renderCurrency,
			formatDecimal: getCurrencyFormatDecimal,
			getCurrencyConfig,
		} = this.context;

		return data.map( ( row ) => {
			const {
				date,
				new_subscriptions,
				new_trials,
				gross_sales,
				coupons,
				net_revenue,
				taxes,
				refunds,
				switches,
				cancellations,
				on_hold,
				expired,
				resubscribes,
			} = row.subtotals;

			return [
				{
					display: (
						<Date
							date={ row.date_start }
							visibleFormat={ dateFormat }
						/>
					),
					value: row.date_start,
				},
				{
					display: new_subscriptions,
					value: Number( new_subscriptions ),
				},
				{
					display: new_trials,
					value: Number( new_trials ),
				},
				{
					display: formatAmount( gross_sales ),
					value: getCurrencyFormatDecimal( gross_sales ),
				},
				{
					display: formatAmount( refunds ),
					value: getCurrencyFormatDecimal( refunds ),
				},
				{
					display: formatAmount( coupons ),
					value: getCurrencyFormatDecimal( coupons ),
				},
				{
					display: formatAmount( net_revenue ),
					value: getCurrencyFormatDecimal( net_revenue ),
				},
				{
					display: formatAmount( taxes ),
					value: getCurrencyFormatDecimal( taxes ),
				},
				{
					display: switches ?? 0,
					value: Number( switches ),
				},
				{
					display: cancellations ?? 0,
					value: Number( cancellations ),
				},
				{
					display: on_hold ?? 0,
					value: Number( on_hold ),
				},
				{
					display: expired ?? 0,
					value: Number( expired ),
				},
				{
					display: resubscribes ?? 0,
					value: Number( resubscribes ),
				},
			];
		} );
	}

	getSummary( totals, totalResults = 0 ) {
		const { new_subscriptions, new_trials, gross_sales } = totals;

		const {
			formatAmount,
			getCurrencyConfig,
			formatDecimal: getCurrencyFormatDecimal,
		} = this.context;

		const currency = getCurrencyConfig();
		return [
			{
				label: _n( 'day', 'days', totalResults, 'sos-analytics' ),
				value: formatValue( currency, 'number', totalResults ),
			},
			{
				label: __( 'New Subscriptions', 'sos-analytics' ),
				value: new_subscriptions ?? 0,
			},
			{
				label: __( 'New Trials', 'sos-analytics' ),
				value: new_trials ?? 0,
			},
			{
				label: __( 'Gross Sales', 'sos-analytics' ),
				value: getCurrencyFormatDecimal( gross_sales ),
			},
		];
	}

	render() {
		const { advancedFilters, filters, tableData, query } = this.props;

		return (
			<ReportTable
				endpoint="subscriptions"
				getHeadersContent={ this.getHeadersContent }
				getRowsContent={ this.getRowsContent }
				getSummary={ this.getSummary }
				summaryFields={ summaryFields }
				query={ query }
				tableData={ tableData }
				title={ __( 'Subscriptions', 'sos-analytics' ) }
				columnPrefsKey="subscriptions_report_columns"
				filters={ filters }
				advancedFilters={ advancedFilters }
			/>
		);
	}
}

SubscriptionReportTable.contextType = CurrencyContext;

/**
 * Memoized props object formatting function.
 *
 * @param {boolean} isError
 * @param {boolean} isRequesting
 * @param {Object}  tableQuery
 * @param {Object}  revenueData
 * @return {Object} formatted tableData prop
 */
const formatProps = memoize(
	( isError, isRequesting, tableQuery, revenueData ) => ( {
		tableData: {
			items: {
				data: get( revenueData, [ 'data', 'intervals' ], EMPTY_ARRAY ),
				totalResults: get( revenueData, [ 'totalResults' ], 0 ),
			},
			isError,
			isRequesting,
			query: tableQuery,
		},
	} ),
	( isError, isRequesting, tableQuery, revenueData ) =>
		[
			isError,
			isRequesting,
			stringify( tableQuery ),
			get( revenueData, [ 'totalResults' ], 0 ),
			get( revenueData, [ 'data', 'intervals' ], EMPTY_ARRAY ).length,
		].join( ':' )
);

/**
 * Memoized table query formatting function.
 *
 * @param {string} order
 * @param {string} orderBy
 * @param {number} page
 * @param {number} pageSize
 * @param {Object} datesFromQuery
 * @return {Object} formatted tableQuery object
 */
const formatTableQuery = memoize(
	// @todo Support hour here when viewing a single day
	( order, orderBy, page, pageSize, datesFromQuery ) => ( {
		interval: 'day',
		orderby: orderBy,
		order,
		page,
		per_page: pageSize,
		after: appendTimestamp( datesFromQuery.primary.after, 'start' ),
		before: appendTimestamp( datesFromQuery.primary.before, 'end' ),
	} ),
	( order, orderBy, page, pageSize, datesFromQuery ) =>
		[
			order,
			orderBy,
			page,
			pageSize,
			datesFromQuery.primary.after,
			datesFromQuery.primary.before,
		].join( ':' )
);

export default compose(
	withSelect( ( select, props ) => {
		const { query, filters, advancedFilters } = props;
		const { woocommerce_default_date_range: defaultDateRange } = select(
			SETTINGS_STORE_NAME
		).getSetting( 'wc_admin', 'wcAdminSettings' );
		const datesFromQuery = getCurrentDates( query, defaultDateRange );
		const { getReportStats, getReportStatsError, isResolving } =
			select( REPORTS_STORE_NAME );
		const tableQuery = formatTableQuery(
			query.order || 'desc',
			query.orderby || 'date',
			query.paged || 1,
			query.per_page || QUERY_DEFAULTS.pageSize,
			datesFromQuery
		);
		const filteredTableQuery = getReportTableQuery( {
			endpoint: 'subscriptions',
			query,
			select,
			tableQuery,
			filters,
			advancedFilters,
		} );
		const revenueData = getReportStats(
			'subscriptions',
			filteredTableQuery
		);
		const isError = Boolean(
			getReportStatsError( 'revenue', filteredTableQuery )
		);
		const isRequesting = isResolving( 'getReportStats', [
			'subscriptions',
			filteredTableQuery,
		] );
		return formatProps( isError, isRequesting, tableQuery, revenueData );
	} )
)( SubscriptionReportTable );
