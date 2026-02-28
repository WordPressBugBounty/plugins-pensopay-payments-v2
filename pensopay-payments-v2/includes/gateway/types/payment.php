<?php

class Pensopay_Api_Payment extends Pensopay_Api_Transaction {
	public function create( $params ) {
		return self::post( 'payments', $params );
	}

	public function capture( $amount ) {
		return self::post( sprintf( 'payments/%s/capture', $this->transaction_id ), [ 'amount' => $amount ] );
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
		return self::post( sprintf( 'payments/%s/cancel', $this->transaction_id ) );
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
	 * Check if payment has permission for action
	 */
	public function can( $action ): bool {
		$data      = self::get_data();

		if ( empty( (array) $data ) ) {
			return false;
		}

		$state     = $data->state;
		$remaining = $data->amount - ( $data->captured ?? 0 );

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
