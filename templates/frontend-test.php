<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// This file is a template/view loaded only via include() inside methods of the
// plugin's own classes, never standalone; so its local variables never actually
// enter the real global namespace, and there is no risk of collision with
// another plugin/theme. Forcing a prefix on the dozens of local variables in
// this file would only reduce readability.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
//
// The one query in this file (the server-side Save & Resume draft lookup
// below) gets its table name from Ravanix_DB::drafts(), a fixed, predefined
// string, never user input; both actual values (test_id, user_id) go through
// $wpdb->prepare() with %d placeholders. The "direct query"/"no caching"
// warnings are unavoidable for this plugin's custom tables, since WordPress
// provides no ready-made API for tables other than its own core tables.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
/** @var object $test Output of Ravanix_DB::get_full_test() - available from the Ravanix_Shortcodes class */

$per_page         = ! empty( $test->questions_per_page ) ? intval( $test->questions_per_page ) : 0; // 0 means no pagination
$total_questions  = count( $test->questions );
$total_pages      = $per_page > 0 ? max( 1, (int) ceil( $total_questions / $per_page ) ) : 1;
$is_paginated     = $total_pages > 1;

// Informed consent: see Ravanix_Settings::get_effective_consent_text() for the
// mode-resolution rules (also used server-side by the AJAX submit handler, so
// the two can never disagree about whether this test requires consent).
$consent_text      = Ravanix_Settings::get_effective_consent_text( $test );
$requires_consent  = '' !== trim( wp_strip_all_tags( $consent_text ) );

$enable_save_resume = ! empty( $test->enable_save_resume );

