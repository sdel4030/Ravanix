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
 * right at activation time — a simple mood-screening scale with two scored
 * subscales, plus a few standalone items that exist purely to demonstrate
 * features that don't affect scoring. No real items or data from any licensed
 * instrument are used (this note is also included in the questionnaire's own
 * description).
 *
 * Deliberately exercises every Lite-tier feature (see the inline comments
 * below for exactly which questions/settings demonstrate what), while using
 * no Pro-tier feature (norms, composite factors, forced-choice questions,
 * etc.) and no dependency on the import engine (which lives in Ravanix Pro) —
 * this file builds everything directly via $wpdb.
 *
 * Two scored dimensions (Mood, Anxiety) each keep a clean, internally
 * consistent raw-score interpretation range. The three standalone items
 * (the screening gate, its conditional follow-up, and the coping-style
 * multiple-choice) are deliberately dimension-less: Ravanix's interpretation
 * ranges are matched against a dimension's raw score (see
 * Ravanix_Scoring::find_interpretation()), so a question that can be
 * skipped by branching, or whose type/weight differs from the rest of a
 * dimension, must not be mixed into an existing dimension's fixed raw-score
 * range without recomputing that range to match — since these three items
 * exist only to demonstrate their respective features, keeping them
 * unscored avoids that recomputation entirely while losing nothing the demo
 * needs to show.
 *
 * The sample content is always in English; only its text direction follows
 * the site's admin language direction (is_rtl()), so the demo still displays
 * sensibly on right-to-left sites.
 */
class Ravanix_Sample_Data {

