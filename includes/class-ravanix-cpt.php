<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
* Registers a custom post type for displaying questionnaires (if enabled in
 * Settings) and syncs each test with a corresponding post in that post type.
 */
class Ravanix_CPT {

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 5 );
		add_filter( 'the_content', array( $this, 'append_related_questionnaires' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'include_in_archives_and_search' ) );
	}

	/**
	 * Registering a taxonomy on a custom post type alone is not enough for it to
	 * show up in WordPress's default tag/category archives (/tag/..., /category/...)
	 * or in site search — this is a known WordPress limitation; the main query for
	 * those pages must be explicitly told to include this post type too.
	 */
	public static function include_in_archives_and_search( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! self::is_enabled() ) {
			return;
		}
		if ( ! ( $query->is_tag() || $query->is_category() || $query->is_search() ) ) {
			return;
		}

		$existing = $query->get( 'post_type' );
		$slug     = self::slug();

		if ( empty( $existing ) || 'any' === $existing ) {
			// WordPress defaults the search query to "any" post type, which should
			// already include ours; it's explicitly set together with "post" as a
			// safeguard, so the default behavior of the theme/other plugins isn't changed.
			$query->set( 'post_type', array( 'post', $slug ) );
		} elseif ( is_array( $existing ) && ! in_array( $slug, $existing, true ) ) {
			$query->set( 'post_type', array_merge( $existing, array( $slug ) ) );
		} elseif ( is_string( $existing ) && $existing !== $slug ) {
			$query->set( 'post_type', array( $existing, $slug ) );
		}
	}

	public static function is_enabled() {
		return (bool) Ravanix_Settings::get_field( 'enable_cpt' );
	}

	public static function slug() {
		$slug = Ravanix_Settings::get_field( 'cpt_slug' );
		return $slug ? sanitize_key( $slug ) : 'questionnaire';
	}

	public static function register_post_type() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$singular = Ravanix_Settings::get_field( 'cpt_singular' ) ?: 'Questionnaire';
		$plural   = Ravanix_Settings::get_field( 'cpt_plural' ) ?: 'Questionnaires';
		$slug     = self::slug();

		$labels = array(
			// "$singular"/"$plural" are values the site admin typed in themselves in the
			// plugin settings (not the plugin's own static text), so they don't need
			// __(); only each label's fixed template/prefix needs to be translatable.
			'name'               => $plural,
			'singular_name'      => $singular,
			/* translators: %s: singular label of the questionnaire post type, as configured by the site admin. */
			'add_new_item'       => sprintf( __( 'Add new %s', 'ravanix-lite' ), $singular ),
			/* translators: %s: singular label of the questionnaire post type, as configured by the site admin. */
			'add_new'            => sprintf( __( 'Add %s', 'ravanix-lite' ), $singular ),
			/* translators: %s: singular label of the questionnaire post type, as configured by the site admin. */
			'edit_item'          => sprintf( __( 'Edit %s', 'ravanix-lite' ), $singular ),
			/* translators: %s: singular label of the questionnaire post type, as configured by the site admin. */
			'new_item'           => sprintf( __( 'New %s', 'ravanix-lite' ), $singular ),
			/* translators: %s: singular label of the questionnaire post type, as configured by the site admin. */
			'view_item'          => sprintf( __( 'View %s', 'ravanix-lite' ), $singular ),
			/* translators: %s: plural label of the questionnaire post type, as configured by the site admin. */
			'search_items'       => sprintf( __( 'Search %s', 'ravanix-lite' ), $plural ),
			'not_found'          => __( 'No items found', 'ravanix-lite' ),
			'not_found_in_trash' => __( 'No items found in the trash', 'ravanix-lite' ),
			/* translators: %s: plural label of the questionnaire post type, as configured by the site admin. */
			'all_items'          => sprintf( __( 'All %s', 'ravanix-lite' ), $plural ),
			'menu_name'          => $plural,
		);

		register_post_type(
			$slug,
			array(
				'labels'              => $labels,
				'public'              => true,
				'publicly_queryable'  => true,
				'has_archive'         => true,
				// Deliberately hidden from the WordPress admin menu: questionnaires are
				// only managed via the "Ravanix" menu, to avoid two parallel admin interfaces.
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => true,
				// REST API support is needed so Gutenberg blocks (like "Query Loop" and
				// "Latest Posts") and page-builder widgets that use REST (like some Elementor
				// widgets) can recognize this post type as a content source and use it for
				// page design (including the homepage).
				'show_in_rest'        => true,
				'rest_base'           => $slug,
				'exclude_from_search' => false,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
				'rewrite'             => array( 'slug' => $slug, 'with_front' => false ),
				'taxonomies'          => array( 'post_tag', 'category' ),
			)
		);

		register_taxonomy_for_object_type( 'post_tag', $slug );
		register_taxonomy_for_object_type( 'category', $slug );
	}

	/**
	 * Registers the post type immediately in the same request in which the
	 * settings are saved, so rewrite rules can be flushed right after saving the
	 * settings; without this, the user would have to visit the "Permalinks" page manually.
	 */
	public static function register_and_flush() {
		self::register_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Creates or updates a test's linked post in the custom post type.
	 * Called from ravanix_save_test.
	 *
	 * @return int|null The created/updated post's ID, or null if the feature is disabled
	 */
	public static function sync_test_to_post( $test_row ) {
		if ( ! self::is_enabled() ) {
			return null;
		}

		$slug_cpt = self::slug();

		$content_prefix = ! empty( $test_row->description ) ? wp_kses_post( $test_row->description ) . "\n\n" : '';

		$post_data = array(
			'post_type'    => $slug_cpt,
			'post_title'   => $test_row->title,
			'post_content' => $content_prefix . '[ravanix_test id="' . intval( $test_row->id ) . '" hide_header="1"]',
			'post_excerpt' => wp_trim_words( wp_strip_all_tags( $test_row->description ), 40 ),
			'post_status'  => ( 'published' === $test_row->status ) ? 'publish' : 'draft',
		);

		if ( ! empty( $test_row->slug ) ) {
			$post_data['post_name'] = sanitize_title( $test_row->slug );
		}

		if ( ! empty( $test_row->cpt_post_id ) && get_post( $test_row->cpt_post_id ) ) {
			$post_data['ID'] = $test_row->cpt_post_id;
			$post_id         = wp_update_post( $post_data, true );
		} else {
			$post_id = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return null;
		}

		// Sync the featured image
		if ( ! empty( $test_row->featured_image_id ) ) {
			set_post_thumbnail( $post_id, intval( $test_row->featured_image_id ) );
		} else {
			delete_post_thumbnail( $post_id );
		}

		// Sync tags
		if ( ! empty( $test_row->tags ) ) {
			$tags = array_filter( array_map( 'trim', explode( ',', $test_row->tags ) ) );
			wp_set_post_terms( $post_id, $tags, 'post_tag', false );
		} else {
			wp_set_post_terms( $post_id, array(), 'post_tag', false );
		}

		// Sync categories: each item is either the ID of an existing category
		// (when selected from the checklist, which always targets exactly the
		// intended real category) or the name of a new category (when typed into
		// the text box, which is created if it doesn't exist). Previously everything
		// was matched by name only, which meant any slight name difference (extra
		// spaces, look-alike Persian/Arabic characters, etc.) could create/select a
		// duplicate, different category instead of the intended one.
		if ( ! empty( $test_row->categories ) ) {
			$tokens      = array_filter( array_map( 'trim', explode( ',', $test_row->categories ) ) );
			$category_ids = array();
			foreach ( $tokens as $token ) {
				if ( is_numeric( $token ) ) {
					$term = get_term( intval( $token ), 'category' );
					if ( $term && ! is_wp_error( $term ) ) {
						$category_ids[] = intval( $term->term_id );
					}
					continue;
				}
				$term = get_term_by( 'name', $token, 'category' );
				if ( ! $term ) {
					$inserted = wp_insert_term( $token, 'category' );
					if ( ! is_wp_error( $inserted ) ) {
						$category_ids[] = intval( $inserted['term_id'] );
					}
				} else {
					$category_ids[] = intval( $term->term_id );
				}
			}
			wp_set_post_terms( $post_id, array_unique( $category_ids ), 'category', false );
		} else {
			wp_set_post_terms( $post_id, array(), 'category', false );
		}

		return $post_id;
	}

	public static function get_edit_link( $post_id ) {
		return $post_id ? get_edit_post_link( $post_id, 'raw' ) : '';
	}

	public static function get_view_link( $post_id ) {
		return $post_id ? get_permalink( $post_id ) : '';
	}

	/**
	 * Questionnaires are displayed on the custom post type entirely with
	 * WordPress's default rendering (no dedicated template), so they're
	 * compatible with any theme and the featured image/title/excerpt are shown by
	 * the theme itself (without duplication). The main content (the test
	 * shortcode + the "Related Questionnaires" block) is added via the the_content filter.
	 */

	/**
	 * Displays related questionnaires (based on a shared tag) at the end of the post type's single page
	 */
	public function append_related_questionnaires( $content ) {
		if ( ! self::is_enabled() || ! is_singular( self::slug() ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		$tags    = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );

		if ( empty( $tags ) || is_wp_error( $tags ) ) {
			return $content;
		}

		$related = new WP_Query(
			array(
				'post_type'      => self::slug(),
				// Only excludes the single current post from a 4-item "related
				// questionnaires" list; this is the WPVIP large-scale-traffic
				// caution guideline, which does not meaningfully apply to such a
				// small, tightly-scoped query.
				'post__not_in'   => array( $post_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
				'posts_per_page' => 4,
				'tax_query'      => array( // phpcs:ignore
					array(
						'taxonomy' => 'post_tag',
						'field'    => 'term_id',
						'terms'    => $tags,
					),
				),
			)
		);

		if ( ! $related->have_posts() ) {
			return $content;
		}

		$block = '<div class="rs-related-questionnaires"><h3>' . esc_html__( 'Related Questionnaires', 'ravanix-lite' ) . '</h3><ul>';
		while ( $related->have_posts() ) {
			$related->the_post();
			$block .= '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
		}
		$block .= '</ul></div>';

		wp_reset_postdata();

		return $content . $block;
	}
}
