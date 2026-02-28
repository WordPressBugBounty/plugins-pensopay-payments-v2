<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

class Pensopay_Order_Table_Transaction_Data extends Pensopay_Payments_V2_Abstract {
	public function hooks() {
		add_action( 'admin_init', [ $this, 'setup' ] );
	}

	public function setup(): void {
		$isHPOS = Pensopay_Payments_V2_Helper_Utility::is_HPOS_enabled();
		if ( Pensopay_Payments_V2_Helper_Utility::is_option_enabled( Pensopay_Payments_V2_Helper_Utility::get_setting( 'pensopay_orders_transaction_info', 'yes' ) ) ) {

			// Add custom column data
			add_filter( $isHPOS ? 'woocommerce_shop_order_list_table_columns' : 'manage_edit-shop_order_columns', [
				$this,
				'filter_shop_order_posts_columns'
			] );
			add_filter( $isHPOS ? 'manage_woocommerce_page_wc-orders_custom_column' : 'manage_shop_order_posts_custom_column', [
				$this,
				'custom_column_data'
			], 10, 2 );
			add_filter( $isHPOS ? 'manage_woocommerce_page_wc-orders--shop_subscription_custom_column' : 'manage_shop_subscription_posts_custom_column', [
				$this,
				'custom_column_data'
			], 10, 2 );
		}


		add_filter( $isHPOS ? 'bulk_actions-woocommerce_page_wc-orders' : 'bulk_actions-edit-shop_order', [
			$this,
			'order_bulk_actions'
		], 20 );
		add_filter( $isHPOS ? 'handle_bulk_actions-woocommerce_page_wc-orders' : 'handle_bulk_actions-edit-shop_order', [
			$this,
			'handle_bulk_actions_orders'
		], 10, 3 );
		add_filter( $isHPOS ? 'handle_bulk_actions-woocommerce_page_wc-orders--shop_subscription' : 'handle_bulk_actions-edit-shop_subscription', [
			$this,
			'handle_bulk_actions_subscriptions'
		], 10, 3 );
		// Subscription actions
		add_filter( 'woocommerce_subscription_bulk_actions', [ $this, 'subscription_bulk_actions' ], 20 );
	}

