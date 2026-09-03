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
global $wpdb;

$result = $wpdb->get_row( $wpdb->prepare(
	"SELECT r.*, t.title AS test_title, u.display_name
	 FROM " . Ravanix_DB::results() . " r
	 LEFT JOIN " . Ravanix_DB::tests() . " t ON t.id = r.test_id
	 LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
	 WHERE r.id = %d", $view_result_id
) );

if ( ! $result ) {
	echo '<div class="wrap"><p>' . esc_html__( 'Result not found.', 'ravanix' ) . '</p></div>';
	return;
}

$scores = $wpdb->get_results( $wpdb->prepare(
	"SELECT rs.*, d.name AS dimension_name
	 FROM " . Ravanix_DB::result_scores() . " rs
	 LEFT JOIN " . Ravanix_DB::dimensions() . " d ON d.id = rs.dimension_id
	 WHERE rs.result_id = %d", $view_result_id
) );

$full_test  = Ravanix_DB::get_full_test( $result->test_id );
$p_meta     = ! empty( $result->participant_meta ) ? json_decode( $result->participant_meta, true ) : array();
$p_age      = isset( $p_meta['age']['value'] ) ? intval( $p_meta['age']['value'] ) : null;
// Prefer the stable 'key' saved alongside the translated display label (see
// Ravanix_Ajax::submit_test); fall back to matching the translated label itself
// only for results saved before this key existed.
$p_gender_v = isset( $p_meta['gender']['key'] ) ? $p_meta['gender']['key'] : ( isset( $p_meta['gender']['value'] ) ? $p_meta['gender']['value'] : null );
$p_gender   = in_array( $p_gender_v, array( 'male', 'female' ), true )
	? $p_gender_v
	: ( ( $p_gender_v === __( 'Male', 'ravanix' ) ) ? 'male' : ( ( $p_gender_v === __( 'Female', 'ravanix' ) ) ? 'female' : null ) );

/**
	 * The composite factor scores for this result, shown in a separate table below
	 * the dimension scores table. Always empty in the Lite version; Ravanix Pro
	 * uses this filter to compute composite factor scores from the stored dimension scores.
	 *
	 * @param array       $composite_scores Default: empty array.
	 * @param object      $full_test        Output of Ravanix_DB::get_full_test().
	 * @param array       $scores           The stored dimension score rows for this result.
	 * @param int|null    $p_age            Participant age (for age-based composite norm matching).
	 * @param string|null $p_gender         Participant gender ('male'/'female').
	 */
$composite_scores = apply_filters( 'ravanix_result_composite_scores', array(), $full_test, $scores, $p_age, $p_gender );

// See the matching comment in class-ravanix-ajax.php: some questionnaires are
// meant to be read as a ranking rather than each dimension's absolute level.
if ( ! empty( $full_test->rank_results ) ) {
	usort(
		$scores,
		function ( $a, $b ) {
			return $b->percentage <=> $a->percentage;
		}
	);
	usort(
		$composite_scores,
		function ( $a, $b ) {
			return $b['percentage'] <=> $a['percentage'];
		}
	);
}


