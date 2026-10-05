<?php
/**
 * Plugin Name:       Instant Quote Form
 * Description:       A quote request form with a live price estimate, a submissions inbox in the admin, email alerts and editable rates. Sample plugin built for a fictional window and gutter cleaning company.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Ha Le
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       instant-quote-form
 *
 * @package InstantQuoteForm
 */

defined( 'ABSPATH' ) || exit;

define( 'IQF_VERSION', '1.1.0' );
define( 'IQF_FILE', __FILE__ );
define( 'IQF_DIR', plugin_dir_path( __FILE__ ) );
define( 'IQF_URL', plugin_dir_url( __FILE__ ) );

require_once IQF_DIR . 'includes/class-iqf-pricing.php';
require_once IQF_DIR . 'includes/class-iqf-settings.php';
require_once IQF_DIR . 'includes/class-iqf-requests.php';
require_once IQF_DIR . 'includes/class-iqf-mailer.php';
require_once IQF_DIR . 'includes/class-iqf-form.php';

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'instant-quote-form', false, dirname( plugin_basename( IQF_FILE ) ) . '/languages' );
	}
);

IQF_Settings::init();
IQF_Requests::init();
IQF_Form::init();

register_activation_hook(
	__FILE__,
	static function () {
		if ( false === get_option( IQF_Settings::OPTION ) ) {
			add_option( IQF_Settings::OPTION, IQF_Settings::defaults() );
		}
		IQF_Requests::register_post_type();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);
