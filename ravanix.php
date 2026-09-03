<?php
/**
 * Plugin Name: Ravanix – Smart Psychological Assessment
 * Plugin URI: https://psykey.ir
 * Description: Free plugin to build and run psychological questionnaires (personality, clinical, screening) with a dynamic scoring engine, automatic result interpretation, and a charted psychological profile. Professional features (norms, composite factors, PDF, import/export, and more) are added by the companion Ravanix Pro plugin.
 * Version: 1.2.1
 * Author: Ravanix
 * Author URI: https://psykey.ir
 * Text Domain: ravanix
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access is not allowed.
}

define( 'RAVANIX_VERSION', '1.2.1' );
define( 'RAVANIX_PLUGIN_FILE', __FILE__ );
define( 'RAVANIX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAVANIX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RAVANIX_TABLE_PREFIX', 'ravanix_' ); // In addition to $wpdb->prefix — deliberately kept independent of the Lite/Pro plugin name so the Pro plugin can connect to the same tables.

/**
 * Load the plugin's core files.
 */
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-activator.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-db.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-settings.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-cpt.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-scoring.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-access.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-admin.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-admin-handlers.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-shortcodes.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-block.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-ajax.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-sample-data.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-widget.php';
require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-privacy.php';

register_activation_hook( __FILE__, array( 'Ravanix_Activator', 'activate' ) );
add_action( 'plugins_loaded', array( 'Ravanix_Activator', 'maybe_upgrade' ) );

/**
 * Plugin bootstrap.
 */
