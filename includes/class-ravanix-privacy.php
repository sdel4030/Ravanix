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
 * Integrates with WordPress's core Personal Data Eraser tool
 * (Tools -> Erase Personal Data), so a site admin can act on a "right to be
 * forgotten" request for one specific, identified (logged-in) user without
 * uninstalling the plugin or affecting any other participant's data.
 *
 * Deliberately anonymization, not row deletion, for completed results:
 * identifying fields (user_id, guest_name, guest_ip, guest_token,
 * participant_meta, and the raw per-item answers_json) are cleared, but the
 * dimension score rows in ravanix_result_scores (raw_score, percentage,
 * T/Z-score, percentile, level, interpretation) are kept, now as anonymous
 * data points, so aggregate/statistical reporting for the test as a whole is
 * not silently degraded by a privacy request.
 *
 * Saved-progress drafts (ravanix_drafts, from the Save & Resume feature) are
 * simply deleted outright rather than anonymized: an in-progress, unfinished
 * submission has no aggregate/statistical value the way a completed result's
 * scores do, so there is nothing worth keeping.
 *
 * No data exporter is registered on purpose: this plugin only implements the
 * erasure side of the Privacy Tools, not the export side.
 *
 * A structural limitation of WordPress's Privacy Tools themselves (not
 * specific to this plugin): they can only act on a request tied to a
 * registered user's email address. A guest submission (no WordPress account)
 * has no such identity to look up, so it is not reachable through this
 * mechanism.
 */
class Ravanix_Privacy {

	const ERASER_ID = 'ravanix-results';

	public function __construct() {
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
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
