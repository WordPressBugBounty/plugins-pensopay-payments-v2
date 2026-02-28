<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Logger {
	public static ?WC_Logger_Interface $logger = null;

	public static string $log_filename = Pensopay_Payments_V2_Gateway::TEXT_DOMAIN;

	public static function log( string $message ): void {
		if ( ! class_exists( 'WC_Logger' ) ) {
			return;
		}

		if ( is_null( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		$options = Pensopay_Payments_V2_Helper_Utility::get_settings();

		if ( empty( $options ) || ( isset( $options['logging'] ) && ! Pensopay_Payments_V2_Helper_Utility::is_option_enabled( $options['logging'] ) ) ) {
			return;
		}

		$entry = $message;

		self::$logger->debug( $entry, [ 'source' => self::$log_filename ] );
	}
}