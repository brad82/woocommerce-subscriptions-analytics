<?php

namespace SOS\Analytics;

use DateInterval;
use DateTimeImmutable;

/**
 * Cli
 *
 * @package SOS\Analytics
 */
class CLI {

	/**
	 * Register CLI Tools
	 *
	 * @return void
	 **/
	public static function register(): void {
		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		// phpcs:ignore
		\WP_CLI::add_command( 'wc_stats', self::class );
	}

	/**
	 * Seeds database with demo order history rollup values
	 *
	 * @return void
	 */
	public function demo_data(): void {
		global $wpdb;

		$table = "{$wpdb->prefix}wc_subscription_stats_rollup";

		$today = new DateTimeImmutable();

		$since = $today->sub( DateInterval::createFromDateString( '1 month' ) );

		$query =
			"INSERT INTO {$table} (
				`date`,
				`count_active`,
				`count_on_hold`,
				`count_pending_cancellation`,
				`count_cancelled`,
				`count_expired`,
				`count_refunds`,
				`count_resubscribes`
			) VALUES (
				%s,				
				%d,				
				%d,
				%d,
				%d,
				%d,
				%d,
				%d
			)
			";

		while ( $since < $today ) {

			$statement = $wpdb->prepare(
				$query,
				array(
					$since->format( 'Y-m-d' ),
					rand( 0, 100 ),
					rand( 0, 10 ),
					rand( 0, 10 ),
					rand( 0, 10 ),
					rand( 0, 10 ),
					rand( 0, 10 ),
					rand( 0, 10 ),
				)
			);

			$wpdb->query( $statement );

			$since = $since->add( DateInterval::createFromDateString( '1 Day' ) );
		}

		echo "Seed $table complete";
	}
}
