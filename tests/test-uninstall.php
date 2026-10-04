<?php
/**
 * Uninstall leaves nothing behind.
 *
 * @package InstantQuoteForm
 */

class Test_IQF_Uninstall extends WP_UnitTestCase {

	public function test_uninstall_removes_settings_requests_and_transients() {
		update_option( IQF_Settings::OPTION, IQF_Settings::defaults() );
		$ids = self::factory()->post->create_many( 3, array( 'post_type' => IQF_Requests::POST_TYPE, 'meta_input' => array( '_iqf_total' => 210 ) ) );
		set_transient( 'iqf_rl_test', 2, HOUR_IN_SECONDS );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'instant-quote-form/instant-quote-form.php' );
		}
		include dirname( __DIR__ ) . '/instant-quote-form/uninstall.php';

		$this->assertFalse( get_option( IQF_Settings::OPTION ) );
		foreach ( $ids as $id ) {
			$this->assertNull( get_post( $id ) );
			$this->assertSame( '', get_post_meta( $id, '_iqf_total', true ) );
		}
		$this->assertFalse( get_transient( 'iqf_rl_test' ) );
	}
}
