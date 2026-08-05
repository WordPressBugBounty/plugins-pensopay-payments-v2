<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Helpers_Media {
	/**
	 * Logo URL for a payment method, for payments that carry no card brand.
	 *
	 * Rather than keep a second copy of the method-to-asset mapping, ask the gateway for
	 * the icon it already renders at checkout: every method declares its own asset in
	 * apply_gateway_icons() via create_gateway_icon(), which also owns the SVG-then-PNG
	 * fallback and the `pensopay_payments_checkout_gateway_icon_url` filter. So a method
	 * that gets a logo at checkout gets the same one in the order overview, and a new
	 * method needs no change here at all.
	 *
	 * Only an unambiguous single icon is used. The card gateway appends one icon per
	 * configured card brand (see Pensopay_Payments_V2_Methods_Abstract::apply_gateway_icons()),
	 * and picking an arbitrary one of those would claim the order was paid with a brand
	 * we do not actually know, so those resolve to nothing instead.
	 *
	 * @param string $facilitator WooCommerce payment method id.
	 *
	 * @return string Logo URL, or an empty string when the method has no single logo.
	 */
	public static function get_facilitator_logo( $facilitator ) {
		if ( empty( $facilitator ) || ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return '';
		}

		$gateways = WC()->payment_gateways()->payment_gateways();

		if ( ! isset( $gateways[ $facilitator ] ) ) {
			return '';
		}

		return self::extract_single_icon_url( $gateways[ $facilitator ]->get_icon() );
	}

	/**
	 * Pull the asset URL out of rendered gateway icon markup, but only when it names
	 * exactly one asset.
	 *
	 * @param string $icon_html Markup returned by WC_Payment_Gateway::get_icon().
	 *
	 * @return string The single icon URL, or an empty string for none or several.
	 */
	public static function extract_single_icon_url( $icon_html ) {
		if ( empty( $icon_html ) || ! is_string( $icon_html ) ) {
			return '';
		}

		if ( ! preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $icon_html, $matches ) ) {
			return '';
		}

		$urls = array_values( array_unique( $matches[1] ) );

		return count( $urls ) === 1 ? $urls[0] : '';
	}

	/**
	 * Resolve the logo for an order row: the brand the payment reports, or failing that
	 * the payment method it was paid with.
	 *
	 * Anyday and ViaBill are authorized and captured by their own providers, so their
	 * payments never get a `payment_details.brand` the way card payments do, and the
	 * overview was left building `assets/images/cards/.svg` and showing nothing.
	 *
	 * A brand still wins, but only when it names an asset that is actually on disk: the
	 * brand slug is not the asset slug, so e.g. a brand of `anyday` used to build
	 * `cards/anyday.svg` and 404 (the only Anyday asset is `anyday-split.svg`), which is
	 * worse than showing the method's own logo. When the brand resolves to no file we fall
	 * through to the same fallback a brandless payment gets. Every brand that does have an
	 * asset keeps the URL the overview has always built.
	 *
	 * @param string $brand       `payment_details.brand` from the payment object.
	 * @param string $facilitator WooCommerce payment method id.
	 *
	 * @return string Logo URL, or an empty string when nothing resolves.
	 */
	public static function get_transaction_logo_url( $brand, $facilitator ) {
		if ( ! empty( $brand ) ) {
			$brand_logo = self::get_card_asset_url( $brand );

			if ( ! empty( $brand_logo ) ) {
				return $brand_logo;
			}
		}

		return self::get_facilitator_logo( $facilitator );
	}

	/**
	 * URL of a `assets/images/cards/` asset, or an empty string when there is no such file.
	 *
	 * SVG is preferred and PNG accepted, the same order and the same directory
	 * Pensopay_Payments_V2_Methods_Abstract::create_gateway_icon() uses for checkout icons,
	 * so a brand whose asset only ships as a PNG (visaelectron, discovercard, apple-pay)
	 * resolves instead of asking for a `.svg` that was never there.
	 *
	 * @param string $slug Asset base name.
	 *
	 * @return string Asset URL, or an empty string when nothing is on disk.
	 */
	public static function get_card_asset_url( $slug ) {
		if ( empty( $slug ) || ! is_string( $slug ) ) {
			return '';
		}

		$directory = 'assets/images/cards/';

		foreach ( array( 'svg', 'png' ) as $extension ) {
			$file = $directory . $slug . '.' . $extension;

			if ( file_exists( plugin_dir_path( PENSOPAY_PAYMENTS_V2_PLUGIN_FILE ) . $file ) ) {
				return plugin_dir_url( PENSOPAY_PAYMENTS_V2_PLUGIN_FILE ) . $file;
			}
		}

		return '';
	}

	public static function get_card_icons() {
		return array(
			'apple-pay'             => 'Apple Pay',
			'googlepay'             => 'Google Pay',
			'dankort'               => 'Dankort',
			'visa'                  => 'Visa',
			'visaelectron'          => 'Visa Electron',
			'visa-verified'         => 'Verified by Visa',
			'mastercard'            => 'Mastercard',
			'mastercard-securecode' => 'Mastercard SecureCode',
			'mastercard-idcheck'    => 'Mastercard ID Check',
			'maestro'               => 'Maestro',
			'jcb'                   => 'JCB',
			'americanexpress'       => 'American Express',
			'diners'                => 'Diner\'s Club',
			'discovercard'          => 'Discover Card',
			'viabill'               => 'ViaBill',
			'paypal'                => 'Paypal',
			'danskebank'            => 'Danske Bank',
			'nordea'                => 'Nordea',
			'mobilepay'             => 'MobilePay',
			'forbrugsforeningen'    => 'Forbrugsforeningen',
			'ideal'                 => 'iDEAL | Wero',
			'unionpay'              => 'UnionPay',
			'sofort'                => 'Sofort',
			'cirrus'                => 'Cirrus',
			'klarna'                => 'Klarna',
			'bankaxess'             => 'BankAxess',
			'vipps'                 => 'Vipps',
			'swish'                 => 'Swish',
			'bitcoin'               => 'Bitcoin',
			'trustly'               => 'Trustly',
			'paysafecard'           => 'Paysafe Card',
		);
	}
}