<?php

namespace SOS\Analytics\Admin;

use Automattic\WooCommerce\Admin\PageController;

use const SOS\Analytics\PLUGIN_FILE;

/**
 * SOS\Analytics Setup Class
 */
class Setup {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'woocommerce_analytics_report_menu_items', array( $this, 'register_page' ) );
	}

	/**
	 * Load all necessary dependencies.
	 *
	 * @since 1.0.0
	 */
	public function register_scripts() {
		if (
			! method_exists( 'Automattic\WooCommerce\Admin\PageController', 'is_admin_or_embed_page' ) ||
			! PageController::is_admin_or_embed_page()
		) {
			return;
		}

		$script_path       = '/build/index.js';
		$script_asset_path = dirname( PLUGIN_FILE ) . '/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => filemtime( $script_path ),
			);

		$script_url = plugins_url( $script_path, PLUGIN_FILE );

		wp_register_script(
			'woocommerce-subscriptions-analytics',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_register_style(
			'woocommerce-subscriptions-analytics',
			plugins_url( '/build/index.css', PLUGIN_FILE ),
			array(),
			filemtime( dirname( PLUGIN_FILE ) . '/build/index.css' )
		);

		wp_enqueue_script( 'woocommerce-subscriptions-analytics' );
		wp_enqueue_style( 'woocommerce-subscriptions-analytics' );
	}

	/**
	 * Register page in wc-admin.
	 *
	 * @param array $report_pages Report pages amp.
	 * @since 1.0.0
	 */
	public function register_page( array $report_pages ): array {
		return array_merge(
			$report_pages,
			array(
				array(
					'id'     => 'analytics-recurring-revenue',
					'title'  => __( 'MRR / ARR', 'sos-analytics' ),
					'parent' => 'woocommerce-analytics',
					'path'   => '/analytics/recurring-revenue',
				),
				array(
					'id'     => 'analytics-subscriptions',
					'title'  => __( 'Subscriptions', 'sos-analytics' ),
					'parent' => 'woocommerce-analytics',
					'path'   => '/analytics/subscriptions',
				),
				array(
					'id'     => 'analytics-renewals',
					'title'  => __( 'Renewals', 'sos-analytics' ),
					'parent' => 'woocommerce-analytics',
					'path'   => '/analytics/renewals',
				),
			)
		);
	}

	/**
	 * Adds the plugin routes to the default WooCommerce route map
	 *
	 * @param array $routes Route Map.
	 * @return array Adjusted route map.
	 */
	public function advertise_report_routes( array $routes ): array {
		$routes[] = array(
			'slug'        => 'subscriptions/stats',
			'description' => __( 'Stats about Subscriptions' ),
		);

		return $routes;
	}
}