	public static function install() {
		global $wpdb;

		$rtl = is_rtl();

		$consent_text = '<p>This is a <strong>fictional, demo-only</strong> consent notice, shown here to demonstrate the informed-consent feature. In a real questionnaire, this is where you would explain what the test measures, how long it takes, and how the answers will be used.</p><p>By continuing, you agree to answer honestly and understand that this demo does not store your answers anywhere except this site\'s own database.</p>';

		$test_data = array(
			'title'          => 'Sample: Mood Screening Scale',
			'slug'           => 'ravanix-sample-mood-scale',
			'description'    => '<p>This is a <strong>completely fictional, demo-only</strong> questionnaire, created automatically on plugin activation to showcase Ravanix\'s features. No real items or data from any validated instrument are used, and it is not suitable for clinical or research use.</p>',
			'instructions'   => 'Please answer each item based on how you have been feeling lately.',
			'status'         => 'published',
			'require_login'  => 0,
			'text_direction' => $rtl ? 'rtl' : 'ltr',
			'tags'           => 'sample, demo',
			'created_by'     => get_current_user_id(),
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
			// Demonstrates: pagination with a progress bar (13 questions over 3 pages).
			'questions_per_page' => 6,
			// Demonstrates: a per-test custom informed-consent notice, overriding
			// (rather than using) the site-wide default set in Ravanix Settings.
			'consent_mode'       => 'custom',
			'consent_text'       => $consent_text,
			// Demonstrates: the "Save my progress" button and server-side resume for logged-in users.
			'enable_save_resume' => 1,
			// Demonstrates: ranking dimensions by score instead of a fixed admin-defined
			// order. (Purely to showcase the feature; a 2-dimension screening scale like
			// this one doesn't strictly need it the way a strengths/interests test would.)
			'rank_results'       => 1,
			// Demonstrates: randomizing each question's answer-option order on screen.
			// Deliberately NOT combined with randomize_questions here: this test also
			// uses branching (see below), and question-order randomization is not
			// recommended together with branching, since a shuffled order can no
			// longer guarantee the question a branch depends on is answered first.
			'randomize_options'  => 1,
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
				// Demonstrates: importance weight. This item counts 1.5x a normal
				// item; the dimension's interpretation ranges below are computed to
				// match this (min 5.5, max 27.5), not the plain 5-25 of an
				// all-weight-1 dimension -- see the class-level doc comment above.
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

			if ( 'anxiety' === $dim['code'] ) {
				// Recomputed for the 1.5x-weighted item above: 4 items at weight 1
				// (range 1-5 each) + 1 item at weight 1.5 (range 1.5-7.5) gives a
				// true achievable raw-score range of 5.5-27.5, in steps of 0.5.
				$ranges = array(
					array( 5.5, 12.5, 'Low', '#3fae54', 'This dimension score is in the low range.' ),
					array( 13.0, 19.5, 'Moderate', '#e0a72e', 'This dimension score is in the moderate range.' ),
					array( 20.0, 27.5, 'High', '#d9534f', 'This dimension score is in the high range; consulting a professional is suggested.' ),
				);
			} else {
				// Mood: 5 plain weight-1 questions, so the original 5-25 range is
				// still exactly correct (its optional, dimension-less branching
				// follow-up question below never contributes to this dimension's score).
				$ranges = array(
					array( 5, 11, 'Low', '#3fae54', 'This dimension score is in the low range.' ),
					array( 12, 18, 'Moderate', '#e0a72e', 'This dimension score is in the moderate range.' ),
					array( 19, 25, 'High', '#d9534f', 'This dimension score is in the high range; consulting a professional is suggested.' ),
				);
			}
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

		// Demonstrates: the "binary" (Yes/No) question type, and is also the
		// question a later item branches on (see below). Dimension-less: it is a
		// screening gate, not itself a scored item.
		$wpdb->insert(
			Ravanix_DB::questions(),
			array(
				'test_id'       => $test_id,
				'dimension_id'  => null,
				'question_text' => 'In the past two weeks, have you had any days where you felt noticeably low or anxious?',
				'question_type' => 'binary',
				'is_reverse'    => 0,
				'weight'        => 1,
				'sort_order'    => 0,
			)
		);
		$gate_question_id = $wpdb->insert_id;

		foreach ( $questions_by_dim['mood'] as $i => $text ) {
			$wpdb->insert(
				Ravanix_DB::questions(),
				array(
					'test_id'       => $test_id,
					'dimension_id'  => $dim_ids['mood'],
					'question_text' => $text,
					'question_type' => 'likert5',
					'is_reverse'    => 4 === $i ? 1 : 0, // "I feel hopeful about the future."
					'weight'        => 1,
					'sort_order'    => $i + 1, // 1-5 (0 is the gate question above)
				)
			);
		}

		// Demonstrates: branching/skip logic (only shown if the gate question
		// above was answered "Yes" -- binary's fixed "Yes" value is '1', see
		// Ravanix_Shortcodes::get_fixed_choices()) and the "likert7" question
		// type, in the same item. Placed on page 2 (questions_per_page = 6)
		// while the gate question it depends on is on page 1, so this also
		// exercises branching working correctly across a page boundary.
		// Dimension-less, for the reason explained in the class doc comment.
		$wpdb->insert(
			Ravanix_DB::questions(),
			array(
				'test_id'                     => $test_id,
				'dimension_id'                => null,
				'question_text'               => 'The days I felt low or anxious significantly interfered with my daily activities.',
				'question_type'                => 'likert7',
				'is_reverse'                  => 0,
				'weight'                      => 1,
				'sort_order'                  => 6,
				'branch_condition_question_id' => $gate_question_id,
				'branch_condition_value'       => '1',
			)
		);

		foreach ( $questions_by_dim['anxiety'] as $i => $text ) {
			$wpdb->insert(
				Ravanix_DB::questions(),
				array(
					'test_id'       => $test_id,
					'dimension_id'  => $dim_ids['anxiety'],
					'question_text' => $text,
					'question_type' => 'likert5',
					'is_reverse'    => 4 === $i ? 1 : 0, // "I generally feel calm."
					'weight'        => 3 === $i ? 1.5 : 1, // "My heart races without physical exertion."
					'sort_order'    => $i + 7, // 7-11
				)
			);
		}

		// Demonstrates: the "multiple" (custom multiple-choice) question type.
		// Dimension-less (purely informational, not scored), on its own page 3.
		$wpdb->insert(
			Ravanix_DB::questions(),
			array(
				'test_id'       => $test_id,
				'dimension_id'  => null,
				'question_text' => 'Which of these best describes how you usually cope with stress?',
				'question_type' => 'multiple',
				'is_reverse'    => 0,
				'weight'        => 1,
				'sort_order'    => 12,
			)
		);
		$coping_question_id = $wpdb->insert_id;

		$coping_options = array(
			array( 'Talking to friends or family', 1 ),
			array( 'Physical activity or exercise', 2 ),
			array( 'Avoiding the situation', 3 ),
			array( 'Something else', 4 ),
		);
		foreach ( $coping_options as $i => $opt ) {
			$wpdb->insert(
				Ravanix_DB::options(),
				array(
					'question_id'  => $coping_question_id,
					'dimension_id' => null,
					'option_text'  => $opt[0],
					'option_value' => $opt[1],
					'sort_order'   => $i,
				)
			);
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
