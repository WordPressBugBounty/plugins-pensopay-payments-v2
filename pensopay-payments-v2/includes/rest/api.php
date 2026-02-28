<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Rest_Api {
	/**
	 * Init the API by setting up action and filter hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
	}

	/**
	 * Register rest routes
	 */
	public function register_rest_routes() {
		register_rest_route( 'pensopay/v1', '/version', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'version_callback' ),
			'permission_callback' => array( $this, 'check_authentication' )
		) );
	}

	/**
	 * Check authentication for endpoint
	 *
	 * @param WP_REST_Request $request
	 */
	public function check_authentication( $request ) {
		$token = $request->get_header( 'Authorization' );

		if ( $token ) {
			// Trim Bearer and optional whitspace
			$token = preg_replace( '/^Bearer\s?/', '', $token );

			$options     = Pensopay_Payments_V2_Helper_Utility::get_settings();
			$private_key = $options['private_key'];

			if ( $private_key ) {
				if ( hash_equals( $private_key, $token ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Handle version endpoint
	 */
	public function version_callback() {
		$data = array(
			'version' => PENSOPAY_PAYMENTS_V2_VERSION
		);

		return new WP_REST_Response( $data );
	}
}

new Pensopay_Payments_V2_Rest_Api();