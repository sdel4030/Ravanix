<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

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
// All POST requests in this file go through the $this->check() helper method,
// called at the start of every action, which performs both nonce verification
// (check_admin_referer) and a capability check (current_user_can); because this
// check happens in a shared method rather than directly in this file, static
// analysis tools cannot trace that connection. $_GET values that are only read
// to determine display content (not to perform an action) also do not need a
// nonce, since a nonce verifies *intent to perform an action*, not merely
// viewing a page.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.Security.NonceVerification.Missing

class Ravanix_Shortcodes {

	public function __construct() {
		add_shortcode( 'ravanix_test', array( $this, 'render_test' ) );
		add_shortcode( 'ravanix_test_list', array( $this, 'render_test_list' ) );
	}

	/**
	 * [ravanix_test id="1"]
	 */
	public function render_test( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'hide_header' => 0 ), $atts, 'ravanix_test' );
		$test_id     = intval( $atts['id'] );
		$hide_header = ! empty( $atts['hide_header'] );

		if ( ! $test_id ) {
			return '<p class="rs-error">' . esc_html__( 'Test ID is not specified.', 'ravanix' ) . '</p>';
		}

		$test = Ravanix_DB::get_full_test( $test_id );

		if ( ! $test || 'published' !== $test->status ) {
			return '<p class="rs-error">' . esc_html__( 'This test is not available.', 'ravanix' ) . '</p>';
		}

		if ( $test->require_login && ! is_user_logged_in() ) {
			return '<p class="rs-error">' . esc_html__( 'You must log in to your account before taking this test.', 'ravanix' ) . ' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in', 'ravanix' ) . '</a></p>';
		}

		$user_id     = get_current_user_id();
		$guest_token = $user_id ? '' : Ravanix_Access::get_or_set_guest_token();

		/**
		 * Extra access checks (access code, execution limit, WooCommerce purchase,
		 * etc.). Always allows by default; the Pro plugin uses this filter to apply
		 * these restrictions, without needing to edit this method. The optional
		 * extra_html key is also reserved for extra content like a purchase button.
		 *
		 * @param array  $result      array( 'allowed' => bool, 'message' => string, 'extra_html' => string ).
		 * @param object $test        The full test object.
		 * @param int    $user_id     The logged-in user's ID (0 for a guest).
		 * @param string $guest_token The guest token.
		 */
		$extra_check = apply_filters(
			'ravanix_extra_access_check',
			array( 'allowed' => true, 'message' => '', 'extra_html' => '' ),
			$test,
			$user_id,
			$guest_token
		);
		if ( ! $extra_check['allowed'] ) {
			wp_enqueue_style( 'ravanix-frontend' );
			return '<p class="rs-error">' . esc_html( $extra_check['message'] ) . '</p>' . ( ! empty( $extra_check['extra_html'] ) ? wp_kses_post( $extra_check['extra_html'] ) : '' );
		}

		wp_enqueue_style( 'ravanix-frontend' );
		wp_enqueue_script( 'ravanix-frontend' );

		// Randomization (if enabled for this test) only ever reorders what is
		// printed to the participant in this HTML output. $test came from
		// Ravanix_DB::get_full_test() and is a fresh, request-local copy that
		// nothing else reads: the AJAX submit handler re-fetches its own fresh
		// copy of the test (always in the fixed, admin-defined order) to score
		// answers by question_id/dimension_id, and every other consumer
		// (admin results, CSV/PDF export, norms, composites) also always reads
		// directly from the database, never from this rendered copy. So this
		// is safe to do in place, right before the template uses it.
		self::randomize_display_order( $test );

		ob_start();
		include RAVANIX_PLUGIN_DIR . 'templates/frontend-test.php';
		return ob_get_clean();
	}

	/**
	 * Shuffles $test->questions (if $test->randomize_questions) and, for
	 * display only, each question's answer choices (if $test->randomize_options):
	 * - 'multiple' / 'forced_choice' questions: their own ->options rows.
	 * - 'likert5' / 'likert7' / 'binary' questions: these have no options rows
	 *   of their own (their choice labels are a fixed, built-in list — see
	 *   get_fixed_choices() below); a shuffled (value, label) pair list is
	 *   attached as ->display_choices for the template to use instead.
	 * See the call site above for why none of this ever touches scoring or
	 * any other consumer of test data.
	 */
	private static function randomize_display_order( $test ) {
		if ( ! is_array( $test->questions ) ) {
			return;
		}

		if ( ! empty( $test->randomize_questions ) ) {
			shuffle( $test->questions );
		}

		foreach ( $test->questions as $q ) {
			if ( in_array( $q->question_type, array( 'likert5', 'likert7', 'binary' ), true ) ) {
				$pairs = array();
				foreach ( self::get_fixed_choices( $q->question_type ) as $value => $label ) {
					$pairs[] = array(
						'value' => $value,
						'label' => $label,
					);
				}
				if ( ! empty( $test->randomize_options ) ) {
					shuffle( $pairs );
				}
				$q->display_choices = $pairs;
			} elseif ( ! empty( $test->randomize_options ) && is_array( $q->options ) ) {
				shuffle( $q->options );
			}
		}
	}

	/**
	 * The canonical value => label choices for the built-in Likert / Yes-No
	 * question types, which (unlike 'multiple'/'forced_choice') have no
	 * admin-editable option rows of their own. Used both for normal display
	 * (frontend-test.php) and, via randomize_display_order() above, as the
	 * source list to shuffle when a test has answer randomization enabled.
	 */
	public static function get_fixed_choices( $question_type ) {
		switch ( $question_type ) {
			case 'likert5':
				return array(
					'1' => __( 'Strongly disagree', 'ravanix' ),
					'2' => __( 'Disagree', 'ravanix' ),
					'3' => __( 'No opinion', 'ravanix' ),
					'4' => __( 'Agree', 'ravanix' ),
					'5' => __( 'Strongly agree', 'ravanix' ),
				);
			case 'likert7':
				return array(
					'1' => __( 'Strongly disagree', 'ravanix' ),
					'2' => __( 'Disagree', 'ravanix' ),
					'3' => __( 'Slightly disagree', 'ravanix' ),
					'4' => __( 'No opinion', 'ravanix' ),
					'5' => __( 'Slightly agree', 'ravanix' ),
					'6' => __( 'Agree', 'ravanix' ),
					'7' => __( 'Strongly agree', 'ravanix' ),
				);
			case 'binary':
				return array(
					'1' => __( 'Yes', 'ravanix' ),
					'0' => __( 'No', 'ravanix' ),
				);
			default:
				return array();
		}
	}

	/**
	 * [ravanix_test_list]  List of published tests
	 */
	/**
	 * [ravanix_test_list layout="grid" columns="3" show_image="1" show_excerpt="1"]
	 * List of published tests. This same method is also called as the
	 * render_callback for the "Questionnaires List" Gutenberg block (see class-ravanix-block.php).
	 */
	public function render_test_list( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'layout'       => 'list', // 'list' | 'grid'
				'columns'      => 3,
				'show_image'   => 1,
				'show_excerpt' => 1,
			),
			$atts,
			'ravanix_test_list'
		);

		global $wpdb;
		$tests = $wpdb->get_results( "SELECT id, title, description, cpt_post_id, featured_image_id FROM " . Ravanix_DB::tests() . " WHERE status = 'published' ORDER BY id DESC" );

		wp_enqueue_style( 'ravanix-frontend' );

		$can_see_hint = current_user_can( 'manage_options' );
		$layout       = ( 'grid' === $atts['layout'] ) ? 'grid' : 'list';
		$columns      = max( 2, min( 4, intval( $atts['columns'] ) ) );

		ob_start();
		?>
		<div class="rs-test-list rs-test-list-<?php echo esc_attr( $layout ); ?>" <?php echo ( 'grid' === $layout ) ? 'style="--rs-columns:' . esc_attr( $columns ) . ';"' : ''; ?>>
			<?php if ( empty( $tests ) ) : ?>
				<p><?php esc_html_e( 'No test has been published yet.', 'ravanix' ); ?></p>
			<?php else : ?>
				<?php foreach ( $tests as $t ) : ?>
					<?php
					$permalink = ( $t->cpt_post_id && get_post( $t->cpt_post_id ) ) ? get_permalink( $t->cpt_post_id ) : '';
					?>
					<div class="rs-test-card">
						<?php if ( $atts['show_image'] && ! empty( $t->featured_image_id ) ) : ?>
							<div class="rs-test-card-image">
								<?php if ( $permalink ) : ?><a href="<?php echo esc_url( $permalink ); ?>"><?php endif; ?>
								<?php echo wp_get_attachment_image( $t->featured_image_id, 'medium' ); ?>
								<?php if ( $permalink ) : ?></a><?php endif; ?>
							</div>
						<?php endif; ?>
						<h3>
							<?php if ( $permalink ) : ?>
								<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $t->title ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $t->title ); ?>
							<?php endif; ?>
						</h3>
						<?php if ( $atts['show_excerpt'] ) : ?>
							<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $t->description ), 30 ) ); ?></p>
						<?php endif; ?>
						<?php if ( $permalink ) : ?>
							<p><a class="rs-btn rs-btn-secondary" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'View and take the test', 'ravanix' ); ?></a></p>
						<?php elseif ( $can_see_hint ) : ?>
							<p class="rs-shortcode-hint">
								<?php esc_html_e( 'This test has no dedicated page; this message is only shown to the site admin. Use the shortcode below to display this test, or enable "Display as a custom post type" in the test settings to give it a direct link:', 'ravanix' ); ?><br>
								<code>[ravanix_test id="<?php echo intval( $t->id ); ?>"]</code>
							</p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
