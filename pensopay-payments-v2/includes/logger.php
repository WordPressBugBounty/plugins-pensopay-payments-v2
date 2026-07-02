<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Logger {
	public static ?WC_Logger_Interface $logger = null;

	public static string $log_filename = Pensopay_Payments_V2_Gateway::TEXT_DOMAIN;

	/** Debug-level log; respects the "logging" setting (off by default). */
	public static function log( string $message ): void {
		$logger = self::get_logger();
		if ( null === $logger ) {
			return;
		}

		$options = Pensopay_Payments_V2_Helper_Utility::get_settings();

		if ( empty( $options ) || ( isset( $options['logging'] ) && ! Pensopay_Payments_V2_Helper_Utility::is_option_enabled( $options['logging'] ) ) ) {
			return;
		}

		$logger->debug( $message, [ 'source' => self::$log_filename ] );
	}

	/** error/warning are always written, regardless of the debug "logging" toggle. */
	public static function error( string $message ): void {
		$logger = self::get_logger();
		if ( null === $logger ) {
			return;
		}
		$logger->error( $message, [ 'source' => self::$log_filename ] );
	}

	public static function warning( string $message ): void {
		$logger = self::get_logger();
		if ( null === $logger ) {
			return;
		}
		$logger->warning( $message, [ 'source' => self::$log_filename ] );
	}

	private static function get_logger(): ?WC_Logger_Interface {
		if ( ! function_exists( 'wc_get_logger' ) && ! class_exists( 'WC_Logger' ) ) {
			return null;
		}

		if ( is_null( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		return self::$logger;
	}
}