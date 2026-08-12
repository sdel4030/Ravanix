<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// This file is a template/view loaded only via include() inside methods of the
// plugin's own classes, never standalone; so its local variables never actually
// enter the real global namespace, and there is no risk of collision with
// another plugin/theme. Forcing a prefix on the dozens of local variables in
// this file would only reduce readability.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/** @var object $test Output of Ravanix_DB::get_full_test() - available from the Ravanix_Shortcodes class */

$likert5_labels = array(
	'1' => __( 'Strongly disagree', 'ravanix-lite' ),
	'2' => __( 'Disagree', 'ravanix-lite' ),
	'3' => __( 'No opinion', 'ravanix-lite' ),
	'4' => __( 'Agree', 'ravanix-lite' ),
	'5' => __( 'Strongly agree', 'ravanix-lite' ),
);
$likert7_labels = array(
	'1' => __( 'Strongly disagree', 'ravanix-lite' ),
	'2' => __( 'Disagree', 'ravanix-lite' ),
	'3' => __( 'Slightly disagree', 'ravanix-lite' ),
	'4' => __( 'No opinion', 'ravanix-lite' ),
	'5' => __( 'Slightly agree', 'ravanix-lite' ),
	'6' => __( 'Agree', 'ravanix-lite' ),
	'7' => __( 'Strongly agree', 'ravanix-lite' ),
);

$per_page         = ! empty( $test->questions_per_page ) ? intval( $test->questions_per_page ) : 0; // 0 means no pagination
$total_questions  = count( $test->questions );
$total_pages      = $per_page > 0 ? max( 1, (int) ceil( $total_questions / $per_page ) ) : 1;
$is_paginated     = $total_pages > 1;
?>
<div class="rs-test-container" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>" data-test-id="<?php echo intval( $test->id ); ?>" data-total-pages="<?php echo intval( $total_pages ); ?>">

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
			printf( esc_html__( 'Number of questions: %d', 'ravanix-lite' ), absint( $total_questions ) );
		?></p>
		<div class="rs-resume-banner" style="display:none;">
			<p><?php esc_html_e( 'Your previous progress on this test has been saved.', 'ravanix-lite' ); ?></p>
			<button type="button" class="rs-btn rs-btn-resume"><?php esc_html_e( 'Resume where you left off', 'ravanix-lite' ); ?></button>
			<button type="button" class="rs-btn-link rs-btn-restart"><?php esc_html_e( 'Start over', 'ravanix-lite' ); ?></button>
		</div>
		<?php if ( ! empty( $test->access_code ) ) : ?>
			<div class="rs-access-code-gate">
				<label for="rs-access-code-input"><?php esc_html_e( 'To begin, enter the access code:', 'ravanix-lite' ); ?></label>
				<input type="text" id="rs-access-code-input" class="rs-access-code-input" dir="ltr" placeholder="<?php esc_attr_e( 'Access code', 'ravanix-lite' ); ?>">
				<button type="button" class="rs-btn rs-btn-start rs-btn-start-with-code"><?php esc_html_e( 'Start Test', 'ravanix-lite' ); ?></button>
				<p class="rs-access-code-error" style="display:none;color:#d9534f;"></p>
			</div>
		<?php else : ?>
			<button type="button" class="rs-btn rs-btn-start"><?php esc_html_e( 'Start Test', 'ravanix-lite' ); ?></button>
		<?php endif; ?>
	</div>

	<form class="rs-test-form" id="rs-form-<?php echo intval( $test->id ); ?>" style="display:none;">

		<!-- Honeypot field: must always stay empty; only bots typically fill it in -->
		<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
			<label><?php esc_html_e( 'Please leave this field empty', 'ravanix-lite' ); ?></label>
			<input type="text" name="ravanix_hp" tabindex="-1" autocomplete="off">
		</div>
		<input type="hidden" name="access_code" class="rs-hidden-access-code" value="">

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
					<label><?php esc_html_e( 'Your name (optional)', 'ravanix-lite' ); ?></label>
					<input type="text" name="guest_name" placeholder="<?php esc_attr_e( 'e.g. Guest user', 'ravanix-lite' ); ?>">
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
								<option value=""><?php esc_html_e( '— Select —', 'ravanix-lite' ); ?></option>
								<option value="male"><?php esc_html_e( 'Male', 'ravanix-lite' ); ?></option>
								<option value="female"><?php esc_html_e( 'Female', 'ravanix-lite' ); ?></option>
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
			<div class="rs-question" data-question-id="<?php echo intval( $q->id ); ?>">
				<p class="rs-question-text"><span class="rs-q-num"><?php echo esc_html( $index + 1 ); ?>.</span> <?php echo esc_html( $q->question_text ); ?></p>

				<div class="rs-options">
					<?php if ( 'likert5' === $q->question_type ) : ?>
						<?php foreach ( $likert5_labels as $val => $label ) : ?>
							<label class="rs-option">
								<input type="radio" name="answers[<?php echo intval( $q->id ); ?>]" value="<?php echo esc_attr( $val ); ?>">
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>

					<?php elseif ( 'likert7' === $q->question_type ) : ?>
						<?php foreach ( $likert7_labels as $val => $label ) : ?>
							<label class="rs-option">
								<input type="radio" name="answers[<?php echo intval( $q->id ); ?>]" value="<?php echo esc_attr( $val ); ?>">
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>

					<?php elseif ( 'binary' === $q->question_type ) : ?>
						<label class="rs-option">
							<input type="radio" name="answers[<?php echo intval( $q->id ); ?>]" value="1">
							<span><?php esc_html_e( 'Yes', 'ravanix-lite' ); ?></span>
						</label>
						<label class="rs-option">
							<input type="radio" name="answers[<?php echo intval( $q->id ); ?>]" value="0">
							<span><?php esc_html_e( 'No', 'ravanix-lite' ); ?></span>
						</label>

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

		<p class="rs-nav-row">
			<button type="button" class="rs-btn rs-btn-secondary rs-btn-prev" style="display:none;"><?php esc_html_e( 'Previous page', 'ravanix-lite' ); ?></button>
			<?php if ( $is_paginated ) : ?>
				<button type="button" class="rs-btn rs-btn-next"><?php esc_html_e( 'Next page', 'ravanix-lite' ); ?></button>
			<?php endif; ?>
			<button type="submit" class="rs-btn rs-btn-submit" style="<?php echo $is_paginated ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Submit answers and view my profile', 'ravanix-lite' ); ?></button>
		</p>
	</form>

	<div class="rs-test-result" style="display:none;"></div>
</div>
