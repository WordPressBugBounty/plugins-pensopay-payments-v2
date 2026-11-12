<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Api_Payment extends Pensopay_Api_Transaction {

	const OUTCOME_SUCCESS   = 'success';
	const OUTCOME_FAILED    = 'failed';
	const OUTCOME_UNDECIDED = 'undecided';

	public function create( $params ) {
		return self::post( 'payments', $params );
	}

	public function capture( $amount ) {
		$result = self::post( sprintf( 'payments/%s/capture', $this->transaction_id ), [ 'amount' => $amount ] );
		$this->fetch();
		return $result;
	}

	public function refund( $amount ) {
		$result = self::post( sprintf( 'payments/%s/refund', $this->transaction_id ), [ 'amount' => $amount ] );
		//There's no callback for refund currently, so we have to update the payment.
		$this->fetch();
		$this->cache_transaction();
		return $result;
	}

	/**
	 * @throws Exception
	 */
	public function cancel() {
		$result = self::post( sprintf( 'payments/%s/cancel', $this->transaction_id ) );
		$this->fetch();
		return $result;
	}

	/**
	 * @throws Exception
	 */
	public function fetch() {
		$payment = self::get( sprintf( 'payments/%s', $this->transaction_id ) );

		if ( ! empty( $payment->id ) ) {
			$payment->events = $this->fetch_events( $payment->id );
		}

		$this->resource_data = $payment;
		$this->cache_transaction();
		return $this->resource_data;
	}

	/**
	 * @throws Exception
	 */
	public function fetch_events( $transaction_id ) {
		return self::get( sprintf( 'payments/%s/events', $transaction_id ) );
	}

	/**
	 * @param bool $capture_expected Was autocapture requested?
	 * @return array{outcome:string, message:string, captured:bool}
	 */
	public function get_outcome( bool $capture_expected ): array {
		$data     = $this->get_data();
		$state    = isset( $data->state ) ? (string) $data->state : '';
		$captured = (int) ( $data->captured ?? 0 );
		// Null, not 0, when absent: a missing amount must not be read as "nothing to capture".
		$amount = isset( $data->amount ) ? (int) $data->amount : null;

		if ( Pensopay_Api_Transaction::is_terminal_negative_state( $state ) ) {
			return self::outcome( self::OUTCOME_FAILED, sprintf(
				/* translators: %s: gateway payment state */
				__( 'gateway state: %s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				$state
			) );
		}

		if ( 'captured' === $state || $captured > 0 ) {
			return self::outcome( self::OUTCOME_SUCCESS, '', true );
		}

		if ( 'authorized' === $state && ( ! $capture_expected || ( null !== $amount && $amount <= 0 ) ) ) {
			return self::outcome( self::OUTCOME_SUCCESS );
		}

		return $this->poll_outcome( $data->id ?? '' );
	}

	/**
	 * @param string|int $payment_id
	 * @return array{outcome:string, message:string, captured:bool}
	 */
	private function poll_outcome( $payment_id ): array {
		try {
			$events = Pensopay_Api::without_availability_kill_switch( function () use ( $payment_id ) {
				return $this->fetch_events( $payment_id );
			} );
		} catch ( \Throwable $e ) {
			Pensopay_Payments_V2_Logger::error( sprintf(
				'Could not read events for payment %s while resolving its outcome: %s',
				$payment_id,
				$e->getMessage()
			) );

			return self::outcome( self::OUTCOME_UNDECIDED );
		}

		$capture   = self::decisive_event( $events, 'capture' );
		$authorize = self::decisive_event( $events, 'authorize' );

		foreach ( array( $capture, $authorize ) as $event ) {
			if ( $event && false === $event->success ) {
				return self::outcome( self::OUTCOME_FAILED, (string) ( $event->message ?? '' ) );
			}
		}

		if ( $capture && true === $capture->success ) {
			return self::outcome( self::OUTCOME_SUCCESS, '', true );
		}

		return self::outcome( self::OUTCOME_UNDECIDED );
	}

	/**
	 * @return array{outcome:string, message:string, captured:bool}
	 */
	private static function outcome( string $outcome, string $message = '', bool $captured = false ): array {
		return array(
			'outcome'  => $outcome,
			'message'  => $message,
			'captured' => $captured,
		);
	}

	/**
	 * @param object|null $events The events sub-resource, or anything shaped like it.
	 * @return object|null
	 */
	public static function decisive_event( $events, string $type ) {
		$data   = ( is_object( $events ) && isset( $events->data ) ) ? $events->data : $events;
		$failed = null;

		foreach ( (array) $data as $event ) {
			if ( ! is_object( $event ) || ( $event->type ?? '' ) !== $type || ! isset( $event->success ) ) {
				continue;
			}

			if ( false === $event->success ) {
				$failed = $failed ?? $event;
				continue;
			}

			return $event;
		}

		return $failed;
	}

	/**
	 * Check if payment has permission for action
	 */
	public function can( $action ): bool {
		$data      = self::get_data();

		if ( empty( (array) $data ) ) {
			return false;
		}

		$state     = $data->state;
		$card_fee  = (int) ( $data->card_fee ?? 0 );
		$remaining = ( $data->amount + $card_fee ) - ( $data->captured ?? 0 );

		$allowed_actions = [
			'capture'          => [ 'authorized' ],
			'cancel'           => [ 'authorized' ],
			'refund'           => [ 'captured', 'refunded' ],
			'splitcapture'     => [ 'authorized', 'captured' ],
		];

		// Allow capture if not all has been captured yet
		if ( $action === 'capture' && $state === 'captured' && $remaining > 0 ) {
			return true;
		}

		return in_array( $state, $allowed_actions[ $action ] );
	}
}
