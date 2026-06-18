<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Methods_StripeSepa extends Pensopay_Payments_V2_Methods_Abstract {
	public function __construct() {
		$this->id = 'pensopay_gateway_sepa';

		$this->supports = array(
			'products',
			'refunds'
		);

		$this->method_title = __( 'SEPA Direct Debit', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );

		$this->init_form_fields();
		$this->init_settings();
		$this->init_hooks();

		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->method_description = __( 'Accept SEPA Direct Debit payments through pensopay', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );

		add_filter( 'pensopay_payments_facilitator_' . $this->id, array( $this, 'set_facilitator' ) );
		add_filter( 'pensopay_payments_' . $this->id . '_methods', array( $this, 'set_method' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'should_disable_gateway' ) );
	}

	public function init_form_fields() {
		$this->form_fields = apply_filters(
			'pensopay_payments_sepa_settings',
			array(
				'enabled'     => array(
					'title'   => __( 'Enable/Disable', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'label'   => __( 'Enable SEPA Direct Debit', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'type'    => 'checkbox',
					'default' => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'default'     => __( 'SEPA Direct Debit', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'default'     => __( 'Pay with SEPA Direct Debit', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'desc_tip'    => true,
				)
			)
		);
	}

	public function set_facilitator( $payment_method ) {
		return $this->id;
	}

	public function set_method() {
		return array( 'stripe_sepa' );
	}

	public function apply_gateway_icons( $icon, $id ) {
		if ( $id == $this->id ) {
			$icon = $this->create_gateway_icon( 'sepa' );
		}

		return $icon;
	}

	public function should_disable_gateway( $gateways ) {
		if ( isset( $gateways[ $this->id ] ) && self::is_checkout_context() && ( $cart = WC()->cart ) ) {
			$context_currency = strtoupper( get_woocommerce_currency() );

			if ( $context_currency !== 'EUR' ) {
				unset( $gateways[ $this->id ] );
				return $gateways;
			}

			$billingCountry    = WC()->customer ? WC()->customer->get_billing_country() : '';
			$allowed_countries = WC()->countries->get_european_union_countries();

			if ( $billingCountry && ! in_array( $billingCountry, $allowed_countries, true ) ) {
				unset( $gateways[ $this->id ] );
				return $gateways;
			}

			if ( Pensopay_Payments_V2_Helpers_Subscription::cart_has_subscription() ) {
				unset( $gateways[ $this->id ] );
				return $gateways;
			}
		}

		return $gateways;
	}
}
