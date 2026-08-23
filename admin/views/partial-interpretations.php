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

if ( empty( $test->dimensions ) ) :
	?>
	<p><?php esc_html_e( 'You must first define at least one dimension in the "Dimensions / Subscales" tab so you can add an interpretation range for it.', 'ravanix' ); ?></p>
	<?php
	return;
endif;

$edit_id = isset( $_GET['edit_interpretation'] ) ? intval( $_GET['edit_interpretation'] ) : 0;
$editing = null;
$editing_dim_id = isset( $_GET['dimension_id'] ) ? intval( $_GET['dimension_id'] ) : $test->dimensions[0]->id;

foreach ( $test->dimensions as $d ) {
	foreach ( $d->interpretations as $int ) {
		if ( $int->id == $edit_id ) { $editing = $int; $editing_dim_id = $d->id; break 2; }
	}
}
?>
<div class="rs-columns">
	<div class="rs-col-form">
		<h2><?php echo $editing ? esc_html__( 'Edit Interpretation Range', 'ravanix' ) : esc_html__( 'Add Interpretation Range', 'ravanix' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ravanix_save_interpretation' ); ?>
			<input type="hidden" name="action" value="ravanix_save_interpretation">
			<input type="hidden" name="test_id" value="<?php echo intval( $test_id ); ?>">
			<input type="hidden" name="interpretation_id" value="<?php echo $editing ? intval( $editing->id ) : 0; ?>">

			<p>
				<label><?php esc_html_e( 'Related dimension', 'ravanix' ); ?></label><br>
				<select name="dimension_id" id="rs-interp-dimension">
					<?php foreach ( $test->dimensions as $d ) : ?>
						<option value="<?php echo intval( $d->id ); ?>" data-basis="<?php echo esc_attr( $d->interpretation_basis ); ?>" <?php selected( $editing_dim_id, $d->id ); ?>><?php echo esc_html( $d->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description" id="rs-interp-basis-hint"></p>
			</p>
			<p>
				<label><?php esc_html_e( 'Minimum score of this range', 'ravanix' ); ?></label><br>
				<input type="number" step="any" name="range_min" required value="<?php echo $editing ? esc_attr( $editing->range_min ) : ''; ?>">
			</p>
			<p>
				<label><?php esc_html_e( 'Maximum score of this range', 'ravanix' ); ?></label><br>
				<input type="number" step="any" name="range_max" required value="<?php echo $editing ? esc_attr( $editing->range_max ) : ''; ?>">
			</p>
			<p>
				<label><?php esc_html_e( 'Level label (e.g.: Low / Moderate / High)', 'ravanix' ); ?></label><br>
				<input type="text" name="level_label" class="regular-text" required value="<?php echo $editing ? esc_attr( $editing->level_label ) : ''; ?>">
			</p>
			<p>
				<label><?php esc_html_e( 'Chart display color', 'ravanix' ); ?></label><br>
				<input type="text" name="level_color" value="<?php echo $editing ? esc_attr( $editing->level_color ) : '#4a90d9'; ?>" class="rs-color-field">
			</p>
			<p>
				<label><?php esc_html_e( 'Interpretive description (shown to the user)', 'ravanix' ); ?></label><br>
				<?php
				wp_editor(
					$editing ? $editing->description : '',
					'ravanix_interpretation_description',
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

			<?php submit_button( $editing ? __( 'Save Changes', 'ravanix' ) : __( 'Add Range', 'ravanix' ) ); ?>
			<?php if ( $editing ) : ?>
				<a href="<?php echo esc_url( $base_url . '&tab=interpretations' ); ?>"><?php esc_html_e( 'Cancel', 'ravanix' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<div class="rs-col-list">
		<?php foreach ( $test->dimensions as $d ) : ?>
			<h3><?php echo esc_html( $d->name ); ?></h3>
			<?php if ( empty( $d->interpretations ) ) : ?>
				<p class="description"><?php esc_html_e( 'No interpretation range has been defined for this dimension.', 'ravanix' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat striped" style="margin-bottom:20px;">
					<thead><tr><th><?php esc_html_e( 'Range', 'ravanix' ); ?></th><th><?php esc_html_e( 'Label', 'ravanix' ); ?></th><th><?php esc_html_e( 'Color', 'ravanix' ); ?></th><th><?php esc_html_e( 'Actions', 'ravanix' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $d->interpretations as $int ) : ?>
						<tr>
							<td><?php echo esc_html( $int->range_min . ' ' . __( 'to', 'ravanix' ) . ' ' . $int->range_max ); ?></td>
							<td><?php echo esc_html( $int->level_label ); ?></td>
							<td><span class="rs-color-dot" style="background:<?php echo esc_attr( $int->level_color ); ?>"></span></td>
							<td>
								<a href="<?php echo esc_url( $base_url . '&tab=interpretations&edit_interpretation=' . $int->id . '&dimension_id=' . $d->id ); ?>"><?php esc_html_e( 'Edit', 'ravanix' ); ?></a>
								|
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ravanix_delete_interpretation&interpretation_id=' . $int->id . '&test_id=' . $test_id ), 'ravanix_delete_interpretation' ) ); ?>"
									onclick="return confirm('<?php echo esc_js( __( 'Delete this range?', 'ravanix' ) ); ?>');" class="rs-link-danger"><?php esc_html_e( 'Delete', 'ravanix' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>
