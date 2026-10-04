<?php
/**
 * Settings sanitizing, capability checks, status saving, block and shortcode.
 *
 * @package InstantQuoteForm
 */

class Test_IQF_Admin extends WP_UnitTestCase {

	public function test_sanitize_clamps_and_drops_bad_values() {
		$out = IQF_Settings::sanitize(
			array(
				'notify_email'        => 'not-an-email',
				'currency'            => '<b>USD$</b>',
				'services'            => array( 'windows' => array( 'enabled' => '1', 'price' => '-4', 'max' => '0', 'label' => '<em>Windows</em>' ) ),
				'frequency_discounts' => array( 'monthly' => '400' ),
				'evil_key'            => 'x',
			)
		);
		$this->assertSame( IQF_Settings::defaults()['notify_email'], $out['notify_email'] );
		$this->assertSame( 0.0, $out['services']['windows']['price'] );
		$this->assertSame( 1, $out['services']['windows']['max'] );
		$this->assertSame( 'Windows', $out['services']['windows']['label'] );
		$this->assertEquals( 90, $out['frequency_discounts']['monthly'] );
		$this->assertSame( 0, $out['services']['gutters']['enabled'] );
		$this->assertArrayNotHasKey( 'evil_key', $out );
		$this->assertLessThanOrEqual( 3, mb_strlen( $out['currency'] ) );
	}

	public function test_settings_page_requires_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->expectException( WPDieException::class );
		IQF_Settings::render();
	}

	public function test_requests_cannot_be_created_by_hand() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertFalse( current_user_can( get_post_type_object( IQF_Requests::POST_TYPE )->cap->create_posts ) );
		$this->assertFalse( get_post_type_object( IQF_Requests::POST_TYPE )->public );
	}

	private function request() {
		return self::factory()->post->create( array( 'post_type' => IQF_Requests::POST_TYPE, 'meta_input' => array( '_iqf_status' => 'new' ) ) );
	}

	public function test_status_saves_with_nonce_and_capability() {
		$id = $this->request();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_POST = array( 'iqf_status_nonce' => wp_create_nonce( 'iqf_status_' . $id ), 'iqf_status' => 'booked' );
		IQF_Requests::save_status( $id, get_post( $id ) );
		$this->assertSame( 'booked', get_post_meta( $id, '_iqf_status', true ) );
	}

	public function test_status_ignored_without_nonce_or_for_subscribers() {
		$id = $this->request();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_POST = array( 'iqf_status' => 'booked' );
		IQF_Requests::save_status( $id, get_post( $id ) );
		$this->assertSame( 'new', get_post_meta( $id, '_iqf_status', true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = array( 'iqf_status_nonce' => wp_create_nonce( 'iqf_status_' . $id ), 'iqf_status' => 'closed' );
		IQF_Requests::save_status( $id, get_post( $id ) );
		$this->assertSame( 'new', get_post_meta( $id, '_iqf_status', true ) );
	}

	public function test_shortcode_and_block_render_the_form_with_a_nonce() {
		$html = do_shortcode( '[instant_quote heading="Get a window quote"]' );
		$this->assertStringContainsString( 'Get a window quote', $html );
		$this->assertStringContainsString( 'name="iqf_nonce"', $html );
		$this->assertStringContainsString( 'name="iqf_website"', $html );
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'instant-quote-form/quote-form' ) );
		$block = render_block( array( 'blockName' => 'instant-quote-form/quote-form', 'attrs' => array( 'heading' => 'Block heading' ), 'innerBlocks' => array() ) );
		$this->assertStringContainsString( 'Block heading', $block );
	}

	public function test_service_tiles_use_the_singular_unit() {
		$html = do_shortcode( '[instant_quote]' );
		$this->assertStringContainsString( 'per foot of gutter', $html );
		$this->assertStringNotContainsString( 'per feet of gutter', $html );
	}
}
