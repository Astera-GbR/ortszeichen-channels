<?php
namespace Ortszeichen\Channels\Adapters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Etsy_Adapter implements Channel_Adapter_Interface {

	public function validate_request( \WP_REST_Request $request ) {
		// Validierung für Etsy Webhooks
		return true; // Stub
	}

	public function normalize_payload( array $payload ): array {
		// Etsy Parsing Logik
		// Hier würden die "Personalization" Felder der Receipt-API ausgelesen.
		return [
			'channel_order_id' => $payload['receipt_id'] ?? '',
			'customer'         => [
				'first_name' => $payload['name'] ?? '',
				// ...
			],
			'items'            => []
		];
	}
}