$participant = $result->user_id && $result->display_name ? $result->display_name : ( $result->guest_name ?: __( 'Guest', 'ravanix' ) );
?>
<div class="wrap rs-wrap" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ravanix-results' ) ); ?>">&rarr; <?php esc_html_e( 'Back to results list', 'ravanix' ); ?></a>
		<?php
		/**
		 * Adds extra links next to "Back to results list" (like the PDF download
		 * link in Ravanix Pro).
		 *
		 * @param int $view_result_id The ID of the result currently being viewed.
		 */
		do_action( 'ravanix_result_view_actions', $view_result_id );
		?>
	</p>
	<h1><?php esc_html_e( 'Psychological profile:', 'ravanix' ); ?> <?php echo esc_html( $participant ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Test:', 'ravanix' ); ?> <strong><?php echo esc_html( $result->test_title ); ?></strong> —
		<?php esc_html_e( 'Date:', 'ravanix' ); ?> <?php echo esc_html( date_i18n( 'Y/m/d H:i', strtotime( $result->submitted_at ) ) ); ?>
	</p>

	<?php if ( ! empty( $result->is_validity_flagged ) ) : ?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'Validity warning:', 'ravanix' ); ?></strong> <?php echo esc_html( $result->validity_notes ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	$participant_meta = ! empty( $result->participant_meta ) ? json_decode( $result->participant_meta, true ) : array();
	?>
	<?php if ( ! empty( $participant_meta ) ) : ?>
		<table class="wp-list-table widefat striped" style="max-width:500px;margin-bottom:20px;">
			<tbody>
				<?php foreach ( $participant_meta as $field ) : ?>
					<tr>
						<th style="width:160px;"><?php echo esc_html( $field['label'] ); ?></th>
						<td><?php echo esc_html( $field['value'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php
	$can_radar    = count( $scores ) >= 3;
	$chart_labels = wp_json_encode( array_map( function( $s ) { return $s->dimension_name ?: ( __( 'Dimension #', 'ravanix' ) . $s->dimension_id ); }, $scores ) );
	$chart_data   = wp_json_encode( array_map( 'floatval', wp_list_pluck( $scores, 'percentage' ) ) );
	$chart_colors = wp_json_encode( array_map( function( $s ) { return $s->level_color ?: '#4a90d9'; }, $scores ) );
	?>
	<?php if ( ! empty( $scores ) ) : ?>
		<div id="rs-admin-chart-data"
			data-labels="<?php echo esc_attr( $chart_labels ); ?>"
			data-values="<?php echo esc_attr( $chart_data ); ?>"
			data-colors="<?php echo esc_attr( $chart_colors ); ?>"
			style="display:none;"></div>
		<div class="rs-chart-tabs">
			<button type="button" class="rs-chart-tab active" data-chart="bar"><?php esc_html_e( 'Bar chart', 'ravanix' ); ?></button>
			<?php if ( $can_radar ) : ?>
				<button type="button" class="rs-chart-tab" data-chart="radar"><?php esc_html_e( 'Radar chart', 'ravanix' ); ?></button>
			<?php endif; ?>
		</div>
		<div class="rs-chart-wrap" data-chart-view="bar" style="max-width:700px;height:<?php echo esc_attr( max( 340, count( $scores ) * 42 ) ); ?>px;">
			<canvas id="rs-admin-bar"></canvas>
		</div>
		<?php if ( $can_radar ) : ?>
			<div class="rs-chart-wrap" data-chart-view="radar" style="max-width:520px;height:340px;display:none;">
				<canvas id="rs-admin-radar"></canvas>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! empty( $composite_scores ) ) : ?>
		<h2><?php esc_html_e( 'Primary (composite) factor scores', 'ravanix' ); ?></h2>
		<table class="wp-list-table widefat striped" style="margin-bottom:25px;max-width:900px;">
			<thead><tr>
				<th><?php esc_html_e( 'Factor', 'ravanix' ); ?></th>
				<th><?php esc_html_e( 'Raw score', 'ravanix' ); ?></th>
				<th><?php esc_html_e( 'Percentage', 'ravanix' ); ?></th>
				<th><?php esc_html_e( 'T-score', 'ravanix' ); ?></th>
				<th><?php esc_html_e( 'Percentile rank', 'ravanix' ); ?></th>
				<th><?php esc_html_e( 'Level', 'ravanix' ); ?></th>
				<th><?php esc_html_e( 'Interpretation', 'ravanix' ); ?></th>
			</tr></thead>
			<tbody>
				<?php foreach ( $composite_scores as $cs ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $cs['name'] ); ?></strong></td>
						<td><?php echo esc_html( $cs['raw_score'] . ' ' . __( 'From', 'ravanix' ) . ' ' . $cs['max_score'] ); ?></td>
						<td><?php echo esc_html( $cs['percentage'] ); ?>%</td>
						<td><?php echo ( null !== $cs['t_score'] ) ? esc_html( $cs['t_score'] ) : '—'; ?></td>
						<td><?php echo ( null !== $cs['percentile'] ) ? esc_html( $cs['percentile'] ) . '%' : '—'; ?></td>
						<td><span class="rs-badge" style="background:<?php echo esc_attr( $cs['level_color'] ); ?>;color:#fff;"><?php echo esc_html( $cs['level_label'] ); ?></span></td>
						<td><?php echo wp_kses_post( $cs['description'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<table class="wp-list-table widefat striped" style="margin-top:20px;max-width:900px;">
		<thead><tr>
			<th><?php esc_html_e( 'Dimension', 'ravanix' ); ?></th>
			<th><?php esc_html_e( 'Raw score', 'ravanix' ); ?></th>
			<th><?php esc_html_e( 'Percentage', 'ravanix' ); ?></th>
			<th><?php esc_html_e( 'T-score', 'ravanix' ); ?></th>
			<th><?php esc_html_e( 'Percentile rank', 'ravanix' ); ?></th>
			<th><?php esc_html_e( 'Level', 'ravanix' ); ?></th>
			<th><?php esc_html_e( 'Interpretation', 'ravanix' ); ?></th>
		</tr></thead>
		<tbody>
			<?php foreach ( $scores as $s ) : ?>
				<tr>
					<td><?php echo esc_html( $s->dimension_name ?: ( __( 'Dimension #', 'ravanix' ) . $s->dimension_id ) ); ?></td>
					<td><?php echo esc_html( $s->raw_score . ' ' . __( 'From', 'ravanix' ) . ' ' . $s->max_score ); ?></td>
					<td><?php echo esc_html( $s->percentage ); ?>%</td>
					<td>
						<?php if ( null !== $s->t_score ) : ?>
							<?php echo esc_html( $s->t_score ); ?>
							<?php if ( $s->norm_group_label ) : ?>
								<br><span class="description"><?php echo esc_html( $s->norm_group_label ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td><?php echo ( null !== $s->percentile ) ? esc_html( $s->percentile ) . '%' : '—'; ?></td>
					<td><span class="rs-badge"><?php echo esc_html( $s->level_label ); ?></span></td>
					<td><?php echo wp_kses_post( $s->description ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php
	/**
	 * Fires after the dimension scores table, still inside the results wrap.
	 * Ravanix Pro uses this to show a trend/timeline chart comparing this
	 * identified user's scores across their repeated attempts of the same
	 * test. $result->user_id is 0 for guest submissions, so implementations
	 * of this action should skip guests (a guest has no stable identity to
	 * track a trend against).
	 *
	 * @param object $result The full result row queried above (includes id, test_id, user_id, submitted_at, ...).
	 */
	do_action( 'ravanix_result_view_after_scores', $result );
	?>
</div>
