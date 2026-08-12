<?php
// Every query in this file gets its table name from a Ravanix_DB::...() method,
// which always returns a fixed, predefined string ($wpdb->prefix + a constant
// table name), never user input; so SQL injection is not possible through this
// path. The "direct query" and "no caching" warnings are also unavoidable for
// this plugin's custom tables, since WordPress provides no ready-made API for
// tables other than its own core tables.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
/**
 * The "Ravanix Tests List" widget: shows a list of published questionnaires in
 * the theme's sidebar. The widget title and number of tests shown are
 * configurable from the widget's own settings (in Appearance → Widgets).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ravanix_Tests_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'ravanix_tests_widget',
			__( 'Ravanix: Tests List', 'ravanix-lite' ),
			array(
				'description' => __( 'Displays a list of published questionnaires.', 'ravanix-lite' ),
			)
		);
	}

	public function widget( $args, $instance ) {
		global $wpdb;

		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Questionnaires', 'ravanix-lite' );
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		$count = ! empty( $instance['count'] ) ? max( 1, intval( $instance['count'] ) ) : 5;

		$tests = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, title, cpt_post_id, featured_image_id FROM ' . Ravanix_DB::tests() . " WHERE status = 'published' ORDER BY id DESC LIMIT %d",
				$count
			)
		);

		if ( empty( $tests ) ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore
		}

		$cpt_enabled = class_exists( 'Ravanix_CPT' ) && Ravanix_CPT::is_enabled();

		echo '<ul class="rs-widget-tests-list">';
		foreach ( $tests as $t ) {
			$link = ( $cpt_enabled && $t->cpt_post_id ) ? get_permalink( $t->cpt_post_id ) : '';
			echo '<li>';
			if ( $link ) {
				echo '<a href="' . esc_url( $link ) . '">';
			}
			if ( ! empty( $t->featured_image_id ) ) {
				echo wp_get_attachment_image( $t->featured_image_id, array( 40, 40 ), false, array( 'style' => 'vertical-align:middle;margin-inline-end:8px;border-radius:4px;' ) );
			}
			echo esc_html( $t->title );
			if ( $link ) {
				echo '</a>';
			}
			echo '</li>';
		}
		echo '</ul>';

		// If the custom post type isn't enabled, there's no link for these items;
		// this is just a reminder to the site admin (not the visitor) to enable that setting.
		if ( ! $cpt_enabled && current_user_can( 'manage_options' ) ) {
			echo '<p class="description" style="font-size:11px;">' . esc_html__( 'To make these items clickable, enable "Custom post type" in Ravanix Settings.', 'ravanix-lite' ) . '</p>';
		}

		echo $args['after_widget']; // phpcs:ignore
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Questionnaires', 'ravanix-lite' );
		$count = isset( $instance['count'] ) ? intval( $instance['count'] ) : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'ravanix-lite' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Number of tests to display:', 'ravanix-lite' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" min="1" max="50" step="1" value="<?php echo esc_attr( $count ); ?>">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = sanitize_text_field( $new_instance['title'] ?? '' );
		$instance['count'] = ! empty( $new_instance['count'] ) ? max( 1, intval( $new_instance['count'] ) ) : 5;
		return $instance;
	}
}
