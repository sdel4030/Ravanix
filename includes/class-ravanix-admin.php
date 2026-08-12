<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Ravanix_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'Ravanix', 'ravanix-lite' ),
			__( 'Ravanix', 'ravanix-lite' ),
			'manage_options',
			'ravanix',
			array( $this, 'render_tests_list' ),
			'dashicons-forms',
			26
		);

		/**
		 * List of the plugin's submenus. Each item: page_title, menu_title, capability,
		 * slug, callback. This filter is the official extension point for other
		 * plugins (including Ravanix Pro) to add a submenu, without needing to edit this file.
		 *
		 * @param array $submenus The list of submenus in display order.
		 */
		$submenus = apply_filters(
			'ravanix_admin_submenus',
			array(
				array( __( 'All Tests', 'ravanix-lite' ), __( 'All Tests', 'ravanix-lite' ), 'manage_options', 'ravanix', array( $this, 'render_tests_list' ) ),
				array( __( 'Add New Test', 'ravanix-lite' ), __( 'Add New Test', 'ravanix-lite' ), 'manage_options', 'ravanix-edit-test', array( $this, 'render_edit_test' ) ),
				array( __( 'Participant Results', 'ravanix-lite' ), __( 'Participant Results', 'ravanix-lite' ), 'manage_options', 'ravanix-results', array( $this, 'render_results_list' ) ),
				array( __( 'Settings', 'ravanix-lite' ), __( 'Settings', 'ravanix-lite' ), 'manage_options', 'ravanix-settings', array( $this, 'render_settings' ) ),
			)
		);

		foreach ( $submenus as $item ) {
			add_submenu_page( 'ravanix', $item[0], $item[1], $item[2], $item[3], $item[4] );
		}

		// The "Upgrade to Pro" item is always last and is added directly (not
		// through the submenus filter) so no other plugin can move or alter it. When
		// Ravanix Pro is active, it hides this item itself via this same filter; if
		// Pro is deactivated, the item is shown again automatically.
		if ( apply_filters( 'ravanix_show_upgrade_menu_item', true ) ) {
			add_submenu_page(
				'ravanix',
				__( 'Upgrade to Pro', 'ravanix-lite' ),
				'<span style="color:#e0a72e;">' . __( 'Upgrade to Pro', 'ravanix-lite' ) . '</span>',
				'manage_options',
				'ravanix-upgrade',
				array( $this, 'render_upgrade' )
			);
		}
	}

	public function render_upgrade() {
		require RAVANIX_PLUGIN_DIR . 'admin/views/upgrade.php';
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'ravanix' ) === false ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'ravanix-admin', RAVANIX_PLUGIN_URL . 'assets/css/ravanix-admin.css', array(), RAVANIX_VERSION );
		wp_enqueue_script( 'ravanix-chartjs', RAVANIX_PLUGIN_URL . 'assets/js/vendor/chart.umd.min.js', array(), '4.5.1', true );
		wp_enqueue_script( 'ravanix-admin', RAVANIX_PLUGIN_URL . 'assets/js/ravanix-admin.js', array( 'jquery', 'wp-color-picker', 'ravanix-chartjs' ), RAVANIX_VERSION, true );
		wp_enqueue_media();
		wp_localize_script(
			'ravanix-admin',
			'ravanixAdminL10n',
			array(
				'chooseImageTitle'       => __( 'Select Featured Image', 'ravanix-lite' ),
				'useImageButton'         => __( 'Use this image', 'ravanix-lite' ),
				'basisHintTScore'        => __( 'This dimension is interpreted based on the T-score; enter the numbers above accordingly (e.g. 65 to 80).', 'ravanix-lite' ),
				'basisHintRaw'           => __( 'This dimension is interpreted based on the raw score.', 'ravanix-lite' ),
				'optionTextPlaceholder'  => __( 'Option text', 'ravanix-lite' ),
				'optionValuePlaceholder' => __( 'Value', 'ravanix-lite' ),
				'chartProfileLabel'      => __( 'Profile', 'ravanix-lite' ),
				'chooseDimensionPlaceholder' => __( '— Select dimension —', 'ravanix-lite' ),
				'reverseLabel'           => __( 'Reverse', 'ravanix-lite' ),
				'weightPlaceholder'      => __( 'Weight', 'ravanix-lite' ),
			)
		);
	}

	public function render_tests_list() {
		require RAVANIX_PLUGIN_DIR . 'admin/views/tests-list.php';
	}

	public function render_edit_test() {
		require RAVANIX_PLUGIN_DIR . 'admin/views/test-edit.php';
	}

	public function render_results_list() {
		require RAVANIX_PLUGIN_DIR . 'admin/views/results-list.php';
	}

	public function render_settings() {
		require RAVANIX_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
