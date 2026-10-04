<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Every query in this file gets its table name from a Ravanix_DB::...() method,
// which always returns a fixed, predefined string ($wpdb->prefix + a constant
// table name), never user input; so SQL injection is not possible through this
// path. The "direct query" and "no caching" warnings are also unavoidable for
// this plugin's custom tables, since WordPress provides no ready-made API for
// tables other than its own core tables.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Integrates with WordPress's core Privacy Tools (Tools -> Export/Erase
 * Personal Data): a site admin can act on a "give me my data" or "right to
 * be forgotten" request for one specific, identified (logged-in) user
 * without uninstalling the plugin or affecting any other participant's data.
 *
 * Export (export_results()): everything a completed result stores about this
 * person -- which test, when, their raw per-item answers (with the question
 * wording they were actually shown, from that result's Test Versioning
 * snapshot when it has one), the resulting dimension scores, consent
 * provenance, and completion time. Deliberately more complete than the
 * project's email-notification policy (which minimizes item-level data in
 * outbound emails to reduce third-party disclosure risk): an export request
 * is the person receiving their *own* data back, not a disclosure to someone
 * else, so the usual minimization reasoning doesn't apply the same way here
 * -- withholding their own answers from their own data export would defeat
 * the point of the request. Ravanix Pro hooks
 * 'ravanix_privacy_export_result_groups' to append composite-factor scores
 * (computed live, like everywhere else composite scores are shown) --
 * nothing in this file requires Pro to be active.
 *
 * Erasure (erase_results()): deliberately anonymization, not row deletion,
 * for completed results: identifying fields (user_id, guest_name, guest_ip,
 * guest_token, participant_meta, and the raw per-item answers_json) are
 * cleared, but the dimension score rows in ravanix_result_scores (raw_score,
 * percentage, T/Z-score, percentile, level, interpretation) are kept, now as
 * anonymous data points, so aggregate/statistical reporting for the test as
 * a whole is not silently degraded by a privacy request.
 *
 * Saved-progress drafts (ravanix_drafts, from the Save & Resume feature) are
 * simply deleted outright rather than anonymized: an in-progress, unfinished
 * submission has no aggregate/statistical value the way a completed result's
 * scores do, so there is nothing worth keeping.
 *
 * A structural limitation of WordPress's Privacy Tools themselves (not
 * specific to this plugin, and identical for both export and erasure): they
 * can only act on a request tied to a registered user's email address. A
 * guest submission (no WordPress account) has no such identity to look up,
 * so it is not reachable through this mechanism.
 */
class Ravanix_Privacy {

	const ERASER_ID   = 'ravanix-results';
	const EXPORTER_ID = 'ravanix-results';

	public function __construct() {
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
	}

	public function register_exporter( $exporters ) {
		$exporters[ self::EXPORTER_ID ] = array(
			'exporter_friendly_name' => __( 'Ravanix questionnaire results', 'ravanix' ),
			'callback'               => array( $this, 'export_results' ),
		);
		return $exporters;
	}

	/**
	 * @param string $email_address
	 * @param int    $page
	 * @return array {
	 *     @type array $data One export "group" per result (see build_result_group()).
	 *     @type bool  $done Whether this exporter has finished (no more pages).
	 * }
	 */
	public function export_results( $email_address, $page = 1 ) {
		$page = max( 1, intval( $page ) );

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			// No matching registered user -- see the class docblock on why a
			// guest submission can't be reached through this mechanism.
			return array( 'data' => array(), 'done' => true );
		}

