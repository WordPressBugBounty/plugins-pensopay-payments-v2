<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Meta_Box_Order_Actions {
	public static function output( $post ) {
		try {
			$order = Pensopay_Payments_V2_Helpers_Order::get_order( method_exists( $post, 'get_id' ) ? $post->get_id() : $post->ID );

			if ( $order ) {
				if ( ! Pensopay_Payments_V2_Helpers_Order::is_order_using_pensopay( $order ) ) {
					return;
				}

				do_action( 'pensopay_payments_meta_box_order_before_content', $order );

				$transaction    = Pensopay_Api_Transaction::from_order( $order );
				$isSubscription = $transaction->isSubscription();

				$transactionData      = $isSubscription ? $transaction->getPayment() : $transaction->get_data();
				$status               = $transactionData->state;
				$remaining            = $transactionData->amount - ( $transactionData->captured ?? 0 );
				$transaction_order_id = $transactionData->order_id ?? $order->get_order_number();

				Pensopay_Payments_V2_Gateway::get_view( 'html-order-actions.php', [
					'transaction'                => $transaction,
					'is_subscription'            => $isSubscription,
					'subscription_id'            => $isSubscription ? $transaction->id : null,
					'payment'                    => $isSubscription ? $transaction->getPayment() : $transaction,
					'status'                     => $status ?? null,
					'remaining'                  => $remaining ?? null,
					'transaction_id'             => $isSubscription ? $transaction->getPayment()->get_transaction_id() ?? null : $transaction->get_transaction_id() ?? null,
					'transaction_order_id'       => $transaction_order_id ?? null,
					'transaction_brand'          => $transaction->get_brand(),
					'transaction_brand_logo_url' => plugin_dir_url( PENSOPAY_PAYMENTS_V2_PLUGIN_FILE ) . 'assets/images/cards/' . $transaction->get_brand() . '.svg',
					'order'                      => $order,
				] );

				do_action( 'pensopay_payments_meta_box_order_after_content', $order );
			}
		} catch ( \Exception $e ) {
			echo 'Failed to get payment';
		}
	}
}