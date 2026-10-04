<?php
/**
 * Estimate rules.
 *
 * @package InstantQuoteForm
 */

class Test_IQF_Pricing extends WP_UnitTestCase {

	private function s() {
		return IQF_Settings::defaults();
	}

	public function test_basic_window_job() {
		$e = IQF_Pricing::estimate( array( 'service' => 'windows', 'quantity' => 24, 'stories' => 1, 'frequency' => 'once' ), $this->s() );
		$this->assertSame( 162.0, $e['total'] );
	}

	public function test_two_story_multiplier() {
		$e = IQF_Pricing::estimate( array( 'service' => 'windows', 'quantity' => 24, 'stories' => 2 ), $this->s() );
		$this->assertSame( 186.3, $e['total'] );
	}

	public function test_minimum_visit_charge_applies() {
		$e = IQF_Pricing::estimate( array( 'service' => 'windows', 'quantity' => 10 ), $this->s() );
		$this->assertSame( 129.0, $e['total'] );
		$this->assertSame( 'Minimum visit charge', $e['lines'][1]['label'] );
	}

	public function test_extras_and_quarterly_discount() {
		$e = IQF_Pricing::estimate(
			array( 'service' => 'windows', 'quantity' => 30, 'frequency' => 'quarterly', 'extras' => array( 'screens', 'hard_water' ) ),
			$this->s()
		);
		// 30 x 6.75 = 202.50, + 45 + 60 = 307.50, less 10 percent = 276.75
		$this->assertSame( 307.5, $e['subtotal'] );
		$this->assertSame( 30.75, $e['discount'] );
		$this->assertSame( 276.75, $e['total'] );
	}

	public function test_extra_for_another_service_is_ignored() {
		$e = IQF_Pricing::estimate( array( 'service' => 'windows', 'quantity' => 30, 'extras' => array( 'guards' ) ), $this->s() );
		$this->assertSame( 202.5, $e['total'] );
	}

	public function test_unknown_or_disabled_service_is_rejected() {
		$s                                   = $this->s();
		$s['services']['pressure']['enabled'] = 0;
		$this->assertWPError( IQF_Pricing::estimate( array( 'service' => 'roof', 'quantity' => 3 ), $s ) );
		$this->assertWPError( IQF_Pricing::estimate( array( 'service' => 'pressure', 'quantity' => 300 ), $s ) );
	}

	public function test_quantity_bounds() {
		$this->assertWPError( IQF_Pricing::estimate( array( 'service' => 'windows', 'quantity' => 0 ), $this->s() ) );
		$this->assertWPError( IQF_Pricing::estimate( array( 'service' => 'windows', 'quantity' => 201 ), $this->s() ) );
	}
}
