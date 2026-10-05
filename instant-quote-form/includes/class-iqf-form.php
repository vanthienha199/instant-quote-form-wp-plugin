<?php
/**
 * Front end: the [instant_quote] shortcode, the Instant Quote block, and the
 * submit handler (admin-post.php, logged-in and logged-out).
 *
 * @package InstantQuoteForm
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quote form.
 */
class IQF_Form {

	const ACTION     = 'iqf_submit';
	const RATE_LIMIT = 5;

	/**
	 * Hook in.
	 */
	public static function init() {
		add_shortcode( 'instant_quote', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'init', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Front-end assets, enqueued only when a form is on the page.
	 */
	public static function register_assets() {
		wp_register_style( 'iqf-form', IQF_URL . 'assets/css/form.css', array(), IQF_VERSION );
		wp_register_script( 'iqf-form', IQF_URL . 'assets/js/form.js', array(), IQF_VERSION, array( 'strategy' => 'defer' ) );
	}

	/**
	 * Block: server-rendered so the shortcode and the block share one template.
	 */
	public static function register_block() {
		wp_register_script(
			'iqf-block-editor',
			IQF_URL . 'blocks/quote-form/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			IQF_VERSION,
			true
		);
		register_block_type(
			IQF_DIR . 'blocks/quote-form',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
				'editor_script'   => 'iqf-block-editor',
				'style'           => 'iqf-form',
			)
		);
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attrs Block attributes.
	 */
	public static function render_block( $attrs ) {
		// The wrapper carries the block's alignment class (wide or full) from the editor.
		return '<div ' . get_block_wrapper_attributes() . '>' . self::render(
			array(
				'heading' => $attrs['heading'] ?? '',
				'intro'   => $attrs['intro'] ?? '',
			)
		) . '</div>';
	}

	/**
	 * Shortcode: [instant_quote heading="..." intro="..."].
	 *
	 * @param array|string $atts Attributes.
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'heading' => __( 'Get an instant estimate', 'instant-quote-form' ),
				'intro'   => '',
			),
			$atts,
			'instant_quote'
		);
		return self::render( $atts );
	}

	/**
	 * Form markup.
	 *
	 * @param array $atts heading, intro.
	 */
	public static function render( array $atts ) {
		if ( ! is_admin() ) {
			wp_enqueue_style( 'iqf-form' );
			wp_enqueue_script( 'iqf-form' );
		}
		$s        = IQF_Settings::get();
		$state    = self::read_state();
		$old      = $state['old'] ?? array();
		$errors   = $state['errors'] ?? array();
		$sent     = ! empty( $state['sent'] );
		$services = array_filter(
			$s['services'],
			static function ( $svc ) {
				return ! empty( $svc['enabled'] );
			}
		);
		$config   = array(
			'currency'  => $s['currency'],
			'services'  => $services,
			'stories'   => $s['story_multipliers'],
			'discounts' => $s['frequency_discounts'],
			'extras'    => $s['extras'],
		);
		$val      = static function ( $k, $default = '' ) use ( $old ) {
			return isset( $old[ $k ] ) ? $old[ $k ] : $default;
		};
		ob_start();
		?>
		<div class="iqf" id="iqf-form" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<?php if ( $atts['heading'] ) : ?>
				<h2 class="iqf-heading"><?php echo esc_html( $atts['heading'] ); ?></h2>
			<?php endif; ?>
			<?php if ( $atts['intro'] ) : ?>
				<p class="iqf-intro"><?php echo esc_html( $atts['intro'] ); ?></p>
			<?php endif; ?>

			<?php if ( $sent ) : ?>
				<div class="iqf-notice iqf-ok" role="status">
					<strong><?php esc_html_e( 'Request sent.', 'instant-quote-form' ); ?></strong>
					<?php echo esc_html( $s['success_message'] ); ?>
					<?php if ( isset( $state['total'] ) ) : ?>
						<span class="iqf-sent-total"><?php echo esc_html( IQF_Pricing::money( (float) $state['total'], $s ) ); ?></span>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<?php if ( $errors ) : ?>
					<div class="iqf-notice iqf-err" role="alert">
						<strong><?php esc_html_e( 'Please check the form.', 'instant-quote-form' ); ?></strong>
						<ul><?php foreach ( $errors as $e ) : ?><li><?php echo esc_html( $e ); ?></li><?php endforeach; ?></ul>
					</div>
				<?php endif; ?>
				<form class="iqf-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<input type="hidden" name="iqf_return" value="<?php echo esc_url( get_permalink() ?: home_url( '/' ) ); ?>">
					<?php wp_nonce_field( self::ACTION, 'iqf_nonce' ); ?>
					<div class="iqf-hp" aria-hidden="true"><label>Website<input type="text" name="iqf_website" tabindex="-1" autocomplete="off"></label></div>

					<div class="iqf-cols">
						<fieldset class="iqf-job">
							<legend class="iqf-sr"><?php esc_html_e( 'The job', 'instant-quote-form' ); ?></legend>
							<div class="iqf-services" role="radiogroup" aria-label="<?php esc_attr_e( 'Service', 'instant-quote-form' ); ?>">
								<?php
								$first = true;
								foreach ( $services as $key => $svc ) :
									$checked = $val( 'service' ) ? $val( 'service' ) === $key : $first;
									$first   = false;
									?>
									<label class="iqf-service">
										<input type="radio" name="service" value="<?php echo esc_attr( $key ); ?>" <?php checked( $checked ); ?>>
										<span><?php echo esc_html( $svc['label'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>

							<p class="iqf-step"><?php esc_html_e( 'Your house', 'instant-quote-form' ); ?></p>
							<div class="iqf-houses" role="radiogroup" aria-label="<?php esc_attr_e( 'House type', 'instant-quote-form' ); ?>">
								<?php
								$houses = array(
									1 => array( __( 'One story', 'instant-quote-form' ), 'M8 62V34L48 12l40 22v28M8 62h80M40 62V46h16v16M18 40h12v10H18zM66 40h12v10H66z' ),
									2 => array( __( 'Two stories', 'instant-quote-form' ), 'M14 62V28L48 6l34 22v34M14 62h68M42 62V48h12v14M22 34h10v9H22zM64 34h10v9H64zM22 48h10v9H22zM64 48h10v9H64z' ),
									3 => array( __( 'Three stories', 'instant-quote-form' ), 'M22 62V20L48 4l26 16v42M22 62h52M43 62V52h10v10M29 24h8v7h-8zM59 24h8v7h-8zM29 36h8v7h-8zM59 36h8v7h-8zM29 48h8v7h-8zM59 48h8v7h-8z' ),
								);
								foreach ( $houses as $n => $house ) :
									?>
									<label class="iqf-house">
										<input type="radio" name="stories" value="<?php echo (int) $n; ?>" <?php checked( (int) $val( 'stories', 1 ), $n ); ?>>
										<span><svg viewBox="0 0 96 68" aria-hidden="true"><path d="<?php echo esc_attr( $house[1] ); ?>"/></svg><?php echo esc_html( $house[0] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>

							<div class="iqf-qty">
								<label for="iqf-quantity" class="iqf-step" data-iqf-unit><?php esc_html_e( 'How many', 'instant-quote-form' ); ?></label>
								<div class="iqf-stepper">
									<button type="button" data-iqf-step="-1" aria-label="<?php esc_attr_e( 'Fewer', 'instant-quote-form' ); ?>">&minus;</button>
									<input id="iqf-quantity" type="number" name="quantity" min="1" inputmode="numeric" required value="<?php echo esc_attr( $val( 'quantity', 24 ) ); ?>">
									<button type="button" data-iqf-step="1" aria-label="<?php esc_attr_e( 'More', 'instant-quote-form' ); ?>">+</button>
								</div>
								<?php
								$active = $val( 'service' ) ? $val( 'service' ) : (string) array_key_first( $services );
								foreach ( $services as $key => $svc ) :
									?>
									<span class="iqf-rate" data-iqf-rate="<?php echo esc_attr( $key ); ?>" <?php echo $key === $active ? '' : 'hidden'; ?>>
										<?php
										/* translators: 1: price, 2: unit. */
										echo esc_html( sprintf( __( 'from %1$s per %2$s', 'instant-quote-form' ), IQF_Pricing::money( $svc['price'], $s ), $svc['unit_one'] ) );
										?>
									</span>
								<?php endforeach; ?>
							</div>

							<p class="iqf-step"><?php esc_html_e( 'How often', 'instant-quote-form' ); ?></p>
							<div class="iqf-freq" role="radiogroup" aria-label="<?php esc_attr_e( 'How often', 'instant-quote-form' ); ?>">
								<?php
								$freqs = array(
									'once'      => __( 'One time', 'instant-quote-form' ),
									/* translators: %s: percent */
									'quarterly' => sprintf( __( 'Quarterly, save %s%%', 'instant-quote-form' ), $s['frequency_discounts']['quarterly'] ),
									/* translators: %s: percent */
									'monthly'   => sprintf( __( 'Monthly, save %s%%', 'instant-quote-form' ), $s['frequency_discounts']['monthly'] ),
								);
								foreach ( $freqs as $f => $label ) :
									?>
									<label><input type="radio" name="frequency" value="<?php echo esc_attr( $f ); ?>" <?php checked( $val( 'frequency', 'once' ), $f ); ?>><span><?php echo esc_html( $label ); ?></span></label>
								<?php endforeach; ?>
							</div>

							<div class="iqf-extras">
								<?php foreach ( $s['extras'] as $key => $ex ) : ?>
									<label class="iqf-extra" data-services="<?php echo esc_attr( implode( ' ', $ex['services'] ) ); ?>">
										<input type="checkbox" name="extras[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $val( 'extras', array() ), true ) ); ?>>
										<span><?php echo esc_html( $ex['label'] ); ?></span>
										<em><?php echo $ex['price'] > 0 ? esc_html( '+' . IQF_Pricing::money( $ex['price'], $s ) ) : esc_html__( 'free', 'instant-quote-form' ); ?></em>
									</label>
								<?php endforeach; ?>
							</div>
						</fieldset>

						<aside class="iqf-estimate" aria-live="polite">
							<span class="iqf-est-label"><?php esc_html_e( 'Your estimate', 'instant-quote-form' ); ?></span>
							<?php
							$start = IQF_Pricing::estimate(
								array(
									'service'   => $val( 'service' ) ? $val( 'service' ) : (string) array_key_first( $services ),
									'quantity'  => $val( 'quantity', 24 ),
									'stories'   => $val( 'stories', 1 ),
									'frequency' => $val( 'frequency', 'once' ),
									'extras'    => (array) $val( 'extras', array() ),
								),
								$s
							);
							?>
							<output class="iqf-total" data-iqf-total><?php echo is_wp_error( $start ) ? '-' : esc_html( IQF_Pricing::money( $start['total'], $s ) ); ?></output>
							<ul class="iqf-breakdown" data-iqf-lines>
								<?php if ( ! is_wp_error( $start ) ) : ?>
									<?php foreach ( $start['lines'] as $line ) : ?>
										<li><span><?php echo esc_html( $line['label'] ); ?></span><b><?php echo esc_html( IQF_Pricing::money( $line['amount'], $s ) ); ?></b></li>
									<?php endforeach; ?>
								<?php endif; ?>
							</ul>
							<p class="iqf-fine"><?php esc_html_e( 'Final price is confirmed after a quick look at the property.', 'instant-quote-form' ); ?></p>
						</aside>
					</div>

					<fieldset class="iqf-contact">
						<legend><?php esc_html_e( 'Your details', 'instant-quote-form' ); ?></legend>
						<div class="iqf-row2">
							<label><?php esc_html_e( 'Full name', 'instant-quote-form' ); ?><input type="text" name="name" required autocomplete="name" value="<?php echo esc_attr( $val( 'name' ) ); ?>"></label>
							<label><?php esc_html_e( 'Email', 'instant-quote-form' ); ?><input type="email" name="email" required autocomplete="email" value="<?php echo esc_attr( $val( 'email' ) ); ?>"></label>
							<label><?php esc_html_e( 'Phone', 'instant-quote-form' ); ?> <small><?php esc_html_e( '(optional)', 'instant-quote-form' ); ?></small><input type="tel" name="phone" autocomplete="tel" value="<?php echo esc_attr( $val( 'phone' ) ); ?>"></label>
							<label><?php esc_html_e( 'Preferred date', 'instant-quote-form' ); ?> <small><?php esc_html_e( '(optional)', 'instant-quote-form' ); ?></small><input type="date" name="date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $val( 'date' ) ); ?>"></label>
						</div>
						<label class="iqf-full"><?php esc_html_e( 'Anything we should know?', 'instant-quote-form' ); ?> <small><?php esc_html_e( '(optional)', 'instant-quote-form' ); ?></small><textarea name="message" rows="3"><?php echo esc_textarea( $val( 'message' ) ); ?></textarea></label>
					</fieldset>
					<button type="submit" class="iqf-submit"><?php esc_html_e( 'Send my request', 'instant-quote-form' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate, store and notify. No output and no redirects, so tests can call it.
	 *
	 * @param array  $post Raw $_POST (unslashed).
	 * @param string $ip   Client IP, only used hashed for the rate limit.
	 * @return array{ok: bool, errors: array, id?: int, total?: float, old: array}
	 */
	public static function process( array $post, $ip ) {
		$s   = IQF_Settings::get();
		$old = array(
			'service'   => sanitize_key( $post['service'] ?? '' ),
			'quantity'  => absint( $post['quantity'] ?? 0 ),
			'stories'   => absint( $post['stories'] ?? 1 ),
			'frequency' => sanitize_key( $post['frequency'] ?? 'once' ),
			'extras'    => array_map( 'sanitize_key', (array) ( $post['extras'] ?? array() ) ),
			'name'      => sanitize_text_field( $post['name'] ?? '' ),
			'email'     => sanitize_email( $post['email'] ?? '' ),
			'phone'     => preg_replace( '/[^0-9+()\-\s.]/', '', sanitize_text_field( $post['phone'] ?? '' ) ),
			'date'      => sanitize_text_field( $post['date'] ?? '' ),
			'message'   => sanitize_textarea_field( $post['message'] ?? '' ),
		);

		if ( ! wp_verify_nonce( sanitize_text_field( $post['iqf_nonce'] ?? '' ), self::ACTION ) ) {
			return array( 'ok' => false, 'errors' => array( __( 'This form expired. Please reload the page and try again.', 'instant-quote-form' ) ), 'old' => $old );
		}
		if ( ! empty( $post['iqf_website'] ) ) {
			// Honeypot filled: pretend it worked and store nothing.
			return array( 'ok' => true, 'errors' => array(), 'spam' => true, 'old' => array() );
		}
		$key  = 'iqf_rl_' . md5( wp_salt( 'nonce' ) . $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= self::RATE_LIMIT ) {
			return array( 'ok' => false, 'errors' => array( __( 'Too many requests from this connection. Please try again in an hour or give us a call.', 'instant-quote-form' ) ), 'old' => $old );
		}

		$errors = array();
		if ( '' === $old['name'] || mb_strlen( $old['name'] ) > 120 ) {
			$errors[] = __( 'Please enter your name.', 'instant-quote-form' );
		}
		if ( ! is_email( $old['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'instant-quote-form' );
		}
		if ( $old['date'] && ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $old['date'] ) || $old['date'] < wp_date( 'Y-m-d' ) ) ) {
			$errors[] = __( 'Please pick a date from today onward.', 'instant-quote-form' );
		}
		if ( mb_strlen( $old['message'] ) > 2000 ) {
			$errors[] = __( 'Please keep the notes under 2,000 characters.', 'instant-quote-form' );
		}
		if ( ! in_array( $old['frequency'], array( 'once', 'quarterly', 'monthly' ), true ) ) {
			$old['frequency'] = 'once';
		}
		$estimate = IQF_Pricing::estimate( $old, $s );
		if ( is_wp_error( $estimate ) ) {
			$errors[] = $estimate->get_error_message();
		}
		if ( $errors ) {
			return array( 'ok' => false, 'errors' => $errors, 'old' => $old );
		}

		$data                  = $old;
		$data['service_label'] = $s['services'][ $old['service'] ]['label'];
		$id                    = IQF_Requests::create( $data, $estimate );
		if ( is_wp_error( $id ) ) {
			return array( 'ok' => false, 'errors' => array( __( 'Something went wrong saving your request. Please try again.', 'instant-quote-form' ) ), 'old' => $old );
		}
		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );
		IQF_Mailer::send( $id, $data, $estimate );
		do_action( 'iqf_request_created', $id, $data, $estimate );

		return array( 'ok' => true, 'errors' => array(), 'id' => $id, 'total' => $estimate['total'], 'old' => array() );
	}

	/**
	 * admin-post.php handler: process, keep the result for one page view, redirect back.
	 */
	public static function handle() {
		// Nonce is verified inside process(); input is sanitized there.
		$post   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$result = self::process( (array) $post, $ip );
		$token  = wp_generate_password( 20, false );
		set_transient(
			'iqf_state_' . $token,
			array(
				'sent'   => $result['ok'],
				'errors' => $result['errors'],
				'old'    => $result['old'],
				'total'  => $result['total'] ?? null,
			),
			10 * MINUTE_IN_SECONDS
		);
		$back = wp_validate_redirect( esc_url_raw( $post['iqf_return'] ?? '' ), home_url( '/' ) );
		wp_safe_redirect( add_query_arg( 'iqf', rawurlencode( $token ), $back ) . '#iqf-form' );
		exit;
	}

	/**
	 * Result of the last submit for this visitor, read once.
	 */
	private static function read_state() {
		$token = isset( $_GET['iqf'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_GET['iqf'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $token ) {
			return array();
		}
		$state = get_transient( 'iqf_state_' . $token );
		return is_array( $state ) ? $state : array();
	}
}
