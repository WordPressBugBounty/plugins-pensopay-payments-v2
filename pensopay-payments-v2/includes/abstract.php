<?php

/**
 * Abstract class to cover basic repeating functionality
 */
abstract class Pensopay_Payments_V2_Abstract {

	protected static $instances;

	protected function __construct() {
		$this->hooks();
	}

	abstract public function hooks();

	public static function get_instance() {
		$class = static::class;

		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new $class;
		}

		return self::$instances[ $class ];
	}

	private function __clone() {
	}

}
