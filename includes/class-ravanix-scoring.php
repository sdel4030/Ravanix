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

/**
* Scoring engine: takes a test's structure + a user's answers and computes the
 * raw score and percentage for each dimension, along with the interpretation matching that score.
 *
 * Norm-based scores (Z/T/Percentile), composite factors, validity scales, and
 * the "multiple-choice with a separate dimension per option"/multi-scale
 * overlapping-keying question type are Ravanix Pro-only features, computed and
 * saved by it via the ravanix_after_save_result action.
 */
class Ravanix_Scoring {

	/**
	 * @param object   $test    Output of Ravanix_DB::get_full_test()
	 * @param array    $answers Array in the form [question_id => value]
	 * @param int|null $age     Participant age (reserved for use by the Pro plugin)
	 * @param string|null $gender Participant gender (reserved for use by the Pro plugin)
	 * @return array The computed result, per dimension
	 */
	public static function calculate( $test, $answers, $age = null, $gender = null ) {

		$acc = array();
		foreach ( $test->dimensions as $dim ) {
			$acc[ $dim->id ] = array(
				'raw'   => 0,
				'min'   => 0,
				'max'   => 0,
				'count' => 0,
			);
		}

		foreach ( $test->dimensions as $dim ) {
			foreach ( $dim->questions as $q ) {
				if ( ! isset( $answers[ $q->id ] ) ) {
					continue;
				}

				$value = floatval( $answers[ $q->id ] );

				list( $q_min, $q_max ) = self::get_question_range( $q );

				if ( $q->is_reverse ) {
					$value = ( $q_min + $q_max ) - $value;
				}

				$value *= floatval( $q->weight );

				$acc[ $dim->id ]['raw']   += $value;
				$acc[ $dim->id ]['max']   += $q_max * floatval( $q->weight );
				$acc[ $dim->id ]['min']   += $q_min * floatval( $q->weight );
				$acc[ $dim->id ]['count']++;
			}
		}

		/**
		 * An opportunity to add dimension scores computed by Pro's custom question
		 * types (forced_choice, multi-scale overlapping keying) to this same
		 * accumulator, before the final output is built. Pro accesses $acc by
		 * reference and can update each dimension's raw/min/max/count values.
		 *
		 * @param array  &$acc     The per-dimension score accumulator.
		 * @param object $test     The full test object.
		 * @param array  $answers  The user's raw answers.
		 */
		do_action( 'ravanix_accumulate_extra_scores', array( &$acc ), $test, $answers );

		// Build the final output for each dimension from the values accumulated above
		$scores = array();
		foreach ( $test->dimensions as $dim ) {

			$raw     = $acc[ $dim->id ]['raw'];
			$min     = $acc[ $dim->id ]['min'];
			$max     = $acc[ $dim->id ]['max'];
			$q_count = $acc[ $dim->id ]['count'];

			$percentage = 0;
			if ( ( $max - $min ) > 0 ) {
				$percentage = round( ( ( $raw - $min ) / ( $max - $min ) ) * 100, 1 );
			}

			$interpretation = self::find_interpretation( $dim->interpretations, $raw );

			// Some questionnaires (e.g. typologies, strengths inventories)
			// don't need score-based interpretation ranges at all -- each
			// dimension just has one fixed, general description of what it
			// measures. When no interpretation range matches (either because
			// none were defined, or the score falls outside all of them),
			// that general description is shown instead of a "not defined" message.
			$no_interpretation_message = ! empty( $dim->description ) ? $dim->description : __( 'No interpretation has been defined for this score range.', 'ravanix-lite' );

			$scores[] = array(
				'dimension_id'          => $dim->id,
				'name'                  => $dim->name,
				'code'                  => $dim->code,
				'raw_score'             => round( $raw, 2 ),
				'min_score'             => round( $min, 2 ),
				'max_score'             => round( $max, 2 ),
				'percentage'            => $percentage,
				'question_count'        => $q_count,
				'z_score'               => null,
				't_score'               => null,
				'percentile'            => null,
				'norm_group_label'      => null,
				'interpretation_basis'  => 'raw',
				'is_validity_scale'     => false,
				'validity_threshold'    => null,
				'level_label'           => $interpretation ? $interpretation->level_label : __( 'Unspecified', 'ravanix-lite' ),
				'level_color'           => $interpretation ? $interpretation->level_color : '#999999',
				'description'           => $interpretation ? $interpretation->description : $no_interpretation_message,
			);
		}

		return $scores;
	}

