<?php
/**
 * Uninstall: remove the settings, every stored request and its meta, and
 * the rate-limit and result transients.
 *
 * @package InstantQuoteForm
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'iqf_settings' );

$iqf_ids = get_posts(
	array(
		'post_type'      => 'iqf_request',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $iqf_ids as $iqf_id ) {
	wp_delete_post( $iqf_id, true );
}

global $wpdb;
// Look the rows up, then delete through the options API so caches are cleared too.
$iqf_options = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_iqf\\_%' OR option_name LIKE '\\_transient\\_timeout\\_iqf\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( $iqf_options as $iqf_option ) {
	delete_option( $iqf_option );
}
