<?php

defined( 'ABSPATH' ) || exit;

abstract class Pensopay_Payments_V2_Methods_Abstract extends WC_Payment_Gateway {
	//Used if we need to regenerate the payment link
	protected $_orderSuffix = '';

	protected static $instances;

	public static function get_instance() {
		$class = static::class;

		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new $class;
		}

		return self::$instances[ $class ];
	}

	/**
	 * Init hooks
	 */
	protected function init_hooks() {
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		add_action( 'woocommerce_order_status_completed', array(
			$this,
			'capture_payment_on_order_completion'
		), 10, 2 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_payment_on_order_cancelled' ), 10, 2 );
//		add_action( 'woocommerce_thankyou', array( $this, 'process_order_status_after_payment' ) );

		add_filter( 'woocommerce_gateway_icon', array( $this, 'apply_gateway_icons' ), 2, 3 );
	}


	/**
	 * Create payment
	 *
	 * @param int $order_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		$redirect            = $this->get_payment_link( $order );
		$redirectFacilitator = $this->get_payment_link_facilitator( $order );
		$facilitator         = $this->get_facilitator( $order );

		$needsChangedLink = ( $redirectFacilitator !== $facilitator && ! empty( $redirectFacilitator ) );

		//Changed options, re-run it
		if ( ! $redirect || $needsChangedLink ) {
			$response = new stdClass();

			if ( $needsChangedLink ) {
				$this->_orderSuffix = '-' . rand( 10000, 99999 );
			}


			$transaction = Pensopay_Api_Transaction::from_order( $order );
			$params      = Pensopay_Payments_V2_Helpers_Subscription::treat_order_as_subscription( $order ) ? $this->get_subscription_params( $order ) : $this->get_payment_params( $order );
			$response    = $transaction->create( $params );

			Pensopay_Payments_V2_Helpers_Order::save_all_order_meta( $order, $response );

			if ( ! Pensopay_Payments_V2_Helper_Utility::is_url( $response->link ) ) {
				/* translators: %s: order id */
				throw new \Exception( sprintf( esc_attr__( 'Invalid payment link received from API for order -parent- #%1$s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), esc_attr( $order->get_id() ) ) );
			}

			$this->set_payment_link_facilitator( $order, $facilitator );

			$redirect = $response->link;
			$response = json_decode( wp_json_encode( $response ), true );
		}

		return [
			'result'   => 'success',
			'redirect' => $redirect,
			'response' => isset( $response ) ? $response : [],
		];
	}


	/**
	 * Handle refunds
	 *
	 * @param int $order_id
	 * @param null $amount
	 * @param string $reason
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		try {
			$order          = wc_get_order( $order_id );
			$transaction    = Pensopay_Api_Transaction::from_order( $order );
			$transaction    = $transaction->isSubscription() ? $transaction->getPayment() : $transaction;
			$transaction_id = $transaction->get_transaction_id();

			if ( ! $transaction_id ) {
				/* translators: %s: order id */
				throw new \Exception( sprintf( __( 'No transaction ID found for order: %s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), $order_id ) );
			}

			if ( $transaction->can( 'refund' ) ) {
				if ( $amount === null ) {
					// No custom amount set. Default to the order total
					$amount = $order->get_total();
				}
				$transaction->refund( Pensopay_Payments_V2_Helpers_Price::multiply_price( $amount, $order->get_currency() ) );
				/* translators: %s: refunded amount */
				$order->add_order_note( sprintf( __( 'Order refunded successfully. Refunded amount: %s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), wc_price( $amount, [ 'currency' => $order->get_currency() ] ) ) );
				return true;
			} else {
				throw new \Exception( __( 'Order is in invalid state for refund.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
			}
		} catch ( \Exception $e ) {
			if ( $transaction->state !== 'refunded' || ( $transaction->amount - $transaction->captured ) > 0 ) {
				$order->add_order_note( sprintf( '%s: %s', __( 'Refund failed' ), $e->getMessage() ) );
				error_log( $e->getMessage() );

				return false;
			}
		}

		return false;
	}

	/**
	 * Validate and handle callback
	 */
	public function handle_callback() {
		$body = file_get_contents( 'php://input' );

		if ( ! $this->is_valid_callback( $body ) ) {
			Pensopay_Payments_V2_Logger::log( 'Invalid callback received' . print_r( $body, true ) );
			status_header( 401 );
			exit;
		}

		$callback_data = json_decode( $body );
		$event         = $callback_data->event;
		$callback      = $callback_data->resource;
		$variables     = $callback->variables;

		if ( ! isset( $variables, $variables->real_order_id ) ) {
			return;
		}

		$order = wc_get_order( $variables->real_order_id );
		if ( ! $order ) {
			return;
		}

		$order_id = $order->get_id();

		// Acquire database-level lock (atomic) to prevent concurrent callback processing
		// If locked, return 503 with Retry-After so gateway will retry
		$lock_name = 'pensopay_order_' . $order_id;
		if ( ! $this->acquire_callback_lock( $lock_name ) ) {
			Pensopay_Payments_V2_Logger::log( sprintf( 'Callback locked, returning 503: order #%d, event: %s', $order_id, $event ) );
			status_header( 503 );
			header( 'Retry-After: 60' );
			exit;
		}

		try {
			// Reload order after acquiring lock to get fresh state
			// (order may have changed while waiting for lock)
			// Clear cache first to avoid stale data
			wp_cache_delete( $order_id, 'posts' );
			wp_cache_delete( $order_id, 'post_meta' );
			// Clear HPOS cache if using High-Performance Order Storage
			if ( function_exists( 'wc_get_container' ) ) {
				try {
					$order_cache = wc_get_container()->get( \Automattic\WooCommerce\Caches\OrderCache::class );
					$order_cache->remove( $order_id );
				} catch ( \Exception $e ) {
					// HPOS not active or cache not available, ignore
				}
			}
			$order = wc_get_order( $order_id );

			$transactionIdentifier = $this->process_callback_event( $order, $event, $callback );

			if ( $transactionIdentifier ) {
				$transaction = Pensopay_Api_Transaction::from_order( $order, true );
				$transaction->cache_transaction();
			}
		} finally {
			$this->release_callback_lock( $lock_name );
		}
	}

	/**
	 * Acquire a database-level lock (atomic)
	 *
	 * @param string $lock_name
	 * @return bool
	 */
	private function acquire_callback_lock( $lock_name ) {
		global $wpdb;

		// Wait up to 5 seconds for lock before giving up
		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT GET_LOCK(%s, 5)",
			$lock_name
		) );

		return $result === '1';
	}

	/**
	 * Release database-level lock
	 *
	 * @param string $lock_name
	 */
	private function release_callback_lock( $lock_name ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			"SELECT RELEASE_LOCK(%s)",
			$lock_name
		) );
	}

	/**
	 * Process callback event by type
	 *
	 * @param WC_Order $order
	 * @param string $event
	 * @param object $callback
	 * @return string|false Transaction identifier or false
	 */
	private function process_callback_event( $order, $event, $callback ) {
		switch ( $event ) {
			case 'payment.authorized':
				return $this->handle_payment_authorized( $order, $callback );

			case 'payment.captured':
				return $this->handle_payment_captured( $order, $callback );

			case 'mandate.authorized':
				return $this->handle_mandate_authorized( $order, $callback );

			case 'recurring.authorized':
				return $this->handle_recurring_authorized( $order, $callback );

			case 'recurring.captured':
				return $this->handle_recurring_captured( $order, $callback );

			default:
				return $callback->id;
		}
	}

	/**
	 * Ensure order is marked as paid (idempotent)
	 * Called by both authorize and capture - whoever arrives first does the work
	 *
	 * @param WC_Order $order
	 * @param object $callback
	 * @return bool True if we processed payment, false if already done
	 */
	private function ensure_order_authorized( $order, $callback ) {
		// Already paid with same transaction - nothing to do
		if ( $order->get_transaction_id() === (string) $callback->id ) {
			return false;
		}

		// Already paid with different transaction - skip
		if ( $order->is_paid() ) {
			return false;
		}

		// Process authorization
		do_action( 'pensopay_payments_accepted_callback_before_processing', $order, $callback );

		$order->payment_complete( $callback->id );
		Pensopay_Payments_V2_Helpers_Order::save_all_order_meta( $order, $callback );

		// Add authorization note
		$order->add_order_note( sprintf(
			/* translators: %s: transaction id */
			__( 'Payment authorized. Transaction ID: %s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
			$callback->id
		) );

		$order->save();

		return true;
	}

	/**
	 * Handle payment.authorized callback
	 */
	private function handle_payment_authorized( $order, $callback ) {
		$this->ensure_order_authorized( $order, $callback );
		return $callback->id;
	}

	/**
	 * Handle payment.captured callback
	 * If capture arrives before authorize, it handles authorization too
	 */
	private function handle_payment_captured( $order, $callback ) {
		// Ensure order is authorized first (handles race condition)
		$this->ensure_order_authorized( $order, $callback );

		// Add capture note
		if ( Pensopay_Payments_V2_Helper_Utility::is_option_enabled( $this->get_autocapture_setting( $order ) ) ) {
			$order->add_order_note( sprintf(
				/* translators: %1$s: currency, %2$s: amount, %3$s: transaction id */
				__( 'Payment captured. Amount: %1$s %2$s. Transaction ID: %3$s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				$callback->currency,
				$callback->captured,
				$callback->id
			) );
		}

		// Complete order if setting enabled
		$complete_on_capture = Pensopay_Payments_V2_Helper_Utility::get_setting( 'complete_on_capture' );
		if ( Pensopay_Payments_V2_Helper_Utility::is_option_enabled( $complete_on_capture ) && ! $order->has_status( 'completed' ) ) {
			$order->update_status( 'completed', __( 'Payment completed.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
		}

		return $callback->id;
	}

	/**
	 * Handle mandate.authorized callback
	 */
	private function handle_mandate_authorized( $order, $callback ) {
		if ( $this->ensure_order_authorized( $order, $callback ) ) {
			$transaction = Pensopay_Api_Transaction::from_order( $order );
			$transaction->authorize(
				$order->get_id(),
				(int) Pensopay_Payments_V2_Helpers_Price::multiply_price( $order->get_total(), $order->get_currency() )
			);
			$order->save();
		}

		return $callback->subscription_id;
	}

	/**
	 * Handle recurring.authorized callback
	 */
	private function handle_recurring_authorized( $order, $callback ) {
		$this->ensure_order_authorized( $order, $callback );
		return $callback->subscription_id;
	}

	/**
	 * Handle recurring.captured callback
	 */
	private function handle_recurring_captured( $order, $callback ) {
		$subscriptions = Pensopay_Payments_V2_Helpers_Subscription::get_subscriptions_for_renewal_order( $order );

		foreach ( $subscriptions as $subscription ) {
			$subscriptionId = Pensopay_Payments_V2_Helpers_Subscription::get_subscription_id( $subscription );

			if ( $subscriptionId == $callback->subscription_id ) {
				$subscription->update_status( 'active' );
				$subscription->add_order_note( sprintf(
				/* translators: %1$s: currency, %2$s: amount, %3$s: transaction id */
					__( 'Payment captured. Amount: %1$s %2$s. Transaction ID: %3$s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
					$callback->currency,
					$callback->captured,
					$callback->id
				) );
				break;
			}
		}

		return $callback->subscription_id;
	}

	public function get_payment_params( $order ) {
		$facilitator = $this->get_facilitator( $order );

		$params = array(
			'order_id'     => $this->get_order_id( $order ),
			'amount'       => (int) Pensopay_Payments_V2_Helpers_Price::multiply_price( $order->get_total(), $order->get_currency() ),
			'currency'     => $this->get_currency( $order ),
			'order'        => $this->get_order_params( $order ),
			'testmode'     => Pensopay_Payments_V2_Helper_Utility::is_testmode(),
			'success_url'  => $this->get_continue_url( $order ),
			'cancel_url'   => $this->get_cancellation_url( $order ),
			'callback_url' => Pensopay_Payments_V2_Gateway::get_callback_url(),
			'autocapture'  => Pensopay_Payments_V2_Helper_Utility::is_option_enabled( $this->get_autocapture_setting( $order ) ),
			'locale'       => $this->get_language(),
			'variables'    => self::get_variables( $order ),
		);

		$methods = $this->get_methods( $facilitator );
		if ( ! empty( $methods ) ) {
			$params['methods'] = $methods;
		}

		return apply_filters(
			'pensopay_payments_payment_params',
			$params
		);
	}

	public function get_subscription_params( $order ) {
		$params = array(
			'reference'    => $this->get_order_id( $order ),
			'amount'       => (int) Pensopay_Payments_V2_Helpers_Price::multiply_price( $order->get_total() > 0 ? $order->get_total() : WC_Subscriptions_Order::get_recurring_total( $order ), $order->get_currency() ),
			'currency'     => $this->get_currency( $order ),
			'description'  => $this->get_order_id( $order ),
			'testmode'     => Pensopay_Payments_V2_Helper_Utility::is_testmode(),
			'callback_url' => Pensopay_Payments_V2_Gateway::get_callback_url(),
			'success_url'  => $this->get_continue_url( $order ),
			'cancel_url'   => $this->get_cancellation_url( $order ),
//			'locale'       => $this->get_language(),
			'variables'    => self::get_variables( $order ),
		);

		if ( class_exists( 'WC_Subscription' ) && $order instanceof WC_Subscription ) {
			$params['amount'] = $params['amount'] ?: 1;
		}

		return apply_filters(
			'pensopay_payments_subscription_params',
			$params
		);
	}

	public function get_mandate_params( $order ) {
		$options     = Pensopay_Payments_V2_Helper_Utility::get_settings();
		$facilitator = $this->get_facilitator( $order );

		$params = array(
			'mandate_id'   => (int) $this->get_order_id( $order ),
			'facilitator'  => $facilitator,
			'success_url'  => $this->get_continue_url( $order ),
			'cancel_url'   => $this->get_cancellation_url( $order ),
			'callback_url' => Pensopay_Payments_V2_Gateway::get_callback_url(),
			'variables'    => self::get_variables( $order ),
		);

		return apply_filters(
			'pensopay_payments_mandate_params',
			$params
		);
	}


	/**
	 * Get order number for gateway
	 *
	 * @param WC_Order $order
	 *
	 * @return mixed|void
	 */
	private function get_order_id( $order ) {
		return apply_filters( 'pensopay_payments_order_id', $order->get_order_number() . $this->_orderSuffix );
	}

	/**
	 * Get basket parameters for API call
	 *
	 * @param WC_Order $order
	 *
	 * @return mixed|void
	 */
	private function get_basket_params( $order ) {
		$basket = array();

		foreach ( $order->get_items() as $item ) {
			$basket[] = $this->get_transaction_basket_params_line_helper( $item );
		}

		return apply_filters( 'pensopay_payments_basket_params', $basket, $order );
	}

	/**
	 * @param $item
	 *
	 * @return array
	 */
	private function get_transaction_basket_params_line_helper( $item ) {
		/**
		 * @var WC_Order_Item_Product $item
		 */
		$taxes = WC_Tax::get_rates( $item->get_tax_class() );

		//Get rates of the product
		$rates = array_shift( $taxes );

		//Take only the item rate and round it.
		$vat_rate = ! empty( $rates ) ? round( array_shift( $rates ) ) : 0;

		$data = array(
			'qty'      => $item->get_quantity(),
			'sku'      => $item->get_product_id(),
			'name'     => $item->get_name(),
			'price'    => (int) wc_get_price_including_tax( $item->get_product() ),
			'vat_rate' => $vat_rate,
		);

		return array(
			'qty'      => $data['qty'],
			'sku'      => (string) $data['sku'],
			//
			'name'     => esc_attr( $data['name'] ),
			'price'    => (int) Pensopay_Payments_V2_Helpers_Price::multiply_price( $data['price'], $item->get_order()->get_currency() ),
			'vat_rate' => $data['vat_rate'] > 0 ? $data['vat_rate'] / 100 : 1
			// Basket item VAT rate (ex. 0.25 for 25%) //TODO 1 -> 0
		);
	}

	/**
	 * Get basket parameters for API call
	 *
	 * @param WC_Order $order
	 *
	 * @return mixed|void
	 */
	public function get_shipping_address_params( $order ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$params = array(
				'name'          => sprintf( '%s %s', $order->shipping_first_name, $order->shipping_last_name ),
				'address'       => $order->shipping_address_1,
				'city'          => $order->shipping_city,
				'zipcode'       => $order->shipping_postcode,
				'country'       => $order->shipping_country,
				'phone_number'  => $order->billing_phone,
				'mobile_number' => $order->billing_phone,
				'email'         => $order->billing_email,
			);
		} else {
			$params = array(
				'name'          => $order->get_formatted_shipping_full_name(),
				'address'       => $order->get_shipping_address_1(),
				'city'          => $order->get_shipping_city(),
				'zipcode'       => $order->get_shipping_postcode(),
				'country'       => $order->get_shipping_country(),
				'phone_number'  => $order->get_billing_phone(),
				'mobile_number' => $order->get_billing_phone(),
				'email'         => $order->get_billing_email(),
			);
		}

//		$params = array(
//			'street'          => $order->get_shipping_street_name(),
//			'house_number'    => $order->get_shipping_house_number(),
//			'house_extension' => $order->get_shipping_house_extension(),
//		);

		return apply_filters( 'pensopay_payments_shipping_params', $params );
	}

	private function get_billing_address_params( WC_Order $order ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$params = array(
				'name'          => sprintf( '%s %s', $this->billing_first_name, $this->billing_last_name ),
				'address'       => $order->billing_address_1,
				'city'          => $order->billing_city,
				'zipcode'       => $order->billing_postcode,
				'country'       => $order->billing_country,
				'phone_number'  => $order->billing_phone,
				'mobile_number' => $order->billing_phone,
				'email'         => $order->billing_email,
			);
		} else {
			$params = array(
				'name'          => $order->get_formatted_billing_full_name(),
				'address'       => $order->get_billing_address_1(),
				'city'          => $order->get_billing_city(),
				'zipcode'       => $order->get_billing_postcode(),
				'country'       => $order->get_billing_country(),
				'phone_number'  => $order->get_billing_phone(),
				'mobile_number' => $order->get_billing_phone(),
				'email'         => $order->get_billing_email(),
			);
		}

		return apply_filters( 'pensopay_payments_billing_params', $params, $order );
	}

	private function get_shipping_params( $order ) {
		$shipping_tax   = $order->get_shipping_tax();
		$shipping_total = version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_total_shipping() : $order->get_shipping_total();

		$shipping_incl_vat = $shipping_total;
		$shipping_vat_rate = 0;

		if ( $shipping_tax && $shipping_total ) {
			$shipping_incl_vat += $shipping_tax;
			$shipping_vat_rate = $shipping_tax / $shipping_total; // Basket item VAT rate (ex. 0.25 for 25%)
		}

		return apply_filters( 'pensopay_payments_params_shipping_params_row', array(
			'method'   => 'own_delivery',
			'company'  => $order->get_shipping_method(),
			'price'    => (int) Pensopay_Payments_V2_Helpers_Price::multiply_price( $shipping_incl_vat, $order->get_currency() ),
			'vat_rate' => $shipping_vat_rate,
		), $order );
	}

	/**
	 * Get saved payment link from order meta
	 *
	 * @param WC_Order $order
	 *
	 * @return mixed
	 */
	public function get_payment_link( $order ) {
		return $order->get_meta( '_pensopay_payment_link' );
	}

	public function get_payment_link_facilitator( $order ) {
		return $order->get_meta( '_pensopay_payment_facilitator', true );
	}

	public function set_payment_link_facilitator( $order, $facilitator ) {
		$order->update_meta_data( '_pensopay_payment_facilitator', $facilitator );
		$order->save();
	}

	/**
	 * The URL where the customer gets sent after succesful payment
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	private function get_continue_url( $order ) {
		if ( method_exists( $order, 'get_checkout_order_received_url' ) ) {
			return $order->get_checkout_order_received_url();
		}

		return add_query_arg( 'key', $order->get_order_key(), add_query_arg( 'order', $order->get_id(), get_permalink( get_option( 'woocommerce_thanks_page_id' ) ) ) );
	}

	/**
	 * The URL where the customer is redirected to if they cancel
	 *
	 * @param WC_Order $order
	 *
	 * @return string|string[]
	 */
	private function get_cancellation_url( $order ) {
		if ( method_exists( $order, 'get_cancel_order_url' ) ) {
			return str_replace( '&amp;', '&', $order->get_cancel_order_url() );
		}

		return add_query_arg( 'key', $order->get_order_key(), add_query_arg( array(
			'order'                => $order->get_id(),
			'payment_cancellation' => 'yes',
		), get_permalink( get_option( 'woocommerce_cart_page_id' ) ) ) );
	}

	/**
	 * Get currency
	 *
	 * @param WC_Order $order
	 *
	 * @return mixed|void
	 */
	private function get_currency( $order ) {
		$currency = $order->get_currency();

		return apply_filters( 'pensopay_payments_currency', $currency, $order );
	}

	/**
	 * Validate incoming callback
	 *
	 * @param $headers
	 * @param $body
	 *
	 * @return false
	 */
	private function is_valid_callback( $body ) {
		if ( $body === null ) {
			return false;
		}

		if ( ! isset( $_SERVER['HTTP_PENSOPAY_SIGNATURE'] ) ) {
			return false;
		}

		$signature = $_SERVER['HTTP_PENSOPAY_SIGNATURE'];

		if ( empty( $signature ) ) {
			return false;
		}

		$options = Pensopay_Payments_V2_Helper_Utility::get_settings();

		if ( ! empty( $options['private_key'] ) ) {
			$private_key = $options['private_key'];

			//Generate signature
			$expected = hash_hmac( 'sha256', $body, $private_key );
			$given    = $signature;

			return hash_equals( $expected, $given );
		}

		return false;
	}

	/**
	 * Capture payment when order status is set to completed
	 *
	 * @param $post_id
	 * @param $order
	 */
	public function capture_payment_on_order_completion( $post_id, $order ) {
		if ( Pensopay_Payments_V2_Helpers_Order::is_order_using_pensopay( $order ) && $order->get_payment_method() === $this->id ) {
			$options = Pensopay_Payments_V2_Helper_Utility::get_settings();


			/**
			 * TODO: get this to have a single functionality that defaults to api_payment or api_subscription based on the order type
			 * We are working off of authorizations as a priority
			 * Capture the subscription only if "complete renewal orders"
			 */


			if ( apply_filters( 'pensopay_payments_capture_on_order_completion', Pensopay_Payments_V2_Helper_Utility::is_option_enabled( $options['capture_on_complete'] ) ) ) {
				try {

					$transaction = Pensopay_Api_Transaction::from_order( $order );
					$transaction = $transaction->isSubscription() ? $transaction->getPayment() : $transaction;

					if ( $transaction->isSubscription() ) {
						//TODO -- subscriptions need to create their own payments
						//and also the meta box needs to display non-payment data
					} else {
						// Check if action is allowed for current payment state
						if ( $transaction->can( 'capture' ) ) {
							$remaining = $transaction->amount - $transaction->captured;
							$response  = $transaction->capture( $remaining );
							Pensopay_Payments_V2_Helpers_Order::save_all_order_meta( $order, $response );
							/* translators: %s: captured amount */
							$order->add_order_note( sprintf( __( 'Payment captured automatically. Captured amount: %s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), wc_price( $remaining / 100, [ 'currency' => $order->get_currency() ] ) ) );
						} else {
							if ( $transaction->state === 'authorized' || ( $transaction->amount - $transaction->captured ) > 0 ) {
								throw new \Exception( sprintf( 'Action: "capture", is not allowed for order #%d, with state "%s"', $order->get_order_number(), $transaction->state ) );
							}
						}
					}
				} catch ( \Exception $e ) {
					$order->add_order_note( sprintf( '%s: %s', __( 'Capture failed' ), $e->getMessage() ) );
					error_log( $e->getMessage() );
				}
			}
		}
	}

	public function cancel_payment_on_order_cancelled( $post_id, $order ) {
		if ( Pensopay_Payments_V2_Helpers_Order::is_order_using_pensopay( $order ) && $order->get_payment_method() === $this->id ) {
			try {
				$transaction = Pensopay_Api_Transaction::from_order( $order );

				if ( $transaction->can( 'cancel' ) ) {
					$transaction->cancel();
				} else {
					throw new \Exception( sprintf( 'Action: "cancel", is not allowed for order #%d, with state "%s"', $order->get_order_number(), $transaction->state ) );
				}


			} catch ( \Exception $e ) {
				$order->add_order_note( sprintf( '%s: %s', __( 'Cancel failed' ), $e->getMessage() ) );
				error_log( $e->getMessage() );
			}
		}
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return mixed
	 */
	protected function get_autocapture_setting( $order ) {
		$options = Pensopay_Payments_V2_Helper_Utility::get_settings();

		$autocapture_default = $options['autocapture'];
		$autocapture_virtual = $options['autocapture_virtual'];

		$has_virtual_products  = false;
		$has_physical_products = false;

		// If the two options are the same, return immediately.
		if ( $autocapture_default === $autocapture_virtual ) {
			return $autocapture_default;
		}

		foreach ( $order->get_items() as $order_item ) {
			$product = $order_item->get_product();

			// Is this product virtual?
			if ( $product instanceof WC_Product && $product->is_virtual() ) {
				$has_virtual_products = true;
			} else {
				$has_physical_products = true;
			}
		}

		// If the order contains both physical and virtual products we use the default

		if ( $has_virtual_products && $has_physical_products ) {
			return $autocapture_default;
		} elseif ( $has_virtual_products ) {
			return $autocapture_virtual;
		} else {
			return $autocapture_default;
		}
	}

	/**
	 * Get language for gateway
	 *
	 * @return mixed|void
	 */
	protected function get_language() {
		$options = Pensopay_Payments_V2_Helper_Utility::get_settings();

		//Get store locale
		$lang_iso = $this->get_wp_locale();

		//Check if language should be hardcoded
		if ( array_key_exists( 'language', $options ) && is_string( $options['language'] ) ) {
			if ( $options['language'] !== 'calc' ) {
				$lang_iso = $options['language'];
			}
		}

		return apply_filters( 'pensopay_payments_language', $lang_iso );
	}

	protected function get_wp_locale() {
		$lang_iso = get_locale();
		if ( $lang_iso === 'fo' ) { //Wordpress does not use the right format for Faroese which is relevant for us.
			$lang_iso = 'fo_FO';
		}

		return $lang_iso;
	}

	/**
	 * Get order information for gateway
	 *
	 * @param $order
	 *
	 * @return array
	 */
	protected function get_order_params( $order ) {
		$options = Pensopay_Payments_V2_Helper_Utility::get_settings();

		$order_params = array(
//			'basket'   => $this->get_basket_params( $order ),
			'shipping' => $this->get_shipping_params( $order ),
		);

		$order_params['shipping_address'] = $this->get_shipping_address_params( $order );
		$order_params['billing_address']  = $this->get_billing_address_params( $order );

		return $order_params;
	}

	/**
	 * Get facilitator
	 *
	 * @param $order
	 */
	private function get_facilitator( $order ) {
		$payment_method = strtolower( version_compare( WC_VERSION, '3.0', '<' ) ? $order->payment_method : $order->get_payment_method() );

		$facilitator = apply_filters( 'pensopay_payments_facilitator_' . $payment_method, $payment_method );

		return apply_filters( 'pensopay_payments_facilitator_' . $payment_method, $payment_method );
	}

	/**
	 * Get available payment methods for facilitator
	 *
	 * @param $facilitator
	 *
	 * @return mixed|void
	 */
	private function get_methods( $facilitator ) {
		$options = Pensopay_Payments_V2_Helper_Utility::get_settings();

		$methods = $options['methods'];

		if ( $methods === 'specified' ) {
			$methods = $options['methods_specified'];
		}

		return apply_filters( 'pensopay_payments_' . $facilitator . '_methods', $methods, $facilitator );
	}

	/**
	 * Payment variables
	 */
	public static function get_variables( $order ) {
		$variables = apply_filters( 'pensopay_payments_payment_params_variables', array(
			'real_order_id' => (string) $order->get_id(),
		) );

		//Sort by key
		ksort( $variables );

		return $variables;
	}

	/**
	 * Fallback
	 *
	 * @param $payment_method
	 *
	 * @return string
	 */
	public function set_default_facilitator( $payment_method ) {
		return 'pensopay_gateway';
	}

	public function set_default_method( $methods ) {
		if ( empty( $methods ) || $methods === 'all' ) {
			return [];
//            return ['card', 'mobilepay', 'applepay', 'anyday', 'viabill', 'swish', 'klarna'];
		}

		return [ 'card' ];
	}


	/**
	 * Add icons to checkout
	 *
	 * @param $icon
	 * @param $id
	 *
	 * @return string
	 */
	public function apply_gateway_icons( $icon, $id ) {
		if ( $id === $this->id ) {
			$options = Pensopay_Payments_V2_Helper_Utility::get_settings();

			$icons = $options['icons'];

			if ( ! empty( $icons ) ) {
				foreach ( $icons as $key => $item ) {
					$icon .= $this->create_gateway_icon( $item );
				}
			}
		}

		return $icon;
	}

	/**
	 * Helper to create image tag for gateway icon
	 *
	 * @param $icon
	 *
	 * @return string
	 */
	protected function create_gateway_icon( $icon ) {
		$options   = Pensopay_Payments_V2_Helper_Utility::get_settings();
		$maxheight = isset( $options['pensopay_icons_maxheight'] ) ? $options['pensopay_icons_maxheight'] : '20px';

		if ( file_exists( plugin_dir_path( PENSOPAY_PAYMENTS_V2_PLUGIN_FILE ) . '/assets/images/cards/' . $icon . '.svg' ) ) {
			$icon_url = $icon_url = WC_HTTPS::force_https_url( plugin_dir_url( PENSOPAY_PAYMENTS_V2_PLUGIN_FILE ) . 'assets/images/cards/' . $icon . '.svg' );
		} else {
			$icon_url = WC_HTTPS::force_https_url( plugin_dir_url( PENSOPAY_PAYMENTS_V2_PLUGIN_FILE ) . 'assets/images/cards/' . $icon . '.png' );
		}

		$icon_url = apply_filters( 'pensopay_payments_checkout_gateway_icon_url', $icon_url, $icon );

		return sprintf( '<img src="%s" alt="%s" style="max-height:%spx"/>', $icon_url, esc_attr( $this->get_title() ), esc_attr( $maxheight ) );
	}

	public function s( $key, $default = null ) {
		if ( isset( $this->settings[ $key ] ) ) {
			return $this->settings[ $key ];
		}

		return apply_filters( 'pensopay_payments_v2_get_setting' . $key, ! is_null( $default ) ? $default : '', $this );
	}
}
