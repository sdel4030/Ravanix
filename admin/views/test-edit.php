<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// This file is a template/view loaded only via include() inside methods of the
// plugin's own classes, never standalone; so its local variables never actually
// enter the real global namespace, and there is no risk of collision with
// another plugin/theme. Forcing a prefix on the dozens of local variables in
// this file would only reduce readability.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
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
global $wpdb;

$test_id = isset( $_GET['test_id'] ) ? intval( $_GET['test_id'] ) : 0;
$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

$test = $test_id ? Ravanix_DB::get_full_test( $test_id ) : null;

if ( $test_id && ! $test ) {
	echo '<div class="wrap"><p>' . esc_html__( 'The requested test was not found.', 'ravanix' ) . '</p></div>';
	return;
}

$is_new = ! $test_id;
$base_url = admin_url( 'admin.php?page=ravanix-edit-test' . ( $test_id ? '&test_id=' . $test_id : '' ) );
?>
<div class="wrap rs-wrap" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
	<h1><?php echo $is_new ? esc_html__( 'Add New Test', 'ravanix' ) : esc_html__( 'Editing test:', 'ravanix' ) . ' ' . esc_html( $test->title ); ?></h1>

	<?php if ( $is_new ) : ?>
		<p class="description"><?php esc_html_e( 'First save the test\'s general information; you can then add dimensions, questions, and interpretations.', 'ravanix' ); ?></p>
	<?php else : ?>
		<?php
		/**
		 * List of test-editor tabs. Each item: array( 'slug' => ..., 'label' => ... ).
		 * Ravanix Pro uses this filter to add its own tabs (e.g. "Norms", "Composite
		 * Factors") in the right place in this list; each tab's content is rendered
		 * via the ravanix_test_editor_tab_content action.
		 *
		 * @param array $tabs    The list of tabs in display order.
		 * @param int   $test_id The ID of the test being edited.
		 */
		$tabs = apply_filters(
			'ravanix_test_editor_tabs',
			array(
				array( 'slug' => 'general', 'label' => __( 'General Info', 'ravanix' ) ),
				array( 'slug' => 'dimensions', 'label' => __( 'Dimensions / Subscales', 'ravanix' ) ),
				array( 'slug' => 'questions', 'label' => __( 'Questions', 'ravanix' ) ),
				array( 'slug' => 'interpretations', 'label' => __( 'Interpretation Ranges', 'ravanix' ) ),
				array( 'slug' => 'preview', 'label' => __( 'Preview and shortcode', 'ravanix' ) ),
			),
			$test_id
		);
		?>
		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $tab_item ) : ?>
				<a href="<?php echo esc_url( $base_url . '&tab=' . $tab_item['slug'] ); ?>" class="nav-tab <?php echo $tab_item['slug'] === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tab_item['label'] ); ?></a>
			<?php endforeach; ?>
		</h2>
	<?php endif; ?>

	<div class="rs-tab-content">
	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Changes saved successfully.', 'ravanix' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['assigned'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php
			/* translators: %d: the number of questions that were just assigned to a dimension. */
			printf( esc_html__( '%d question(s) assigned to this dimension.', 'ravanix' ), intval( $_GET['assigned'] ) );
		?></p></div>
	<?php endif; ?>
	<?php if ( $is_new || 'general' === $tab ) : ?>

		<?php
		// The text direction of these admin fields (and, further below, of the
		// test itself on the front end) is no longer a manual per-test setting.
		// It is detected automatically from WordPress's own is_rtl(), which
		// reflects the current site/user language (e.g. Persian/Arabic ⇒ RTL,
		// English/Latin ⇒ LTR) — this removes the need for a separate,
		// easy-to-forget setting while still doing the right thing for
		// right-to-left and left-to-right questionnaires alike.
		$field_dir = is_rtl() ? 'rtl' : 'ltr';
		?>
		<form method="post" id="rs-general-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ravanix_save_test' ); ?>
			<input type="hidden" name="action" value="ravanix_save_test">
			<input type="hidden" name="test_id" value="<?php echo intval( $test_id ); ?>">

			<table class="form-table">
				<tr>
					<th><label for="title"><?php esc_html_e( 'Test title', 'ravanix' ); ?></label></th>
					<td><input type="text" id="title" name="title" class="regular-text" required dir="<?php echo esc_attr( $field_dir ); ?>"
						value="<?php echo $test ? esc_attr( $test->title ) : ''; ?>"></td>
				</tr>
				<tr>
					<th><label for="slug"><?php esc_html_e( 'Slug — optional', 'ravanix' ); ?></label></th>
					<td>
						<input type="text" id="slug" name="slug" class="regular-text" dir="ltr"
							value="<?php echo $test ? esc_attr( $test->slug ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. beck-depression-inventory', 'ravanix' ); ?>">
						<p class="description"><?php esc_html_e( 'For better site SEO. If left empty, a slug is generated automatically. Only English letters, numbers, and hyphens are allowed.', 'ravanix' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ravanix_description"><?php esc_html_e( 'Short description', 'ravanix' ); ?></label></th>
					<td>
						<?php
						wp_editor(
							$test ? $test->description : '',
							'ravanix_description',
							array(
								'textarea_name' => 'description',
								'textarea_rows' => 8,
								'media_buttons' => false,
								'teeny'         => false,
								'tinymce'       => array( 'directionality' => is_rtl() ? 'rtl' : 'ltr' ),
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'This text is shown to the user before starting the test, with formatting (bold, lists, paragraphs, etc.).', 'ravanix' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="instructions"><?php esc_html_e( 'Test instructions', 'ravanix' ); ?></label></th>
					<td><textarea id="instructions" name="instructions" rows="4" class="large-text" dir="<?php echo esc_attr( $field_dir ); ?>" placeholder="<?php esc_attr_e( 'Text shown to the user before starting the test...', 'ravanix' ); ?>"><?php echo $test ? esc_textarea( $test->instructions ) : ''; ?></textarea></td>
				</tr>
				<tr>
					<th><label for="tags"><?php esc_html_e( 'Tags', 'ravanix' ); ?></label></th>
					<td>
						<input type="text" id="tags" name="tags" class="regular-text"
							value="<?php echo $test ? esc_attr( $test->tags ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. depression, screening, clinical', 'ravanix' ); ?>">
						<p class="description"><?php esc_html_e( 'Separate with commas. Used to display "Related questionnaires" on the custom post type page.', 'ravanix' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="categories"><?php esc_html_e( 'Category', 'ravanix' ); ?></label></th>
					<td>
						<?php
						$existing_categories = get_categories( array( 'hide_empty' => false ) );
						$selected_cat_tokens = $test && $test->categories ? array_filter( array_map( 'trim', explode( ',', $test->categories ) ) ) : array();
						$existing_cat_names  = wp_list_pluck( $existing_categories, 'name' );
						// A category can be stored in the saved field either as an ID (the new,
						// reliable method) or as a name (for compatibility with data saved by earlier versions).
						$custom_selected = array_filter(
							$selected_cat_tokens,
							function ( $token ) use ( $existing_cat_names, $existing_categories ) {
								if ( in_array( $token, $existing_cat_names, true ) ) {
									return false;
								}
								foreach ( $existing_categories as $c ) {
									if ( (string) $c->term_id === (string) $token ) {
										return false;
									}
								}
								return true;
							}
						);
						?>
						<?php if ( ! empty( $existing_categories ) ) : ?>
							<p class="description"><?php esc_html_e( 'Choose from the categories already on this site:', 'ravanix' ); ?></p>
							<div class="rs-category-checklist">
								<?php foreach ( $existing_categories as $cat ) : ?>
									<label class="rs-category-item">
										<input type="checkbox" name="wp_categories[]" value="<?php echo intval( $cat->term_id ); ?>" <?php checked( in_array( (string) $cat->term_id, $selected_cat_tokens, true ) || in_array( $cat->name, $selected_cat_tokens, true ), true ); ?>>
										<?php echo esc_html( $cat->name ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<input type="text" id="categories" name="categories" class="regular-text"
							value="<?php echo esc_attr( implode( ', ', $custom_selected ) ); ?>" placeholder="<?php esc_attr_e( 'Or type a new category, e.g.: Personality tests', 'ravanix' ); ?>">
						<p class="description"><?php esc_html_e( 'For a new category, separate names with commas. Categories that don\'t already exist will be created automatically. These values are shown on the archive and single post type pages (if enabled).', 'ravanix' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="featured_image_id"><?php esc_html_e( 'Featured image', 'ravanix' ); ?></label></th>
					<td>
						<input type="hidden" id="featured_image_id" name="featured_image_id" value="<?php echo $test ? intval( $test->featured_image_id ) : ''; ?>">
						<div id="rs-featured-image-preview">
							<?php if ( $test && $test->featured_image_id ) : ?>
								<?php echo wp_get_attachment_image( $test->featured_image_id, 'medium', false, array( 'style' => 'max-width:260px;height:auto;display:block;margin-bottom:8px;' ) ); ?>
							<?php endif; ?>
						</div>
						<button type="button" class="button" id="rs-select-featured-image"><?php esc_html_e( 'Select Image', 'ravanix' ); ?></button>
						<button type="button" class="button rs-link-danger" id="rs-remove-featured-image" style="<?php echo ( $test && $test->featured_image_id ) ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove image', 'ravanix' ); ?></button>
						<p class="description"><?php esc_html_e( 'This image is shown to users on the questionnaire archive page and at the top of this test\'s single page (if the custom post type is enabled).', 'ravanix' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Publish status', 'ravanix' ); ?></th>
					<td>
						<select name="status">
							<option value="draft" <?php selected( $test ? $test->status : 'draft', 'draft' ); ?>><?php esc_html_e( 'Draft', 'ravanix' ); ?></option>
							<option value="published" <?php selected( $test ? $test->status : '', 'published' ); ?>><?php esc_html_e( 'Published (visible on the site)', 'ravanix' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="questions_per_page"><?php esc_html_e( 'Questions per page', 'ravanix' ); ?></label></th>
					<td>
						<input type="number" id="questions_per_page" name="questions_per_page" min="1" style="width:100px;"
							value="<?php echo ( $test && $test->questions_per_page ) ? intval( $test->questions_per_page ) : ''; ?>" placeholder="<?php esc_attr_e( 'All on one page', 'ravanix' ); ?>">
						<p class="description"><?php esc_html_e( 'For long questionnaires (such as the NEO-PI-R with 240 questions), specify how many questions to show per page. If left empty, all questions are shown on a single page (the default behavior).', 'ravanix' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'User login', 'ravanix' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="require_login" value="1" <?php checked( $test ? $test->require_login : 1, 1 ); ?>>
							<?php esc_html_e( 'Only logged-in users can take this test', 'ravanix' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Result display order', 'ravanix' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="rank_results" value="1" <?php checked( $test ? $test->rank_results : 0, 1 ); ?>>
							<?php esc_html_e( 'Show results ranked from highest to lowest', 'ravanix' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Useful for questionnaires where the relative ranking between dimensions matters (e.g. top strengths, career interests, dominant personality traits), rather than each dimension\'s absolute clinical level. When enabled, dimension and composite-factor scores are sorted by score on the result page and PDF instead of the admin-defined order.', 'ravanix' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Randomize order', 'ravanix' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="randomize_questions" value="1" <?php checked( $test ? $test->randomize_questions : 0, 1 ); ?>>
							<?php esc_html_e( 'Show questions in random order', 'ravanix' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="randomize_options" value="1" <?php checked( $test ? $test->randomize_options : 0, 1 ); ?>>
							<?php esc_html_e( 'Show each question\'s answer options in random order', 'ravanix' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Randomizes only what the participant sees on screen, to reduce order effects. Scoring, norms, admin results, exports, and PDF reports always use the fixed order defined below, regardless of these settings.', 'ravanix' ); ?></p>
					</td>
				</tr>
				<?php
				/**
				 * Extra rows for the general test settings table. Ravanix Pro uses this action
				 * to add its own rows (execution limit, access code, WooCommerce sale)
				 * without needing to edit this file.
				 *
				 * @param object|null $test The full test object (or null for a new test).
				 */
				do_action( 'ravanix_test_editor_general_fields', $test );
				?>
				<?php if ( $test && ! empty( $test->cpt_post_id ) && get_post( $test->cpt_post_id ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Questionnaire URL on this site', 'ravanix' ); ?></th>
					<td>
						<a href="<?php echo esc_url( get_permalink( $test->cpt_post_id ) ); ?>" target="_blank"><?php echo esc_html( get_permalink( $test->cpt_post_id ) ); ?></a>
						—
						<a href="<?php echo esc_url( get_edit_post_link( $test->cpt_post_id ) ); ?>"><?php esc_html_e( 'Edit the linked post', 'ravanix' ); ?></a>
					</td>
				</tr>
				<?php endif; ?>
			</table>

			<?php submit_button( $is_new ? __( 'Save and continue', 'ravanix' ) : __( 'Save Changes', 'ravanix' ) ); ?>
		</form>

		<?php
		/**
		 * Extra content after the general info form. Ravanix Pro uses this action to
		 * show the "create a new WooCommerce product for this test" form.
		 *
		 * @param object|null $test    The full test object (or null for a new test).
		 * @param int         $test_id The test's ID.
		 */
		do_action( 'ravanix_test_editor_after_form', $test, $test_id );
		?>

	<?php elseif ( 'dimensions' === $tab ) : ?>
		<?php include RAVANIX_PLUGIN_DIR . 'admin/views/partial-dimensions.php'; ?>

	<?php elseif ( 'questions' === $tab ) : ?>
		<?php include RAVANIX_PLUGIN_DIR . 'admin/views/partial-questions.php'; ?>

	<?php elseif ( 'interpretations' === $tab ) : ?>
		<?php include RAVANIX_PLUGIN_DIR . 'admin/views/partial-interpretations.php'; ?>

	<?php elseif ( 'preview' === $tab ) : ?>
		<?php include RAVANIX_PLUGIN_DIR . 'admin/views/partial-preview.php'; ?>

	<?php else : ?>
		<?php
		/**
		 * Content of tabs added by another plugin (like the "Norms" or "Composite
		 * Factors" tab in Ravanix Pro). This action only fires when $tab doesn't
		 * match any of the tabs above.
		 *
		 * @param string $tab     The current tab's slug.
		 * @param int    $test_id The test's ID.
		 * @param object $test    The full test object.
		 */
		do_action( 'ravanix_test_editor_tab_content', $tab, $test_id, $test );
		?>
	<?php endif; ?>
	</div>
</div>
