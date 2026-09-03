<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// This file is a template/view loaded only via include() inside methods of the
// plugin's own classes, never standalone; so its local variables never actually
// enter the real global namespace, and there is no risk of collision with
// another plugin/theme. Forcing a prefix on the dozens of local variables in
// this file would only reduce readability.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$total_questions = count( $test->questions );
$has_no_dim_questions = 0;
foreach ( $test->questions as $q ) {
	if ( empty( $q->dimension_id ) ) { $has_no_dim_questions++; }
}
?>
<div class="rs-preview-box">
	<h2><?php esc_html_e( 'Test readiness status', 'ravanix' ); ?></h2>
	<ul class="rs-checklist">
		<li class="<?php echo $total_questions > 0 ? 'ok' : 'warn'; ?>">
			<?php echo $total_questions > 0 ? '✔' : '✘'; ?> <?php esc_html_e( 'Number of questions:', 'ravanix' ); ?> <?php echo intval( $total_questions ); ?>
		</li>
		<li class="<?php echo count( $test->dimensions ) > 0 ? 'ok' : 'warn'; ?>">
			<?php echo count( $test->dimensions ) > 0 ? '✔' : '✘'; ?> <?php esc_html_e( 'Number of dimensions:', 'ravanix' ); ?> <?php echo esc_html( count( $test->dimensions ) ); ?>
		</li>
		<li class="<?php echo $has_no_dim_questions === 0 ? 'ok' : 'warn'; ?>">
			<?php echo $has_no_dim_questions === 0 ? '✔' : '✘'; ?>
			<?php
			if ( 0 === $has_no_dim_questions ) {
				echo esc_html__( 'All questions belong to a dimension', 'ravanix' );
			} else {
				/* translators: %d: the number of questions that are not assigned to any dimension. */
				echo esc_html( sprintf( __( '%d question(s) are not assigned to any dimension (they are not included in scoring)', 'ravanix' ), $has_no_dim_questions ) );
			}
			?>
		</li>
		<li class="<?php echo 'published' === $test->status ? 'ok' : 'warn'; ?>">
			<?php echo 'published' === $test->status ? '✔ ' . esc_html__( 'The test is published and visible', 'ravanix' ) : '✘ ' . esc_html__( 'The test is still in draft mode — change its status to "Published" in the "General Info" tab', 'ravanix' ); ?>
		</li>
		<li class="ok">
			<?php if ( ! empty( $test->questions_per_page ) ) : ?>
				✔ <?php
				/* translators: %1$d: number of questions per page. %2$d: total number of pages. */
				printf( esc_html__( 'Pagination is enabled: %1$d question(s) per page (%2$d pages)', 'ravanix' ), intval( $test->questions_per_page ), (int) ceil( $total_questions / max( 1, intval( $test->questions_per_page ) ) ) );
				?>
			<?php else : ?>
				✔ <?php esc_html_e( 'All questions are shown on a single page (no pagination)', 'ravanix' ); ?>
			<?php endif; ?>
		</li>
	</ul>

	<h2><?php esc_html_e( 'Display code for a page or post', 'ravanix' ); ?></h2>
	<p><?php esc_html_e( 'To display this test on any page or post on your site, place the code below in the editor:', 'ravanix' ); ?></p>
	<p><code style="font-size:16px;">[ravanix_test id="<?php echo intval( $test_id ); ?>"]</code></p>

	<h2><?php esc_html_e( 'Question preview', 'ravanix' ); ?></h2>
	<?php if ( empty( $test->questions ) ) : ?>
		<p><?php esc_html_e( 'No question has been added yet.', 'ravanix' ); ?></p>
	<?php else : ?>
		<ol class="rs-preview-list">
			<?php foreach ( $test->questions as $q ) : ?>
				<li><?php echo esc_html( $q->question_text ); ?> <span class="description">(<?php echo esc_html( $q->question_type ); ?><?php echo $q->is_reverse ? ', ' . esc_html__( 'Reverse', 'ravanix' ) : ''; ?>)</span></li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</div>
