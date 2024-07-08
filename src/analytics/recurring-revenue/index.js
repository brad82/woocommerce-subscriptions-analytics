/**
 * External dependencies
 */
import { Component, Fragment } from '@wordpress/element';
import PropTypes from 'prop-types';
import { ReportFilters } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { advancedFilters, charts, filters } from './config';
import getSelectedChart from '../../lib/get-selected-chart';
import ReportChart from '../../components/report-chart';
import ReportSummary from '../../components/report-summary';
// import RevenueReportTable from './table';

export default class RecurrentRevenueReport extends Component {
	render() {
		const { path, query } = this.props;

		return (
			<Fragment>
				<ReportSummary
					charts={ charts }
					endpoint="recurring-revenue"
					query={ query }
					selectedChart={ getSelectedChart( query.chart, charts ) }
					filters={ filters }
					advancedFilters={ advancedFilters }
				/>
				<ReportChart
					charts={ charts }
					endpoint="recurring-revenue"
					path={ path }
					query={ query }
					selectedChart={ getSelectedChart( query.chart, charts ) }
					filters={ filters }
					advancedFilters={ advancedFilters }
				/>
			</Fragment>
		);
	}
}

RecurrentRevenueReport.propTypes = {
	path: PropTypes.string.isRequired,
	query: PropTypes.object.isRequired,
};
