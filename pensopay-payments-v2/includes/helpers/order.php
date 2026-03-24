<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Helpers_Order {
	/**
	 * Checks if the order is paid with us.
	 */
	public static function is_order_using_pensopay( WC_Order $order ): bool {
		return strpos( $order->get_payment_method(), 'pensopay_gateway' ) !== false;
	}

	/**
	 * Add a card fee from the gateway callback to the order
	 *
	 * @param WC_Order $order
	 * @param int $fee_in_cents
	 *
	 * @return bool
	 */
	public static function add_order_item_card_fee( WC_Order $order, int $fee_in_cents ): bool {
		if ( $fee_in_cents <= 0 ) {
			return false;
		}

		$fee = new WC_Order_Item_Fee();
		/* translators: Fee line item name shown on the order */
		$fee->set_name( __( 'Payment fee', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
		$fee->set_total( Pensopay_Payments_V2_Helpers_Price::normalize_price( $fee_in_cents ) );
		$fee->set_tax_status( 'none' );
		$fee->set_total_tax( 0 );
		$fee->set_order_id( $order->get_id() );
		$fee->save();

		$order->add_item( apply_filters( 'pensopay_payments_card_fee_data', $fee, $order ) );
		$order->calculate_taxes();
		$order->calculate_totals( false );
		$order->save();

		return true;
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
