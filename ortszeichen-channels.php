<?php
/**
 * Plugin Name: ORTSZEICHEN Channels
 * Description: Flexible Schnittstelle für externe Vertriebskanäle (Amazon Custom, Etsy, etc.). Importiert Bestellungen inkl. Personalisierungsdaten und stellt flexible Auto-Exports/Feeds bereit.
 * Version: 1.1.0
 * Author: Astera GbR
 * Text Domain: ortszeichen-channels
 * Requires at least: 6.4
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OZ_CHANNELS_VERSION', '1.1.0' );
define( 'OZ_CHANNELS_PATH', plugin_dir_path( __FILE__ ) );

// Kompatibilität mit WooCommerce HPOS
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// Autoloader & Init
require_once OZ_CHANNELS_PATH . 'inc/adapters/interface-channel-adapter.php';
require_once OZ_CHANNELS_PATH . 'inc/adapters/class-amazon-adapter.php';
require_once OZ_CHANNELS_PATH . 'inc/adapters/class-etsy-adapter.php';
require_once OZ_CHANNELS_PATH . 'inc/class-order-importer.php';
require_once OZ_CHANNELS_PATH . 'inc/class-rest-api.php';
require_once OZ_CHANNELS_PATH . 'inc/class-admin-settings.php';
require_once OZ_CHANNELS_PATH . 'inc/class-price-calculator.php';
require_once OZ_CHANNELS_PATH . 'inc/class-feed-engine.php';

function oz_channels_init() {
	// 1. Order Webhooks
	$rest_api = new \Ortszeichen\Channels\REST_API();
	$rest_api->register_routes();

	// 2. Admin UI
	if ( is_admin() ) {
		$admin = new \Ortszeichen\Channels\Admin_Settings();
		$admin->register();
	}

	// 3. Feed & Export Engine
	$feed_engine = new \Ortszeichen\Channels\Feed_Engine();
	$feed_engine->register();
}
add_action( 'rest_api_init', 'oz_channels_init' );
add_action( 'init', 'oz_channels_init' ); // Für Admin-Menu Hooks
