<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Api {

	/**
	 * API Endpoint
	 */
	const ENDPOINT = 'https://api.pensopay.com/v2/';

	const ACCOUNT_METHODS_OPTION = 'pensopay_account_methods';

	protected static bool $is_api_available = true;

	protected static string $api_key = '';

	protected object $resource_data;

	public function __construct() {
		$this->resource_data = new stdClass();
	}

	public static function get_api_key(): string {
		if ( ! self::$api_key ) {
			$options = Pensopay_Payments_V2_Helper_Utility::get_settings();

			self::$api_key = $options['api_key'] ?? '';
		}

		return self::$api_key;
	}

	/**
	 * Get default headers
	 *
	 * @return mixed|void
	 */
	public static function get_headers() {
		return apply_filters(
			'pensopay_payments_request_headers',
			array(
				'Authorization' => 'Bearer ' . self::get_api_key(),
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json'
			)
		);
	}

	/**
	 * @throws Exception
	 */
	public static function post( $resource, $request = array() ) {
		return self::request( $resource, $request );
	}

	/**
	 * @throws Exception
	 */
	public static function get( $resource, $request = array() ) {
		return self::request( $resource, $request, 'GET' );
	}

	/**
	 * Perform pensopay API request
	 *
	 * @param $resource
	 * @param array $request
	 * @param string $method
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public static function request( $resource, array $request = array(), string $method = 'POST' ) {
		if (!self::$is_api_available) {
			throw new \Pensopay_Payments_V2_Exception( esc_attr__( 'Pensopay API is not available', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
		}

		$headers = self::get_headers();

		$data = [
			'method'  => $method,
			'headers' => $headers,
			'timeout' => $method === 'GET' ? 2 : 30,
		];

		if ( ! empty( $request ) && isset( $request['amount'] ) ) {
			$request['amount'] = (int) $request['amount'];
		}

		if ( $method !== 'GET' ) {
			$data['body'] = wp_json_encode( apply_filters( 'pensopay_payments_request_body', $request, $resource ) );
		}

		$response = wp_safe_remote_request(
			self::ENDPOINT . $resource,
			$data
		);

		if ($response instanceof WP_Error) {
			if ($method === 'GET') {
				self::$is_api_available = false;
			}

			throw new \Pensopay_Payments_V2_Exception( esc_attr( $response->get_error_message() ) );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = is_array( $response ) ? ( $response['body'] ?? '' ) : '';
		$response    = json_decode( $raw_body ) ?? new stdClass();

		if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
			Pensopay_Payments_V2_Logger::log( sprintf( 'pensopay API %s %s -> HTTP %s: %s', $method, $resource, $status_code, $raw_body ) );

			if ( isset( $response->response, $response->response->error_code ) ) {
				throw new \Pensopay_Payments_V2_Exception( esc_attr( $response->response->error_message ) );
			}

			if ( isset( $response->message ) ) {
				throw new \Pensopay_Payments_V2_Exception( esc_attr( $response->message ) );
			}

			// Non-2xx with an empty or non-JSON body: throw rather than return empty data.
			$detail = trim( (string) $raw_body );
			if ( '' === $detail ) {
				$detail = 'empty response body';
			}

			throw new \Pensopay_Payments_V2_Exception(
				esc_attr( sprintf(
					/* translators: 1: HTTP status code, 2: raw response detail */
					__( 'pensopay API request failed (HTTP %1$s): %2$s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					$status_code,
					function_exists( 'mb_substr' ) ? mb_substr( $detail, 0, 300 ) : substr( $detail, 0, 300 )
				) )
			);
		}

		return $response;
	}

	public static function fetch_account_methods() {
		try {
			$response = self::get( 'methods' );
			if ( ! empty( $response ) && isset( $response->methods ) ) {
				$methods = (array) $response->methods;
				update_option( self::ACCOUNT_METHODS_OPTION, $methods, false );
				return $methods;
			}
		} catch ( \Exception $e ) {
			// fail-open: don't hide gateways on API failure
		}
		return null;
	}

	public static function get_account_methods() {
		$cached = get_option( self::ACCOUNT_METHODS_OPTION, null );
		return is_array( $cached ) ? $cached : null;
	}

	public static function clear_account_methods_cache() {
		delete_option( self::ACCOUNT_METHODS_OPTION );
	}

	public static function reset_api_key_cache() {
		self::$api_key = '';
	}
}
