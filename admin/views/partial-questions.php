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
$edit_id = isset( $_GET['edit_question'] ) ? intval( $_GET['edit_question'] ) : 0;
$editing = null;
if ( $edit_id ) {
	foreach ( $test->questions as $q ) {
		if ( $q->id == $edit_id ) { $editing = $q; break; }
	}
}
?>
<div class="rs-bulk-import-box">
	<h2><?php esc_html_e( 'Bulk-import questions', 'ravanix' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Treat each line as a new question. To mark a question as "reverse-scored", add a star (*) at the end of that line. All questions in this batch will be added with the same answer type and dimension; edit each one individually afterwards if needed.', 'ravanix' ); ?>
	</p>
	<?php if ( isset( $_GET['imported'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo intval( $_GET['imported'] ); ?> <?php esc_html_e( 'Question added successfully.', 'ravanix' ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ravanix_bulk_import_questions' ); ?>
		<input type="hidden" name="action" value="ravanix_bulk_import_questions">
		<input type="hidden" name="test_id" value="<?php echo intval( $test_id ); ?>">

		<div class="rs-bulk-row">
			<p>
				<label><?php esc_html_e( 'Dimension', 'ravanix' ); ?></label><br>
				<select name="dimension_id">
					<option value=""><?php esc_html_e( '— No dimension —', 'ravanix' ); ?></option>
					<?php foreach ( $test->dimensions as $d ) : ?>
						<option value="<?php echo intval( $d->id ); ?>"><?php echo esc_html( $d->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label><?php esc_html_e( 'Answer type', 'ravanix' ); ?></label><br>
				<select name="question_type" id="rs-bulk-question-type">
					<option value="likert5"><?php esc_html_e( '5-point Likert', 'ravanix' ); ?></option>
					<option value="likert7"><?php esc_html_e( '7-point Likert', 'ravanix' ); ?></option>
					<option value="binary"><?php esc_html_e( 'Yes / No', 'ravanix' ); ?></option>
					<option value="multiple"><?php esc_html_e( 'Custom multiple-choice (same options for all questions in this batch)', 'ravanix' ); ?></option>
				</select>
			</p>
			<p>
				<label><?php esc_html_e( 'Importance weight (default 1)', 'ravanix' ); ?></label><br>
				<input type="number" step="any" name="weight" value="1" style="width:100px;">
			</p>
		</div>

		<div id="rs-bulk-shared-options" style="display:none;">
			<p class="description">
				<?php esc_html_e( 'These answer options will be the same for all questions you enter in the box below (e.g. the 4-point response scale of the Beck Anxiety Inventory). One option per line, in the format: option text|numeric value', 'ravanix' ); ?>
			</p>
			<textarea name="shared_options" rows="4" class="large-text" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>"
				placeholder="<?php echo esc_attr( __( 'Not at all', 'ravanix' ) . "|0\n" . __( 'Mild (did not bother me much)', 'ravanix' ) . "|1\n" . __( 'Moderate (very unpleasant but I tolerated it)', 'ravanix' ) . "|2\n" . __( 'Severe (I could not stand it)', 'ravanix' ) . "|3" ); ?>"></textarea>
		</div>

		<p>
			<textarea name="bulk_questions" rows="10" class="large-text" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>" placeholder="<?php echo esc_attr( __( 'I feel comfortable in groups', 'ravanix' ) . "\n" . __( 'I enjoy being alone', 'ravanix' ) . "\n" . __( 'I usually feel worried *', 'ravanix' ) ); ?>"></textarea>
		</p>

		<?php submit_button( __( 'Add all questions', 'ravanix' ) ); ?>
	</form>
</div>

<hr>

<div class="rs-columns">
	<div class="rs-col-form">
		<h2><?php echo $editing ? esc_html__( 'Edit Question', 'ravanix' ) : esc_html__( 'Add New Question', 'ravanix' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rs-question-form">
			<?php wp_nonce_field( 'ravanix_save_question' ); ?>
			<input type="hidden" name="action" value="ravanix_save_question">
			<input type="hidden" name="test_id" value="<?php echo intval( $test_id ); ?>">
			<input type="hidden" name="question_id" value="<?php echo $editing ? intval( $editing->id ) : 0; ?>">

			<p>
				<label><?php esc_html_e( 'Question text', 'ravanix' ); ?></label><br>
				<textarea name="question_text" rows="3" class="large-text" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>" required><?php echo $editing ? esc_textarea( $editing->question_text ) : ''; ?></textarea>
			</p>
			<p>
				<label><?php esc_html_e( 'Dimension', 'ravanix' ); ?></label><br>
				<select name="dimension_id">
					<option value=""><?php esc_html_e( '— No dimension —', 'ravanix' ); ?></option>
					<?php foreach ( $test->dimensions as $d ) : ?>
						<option value="<?php echo intval( $d->id ); ?>" <?php selected( $editing ? $editing->dimension_id : '', $d->id ); ?>>
							<?php echo esc_html( $d->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( empty( $test->dimensions ) ) : ?>
					<span class="description"><?php esc_html_e( '— First create a dimension in the "Dimensions" tab —', 'ravanix' ); ?></span>
				<?php endif; ?>
			</p>
			<p>
				<label><?php esc_html_e( 'Answer type', 'ravanix' ); ?></label><br>
				<select name="question_type" id="rs-question-type">
					<option value="likert5" <?php selected( $editing ? $editing->question_type : 'likert5', 'likert5' ); ?>><?php esc_html_e( '5-point Likert (strongly disagree to strongly agree)', 'ravanix' ); ?></option>
					<option value="likert7" <?php selected( $editing ? $editing->question_type : '', 'likert7' ); ?>><?php esc_html_e( '7-point Likert', 'ravanix' ); ?></option>
					<option value="binary" <?php selected( $editing ? $editing->question_type : '', 'binary' ); ?>><?php esc_html_e( 'Yes / No', 'ravanix' ); ?></option>
					<option value="multiple" <?php selected( $editing ? $editing->question_type : '', 'multiple' ); ?>><?php esc_html_e( 'Custom multiple-choice', 'ravanix' ); ?></option>
					<?php
					/**
					 * Adds custom answer-type option fields. Ravanix Pro uses this action to
					 * add the "multiple-choice with a separate dimension per option
					 * (forced-choice)" option.
					 *
					 * @param object|null $editing The question being edited (or null for a new question).
					 */
					do_action( 'ravanix_question_type_options', $editing );
					?>
				</select>
			</p>

			<div id="rs-custom-options" style="<?php echo ( $editing && in_array( $editing->question_type, array( 'multiple' ), true ) ) ? '' : 'display:none;'; ?>">
				<label><?php esc_html_e( 'Custom options (option text and its numeric value for scoring)', 'ravanix' ); ?></label>

				<div class="rs-bulk-options-paste">
					<textarea id="rs-bulk-options-textarea" rows="4" class="large-text" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>"
						placeholder="<?php echo esc_attr( __( 'One option per line, in the format: option text|numeric value', 'ravanix' ) . "\n" . __( 'Example:', 'ravanix' ) . "\n" . __( 'Never', 'ravanix' ) . "|0\n" . __( 'Sometimes', 'ravanix' ) . "|1\n" . __( 'Always', 'ravanix' ) . "|2" ); ?>"></textarea>
					<button type="button" class="button" id="rs-parse-bulk-options"><?php esc_html_e( 'Import options from the text above', 'ravanix' ); ?></button>
				</div>

				<div id="rs-options-wrap">
					<?php
					$opts = ( $editing && ! empty( $editing->options ) ) ? $editing->options : array( (object) array( 'option_text' => '', 'option_value' => 0, 'dimension_id' => null ), (object) array( 'option_text' => '', 'option_value' => 1, 'dimension_id' => null ) );
					foreach ( $opts as $o ) :
						?>
						<p class="rs-option-row">
							<input type="text" name="option_text[]" placeholder="<?php esc_attr_e( 'Option text', 'ravanix' ); ?>" value="<?php echo esc_attr( $o->option_text ); ?>">
							<input type="number" step="any" name="option_value[]" placeholder="<?php esc_attr_e( 'Value', 'ravanix' ); ?>" value="<?php echo esc_attr( $o->option_value ); ?>" style="width:80px;">
						</p>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button" id="rs-add-option">+ <?php esc_html_e( 'Add Option', 'ravanix' ); ?></button>
			</div>

			<p>
				<label>
					<input type="checkbox" name="is_reverse" value="1" <?php checked( $editing ? $editing->is_reverse : 0, 1 ); ?>>
					<?php esc_html_e( 'Reverse scoring', 'ravanix' ); ?>
				</label>
			</p>
			<p>
				<label><?php esc_html_e( 'Question importance weight (default 1)', 'ravanix' ); ?></label><br>
				<input type="number" step="any" name="weight" value="<?php echo $editing ? esc_attr( $editing->weight ) : '1'; ?>">
			</p>
			<p>
				<label><?php esc_html_e( 'Display order', 'ravanix' ); ?></label><br>
				<input type="number" name="sort_order" value="<?php echo $editing ? intval( $editing->sort_order ) : 0; ?>">
			</p>

			<?php
			/**
			 * Extra question-form fields. Ravanix Pro uses this action to add the
			 * "multiple-choice with a separate dimension per option" fields (when the
			 * question type is forced_choice) and the "extra dimensions" section
			 * (multi-scale overlapping keying).
			 *
			 * @param object|null $editing The question being edited (or null for a new question).
			 * @param object      $test    The full test object.
			 */
			do_action( 'ravanix_question_form_extra_fields', $editing, $test );
			?>

			<?php submit_button( $editing ? __( 'Save Changes', 'ravanix' ) : __( 'Add Question', 'ravanix' ) ); ?>
			<?php if ( $editing ) : ?>
				<a href="<?php echo esc_url( $base_url . '&tab=questions' ); ?>"><?php esc_html_e( 'Cancel', 'ravanix' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<div class="rs-col-list">
		<h2><?php esc_html_e( 'Questions in this test', 'ravanix' ); ?> (<?php echo esc_html( count( $test->questions ) ); ?>)</h2>
		<?php if ( empty( $test->questions ) ) : ?>
			<p><?php esc_html_e( 'No question has been added yet.', 'ravanix' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat striped">
				<thead><tr><th>#</th><th><?php esc_html_e( 'Question text', 'ravanix' ); ?></th><th><?php esc_html_e( 'Dimension', 'ravanix' ); ?></th><th><?php esc_html_e( 'Type', 'ravanix' ); ?></th><th><?php esc_html_e( 'Reverse', 'ravanix' ); ?></th><th><?php esc_html_e( 'Actions', 'ravanix' ); ?></th></tr></thead>
				<tbody>
				<?php
				$dim_names = array();
				foreach ( $test->dimensions as $d ) { $dim_names[ $d->id ] = $d->name; }
				foreach ( $test->questions as $i => $q ) :
					?>
					<tr>
						<td><?php echo esc_html( $i + 1 ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $q->question_text, 12 ) ); ?></td>
						<td>
							<?php echo 'forced_choice' === $q->question_type ? esc_html__( 'By option', 'ravanix' ) : ( ( $q->dimension_id && isset( $dim_names[ $q->dimension_id ] ) ) ? esc_html( $dim_names[ $q->dimension_id ] ) : '—' ); ?>
							<?php if ( ! empty( $q->extra_dimensions ) ) : ?>
								<br><span class="description">+<?php echo esc_html( count( $q->extra_dimensions ) ); ?> <?php esc_html_e( 'Another dimension', 'ravanix' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $q->question_type ); ?></td>
						<td><?php echo $q->is_reverse ? esc_html__( 'Yes', 'ravanix' ) : '—'; ?></td>
						<td>
							<a href="<?php echo esc_url( $base_url . '&tab=questions&edit_question=' . $q->id ); ?>"><?php esc_html_e( 'Edit', 'ravanix' ); ?></a>
							|
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ravanix_delete_question&question_id=' . $q->id . '&test_id=' . $test_id ), 'ravanix_delete_question' ) ); ?>"
								onclick="return confirm('<?php echo esc_js( __( 'Delete this question?', 'ravanix' ) ); ?>');" class="rs-link-danger"><?php esc_html_e( 'Delete', 'ravanix' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
