<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Admin_Panel extends Pensopay_Payments_V2_Abstract {
	public array $notices = [];

	public function hooks() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'add_meta_boxes_shop_order', [ $this, 'add_payment_actions_meta_box' ] );
		add_action( 'add_meta_boxes_woocommerce_page_wc-orders', [ $this, 'add_payment_actions_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );

		add_action( 'wp_ajax_pensopay_transaction_actions', [ $this, 'handle_ajax_transaction_actions' ] );

		Pensopay_Admin_Order_Actions::get_instance();
		Pensopay_Payments_V2_Emails_Manager::get_instance();
		Pensopay_Order_Table_Transaction_Data::get_instance();
	}

	public function add_admin_notice( $slug, $class, $message, $dismissible = false ) {
		$this->notices[ $slug ] = array(
			'class'       => $class,
			'message'     => $message,
			'dismissible' => $dismissible,
		);
	}

	/**
	 * Display pensopay admin notices
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		foreach ( (array) $this->notices as $notice_key => $notice ) {
			echo '<div class="' . esc_attr( $notice['class'] ) . '" style="position:relative;">';

			if ( $notice['dismissible'] ) {
				?>
                <a href="<?php echo esc_url(
					wp_nonce_url(
						add_query_arg( 'wc-stripe-hide-notice', $notice_key ),
						'wc_stripe_hide_notices_nonce',
						'_wc_stripe_notice_nonce' )
				); ?>" class="woocommerce-message-close notice-dismiss"
                   style="position:relative;float:right;padding:9px 0px 9px 9px;text-decoration:none;"></a>
				<?php
			}

			echo '<p>';
			echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array(), 'target' => array() ) ) );
			echo '</p></div>';
		}
	}

	/**
	 * Add payment actions meta box
	 */
	public function add_payment_actions_meta_box( $post ) {
		if ( $post instanceof WP_Post ) {
			$order = wc_get_order( $post->ID );
		} else {
			$order = wc_get_order( $post->get_id() );
		}

		if ( Pensopay_Payments_V2_Helpers_Order::is_order_using_pensopay( $order ) ) {
			add_meta_box( 'pensopay-payments-v2-payment-actions',
				__( 'pensopay Payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'Pensopay_Meta_Box_Order_Actions::output',
				'woocommerce_page_wc-orders',
				'side', 'high'
			);
			add_meta_box( 'pensopay-payments-v2-payment-actions',
				__( 'pensopay Payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'Pensopay_Meta_Box_Order_Actions::output',
				'shop_order', 'side', 'high'
			);
		}
	}

	/**
	 * Handle ajax actions (capture/cancel) for payments
	 */
	public function handle_ajax_transaction_actions() {
		if ( isset( $_REQUEST['pensopay_payments_v2_ajax_action'] ) && isset( $_REQUEST['order_id'] ) ) {
			check_ajax_referer( 'pensopay_payments_v2_ajax_action' );

			$action   = sanitize_text_field( wp_unslash( $_REQUEST['pensopay_payments_v2_ajax_action'] ) );
			$order_id = sanitize_text_field( wp_unslash( $_REQUEST['order_id'] ) );

			$order = wc_get_order( $order_id );

			try {
                $transaction    = Pensopay_Api_Transaction::from_order( $order );
                $transaction    = $transaction->isSubscription() ? $transaction->getPayment() : $transaction;

				// Check if action is allowed for current payment state
				if ( $transaction->can( $action ) ) {
					$remaining  = $transaction->amount - $transaction->captured;
					$amount     = isset( $_REQUEST['pensopay_amount'] )
						? Pensopay_Payments_V2_Helpers_Price::price_custom_to_multiplied( sanitize_text_field( wp_unslash( $_REQUEST['pensopay_amount'] ) ), $order->get_currency() )
						: $remaining;
                    $realAmount = $amount / 100;

                    $transaction->{$action}( $amount );
					$order->add_order_note( 'pensopay: ' . sprintf( '%s: %s', $action, wc_price( $realAmount ) ) );
				} else {
					$message = sprintf(
						'Action: "%s", is not allowed for order #%d, with state "%s"',
						$action,
						$order->get_order_number(),
						$transaction->get_state()
					);
					$order->add_order_note( 'pensopay: ' . $message );
					throw new \Exception( $message );
				}
			} catch ( \Exception $e ) {
				echo esc_attr( $e->getMessage() );
				exit;
			}
		}
	}

	public function admin_scripts( $hook ) {
		wp_enqueue_script(
			'pensopay_payments_v2_admin_js',
			plugins_url( 'assets/js/admin/pensopay.js', PENSOPAY_PAYMENTS_V2_PLUGIN_FILE ),
			[],
			PENSOPAY_PAYMENTS_V2_VERSION
		);

		$localizationOptions = [
			'refund_warning' => __( 'Note! Refunding the order like this will refund the entire order. If you want to refund partially or specific items, use the refund button on the bottom of the items list. Do you want to refund the entire order?', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
			'nonce'          => [
				'pensopay_payments_v2_ajax_action' => wp_create_nonce( 'pensopay_payments_v2_ajax_action' ),
				'action'                           => wp_create_nonce( 'action' )
			]
		];

		$order = wc_get_order();
		if ( $order ) {
			$localizationOptions['is_pensopay'] = Pensopay_Payments_V2_Helpers_Order::is_order_using_pensopay( $order );
		} else {
			$localizationOptions['is_pensopay'] = false;
		}
		wp_localize_script( 'pensopay_payments_v2_admin_js', 'pensopayBackend', $localizationOptions );
		wp_enqueue_style(
			'pensopay_payments_v2_admin_style',
			plugins_url( 'assets/css/admin/pensopay.css', PENSOPAY_PAYMENTS_V2_PLUGIN_FILE ),
			[],
			PENSOPAY_PAYMENTS_V2_VERSION
		);

		if ( $hook === 'woocommerce_page_wc-settings' ) {
			wp_enqueue_script(
				'pensopay_payments_v2_admin_settings',
				plugins_url( 'assets/js/admin/pensopay-settings.js', PENSOPAY_PAYMENTS_V2_PLUGIN_FILE ),
				[],
				PENSOPAY_PAYMENTS_V2_VERSION
			);
		}
	}
}