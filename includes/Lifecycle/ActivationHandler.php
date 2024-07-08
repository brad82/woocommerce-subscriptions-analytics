<?php
/**
 * API\Reports\Subscriptions\Stats\DataStore class file.
 *
 * @package SOS\Analytics
 */

namespace SOS\Analytics\Lifecycle;

use function wc_get_container;
use Automattic\WooCommerce\Internal\Utilities\DatabaseUtil;

class ActivationHandler {

	/**
	 * Activation hook
	 *
	 * @return void
	 */
	public static function on_activate(): void {
		add_action( 'admin_notices', array( static::class, 'missing_wc_notice' ) );

		self::create_tables();
	}

	/**
	 * Adds admin notice if WooCommerce is not installed
	 *
	 * @since 0.1.0
	 * @return void
	 **/
	public static function missing_wc_notice(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}

		/* translators: %s WC download URL link. */
		echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Woocommerce Subscriptions Analytics requires WooCommerce to be installed and active. You can download %s here.', 'sos-analytics' ), '<a href="https://woo.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
	}

	/**
	 * Create tables
	 *
	 * @return void
	 **/
	public static function create_tables(): void {
		global $wpdb;

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$max_index_length = wc_get_container()->get( DatabaseUtil::class )->get_max_index_length();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = "{$wpdb->prefix}wc_subscription_stats_rollup";
		maybe_create_table(
			$table,
			"
			CREATE TABLE `{$table}` (
				`date` DATE NOT NULL,
				`count_active` INT  unsigned DEFAULT '0',
				`count_active_trials` INT unsigned DEFAULT '0',
				`count_total_customers` INT unsigned DEFAULT '0',
				`calculated_arpu` INT unsigned DEFAULT '0',
				`calculated_mrr` INT unsigned DEFAULT '0',
				`calculated_arr` INT unsigned DEFAULT '0',
				`updated_at` TIMESTAMP  DEFAULT (CURRENT_TIMESTAMP),
				`created_at` TIMESTAMP  DEFAULT (CURRENT_TIMESTAMP),
				UNIQUE KEY `log_date` (`date`) USING BTREE,
				PRIMARY KEY (date)
			);
		"
		);

		$table = "{$wpdb->prefix}wc_subscription_stats";
		maybe_create_table(
			$table,
			"
			CREATE TABLE {$wpdb->prefix}wc_subscription_stats (
				order_id bigint(20) unsigned NOT NULL,
				subscription_id bigint(20) unsigned NOT NULL,
				parent_id bigint(20) unsigned DEFAULT 0 NOT NULL,
				date_created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				date_created_gmt datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				date_paid datetime DEFAULT '0000-00-00 00:00:00',
				date_completed datetime DEFAULT '0000-00-00 00:00:00',
				num_items_sold int(11) DEFAULT 0 NOT NULL,
				total_sales double DEFAULT 0 NOT NULL,
				tax_total double DEFAULT 0 NOT NULL,
				shipping_total double DEFAULT 0 NOT NULL,
				net_total double DEFAULT 0 NOT NULL,
				returning_customer tinyint(1) DEFAULT NULL,
				status varchar(200) NOT NULL,
				customer_id bigint(20) unsigned NOT NULL,
				KEY date_created (date_created),
				KEY subscription_id (subscription_id),
				KEY customer_id (customer_id),
				KEY status (status({$max_index_length})),
				PRIMARY KEY (order_id, status)
		) $collate;
		"
		);
	}
}
