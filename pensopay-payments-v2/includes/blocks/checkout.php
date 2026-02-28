<?php

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

/**
 * Class Pensopay_Blocks
 *
 * Loads WC blocks related logic
 */
class Pensopay_Blocks_Checkout extends Pensopay_Payments_V2_Abstract {

	/**
	 * @return void
	 */
	public function hooks() {
		add_action( 'woocommerce_blocks_loaded', [ $this, 'check_blocks_support' ] );

		// Stylesheets
		add_action( 'wp_print_footer_scripts', [ $this, 'maybe_apply_block_styles' ], 1 );
		add_action( 'admin_print_scripts', [ $this, 'maybe_apply_block_styles' ], 5 );
	}

	/**
	 * Loads the gateway specific stylesheet if the pensopay-gateway-blocks-integration script is loaded
	 * @return void
	 */
	public function maybe_apply_block_styles(): void {
		if ( wp_script_is( 'pensopay-gateway-blocks-integration' ) ) {
			// Enqueue the stylesheet only if the JavaScript file is enqueued
			wp_enqueue_style( 'pensopay-gateway-blocks-integration-styles' );
		}
	}

	/**
	 * Checks if the current environment supports blocks integration.
	 *
	 * @return void
	 */
	public function check_blocks_support(): void {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			include_once Pensopay_Payments_V2::plugin_path( '/includes/blocks/gateway.php' );

			add_action( 'woocommerce_blocks_payment_method_type_registration', [ $this, 'register_gateway_blocks' ] );
		}
	}

	/**
	 * Registers gateway blocks for the specified payment method registry.
	 *
	 * @param PaymentMethodRegistry $payment_method_registry The payment method registry to register gateway blocks for.
	 *
	 * @return void
	 */
	public function register_gateway_blocks( PaymentMethodRegistry $payment_method_registry ): void {

		$this->register_module_block_settings();

		foreach ( wc()->payment_gateways()->payment_gateways() as $payment_gateway ) {
			if ( static::is_plugin_gateway( $payment_gateway ) ) {
				$payment_method_registry->register( new Pensopay_Gateway_Block( $payment_gateway ) );
			}
		}
	}

	public function register_module_block_settings(): void {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry' ) ) {
			Automattic\WooCommerce\Blocks\Package::container()
			                                     ->get( Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry::class )
			                                     ->add( 'pensopay-gateway-plugin', $this->get_plugin_settings_data() );
		}
	}

	/**
	 * @return array|array[]
	 */
	protected function get_plugin_settings_data(): array {
		return [
			'gateways' => array_values( array_map( static fn( $gateway ) => $gateway->id, self::get_plugin_gateways() ) )
		];
	}

	public static function get_plugin_gateways(): array {
		return array_filter( wc()->payment_gateways()->payment_gateways(), static function ( $gateway ) {
			return static::is_plugin_gateway( $gateway );
		} );
	}

	public static function is_plugin_gateway( WC_Payment_Gateway $class ): bool {
		return $class instanceof Pensopay_Payments_V2_Methods_Abstract;
	}
}
