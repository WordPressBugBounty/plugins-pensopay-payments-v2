<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Methods_Swish extends Pensopay_Payments_V2_Methods_Abstract {
	public function __construct() {
		$this->id = 'pensopay_gateway_swish';

		$this->supports = array(
			'products',
			'refunds'
		);

		$this->method_title = __( 'Swish', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );

		$this->init_form_fields();
		$this->init_settings();
		$this->init_hooks();

		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->method_description = __( 'Accept Swish payments through pensopay', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );

		add_filter( 'pensopay_payments_facilitator_' . $this->id, array( $this, 'set_facilitator' ) );
		add_filter( 'pensopay_payments_' . $this->id . '_methods', array( $this, 'set_method' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'should_disable_gateway' ) );
		add_filter( 'pensopay_payments_request_body', array( $this, 'set_swish_fields' ) );
	}

	public function set_swish_fields( $request ) {
//		if ($request['facilitator'] && $request['facilitator'] == 'swish') {
//			$request['order']['billing_address']['phone'] = $request['order']['billing_address']['phone_number'];
//			$request['order']['shipping_address']['phone'] = $request['order']['shipping_address']['phone_number'];
//		}
		return $request;
	}

	public function init_form_fields() {
		$this->form_fields = apply_filters(
			'pensopay_payments_swish_settings',
			array(
				'enabled'     => array(
					'title'   => __( 'Enable/Disable', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'label'   => __( 'Enable Swish', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'type'    => 'checkbox',
					'default' => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'default'     => __( 'Swish', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'default'     => __( 'Pay with Swish', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
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
		return [ 'swish' ];
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
			$icon = $this->create_gateway_icon( 'swish' );
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
		if ( isset( $gateways[ $this->id ] ) && is_checkout() && ( $cart = WC()->cart ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

				$product = $cart_item['data'];

				$context_currency   = strtoupper( get_woocommerce_currency() );
				$allowed_currencies = array( 'SEK' );

				if ( ! in_array( $context_currency, $allowed_currencies, true ) ) {
					unset( $gateways[ $this->id ] );
				}

				if ( Pensopay_Payments_V2_Helpers_Subscription::cart_has_subscription() ) {
					unset( $gateways[ $this->id ] );

					return $gateways;
				}
			}

		}

		return $gateways;
	}
}