	/**
	 * Determines the possible value range for each question type
	 */
	private static function get_question_range( $q ) {
		switch ( $q->question_type ) {
			case 'likert5':
				return array( 1, 5 );
			case 'likert7':
				return array( 1, 7 );
			case 'binary':
				return array( 0, 1 );
			case 'multiple':
			case 'custom':
				if ( ! empty( $q->options ) ) {
					$values = wp_list_pluck( $q->options, 'option_value' );
					return array( min( $values ), max( $values ) );
				}
				return array( 0, 1 );
			default:
				return array( 1, 5 );
		}
	}

	/**
	 * Finds the interpretation range matching the raw score
	 */
	private static function find_interpretation( $interpretations, $compare_value ) {
		if ( empty( $interpretations ) ) {
			return null;
		}
		foreach ( $interpretations as $range ) {
			if ( $compare_value >= floatval( $range->range_min ) && $compare_value <= floatval( $range->range_max ) ) {
				return $range;
			}
		}
		return null;
	}

	/**
	 * Saves the full result to the database and returns the result's ID
	 */
	public static function save_result( $test_id, $user_id, $guest_name, $answers, $scores, $participant_meta = array(), $validity = array(), $guest_token = '' ) {
		global $wpdb;

		$wpdb->insert(
			Ravanix_DB::results(),
			array(
				'test_id'             => $test_id,
				'user_id'             => $user_id,
				'guest_name'          => $guest_name,
				'guest_ip'            => self::get_ip(),
				'guest_token'         => $guest_token ? $guest_token : null,
				'participant_meta'    => ! empty( $participant_meta ) ? wp_json_encode( $participant_meta ) : null,
				'answers_json'        => wp_json_encode( $answers ),
				'is_validity_flagged' => ! empty( $validity['flagged'] ) ? 1 : 0,
				'validity_notes'      => ! empty( $validity['notes'] ) ? implode( ' ', $validity['notes'] ) : null,
				'submitted_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		$result_id = $wpdb->insert_id;

		foreach ( $scores as $s ) {
			$wpdb->insert(
				Ravanix_DB::result_scores(),
				array(
					'result_id'        => $result_id,
					'dimension_id'     => $s['dimension_id'],
					'raw_score'        => $s['raw_score'],
					'min_score'        => $s['min_score'],
					'max_score'        => $s['max_score'],
					'percentage'       => $s['percentage'],
					'z_score'          => $s['z_score'],
					't_score'          => $s['t_score'],
					'percentile'       => $s['percentile'],
					'norm_group_label' => $s['norm_group_label'],
					'level_label'      => $s['level_label'],
					'level_color'      => $s['level_color'],
					'description'      => $s['description'],
				),
				array( '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s' )
			);
		}

		/**
		 * After the full result and raw scores are saved. Ravanix Pro uses this
		 * action to compute and save norm-based scores (T/Z/Percentile), composite
		 * factor scores, and validity-scale checks.
		 *
		 * @param int   $result_id The saved result's ID.
		 * @param int   $test_id   The test's ID.
		 * @param array $scores    The raw scores computed for each dimension.
		 */
		do_action( 'ravanix_after_save_result', $result_id, $test_id, $scores );

		return $result_id;
	}

	private static function get_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return '';
	}
}
