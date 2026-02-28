<?php

class Pensopay_Admin_Order_Actions extends Pensopay_Payments_V2_Abstract {

	public function hooks() {
		// Custom order actions
		add_filter( 'woocommerce_order_actions', [ $this, 'admin_order_actions' ], 10, 1 );
		add_action( 'woocommerce_order_action_pensopay_gateway_create_payment_link', [
			$this,
			'order_action_pensopay_create_payment_link'
		], 50, 2 );
	}

	/**
	 * @param \WC_Order $order
	 *
	 * @return bool|void
	 */
	public function order_action_pensopay_create_payment_link( $order ) {
		if ( ! $order ) {
			return;
		}

		// Determine if payment link creation should be skipped.
		// By default, we will skip payment link creation if the order is paid already.
		if ( ! apply_filters( 'pensopay_payments_order_action_create_payment_link_for_order', ! $order->is_paid(), $order ) ) {
			return;
		}

		try {
			$transaction    = Pensopay_Api_Transaction::from_order( $order );
			$transaction_id = $transaction->isSubscription() ? $transaction->getPayment()->get_transaction_id() : $transaction->get_transaction_id();

			$is_subscription        = Pensopay_Payments_V2_Helpers_Subscription::is_subscription( $order );
			$is_renewal_order       = false;
			$is_card_update_enabled = false;
			$is_change_payment_request_flag_modified = false;

			if ( ! $is_subscription ) {
				if ( $is_card_update_enabled && ( $is_renewal_order = Pensopay_Payments_V2_Helpers_Subscription::is_renewal( $order ) ) ) {
					WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = true;
					$is_change_payment_request_flag_modified                               = true;
				}
			}

			if ( ! $transaction_id ) {
				$order->set_payment_method( 'pensopay_gateway' );
				$order->set_payment_method_title( 'pensopay' );
				$order->save();

				// Append string to the order number to ensure that errors about "duplicate order numbers" are returned from the API.
				add_filter( 'pensopay_payments_order_number_for_api', [ $this, 'make_api_order_number_unique' ], 5 );

				$gateway        = new Pensopay_Payments_V2_Methods_Creditcard();
				$transaction    = $gateway->process_payment( $order->get_id() );
				$transaction_id = $transaction['response']->id;
				$link           = $transaction['redirect'];
				$order->set_transaction_id( $transaction_id );
				$order->save();

				// Remove filter from above.
				remove_filter( 'pensopay_payments_order_number_for_api', [ $this, 'make_api_order_number_unique' ], 5 );
			} else {
				$transaction = new Pensopay_Api_Payment( $transaction_id );
				$payment     = $transaction->maybe_load_transaction_from_cache( $transaction_id, true );
				$link = $payment->link;
			}

			if ( empty( $link ) ) {
				$link = $order->get_meta( '_pensopay_payment_link' );
			}

			// Reset change payment request flag
			if ( $is_renewal_order && $is_card_update_enabled && $is_change_payment_request_flag_modified ) {
				WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = false;
			}

			// Check URL
			if ( ! Pensopay_Payments_V2_Helper_Utility::is_url( $link ) ) {
				/* translators: %s: order id */
				throw new Exception( sprintf( __( 'Invalid payment link received from API for order #%s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), $order->get_id() ) );
			}

			// Late save for subscriptions. This is only to make sure that manual renewal is not set to true if an error occurs during the link creation.
			if ( $is_subscription ) {
				$subscription = wcs_get_subscription( $order->get_id() );
				$subscription->set_requires_manual_renewal( false );
				$subscription->save();
			}

			// Make sure to save the changes to the order/subscription object
			$order->save();
			/* translators: %s: payment link */
			$order->add_order_note( sprintf( __( 'Payment link manually created from backend: %s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), $link ), false, true );

			do_action( 'pensopay_payments_order_action_payment_link_created', $link, $order );

			return true;
		} catch ( \Exception $e ) {
			/* translators:
				%s: order id
				%s: error message
			*/
//			pensopay_payments_add_admin_notice( sprintf( __( 'Payment link could not be created for order #%1$s. Error: %2$s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), $order->get_id(), $e->getMessage() ), 'error' );
			return false;
		}
	}

	/**
	 * Filter to append a random string to the order number sent to the API.
	 *
	 * @param $api_order_number
	 *
	 * @return string
	 */
	public function make_api_order_number_unique( $api_order_number ): string {
		if ( ! preg_match( '/-.{3,}$/', $api_order_number ) ) {
			$api_order_number .= '-' . Pensopay_Payments_V2_Helper_Utility::create_random_string( 3 );
		}

		return $api_order_number;
	}

	public function admin_order_actions( $actions ) {
		$actions['pensopay_gateway_create_payment_link'] = __( 'pensopay: Create payment link', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );

		return $actions;
	}
}
