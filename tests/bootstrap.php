<?php
/**
 * PHPUnit bootstrap for the WordPress test suite (wp-env provides WP_TESTS_DIR).
 *
 * @package InstantQuoteForm
 */

$iqf_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/wordpress-phpunit';
if ( ! file_exists( $iqf_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test library not found in {$iqf_tests_dir}. Run the tests with: npm run test\n"; // phpcs:ignore
	exit( 1 );
}
require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $iqf_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/instant-quote-form/instant-quote-form.php';
	}
);

require $iqf_tests_dir . '/includes/bootstrap.php';
