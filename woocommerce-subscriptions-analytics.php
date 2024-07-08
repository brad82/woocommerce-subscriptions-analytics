<?php

/**
 * Plugin Name: Woocommerce Subscriptions Analytics
 * Version: 0.1.0
 * Author: The WordPress Contributors
 * Author URI: https://woo.com
 * Text Domain: woocommerce-subscriptions-analytics
 * Domain Path: /languages
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MAIN_PLUGIN_FILE' ) ) {
	define( 'MAIN_PLUGIN_FILE', __FILE__ );
}

require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload_packages.php';

use SOS\Analytics\CLI;
use SOS\Analytics\Admin\Setup;
use SOS\Analytics\Lifecycle\ActivationHandler;

// phpcs:disable WordPress.Files.FileName

register_activation_hook( __FILE__, array( ActivationHandler::class, 'on_activate' ) );

if ( ! class_exists( 'Woocommerce_Subscriptions_Analytics' ) ) :
	/**
	 * The Woocommerce_Subscriptions_Analytics class.
	 */
	class Woocommerce_Subscriptions_Analytics {

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

			SOS\Analytics\Admin\Schedulers\SubscriptionsScheduler::init();

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
					\SOS\Analytics\Admin\API\Reports\Subscriptions\Stats\Controller::class,
					\SOS\Analytics\Admin\API\Reports\RecurringRevenue\Stats\Controller::class,
					\SOS\Analytics\Admin\API\Reports\Renewals\Stats\Controller::class,
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
					'report-subscriptions-stats' => \SOS\Analytics\Admin\API\Reports\Subscriptions\Stats\DataStore::class,
					'report-renewals-stats'      => \SOS\Analytics\Admin\API\Reports\Renewals\Stats\DataStore::class,
					'report-subscriptions-stats-recurring-revenue'      => \SOS\Analytics\Admin\API\Reports\RecurringRevenue\Stats\DataStore::class,
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

endif;

add_action( 'plugins_loaded', 'woocommerce_subscriptions_analytics_init', 10 );

/**
 * Initialize the plugin.
 *
 * @since 0.1.0
 */
function woocommerce_subscriptions_analytics_init() {
	load_plugin_textdomain( 'woocommerce_subscriptions_analytics', false, plugin_basename( __DIR__ ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	Woocommerce_Subscriptions_Analytics::instance();
}
