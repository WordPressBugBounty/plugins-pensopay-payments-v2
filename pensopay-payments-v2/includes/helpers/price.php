<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Helpers_Price {
	/**
	 * Convert price to cents
	 */
	public static function multiply_price( $price, $currency = false ) {
		if ( $currency && self::is_currency_using_decimals( $currency ) ) {
			return number_format( $price * 100, 0, '', '' );
		}

		return $price;
	}

	/**
	 * Multiplies a custom formatted price based on the WooCommerce decimal- and thousand separators
	 */
	public static function price_custom_to_multiplied( $price, $currency ) {
		$decimal_separator  = get_option( 'woocommerce_price_decimal_sep' );
		$thousand_separator = get_option( 'woocommerce_price_thousand_sep' );

		$price = str_replace( [ $thousand_separator, $decimal_separator ], [ '', '.' ], $price );

		return self::multiply_price( $price, $currency );
	}

	public static function is_currency_using_decimals( $currency ): bool {
		$non_decimal_currencies = [
			'BIF',
			'CLP',
			'DJF',
			'GNF',
			'JPY',
			'KMF',
			'KRW',
			'PYG',
			'RWF',
			'UGX',
			'UYI',
			'VND',
			'VUV',
			'XAF',
			'XOF',
			'XPF',
		];

		return ! in_array( strtoupper( $currency ), $non_decimal_currencies, true );
	}

	/**
	 * Normalize price
	 *
	 * @param $price
	 *
	 * @return float|int
	 */
	public static function normalize_price( $price ) {
		return $price / 100;
	}
}