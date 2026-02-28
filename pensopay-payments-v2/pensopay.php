<?php /** @noinspection PhpDefineCanBeReplacedWithConstInspection */

/**
 * Plugin Name: pensopay Payments v2
 * Plugin URI: http://wordpress.org/plugins/pensopay/
 * Description: Integrates your pensopay v2 payment gateway into your WooCommerce installation.
 * Version: 2.0.6
 * Author: pensopay
 * Text Domain: pensopay-payments-v2
 * Domain Path: /languages/
 * Author URI: https://pensopay.com/
 * Wiki: https://knowledgebase.pensopay.com
 * License: GPLv2
 * WC requires at least: 8.2
 * WC tested up to: 9.4.1
 * Requires Plugins: woocommerce
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

define( 'PENSOPAY_PAYMENTS_V2_VERSION', '2.0.6' );
define( 'PENSOPAY_PAYMENTS_V2_WC_MIN_VER', '8.2' );
define( 'PENSOPAY_PAYMENTS_V2_PLUGIN_FILE', __FILE__ );
define( 'PPV2_PATH', plugin_dir_path( __FILE__ ) );

class Pensopay_Payments_V2 {
	public function __construct() {
		$this->includes();

		add_action( 'before_woocommerce_init', function () {
			if ( class_exists( FeaturesUtil::class ) ) {
				FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
			}
		} );

		//This one will handle the rest of the module initialization
		add_action( 'plugins_loaded', [ Pensopay_Payments_V2_Gateway::class, 'get_instance' ] );
	}

	//No unserializing
	public function __wakeup() {
	}

	public function includes() {
		//Order matters here
		//Core functionality initialization
		require_once PPV2_PATH . '/includes/abstract.php';
		require_once PPV2_PATH . '/includes/logger.php';
		require_once PPV2_PATH . '/includes/exceptions.php';

		require_once PPV2_PATH . '/includes/gateway.php';
	}

	public static function plugin_url( $path ): string {
		return plugins_url( $path, __FILE__ );
	}

	public static function plugin_path( string $path ): string {
		return __DIR__ . $path;
	}
}

new Pensopay_Payments_V2();