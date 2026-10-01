<?php
/**
 * Plugin Name: ORTSZEICHEN Channels
 * Description: Flexible Schnittstelle für externe Vertriebskanäle (Amazon Custom, Etsy, etc.). Importiert Bestellungen inkl. Personalisierungsdaten und übergibt sie an die Print-Engine.
 * Version: 1.0.0
 * Author: Astera GbR
 * Text Domain: ortszeichen-channels
 * Requires at least: 6.4
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OZ_CHANNELS_VERSION', '1.0.0' );
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

function oz_channels_init() {
	$rest_api = new \Ortszeichen\Channels\REST_API();
	$rest_api->register_routes();
}
add_action( 'rest_api_init', 'oz_channels_init' );
