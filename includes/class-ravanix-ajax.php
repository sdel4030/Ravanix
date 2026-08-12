<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

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

class Ravanix_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_ravanix_submit_test', array( $this, 'submit_test' ) );
		add_action( 'wp_ajax_nopriv_ravanix_submit_test', array( $this, 'submit_test' ) );
	}

	public function submit_test() {
		check_ajax_referer( 'ravanix_frontend_nonce', 'nonce' );

		$test_id = isset( $_POST['test_id'] ) ? intval( $_POST['test_id'] ) : 0;
		$raw_answers = isset( $_POST['answers'] ) ? (array) wp_unslash( $_POST['answers'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each answer value is floatval()'d individually below before use.

		if ( ! $test_id || empty( $raw_answers ) ) {
			wp_send_json_error( array( 'message' => __( 'Answers are incomplete.', 'ravanix-lite' ) ) );
		}

		$test = Ravanix_DB::get_full_test( $test_id );
		if ( ! $test || 'published' !== $test->status ) {
			wp_send_json_error( array( 'message' => __( 'Test not found.', 'ravanix-lite' ) ) );
		}

		if ( $test->require_login && ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to submit answers.', 'ravanix-lite' ) ) );
		}

		$user_id     = get_current_user_id();
		$guest_token = $user_id ? '' : Ravanix_Access::get_or_set_guest_token();
		$ip          = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		// Rate-limit submissions (anti-spam/bot)
		if ( ! Ravanix_Access::check_rate_limit( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'You\'ve made too many requests; please wait a bit and try again.', 'ravanix-lite' ) ) );
		}

		// Check the honeypot and minimum completion time (anti-spam/bot)
		$honeypot   = sanitize_text_field( wp_unslash( $_POST['ravanix_hp'] ?? '' ) );
		$elapsed_ms = isset( $_POST['elapsed_ms'] ) ? intval( $_POST['elapsed_ms'] ) : null;
		if ( ! Ravanix_Access::check_honeypot_and_timing( $honeypot, $elapsed_ms ) ) {
			// A generic message the bot can't use to detect the check
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'ravanix-lite' ) ) );
		}

		/**
		 * Extra access checks (access code, execution limit, WooCommerce purchase,
		 * etc.) at answer-submission time — the same filter used when the form is
		 * displayed (Ravanix_Shortcodes::render_test), so a user who bypasses the
		 * displayed page and sends the AJAX request directly is still subject to the same restrictions.
		 *
		 * @param array  $result      array( 'allowed' => bool, 'message' => string ).
		 * @param object $test        The full test object.
		 * @param int    $user_id     The logged-in user's ID (0 for a guest).
		 * @param string $guest_token The guest token.
		 */
		$extra_check = apply_filters(
			'ravanix_extra_access_check',
			array( 'allowed' => true, 'message' => '' ),
			$test,
			$user_id,
			$guest_token
		);
		if ( ! $extra_check['allowed'] ) {
			wp_send_json_error( array( 'message' => $extra_check['message'] ) );
		}

		// Validation: all questions must be answered
		$answers = array();
		foreach ( $test->questions as $q ) {
			if ( ! isset( $raw_answers[ $q->id ] ) || '' === $raw_answers[ $q->id ] ) {
				wp_send_json_error( array( 'message' => __( 'Please answer all the questions.', 'ravanix-lite' ) ) );
			}
			$answers[ $q->id ] = floatval( $raw_answers[ $q->id ] );
		}

		// Process participant info fields per the settings enabled in the admin panel
		$participant_conf = Ravanix_Settings::get_field( 'participant_fields' );
		$raw_participant   = isset( $_POST['participant'] ) ? (array) wp_unslash( $_POST['participant'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized individually (sanitize_text_field/sanitize_email/intval, per the field's own type) further down before use.
		$participant_meta  = array();
		$participant_age   = null;
		$participant_gender = null;

		if ( ! empty( $participant_conf ) ) {
			foreach ( $participant_conf as $key => $conf ) {
				if ( empty( $conf['enabled'] ) ) {
					continue;
				}
				$value = isset( $raw_participant[ $key ] ) ? trim( (string) $raw_participant[ $key ] ) : '';

				if ( ! empty( $conf['required'] ) && '' === $value ) {
					wp_send_json_error( array(
						/* translators: %s: the label of the missing required field */
						'message' => sprintf( __( 'Please fill in the required field "%s".', 'ravanix-lite' ), $conf['label'] ),
					) );
				}

				if ( '' === $value ) {
					continue;
				}

				if ( 'email' === $key ) {
					$value = sanitize_email( $value );
				} elseif ( 'age' === $key ) {
					$value          = intval( $value );
					$participant_age = $value;
				} elseif ( 'gender' === $key ) {
					$value             = in_array( $value, array( 'male', 'female' ), true ) ? $value : '';
					$participant_gender = $value;
					if ( '' === $value ) {
						continue;
					}
				} else {
					$value = sanitize_text_field( $value );
				}

				$participant_meta[ $key ] = array(
					'label' => $conf['label'],
					'value' => ( 'gender' === $key ) ? ( 'male' === $value ? __( 'Male', 'ravanix-lite' ) : __( 'Female', 'ravanix-lite' ) ) : $value,
				);
				// For gender, also store a stable, non-translatable key ('male'/'female')
				// alongside the translated display label above. Only the 'value' label is
				// shown to the user; 'key' is what any later scoring/matching logic should
				// compare against, so a site-language change or a customized translation of
				// "Male"/"Female" can never silently break gender-based norm matching.
				if ( 'gender' === $key ) {
					$participant_meta[ $key ]['key'] = $value;
				}
			}
		}

		$scores = Ravanix_Scoring::calculate( $test, $answers, $participant_age, $participant_gender );

		$guest_name = ! $user_id ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ?? 'Guest' ) ) : '';

		$result_id = Ravanix_Scoring::save_result( $test_id, $user_id, $guest_name, $answers, $scores, $participant_meta, array(), $guest_token );

		/**
		 * Builds the final AJAX answer-submission response. Ravanix Pro uses this
		 * filter to add composite factor scores, validity checks, and the PDF
		 * download link (already computed/saved earlier by
		 * do_action( 'ravanix_after_save_result', ... ) in Ravanix_Scoring::save_result())
		 * to the response, without needing to edit this file.
		 *
		 * @param array  $response  The base response.
		 * @param int    $result_id The saved result's ID.
		 * @param object $test      The full test object.
		 * @param array  $scores    The raw scores computed for each dimension.
		 */
		$response = apply_filters(
			'ravanix_submit_response',
			array(
				'result_id'  => $result_id,
				'test_title' => $test->title,
				'scores'     => $scores,
			),
			$result_id,
			$test,
			$scores
		);

		// Some questionnaires (e.g. strengths, career interests, dominant
		// personality traits) are meant to be read as a ranking rather than
		// each dimension's absolute level; for those, the admin can enable
		// "Show results ranked from highest to lowest" in the test's General
		// Info tab, which reorders the score cards/chart here by percentage
		// instead of the admin-defined display order.
		if ( ! empty( $test->rank_results ) ) {
			if ( ! empty( $response['scores'] ) && is_array( $response['scores'] ) ) {
				usort(
					$response['scores'],
					function ( $a, $b ) {
						return $b['percentage'] <=> $a['percentage'];
					}
				);
			}
			if ( ! empty( $response['composites'] ) && is_array( $response['composites'] ) ) {
				usort(
					$response['composites'],
					function ( $a, $b ) {
						return $b['percentage'] <=> $a['percentage'];
					}
				);
			}
		}

		wp_send_json_success( $response );
	}
}
