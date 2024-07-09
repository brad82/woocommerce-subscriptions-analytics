<?php

namespace SOS\Analytics;

class Bootstrap extends Abstracts\AbstractSingleton {

	/**
	 * Plugin Version
	 *
	 * @var string Semver
	 */
	public string $version = PLUGIN_VERSION;

	/**
	 * Main plugin entrypoint.
	 */
	public function __construct() {
		if ( is_admin() ) {
			new Admin\Setup();
		}

		Tools\CLI::init();
		Admin\Schedulers\SubscriptionsScheduler::init();

		add_filter( 'woocommerce_admin_rest_controllers', array( $this, 'register_rest_controllers' ) );
		add_filter( 'woocommerce_data_stores', array( $this, 'add_data_stores' ) );

		$this->schedule();

		add_action( 'sos_analytics_generate_snapshot_data', array( Admin\API\Reports\RecurringRevenue\Stats\DataStore::class, 'calculate_current_data' ) );
	}

	/**
	 * Creates Action Scheduler tasks for background operations
	 *
	 * @return void 
	 */
	private function schedule() {
		if ( false === as_has_scheduled_action( 'sos_analytics_generate_snapshot_data' ) ) {
			as_schedule_recurring_action(
				strtotime( 'tomorrow' ),
				apply_filters( 'sos_analytics_generate_snapshot_data_interval', DAY_IN_SECONDS / 4 ),
				'sos_analytics_generate_snapshot_data',
				array(),
				'',
				true
			);
		}
	}

	/**
	 * Register Rest Routes
	 *
	 * @param array $controllers Controller map.
	 * @return array
	 */
	public function register_rest_controllers( array $controllers ): array {
		return array_merge(
			$controllers,
			array(
				Admin\API\Reports\Subscriptions\Stats\Controller::class,
				Admin\API\Reports\RecurringRevenue\Stats\Controller::class,
				Admin\API\Reports\Renewals\Stats\Controller::class,
			)
		);
	}

	/**
	 * Register Data Stores
	 *
	 * @param array $data_stores Data store map.
	 * @return array
	 */
	public function add_data_stores( array $data_stores = array() ): array {
		return array_merge(
			$data_stores,
			array(
				'report-subscriptions-stats' => Admin\API\Reports\Subscriptions\Stats\DataStore::class,
				'report-renewals-stats'      => Admin\API\Reports\Renewals\Stats\DataStore::class,
				'report-subscriptions-stats-recurring-revenue' => Admin\API\Reports\RecurringRevenue\Stats\DataStore::class,
			)
		);
	}
}
