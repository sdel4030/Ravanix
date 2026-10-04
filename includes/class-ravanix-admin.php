<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Ravanix_Admin {

	/**
	 * The real hook suffix WordPress assigns the Settings submenu page,
	 * captured from add_submenu_page()'s own return value in register_menu()
	 * and used in enqueue_assets() below -- never guessed/hardcoded as a
	 * literal string. WordPress derives a top-level page's hook-name
	 * component from sanitize_title() of its *menu title*, not from the
	 * literal $menu_slug passed to add_menu_page(); since that title
	 * (__('Ravanix', 'ravanix')) is translatable, the actual hook can be
	 * something like "{sanitized-persian-title}_page_ravanix-settings" on a
	 * site with a Persian translation active, not "ravanix_page_ravanix-settings"
	 * -- a hardcoded guess silently never matches on such a site, which is
	 * exactly what happened before this was fixed (this page's CSS/JS simply
	 * never enqueued under an RTL/Persian admin locale).
	 */
	private $settings_hook = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'Ravanix', 'ravanix' ),
			__( 'Ravanix', 'ravanix' ),
			Ravanix_Roles::CAP_ACCESS,
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
				array( __( 'All Tests', 'ravanix' ), __( 'All Tests', 'ravanix' ), Ravanix_Roles::CAP_MANAGE_TESTS, 'ravanix', array( $this, 'render_tests_list' ) ),
				array( __( 'Add New Test', 'ravanix' ), __( 'Add New Test', 'ravanix' ), Ravanix_Roles::CAP_MANAGE_TESTS, 'ravanix-edit-test', array( $this, 'render_edit_test' ) ),
				array( __( 'Participant Results', 'ravanix' ), __( 'Participant Results', 'ravanix' ), Ravanix_Roles::CAP_VIEW_RESULTS, 'ravanix-results', array( $this, 'render_results_list' ) ),
				array( __( 'Settings', 'ravanix' ), __( 'Settings', 'ravanix' ), Ravanix_Roles::CAP_MANAGE_SETTINGS, 'ravanix-settings', array( $this, 'render_settings' ) ),
			)
		);

		foreach ( $submenus as $item ) {
			$page_hook = add_submenu_page( 'ravanix', $item[0], $item[1], $item[2], $item[3], $item[4] );
			if ( 'ravanix-settings' === $item[3] ) {
				$this->settings_hook = $page_hook;
			}
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
				Ravanix_Roles::CAP_ACCESS,
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
				'conditionValuePlaceholder' => __( 'e.g. 1', 'ravanix' ),
				'removeConditionLabel'   => __( 'Remove condition', 'ravanix' ),
				'conditionOperators'     => array(
					array( 'value' => 'equals', 'label' => Ravanix_Branching::operator_label( 'equals' ) ),
					array( 'value' => 'not_equals', 'label' => Ravanix_Branching::operator_label( 'not_equals' ) ),
					array( 'value' => 'gt', 'label' => Ravanix_Branching::operator_label( 'gt' ) ),
					array( 'value' => 'lt', 'label' => Ravanix_Branching::operator_label( 'lt' ) ),
					array( 'value' => 'gte', 'label' => Ravanix_Branching::operator_label( 'gte' ) ),
					array( 'value' => 'lte', 'label' => Ravanix_Branching::operator_label( 'lte' ) ),
				),
				'confirmDeleteOnUninstall' => __( 'Are you sure? If you delete the Ravanix plugin while this option is enabled, every questionnaire, every participant\'s results, and all plugin settings will be permanently deleted from the database. This cannot be undone.', 'ravanix' ),
				'validityValuePlaceholder'  => __( 'Value', 'ravanix' ),
				'validityValue2Placeholder' => __( 'and up to', 'ravanix' ),
				'removeRuleLabel'           => __( 'Remove rule', 'ravanix' ),
				'validityOperators'         => class_exists( 'Ravanix_Pro_Scoring' ) ? array(
					array( 'value' => 'gte', 'label' => Ravanix_Pro_Scoring::validity_operator_label( 'gte' ) ),
					array( 'value' => 'lte', 'label' => Ravanix_Pro_Scoring::validity_operator_label( 'lte' ) ),
					array( 'value' => 'equals', 'label' => Ravanix_Pro_Scoring::validity_operator_label( 'equals' ) ),
					array( 'value' => 'between', 'label' => Ravanix_Pro_Scoring::validity_operator_label( 'between' ) ),
					array( 'value' => 'abs_gte', 'label' => Ravanix_Pro_Scoring::validity_operator_label( 'abs_gte' ) ),
				) : array(),
			)
		);

		// Only the Settings screen needs its tabbed-card layout and (via
		// wp_enqueue_editor()) the wp.editor JS API that
		// ravanix-settings.js uses to lazily initialize this page's three
		// rich-text fields -- see that file's own docblock for why they
		// aren't rendered through wp_editor() directly. Loading these only
		// here, rather than on every Ravanix admin screen, keeps the other
		// screens (which don't need any of this) lighter.
		//
		// Compared against $this->settings_hook (captured from
		// add_submenu_page()'s own return value in register_menu()), never a
		// hardcoded guess like 'ravanix_page_ravanix-settings' -- see that
		// property's own docblock for why a guess like that can silently
		// never match, depending on translation.
		if ( $this->settings_hook && $hook === $this->settings_hook ) {
			wp_enqueue_style( 'ravanix-settings', RAVANIX_PLUGIN_URL . 'assets/css/ravanix-settings.css', array( 'ravanix-admin' ), RAVANIX_VERSION );
			wp_enqueue_editor();
			wp_enqueue_script( 'ravanix-settings', RAVANIX_PLUGIN_URL . 'assets/js/ravanix-settings.js', array(), RAVANIX_VERSION, true );
		}
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
