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
import ReportTable from '@woocommerce/components';
import { CurrencyContext } from '../../lib/currency-context';

const EMPTY_ARRAY = [];

const summaryFields = [];

class Recurrent_revenueReportTable extends Component {
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
				label: __( 'Total Customers', 'sos-analytics' ),
				key: 'total_customers',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'ARPU', 'sos-analytics' ),
				key: 'arpu',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'MRR', 'sos-analytics' ),
				key: 'mrr',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'ARR', 'sos-analytics' ),
				key: 'arr',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
		];
	}

	getRowsContent( data = [] ) {
		/*
		const dateFormat = getAdminSetting(
			'dateFormat',
			defaultTableDateFormat
		);
		*/

		const dateFormat = defaultTableDateFormat;
		const {
			formatAmount,
			render: renderCurrency,
			formatDecimal: getCurrencyFormatDecimal,
			getCurrencyConfig,
		} = this.context;

		return data.map( ( row ) => {
			const {
				total_customers: totalCustomers,
				arpu,
				mrr,
				arr,
			} = row.subtotals;
			// @todo How to create this per-report? Can use `w`, `year`, `m` to build time-specific order links
			// we need to know which kind of report this is, and parse the `label` to get this row's date
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
					display: totalCustomers,
					value: Number( totalCustomers ),
				},
				{
					display: renderCurrency( arpu ),
					value: getCurrencyFormatDecimal( arpu ),
				},
				{
					display: formatAmount( mrr ),
					value: getCurrencyFormatDecimal( mrr ),
				},
				{
					display: formatAmount( arr ),
					value: getCurrencyFormatDecimal( arr ),
				},
			];
		} );
	}

	getSummary( totals, totalResults = 0 ) {
		const {
			total_customers: totalCustomers = 0,
			arpu: arpu = 0,
			mrr: mrr = 0,
			arr: arr = 0,
		} = totals;
		const { formatAmount, getCurrencyConfig } = this.context;
		const currency = getCurrencyConfig();
		return [
			{
				label: _n( 'day', 'days', totalResults, 'sos-analytics' ),
				value: formatValue( currency, 'number', totalResults ),
			},
		];
	}

	render() {
		const { advancedFilters, filters, tableData, query } = this.props;

		return (
			<ReportTable
				endpoint="revenue"
				getHeadersContent={ this.getHeadersContent }
				getRowsContent={ this.getRowsContent }
				getSummary={ this.getSummary }
				summaryFields={ summaryFields }
				query={ query }
				tableData={ tableData }
				title={ __( 'Revenue', 'sos-analytics' ) }
				columnPrefsKey="revenue_report_columns"
				filters={ filters }
				advancedFilters={ advancedFilters }
			/>
		);
	}
}

RevenueReportTable.contextType = CurrencyContext;

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
			endpoint: 'revenue',
			query,
			select,
			tableQuery,
			filters,
			advancedFilters,
		} );
		const revenueData = getReportStats( 'revenue', filteredTableQuery );
		const isError = Boolean(
			getReportStatsError( 'revenue', filteredTableQuery )
		);
		const isRequesting = isResolving( 'getReportStats', [
			'revenue',
			filteredTableQuery,
		] );
		return formatProps( isError, isRequesting, tableQuery, revenueData );
	} )
)( Recurrent_revenueReportTable );
