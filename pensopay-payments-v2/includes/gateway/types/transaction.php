<?php

class Pensopay_Api_Transaction extends Pensopay_Api {
	protected bool $loaded_from_cache = false;

	protected string $transaction_id = '';

	public function __construct( $transaction_id = '', bool $refresh = false ) {
		parent::__construct();

		if ( ! empty( $transaction_id ) ) {
			$this->transaction_id = $transaction_id;
			$this->maybe_load_transaction_from_cache( $refresh );
		}
	}

	public function __get( $name ) {
		if ( property_exists( $this, $name ) ) {
			if ( $name === 'resource_data' ) {
				return $this->get_data();
			}
			return $this->$name ?? null;
		} else {
			return $this->get_data()->$name ?? null;
		}
	}

	public function get_transaction_id(): string {
		return $this->transaction_id;
	}

	public static function from_order( $order, $refresh = false ) {
		$paymentId = $order->get_transaction_id() ?: $order->get_meta( '_pensopay_payment_id' );
 		if ( Pensopay_Payments_V2_Helpers_Subscription::treat_order_as_subscription( $order ) ) {
			$transaction = new Pensopay_Api_Subscription( $order->get_meta( '_pensopay_payment_subscription_id' ), $refresh );
			$transaction->setPaymentId( $paymentId );
		} else {
			$transaction = new Pensopay_Api_Payment( $paymentId, $refresh );
		}

		return $transaction;
	}

	public function maybe_load_transaction_from_cache( bool $refresh = false ) {
		$is_caching_enabled = self::is_transaction_caching_enabled();
		if ( empty( $this->transaction_id ) ) {
			throw new Pensopay_Payments_V2_Exception( esc_attr__( 'Transaction ID cannot be empty', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) );
		}

		if ( ! $refresh && ( $is_caching_enabled && false !== ( $transient = get_transient( 'wcpp_transaction_' . $this->transaction_id ) ) ) ) {
			$this->loaded_from_cache = true;
			try {
				$this->resource_data = json_decode( $transient, false, 512, JSON_THROW_ON_ERROR );
			} catch ( JsonException $e ) {
				throw new Pensopay_Payments_V2_Exception( 'Failed to decode cached transaction data.', 0, $e );
			}

			return $this->resource_data;
		}

		$this->fetch();

		if ( $is_caching_enabled ) {
			$this->cache_transaction();
		}

		return $this->resource_data;
	}

	/**
	 * @return boolean
	 */
	public static function is_transaction_caching_enabled(): bool {
		$is_enabled = ! ( strtolower( Pensopay_Payments_V2_Helper_Utility::get_setting( 'pensopay_caching_enabled' ) ) === 'no' );

		return apply_filters( 'pensopay_payments_transaction_cache_enabled', $is_enabled );
	}

	/**
	 * Updates cache data for a transaction
	 *
	 * @return boolean
	 * @throws Pensopay_Payments_V2_Exception
	 */
	public function cache_transaction(): bool {
		if ( empty( (array) $this->resource_data ) ) {
			throw new Pensopay_Payments_V2_Exception( 'Cannot cache empty transaction.' );
		}

		if ( ! self::is_transaction_caching_enabled() ) {
			return false;
		}

		if ( $this instanceof Pensopay_Api_Subscription ) {
			if ( $this->getPaymentId() ) {
				//cache payment too
				$this->getPayment()->fetch();
				$this->getPayment()->cache_transaction();
			}
		}

		$expiration = (int) Pensopay_Payments_V2_Helper_Utility::get_setting( 'pensopay_caching_expiration' );

		if ( ! $expiration ) {
			$expiration = 7 * DAY_IN_SECONDS;
		}

		// Cache expiration in seconds
		$expiration = apply_filters( 'pensopay_payments_transaction_cache_expiration', $expiration );

		return set_transient( 'wcpp_transaction_' . $this->resource_data->id, wp_json_encode( $this->resource_data, JSON_THROW_ON_ERROR ), $expiration );
	}

	/**
	 * @return bool
	 */
	public function is_loaded_from_cached(): bool {
		return $this->loaded_from_cache;
	}

	public function get_data( $forceRefresh = false ): object {
		if ( empty( (array) $this->resource_data ) || $forceRefresh ) {
			$this->fetch();
		}
		return $this->resource_data;
	}

	public function set_data( $data ): void {
		$this->resource_data = $data;
	}

	public function get_state(): string {
		return self::get_data()->state ?? '';
	}

	public function get_last_operation() {
		$data = $this->get_data();
		if ( empty( (array) $data ) || ! isset( $data->events ) ) {
			$data = $this->get_data( true );
		}

		if ( empty( (array) $data ) || ! isset( $data->events ) ) {
			return null;
		}

		$successful_operations = array_filter( $data->events->data ?? [], static function ( $operation ) {
			return $operation->success;
		} );

		$last_operation = array_reduce( $successful_operations, static function ( $carry, $operation ) {
			return ( $carry === null || $operation->id > $carry->id )
				? $operation
				: $carry;
		} );

		return $last_operation;
	}

	public function get_current_type(): string {
		$last_operation = $this->get_last_operation();

		if ( ! is_object( $last_operation ) ) {
			return '';
		}

		return $last_operation->type;
	}

	/**
	 * Check payment for state or array of states
	 */
	public function is( $status ): bool {
		$state = self::get_data()->state;
		if ( is_array( $status ) ) {
			return in_array( $state, $status, true );
		} else {
			return ( $state === $status );
		}
	}


	public function get_brand(): string {
		return self::get_data()->payment_details->brand ?? '';
	}

	public function is_test(): bool {
		return self::get_data()->testmode ?? false;
	}

	public function get_order_id(): ?string {
		return self::get_data()->order_id ?? null;
	}

	public function isSubscription(): bool {
		return $this instanceof Pensopay_Api_Subscription;
	}
}