// Fetched inline (rather than via a separate AJAX call on page load) so the
// resume banner can appear immediately without a round-trip. Only for a
// logged-in participant on a test where the admin turned this on; see
// Ravanix_Ajax::save_draft() for how this row is written.
$server_draft = null;
if ( $enable_save_resume && is_user_logged_in() ) {
	global $wpdb;
	$row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT answers_json, participant_json, page, updated_at FROM ' . Ravanix_DB::drafts() . ' WHERE test_id = %d AND user_id = %d',
			$test->id,
			get_current_user_id()
		)
	);
	if ( $row ) {
		$server_draft = array(
			'answers'     => json_decode( $row->answers_json, true ) ?: array(),
			'participant' => json_decode( $row->participant_json, true ) ?: array(),
			'page'        => intval( $row->page ),
			// updated_at is stored in the site's local time (current_time('mysql'),
			// the same convention used for every other timestamp column in this
			// plugin), so it must be converted to true UTC via get_gmt_from_date()
			// before being treated as UTC here -- otherwise this timestamp would be
			// off by the site's UTC offset, which could make a genuinely older
			// server draft look newer than a more recent browser-local draft (or
			// vice versa) when the two are compared client-side in milliseconds-since-epoch.
			'saved_at'    => strtotime( get_gmt_from_date( $row->updated_at ) . ' UTC' ) * 1000,
		);
	}
}
?>
<div class="rs-test-container" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>" data-test-id="<?php echo intval( $test->id ); ?>" data-total-pages="<?php echo intval( $total_pages ); ?>" data-save-resume="<?php echo $enable_save_resume ? '1' : '0'; ?>"
	<?php if ( $server_draft ) : ?>
		data-server-draft="<?php echo esc_attr( wp_json_encode( $server_draft ) ); ?>"
	<?php endif; ?>
	>

	<div class="rs-test-intro" id="rs-intro-<?php echo intval( $test->id ); ?>">
		<?php if ( empty( $hide_header ) ) : ?>
			<?php if ( ! empty( $test->featured_image_id ) ) : ?>
				<div class="rs-test-featured-image">
					<?php echo wp_get_attachment_image( $test->featured_image_id, 'large' ); ?>
				</div>
			<?php endif; ?>
			<h2 class="rs-test-title"><?php echo esc_html( $test->title ); ?></h2>
			<?php if ( ! empty( $test->description ) ) : ?>
				<div class="rs-test-desc"><?php echo wp_kses_post( wpautop( $test->description ) ); ?></div>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( ! empty( $test->instructions ) ) : ?>
			<div class="rs-test-instructions"><?php echo wp_kses_post( wpautop( esc_html( $test->instructions ) ) ); ?></div>
		<?php endif; ?>
		<p class="rs-test-meta"><?php
			/* translators: %d: total number of questions in this test. */
			printf( esc_html__( 'Number of questions: %d', 'ravanix' ), absint( $total_questions ) );
		?></p>
		<div class="rs-resume-banner" style="display:none;">
			<p><?php esc_html_e( 'Your previous progress on this test has been saved.', 'ravanix' ); ?></p>
			<button type="button" class="rs-btn rs-btn-start rs-btn-resume" <?php disabled( $requires_consent ); ?>><?php esc_html_e( 'Resume where you left off', 'ravanix' ); ?></button>
			<button type="button" class="rs-btn-link rs-btn-restart"><?php esc_html_e( 'Start over', 'ravanix' ); ?></button>
		</div>
		<?php if ( $requires_consent ) : ?>
			<div class="rs-consent-block">
				<details class="rs-consent-details">
					<summary><?php esc_html_e( 'Read the consent notice', 'ravanix' ); ?></summary>
					<div class="rs-consent-text"><?php echo wp_kses_post( $consent_text ); ?></div>
				</details>
				<label class="rs-consent-checkbox">
					<input type="checkbox" id="rs-consent-checkbox-<?php echo intval( $test->id ); ?>">
					<?php esc_html_e( 'I have read and agree to the notice above', 'ravanix' ); ?>
				</label>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $test->access_code ) ) : ?>
			<div class="rs-access-code-gate">
				<label for="rs-access-code-input"><?php esc_html_e( 'To begin, enter the access code:', 'ravanix' ); ?></label>
				<input type="text" id="rs-access-code-input" class="rs-access-code-input" dir="ltr" placeholder="<?php esc_attr_e( 'Access code', 'ravanix' ); ?>">
				<button type="button" class="rs-btn rs-btn-start rs-btn-start-with-code" <?php disabled( $requires_consent ); ?>><?php esc_html_e( 'Start Test', 'ravanix' ); ?></button>
				<p class="rs-access-code-error" style="display:none;color:#d9534f;"></p>
			</div>
		<?php else : ?>
			<button type="button" class="rs-btn rs-btn-start" <?php disabled( $requires_consent ); ?>><?php esc_html_e( 'Start Test', 'ravanix' ); ?></button>
		<?php endif; ?>
		<?php if ( $requires_consent ) : ?>
			<p class="rs-consent-required-note" style="display:none;color:#d9534f;"><?php esc_html_e( 'Please agree to the consent notice above before starting.', 'ravanix' ); ?></p>
		<?php endif; ?>
	</div>

	<form class="rs-test-form" id="rs-form-<?php echo intval( $test->id ); ?>" style="display:none;">

		<!-- Honeypot field: must always stay empty; only bots typically fill it in -->
		<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
			<label><?php esc_html_e( 'Please leave this field empty', 'ravanix' ); ?></label>
			<input type="text" name="ravanix_hp" tabindex="-1" autocomplete="off">
		</div>
		<input type="hidden" name="access_code" class="rs-hidden-access-code" value="">
		<?php if ( $requires_consent ) : ?>
			<input type="hidden" name="consent_agreed" class="rs-consent-agreed-field" value="0">
		<?php endif; ?>

		<?php if ( $is_paginated ) : ?>
			<div class="rs-progress-wrap">
				<div class="rs-progress-bar-bg"><div class="rs-progress-bar-fill" style="width:0%;"></div></div>
				<p class="rs-progress-text"></p>
			</div>
		<?php endif; ?>

		<div class="rs-page" data-page="0">
			<?php
			$participant_fields = Ravanix_Settings::get_field( 'participant_fields' );
			$field_types         = array(
				'full_name' => 'text',
				'education' => 'text',
				'mobile'    => 'tel',
				'email'     => 'email',
				'age'       => 'number',
			);
			$full_name_active = ! empty( $participant_fields['full_name']['enabled'] );
			?>
			<?php if ( ! is_user_logged_in() && ! $full_name_active ) : ?>
				<p class="rs-field">
					<label><?php esc_html_e( 'Your name (optional)', 'ravanix' ); ?></label>
					<input type="text" name="guest_name" placeholder="<?php esc_attr_e( 'e.g. Guest user', 'ravanix' ); ?>">
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $participant_fields ) ) : ?>
				<?php foreach ( $participant_fields as $fkey => $fconf ) : ?>
					<?php if ( empty( $fconf['enabled'] ) ) { continue; } ?>
					<div class="rs-field rs-participant-field" data-field-key="<?php echo esc_attr( $fkey ); ?>" data-required="<?php echo ! empty( $fconf['required'] ) ? '1' : '0'; ?>">
						<label>
							<?php echo esc_html( $fconf['label'] ); ?>
							<?php if ( ! empty( $fconf['required'] ) ) : ?><span class="rs-required-star">*</span><?php endif; ?>
						</label>
						<?php if ( 'gender' === $fkey ) : ?>
							<select name="participant[<?php echo esc_attr( $fkey ); ?>]">
								<option value=""><?php esc_html_e( '— Select —', 'ravanix' ); ?></option>
								<option value="male"><?php esc_html_e( 'Male', 'ravanix' ); ?></option>
								<option value="female"><?php esc_html_e( 'Female', 'ravanix' ); ?></option>
							</select>
						<?php else : ?>
							<input type="<?php echo esc_attr( $field_types[ $fkey ] ?? 'text' ); ?>" name="participant[<?php echo esc_attr( $fkey ); ?>]">
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

		<?php
		$current_page = 0;
		foreach ( $test->questions as $index => $q ) :
			$page_index = ( $per_page > 0 ) ? (int) floor( $index / $per_page ) : 0;
			if ( $page_index !== $current_page ) :
				?>
		</div>
		<div class="rs-page" data-page="<?php echo intval( $page_index ); ?>" style="display:none;">
				<?php
				$current_page = $page_index;
			endif;
			?>
			<div class="rs-question" data-question-id="<?php echo intval( $q->id ); ?>"
				<?php if ( ! empty( $q->branch_condition_question_id ) ) : ?>
					data-branch-question="<?php echo intval( $q->branch_condition_question_id ); ?>" data-branch-value="<?php echo esc_attr( $q->branch_condition_value ); ?>"
				<?php endif; ?>
				>
				<p class="rs-question-text"><span class="rs-q-num rs-q-num-live"><?php echo esc_html( $index + 1 ); ?>.</span> <?php echo esc_html( $q->question_text ); ?></p>

				<div class="rs-options">
					<?php if ( in_array( $q->question_type, array( 'likert5', 'likert7', 'binary' ), true ) ) : ?>
						<?php foreach ( $q->display_choices as $choice ) : ?>
							<label class="rs-option">
								<input type="radio" name="answers[<?php echo intval( $q->id ); ?>]" value="<?php echo esc_attr( $choice['value'] ); ?>">
								<span><?php echo esc_html( $choice['label'] ); ?></span>
							</label>
						<?php endforeach; ?>

					<?php elseif ( 'multiple' === $q->question_type && ! empty( $q->options ) ) : ?>
						<?php foreach ( $q->options as $opt ) : ?>
							<label class="rs-option">
								<input type="radio" name="answers[<?php echo intval( $q->id ); ?>]" value="<?php echo esc_attr( $opt->option_value ); ?>">
								<span><?php echo esc_html( $opt->option_text ); ?></span>
							</label>
						<?php endforeach; ?>

					<?php elseif ( 'forced_choice' === $q->question_type && ! empty( $q->options ) ) : ?>
						<?php
						// The submitted value here is the option's own ID, not its numeric value;
						// since in "multiple-choice with a separate dimension per option" questions
						// all options usually share the same value (e.g. all worth 1 point), only
						// the option ID can identify exactly which option (and hence which
						// dimension) was selected.
						?>
						<?php foreach ( $q->options as $opt ) : ?>
							<label class="rs-option">
								<input type="radio" name="answers[<?php echo intval( $q->id ); ?>]" value="<?php echo intval( $opt->id ); ?>">
								<span><?php echo esc_html( $opt->option_text ); ?></span>
							</label>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
		</div>

		<p class="rs-form-error" style="display:none;"></p>

		<?php if ( $enable_save_resume ) : ?>
			<p class="rs-save-progress-row">
				<button type="button" class="rs-btn rs-btn-secondary rs-btn-save-progress"><?php esc_html_e( 'Save my progress', 'ravanix' ); ?></button>
				<span class="rs-save-progress-status" aria-live="polite"></span>
			</p>
		<?php endif; ?>

		<p class="rs-nav-row">
			<button type="button" class="rs-btn rs-btn-secondary rs-btn-prev" style="display:none;"><?php esc_html_e( 'Previous page', 'ravanix' ); ?></button>
			<?php if ( $is_paginated ) : ?>
				<button type="button" class="rs-btn rs-btn-next"><?php esc_html_e( 'Next page', 'ravanix' ); ?></button>
			<?php endif; ?>
			<button type="submit" class="rs-btn rs-btn-submit" style="<?php echo $is_paginated ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Submit answers and view my profile', 'ravanix' ); ?></button>
		</p>
	</form>

	<div class="rs-test-result" style="display:none;"></div>
</div>