		global $wpdb;
		$per_page = 10;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.*, t.title AS test_title
				 FROM ' . Ravanix_DB::results() . ' r
				 LEFT JOIN ' . Ravanix_DB::tests() . ' t ON t.id = r.test_id
				 WHERE r.user_id = %d
				 ORDER BY r.id ASC
				 LIMIT %d OFFSET %d',
				$user->ID,
				$per_page,
				( $page - 1 ) * $per_page
			)
		);

		$export_items = array();
		foreach ( $results as $result ) {
			$export_items[] = $this->build_result_group( $result );
		}

		return array(
			'data' => $export_items,
			'done' => count( $results ) < $per_page,
		);
	}

	/**
	 * One export "group" (WordPress's Privacy Tools term for one exportable
	 * record) for a single result row: everything section 43's "provenance"
	 * principle says a high-stakes stored score should be traceable to --
	 * here read back out for the person it belongs to, in plain language
	 * rather than raw column names.
	 *
	 * @param object $result A row from ravanix_results, with test_title joined in.
	 * @return array
	 */
	private function build_result_group( $result ) {
		global $wpdb;

		$data = array(
			array( 'name' => __( 'Test', 'ravanix' ), 'value' => $result->test_title ? $result->test_title : $result->test_id ),
			array( 'name' => __( 'Submitted', 'ravanix' ), 'value' => $result->submitted_at ),
		);

		if ( $result->elapsed_ms ) {
			/* translators: %s: number of seconds the attempt took to complete */
			$data[] = array( 'name' => __( 'Completion time', 'ravanix' ), 'value' => sprintf( __( '%s seconds', 'ravanix' ), round( $result->elapsed_ms / 1000 ) ) );
		}

		$data[] = array(
			'name'  => __( 'Consent', 'ravanix' ),
			'value' => $result->consent_agreed
				? ( $result->consented_at ? sprintf(
					/* translators: 1: date/time consent was given, 2: a short hash identifying the exact consent wording shown */
					__( 'Agreed on %1$s (wording: %2$s)', 'ravanix' ),
					$result->consented_at,
					$result->consent_version ? $result->consent_version : '—'
				) : __( 'Agreed', 'ravanix' ) )
				: __( 'Not required for this test', 'ravanix' ),
		);

		if ( $result->is_validity_flagged ) {
			$data[] = array( 'name' => __( 'Validity flag', 'ravanix' ), 'value' => $result->validity_notes ? $result->validity_notes : __( 'Flagged', 'ravanix' ) );
		}
		if ( $result->is_quality_flagged ) {
			$data[] = array( 'name' => __( 'Response-quality flag', 'ravanix' ), 'value' => $result->quality_notes ? $result->quality_notes : __( 'Flagged', 'ravanix' ) );
		}

		// Raw per-item answers, with the question wording actually shown for
		// this attempt: from this result's own Test Versioning snapshot when
		// it has one (the question may since have been reworded or deleted),
		// falling back to the test's current question text for an older
		// result that predates version tracking.
		$question_labels = $this->get_question_labels_for_result( $result );
		$answers          = $result->answers_json ? json_decode( $result->answers_json, true ) : array();
		if ( is_array( $answers ) ) {
			foreach ( $answers as $question_id => $value ) {
				$label = isset( $question_labels[ $question_id ] ) ? $question_labels[ $question_id ] : sprintf(
					/* translators: %d: a question's internal ID, shown only when its wording could not be recovered */
					__( 'Question #%d', 'ravanix' ),
					$question_id
				);
				$data[] = array( 'name' => $label, 'value' => $value );
			}
		}

		// Dimension-level scores.
		$scores = $wpdb->get_results( $wpdb->prepare( 'SELECT rs.*, d.name AS dimension_name FROM ' . Ravanix_DB::result_scores() . ' rs LEFT JOIN ' . Ravanix_DB::dimensions() . ' d ON d.id = rs.dimension_id WHERE rs.result_id = %d', $result->id ) );
		foreach ( $scores as $s ) {
			$parts = array(
				sprintf(
					/* translators: 1: raw score, 2: maximum possible raw score, 3: percentage */
					__( 'raw %1$s/%2$s (%3$s%%)', 'ravanix' ),
					$s->raw_score,
					$s->max_score,
					$s->percentage
				),
			);
			if ( null !== $s->t_score ) {
				/* translators: %s: a T-score value */
				$parts[] = sprintf( __( 'T-score %s', 'ravanix' ), $s->t_score );
			}
			if ( null !== $s->percentile ) {
				/* translators: %s: a percentile rank */
				$parts[] = sprintf( __( 'percentile %s', 'ravanix' ), $s->percentile );
			}
			if ( $s->level_label ) {
				$parts[] = $s->level_label;
			}
			$data[] = array(
				/* translators: %s: a dimension/subscale name */
				'name'  => sprintf( __( 'Score: %s', 'ravanix' ), $s->dimension_name ? $s->dimension_name : $s->dimension_id ),
				'value' => implode( ', ', $parts ),
			);
		}

		/**
		 * Lets Ravanix Pro append composite-factor scores (computed live from
		 * the same data ravanix_result_scores holds, exactly like every other
		 * composite-score display in the plugin -- nothing is stored per-result
		 * for composites, see the note on that in Ravanix_Pro_Scoring), and
		 * gives any other extension a place to add its own exportable fields
		 * for this same result without needing its own separate exporter
		 * registration.
		 *
		 * @param array  $data   The group's data rows so far.
		 * @param object $result The full result row.
		 */
		$data = apply_filters( 'ravanix_privacy_export_result_data', $data, $result );

		return array(
			'group_id'    => 'ravanix-results',
			'group_label' => __( 'Ravanix questionnaire results', 'ravanix' ),
			'item_id'     => 'ravanix-result-' . intval( $result->id ),
			'data'        => $data,
		);
	}

	/**
	 * question_id => question_text for one result, preferring that result's
	 * own Test Versioning snapshot (the exact wording the participant was
	 * actually shown) over the test's current questions -- see the docblock
	 * at this method's one call site.
	 *
	 * @param object $result
	 * @return array<int,string>
	 */
	private function get_question_labels_for_result( $result ) {
		global $wpdb;
		$labels = array();

		if ( ! empty( $result->test_version_id ) ) {
			$snapshot_json = $wpdb->get_var( $wpdb->prepare( 'SELECT snapshot_json FROM ' . Ravanix_DB::test_versions() . ' WHERE id = %d', intval( $result->test_version_id ) ) );
			$snapshot      = $snapshot_json ? json_decode( $snapshot_json, true ) : null;
			if ( ! empty( $snapshot['questions'] ) ) {
				foreach ( $snapshot['questions'] as $q ) {
					if ( isset( $q['id'], $q['question_text'] ) ) {
						$labels[ intval( $q['id'] ) ] = $q['question_text'];
					}
				}
				return $labels;
			}
		}

		// No snapshot available (a result that predates Test Versioning, or a
		// snapshot lookup that came back empty): fall back to the test's
		// current question wording, on a best-effort basis.
		$full_test = Ravanix_DB::get_full_test( $result->test_id );
		if ( $full_test ) {
			foreach ( $full_test->questions as $q ) {
				$labels[ intval( $q->id ) ] = $q->question_text;
			}
		}

		return $labels;
	}

	public function register_eraser( $erasers ) {
		$erasers[ self::ERASER_ID ] = array(
			'eraser_friendly_name' => __( 'Ravanix questionnaire results', 'ravanix' ),
			'callback'             => array( $this, 'erase_results' ),
		);
		return $erasers;
	}

	/**
	 * @param string $email_address
	 * @param int    $page
	 * @return array {
	 *     @type bool     $items_removed  Whether any row was modified on this page.
	 *     @type bool     $items_retained Whether an anonymized data point was
	 *                                    kept (always true when a row was
	 *                                    found), so WordPress accurately tells
	 *                                    the admin that some (now anonymous)
	 *                                    data remains, rather than falsely
	 *                                    reporting complete removal.
	 *     @type string[] $messages       Notes shown to the admin on the request's screen.
	 *     @type bool     $done           Whether this eraser has finished (no more pages).
	 */
	public function erase_results( $email_address, $page = 1 ) {
		$response = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			// No matching registered user: nothing this eraser can act on
			// (guest submissions have no account/email to look this request up by).
			return $response;
		}

		global $wpdb;

		// Drafts are deleted outright, independent of the completed-results
		// pagination below (there is at most one draft row per test for this
		// user, so no paging is needed here).
		$deleted_drafts = $wpdb->delete( Ravanix_DB::drafts(), array( 'user_id' => $user->ID ) );
		if ( $deleted_drafts ) {
			$response['items_removed'] = true;
			$response['messages'][]    = __( 'Ravanix: this user\'s saved (but not yet submitted) test progress was deleted.', 'ravanix' );
		}

		$per_page = 50;

		// Deliberately no OFFSET: this eraser clears user_id itself as part of
		// processing each batch (see the update below), so already-anonymized
		// rows automatically stop matching "user_id = %d" and drop out of
		// future queries on their own. Re-querying "the first $per_page
		// still-matching rows" on every call (rather than paging by a fixed
		// OFFSET into a set that keeps shrinking mid-request) is what keeps
		// this correct across multiple calls -- an OFFSET here would skip rows
		// that a prior page already caused to fall out of the WHERE clause.
		//
		// Filtering only by user_id (not by whether guest_name/participant_meta/
		// etc. happen to be non-null) matters: user_id alone identifies this
		// person regardless of which other fields were ever populated for a
		// given submission (e.g. a logged-in user's row with no participant_meta
		// collected is still identifying through user_id and must be included).
		$result_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . Ravanix_DB::results() . ' WHERE user_id = %d ORDER BY id ASC LIMIT %d',
				$user->ID,
				$per_page
			)
		);

		if ( empty( $result_ids ) ) {
			$response['done'] = true;
			return $response;
		}

		foreach ( $result_ids as $result_id ) {
			$wpdb->update(
				Ravanix_DB::results(),
				array(
					'user_id'          => 0,
					'guest_name'       => null,
					'guest_ip'         => null,
					'guest_token'      => null,
					'participant_meta' => null,
					'answers_json'     => null,
				),
				array( 'id' => intval( $result_id ) ),
				array( '%d', '%s', '%s', '%s', '%s', '%s' ), // Column formats; WordPress writes an actual SQL NULL for null values here (supported since WP 3.9), not the string "".
				array( '%d' )
			);
		}

		$response['items_removed']  = true;
		$response['items_retained'] = true;
		$response['messages'][]     = __( 'Ravanix: this user\'s identifying information (name, contact details, and individual question answers) was removed from their questionnaire results. The resulting anonymous scores (used for the site\'s aggregate statistics) were kept and can no longer be traced back to this user.', 'ravanix' );

		// Report whether another page of matching rows likely remains, so
		// WordPress calls this eraser again if needed.
		$response['done'] = count( $result_ids ) < $per_page;

		return $response;
	}
}
