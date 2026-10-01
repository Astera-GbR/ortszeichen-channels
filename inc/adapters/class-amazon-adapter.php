<?php
namespace Ortszeichen\Channels\Adapters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Amazon Custom Adapter
 * 
 * VORGABE FÜR AMAZON SELLER CENTRAL:
 * Beim Anlegen des Produkts in Amazon Custom müssen 5 Text-Anpassungsflächen (Text Customization)
 * mit exakt folgenden "Label"-Namen angelegt werden:
 * 1. "City" (Pflichtfeld)
 * 2. "Subtitle" (Optional)
 * 3. "Latitude" (Pflichtfeld, z.B. 53.5511)
 * 4. "Longitude" (Pflichtfeld, z.B. 9.9937)
 * 5. "Style" (Dropdown/Text, z.B. STADTCODE, ORTSRASTER, MINIMAL)
 */
class Amazon_Adapter implements Channel_Adapter_Interface {

	public function validate_request( \WP_REST_Request $request ) {
		$api_key = $request->get_header( 'X-Amazon-Webhook-Secret' );
		if ( $api_key !== get_option( 'oz_channels_amazon_secret', 'DEFAULT_SECRET' ) ) {
			return new \WP_Error( 'forbidden', 'Invalid API key for Amazon channel', [ 'status' => 403 ] );
		}
		return true;
	}

	public function normalize_payload( array $payload ): array {
		$normalized = [
			'channel_order_id' => sanitize_text_field( $payload['AmazonOrderId'] ?? '' ),
			'customer'         => [
				'first_name' => sanitize_text_field( $payload['ShippingAddress']['Name'] ?? '' ),
				'last_name'  => '', 
				'email'      => sanitize_email( $payload['BuyerInfo']['BuyerEmail'] ?? '' ),
				'address_1'  => sanitize_text_field( $payload['ShippingAddress']['AddressLine1'] ?? '' ),
				'city'       => sanitize_text_field( $payload['ShippingAddress']['City'] ?? '' ),
				'postcode'   => sanitize_text_field( $payload['ShippingAddress']['PostalCode'] ?? '' ),
				'country'    => sanitize_text_field( $payload['ShippingAddress']['CountryCode'] ?? '' ),
			],
			'items'            => []
		];

		if ( ! empty( $payload['OrderItems'] ) && is_array( $payload['OrderItems'] ) ) {
			foreach ( $payload['OrderItems'] as $item ) {
				$customization = [];
				
				// Amazon Custom Daten (Format: SP-API Order Metrics JSON)
				if ( ! empty( $item['CustomizationInfo'] ) && is_array( $item['CustomizationInfo'] ) ) {
					// Mappe die Amazon-Labels exakt auf unser internes Meta-Format
					foreach ( $item['CustomizationInfo'] as $custom_field ) {
						$label = trim( $custom_field['Label'] ?? '' );
						$val   = trim( $custom_field['Value'] ?? '' );

						switch ( strtolower( $label ) ) {
							case 'city':
								$customization['_oz_location_city'] = sanitize_text_field( $val );
								break;
							case 'subtitle':
								$customization['_oz_location_subtitle'] = sanitize_text_field( $val );
								break;
							case 'latitude':
								$customization['_oz_location_lat'] = sanitize_text_field( $val );
								break;
							case 'longitude':
								$customization['_oz_location_lng'] = sanitize_text_field( $val );
								break;
							case 'style':
								$customization['_oz_style'] = sanitize_text_field( $val );
								break;
						}
					}
				}

				// Fallbacks setzen, falls ein Feld auf Amazon leer gelassen wurde
				if ( empty( $customization['_oz_location_city'] ) ) {
					$customization['_oz_location_city'] = 'Berlin'; // Safe fallback
				}
				if ( empty( $customization['_oz_style'] ) ) {
					$customization['_oz_style'] = 'STADTCODE';
				}

				$normalized['items'][] = [
					'sku'           => sanitize_text_field( $item['SellerSKU'] ?? '' ),
					'quantity'      => (int) ( $item['QuantityOrdered'] ?? 1 ),
					'price'         => (float) ( $item['ItemPrice']['Amount'] ?? 0.00 ),
					'location_meta' => $customization
				];
			}
		}

		return $normalized;
	}
}
