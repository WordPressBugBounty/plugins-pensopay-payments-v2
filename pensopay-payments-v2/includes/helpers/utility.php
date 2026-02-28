<?php

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

class Pensopay_Payments_V2_Helper_Utility {

	public static function get_settings() {
		return get_option( Pensopay_Payments_V2_Gateway::SETTINGS_DOMAIN, false );
	}

	public static function is_testmode(): bool {
		$options = self::get_settings();

		if ( ! $options ) {
			return false;
		}

		return isset( $options['testmode'] ) && $options['testmode'] === 'yes';
	}

	/**
	 * Convert wordpress yes / no options to boolean
	 */
	public static function is_option_enabled( $value ): bool {
		return $value === 'yes';
	}

	/**
	 * Checks if High Performance Order Storage is enabled
	 */
	public static function is_HPOS_enabled(): bool {
		return wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled();
	}

	public static function is_browser( $browser ): bool {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}

		$u_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		$name    = 'Unknown';

		if ( false !== stripos( $u_agent, "MSIE" ) && false === stripos( $u_agent, "Opera" ) ) {
			$name = "MSIE";
		} elseif ( false !== stripos( $u_agent, "Firefox" ) ) {
			$name = "Firefox";
		} elseif ( false !== stripos( $u_agent, "Chrome" ) ) {
			$name = "Chrome";
		} elseif ( false !== stripos( $u_agent, "Safari" ) ) {
			$name = "Safari";
		} elseif ( false !== stripos( $u_agent, "Opera" ) ) {
			$name = "Opera";
		} elseif ( false !== stripos( $u_agent, "Netscape" ) ) {
			$name = "Netscape";
		}

		return strtolower( $name ) === strtolower( $browser );
	}

	public static function is_url( $url ): bool {
		return ! filter_var( $url, FILTER_VALIDATE_URL ) === false;
	}

	/**
	 * Inserts a new key/value after the key in the array.
	 *
	 * @param string $needle The array key to insert the element after
	 * @param array $haystack An array to insert the element into
	 * @param string $new_key The key to insert
	 * @param mixed $new_value An value to insert
	 *
	 * @return array The new array if the $needle key exists, otherwise an unmodified $haystack
	 */
	public static function array_insert_after( $needle, $haystack, $new_key, $new_value ): array {

		if ( array_key_exists( $needle, $haystack ) ) {

			$new_array = [];

			foreach ( $haystack as $key => $value ) {

				$new_array[ $key ] = $value;

				if ( $key === $needle ) {
					$new_array[ $new_key ] = $new_value;
				}
			}

			return $new_array;
		}

		return $haystack;
	}

	public static function get_setting( $key, $default = null ) {
		return Pensopay_Payments_V2_Methods_Creditcard::get_instance()->s( $key, $default );
	}

	public static function create_random_string( $n ): string {
		$characters    = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$random_string = '';

		for ( $i = 0; $i < $n; $i ++ ) {
			try {
				$index         = random_int( 0, strlen( $characters ) - 1 );
				$random_string .= $characters[ $index ];
			} catch ( Exception $e ) {
				$random_string = substr( time(), - $n );
			}
		}

		return $random_string;
	}
}