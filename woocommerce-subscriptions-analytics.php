<?php
/**
 * Plugin Name: Woocommerce Subscriptions Analytics
 * Description: Adds extended analytics to WooCommerce for Subscriptions
 * Version: 0.1.0
 * Author: Supreme Online Solutions
 * Author URI: https://supremeonline.solutions
 * Text Domain: sos-analytics
 * Domain Path: /languages
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package extension
 */
namespace SOS\Analytics;

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload_packages.php';

const PLUGIN_VERSION = '0.1.0';
const PLUGIN_DIR = __DIR__;
const PLUGIN_FILE = __FILE__;

register_activation_hook( __FILE__, array( Lifecycle\ActivationHandler::class, 'on_activate' ) );

/**
 * Initialize the plugin.
 *
 * @since 0.1.0
 */
function sos_analytics_init() {
	load_plugin_textdomain( 'sos-analytics', false, plugin_basename( __DIR__ ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	Bootstrap::instance();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\sos_analytics_init', 10 );
