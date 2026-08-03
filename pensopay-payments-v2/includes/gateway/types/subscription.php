<?php

class Pensopay_Api_Subscription extends Pensopay_Api_Transaction {

	protected string $payment_id = '';

	protected Pensopay_Api_Payment $payment;

	/**
	 * @throws Exception
	 */
	public function cancel() {
		$result = self::post( sprintf( 'subscriptions/%s/cancel', $this->transaction_id ) );
		$this->fetch();
		$this->cache_transaction();
		return $result;
	}

	public function setPaymentId( $payment_id ) {
		$this->payment_id = $payment_id;
	}

	public function getPaymentId() {
		return $this->payment_id;
	}

	public function getPayment(): Pensopay_Api_Payment {
		if ( ! isset( $this->payment ) ) {
			$this->payment = new Pensopay_Api_Payment( $this->payment_id );
		}
		return $this->payment;
	}

	/**
	 * @throws Exception
	 */
	public function fetch() {
		$this->resource_data = self::get( 'subscriptions/' . $this->transaction_id );

		return $this->resource_data;
	}

	/**
	 * Fetch all payments for this subscription
	 *
	 * @throws Exception
	 */
	public function fetch_payments() {
		return self::get( sprintf( 'subscriptions/%s/payments', $this->transaction_id ) );
	}

	/**
	 * Get the card fee from the latest payment on this subscription
	 *
	 * @return int card fee in cents
	 */
	public function get_latest_payment_card_fee(): int {
		try {
			$response = $this->fetch_payments();
			$payments = $response->data ?? [];

			if ( empty( $payments ) ) {
				return 0;
			}

			$latest = array_reduce( (array) $payments, function ( $carry, $payment ) {
				if ( ! $carry || strtotime( $payment->created_at ) > strtotime( $carry->created_at ) ) {
					return $payment;
				}
				return $carry;
			} );

			return (int) ( $latest->card_fee ?? 0 );
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	public function create( $params ) {
		return self::post( 'subscriptions', $params );
	}

	public function authorize( $order_id, $amount, $autocapture = false ) {
		$order = wc_get_order( $order_id );

		// Resolve the V2 subscription id via the recovery chain (a V1-origin renewal lacks it).
		$recovered       = Pensopay_Payments_V2_Helpers_Subscription::recover_v2_subscription_meta( $order );
		$subscription_id = $recovered['subscription_id'];
		$mandate_id      = (int) $recovered['mandate_id'];

		if ( empty( $subscription_id ) ) {
			$message = sprintf(
				/* translators: %d: order id */
				__( 'Cannot authorize subscription renewal for order #%d: no pensopay subscription id could be resolved (order, subscription, parent order, or gateway).', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				$order_id
			);
			$order->update_status( 'failed', $message );
			Pensopay_Payments_V2_Logger::error( $message );
			throw new Exception( $message );
		}

		$postData = array(
			'order_id'    => (string) $order_id,
			'amount'      => $amount,
			'autocapture' => $autocapture,
			'variables'   => Pensopay_Payments_V2_Methods_Abstract::get_variables( $order ),
		);

		if ( $mandate_id ) {
			$postData['mandate_id'] = $mandate_id;
		}

		// The gateway rejects a duplicate order_id, so on POST failure we adopt any payment a
		// prior attempt already created (idempotent) rather than fail or charge twice.
		$result     = null;
		$post_error = null;
		try {
			$result = self::post( sprintf( 'subscriptions/%s/payments', $subscription_id ), $postData );
		} catch ( \Throwable $e ) {
			$post_error = $e;
		}

		if ( empty( $result ) || empty( $result->id ) ) {
			$existing = Pensopay_Payments_V2_Helpers_Subscription::find_gateway_payment_for_order( $order_id );
			if ( $existing && ! empty( $existing->id ) ) {
				$result = $existing; // adopt the payment the earlier attempt created
			} else {
				$message = sprintf(
					/* translators: %d: order id */
					__( 'Failed to authorize subscription payment for order #%d.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					$order_id
				);
				$order->update_status( 'failed', $message );
				Pensopay_Payments_V2_Logger::error( $message . ( $post_error ? ' | ' . $post_error->getMessage() : '' ) );
				throw new Exception( $message, 0, $post_error );
			}
		}

		// Warm the transaction cache with the created/adopted payment so the follow-up
		// capture/meta-box read hits the cache instead of re-fetching (same as fetch/refund).
		$payment = new Pensopay_Api_Payment();
		$payment->set_data( $result );
		$payment->cache_transaction();

		Pensopay_Payments_V2_Helpers_Order::save_all_order_meta( $order, $result );
		$order->set_status( 'processing', __( 'Subscription payment authorized successfully', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
		$order->set_transaction_id( $result->id );
		$order->save();
	}

	/**
	 * Check if subscription is active for actions
	 */
	public function can( $action ): bool {
		$data      = self::get_data();

		if ( empty( (array) $data ) ) {
			return false;
		}

		return $data->state === 'active';
	}
}
