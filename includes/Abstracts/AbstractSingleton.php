<?php

namespace SOS\Analytics\Abstracts;

use function wc_doing_it_wrong;

abstract class AbstractSingleton {
	/**
	 * This class instance.
	 *
	 * @var static single instance of this class.
	 */
	protected static $instance;

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
	 * Gets the main instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @return static
	 */
	public static function instance() {

		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}
}
