<?php
/**
 * Order syncing related functions and actions.
 */

namespace SOS\Analytics\Admin\Schedulers;

defined( 'ABSPATH' ) || exit;

use WC_Subscription;
use SOS\Analytics\Admin\API\Reports\Subscriptions\Stats\DataStore as SubscriptionsStatsDataStore;
use SOS\Analytics\Admin\API\Reports\Renewals\Stats\DataStore as RenewalsStatsDataStore;
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
	final public static function init() {
		add_action( 'woocommerce_subscription_status_updated', array( static::class, 'status_changed' ), 10, 2 );
		add_action( 'woocommerce_subscriptions_switch_completed', array( static::class, 'subscription_switched' ), 10 );
		add_action( 'woocommerce_scheduled_subscription_trial_end', array( static::class, 'trial_ended_naturally' ), 10 );
		add_action( 'woocommerce_subscription_date_updated', array( static::class, 'subscripion_dates_changed' ), 10, 2 );

		SubscriptionsStatsDataStore::init();
	}

	/**
	 * Imports a single order or refund to update lookup tables for.
	 *
	 * @param WC_Subscription $subscription Subscription that has been updated.
	 * @param string          $new_status Subscription Status.
	 * @return void
	 */
	public static function status_changed( WC_Subscription $subscription, string $new_status ) {
		self::guard_import( $subscription, $new_status );
		ReportsCache::invalidate();
	}

	/**
	 * Imports a single order or refund to update lookup tables for.
	 *
	 * @param int|string $subscription_id Subscription that has been updated.
	 * @return void
	 */
	public static function trial_ended_naturally( $subscription_id ) {
		$subscription = new WC_Subscription( $subscription_id );
		self::guard_import( $subscription, 'trial-expired' );
		ReportsCache::invalidate();
	}

	/**
	 * Imports a single order or refund to update lookup tables for.
	 * If an error is encountered in one of the updates, a retry action is scheduled.
	 *
	 * @param WC_Subscription $subscription Subscription that has been updated.
	 * @return void
	 */
	public static function subscription_switched( WC_Subscription $subscription ) {
		self::guard_import( $subscription, 'switched' );
		ReportsCache::invalidate();
	}

	/**
	 * Ensures the next_payment date stays in sync
	 *
	 * @param WC_Subscription $subscription Subscription that has been updated.
	 * @param string $date_type object date key.
	 * @return void
	 */
	public static function subscripion_dates_changed( WC_Subscription $subscription, string $date_type ) {
		if ( $date_type != 'next_payment' ) {
			return;
		}
		
		RenewalsStatsDataStore::update($subscription, $date_type);
		ReportsCache::invalidate();
	}

	/**
	 * Checks if subscription is valid
	 *
	 * @param mixed  $subscription Subscription.
	 * @param string $new_status Status.
	 * @return int|bool|void
	 */
	protected static function guard_import( $subscription, string $new_status ) {
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