	/**
	 * Adds a separate column for payment info
	 *
	 * @param array $show_columns
	 *
	 * @return array
	 */
	public function filter_shop_order_posts_columns( $show_columns ): array {
		$column_name   = 'pensopay_transaction_info';
		$column_header = __( 'Payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );

		return Pensopay_Payments_V2_Helper_Utility::array_insert_after( 'shipping_address', $show_columns, $column_name, $column_header );
	}



	public function custom_column_data( $column, $post_id_or_order_object ): void {
		$order = Pensopay_Payments_V2_Helpers_Order::get_order( $post_id_or_order_object );

		$order_type = OrderUtil::get_order_type( $order );

		$isShopOrderView = ( $order_type === 'shop_order' && $column === 'pensopay_transaction_info' );
		$isShopSubscriptionView = ( $order_type === 'shop_subscription' && $column === 'order_title' );

		if ( $order && ( $isShopOrderView || $isShopSubscriptionView ) ) {
			if ( ! Pensopay_Payments_V2_Helpers_Order::is_order_using_pensopay( $order ) ) {
				return;
			}

			try {
				$transaction    = Pensopay_Api_Transaction::from_order( $order );
				$isSubscription = $transaction->isSubscription();

				if ( $isSubscription && $isShopSubscriptionView ) {
					Pensopay_Payments_V2_Gateway::get_view( 'html-subscription-table-transaction-data.php', [
						'transaction_id'             => $transaction->get_transaction_id(),
						'transaction_order_id'       => $transaction->get_order_id() ?? $order->get_order_number(),
						'transaction_brand'          => $transaction->get_brand(),
						'transaction_brand_logo_url' => plugin_dir_url( PENSOPAY_PAYMENTS_V2_PLUGIN_FILE ) . 'assets/images/cards/' . $transaction->get_brand() . '.svg',
						'transaction_status'         => $transaction->get_state(),
						'transaction_is_test'        => $transaction->is_test(),
						'is_cached'                  => $transaction->is_loaded_from_cached(),
						'is_subscription'            => $isSubscription
					] );
				} else {
					$transactionId = $transaction->get_transaction_id();
					if ( $isSubscription ) {
						$transaction = $transaction->getPayment();
					}
					Pensopay_Payments_V2_Gateway::get_view( 'html-order-table-transaction-data.php', [
						'transaction_id'             => $transactionId,
						'transaction_order_id'       => $transaction->get_order_id() ?? $order->get_order_number(),
						'transaction_brand'          => $transaction->get_brand(),
						'transaction_brand_logo_url' => plugin_dir_url( PENSOPAY_PAYMENTS_V2_PLUGIN_FILE ) . 'assets/images/cards/' . $transaction->get_brand() . '.svg',
						'transaction_status'         => $transaction->get_current_type(),
						'transaction_is_test'        => $transaction->is_test(),
						'is_cached'                  => $transaction->is_loaded_from_cached(),
						'is_subscription'            => $isSubscription
					] );
				}
			} catch ( Pensopay_Payments_V2_Exception $e ) { //Exception auto-logs, we just need it to continue here.
			}
		}
	}

	/**
	 * @param array $actions
	 *
	 * @return array
	 */
	public function order_bulk_actions( array $actions ): array {
		if ( apply_filters( 'pensopay_payments_allow_orders_bulk_actions', current_user_can( 'manage_woocommerce' ) ) ) {
//			$actions['pensopay_capture_recurring']   = __( 'pensopay: Capture payment and activate subscription', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );
			$actions['pensopay_gateway_create_payment_link'] = __( 'pensopay: Create payment link', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );
		}

		return $actions;
	}

	/**
	 * @param array $actions
	 *
	 * @return array
	 */
	public function subscription_bulk_actions( array $actions ): array {
		if ( apply_filters( 'pensopay_payments_allow_subscriptions_bulk_actions', current_user_can( 'manage_woocommerce' ) ) ) {
			$actions['pensopay_create_payment_link'] = __( 'pensopay: Create payment link', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );
		}

		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param ?string $redirect_to URL to redirect to.
	 * @param string $action Action name.
	 * @param array $order_ids List of ids.
	 *
	 * @return string
	 */
	public function handle_bulk_actions_orders( ?string $redirect_to, string $action, array $order_ids ): string {
//		if ( 'pensopay_capture_recurring' === $action && current_user_can( 'manage_woocommerce' ) ) {
//			// Security check
//			$this->bulk_action_pensopay_capture_recurring( $order_ids );
//
//			// Redirect client
//			wp_redirect( $_SERVER['HTTP_REFERER'] );
//			exit;
//		}

		if ( 'pensopay_gateway_create_payment_link' === $action && current_user_can( 'manage_woocommerce' ) ) {
			$changed = 0;

			foreach ( $order_ids as $id ) {
				if ( ( $order = wc_get_order( $id ) ) && WC_PensoPay_Admin_Orders::get_instance()->order_action_pensopay_create_payment_link( $order ) ) {
					$changed ++;
				} else if ( ( $subscription = Pensopay_Payments_V2_Helpers_Subscription::get_subscription_id( $id ) ) && WC_PensoPay_Admin_Orders::get_instance()->order_action_pensopay_create_payment_link( $subscription ) ) {
					$changed ++;
				}
			}

			if ( $changed ) {
				/* translators: %d: amount of orders payment links created for */
//				pensopay_payments_add_admin_notice( sprintf( __( 'Payment links created for %d orders.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), $changed ) );
			}

			wp_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '' );
			exit;
		}

		return esc_url_raw( $redirect_to );
	}

	/**
	 * Handle bulk actions for subscriptions.
	 *
	 * @param ?string $redirect_to URL to redirect to.
	 * @param string $action Action name.
	 * @param array $order_ids List of ids.
	 *
	 * @return string
	 */
	public function handle_bulk_actions_subscriptions( ?string $redirect_to, string $action, array $order_ids ): string {

		if ( 'pensopay_gateway_create_payment_link' === $action && current_user_can( 'manage_woocommerce' ) ) {
			$changed = 0;

			foreach ( $order_ids as $id ) {
				if ( ( $subscription = Pensopay_Payments_V2_Helpers_Subscription::get_subscription( $id ) ) && WC_PensoPay_Admin_Orders::get_instance()->order_action_pensopay_create_payment_link( $subscription ) ) {
					$changed ++;
				}
			}

			if ( $changed ) {
				/* translators: %d: subscription count that payment links created for  */
//				pensopay_payments_add_admin_notice( sprintf( __( 'Payment links created for %d subscriptions.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), $changed ) );
			}

			wp_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '' );
			exit;
		}

		return esc_url_raw( $redirect_to );
	}
}
