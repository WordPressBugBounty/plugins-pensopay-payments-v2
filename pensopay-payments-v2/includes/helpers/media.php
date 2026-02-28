<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Helpers_Media {
	public static function get_facilitator_logo( $facilitator ) {
		return 'none.png';
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
			'ideal'                 => 'iDEAL',
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