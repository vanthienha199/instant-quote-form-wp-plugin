<?php
/**
 * Settings page (Settings > Instant Quote) built on the Settings API.
 *
 * @package InstantQuoteForm
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings.
 */
class IQF_Settings {

	const OPTION = 'iqf_settings';
	const PAGE   = 'iqf-settings';

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( IQF_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Default settings for the sample company.
	 */
	public static function defaults() {
		return array(
			'company'             => 'Ruiz Window Co.',
			'notify_email'        => get_option( 'admin_email' ),
			'customer_copy'       => 1,
			'currency'            => '$',
			'success_message'     => __( 'Thanks, your request is in. We will confirm a time within one business day.', 'instant-quote-form' ),
			'services'            => array(
				'windows'  => array(
					'enabled' => 1,
					'label'   => __( 'Window cleaning', 'instant-quote-form' ),
					'unit'    => __( 'windows', 'instant-quote-form' ),
					'unit_one' => __( 'window', 'instant-quote-form' ),
					'price'   => 6.75,
					'minimum' => 129,
					'max'     => 200,
				),
				'gutters'  => array(
					'enabled' => 1,
					'label'   => __( 'Gutter cleaning', 'instant-quote-form' ),
					'unit'    => __( 'feet of gutter', 'instant-quote-form' ),
					'unit_one' => __( 'foot of gutter', 'instant-quote-form' ),
					'price'   => 1.35,
					'minimum' => 149,
					'max'     => 600,
				),
				'pressure' => array(
					'enabled' => 1,
					'label'   => __( 'Pressure washing', 'instant-quote-form' ),
					'unit'    => __( 'square feet', 'instant-quote-form' ),
					'unit_one' => __( 'square foot', 'instant-quote-form' ),
					'price'   => 0.32,
					'minimum' => 199,
					'max'     => 5000,
				),
			),
			'story_multipliers'   => array(
				1 => 1,
				2 => 1.15,
				3 => 1.3,
			),
			'frequency_discounts' => array(
				'once'      => 0,
				'quarterly' => 10,
				'monthly'   => 15,
			),
			'extras'              => array(
				'screens'    => array(
					'label'    => __( 'Screen cleaning', 'instant-quote-form' ),
					'price'    => 45,
					'services' => array( 'windows' ),
				),
				'hard_water' => array(
					'label'    => __( 'Hard water stain treatment', 'instant-quote-form' ),
					'price'    => 60,
					'services' => array( 'windows' ),
				),
				'guards'     => array(
					'label'    => __( 'Gutter guard check', 'instant-quote-form' ),
					'price'    => 35,
					'services' => array( 'gutters' ),
				),
				'sealant'    => array(
					'label'    => __( 'Deck sealant quote', 'instant-quote-form' ),
					'price'    => 0,
					'services' => array( 'pressure' ),
				),
			),
		);
	}

	/**
	 * Current settings merged over the defaults.
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		$d     = self::defaults();
		if ( ! is_array( $saved ) ) {
			return $d;
		}
		$out = array_merge( $d, array_intersect_key( $saved, $d ) );
		foreach ( array( 'services', 'extras', 'frequency_discounts', 'story_multipliers' ) as $group ) {
			foreach ( $d[ $group ] as $key => $value ) {
				if ( is_array( $value ) ) {
					$out[ $group ][ $key ] = array_merge( $value, (array) ( $saved[ $group ][ $key ] ?? array() ) );
				} elseif ( isset( $saved[ $group ][ $key ] ) ) {
					$out[ $group ][ $key ] = $saved[ $group ][ $key ];
				}
			}
		}
		return $out;
	}

	/**
	 * Register the option with a sanitize callback.
	 */
	public static function register() {
		register_setting(
			'iqf_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize everything that comes back from the form. Unknown keys are dropped.
	 *
	 * @param mixed $raw Posted value.
	 */
	public static function sanitize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$d   = self::defaults();
		$out = self::get();

		$out['company']         = sanitize_text_field( $raw['company'] ?? $out['company'] );
		$email                  = sanitize_email( $raw['notify_email'] ?? '' );
		$out['notify_email']    = is_email( $email ) ? $email : $out['notify_email'];
		$out['customer_copy']   = empty( $raw['customer_copy'] ) ? 0 : 1;
		$out['currency']        = mb_substr( sanitize_text_field( $raw['currency'] ?? '$' ), 0, 3 );
		$out['success_message'] = sanitize_textarea_field( $raw['success_message'] ?? $out['success_message'] );

		foreach ( $d['services'] as $key => $def ) {
			$in                               = (array) ( $raw['services'][ $key ] ?? array() );
			$out['services'][ $key ]['enabled'] = empty( $in['enabled'] ) ? 0 : 1;
			$out['services'][ $key ]['label']   = sanitize_text_field( $in['label'] ?? $def['label'] );
			$out['services'][ $key ]['unit']    = sanitize_text_field( $in['unit'] ?? $def['unit'] );
			$out['services'][ $key ]['unit_one'] = sanitize_text_field( $in['unit_one'] ?? $def['unit_one'] );
			$out['services'][ $key ]['price']   = max( 0.0, round( (float) ( $in['price'] ?? $def['price'] ), 2 ) );
			$out['services'][ $key ]['minimum'] = max( 0.0, round( (float) ( $in['minimum'] ?? $def['minimum'] ), 2 ) );
			$out['services'][ $key ]['max']     = max( 1, absint( $in['max'] ?? $def['max'] ) );
		}
		foreach ( array( 'quarterly', 'monthly' ) as $f ) {
			$out['frequency_discounts'][ $f ] = min( 90.0, max( 0.0, round( (float) ( $raw['frequency_discounts'][ $f ] ?? 0 ), 1 ) ) );
		}
		foreach ( $d['extras'] as $key => $def ) {
			$out['extras'][ $key ]['price'] = max( 0.0, round( (float) ( $raw['extras'][ $key ]['price'] ?? $def['price'] ), 2 ) );
		}
		return $out;
	}

	/**
	 * Menu entry under Settings.
	 */
	public static function menu() {
		add_options_page(
			__( 'Instant Quote Form', 'instant-quote-form' ),
			__( 'Instant Quote', 'instant-quote-form' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Admin styles on our screens only.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( 'settings_page_' . self::PAGE === $hook || ( $screen && IQF_Requests::POST_TYPE === $screen->post_type ) ) {
			wp_enqueue_style( 'iqf-admin', IQF_URL . 'assets/css/admin.css', array(), IQF_VERSION );
		}
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'instant-quote-form' ) . '</a>' );
		return $links;
	}

	/**
	 * Render the page. Capability checked again here, not only in the menu.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'instant-quote-form' ) );
		}
		$s    = self::get();
		$name = self::OPTION;
		?>
		<div class="wrap iqf-wrap">
			<h1><?php esc_html_e( 'Instant Quote Form', 'instant-quote-form' ); ?></h1>
			<p class="iqf-lede"><?php esc_html_e( 'Rates, discounts and emails for the quote form. Add the form to any page with the Instant Quote block or the [instant_quote] shortcode.', 'instant-quote-form' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'iqf_settings_group' ); ?>
				<div class="iqf-grid">
					<section class="iqf-card iqf-wide">
						<h2><?php esc_html_e( 'Services and rates', 'instant-quote-form' ); ?></h2>
						<table class="iqf-rates">
							<thead><tr>
								<th><?php esc_html_e( 'On', 'instant-quote-form' ); ?></th>
								<th><?php esc_html_e( 'Service', 'instant-quote-form' ); ?></th>
								<th><?php esc_html_e( 'Unit, plural', 'instant-quote-form' ); ?></th>
								<th><?php esc_html_e( 'Unit, one', 'instant-quote-form' ); ?></th>
								<th class="num"><?php esc_html_e( 'Price per unit', 'instant-quote-form' ); ?></th>
								<th class="num"><?php esc_html_e( 'Minimum', 'instant-quote-form' ); ?></th>
								<th class="num"><?php esc_html_e( 'Max units', 'instant-quote-form' ); ?></th>
							</tr></thead>
							<tbody>
							<?php foreach ( $s['services'] as $key => $svc ) : ?>
								<tr>
									<td><input type="checkbox" name="<?php echo esc_attr( "{$name}[services][{$key}][enabled]" ); ?>" value="1" <?php checked( $svc['enabled'] ); ?> aria-label="<?php echo esc_attr( $svc['label'] ); ?>"></td>
									<td><input type="text" name="<?php echo esc_attr( "{$name}[services][{$key}][label]" ); ?>" value="<?php echo esc_attr( $svc['label'] ); ?>"></td>
									<td><input type="text" name="<?php echo esc_attr( "{$name}[services][{$key}][unit]" ); ?>" value="<?php echo esc_attr( $svc['unit'] ); ?>"></td>
									<td><input type="text" name="<?php echo esc_attr( "{$name}[services][{$key}][unit_one]" ); ?>" value="<?php echo esc_attr( $svc['unit_one'] ); ?>"></td>
									<td class="num"><input type="number" step="0.01" min="0" name="<?php echo esc_attr( "{$name}[services][{$key}][price]" ); ?>" value="<?php echo esc_attr( $svc['price'] ); ?>"></td>
									<td class="num"><input type="number" step="1" min="0" name="<?php echo esc_attr( "{$name}[services][{$key}][minimum]" ); ?>" value="<?php echo esc_attr( $svc['minimum'] ); ?>"></td>
									<td class="num"><input type="number" step="1" min="1" name="<?php echo esc_attr( "{$name}[services][{$key}][max]" ); ?>" value="<?php echo esc_attr( $svc['max'] ); ?>"></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</section>

					<section class="iqf-card">
						<h2><?php esc_html_e( 'Repeat service discounts', 'instant-quote-form' ); ?></h2>
						<?php foreach ( array( 'quarterly' => __( 'Quarterly', 'instant-quote-form' ), 'monthly' => __( 'Monthly', 'instant-quote-form' ) ) as $f => $label ) : ?>
							<label class="iqf-row"><span><?php echo esc_html( $label ); ?></span>
								<span class="iqf-suffix"><input type="number" step="0.5" min="0" max="90" name="<?php echo esc_attr( "{$name}[frequency_discounts][{$f}]" ); ?>" value="<?php echo esc_attr( $s['frequency_discounts'][ $f ] ); ?>">%</span>
							</label>
						<?php endforeach; ?>
						<h2><?php esc_html_e( 'Add-ons', 'instant-quote-form' ); ?></h2>
						<?php foreach ( $s['extras'] as $key => $ex ) : ?>
							<label class="iqf-row"><span><?php echo esc_html( $ex['label'] ); ?></span>
								<span class="iqf-prefix"><?php echo esc_html( $s['currency'] ); ?><input type="number" step="1" min="0" name="<?php echo esc_attr( "{$name}[extras][{$key}][price]" ); ?>" value="<?php echo esc_attr( $ex['price'] ); ?>"></span>
							</label>
						<?php endforeach; ?>
					</section>

					<section class="iqf-card">
						<h2><?php esc_html_e( 'Notifications', 'instant-quote-form' ); ?></h2>
						<label class="iqf-field"><?php esc_html_e( 'Business name', 'instant-quote-form' ); ?>
							<input type="text" name="<?php echo esc_attr( "{$name}[company]" ); ?>" value="<?php echo esc_attr( $s['company'] ); ?>"></label>
						<label class="iqf-field"><?php esc_html_e( 'Send new requests to', 'instant-quote-form' ); ?>
							<input type="email" name="<?php echo esc_attr( "{$name}[notify_email]" ); ?>" value="<?php echo esc_attr( $s['notify_email'] ); ?>"></label>
						<label class="iqf-check"><input type="checkbox" name="<?php echo esc_attr( "{$name}[customer_copy]" ); ?>" value="1" <?php checked( $s['customer_copy'] ); ?>>
							<?php esc_html_e( 'Email the customer a copy of their estimate', 'instant-quote-form' ); ?></label>
						<label class="iqf-field"><?php esc_html_e( 'Currency symbol', 'instant-quote-form' ); ?>
							<input type="text" class="small-text" maxlength="3" name="<?php echo esc_attr( "{$name}[currency]" ); ?>" value="<?php echo esc_attr( $s['currency'] ); ?>"></label>
						<label class="iqf-field"><?php esc_html_e( 'Message after submit', 'instant-quote-form' ); ?>
							<textarea rows="3" name="<?php echo esc_attr( "{$name}[success_message]" ); ?>"><?php echo esc_textarea( $s['success_message'] ); ?></textarea></label>
					</section>
				</div>
				<?php submit_button( __( 'Save settings', 'instant-quote-form' ) ); ?>
			</form>
		</div>
		<?php
	}
}
