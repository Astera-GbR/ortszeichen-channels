<?php
namespace Ortszeichen\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Feed_Engine {

	public function register() {
		// Registriere REST-Route für Live-Feeds
		add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
		
		// Registriere WP-Cron für statische Feeds
		add_action( 'oz_channels_generate_static_feed', [ $this, 'generate_static_file' ], 10, 1 );
		
		// Planen des Crons beim Speichern der Settings
		add_action( 'update_option_oz_channels_feed_mode', [ $this, 'handle_feed_mode_change' ], 10, 2 );
	}

	public function register_rest_route() {
		register_rest_route( 'ortszeichen-channels/v1', '/feed/(?P<channel>[a-zA-Z0-9-]+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'serve_live_feed' ],
			'permission_callback' => static fn() => current_user_can( 'manage_woocommerce' ), // Feed ist öffentlich (kann mit API-Key gesichert werden)
		] );
	}

	public function serve_live_feed( \WP_REST_Request $request ) {
		$channel = $request->get_param( 'channel' );
		
		if ( ! $this->is_channel_active( $channel ) ) {
			return new \WP_Error( 'inactive_channel', 'Kanal ist deaktiviert.', [ 'status' => 403 ] );
		}

		// Always rebuild after current approval checks; public static snapshots cannot revoke products.
		// Live generieren
		$data = $this->generate_feed_data( $channel );
		return new \WP_REST_Response( $data, 200 );
	}

	public function handle_feed_mode_change( $old_value, $new_value ) {
		if ( $new_value === 'static' ) {
			if ( ! wp_next_scheduled( 'oz_channels_generate_static_feed', ['amazon'] ) ) {
				wp_schedule_event( time(), 'hourly', 'oz_channels_generate_static_feed', ['amazon'] );
			}
			if ( ! wp_next_scheduled( 'oz_channels_generate_static_feed', ['etsy'] ) ) {
				wp_schedule_event( time(), 'hourly', 'oz_channels_generate_static_feed', ['etsy'] );
			}
		} else {
			wp_clear_scheduled_hook( 'oz_channels_generate_static_feed', ['amazon'] );
			wp_clear_scheduled_hook( 'oz_channels_generate_static_feed', ['etsy'] );
		}
	}

	public function generate_static_file( $channel ) {
		// Public static export is disabled until private, revocation-aware storage is implemented.
		return new \WP_Error( 'static_export_unavailable', 'Bitte den authentifizierten Live-Feed verwenden.' );
	}

	private function generate_feed_data( $channel ): array {
		$formulas = get_option( 'oz_channels_pricing_formulas', [] );
		$formula  = $formulas[ $channel ] ?? '';

		$args = [
			'status' => 'publish',
			'limit'  => -1,
			'type' => array( 'simple', 'variation' ),
		];
		$products = wc_get_products( $args );

		$feed = [];
		foreach ( $products as $product ) {
			if ( ! $product->is_purchasable() || ! $product->is_in_stock() || '' === $product->get_sku() ) { continue; }
			$base_price = (float) $product->get_price();
			$channel_price = Price_Calculator::apply_formula( $base_price, $formula );

			if ( ! is_finite( $channel_price ) || $channel_price <= 0 || ! class_exists( '\\Ortszeichen\\Pricing' ) ) { continue; }
			$costs = $product->get_meta( '_oz_verified_costs', true );
			$costs = is_array( $costs ) ? $costs : array();
			if ( ( $costs['channel'] ?? '' ) !== $channel || ( $costs['currency'] ?? '' ) !== get_woocommerce_currency() || ( $costs['supplier_sku'] ?? '' ) !== $product->get_meta( '_oz_supplier_sku', true ) ) { continue; }
			$costs['gross_price'] = $channel_price;
			if ( ! \Ortszeichen\Pricing::evaluate( $costs )['eligible'] ) { continue; }
			$feed[] = [
				'sku'          => $product->get_sku(),
				'title'        => $product->get_name(),
				'url'          => $product->get_permalink(),
				'image'        => wp_get_attachment_url( $product->get_image_id() ),
				'stock'        => $product->get_stock_status(),
				'base_price'   => $base_price,
				'channel_price'=> $channel_price,
				'currency'     => get_woocommerce_currency()
			];
		}

		return [
			'channel'      => $channel,
			'generated_at' => current_time( 'mysql' ),
			'products'     => $feed
		];
	}

	private function is_channel_active( $channel ): bool {
		$active_adapters = get_option( 'oz_channels_active_adapters', [] );
		return in_array( $channel, array( 'amazon', 'etsy' ), true ) && isset( $active_adapters[ $channel ] ) && $active_adapters[ $channel ] === '1';
	}
}
