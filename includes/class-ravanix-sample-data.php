<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

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
// This file's local variables and its install() method are never called
// directly by WordPress core or another plugin/theme — only by this plugin's
// own activator — so there is no realistic risk of a naming collision;
// forcing a "ravanix_" prefix on every local variable here would only reduce readability.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/**
 * Installs a short, entirely fictional sample questionnaire (simulated, not
 * copyrighted) to help the site admin get familiar with the plugin's features
 * right at activation time — a simple mood-screening scale with two subscales.
 * No real items or data from any licensed instrument are used (this note is
 * also included in the questionnaire's own description).
 *
 * This sample questionnaire deliberately uses only Lite-tier features (no
 * norms, composite factors, or forced-choice questions) and is built directly
 * via $wpdb, without depending on the import engine (which lives in Ravanix Pro).
 *
 * The sample content is always in English; only its text direction follows
 * the site's admin language direction (is_rtl()), so the demo still displays
 * sensibly on right-to-left sites.
 */
class Ravanix_Sample_Data {

	public static function install() {
		global $wpdb;

		$rtl = is_rtl();

		$test_data = array(
			'title'          => 'Sample: Mood Screening Scale',
			'slug'           => 'ravanix-sample-mood-scale',
			'description'    => '<p>This is a <strong>completely fictional, demo-only</strong> questionnaire, created automatically on plugin activation to showcase Ravanix\'s basic features. No real items or data from any validated instrument are used, and it is not suitable for clinical or research use.</p>',
			'instructions'   => 'Please answer each item based on how you have been feeling lately.',
			'status'         => 'published',
			'require_login'  => 0,
			'text_direction' => $rtl ? 'rtl' : 'ltr',
			'tags'           => 'sample, demo',
			'created_by'     => get_current_user_id(),
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		);

		// If already installed (e.g. after a deactivate/reactivate cycle), don't recreate it
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM ' . Ravanix_DB::tests() . ' WHERE slug = %s LIMIT 1', $test_data['slug'] )
		);
		if ( $exists ) {
			return;
		}

		$wpdb->insert( Ravanix_DB::tests(), $test_data );
		$test_id = $wpdb->insert_id;
		if ( ! $test_id ) {
			return;
		}

		$dimensions = array(
			array( 'name' => 'Depressed Mood', 'code' => 'mood', 'order' => 0 ),
			array( 'name' => 'Anxiety', 'code' => 'anxiety', 'order' => 1 ),
		);

		$questions_by_dim = array(
			'mood'    => array(
				'I feel down most of the time these days.',
				'I enjoy things less than I used to.',
				"I don't have enough energy for daily tasks.",
				'My sleep has been disrupted.',
				'I feel hopeful about the future.',
			),
			'anxiety' => array(
				'I feel worried without a clear reason.',
				'I feel tense in everyday situations.',
				'It has become hard to concentrate.',
				'My heart races without physical exertion.',
				'I generally feel calm.',
			),
		);

		$dim_ids = array();
		foreach ( $dimensions as $dim ) {
			$wpdb->insert(
				Ravanix_DB::dimensions(),
				array(
					'test_id'    => $test_id,
					'name'       => $dim['name'],
					'code'       => $dim['code'],
					'sort_order' => $dim['order'],
				)
			);
			$dim_ids[ $dim['code'] ] = $wpdb->insert_id;

			// A simple three-level interpretation range based on the raw score
			// (5 5-point-Likert questions; raw score range 5 to 25)
			$ranges = array(
				array( 5, 11, 'Low', '#3fae54', 'This dimension score is in the low range.' ),
				array( 12, 18, 'Moderate', '#e0a72e', 'This dimension score is in the moderate range.' ),
				array( 19, 25, 'High', '#d9534f', 'This dimension score is in the high range; consulting a professional is suggested.' ),
			);
			foreach ( $ranges as $r ) {
				$wpdb->insert(
					Ravanix_DB::interpretations(),
					array(
						'dimension_id' => $dim_ids[ $dim['code'] ],
						'range_min'    => $r[0],
						'range_max'    => $r[1],
						'level_label'  => $r[2],
						'level_color'  => $r[3],
						'description'  => $r[4],
					)
				);
			}
		}

		foreach ( $questions_by_dim as $dim_code => $texts ) {
			foreach ( $texts as $i => $text ) {
				$wpdb->insert(
					Ravanix_DB::questions(),
					array(
						'test_id'       => $test_id,
						'dimension_id'  => $dim_ids[ $dim_code ],
						'question_text' => $text,
						'question_type' => 'likert5',
						'is_reverse'    => ( 4 === $i && 'mood' === $dim_code ) || ( 4 === $i && 'anxiety' === $dim_code ) ? 1 : 0,
						'weight'        => 1,
						'sort_order'    => $i,
					)
				);
			}
		}

		self::sync_to_cpt( $test_id );
	}

	/**
	 * Syncs the sample test with the custom post type (if enabled), exactly
	 * matching the behavior that happens when a test is saved normally from
	 * the admin panel; without this step, the sample test would be created in
	 * the ravanix_tests table but have no corresponding WordPress post, and
	 * would not show up in the questionnaire archive/permalink.
	 */
	private static function sync_to_cpt( $test_id ) {
		global $wpdb;
		try {
			$test_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Ravanix_DB::tests() . ' WHERE id = %d', $test_id ) );
			if ( $test_row && class_exists( 'Ravanix_CPT' ) ) {
				$cpt_post_id = Ravanix_CPT::sync_test_to_post( $test_row );
				if ( $cpt_post_id ) {
					$wpdb->update( Ravanix_DB::tests(), array( 'cpt_post_id' => $cpt_post_id ), array( 'id' => $test_id ) );
				}
			}
		} catch ( \Throwable $e ) {
			// Ignored; the sample test itself has already been saved to the database successfully
		}
	}
}
