<?php

defined( 'ABSPATH' ) || exit;

class Pensopay_Payments_V2_Gateway extends Pensopay_Payments_V2_Abstract {
	private static ?Pensopay_Payments_V2_Gateway $instance = null;

	public const TEXT_DOMAIN = 'pensopay-payments-v2';

	public const SETTINGS_DOMAIN = 'woocommerce_pensopay_gateway_settings';

	public const DOCS_URL = 'https://knowledgebase.pensopay.com/woocommerce';
	public const SUPPORT_URL = 'https://wordpress.org/support/plugin/pensopay-payments-v2/';

	public function __construct() {
		self::$instance = $this;

		//Verify we have all requirements
		if ( ! class_exists( 'WooCommerce' )
		     || ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, PENSOPAY_PAYMENTS_V2_WC_MIN_VER, '<' ) )
		) {
			add_action( 'admin_notices', [ $this, 'missing_wc_notice' ] );

			return; //Stop initialization, WooCommerce is missing/incompatible
		}

		$this->includes();
		parent::__construct();

//        add_filter('pensopay_payments_v2_callback_url', function($home, $args, $post_id) {
//            return str_replace('pensopaywordpress.local', 'x.ngrok-free.app', $home) ;
//        }, 10, 3);
	}

	public function hooks() {
		// Load plugin text domain
		add_action( 'init', [ $this, 'load_plugin_textdomain' ] );

		add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateways' ] );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_links' ] );
		add_filter( 'allowed_redirect_hosts', [ $this, 'allowed_redirect_hosts' ] );
		add_filter( 'auto_update_plugin', [ $this, 'prevent_dangerous_auto_updates' ], 99, 2 );

		Pensopay_Blocks_Checkout::get_instance();

		if ( is_admin() ) {
			new Pensopay_Payments_V2_Admin_Panel();
		}
	}

	/**
	 * Add allowed redirect hosts
	 */
	public function allowed_redirect_hosts( $allowed_hosts ) {
		$allowed_hosts[] = 'pensopay.com';
		$allowed_hosts[] = 'pay.pensopay.com';

		return $allowed_hosts;
	}

	/**
	 * Get callback URL
	 */
	public static function get_callback_url( $post_id = null ) {
		if ( version_compare( WC_VERSION, '9.0', '>=' ) ) {
			$args = [];
			$url  = get_rest_url( null, 'pensopay/v2/callback' );
		} else {
			$args = [ 'wc-api' => 'Pensopay' ];

			if ( $post_id !== null ) {
				$args['order_post_id'] = $post_id;
			}

			$args = apply_filters( 'pensopay_payments_v2_callback_args', $args, $post_id );
			$url  = home_url( '/' );
		}

		return apply_filters( 'pensopay_payments_v2_callback_url', add_query_arg( $args, $url ), $args, $post_id );
	}

	public function add_gateways( $methods ) {
		$methods[] = 'Pensopay_Payments_V2_Methods_Creditcard';

		$methods[] = 'Pensopay_Payments_V2_Methods_Viabill';
		$methods[] = 'Pensopay_Payments_V2_Methods_Mobilepay';
		$methods[] = 'Pensopay_Payments_V2_Methods_Anyday';
//        $methods[] = 'Pensopay_Payments_V2_Methods_Klarna';
        $methods[] = 'Pensopay_Payments_V2_Methods_StripeKlarna';
        $methods[] = 'Pensopay_Payments_V2_Methods_StripeIdeal';
		$methods[] = 'Pensopay_Payments_V2_Methods_Swish';
		$methods[] = 'Pensopay_Payments_V2_Methods_ApplePay';
		$methods[] = 'Pensopay_Payments_V2_Methods_GooglePay';
		$methods[] = 'Pensopay_Payments_V2_Methods_VippsPsp';

		return $methods;
	}

	public function pensopay_refund_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( Pensopay_Payments_V2_Helpers_Order::is_order_using_pensopay( $order ) ) {
			$payment = new Pensopay_Payments_V2_Methods_Creditcard();
			$payment->process_refund( $order_id );
		}
	}

	/**
	 * Add meta links to plugin page
	 */
	public function plugin_row_meta( $links, $file ): array {
		if ( plugin_basename( __FILE__ ) === $file ) {
			$row_meta = [
				'docs'    => sprintf( '<a href="%s" target="_blank" aria-label="%s">%s</a>',
					esc_url( apply_filters( 'pensopay_payments_v2_docs_url', self::DOCS_URL ) ),
					esc_attr( __( 'View Documentation', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) ),
					__( 'Docs', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN )
				),
				'support' => sprintf( '<a href="%s" target="_blank" aria-label="%s">%s</a>',
					esc_url( apply_filters( 'pensopay_payments_v2_support_url', self::SUPPORT_URL ) ),
					esc_attr( __( 'Open a support request at wordpress.org', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) ),
					__( 'Support', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN )
				)
			];

			return array_merge( $links, $row_meta );
		}

		return (array) $links;
	}

	/**
	 * Add settings link to plugin actions
	 */
	public function plugin_action_links( $links ): array {
		$action_links = [
			'settings' => sprintf( '<a href="%s" aria-label="%s">%s</a>',
				admin_url( 'admin.php?page=wc-settings&tab=checkout&section=pensopay_gateway' ),
				esc_attr__( 'View pensopay settings', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				esc_html__( 'Settings', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN )
			)
		];

		return array_merge( $action_links, $links );
	}

	public function includes() {
		require_once PPV2_PATH . '/includes/helpers/utility.php';
		require_once PPV2_PATH . '/includes/helpers/localization.php';
		require_once PPV2_PATH . '/includes/helpers/media.php';
		require_once PPV2_PATH . '/includes/helpers/order.php';
		require_once PPV2_PATH . '/includes/helpers/price.php';
		require_once PPV2_PATH . '/includes/helpers/subscription.php';

		require_once PPV2_PATH . '/includes/gateway/api.php';
		require_once PPV2_PATH . '/includes/rest/api.php';
		require_once PPV2_PATH . '/includes/blocks/checkout.php';

		// Methods
		require_once PPV2_PATH . '/includes/methods/abstract.php';
		require_once PPV2_PATH . '/includes/methods/creditcard.php';
		require_once PPV2_PATH . '/includes/methods/viabill.php';
		require_once PPV2_PATH . '/includes/methods/mobilepay.php';
		require_once PPV2_PATH . '/includes/methods/anyday.php';
		require_once PPV2_PATH . '/includes/methods/klarna.php';
		require_once PPV2_PATH . '/includes/methods/stripe-klarna.php';
		require_once PPV2_PATH . '/includes/methods/stripe-ideal.php';
		require_once PPV2_PATH . '/includes/methods/swish.php';
		require_once PPV2_PATH . '/includes/methods/applepay.php';
		require_once PPV2_PATH . '/includes/methods/googlepay.php';
		require_once PPV2_PATH . '/includes/methods/vippspsp.php';

		// Meta boxes
		require_once PPV2_PATH . '/includes/admin/meta-boxes/meta-box-order-actions.php';

		// Order table
		require_once PPV2_PATH . '/includes/admin/order-table/order-table-transaction-data.php';
		require_once PPV2_PATH . '/includes/admin/admin-order-actions.php';

		//Emails
		require_once PPV2_PATH . '/includes/emails/manager.php';

		//Payment objects
		require_once PPV2_PATH . '/includes/gateway/types/transaction.php';
		require_once PPV2_PATH . '/includes/gateway/types/payment.php';
		require_once PPV2_PATH . '/includes/gateway/types/subscription.php';

		if ( is_admin() ) {
			require_once PPV2_PATH . '/includes/admin/panel.php';
		}
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function load_plugin_textdomain() {
		load_plugin_textdomain( self::TEXT_DOMAIN, false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}

	public static function get_view( $path, $args = [] ) {
		if ( is_array( $args ) && ! empty( $args ) ) {
			extract( $args );
		}

		$file = PPV2_PATH . 'views/' . trim( $path );

		if ( file_exists( $file ) ) {
			include $file;
		}
	}

	public function missing_wc_notice() {
		/* translators: %1: woocommerce version */
		echo sprintf( '<div class="error"><p><strong>%s</strong></p></div>',
			sprintf(
				esc_html__(
					'pensopay requires WooCommerce %1$s or greater to be installed and active.',
					Pensopay_Payments_V2_Gateway::TEXT_DOMAIN
				),
				esc_attr( PENSOPAY_PAYMENTS_V2_WC_MIN_VER )
			)
		);
	}

	public function prevent_dangerous_auto_updates( $should_update, $plugin ) {
		if ( ! isset( $plugin->plugin ) ) {
			return $should_update;
		}

		if ( 'pensopay-payments-v2/pensopay-payments-v2.php' !== $plugin->plugin ) {
			return $should_update;
		}

		if ( ! isset( $plugin->new_version, $plugin->Version ) ) {
			return false; //Do not auto update if we can't ensure safety
		}

		$current_major = (int) explode( '.', $plugin->Version )[0];
		$new_major     = (int) explode( '.', $plugin->new_version )[0];

		if ( $current_major === $new_major ) {
			return $should_update;
		}
		return false;
	}
}