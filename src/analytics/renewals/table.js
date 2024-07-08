/**
 * External dependencies
 */
import { __, _n } from '@wordpress/i18n';
import { Component } from '@wordpress/element';
import { withSelect } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { get, memoize } from 'lodash';
import { Date } from '@woocommerce/components';

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
import { CurrencyContext } from '../../lib/currency-context';
import ReportTable from '../../components/report-table';

const EMPTY_ARRAY = [];

const summaryFields = [ 'date', 'renewals' ];

class RenewalsReportTable extends Component {
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
				label: __( 'Projected Revenue', 'sos-analytics' ),
				key: 'revenue',
				required: false,
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'Renewals', 'sos-analytics' ),
				key: 'renewals_count',
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
			const { revenue: revenue, renewal_count: totalRenewals } =
				row.subtotals;
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
					display: renderCurrency( revenue ),
					value: getCurrencyFormatDecimal( revenue ),
				},
				{
					display: parseInt( totalRenewals ),
					value: totalRenewals,
				},
			];
		} );
	}

	getSummary( totals, totalResults = 0 ) {
		const { revenue, renewal_count } = totals;
		const { formatAmount, getCurrencyConfig } = this.context;
		const currency = getCurrencyConfig();
		return [
			{
				label: __( 'Projected Revenue', 'sos-analytics' ),
				value: formatAmount( currency, 'number', revenue ),
			},
			{
				label: __( 'Total Renewals', 'sos-analytics' ),
				value: parseInt( renewal_count ),
			},
		];
	}

	render() {
		const { advancedFilters, filters, tableData, query } = this.props;

		return (
			<ReportTable
				endpoint="renewals"
				getHeadersContent={ this.getHeadersContent }
				getRowsContent={ this.getRowsContent }
				getSummary={ this.getSummary }
				summaryFields={ summaryFields }
				query={ query }
				tableData={ tableData }
				title={ __( 'Upcoming Renewals', 'sos-analytics' ) }
				columnPrefsKey="renewals_report_columns"
				filters={ filters }
				advancedFilters={ advancedFilters }
			/>
		);
	}
}

RenewalsReportTable.contextType = CurrencyContext;

/**
 * Memoized props object formatting function.
 *
 * @param {boolean} isError
 * @param {boolean} isRequesting
 * @param {Object}  tableQuery
 * @param {Object}  renewalsData
 * @return {Object} formatted tableData prop
 */
const formatProps = memoize(
	( isError, isRequesting, tableQuery, renewalsData ) => ( {
		tableData: {
			items: {
				data: get( renewalsData, [ 'data', 'intervals' ], EMPTY_ARRAY ),
				totalResults: get( renewalsData, [ 'totalResults' ], 0 ),
			},
			isError,
			isRequesting,
			query: tableQuery,
		},
	} ),
	( isError, isRequesting, tableQuery, renewalsData ) =>
		[
			isError,
			isRequesting,
			stringify( tableQuery ),
			get( renewalsData, [ 'totalResults' ], 0 ),
			get( renewalsData, [ 'data', 'intervals' ], EMPTY_ARRAY ).length,
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
			endpoint: 'renewals',
			query,
			select,
			tableQuery,
			filters,
			advancedFilters,
		} );
		const renewalsData = getReportStats( 'renewals', filteredTableQuery );
		const isError = Boolean(
			getReportStatsError( 'renewals', filteredTableQuery )
		);
		const isRequesting = isResolving( 'getReportStats', [
			'renewals',
			filteredTableQuery,
		] );
		return formatProps( isError, isRequesting, tableQuery, renewalsData );
	} )
)( RenewalsReportTable );