final class Ravanix_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		// Since WordPress 4.6, translations for plugins hosted on WordPress.org
		// are loaded automatically; there is no need to call
		// load_plugin_textdomain() manually.
		new Ravanix_CPT(); // Registers the custom post type if enabled in Settings (acts on its own init hook).

		if ( is_admin() ) {
			new Ravanix_Admin();
			new Ravanix_Admin_Handlers();
		}

		new Ravanix_Shortcodes();
		new Ravanix_Block();
		new Ravanix_Ajax();
		new Ravanix_Privacy();

		add_action( 'widgets_init', function () {
			register_widget( 'Ravanix_Tests_Widget' );
		} );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	public function enqueue_frontend_assets() {
		wp_register_style( 'ravanix-frontend', RAVANIX_PLUGIN_URL . 'assets/css/ravanix-frontend.css', array(), RAVANIX_VERSION );

		// Overrides ravanix-frontend.css's own --rs-brand fallback with the
		// admin's chosen color (Ravanix Settings -> Branding), if it differs
		// from the default -- every MD3 color role in that file derives from
		// this single custom property via color-mix(), so this one small
		// inline rule is enough to re-tint the entire front-end palette.
		// Passed through ensure_min_contrast_with_white() first: since WCAG
		// contrast is symmetric, guaranteeing the chosen color has readable
		// contrast against white simultaneously covers both of its front-end
		// uses -- white button text drawn ON it, and the color itself used
		// AS text/icon color on a white-ish surface (e.g. question numbers) --
		// with a single check, regardless of which hue the admin picked. The
		// admin's own saved setting is never modified; only the value output
		// here (recomputed on every page load) is adjusted.
		$brand_color = Ravanix_Settings::get_field( 'brand_color' );
		if ( $brand_color && '#4a6fa5' !== $brand_color ) {
			$safe_brand_color   = Ravanix_Settings::ensure_min_contrast_with_white( $brand_color );
			$dark_on_primary    = Ravanix_Settings::get_dark_mode_on_primary( $safe_brand_color );
			wp_add_inline_style(
				'ravanix-frontend',
				'.rs-test-container,.rs-my-results,.rs-test-list,.rs-cpt-archive-inner,.rs-cpt-single-inner{--rs-brand:' . esc_attr( $safe_brand_color ) . ';}'
				. '@media (prefers-color-scheme: dark){.rs-test-container,.rs-my-results,.rs-test-list,.rs-cpt-archive-inner,.rs-cpt-single-inner{--md-on-primary:' . esc_attr( $dark_on_primary ) . ' !important;}}'
			);
		}
		wp_register_script( 'ravanix-chartjs', RAVANIX_PLUGIN_URL . 'assets/js/vendor/chart.umd.min.js', array(), '4.5.1', true );
		wp_register_script( 'ravanix-frontend', RAVANIX_PLUGIN_URL . 'assets/js/ravanix-frontend.js', array( 'jquery', 'ravanix-chartjs' ), RAVANIX_VERSION, true );
		wp_localize_script(
			'ravanix-frontend',
			'Ravanix_Frontend',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'ravanix_frontend_nonce' ),
				'is_logged_in'  => is_user_logged_in(),
				// Off unless the admin explicitly opted in under Ravanix Settings
				// -> Branding (WordPress.org Guideline 10: credit links/displays
				// must be optional and default to not showing).
				'show_branding' => (bool) Ravanix_Settings::get_field( 'show_branding' ),
				'i18n'     => array(
					'required'     => __( 'Please answer the questions marked in red.', 'ravanix' ),
					'submitting'   => __( 'Submitting your answers...', 'ravanix' ),
					'error'        => __( 'Something went wrong. Please try again.', 'ravanix' ),
					'submit_btn'   => __( 'Submit answers and view my profile', 'ravanix' ),
					'your_profile' => __( 'Your psychological profile:', 'ravanix' ),
					'your_score'   => __( 'Your score', 'ravanix' ),
					'raw_score_of' => __( 'Raw score:', 'ravanix' ),
					'of_word'      => __( 'out of', 'ravanix' ),
					'chart_bar'    => __( 'Bar chart', 'ravanix' ),
					'chart_radar'  => __( 'Radar chart', 'ravanix' ),
					'composite_scores_title' => __( 'Composite factor scores', 'ravanix' ),
					'facet_scores_title'     => __( 'Subscale scores', 'ravanix' ),
					't_score_label'          => __( 'T-score', 'ravanix' ),
					'percentile_label'       => __( 'Percentile rank', 'ravanix' ),
					'validity_warning'       => __( 'Note: based on the validity scales, this result may not be reliable (e.g. due to inconsistent or biased responding). Consult a professional for an accurate interpretation.', 'ravanix' ),
					/* translators: %1$d: number of questions answered so far. %2$d: total number of questions. Substituted in JavaScript, not via PHP printf. */
					'progress_text'          => __( 'Answered: %1$d of %2$d questions', 'ravanix' ),
					'download_pdf'           => __( 'Download PDF', 'ravanix' ),
					'branding_tagline'       => __( 'Powered by Ravanix', 'ravanix' ),
					'table_factor'           => __( 'Factor', 'ravanix' ),
					'table_raw_score'        => __( 'Raw score', 'ravanix' ),
					'table_percentage'       => __( 'Percentage', 'ravanix' ),
					'table_level'            => __( 'Level', 'ravanix' ),
					'consent_required'       => __( 'Please agree to the consent notice above before starting.', 'ravanix' ),
					'saving_progress'        => __( 'Saving…', 'ravanix' ),
					'progress_saved'         => __( 'Saved.', 'ravanix' ),
					'progress_save_error'    => __( 'Could not save your progress; please try again.', 'ravanix' ),
				),
			)
		);

		// On the questionnaire archive/single page, the frontend style and script are enqueued automatically.
		if ( class_exists( 'Ravanix_CPT' ) && Ravanix_CPT::is_enabled() && ( is_post_type_archive( Ravanix_CPT::slug() ) || is_singular( Ravanix_CPT::slug() ) ) ) {
			wp_enqueue_style( 'ravanix-frontend' );
			wp_enqueue_script( 'ravanix-frontend' );
		}
	}
}

Ravanix_Plugin::instance();
