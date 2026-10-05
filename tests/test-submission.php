<?php
/**
 * The submit path: nonce, honeypot, rate limit, validation, storage and email.
 *
 * @package InstantQuoteForm
 */

class Test_IQF_Submission extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		reset_phpmailer_instance();
		// The test site runs on localhost, and PHPMailer rejects wordpress@localhost as a sender.
		add_filter( 'wp_mail_from', static function () {
			return 'wordpress@ruizwindows.test';
		} );
		update_option( IQF_Settings::OPTION, array_merge( IQF_Settings::defaults(), array( 'notify_email' => 'owner@ruizwindows.test' ) ) );
	}

	private function post( array $over = array() ) {
		return array_merge(
			array(
				'iqf_nonce' => wp_create_nonce( IQF_Form::ACTION ),
				'service'   => 'gutters',
				'quantity'  => '180',
				'stories'   => '2',
				'frequency' => 'once',
				'extras'    => array( 'guards' ),
				'name'      => 'Dana Whitlock',
				'email'     => 'dana.whitlock@example.com',
				'phone'     => '(503) 555-0147',
				'date'      => wp_date( 'Y-m-d', strtotime( '+5 days' ) ),
				'message'   => 'Side gate code is 4417.',
				'total'     => '1.00',
			),
			$over
		);
	}

	public function test_valid_request_is_stored_with_a_server_side_estimate() {
		$r = IQF_Form::process( $this->post(), '203.0.113.7' );
		$this->assertTrue( $r['ok'], implode( ', ', $r['errors'] ) );
		$this->assertSame( IQF_Requests::POST_TYPE, get_post_type( $r['id'] ) );
		$this->assertSame( 'new', get_post_meta( $r['id'], '_iqf_status', true ) );
		// 180 ft x 1.35 x 1.15 = 279.45, + 35 guard check; the posted "total" of 1.00 is ignored.
		$this->assertEquals( 314.45, (float) get_post_meta( $r['id'], '_iqf_total', true ) );
		$this->assertSame( 'dana.whitlock@example.com', get_post_meta( $r['id'], '_iqf_email', true ) );
	}

	public function test_owner_and_customer_emails_are_sent() {
		IQF_Form::process( $this->post(), '203.0.113.8' );
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertSame( 'owner@ruizwindows.test', $mailer->get_recipient( 'to', 0 )->address );
		$this->assertStringContainsString( 'Gutter cleaning for Dana Whitlock', $mailer->get_sent( 0 )->subject );
		$this->assertStringContainsString( '314.45', $mailer->get_sent( 0 )->body );
		$this->assertSame( 'dana.whitlock@example.com', $mailer->get_recipient( 'to', 1 )->address );
	}

	public function test_customer_copy_can_be_turned_off() {
		$s                  = IQF_Settings::get();
		$s['customer_copy'] = 0;
		update_option( IQF_Settings::OPTION, $s );
		IQF_Form::process( $this->post(), '203.0.113.9' );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent( 1 ) );
	}

	public function test_bad_nonce_is_rejected_and_nothing_is_stored() {
		$r = IQF_Form::process( $this->post( array( 'iqf_nonce' => 'forged' ) ), '203.0.113.10' );
		$this->assertFalse( $r['ok'] );
		$this->assertSame( 0, IQF_Requests::count_new() );
	}

	public function test_honeypot_returns_ok_but_stores_nothing() {
		$r = IQF_Form::process( $this->post( array( 'iqf_website' => 'http://spam.example' ) ), '203.0.113.11' );
		$this->assertTrue( $r['ok'] );
		$this->assertArrayNotHasKey( 'id', $r );
		$this->assertSame( 0, IQF_Requests::count_new() );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent( 0 ) );
	}

	public function test_rate_limit_after_five_requests_per_connection() {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertTrue( IQF_Form::process( $this->post(), '198.51.100.20' )['ok'] );
		}
		$sixth = IQF_Form::process( $this->post(), '198.51.100.20' );
		$this->assertFalse( $sixth['ok'] );
		$this->assertTrue( IQF_Form::process( $this->post(), '198.51.100.21' )['ok'] );
	}

	public function test_invalid_fields_come_back_as_errors_with_the_input_kept() {
		$r = IQF_Form::process(
			$this->post( array( 'email' => 'dana@', 'name' => '', 'date' => '2020-01-01', 'quantity' => '0' ) ),
			'203.0.113.12'
		);
		$this->assertFalse( $r['ok'] );
		$this->assertCount( 4, $r['errors'] );
		$this->assertSame( 'gutters', $r['old']['service'] );
	}

	public function test_markup_is_escaped_not_stored_raw() {
		$r = IQF_Form::process( $this->post( array( 'name' => '<script>alert(1)</script>Dana' ) ), '203.0.113.13' );
		$this->assertTrue( $r['ok'] );
		$this->assertStringNotContainsString( '<script>', get_post_meta( $r['id'], '_iqf_name', true ) );
	}
}
