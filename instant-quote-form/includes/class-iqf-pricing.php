<?php
/**
 * Price estimate. Pure functions, so the browser preview and the server
 * use the same rules and the stored estimate never trusts the browser.
 *
 * @package InstantQuoteForm
 */

defined( 'ABSPATH' ) || exit;

/**
 * Estimate calculator.
 */
class IQF_Pricing {

	/**
	 * Estimate a job.
	 *
	 * @param array $input    service, quantity, stories, frequency, extras[].
	 * @param array $settings Plugin settings (rates, discounts, extras).
	 * @return array{subtotal: float, discount: float, total: float, lines: array}|WP_Error
	 */
	public static function estimate( array $input, array $settings ) {
		$services = $settings['services'] ?? array();
		$service  = (string) ( $input['service'] ?? '' );
		if ( ! isset( $services[ $service ] ) || empty( $services[ $service ]['enabled'] ) ) {
			return new WP_Error( 'iqf_service', __( 'Please choose a service.', 'instant-quote-form' ) );
		}
		$rate     = $services[ $service ];
		$quantity = (int) ( $input['quantity'] ?? 0 );
		if ( $quantity < 1 || $quantity > (int) $rate['max'] ) {
			/* translators: 1: unit name, 2: maximum. */
			return new WP_Error( 'iqf_quantity', sprintf( __( 'Enter between 1 and %2$d %1$s.', 'instant-quote-form' ), $rate['unit'], (int) $rate['max'] ) );
		}

		$stories     = max( 1, min( 3, (int) ( $input['stories'] ?? 1 ) ) );
		$story_mult  = (float) ( $settings['story_multipliers'][ $stories ] ?? 1 );
		$base        = round( $quantity * (float) $rate['price'] * $story_mult, 2 );
		$lines       = array();
		$lines[]     = array(
			/* translators: 1: quantity, 2: unit, 3: service name. */
			'label'  => sprintf( __( '%1$d %2$s, %3$s', 'instant-quote-form' ), $quantity, $rate['unit'], $rate['label'] ),
			'amount' => $base,
		);
		if ( $base < (float) $rate['minimum'] ) {
			$lines[] = array(
				'label'  => __( 'Minimum visit charge', 'instant-quote-form' ),
				'amount' => round( (float) $rate['minimum'] - $base, 2 ),
			);
			$base    = (float) $rate['minimum'];
		}

		$extras_total = 0.0;
		foreach ( (array) ( $input['extras'] ?? array() ) as $key ) {
			$extra = $settings['extras'][ $key ] ?? null;
			if ( $extra && in_array( $service, (array) $extra['services'], true ) ) {
				$extras_total += (float) $extra['price'];
				$lines[]       = array(
					'label'  => $extra['label'],
					'amount' => (float) $extra['price'],
				);
			}
		}

		$subtotal  = round( $base + $extras_total, 2 );
		$frequency = (string) ( $input['frequency'] ?? 'once' );
		$pct       = (float) ( $settings['frequency_discounts'][ $frequency ] ?? 0 );
		$discount  = round( $subtotal * $pct / 100, 2 );
		if ( $discount > 0 ) {
			$lines[] = array(
				/* translators: %s: discount percent. */
				'label'  => sprintf( __( 'Repeat service discount (%s%%)', 'instant-quote-form' ), rtrim( rtrim( number_format( $pct, 1 ), '0' ), '.' ) ),
				'amount' => -$discount,
			);
		}

		return array(
			'subtotal' => $subtotal,
			'discount' => $discount,
			'total'    => round( $subtotal - $discount, 2 ),
			'lines'    => $lines,
		);
	}

	/**
	 * Format money with the configured currency symbol.
	 *
	 * @param float $amount Amount.
	 * @param array $settings Settings.
	 */
	public static function money( $amount, array $settings ) {
		$symbol = $settings['currency'] ?? '$';
		$sign   = $amount < 0 ? '-' : '';
		return $sign . $symbol . number_format_i18n( abs( (float) $amount ), 2 );
	}
}
