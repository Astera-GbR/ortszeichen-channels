<?php
namespace Ortszeichen\Channels;

use Ortszeichen\Channels\Adapters\Amazon_Adapter;
use Ortszeichen\Channels\Adapters\Channel_Adapter_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class REST_API {

	public function register_routes() {
		register_rest_route( 'ortszeichen-channels/v1', '/order/import/(?P<channel>[a-zA-Z0-9-]+)', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'handle_import' ],
			'permission_callback' => '__return_true', // Validierung passiert im Adapter
		] );
	}

	public function handle_import( \WP_REST_Request $request ) {
		$channel = $request->get_param( 'channel' );
		
		// Prüfe, ob der Kanal aktiv ist
		$active_adapters = get_option( 'oz_channels_active_adapters', [] );
		if ( empty( $active_adapters[ $channel ] ) ) {
			return new \WP_Error( 'inactive_channel', 'Dieser Vertriebskanal ist in den Einstellungen deaktiviert.', [ 'status' => 403 ] );
		}

		$adapter = $this->get_adapter( $channel );
		if ( ! $adapter ) {
			return new \WP_Error( 'invalid_channel', 'Der angegebene Vertriebskanal wird nicht unterstützt.', [ 'status' => 400 ] );
		}

		// 1. Validierung (Signatur prüfen)
		$is_valid = $adapter->validate_request( $request );
		if ( is_wp_error( $is_valid ) ) {
			return $is_valid;
		}

		// 2. Payload parsen
		$payload = $request->get_json_params();
		if ( empty( $payload ) ) {
			return new \WP_Error( 'empty_payload', 'Kein gültiger JSON Payload gefunden.', [ 'status' => 400 ] );
		}

		// 3. Normalisieren
		$normalized_data = $adapter->normalize_payload( $payload );

		// 4. Order anlegen
		$importer = new Order_Importer();
		$order_id = $importer->import_order( $normalized_data );

		if ( is_wp_error( $order_id ) ) {
			return new \WP_Error( 'import_failed', 'Bestellung konnte nicht angelegt werden: ' . $order_id->get_error_message(), [ 'status' => 500 ] );
		}

		return new \WP_REST_Response( [
			'success'  => true,
			'order_id' => $order_id,
			'message'  => 'Bestellung erfolgreich importiert und an Print-Engine übergeben.'
		], 201 );
	}

	/**
	 * Factory-Methode für die Channel Adapter
	 * 
	 * @param string $channel
	 * @return Channel_Adapter_Interface|null
	 */
	private function get_adapter( string $channel ): ?Channel_Adapter_Interface {
		switch ( strtolower( $channel ) ) {
			case 'amazon':
				return new Amazon_Adapter();
			// Weitere Kanäle können hier registriert werden (Etsy etc.)
			default:
				return null;
		}
	}
}
