<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Methods_VippsEpayment extends Pensopay_Payments_V2_Methods_Abstract {
	public function __construct() {
		$this->id = 'pensopay_gateway_vippsepayment';

		$this->supports = array(
			'products',
			'refunds'
		);

		$this->method_title = __( 'Vipps MobilePay', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );

		$this->init_form_fields();
		$this->init_settings();
		$this->init_hooks();

		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->method_description = __( 'Accept Vipps MobilePay payments through pensopay', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );

		add_filter( 'pensopay_payments_facilitator_' . $this->id, array( $this, 'set_facilitator' ) );
		add_filter( 'pensopay_payments_' . $this->id . '_methods', array( $this, 'set_method' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'should_disable_gateway' ) );
	}

	public function init_form_fields() {
		$this->form_fields = apply_filters(
			'pensopay_payments_vippsepayment_settings',
			array(
				'enabled'     => array(
					'title'   => __( 'Enable/Disable', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'label'   => __( 'Enable Vipps MobilePay', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'type'    => 'checkbox',
					'default' => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'default'     => __( 'Vipps MobilePay', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'default'     => __( 'Pay with Vipps MobilePay', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'desc_tip'    => true,
				)
			)
		);
	}

	/**
	 * Set facilitator
	 *
	 * @param $payment_method
	 *
	 * @return string
	 */
	public function set_facilitator( $payment_method ) {
		return $this->id;
	}

	/**
	 * Set payment method
	 *
	 * @return array
	 */
	public function set_method() {
		return [ 'vippsepayment' ];
	}

	/**
	 * Add icons to checkout
	 *
	 * @param $icon
	 * @param $id
	 *
	 * @return string
	 */
	public function apply_gateway_icons( $icon, $id ) {
		if ( $id == $this->id ) {
			$icon = $this->create_gateway_icon( 'vipps' );
		}

		return $icon;
	}

	/**
	 * Determine if gateway should be disabled
	 *
	 * @param $gateways
	 *
	 * @return array
	 */
	public function should_disable_gateway( $gateways ) {
		if ( isset( $gateways[ $this->id ] ) && self::is_checkout_context() && WC()->cart ) {
			$context_currency   = strtoupper( get_woocommerce_currency() );
			$allowed_currencies = array( 'NOK', 'DKK', 'EUR' );

			if ( ! in_array( $context_currency, $allowed_currencies, true ) ) {
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
