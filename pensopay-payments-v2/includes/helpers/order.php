<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Helpers_Order {

	const CARD_FEE_ITEM_META = '_pensopay_card_fee';

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

		$existing = self::find_card_fee_item( self::get_fresh_order( $order->get_id() ) ?: $order );

		if ( null !== $existing ) {
			$existing_cents = self::get_card_fee_cents( $existing );

			if ( $existing_cents !== $fee_in_cents ) {
				Pensopay_Payments_V2_Logger::warning( sprintf(
					'Order #%d already carries a card fee of %d; not replacing it with %d.',
					$order->get_id(),
					$existing_cents,
					$fee_in_cents
				) );
			}

			return false;
		}

		$fee = new WC_Order_Item_Fee();
		/* translators: Fee line item name shown on the order */
		$fee->set_name( __( 'Payment fee', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
		$fee->set_total( Pensopay_Payments_V2_Helpers_Price::normalize_price( $fee_in_cents ) );
		$fee->set_tax_status( 'none' );
		$fee->set_total_tax( 0 );
		$fee->set_order_id( $order->get_id() );
		$fee->add_meta_data( self::CARD_FEE_ITEM_META, $fee_in_cents, true );
		$fee->save();

		$order->add_item( apply_filters( 'pensopay_payments_card_fee_data', $fee, $order ) );
		$order->calculate_taxes();
		$order->calculate_totals( false );
		$order->save();

		return true;
	}

	/**
	 * @param WC_Order $order
	 * @return WC_Order_Item_Fee|null
	 */
	private static function find_card_fee_item( WC_Order $order ): ?WC_Order_Item_Fee {
		$our_names = array( 'Payment fee', __( 'Payment fee', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );

		foreach ( $order->get_fees() as $fee ) {
			if ( $fee->meta_exists( self::CARD_FEE_ITEM_META ) || in_array( $fee->get_name(), $our_names, true ) ) {
				return $fee;
			}
		}

		return null;
	}

	/**
	 * @param WC_Order_Item_Fee $fee
	 * @return int
	 */
	private static function get_card_fee_cents( WC_Order_Item_Fee $fee ): int {
		if ( $fee->meta_exists( self::CARD_FEE_ITEM_META ) ) {
			return (int) $fee->get_meta( self::CARD_FEE_ITEM_META );
		}

		return (int) round( (float) $fee->get_total() * 100 );
	}

	/**
	 * @param int $order_id
	 * @return WC_Order|false
	 */
	public static function get_fresh_order( $order_id ) {
		wp_cache_delete( $order_id, 'posts' );
		wp_cache_delete( $order_id, 'post_meta' );

		if ( function_exists( 'wc_get_container' ) ) {
			try {
				$order_cache = wc_get_container()->get( \Automattic\WooCommerce\Caches\OrderCache::class );
				$order_cache->remove( $order_id );
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- HPOS inactive or no cache container: there is simply nothing to invalidate.
				$order_cache = null;
			}
		}

		return wc_get_order( $order_id );
	}

	/**
	 * @param int $order_id
	 * @return bool
	 */
	public static function acquire_order_lock( $order_id ): bool {
		global $wpdb;

		$result = $wpdb->get_var( $wpdb->prepare(
			'SELECT GET_LOCK(%s, 5)',
			self::get_order_lock_name( $order_id )
		) );

		return $result === '1';
	}

	/**
	 * @param int $order_id
	 */
	public static function release_order_lock( $order_id ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			'SELECT RELEASE_LOCK(%s)',
			self::get_order_lock_name( $order_id )
		) );
	}

	/**
	 * @param int $order_id
	 */
	public static function get_order_lock_name( $order_id ): string {
		return 'pensopay_order_' . $order_id;
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
