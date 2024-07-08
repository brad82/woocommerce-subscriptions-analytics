<?php
/**
 * Order syncing related functions and actions.
 */

namespace SOS\Analytics\Admin\Schedulers;

defined( 'ABSPATH' ) || exit;

use WC_Subscription;
use SOS\Analytics\Admin\API\Reports\Subscriptions\Stats\DataStore as SubscriptionsStatsDataStore;
use Automattic\WooCommerce\Admin\API\Reports\Cache as ReportsCache;

/**
 * SubscriptionsScheduler Class.
 */
class SubscriptionsScheduler {
	/**
	 * Slug to identify the scheduler.
	 *
	 * @var string
	 */
	public static $name = 'subscriptions';

	/**
	 * Attach order lookup update hooks.
	 *
	 * @internal
	 */
	public static function init() {
		add_action( 'woocommerce_subscription_status_updated', array( static::class, 'status_changed' ), 10, 2 );
		add_action( 'woocommerce_subscriptions_switch_completed', array( static::class, 'subscription_switched' ), 10 );
		add_action( 'woocommerce_scheduled_subscription_trial_end', array( static::class, 'trial_ended_naturally' ), 10 );

		SubscriptionsStatsDataStore::init();
	}

	/**
	 * Imports a single order or refund to update lookup tables for.
	 *
	 * @param WC_Subscription $subscription Subscription that has been updated.
	 * @param string          $new_status Subscriptipn Status
	 * @return void
	 */
	public static function status_changed( WC_Subscription $subscription, string $new_status ) {
		self::guard_import( $subscription, $new_status );
		ReportsCache::invalidate();
	}

	/**
	 * Imports a single order or refund to update lookup tables for.
	 *
	 * @param WC_Subscription $subscription Subscription that has been updated.
	 * @param string          $new_status Subscriptipn Status
	 * @return void
	 */
	public static function trial_ended_naturally( WC_Subscription $subscription_id ) {
		$subscription = WC_Subscription( $subscription_id );
		self::guard_import( $subscription, 'trial-expired' );
		ReportsCache::invalidate();
	}

	/**
	 * Imports a single order or refund to update lookup tables for.
	 * If an error is encountered in one of the updates, a retry action is scheduled.
	 *
	 * @param WC_Subscription $subscription Subscription that has been updated.
	 * @param string          $new_status Subscriptipn Status
	 * @return void
	 */
	public static function subscription_switched( WC_Subscription $subscription ) {
		self::guard_import( $subscription, 'switched' );
		ReportsCache::invalidate();
	}

	protected static function guard_import( WC_Subscription $subscription, string $new_status ) {
		// If the order isn't found for some reason, skip the sync.
		if ( ! $subscription ) {
			return;
		}

		// If the order has no id or date created, skip sync.
		if ( ! $subscription->get_id() || ! $subscription->get_date_created() ) {
			return;
		}

		return SubscriptionsStatsDataStore::sync_subscription( $subscription, $new_status );
	}
}
