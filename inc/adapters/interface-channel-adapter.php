<?php
namespace Ortszeichen\Channels\Adapters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Channel_Adapter_Interface
 * Jeder Channel (Amazon, Etsy, Shopify etc.) muss dieses Interface implementieren,
 * um eingehende Payload-Daten in das standardisierte ORTSZEICHEN-Format zu übersetzen.
 */
interface Channel_Adapter_Interface {

	/**
	 * Prüft, ob der Payload authentisch und für diesen Adapter bestimmt ist.
	 * 
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function validate_request( \WP_REST_Request $request );

	/**
	 * Konvertiert den kanalspezifischen Payload in ein standardisiertes Order-Array.
	 * 
	 * Erwartetes Rückgabeformat:
	 * [
	 *   'channel_order_id' => '114-1234567-8901234',
	 *   'customer' => [
	 *      'first_name' => '...',
	 *      'last_name'  => '...',
	 *      'email'      => '...',
	 *      'address_1'  => '...',
	 *      ...
	 *   ],
	 *   'items' => [
	 *      [
	 *         'sku' => 'POSTER-A3-OAK',
	 *         'quantity' => 1,
	 *         'price' => 49.90,
	 *         // Hier passiert die Magie: Mapping der Amazon Custom / Etsy Daten auf unser Schema
	 *         'location_meta' => [
	 *            '_oz_location_city' => 'Hamburg',
	 *            '_oz_location_subtitle' => 'Deutschland',
	 *            '_oz_location_lat' => '53.5511',
	 *            '_oz_location_lng' => '9.9937',
	 *            '_oz_style' => 'STADTCODE'
	 *         ]
	 *      ]
	 *   ]
	 * ]
	 * 
	 * @param array $payload
	 * @return array Standardisiertes Array
	 */
	public function normalize_payload( array $payload ): array;
}
