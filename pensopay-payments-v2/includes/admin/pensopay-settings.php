<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Settings {
	public static function get_settings() {
		return self::get_gateway_settings();
	}

	private static function get_gateway_settings() {
		$settings = [];

		$settings[] = [
			//GENERAL
			'_general'    => array(
				'type'  => 'title',
				'title' => __( 'General Settings', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN )
			),
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'label'   => __( 'Enable pensopay Payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'api_key'     => array(
				'title'       => __( 'API Key', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'label'       => __( 'API Key', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'Get your API key and the private key below from your pensopay account.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'desc_tip'    => true
			),
			'private_key' => array(
				'title'       => __( 'Private Key', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'label'       => __( 'Private Key', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => Pensopay_Payments_V2_Helper_Utility::is_testmode() ? __( '<span style="color:red;font-weight:bold;">NOTICE: WHEN TEST PAYMENTS ARE ENABLED, LIVE PAYMENTS WILL NOT WORK</span>', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) : '',
				'desc_tip'    => false
			),
			'testmode'    => array(
				'title'       => __( 'Enable test mode', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'description' => __( 'When enabled test mode is active.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default'     => 'no',
				'desc_tip'    => true,
			)
		];

		//APPEARANCE
		$settings[] = [
			'_appearance'              => array(
				'type'  => 'title',
				'title' => __( 'Appearance', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN )
			),
			'title'                    => array(
				'title'       => __( 'Title', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default'     => __( 'Credit Card', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'desc_tip'    => true,
			),
			'description'              => array(
				'title'       => __( 'Description', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default'     => __( 'Pay with your credit card via pensopay.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'desc_tip'    => true,
			),
			'icons'                    => [
				'title'             => __( 'Credit card icons', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'              => 'multiselect',
				'description'       => __( 'Choose the card icons you wish to show next to the pensopay payment option in your shop.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'desc_tip'          => true,
				'class'             => 'wc-enhanced-select',
				'custom_attributes' => [
					'data-placeholder' => __( 'Select icons', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN )
				],
				'default'           => '',
				'options'           => Pensopay_Payments_V2_Helpers_Media::get_card_icons(),
			],
			'pensopay_icons_maxheight' => [
				'title'       => __( 'Credit card icons maximum height', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'number',
				'description' => __( 'Set the maximum pixel height of the credit card icons shown on the frontend.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default'     => 50,
				'desc_tip'    => true,
			],
			'language'                 => array(
				'title'       => __( 'Language', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'description' => __( 'Payment Window Language', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'desc_tip'    => true,
				'type'        => 'select',
				'options'     => call_user_func( function ( $languages ) {
					// Alphabetically sort language
					asort( $languages );

					return $languages;
				}, Pensopay_Payments_V2_Helpers_Localization::getSupportedLanguages() ),
				'default'     => 'calc'
			)
		];

		//ADVANCED
		$settings[] = [

			'_advanced'           => [
				'type'  => 'title',
				'title' => __( 'Advanced Settings', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN )
			],
			'methods'             => array(
				'title'   => __( 'Payment methods', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'    => 'select',
				'options' => array(
					'all'        => __( 'All available', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'creditcard' => __( 'All credit cards', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'specified'  => __( 'As specified', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				),
				'default' => 'creditcard'
			),
			'methods_specified'   => array(
				'title' => __( 'Specify payment methods', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'  => 'text',
			),
			'autocapture'         => array(
				'title'       => __( 'Physical products', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'description' => __( 'Automatically capture payments on physical products.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default'     => 'no',
			),
			'autocapture_virtual' => array(
				'title'       => __( 'Virtual products', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'description' => __( 'Automatically capture payments on virtual products. If the order contains both physical and virtual products, this setting will be overwritten by the default setting above.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default'     => 'no',
			),
			'capture_on_complete' => array(
				'title'       => __( 'Capture payment on order completion', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'description' => __( 'When enabled payments will automatically be captured when order state is set to "Complete".', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'complete_on_capture' => [
				'title'       => __( 'Complete order on capture callbacks', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'description' => __( 'When enabled, an order will be automatically completed when capture callbacks are sent to WooCommerce. Callbacks are sent by Pensopay when the payment is captured from either the shop or the Pensopay manager. Keep disabled to manually complete orders. ', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default'     => 'no',
			]
		];

		//EXPERT
		$settings[] = [
			'_expert'                     => [
				'type'  => 'title',
				'title' => __( 'Expert Settings', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN )
			],
			'pensopay_caching_enabled'    => [
				'title'       => __( 'Enable Caching', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'description' => __( 'Caches transaction data to improve application and web-server performance. <strong>Recommended.</strong>', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default'     => 'yes',
				'desc_tip'    => false,
			],
			'pensopay_caching_expiration' => [
				'title'       => __( 'Cache Expiration', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'label'       => __( 'Cache Expiration', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'number',
				'description' => __( '<strong>Time in seconds</strong> for how long a transaction should be cached. <strong>Default: 604800 (7 days).</strong>', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default'     => 7 * DAY_IN_SECONDS,
				'desc_tip'    => false,
			],
			'logging'                     => array(
				'title'       => __( 'Logging', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'label'       => __( 'Log debug messages', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default'     => 'true',
				'desc_tip'    => true,
			)
		];

		//SUBSCRIPTIONS
		if ( Pensopay_Payments_V2_Helpers_Subscription::plugin_is_active() ) {
			$settings[] = [
				'_subscriptions'                                     => [
					'type'  => 'title',
					'title' => __( 'Woocommerce Subscription Settings', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN )
				],
				'subscription_autocomplete_renewal_orders'           => [
					'title'       => __( 'Complete renewal orders', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'description' => __( 'Automatically mark a renewal order as complete on successful recurring payments.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					'default'     => 'no',
					'desc_tip'    => true,
				],
//				'subscription_update_card_on_manual_renewal_payment' => [
//					'title'       => __( 'Update card on manual renewal payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
//					'type'        => 'checkbox',
//					'label'       => __( 'Enable', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
//					'description' => __( 'When paying failed renewals, the payment link will authorize a new subscription transaction which will be saved on the customer\'s subscription. On callback, a payment transaction related to the actual renewal order will be created.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
//					'default'     => 'no',
//					'desc_tip'    => true,
//				],
			];
		}

		return apply_filters( 'pensopay_gateway', array_merge( ...$settings ) );
	}
}