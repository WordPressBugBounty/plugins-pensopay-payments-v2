<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Api {

	/**
	 * API Endpoint
	 */
	const ENDPOINT = 'https://api.pensopay.com/v2/';

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
		$response    = json_decode( $response['body'] ) ?? new stdClass();

		if ( ! in_array( $status_code, array( 200, 201 ) ) ) {
			Pensopay_Payments_V2_Logger::log( print_r( $response, true ) );
			if ( isset( $response->response, $response->response->error_code ) ) {
				$errorString = $response->response->error_message;
				throw new \Pensopay_Payments_V2_Exception( esc_attr( $errorString ) );
			} else if ( isset( $response->message ) ) {
				throw new \Pensopay_Payments_V2_Exception( esc_attr( $response->message ) );
			}
		}

		return $response;
	}
}
