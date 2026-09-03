<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Ravanix_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'Ravanix', 'ravanix' ),
			__( 'Ravanix', 'ravanix' ),
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
				array( __( 'All Tests', 'ravanix' ), __( 'All Tests', 'ravanix' ), 'manage_options', 'ravanix', array( $this, 'render_tests_list' ) ),
				array( __( 'Add New Test', 'ravanix' ), __( 'Add New Test', 'ravanix' ), 'manage_options', 'ravanix-edit-test', array( $this, 'render_edit_test' ) ),
				array( __( 'Participant Results', 'ravanix' ), __( 'Participant Results', 'ravanix' ), 'manage_options', 'ravanix-results', array( $this, 'render_results_list' ) ),
				array( __( 'Settings', 'ravanix' ), __( 'Settings', 'ravanix' ), 'manage_options', 'ravanix-settings', array( $this, 'render_settings' ) ),
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
				__( 'Upgrade to Pro', 'ravanix' ),
				'<span style="color:#e0a72e;">' . __( 'Upgrade to Pro', 'ravanix' ) . '</span>',
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
				'chooseImageTitle'       => __( 'Select Featured Image', 'ravanix' ),
				'useImageButton'         => __( 'Use this image', 'ravanix' ),
				'basisHintTScore'        => __( 'This dimension is interpreted based on the T-score; enter the numbers above accordingly (e.g. 65 to 80).', 'ravanix' ),
				'basisHintRaw'           => __( 'This dimension is interpreted based on the raw score.', 'ravanix' ),
				'optionTextPlaceholder'  => __( 'Option text', 'ravanix' ),
				'optionValuePlaceholder' => __( 'Value', 'ravanix' ),
				'chartProfileLabel'      => __( 'Profile', 'ravanix' ),
				'chooseDimensionPlaceholder' => __( '— Select dimension —', 'ravanix' ),
				'reverseLabel'           => __( 'Reverse', 'ravanix' ),
				'weightPlaceholder'      => __( 'Weight', 'ravanix' ),
				'confirmDeleteOnUninstall' => __( 'Are you sure? If you delete the Ravanix plugin while this option is enabled, every questionnaire, every participant\'s results, and all plugin settings will be permanently deleted from the database. This cannot be undone.', 'ravanix' ),
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
