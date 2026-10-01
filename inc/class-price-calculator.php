<?php
namespace Ortszeichen\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Price_Calculator {

	/**
	 * Berechnet den neuen Preis basierend auf einer dynamischen Formel.
	 * Unterstützt sichere, einfache Rechenoperationen wie "* 1.15 + 4.90".
	 * 
	 * @param float $base_price Der WooCommerce Basispreis
	 * @param string $formula Die Konfigurierte Formel (z. B. "* 1.15 + 4.90")
	 * @return float Berechneter Preis
	 */
	public static function apply_formula( float $base_price, string $formula ): float {
		$formula = trim( $formula );
		if ( empty( $formula ) ) {
			return $base_price;
		}

		$current_price = $base_price;

		// Regex: Findet Operatoren (+, -, *, /) und Zahlen (inkl. Dezimalpunkt)
		// z.B. "* 1.15" => [0] => "* 1.15", [1] => "*", [2] => "1.15"
		preg_match_all( '/([\+\-\*\/])\s*([\d\.]+)/', $formula, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$operator = $match[1];
			$operand  = (float) $match[2];

			switch ( $operator ) {
				case '*':
					$current_price *= $operand;
					break;
				case '/':
					if ( $operand != 0 ) $current_price /= $operand;
					break;
				case '+':
					$current_price += $operand;
					break;
				case '-':
					$current_price -= $operand;
					break;
			}
		}

		// Runden auf 2 Nachkommastellen (kaufmännisch)
		return round( $current_price, 2 );
	}
}
