<?php

/**
 * Plugin Name: Woocommerce Subscriptions Analytics
 * Description: Adds extended analytics to WooCommerce for Subscriptions
 * Version: 0.1.0
 * Author: Supreme Online Solutions
 * Author URI: https://supremeonline.solutions
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

register_activation_hook( __FILE__, array( SOS\Analytics\Lifecycle\ActivationHandler::class, 'on_activate' ) );

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

	SOS\Analytics\Bootstrap::instance();
}

add_action( 'plugins_loaded', 'woocommerce_subscriptions_analytics_init', 10 );
