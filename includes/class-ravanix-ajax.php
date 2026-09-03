<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Every action in this file calls check_ajax_referer('ravanix_frontend_nonce', ...)
// directly at the top of its own method (not via a shared helper), verifying
// the request's nonce; static analysis tools can't always trace that a nonce
// check happened a few lines earlier in the same function, hence the disables
// below. submit_test() is intentionally public (registered for both
// wp_ajax_ and wp_ajax_nopriv_, so guests can take a test), so it has no
// current_user_can() check by design; save_draft()/delete_draft() restrict
// themselves to logged-in users by registering only a wp_ajax_ (no _nopriv_)
// action -- WordPress itself rejects a logged-out request before the
// callback runs -- plus a defensive is_user_logged_in() check inside each.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.Security.NonceVerification.Missing
//
// The $wpdb->delete()/->replace() calls in this file (Save & Resume drafts)
// target Ravanix_DB::drafts(), a fixed, predefined table name, never user
// input, through WordPress's own sanctioned write-method APIs (not a raw
// ->query() call). The "direct query"/"no caching" warnings are unavoidable
// for this plugin's custom tables, since WordPress provides no ready-made API
// for tables other than its own core tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

class Ravanix_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_ravanix_submit_test', array( $this, 'submit_test' ) );
		add_action( 'wp_ajax_nopriv_ravanix_submit_test', array( $this, 'submit_test' ) );

		// Server-side Save & Resume is only for logged-in users (a guest has no
		// stable identity across devices/browsers for a server-side draft to be
		// looked up by later); guests keep using the existing browser-local
		// (localStorage) autosave only, which needs no server round-trip at all.
		add_action( 'wp_ajax_ravanix_save_draft', array( $this, 'save_draft' ) );
		add_action( 'wp_ajax_ravanix_delete_draft', array( $this, 'delete_draft' ) );
	}

	public function submit_test() {
		check_ajax_referer( 'ravanix_frontend_nonce', 'nonce' );

		$test_id = isset( $_POST['test_id'] ) ? intval( $_POST['test_id'] ) : 0;
		$raw_answers = isset( $_POST['answers'] ) ? (array) wp_unslash( $_POST['answers'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each answer value is floatval()'d individually below before use.

		if ( ! $test_id || empty( $raw_answers ) ) {
			wp_send_json_error( array( 'message' => __( 'Answers are incomplete.', 'ravanix' ) ) );
		}

		$test = Ravanix_DB::get_full_test( $test_id );
		if ( ! $test || 'published' !== $test->status ) {
			wp_send_json_error( array( 'message' => __( 'Test not found.', 'ravanix' ) ) );
		}

		if ( $test->require_login && ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to submit answers.', 'ravanix' ) ) );
		}

		$user_id     = get_current_user_id();
		$guest_token = $user_id ? '' : Ravanix_Access::get_or_set_guest_token();
		$ip          = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		// Rate-limit submissions (anti-spam/bot)
		if ( ! Ravanix_Access::check_rate_limit( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'You\'ve made too many requests; please wait a bit and try again.', 'ravanix' ) ) );
		}

		// Check the honeypot and minimum completion time (anti-spam/bot)
		$honeypot   = sanitize_text_field( wp_unslash( $_POST['ravanix_hp'] ?? '' ) );
		$elapsed_ms = isset( $_POST['elapsed_ms'] ) ? intval( $_POST['elapsed_ms'] ) : null;
		if ( ! Ravanix_Access::check_honeypot_and_timing( $honeypot, $elapsed_ms ) ) {
			// A generic message the bot can't use to detect the check
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'ravanix' ) ) );
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

		// Informed consent: enforced server-side using the exact same
		// mode-resolution rules the frontend template used to decide whether to
		// show the consent block at all (Ravanix_Settings::get_effective_consent_text()),
		// so a request sent directly to this endpoint (bypassing the displayed
		// page/its client-side checkbox) is still held to the same requirement.
		$consent_text     = Ravanix_Settings::get_effective_consent_text( $test );
		$requires_consent = '' !== trim( wp_strip_all_tags( $consent_text ) );
		$consent_agreed   = isset( $_POST['consent_agreed'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['consent_agreed'] ) );
		if ( $requires_consent && ! $consent_agreed ) {
			wp_send_json_error( array( 'message' => __( 'Please agree to the consent notice before submitting.', 'ravanix' ) ) );
		}

		// Validation: every question that is actually active for this submission
		// must be answered. A question is active unless it has a "show only if"
		// (skip logic) condition that the submitted answers do not satisfy -- in
		// which case it is expected to have no answer at all, and any stray value
		// submitted for it anyway (e.g. a tampered request bypassing the
		// client-side hide/show behavior) is deliberately ignored below rather
		// than scored, since the branch condition says this question was not
		// meant to be presented for this particular set of answers.
		$answers = array();
		foreach ( $test->questions as $q ) {
			if ( ! empty( $q->branch_condition_question_id ) ) {
				$dep_value = $raw_answers[ $q->branch_condition_question_id ] ?? null;
				$is_active = ( null !== $dep_value && '' !== $dep_value && (string) $dep_value === (string) $q->branch_condition_value );
				if ( ! $is_active ) {
					continue;
				}
			}
			if ( ! isset( $raw_answers[ $q->id ] ) || '' === $raw_answers[ $q->id ] ) {
				wp_send_json_error( array( 'message' => __( 'Please answer all the questions.', 'ravanix' ) ) );
			}
			if ( ! self::is_answer_value_valid( $q, $raw_answers[ $q->id ] ) ) {
				// A tampered request submitted a value this specific question
				// never actually offered (e.g. "999" for a 5-point Likert item).
				// Rejecting outright, rather than merely sanitizing/clamping it,
				// matters here beyond the usual security reasons: an
				// out-of-range value would directly corrupt this dimension's
				// raw score and percentage, which is a correctness problem for
				// a psychological assessment tool, not just a security one.
				wp_send_json_error( array( 'message' => __( 'One of the submitted answers is not valid for its question.', 'ravanix' ) ) );
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
						'message' => sprintf( __( 'Please fill in the required field "%s".', 'ravanix' ), $conf['label'] ),
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
					'value' => ( 'gender' === $key ) ? ( 'male' === $value ? __( 'Male', 'ravanix' ) : __( 'Female', 'ravanix' ) ) : $value,
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

		$result_id = Ravanix_Scoring::save_result( $test_id, $user_id, $guest_name, $answers, $scores, $participant_meta, array(), $guest_token, $consent_agreed );

		// A completed submission has no more use for a saved-progress draft.
		if ( $user_id ) {
			global $wpdb;
			$wpdb->delete( Ravanix_DB::drafts(), array( 'test_id' => $test_id, 'user_id' => $user_id ) );
		}

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

	/**
	 * Whether $value is actually one of the values this specific question can
	 * legitimately produce -- i.e. rejects a tampered request that submits an
	 * answer its own question never offered (e.g. "999" for a 5-point Likert
	 * item, or an option ID that isn't one of this question's own options).
	 *
	 * String comparison throughout: $value always arrives from $_POST as a
	 * string, and this avoids "1" vs 1.0 float-vs-string comparison surprises.
	 */
	private static function is_answer_value_valid( $q, $value ) {
		$value = (string) $value;
		switch ( $q->question_type ) {
			case 'likert5':
			case 'likert7':
			case 'binary':
				return array_key_exists( $value, Ravanix_Shortcodes::get_fixed_choices( $q->question_type ) );

			case 'multiple':
				// Submitted value is the option's own option_value (see the
				// frontend template's rendering of this question type).
				foreach ( $q->options as $opt ) {
					if ( $value === (string) $opt->option_value ) {
						return true;
					}
				}
				return false;

			case 'forced_choice':
				// Submitted value is the option's row ID, not its option_value
				// (multiple forced-choice options often share the same
				// option_value -- see the frontend template's own comment on
				// this exact question type for why).
				foreach ( $q->options as $opt ) {
					if ( $value === (string) $opt->id ) {
						return true;
					}
				}
				return false;

			default:
				// An unrecognized question type (e.g. from a future extension)
				// fails closed by default -- rejecting an unvalidatable answer
				// is safer than silently accepting it unchecked -- but an
				// extension can explicitly vouch for its own question type here.
				return (bool) apply_filters( 'ravanix_validate_answer_value', false, $q, $value );
		}
	}

	/**
	 * Upserts this logged-in user's in-progress answers for one test, so they
	 * can resume on a different device/browser later. Guests never reach this
	 * (see the constructor: no wp_ajax_nopriv_ counterpart is registered for
	 * this action, so WordPress itself rejects a logged-out request before our
	 * callback ever runs).
	 */
	public function save_draft() {
		check_ajax_referer( 'ravanix_frontend_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to save your progress.', 'ravanix' ) ) );
		}

		$test_id = isset( $_POST['test_id'] ) ? intval( $_POST['test_id'] ) : 0;
		$test    = $test_id ? Ravanix_DB::get_full_test( $test_id ) : null;
		if ( ! $test || 'published' !== $test->status ) {
			wp_send_json_error( array( 'message' => __( 'Test not found.', 'ravanix' ) ) );
		}

		// Stored as-is (not individually sanitized per field) because a draft is
		// never scored, never rendered as HTML anywhere, and is only ever read
		// back into the very same form fields it came from (see the frontend
		// JS's restoreDraft()); it is treated the same way as the existing
		// browser-local (localStorage) draft it mirrors -- opaque JSON, not markup.
		$raw_answers     = isset( $_POST['answers'] ) ? (array) wp_unslash( $_POST['answers'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see comment above; not rendered as HTML anywhere.
		$raw_participant = isset( $_POST['participant'] ) ? (array) wp_unslash( $_POST['participant'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see comment above; not rendered as HTML anywhere.
		$page            = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 0;

		// Only keep answers for question IDs that actually belong to this test,
		// so a tampered request can't use this endpoint to stash arbitrary data.
		$valid_question_ids = wp_list_pluck( $test->questions, 'id' );
		$clean_answers      = array();
		foreach ( $raw_answers as $q_id => $value ) {
			if ( in_array( intval( $q_id ), $valid_question_ids, true ) ) {
				$clean_answers[ intval( $q_id ) ] = sanitize_text_field( (string) $value );
			}
		}
		$clean_participant = array();
		foreach ( $raw_participant as $key => $value ) {
			$clean_participant[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
		}

		global $wpdb;
		$wpdb->replace(
			Ravanix_DB::drafts(),
			array(
				'test_id'          => $test_id,
				'user_id'          => get_current_user_id(),
				'answers_json'     => wp_json_encode( $clean_answers ),
				'participant_json' => wp_json_encode( $clean_participant ),
				'page'             => $page,
				'updated_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		wp_send_json_success( array( 'message' => __( 'Your progress has been saved.', 'ravanix' ) ) );
	}

	/**
	 * Deletes this logged-in user's saved draft for one test (used by the
	 * frontend's "Start over" action, so an explicitly-discarded draft does not
	 * linger and reappear as a resume offer later).
	 */
	public function delete_draft() {
		check_ajax_referer( 'ravanix_frontend_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_success(); // Nothing to delete for a guest; not an error.
		}

		$test_id = isset( $_POST['test_id'] ) ? intval( $_POST['test_id'] ) : 0;
		global $wpdb;
		$wpdb->delete( Ravanix_DB::drafts(), array( 'test_id' => $test_id, 'user_id' => get_current_user_id() ) );

		wp_send_json_success();
	}
}
