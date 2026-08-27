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

			// Default order_type is ['parent', 'switch'] — excludes 'renewal'. Without 'any'
			// here, paying a failed renewal order directly (a new mandate's real_order_id is
			// the renewal order's own id, not the subscription's) saves the new id onto the
			// renewal order but never propagates it to the actual WC_Subscription, permanently
			// breaking every future scheduled renewal until manually backfilled.
			$subscriptions = wcs_get_subscriptions_for_order( $order->get_id(), [ 'order_type' => 'any' ] );

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

	/**
	 * WCS copies ALL subscription meta onto a new renewal order by default
	 * (WC_Subscriptions_Data_Copier), including this plugin's own per-transaction
	 * fields. If left in place, process_payment() finds a non-empty inherited
	 * _pensopay_payment_link and reuses that ancestor's already-consumed link
	 * instead of creating a fresh transaction for the new renewal order — the
	 * customer lands on a dead "payment no longer pending authorization" page
	 * instead of a working checkout when paying a failed renewal directly.
	 *
	 * Mirrors WCS's own exclusion of _transaction_id from the copy, for the same
	 * reason: a renewal order must always get its own transaction, never inherit
	 * one from the subscription or a prior order.
	 *
	 * Checks $subscription's payment method, NOT $renewal_order's: WCS excludes
	 * _payment_method from the meta copy and only sets it on the renewal order
	 * later in process_renewal(), AFTER this filter runs — so the renewal order
	 * itself has no payment method yet at this point.
	 *
	 * @param WC_Order        $renewal_order
	 * @param WC_Subscription $subscription
	 * @return WC_Order
	 */
	public static function strip_renewal_order_payment_meta( $renewal_order, $subscription ) {
		if ( $renewal_order instanceof WC_Order && $subscription instanceof WC_Order && Pensopay_Payments_V2_Helpers_Order::is_order_using_pensopay( $subscription ) ) {
			$renewal_order->delete_meta_data( '_pensopay_payment_id' );
			$renewal_order->delete_meta_data( '_pensopay_payment_link' );
			$renewal_order->delete_meta_data( '_pensopay_payment_facilitator' );
			$renewal_order->save();
		}
		return $renewal_order;
	}

	/**
	 * Resolve which order a newly-authorized mandate's recurring payment should be
	 * charged against. Normally that's $order itself (e.g. initial signup, where
	 * $order is the parent order). But a mandate can also be (re)authorized via
	 * "Change payment method" run directly on the SUBSCRIPTION (e.g. a customer
	 * fixing a card after a failed renewal) — there, $order is the subscription,
	 * and charging $order->get_id() files the payment under the subscription's
	 * own id instead of the actual overdue renewal order nothing else is
	 * watching for that id ever captures.
	 *
	 * @param WC_Order $order
	 * @return WC_Order
	 */
	public static function resolve_order_needing_payment( $order ) {
		if ( self::is_subscription( $order ) ) {
			$last_renewal_id = $order->get_last_order( 'ids', 'renewal' );
			if ( $last_renewal_id ) {
				$last_renewal = wc_get_order( $last_renewal_id );
				if ( $last_renewal && $last_renewal->needs_payment() ) {
					return $last_renewal;
				}
			}
		}
		return $order;
	}

	public static function on_payment_authorized( $order ): void {
		$autocomplete_renewal_orders = Pensopay_Payments_V2_Helper_Utility::is_option_enabled(
			Pensopay_Payments_V2_Helper_Utility::get_setting( 'subscription_autocomplete_renewal_orders' )
		);

		$options = Pensopay_Payments_V2_Helper_Utility::get_settings();
		if ( $autocomplete_renewal_orders && self::is_renewal( $order ) ) {
			$order->update_status( 'completed', __( 'Automatically completing order status due to successful recurring payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), false );
		}
	}

	public static function get_subscription_id( WC_Order $order, $deep = true ) {
		$subscriptionId = $order->get_meta( '_pensopay_payment_subscription_id' );
		if (!$subscriptionId && $deep) {

			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
			if ( ! empty( $subscriptions ) ) {
				$subscription = end( $subscriptions );
				$subscriptionId = $subscription->get_meta( '_pensopay_payment_subscription_id' );
			}
		}
		return $subscriptionId;
	}

	/**
	 * Resolve the V2 subscription id + mandate for an order missing them, trying: order meta,
	 * the subscription(s), the parent order, then the gateway. Meta hits are persisted back.
	 *
	 * @return array{subscription_id:string, mandate_id:string}
	 */
	public static function recover_v2_subscription_meta( $order, bool $persist = true ): array {
		$subscription_id = (string) $order->get_meta( '_pensopay_payment_subscription_id' );
		$mandate_id      = (string) $order->get_meta( '_pensopay_payment_mandate_id' );

		if ( $subscription_id ) {
			return [ 'subscription_id' => $subscription_id, 'mandate_id' => $mandate_id ];
		}

		// Candidate subscriptions: a renewal's, else the order's, else the order itself.
		$subscriptions = self::get_subscriptions_for_renewal_order( $order );
		if ( empty( $subscriptions ) ) {
			$subscriptions = self::get_subscriptions_for_order( $order );
		}
		if ( empty( $subscriptions ) && self::is_subscription( $order ) ) {
			$subscriptions = [ $order ];
		}

		$sources = [];
		foreach ( $subscriptions as $subscription ) {
			$sources[] = $subscription;
			$parent    = $subscription->get_parent();
			if ( $parent ) {
				$sources[] = $parent;
			}
		}

		// $from_meta guards persistence: only per-customer meta hits are saved back, never a
		// gateway guess (which could be a different subscription after a switch).
		$from_meta = false;
		foreach ( $sources as $source ) {
			if ( ! $subscription_id ) {
				$subscription_id = (string) $source->get_meta( '_pensopay_payment_subscription_id' );
				if ( $subscription_id ) {
					$from_meta = true;
				}
			}
			if ( ! $mandate_id ) {
				$mandate_id = (string) $source->get_meta( '_pensopay_payment_mandate_id' );
			}
			if ( $subscription_id && $mandate_id ) {
				break;
			}
		}

		// Last resort: ask the gateway (used transiently, never persisted — see $from_meta).
		if ( ! $subscription_id ) {
			$recovered = self::recover_subscription_id_from_gateway( $subscriptions );
			if ( $recovered['subscription_id'] ) {
				$subscription_id = $recovered['subscription_id'];
				$mandate_id      = $mandate_id ?: $recovered['mandate_id'];
			}
		}

		if ( $subscription_id && $persist && $from_meta ) {
			self::persist_v2_subscription_meta( $order, $subscriptions, $subscription_id, $mandate_id );
		}

		return [ 'subscription_id' => $subscription_id, 'mandate_id' => $mandate_id ];
	}

	/**
	 * Persist recovered V2 ids onto the order and its subscription(s) so recovery only
	 * has to happen once. Only fills empty keys; never overwrites an existing value.
	 */
	public static function persist_v2_subscription_meta( $order, array $subscriptions, string $subscription_id, string $mandate_id ): void {
		$apply = static function ( $obj ) use ( $subscription_id, $mandate_id ) {
			if ( ! $obj ) {
				return;
			}
			$changed = false;
			if ( $subscription_id && ! $obj->get_meta( '_pensopay_payment_subscription_id' ) ) {
				$obj->update_meta_data( '_pensopay_payment_subscription_id', $subscription_id );
				$changed = true;
			}
			if ( $mandate_id && ! $obj->get_meta( '_pensopay_payment_mandate_id' ) ) {
				$obj->update_meta_data( '_pensopay_payment_mandate_id', $mandate_id );
				$changed = true;
			}
			if ( $changed ) {
				$obj->save();
			}
		};

		$apply( $order );
		foreach ( $subscriptions as $subscription ) {
			$apply( $subscription );
		}
	}

	/**
	 * Return the gateway payment already recorded for a WC order id, or null. Best-effort.
	 *
	 * @return object|null
	 */
	public static function find_gateway_payment_for_order( $order_id ) {
		try {
			$response = Pensopay_Api::get( 'payments?order_id=' . rawurlencode( (string) $order_id ) );
		} catch ( \Throwable $e ) {
			return null;
		}

		$payments = ( is_object( $response ) && isset( $response->data ) ) ? $response->data : $response;

		$undetermined      = null;
		$terminal_negative = null;

		foreach ( (array) $payments as $payment ) {
			if ( ! is_object( $payment ) || empty( $payment->id ) ) {
				continue;
			}

			$state = $payment->state ?? '';

			if ( Pensopay_Api_Transaction::is_authorized_state( $state ) ) {
				return $payment;
			}

			if ( Pensopay_Api_Transaction::is_terminal_negative_state( $state ) ) {
				$terminal_negative = $terminal_negative ?? $payment;
				continue;
			}

			$undetermined = $undetermined ?? $payment;
		}

		return $undetermined ?? $terminal_negative;
	}

	/**
	 * Best-effort gateway recovery of the V2 subscription id from the parent/switch order's
	 * payment. Reads that payment through Pensopay_Api_Payment (transaction-cache aware)
	 * rather than a bespoke lookup. Degrades gracefully (returns empty).
	 *
	 * @return array{subscription_id:string, mandate_id:string}
	 */
	private static function recover_subscription_id_from_gateway( array $subscriptions ): array {
		$empty = [ 'subscription_id' => '', 'mandate_id' => '' ];

		foreach ( $subscriptions as $subscription ) {
			$parent = $subscription->get_parent();
			if ( ! $parent ) {
				continue;
			}

			$payment_id = $parent->get_transaction_id() ?: $parent->get_meta( '_pensopay_payment_id' );
			if ( ! $payment_id ) {
				continue;
			}

			try {
				$data = ( new Pensopay_Api_Payment( $payment_id ) )->get_data();
			} catch ( \Throwable $e ) {
				continue;
			}

			if ( ! empty( $data->subscription_id ) ) {
				return [
					'subscription_id' => (string) $data->subscription_id,
					'mandate_id'      => (string) ( $data->mandate_id ?? '' ),
				];
			}
		}

		return $empty;
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