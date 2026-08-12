<?php
/**
 * Plugin Name: Ravanix Lite – Smart Psychological Assessment
 * Plugin URI: http://psykey.ir/ravanix-pro
 * Description: Free plugin to build and run psychological questionnaires (personality, clinical, screening) with a dynamic scoring engine, automatic result interpretation, and a charted psychological profile. Professional features (norms, composite factors, PDF, import/export, and more) are added by the companion Ravanix Pro plugin.
 * Version: 1.0.7
 * Author: PsyKey
 * Author URI: https://psykey.ir
 * Text Domain: ravanix-lite
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access is not allowed.
}

define( 'RAVANIX_VERSION', '1.0.2' );
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

		add_action( 'widgets_init', function () {
			register_widget( 'Ravanix_Tests_Widget' );
		} );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	public function enqueue_frontend_assets() {
		wp_register_style( 'ravanix-frontend', RAVANIX_PLUGIN_URL . 'assets/css/ravanix-frontend.css', array(), RAVANIX_VERSION );
		wp_register_script( 'ravanix-chartjs', RAVANIX_PLUGIN_URL . 'assets/js/vendor/chart.umd.min.js', array(), '4.5.1', true );
		wp_register_script( 'ravanix-frontend', RAVANIX_PLUGIN_URL . 'assets/js/ravanix-frontend.js', array( 'jquery', 'ravanix-chartjs' ), RAVANIX_VERSION, true );
		wp_localize_script(
			'ravanix-frontend',
			'Ravanix_Frontend',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ravanix_frontend_nonce' ),
				'i18n'     => array(
					'required'     => __( 'Please answer the questions marked in red.', 'ravanix-lite' ),
					'submitting'   => __( 'Submitting your answers...', 'ravanix-lite' ),
					'error'        => __( 'Something went wrong. Please try again.', 'ravanix-lite' ),
					'submit_btn'   => __( 'Submit answers and view my profile', 'ravanix-lite' ),
					'your_profile' => __( 'Your psychological profile:', 'ravanix-lite' ),
					'your_score'   => __( 'Your score', 'ravanix-lite' ),
					'raw_score_of' => __( 'Raw score:', 'ravanix-lite' ),
					'of_word'      => __( 'out of', 'ravanix-lite' ),
					'chart_bar'    => __( 'Bar chart', 'ravanix-lite' ),
					'chart_radar'  => __( 'Radar chart', 'ravanix-lite' ),
					'composite_scores_title' => __( 'Composite factor scores', 'ravanix-lite' ),
					'facet_scores_title'     => __( 'Subscale scores', 'ravanix-lite' ),
					't_score_label'          => __( 'T-score', 'ravanix-lite' ),
					'percentile_label'       => __( 'Percentile rank', 'ravanix-lite' ),
					'validity_warning'       => __( 'Note: based on the validity scales, this result may not be reliable (e.g. due to inconsistent or biased responding). Consult a professional for an accurate interpretation.', 'ravanix-lite' ),
					/* translators: %1$d: number of questions answered so far. %2$d: total number of questions. Substituted in JavaScript, not via PHP printf. */
					'progress_text'          => __( 'Answered: %1$d of %2$d questions', 'ravanix-lite' ),
					'download_pdf'           => __( 'Download PDF', 'ravanix-lite' ),
					'branding_tagline'       => __( 'Ravanix — a smart psychological questionnaire tool - psykey.ir', 'ravanix-lite' ),
					'table_factor'           => __( 'Factor', 'ravanix-lite' ),
					'table_raw_score'        => __( 'Raw score', 'ravanix-lite' ),
					'table_percentage'       => __( 'Percentage', 'ravanix-lite' ),
					'table_level'            => __( 'Level', 'ravanix-lite' ),
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
