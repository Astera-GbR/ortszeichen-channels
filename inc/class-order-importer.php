<?php
namespace Ortszeichen\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Order_Importer {

	/**
	 * Erstellt eine WooCommerce-Bestellung aus dem normalisierten Payload
	 * 
	 * @param array $data Standardisiertes Array aus dem Adapter
	 * @return int|\WP_Error Order ID bei Erfolg, sonst Fehler
	 */
	public function import_order( array $data ) {
		if ( empty( $data['channel_order_id'] ) ) { return new \WP_Error( 'missing_identity', 'Externe Bestellkennung fehlt.' ); }
		$lock = 'oz_channel_import_' . hash( 'sha256', ( $data['channel'] ?? 'external' ) . ':' . $data['channel_order_id'] );
		// add_option uses the unique option_name index: simultaneous requests cannot both enter.
		if ( ! add_option( $lock, time(), '', false ) ) { return new \WP_Error( 'import_locked', 'Import l�uft oder ben�tigt manuelle Wiederherstellung.', array( 'status' => 409 ) ); }
		try { return $this->import_locked( $data ); }
		finally { delete_option( $lock ); }
	}

	private function import_locked( array $data ) {
		if ( empty( $data['items'] ) ) {
			return new \WP_Error( 'no_items', 'Bestellung hat keine Artikel.' );
		}

		// Imported payloads do not establish payment or permission to manufacture.
		if ( empty( $data['channel_order_id'] ) ) { return new \WP_Error( 'missing_identity', 'Externe Bestellkennung fehlt.' ); }
		$identity = hash( 'sha256', ( $data['channel'] ?? 'external' ) . ':' . $data['channel_order_id'] );
		$existing = wc_get_orders( array( 'limit' => 1, 'meta_key' => '_oz_channel_external_key', 'meta_value' => $identity ) );
		if ( $existing ) { return $existing[0]->get_id(); }
		foreach ( $data['items'] as $line ) {
			$product = wc_get_product( wc_get_product_id_by_sku( $line['sku'] ?? '' ) );
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() || ! is_numeric( $line['quantity'] ?? null ) || $line['quantity'] < 1 || floor( $line['quantity'] ) != $line['quantity'] || ! is_numeric( $line['price'] ?? null ) || ! is_finite( (float) $line['price'] ) || $line['price'] <= 0 || ! empty( $line['location_meta'] ) ) {
				return new \WP_Error( 'unapproved_item', 'Unbekannter, ungepr�fter oder personalisierter Artikel.' );
			}
		}
		$order = wc_create_order();
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		// Adressdaten setzen
		$address = [
			'first_name' => $data['customer']['first_name'] ?? '',
			'last_name'  => $data['customer']['last_name'] ?? '',
			'email'      => $data['customer']['email'] ?? '',
			'address_1'  => $data['customer']['address_1'] ?? '',
			'city'       => $data['customer']['city'] ?? '',
			'postcode'   => $data['customer']['postcode'] ?? '',
			'country'    => $data['customer']['country'] ?? '',
		];

		$order->set_address( $address, 'billing' );
		$order->set_address( $address, 'shipping' );

		// Metadaten für Channel Tracking
		$order->update_meta_data( '_oz_channel', $data['channel'] ?? 'external' );
		$order->update_meta_data( '_oz_channel_external_key', $identity );
		$order->update_meta_data( '_oz_channel_order_id', $data['channel_order_id'] ?? '' );

		// Artikel hinzufügen
		foreach ( $data['items'] as $item_data ) {
			// Versuche das Produkt anhand der SKU zu finden
			$product_id = wc_get_product_id_by_sku( $item_data['sku'] );
			if ( ! $product_id ) {
				// Fallback: Als Generic-Item hinzufügen, falls SKU nicht existiert
				$item = new \WC_Order_Item_Product();
				$item->set_name( 'Externes Produkt: ' . $item_data['sku'] );
				$item->set_quantity( $item_data['quantity'] );
				$item->set_subtotal( $item_data['price'] * $item_data['quantity'] );
				$item->set_total( $item_data['price'] * $item_data['quantity'] );
			} else {
				$product = wc_get_product( $product_id );
				$item = new \WC_Order_Item_Product();
				$item->set_props( [
					'product'  => $product,
					'quantity' => $item_data['quantity'],
					'subtotal' => $item_data['price'] * $item_data['quantity'],
					'total'    => $item_data['price'] * $item_data['quantity'],
				] );
			}

			// Das WICHTIGSTE: Personalisierungsdaten anhängen!
			// Die Print-Engine (class-print-engine.php) horcht auf diese Felder.
			if ( ! empty( $item_data['location_meta'] ) ) {
				foreach ( $item_data['location_meta'] as $meta_key => $meta_value ) {
					$item->add_meta_data( $meta_key, $meta_value, true );
					
					// Auch als sichtbare Meta für den Kunden im Dashboard / E-Mail anzeigen (falls gewünscht)
					if ( strpos( $meta_key, '_oz_' ) === 0 ) {
						$label = str_replace( '_oz_location_', '', $meta_key );
						$item->add_meta_data( ucfirst( $label ), $meta_value, true );
					}
				}
			}

			$order->add_item( $item );
		}

		$order->calculate_totals();
		
		// Status auf Processing setzen -> Das feuert den Webhook an Gelato (Print-Engine)!
		$order->update_status( 'on-hold', 'Importiert via ORTSZEICHEN Channels (ID: ' . $data['channel_order_id'] . ').' );
		
		return $order->get_id();
	}
}
