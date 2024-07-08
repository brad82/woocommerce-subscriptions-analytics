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
import ReportSummary from '../../components/report-summary';
import RenewalsReportTable from './table';

export default class RenewalsReport extends Component {
	render() {
		const { path, query } = this.props;

		return (
			<Fragment>
				<ReportFilters
					query={ query }
					path={ path }
					report="renewals"
					filters={ filters }
					advancedFilters={ advancedFilters }
				/>
				<ReportSummary
					charts={ charts }
					endpoint="renewals"
					query={ query }
					selectedChart={ getSelectedChart( query.chart, charts ) }
					filters={ filters }
					advancedFilters={ advancedFilters }
				/>
				<RenewalsReportTable
					query={ query }
					filters={ filters }
					advancedFilters={ advancedFilters }
				/>
			</Fragment>
		);
	}
}

RenewalsReport.propTypes = {
	path: PropTypes.string.isRequired,
	query: PropTypes.object.isRequired,
};
