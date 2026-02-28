<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Helpers_Order {
	/**
	 * Checks if the order is paid with us.
	 */
	public static function is_order_using_pensopay( WC_Order $order ): bool {
		return strpos( $order->get_payment_method(), 'pensopay_gateway' ) !== false;
	}

	public static function save_all_order_meta( $order, $response ) {
		$fields = [
			'id',
			'link',
		];

		foreach ($fields as $field) {
			if ( ! empty( $response->{$field} ) ) {
				$order->update_meta_data( '_pensopay_payment_' . $field, $response->{$field} );
			}
		}

//		if ( ! empty( $response->payment_details->brand ) ) {
//			$order->update_meta_data( '_pensopay_payment_brand', $response->payment_details->brand );
//		}

		Pensopay_Payments_V2_Helpers_Subscription::save_all_order_meta( $order, $response );

		$order->save();
	}

	/**
	 * Creates a payment transaction.
	 */
	public static function get_order( $order ): ?WC_Order {
		if ( ! is_object( $order ) ) {
			return wc_get_order( $order ) ?: null;
		}

		if ( $order instanceof WP_Post ) {
			return wc_get_order( $order->ID ) ?: null;
		}

		return $order;
	}
}
