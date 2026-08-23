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
$edit_id = isset( $_GET['edit_dimension'] ) ? intval( $_GET['edit_dimension'] ) : 0;
$editing = null;
if ( $edit_id ) {
	foreach ( $test->dimensions as $d ) {
		if ( $d->id == $edit_id ) { $editing = $d; break; }
	}
}
?>
<div class="rs-columns">
	<div class="rs-col-form">
		<h2><?php echo $editing ? esc_html__( 'Edit Dimension', 'ravanix' ) : esc_html__( 'Add New Dimension', 'ravanix' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ravanix_save_dimension' ); ?>
			<input type="hidden" name="action" value="ravanix_save_dimension">
			<input type="hidden" name="test_id" value="<?php echo intval( $test_id ); ?>">
			<input type="hidden" name="dimension_id" value="<?php echo $editing ? intval( $editing->id ) : 0; ?>">

			<p>
				<label><?php esc_html_e( 'Dimension name', 'ravanix' ); ?></label><br>
				<input type="text" name="name" class="regular-text" required dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>"
					value="<?php echo $editing ? esc_attr( $editing->name ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Extraversion', 'ravanix' ); ?>">
			</p>
			<p>
				<label><?php esc_html_e( 'Unique code (English, no spaces)', 'ravanix' ); ?></label><br>
				<input type="text" name="code" class="regular-text"
					value="<?php echo $editing ? esc_attr( $editing->code ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. extraversion', 'ravanix' ); ?>">
			</p>
			<p>
				<label><?php esc_html_e( 'Description', 'ravanix' ); ?></label><br>
				<?php
				wp_editor(
					$editing ? $editing->description : '',
					'ravanix_dimension_description',
					array(
						'textarea_name' => 'description',
						'textarea_rows' => 6,
						'media_buttons' => false,
						'teeny'         => true,
						'tinymce'       => array( 'directionality' => is_rtl() ? 'rtl' : 'ltr' ),
					)
				);
				?>
			</p>
			<p>
				<label><?php esc_html_e( 'Display order', 'ravanix' ); ?></label><br>
				<input type="number" name="sort_order" value="<?php echo $editing ? intval( $editing->sort_order ) : 0; ?>">
			</p>
			<?php if ( class_exists( 'Ravanix_Pro_Scoring' ) ) : ?>
			<p>
				<label><?php esc_html_e( 'Interpretation basis', 'ravanix' ); ?></label><br>
				<select name="interpretation_basis">
					<option value="raw" <?php selected( $editing ? $editing->interpretation_basis : 'raw', 'raw' ); ?>><?php esc_html_e( 'Raw score', 'ravanix' ); ?></option>
					<option value="t_score" <?php selected( $editing ? $editing->interpretation_basis : '', 't_score' ); ?>><?php esc_html_e( 'T-score (requires a norm table)', 'ravanix' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'If you choose "T-score", this dimension\'s interpretation ranges must be written based on the T-score, not the raw score. For this mode, define at least one norm table for this dimension in the "Norms" tab.', 'ravanix' ); ?></p>
			</p>
			<p>
				<label>
					<input type="checkbox" name="is_validity_scale" id="rs-is-validity-scale" value="1" <?php checked( $editing ? $editing->is_validity_scale : 0, 1 ); ?>>
					<?php esc_html_e( 'This dimension is a validity scale (like L/F/K in the MMPI)', 'ravanix' ); ?>
				</label>
			</p>
			<p id="rs-validity-threshold-wrap" style="<?php echo ( $editing && $editing->is_validity_scale ) ? '' : 'display:none;'; ?>">
				<label><?php esc_html_e( 'Warning threshold (score at or above which the result is flagged as questionable)', 'ravanix' ); ?></label><br>
				<input type="number" step="any" name="validity_threshold" value="<?php echo ( $editing && null !== $editing->validity_threshold ) ? esc_attr( $editing->validity_threshold ) : ''; ?>">
			</p>
			<?php else : ?>
			<input type="hidden" name="interpretation_basis" value="raw">
			<?php endif; ?>
			<?php submit_button( $editing ? __( 'Save Changes', 'ravanix' ) : __( 'Add Dimension', 'ravanix' ) ); ?>
			<?php if ( $editing ) : ?>
				<a href="<?php echo esc_url( $base_url . '&tab=dimensions' ); ?>"><?php esc_html_e( 'Cancel', 'ravanix' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<div class="rs-col-list">
		<h2><?php esc_html_e( 'Dimensions of this test', 'ravanix' ); ?></h2>
		<?php if ( ! empty( $test->dimensions ) && ! empty( $test->questions ) ) : ?>
			<p class="description"><?php esc_html_e( 'To quickly assign questions to a dimension, separate the question numbers (the same numbers you see in the "Questions" tab) with commas and enter them in the box next to that dimension; example: 5, 6, 7, 15', 'ravanix' ); ?></p>
		<?php endif; ?>
		<?php if ( empty( $test->dimensions ) ) : ?>
			<p><?php esc_html_e( 'No dimension has been defined yet. If your test is single-dimensional (like a simple screening test), still create one dimension with the test\'s general name so scoring and interpretation can be performed.', 'ravanix' ); ?></p>
		<?php else : ?>
			<?php
			// Maps question ID to its sequential position (the same number shown in the "Questions" tab)
			$position_by_qid = array();
			foreach ( $test->questions as $i => $q ) {
				$position_by_qid[ $q->id ] = $i + 1;
			}
			?>
			<table class="wp-list-table widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Name', 'ravanix' ); ?></th>
					<th><?php esc_html_e( 'Code', 'ravanix' ); ?></th>
					<th><?php esc_html_e( 'Number of questions', 'ravanix' ); ?></th>
					<th><?php esc_html_e( 'Quick-assign questions (by number)', 'ravanix' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ravanix' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $test->dimensions as $d ) : ?>
					<?php
					$positions = array();
					foreach ( $d->questions as $q ) {
						if ( isset( $position_by_qid[ $q->id ] ) ) {
							$positions[] = $position_by_qid[ $q->id ];
						}
					}
					sort( $positions );
					?>
					<tr>
						<td><?php echo esc_html( $d->name ); ?></td>
						<td><code><?php echo esc_html( $d->code ); ?></code></td>
						<td><?php echo esc_html( count( $d->questions ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rs-assign-form">
								<?php wp_nonce_field( 'ravanix_assign_dimension_questions' ); ?>
								<input type="hidden" name="action" value="ravanix_assign_dimension_questions">
								<input type="hidden" name="test_id" value="<?php echo intval( $test_id ); ?>">
								<input type="hidden" name="dimension_id" value="<?php echo intval( $d->id ); ?>">
								<input type="text" name="question_numbers" class="regular-text" dir="ltr"
									value="<?php echo esc_attr( implode( ', ', $positions ) ); ?>"
									placeholder="<?php esc_attr_e( '5, 6, 7, 15', 'ravanix' ); ?>">
								<button type="submit" class="button button-small"><?php esc_html_e( 'Update', 'ravanix' ); ?></button>
							</form>
						</td>
						<td>
							<a href="<?php echo esc_url( $base_url . '&tab=dimensions&edit_dimension=' . $d->id ); ?>"><?php esc_html_e( 'Edit', 'ravanix' ); ?></a>
							|
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ravanix_delete_dimension&dimension_id=' . $d->id . '&test_id=' . $test_id ), 'ravanix_delete_dimension' ) ); ?>"
								onclick="return confirm('<?php echo esc_js( __( 'Delete this dimension? Related questions will not be deleted, but will be unassigned from it.', 'ravanix' ) ); ?>');" class="rs-link-danger"><?php esc_html_e( 'Delete', 'ravanix' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
