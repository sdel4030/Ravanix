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

// Status filter (matches the native "All | Published | Draft" links shown at
// the top of WordPress's own "All Posts" screen).
$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
if ( ! in_array( $status_filter, array( 'published', 'draft' ), true ) ) {
	$status_filter = '';
}

$counts_raw = $wpdb->get_results( 'SELECT status, COUNT(*) AS cnt FROM ' . Ravanix_DB::tests() . ' GROUP BY status' );
$counts     = array(
	'all'       => 0,
	'published' => 0,
	'draft'     => 0,
);
foreach ( $counts_raw as $row ) {
	$counts['all'] += intval( $row->cnt );
	if ( isset( $counts[ $row->status ] ) ) {
		$counts[ $row->status ] = intval( $row->cnt );
	}
}

$where = '';
if ( '' !== $status_filter ) {
	$where = $wpdb->prepare( ' WHERE t.status = %s', $status_filter );
}

$tests = $wpdb->get_results(
	"SELECT t.*, 
	(SELECT COUNT(*) FROM " . Ravanix_DB::questions() . ' q WHERE q.test_id = t.id) AS question_count,
	(SELECT COUNT(*) FROM ' . Ravanix_DB::results() . " r WHERE r.test_id = t.id) AS result_count
	FROM " . Ravanix_DB::tests() . ' t' . $where . ' ORDER BY t.id DESC'
);

$cpt_enabled = class_exists( 'Ravanix_CPT' ) && Ravanix_CPT::is_enabled();

// A local closure (not a named global function) so this template can safely
// be require()'d more than once in the same request without a "cannot
// redeclare function" fatal error.
$ravanix_status_link = function ( $status, $label, $count ) use ( $status_filter ) {
	$url   = ( '' === $status ) ? admin_url( 'admin.php?page=ravanix' ) : admin_url( 'admin.php?page=ravanix&status=' . $status );
	$class = ( $status === $status_filter ) ? ' class="current" aria-current="page"' : '';
	return '<a href="' . esc_url( $url ) . '"' . $class . '>' . esc_html( $label ) . ' <span class="count">(' . intval( $count ) . ')</span></a>';
};
?>
<div class="wrap rs-wrap" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Psychological Tests', 'ravanix-lite' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=ravanix-edit-test' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Test', 'ravanix-lite' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The item was deleted.', 'ravanix-lite' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['bulk_done'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bulk action completed successfully.', 'ravanix-lite' ); ?></p></div>
	<?php endif; ?>

	<ul class="subsubsub">
		<li class="all"><?php echo wp_kses_post( $ravanix_status_link( '', __( 'All', 'ravanix-lite' ), $counts['all'] ) ); ?> |</li>
		<li class="publish"><?php echo wp_kses_post( $ravanix_status_link( 'published', __( 'Published', 'ravanix-lite' ), $counts['published'] ) ); ?> |</li>
		<li class="draft"><?php echo wp_kses_post( $ravanix_status_link( 'draft', __( 'Draft', 'ravanix-lite' ), $counts['draft'] ) ); ?></li>
	</ul>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rs-tests-bulk-form">
		<?php wp_nonce_field( 'ravanix_bulk_tests_action' ); ?>
		<input type="hidden" name="action" value="ravanix_bulk_tests_action">

		<div class="tablenav top">
			<div class="alignleft actions">
				<select name="bulk_action">
					<option value=""><?php esc_html_e( 'Bulk actions', 'ravanix-lite' ); ?></option>
					<option value="publish"><?php esc_html_e( 'Publish', 'ravanix-lite' ); ?></option>
					<option value="draft"><?php esc_html_e( 'Move to draft', 'ravanix-lite' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'ravanix-lite' ); ?></option>
				</select>
				<button type="submit" class="button" id="rs-tests-bulk-apply"
					onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to perform this action on the selected items?', 'ravanix-lite' ) ); ?>');">
					<?php esc_html_e( 'Apply', 'ravanix-lite' ); ?>
				</button>
			</div>
			<br class="clear">
		</div>

		<table class="wp-list-table widefat fixed striped rs-table">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column"><input type="checkbox" id="rs-tests-select-all"></td>
					<th class="column-primary"><?php esc_html_e( 'Title', 'ravanix-lite' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ravanix-lite' ); ?></th>
					<th><?php esc_html_e( 'Number of questions', 'ravanix-lite' ); ?></th>
					<th><?php esc_html_e( 'Number of participants', 'ravanix-lite' ); ?></th>
					<th><?php esc_html_e( 'Display code (shortcode)', 'ravanix-lite' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $tests ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No test has been created yet.', 'ravanix-lite' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $tests as $t ) : ?>
					<?php
					$edit_url    = admin_url( 'admin.php?page=ravanix-edit-test&test_id=' . $t->id );
					$results_url = admin_url( 'admin.php?page=ravanix-results&test_id=' . $t->id );
					$delete_url  = wp_nonce_url( admin_url( 'admin-post.php?action=ravanix_delete_test&test_id=' . $t->id ), 'ravanix_delete_test' );
					$view_url    = ( $cpt_enabled && ! empty( $t->cpt_post_id ) && get_post( $t->cpt_post_id ) ) ? get_permalink( $t->cpt_post_id ) : '';
					?>
					<tr>
						<th class="check-column"><input type="checkbox" name="test_ids[]" value="<?php echo intval( $t->id ); ?>" class="rs-row-checkbox"></th>
						<td class="column-primary" data-colname="<?php esc_attr_e( 'Title', 'ravanix-lite' ); ?>">
							<strong>
								<a href="<?php echo esc_url( $edit_url ); ?>">
									<?php echo esc_html( $t->title ); ?>
								</a>
							</strong>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'ravanix-lite' ); ?></a></span>
								<?php if ( $view_url ) : ?>
									 | <span class="view"><a href="<?php echo esc_url( $view_url ); ?>" target="_blank"><?php esc_html_e( 'View', 'ravanix-lite' ); ?></a></span>
								<?php endif; ?>
								 | <span class="results"><a href="<?php echo esc_url( $results_url ); ?>"><?php esc_html_e( 'View Results', 'ravanix-lite' ); ?></a></span>
								 | <span class="delete">
									<a href="<?php echo esc_url( $delete_url ); ?>"
										onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this test and all of its results?', 'ravanix-lite' ) ); ?>');"
										class="rs-link-danger"><?php esc_html_e( 'Delete', 'ravanix-lite' ); ?></a>
								</span>
							</div>
							<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'ravanix-lite' ); ?></span></button>
						</td>
						<td data-colname="<?php esc_attr_e( 'Status', 'ravanix-lite' ); ?>">
							<?php if ( 'published' === $t->status ) : ?>
								<span class="rs-badge rs-badge-green"><?php esc_html_e( 'Published', 'ravanix-lite' ); ?></span>
							<?php else : ?>
								<span class="rs-badge rs-badge-gray"><?php esc_html_e( 'Draft', 'ravanix-lite' ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $t->woocommerce_product_id ) ) : ?>
								<span class="rs-badge" style="background:#e6f6ea;color:#2a8a45;">💰 <?php esc_html_e( 'Paid', 'ravanix-lite' ); ?></span>
							<?php endif; ?>
						</td>
						<td data-colname="<?php esc_attr_e( 'Number of questions', 'ravanix-lite' ); ?>"><?php echo intval( $t->question_count ); ?></td>
						<td data-colname="<?php esc_attr_e( 'Number of participants', 'ravanix-lite' ); ?>"><?php echo intval( $t->result_count ); ?></td>
						<td data-colname="<?php esc_attr_e( 'Display code (shortcode)', 'ravanix-lite' ); ?>"><code>[ravanix_test id="<?php echo intval( $t->id ); ?>"]</code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</form>
</div>