<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// This file is a template/view loaded only via include() inside methods of the
// plugin's own classes, never standalone; so its local variables never actually
// enter the real global namespace, and there is no risk of collision with
// another plugin/theme. Forcing a prefix on the local variables in this file
// would only reduce readability.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/**
	 * The "Upgrade to Pro" page. This page is purely informational; it does not
	 * show any nag/notice anywhere in WordPress outside this dedicated page, and
	 * does not lock or restrict any Lite feature — per the WordPress.org
	 * repository's "Trialware" guideline, this is the only permitted way to
	 * promote a paid version.
	 *
	 * @link https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#5-plugins-may-not-embed-external-links-or-ads-except-as-permitted
	 */

$purchase_url = 'https://psykey.ir/ravanix-pro';

/**
	 * Comparison table rows, grouped by category. Each row: [feature label, in Lite? (bool), in Pro? (bool)].
	 * The Pro plugin can use this filter to add a new row or group to the table so
	 * this page always reflects exactly what's in the actually-installed Pro version.
	 */
$groups = apply_filters(
	'ravanix_upgrade_comparison_groups',
	array(
		__( 'Building questionnaires', 'ravanix' ) => array(
			array( __( 'Unlimited dimensions, questions, and options', 'ravanix' ), true, true ),
			array( __( 'Bulk paste-import of questions', 'ravanix' ), true, true ),
			array( __( 'Multiple-choice question with a separate dimension per option (forced-choice)', 'ravanix' ), false, true ),
			array( __( 'Multi-scale overlapping keying', 'ravanix' ), false, true ),
		),
		__( 'Scoring and interpretation', 'ravanix' ) => array(
			array( __( 'Raw score, percentage, and interpretation range', 'ravanix' ), true, true ),
			array( __( 'Norm tables and T/Z scores', 'ravanix' ), false, true ),
			array( __( 'Percentile rank', 'ravanix' ), false, true ),
			array( __( 'Composite factors', 'ravanix' ), false, true ),
			array( __( 'Validity scales', 'ravanix' ), false, true ),
		),
		__( 'Access and execution control', 'ravanix' ) => array(
			array( __( 'Access codes to restrict participants', 'ravanix' ), false, true ),
			array( __( 'Limiting the number of times a test can be run', 'ravanix' ), false, true ),
			array( __( 'Selling access to a test via WooCommerce', 'ravanix' ), false, true ),
		),
		__( 'Export and reporting', 'ravanix' ) => array(
			array( __( 'Display of the result with a chart on the site', 'ravanix' ), true, true ),
			array( __( 'PDF export of each participant\'s result', 'ravanix' ), false, true ),
			array( __( 'CSV/Excel export of all results', 'ravanix' ), false, true ),
			array( __( 'Timeline chart of a user\'s results (pre/post)', 'ravanix' ), false, true ),
			array( __( 'Full JSON import/export of a test', 'ravanix' ), false, true ),
		),
		__( 'Support', 'ravanix' ) => array(
			array( __( 'WordPress.org forum support', 'ravanix' ), true, true ),
			array( __( 'Dedicated, priority support', 'ravanix' ), false, true ),
		),
	)
);
?>
<div class="wrap rs-wrap" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
	<h1><?php esc_html_e( 'Upgrade to Ravanix Pro', 'ravanix' ); ?></h1>
	<p class="rs-upgrade-lede">
		<?php esc_html_e( 'Ravanix (your current version) is fully and freely available for building, running, and displaying psychological questionnaires. The Pro version adds specialized capabilities needed for clinical and research use — including norms, composite scores, PDF/CSV export, and access control.', 'ravanix' ); ?>
	</p>

	<div class="rs-compare-table-wrap">
	<table class="rs-compare-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Feature', 'ravanix' ); ?></th>
				<th scope="col" class="rs-compare-col"><?php esc_html_e( 'Lite (your current version)', 'ravanix' ); ?></th>
				<th scope="col" class="rs-compare-col rs-compare-col-pro"><?php esc_html_e( 'Pro', 'ravanix' ); ?></th>
			</tr>
		</thead>
		<?php foreach ( $groups as $group_title => $rows ) : ?>
			<tbody>
				<tr class="rs-compare-group-row">
					<th colspan="3" scope="colgroup"><?php echo esc_html( $group_title ); ?></th>
				</tr>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row[0] ); ?></td>
						<td class="rs-compare-col">
							<?php if ( $row[1] ) : ?>
								<span class="rs-compare-icon rs-yes"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Available', 'ravanix' ); ?></span>
							<?php else : ?>
								<span class="rs-compare-icon rs-no"><span class="dashicons dashicons-minus" aria-hidden="true"></span></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Not available', 'ravanix' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="rs-compare-col rs-compare-col-pro">
							<?php if ( $row[2] ) : ?>
								<span class="rs-compare-icon rs-yes"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Available', 'ravanix' ); ?></span>
							<?php else : ?>
								<span class="rs-compare-icon rs-no"><span class="dashicons dashicons-minus" aria-hidden="true"></span></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Not available', 'ravanix' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		<?php endforeach; ?>
	</table>
	</div>

	<div class="rs-upgrade-cta">
		<a href="<?php echo esc_url( $purchase_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-hero">
			<?php esc_html_e( 'View pricing and buy Pro', 'ravanix' ); ?>
		</a>
		<p class="description">
			<?php esc_html_e( 'Upgrading to Pro requires installing a separate plugin (Ravanix Pro) alongside this one; none of your data will be moved or deleted.', 'ravanix' ); ?>
		</p>
	</div>
</div>
