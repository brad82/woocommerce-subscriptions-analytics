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
