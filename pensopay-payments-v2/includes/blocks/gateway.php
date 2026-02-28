<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Container;

if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
	class Pensopay_Gateway_Block extends AbstractPaymentMethodType {

		protected string $settings_key;

		protected Pensopay_Payments_V2_Methods_Abstract $gateway;

		public function __construct( Pensopay_Payments_V2_Methods_Abstract $gateway ) {
			$this->gateway = $gateway;
			$this->name    = 'payment-gateway-' . $this->gateway->id;
		}

		/**
		 * @return void
		 */
		public function initialize() {
			$this->settings = $this->gateway->settings;
		}

		/**
		 * Retrieves the script handles for the payment method.
		 *
		 * @return string[] An array of script handles.
		 */
		public function get_payment_method_script_handles() {
			$dependencies = [
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
				'wp-hooks'
			];

			wp_register_script( 'pensopay-gateway-blocks-integration', Pensopay_Payments_V2::plugin_url( 'assets/js/checkout-blocks.js' ), $dependencies, 1, [ 'in_footer' => true ] );
			wp_register_style( 'pensopay-gateway-blocks-integration-styles', Pensopay_Payments_V2::plugin_url( 'assets/css/checkout-blocks.css' ), [], 1 );

			return [ 'pensopay-gateway-blocks-integration' ];
		}

		public function is_active() {
			return $this->gateway->is_available();
		}

		public function get_payment_method_data() {
			return [
				'label'       => $this->gateway->get_title(),
				'description' => $this->gateway->description,
				'supports'    => $this->gateway->supports,
				'icon'        => $this->gateway->get_icon()
			];
		}
	}
}
