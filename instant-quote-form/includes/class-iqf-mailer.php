<?php
/**
 * Email alerts: one to the business, an optional copy to the customer.
 *
 * @package InstantQuoteForm
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notifications.
 */
class IQF_Mailer {

	/**
	 * Send the alerts for a stored request.
	 *
	 * @param int   $post_id  Request ID.
	 * @param array $data     Clean form data.
	 * @param array $estimate Estimate.
	 * @return array{owner: bool, customer: bool|null}
	 */
	public static function send( $post_id, array $data, array $estimate ) {
		$s       = IQF_Settings::get();
		$money   = static function ( $v ) use ( $s ) {
			return IQF_Pricing::money( $v, $s );
		};
		$summary = array();
		foreach ( $estimate['lines'] as $line ) {
			$summary[] = sprintf( '  %s: %s', $line['label'], $money( $line['amount'] ) );
		}
		$summary[] = sprintf( '  %s: %s', __( 'Estimated total', 'instant-quote-form' ), $money( $estimate['total'] ) );
		$summary   = implode( "\n", $summary );

		/* translators: 1: service, 2: customer name. */
		$subject = sprintf( __( 'New quote request: %1$s for %2$s', 'instant-quote-form' ), $data['service_label'], $data['name'] );
		$body    = implode(
			"\n",
			array(
				/* translators: %s: business name. */
				sprintf( __( 'A new quote request came in through the %s website.', 'instant-quote-form' ), $s['company'] ),
				'',
				sprintf( '%s: %s', __( 'Name', 'instant-quote-form' ), $data['name'] ),
				sprintf( '%s: %s', __( 'Email', 'instant-quote-form' ), $data['email'] ),
				sprintf( '%s: %s', __( 'Phone', 'instant-quote-form' ), $data['phone'] ?: '-' ),
				sprintf( '%s: %s', __( 'Preferred date', 'instant-quote-form' ), $data['date'] ?: __( 'Flexible', 'instant-quote-form' ) ),
				'',
				$summary,
				'',
				sprintf( '%s: %s', __( 'Notes', 'instant-quote-form' ), $data['message'] ?: '-' ),
				'',
				admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ),
			)
		);
		$headers = array( 'Reply-To: ' . $data['name'] . ' <' . $data['email'] . '>' );
		$owner   = wp_mail( $s['notify_email'], $subject, $body, $headers );

		$customer = null;
		if ( ! empty( $s['customer_copy'] ) ) {
			/* translators: %s: business name. */
			$c_subject = sprintf( __( 'Your estimate from %s', 'instant-quote-form' ), $s['company'] );
			$c_body    = implode(
				"\n",
				array(
					/* translators: %s: customer first name. */
					sprintf( __( 'Hi %s,', 'instant-quote-form' ), strtok( $data['name'], ' ' ) ),
					'',
					__( 'Thanks for your request. Here is the estimate you saw on the website:', 'instant-quote-form' ),
					'',
					$summary,
					'',
					$s['success_message'],
					'',
					$s['company'],
				)
			);
			$customer  = wp_mail( $data['email'], $c_subject, $c_body );
		}
		return array(
			'owner'    => (bool) $owner,
			'customer' => $customer,
		);
	}
}
