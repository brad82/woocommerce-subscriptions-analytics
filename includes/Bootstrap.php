<?php

namespace SOS\Analytics;

class Bootstrap {

	/**
	 * Plugin Version
	 *
	 * @var string Semver
	 */
	public string $version = '0.1.0';
	/**
	 * This class instance.
	 *
	 * @var \woocommerce_subscriptions_analytics single instance of this class.
	 */
	private static $instance;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( is_admin() ) {
			new Setup();
		}

		CLI::register();

		Admin\Schedulers\SubscriptionsScheduler::init();

		add_filter( 'woocommerce_admin_rest_controllers', array( $this, 'register_rest_controllers' ) );
		add_filter( 'woocommerce_data_stores', array( $this, 'add_data_stores' ) );
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'sos-analytics' ), $this->version );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'sos-analytics' ), $this->version );
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
				'report-subscriptions-stats' =>	Admin\API\Reports\Subscriptions\Stats\DataStore::class,
				'report-renewals-stats'      =>	Admin\API\Reports\Renewals\Stats\DataStore::class,
				'report-subscriptions-stats-recurring-revenue' => Admin\API\Reports\RecurringRevenue\Stats\DataStore::class,
			)
		);
	}

	/**
	 * Gets the main instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @return \Woocommerce_Subscriptions_Analytics
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}