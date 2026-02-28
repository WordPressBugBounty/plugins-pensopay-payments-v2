<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Methods_Creditcard extends Pensopay_Payments_V2_Methods_Abstract {
	public function __construct() {
		$this->id           = 'pensopay_gateway';
		$this->method_title = __( 'pensopay credit card', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );
		$this->has_fields   = true;

		$this->supports = array(
			'products',
			'refunds',
			'pre-orders',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);

		// Load form fields and settings
		$this->init_form_fields();
		$this->init_settings();
		$this->init_hooks();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		self::$instances[ static::class ] = $this;
	}

	public function apply_gateway_icons( $icon, $id ) {
		if ( $id == $this->id ) {
			$icon = '';

			$options = Pensopay_Payments_V2_Helper_Utility::get_settings();
			$icons   = isset( $options['icons'] ) ? $options['icons'] : [];

			if ( ! empty( $icons ) ) {

				foreach ( $icons as $key => $item ) {
					$icon .= $this->create_gateway_icon( $item );
				}
			}
		}

		return $icon;
	}

	public function init_form_fields() {
		require_once PPV2_PATH . 'includes/admin/pensopay-settings.php';
		$this->form_fields = Pensopay_Payments_V2_Settings::get_settings();
	}

	/**
	 * Init hooks
	 */
	public function init_hooks() {
		parent::init_hooks();

		add_action( 'rest_api_init', array( $this, 'register_rest_api' ) );
		add_action( 'woocommerce_api_pensopay', array( $this, 'handle_callback' ) );
		add_filter( 'pensopay_payments_facilitator_pensopay_gateway', array( $this, 'set_default_facilitator' ) );
		add_filter( 'pensopay_payments_' . $this->id . '_methods', array( $this, 'set_default_method' ) );

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [
			$this,
			'scheduled_subscription_payment'
		], 10, 2 );
	}

	public function scheduled_subscription_payment( $amount_to_charge, WC_Order $order ) {
		if ( ( $order->get_payment_method() === $this->id ) && $order->needs_payment() ) {
			$transaction = Pensopay_Api_Transaction::from_order( $order );
			$transaction->authorize( $order->get_id(), (int) Pensopay_Payments_V2_Helpers_Price::multiply_price( $amount_to_charge, $order->get_currency() ) );
			/* translators: %s: captured amount */
			$order->add_order_note( sprintf( __( 'Subscription authorized automatically. Captured amount: %s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), wc_price( $amount_to_charge, [ 'currency' => $order->get_currency() ] ) ) );
		}
	}

	public function register_rest_api() {
		if ( version_compare( WC_VERSION, '9.0', '>=' ) ) {
			//New
			register_rest_route( 'pensopay/v2', '/callback', [
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_callback' ],
				'permission_callback' => '__return_true'
			] );
		}
	}
}
