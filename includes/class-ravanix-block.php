<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// The query below gets its table name from Ravanix_DB::tests(), which always
// returns a fixed, predefined string ($wpdb->prefix + a constant table name),
// never user input; so SQL injection is not possible through this path. The
// "direct query" and "no caching" warnings are also unavoidable for this
// plugin's custom tables, since WordPress provides no ready-made API for
// tables other than its own core tables.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
* The "Ravanix – Questionnaire" Gutenberg block. Two modes: a specific test
 * (equivalent to the [ravanix_test] shortcode) or a list of published tests
 * with a list/grid display (equivalent to the [ravanix_test_list] shortcode),
 * with a live preview in the editor.
 */
class Ravanix_Block {

	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return; // WordPress version without Gutenberg
		}

		wp_register_script(
			'ravanix-block-editor',
			RAVANIX_PLUGIN_URL . 'assets/js/ravanix-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render', 'wp-data' ),
			RAVANIX_VERSION,
			true
		);

		wp_set_script_translations( 'ravanix-block-editor', 'ravanix', RAVANIX_PLUGIN_DIR . 'languages' );

		global $wpdb;
		$tests = $wpdb->get_results( "SELECT id, title FROM " . Ravanix_DB::tests() . " WHERE status = 'published' ORDER BY title ASC" );
		wp_localize_script(
			'ravanix-block-editor',
			'RavanixBlockData',
			array(
				'tests' => array_map(
					function ( $t ) {
						return array( 'id' => (int) $t->id, 'title' => $t->title );
					},
					$tests
				),
			)
		);

		wp_register_style( 'ravanix-block-editor', RAVANIX_PLUGIN_URL . 'assets/css/ravanix-block-editor.css', array(), RAVANIX_VERSION );

		register_block_type(
			'ravanix/questionnaire',
			array(
				'title'           => __( 'Ravanix – Questionnaire', 'ravanix' ),
				'description'     => __( 'Display a specific questionnaire, or a list of all published questionnaires as a list or a grid.', 'ravanix' ),
				'category'        => 'widgets',
				'icon'            => 'forms',
				'editor_script'   => 'ravanix-block-editor',
				'editor_style'    => 'ravanix-block-editor',
				'render_callback' => array( $this, 'render' ),
				'attributes'      => array(
					'mode'        => array( 'type' => 'string', 'default' => 'list' ), // 'list' | 'single'
					'testId'      => array( 'type' => 'number', 'default' => 0 ),
					'layout'      => array( 'type' => 'string', 'default' => 'grid' ), // 'grid' | 'list'
					'columns'     => array( 'type' => 'number', 'default' => 3 ),
					'showImage'   => array( 'type' => 'boolean', 'default' => true ),
					'showExcerpt' => array( 'type' => 'boolean', 'default' => true ),
					'hideHeader'  => array( 'type' => 'boolean', 'default' => false ),
				),
			)
		);
	}

	public function render( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'mode'        => 'list',
				'testId'      => 0,
				'layout'      => 'grid',
				'columns'     => 3,
				'showImage'   => true,
				'showExcerpt' => true,
				'hideHeader'  => false,
			)
		);

		if ( ! class_exists( 'Ravanix_Shortcodes' ) ) {
			return '';
		}
		$shortcodes = new Ravanix_Shortcodes();

		if ( 'single' === $attributes['mode'] ) {
			if ( ! $attributes['testId'] ) {
				return current_user_can( 'manage_options' )
					? '<p class="rs-error">' . esc_html__( 'Select a questionnaire from the block settings.', 'ravanix' ) . '</p>'
					: '';
			}
			return $shortcodes->render_test(
				array(
					'id'          => intval( $attributes['testId'] ),
					'hide_header' => $attributes['hideHeader'] ? 1 : 0,
				)
			);
		}

		return $shortcodes->render_test_list(
			array(
				'layout'       => $attributes['layout'],
				'columns'      => intval( $attributes['columns'] ),
				'show_image'   => $attributes['showImage'] ? 1 : 0,
				'show_excerpt' => $attributes['showExcerpt'] ? 1 : 0,
			)
		);
	}
}
