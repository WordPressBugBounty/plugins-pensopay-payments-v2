<?php

class Pensopay_Api_Subscription extends Pensopay_Api_Transaction {

	protected string $payment_id = '';

	protected Pensopay_Api_Payment $payment;

	/**
	 * @throws Exception
	 */
	public function cancel() {
		return self::post( sprintf( 'subscriptions/%s/cancel', $this->transaction_id ) );
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

	public function create( $params ) {
		return self::post( 'subscriptions', $params );
	}

	public function authorize( $order_id, $amount ) {
		$order      = wc_get_order( $order_id );

		$postData = array(
			'order_id'    => (string) $order_id,
			'amount'      => $amount,
			'autocapture' => false,
			'variables'   => Pensopay_Payments_V2_Methods_Abstract::get_variables( wc_get_order( $order_id ) )
		);

		$mandate_id = (int)$order->get_meta( '_pensopay_payment_mandate_id' );
		if ( $mandate_id ) {
			$postData['mandate_id'] = $mandate_id;
		}

		$result = self::post( sprintf( 'subscriptions/%s/payments', $order->get_meta( '_pensopay_payment_subscription_id' ) ), $postData );

		if ( ! $result ) {
			$order->set_status( 'failed', __( 'Failed to authorize subscription payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
			throw new Exception( __( 'Failed to authorize subscription payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
		}

		Pensopay_Payments_V2_Helpers_Order::save_all_order_meta( $order, $result );
		$order->set_status( 'processing', __( 'Subscription payment authorized successfully', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
		$order->set_transaction_id( $result->id );
		$order->save();
	}

	public function capture( $order_id, $amount ) {
		$order = wc_get_order( $order_id );

		$payment = new Pensopay_Api_Payment( $order->get_meta( '_pensopay_payment_id' ) );
		$result  = $payment->capture( $amount );

		if ( ! $result ) {
			$order->set_status( 'failed', __( 'Failed to capture subscription payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
			throw new Exception( __( 'Failed to capture subscription payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
		}

		$order->set_status( 'completed', __( 'Subscription payment captured successfully', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
		$order->set_transaction_id( $result->id );
		$order->save();
	}

	/**
	 * @throws Exception
	 */
	public static function autocapture( $subscription_id, $order_id, $amount, $mandate_id = false ) {
		$postData = array(
			'order_id'    => (string) $order_id,
			'amount'      => $amount,
			'autocapture' => true
		);
		if ( $mandate_id ) {
			$postData['mandate_id'] = (int)$mandate_id;
		}
		$result = Pensopay_Api::post( sprintf( 'subscriptions/%s/payments', $subscription_id ), $postData );
		$order  = wc_get_order( $order_id );

		if ( ! $result ) {
			$order->set_status( 'failed', __( 'Failed to capture subscription payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
			throw new Exception( __( 'Failed to capture subscription payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
		}

		$order->set_status( 'completed', __( 'Subscription payment captured successfully', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
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
