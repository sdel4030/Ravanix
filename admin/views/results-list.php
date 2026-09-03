<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// This file is a template/view loaded only via include() inside methods of the
// plugin's own classes, never standalone; so its local variables never actually
// enter the real global namespace, and there is no risk of collision with
// another plugin/theme. Forcing a prefix on the dozens of local variables in
// this file would only reduce readability.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
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
global $wpdb;

$view_result_id = isset( $_GET['result_id'] ) ? intval( $_GET['result_id'] ) : 0;
if ( $view_result_id ) {
	include RAVANIX_PLUGIN_DIR . 'admin/views/result-view.php';
	return;
}

$filter_test_id = isset( $_GET['test_id'] ) ? intval( $_GET['test_id'] ) : 0;

$tests_for_filter = $wpdb->get_results( "SELECT id, title FROM " . Ravanix_DB::tests() . " ORDER BY title ASC" );

$where = '';
if ( $filter_test_id ) {
	$where = $wpdb->prepare( ' WHERE r.test_id = %d', $filter_test_id );
}

$results = $wpdb->get_results(
	// $where is already fully built via $wpdb->prepare() above (or left as an
	// empty string); interpolating it here does not introduce any unescaped
	// user input, but the sniff below can't verify that since it only sees
	// this string, not where $where came from. Disabled for the whole
	// statement (not phpcs:ignore on a single line) since the flagged token
	// is a few lines further down, inside this same multi-line string.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	"SELECT r.*, t.title AS test_title, u.display_name
	 FROM " . Ravanix_DB::results() . " r
	 LEFT JOIN " . Ravanix_DB::tests() . " t ON t.id = r.test_id
	 LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
	 {$where}
	 ORDER BY r.submitted_at DESC
	 LIMIT 300"
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
);
?>
<div class="wrap rs-wrap" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
	<h1><?php esc_html_e( 'Participant Results', 'ravanix' ); ?></h1>

	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The result was deleted.', 'ravanix' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['bulk_done'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bulk action completed successfully.', 'ravanix' ); ?></p></div>
	<?php endif; ?>

	<form method="get" style="margin:15px 0;">
		<input type="hidden" name="page" value="ravanix-results">
		<label><?php esc_html_e( 'Filter by test:', 'ravanix' ); ?></label>
		<select name="test_id" onchange="this.form.submit()">
			<option value="0"><?php esc_html_e( 'All Tests', 'ravanix' ); ?></option>
			<?php foreach ( $tests_for_filter as $t ) : ?>
				<option value="<?php echo intval( $t->id ); ?>" <?php selected( $filter_test_id, $t->id ); ?>><?php echo esc_html( $t->title ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		/**
		 * Adds an extra button/link next to the test filter (like "CSV export" in Ravanix Pro).
		 *
		 * @param int $filter_test_id The filtered test's ID (0 for all tests).
		 */
		do_action( 'ravanix_results_list_toolbar_actions', $filter_test_id );
		?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rs-results-bulk-form">
		<?php wp_nonce_field( 'ravanix_bulk_results_action' ); ?>
		<input type="hidden" name="action" value="ravanix_bulk_results_action">
		<input type="hidden" name="filter_test_id" value="<?php echo intval( $filter_test_id ); ?>">

		<div class="tablenav top">
			<div class="alignleft actions">
				<select name="bulk_action">
					<option value=""><?php esc_html_e( 'Bulk actions', 'ravanix' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'ravanix' ); ?></option>
				</select>
				<button type="submit" class="button"
					onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete the selected items?', 'ravanix' ) ); ?>');">
					<?php esc_html_e( 'Apply', 'ravanix' ); ?>
				</button>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped rs-table">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column"><input type="checkbox" id="rs-results-select-all"></td>
					<th><?php esc_html_e( 'Date', 'ravanix' ); ?></th>
					<th class="column-primary"><?php esc_html_e( 'Test', 'ravanix' ); ?></th>
					<th><?php esc_html_e( 'Participant', 'ravanix' ); ?></th>
					<th><?php esc_html_e( 'Validity', 'ravanix' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ravanix' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $results ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No results have been recorded yet.', 'ravanix' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $results as $r ) : ?>
					<?php
					$participant_meta = ! empty( $r->participant_meta ) ? json_decode( $r->participant_meta, true ) : array();
					$display_label    = '';
					if ( ! empty( $participant_meta['full_name']['value'] ) ) {
						$display_label = $participant_meta['full_name']['value'];
					} elseif ( $r->user_id && $r->display_name ) {
						$display_label = $r->display_name;
					} elseif ( $r->guest_name ) {
						$display_label = $r->guest_name;
					}
					?>
					<tr>
						<th class="check-column"><input type="checkbox" name="result_ids[]" value="<?php echo intval( $r->id ); ?>" class="rs-row-checkbox"></th>
						<td data-colname="<?php esc_attr_e( 'Date', 'ravanix' ); ?>"><?php echo esc_html( date_i18n( 'Y/m/d H:i', strtotime( $r->submitted_at ) ) ); ?></td>
						<td class="column-primary" data-colname="<?php esc_attr_e( 'Test', 'ravanix' ); ?>">
							<?php echo esc_html( $r->test_title ); ?>
							<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'ravanix' ); ?></span></button>
						</td>
						<td data-colname="<?php esc_attr_e( 'Participant', 'ravanix' ); ?>">
							<?php if ( $display_label && ! $r->user_id ) : ?>
								<?php echo esc_html( $display_label ); ?> <span class="description">(<?php esc_html_e( 'Guest', 'ravanix' ); ?>)</span>
							<?php elseif ( $display_label ) : ?>
								<?php echo esc_html( $display_label ); ?>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Guest', 'ravanix' ); ?></span>
							<?php endif; ?>
						</td>
						<td data-colname="<?php esc_attr_e( 'Validity', 'ravanix' ); ?>">
							<?php if ( ! empty( $r->is_validity_flagged ) ) : ?>
								<span class="rs-badge" style="background:#fff3cd;color:#7a5b00;">⚠ <?php esc_html_e( 'Questionable', 'ravanix' ); ?></span>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
						<td data-colname="<?php esc_attr_e( 'Actions', 'ravanix' ); ?>">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ravanix-results&result_id=' . $r->id ) ); ?>"><?php esc_html_e( 'View profile', 'ravanix' ); ?></a>
							<?php
							/**
							 * Adds extra links for each result row (like the PDF download link in
							 * Ravanix Pro). Each item this action echoes must start with " | " (its
							 * own leading separator) and must NOT add a trailing one, so that any
							 * number of items -- zero or more -- compose correctly with the "Delete"
							 * link that always follows.
							 *
							 * @param int $result_id The result's ID.
							 */
							do_action( 'ravanix_results_list_row_actions', $r->id );
							?>
							|
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ravanix_delete_result&result_id=' . $r->id ), 'ravanix_delete_result' ) ); ?>"
								onclick="return confirm('<?php echo esc_js( __( 'Delete this result?', 'ravanix' ) ); ?>');" class="rs-link-danger"><?php esc_html_e( 'Delete', 'ravanix' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</form>
</div>

