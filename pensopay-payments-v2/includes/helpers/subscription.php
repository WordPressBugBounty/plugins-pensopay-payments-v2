<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Helpers_Subscription {
	public static function order_has_subscription( $order, $order_types = [ 'parent', 'resubscribe', 'switch', 'renewal' ] ): bool {
		return self::plugin_is_active() && wcs_order_contains_subscription( $order, $order_types );
	}

	public static function treat_order_as_subscription( $order ): bool {
		if ( self::plugin_is_active() && ( self::order_has_subscription( $order ) || $order instanceof WC_Subscription ) ) {
			return true;
		}
		return false;
	}

	public static function save_all_order_meta( $order, $response ) {
		if ( self::treat_order_as_subscription( $order ) ) {
			$fields = [
				'mandate_id' => 'mandate_id',
				'subscription_id' => 'subscription_id',
//				'testmode' => 'testmode',
//				'acquirer' => 'acquirer'
			];

			//We do this because create subscription hosts that value here, everywhere else it is referenced
			if ( !isset($response->subscription_id) && isset($response->mandate_id) ) {
				$fields['subscription_id'] = 'id';
			}

			$subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );

			foreach ($fields as $fieldName => $field) {
				if ( ! empty( $response->{$field} ) ) {
					$order->update_meta_data( '_pensopay_payment_' . $fieldName, $response->{$field} );
					foreach ( $subscriptions as $subscription ) {
						$subscription->update_meta_data( '_pensopay_payment_' . $fieldName, $response->{$field} );
					}
				}
			}

			foreach ($subscriptions as $subscription) {
				$subscription->save();
			}
		}
	}

	public function on_payment_authorized( $order ): void {
		$autocomplete_renewal_orders = WC_PensoPay_Helper::option_is_enabled( WC_PP()->s( 'subscription_autocomplete_renewal_orders' ) );

		if ( $autocomplete_renewal_orders && self::is_renewal( $order ) ) {
			$order->update_status( 'completed', __( 'Automatically completing order status due to successful recurring payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), false );
		}
	}

	public static function get_subscription_id( WC_Order $order, $deep = true ) {
		$subscriptionId = $order->get_meta( '_pensopay_subscription_id' );
		if (!$subscriptionId && $deep) {

			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
			if ( ! empty( $subscriptions ) ) {
				$subscription = end( $subscriptions );
				$subscriptionId = $subscription->get_meta( '_pensopay_subscription_id' );
			}
		}
		return $subscriptionId;
	}

	public static function get_woocommerce_subscription_id( WC_Order $order ) {
		$order_id = $order->get_id();
		if ( self::is_subscription( $order_id ) ) {
			return $order_id;
		} else if ( $order->contains_subscription() ) {
			// Find all subscriptions
			$subscriptions = self::get_subscriptions_for_order( $order_id );
			// Get the last one and base the transaction on it.
			$subscription = end( $subscriptions );

			// Fetch the post ID of the subscription, not the parent order.
			return $subscription->get_id();
		}
		return false;
	}

	/**
	 * Checks if a subscription is up for renewal.
	 * Ensures backwards compatability.
	 */
	public static function is_renewal( $order ) {
		if ( function_exists( 'wcs_order_contains_renewal' ) ) {
			return wcs_order_contains_renewal( $order );
		}

		return false;
	}

	/**
	 * Checks if Woocommerce Subscriptions is enabled or not
	 */
	public static function plugin_is_active(): bool {
		return class_exists( 'WC_Subscriptions' ) && WC_Subscriptions::$name = 'subscription';
	}

	/**
	 * Convenience wrapper for wcs_cart_contains_failed_renewal_order_payment
	 */
	public static function cart_contains_failed_renewal_order_payment() {
		if ( function_exists( 'wcs_cart_contains_failed_renewal_order_payment' ) ) {
			return wcs_cart_contains_failed_renewal_order_payment();
		}

		return false;
	}

	/**
	 * Convenience wrapper for wcs_cart_contains_renewal
	 */
	public static function cart_contains_renewal() {
		if ( function_exists( 'wcs_cart_contains_renewal' ) ) {
			return wcs_cart_contains_renewal();
		}

		return false;
	}

	/**
	 * Convenience wrapper for wcs_get_subscriptions_for_renewal_order
	 */
	public static function get_subscriptions_for_renewal_order( $order, $single = false ) {
		if ( function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );

			return $single ? end( $subscriptions ) : $subscriptions;
		}

		return [];
	}

	/**
	 * Convenience wrapper for wcs_get_subscriptions_for_order
	 */
	public static function get_subscriptions_for_order( $order ) {
		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return wcs_get_subscriptions_for_order( $order );
		}

		return [];
	}

	/**
	 * Activates subscriptions on a parent order
	 */
	public static function activate_subscriptions_for_order( $order ) {
		if ( self::plugin_is_active() ) {
			WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
		}

		return false;
	}

	/**
	 * Check if a given object is a WC_Subscription (or child class of WC_Subscription), or if a given ID
	 * belongs to a post with the subscription post type ('shop_subscription')
	 */
	public static function is_subscription( $subscription ) {
		if ( function_exists( 'wcs_is_subscription' ) ) {
			return wcs_is_subscription( $subscription );
		}

		return false;
	}

	public static function cart_has_subscription() {
		return class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription();
	}

	/**
	 * Returns a WC_Subscription object.
	 */
	public static function get_subscription( $subscription ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return null;
		}

		if ( ! is_object( $subscription ) ) {
			return wcs_get_subscription( $subscription ) ?: null;
		}

		if ( $subscription instanceof WP_Post ) {
			return wcs_get_subscription( $subscription->ID ) ?: null;
		}

		return $subscription;
	}
}