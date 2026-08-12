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
 * Helper class for quick access to table names.
 */
class Ravanix_DB {

	public static function tests() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_tests';
	}

	public static function dimensions() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_dimensions';
	}

	public static function questions() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_questions';
	}

	public static function options() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_options';
	}

	public static function question_dimensions() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_question_dimensions';
	}

	public static function interpretations() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_interpretations';
	}

	public static function norms() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_norms';
	}

	public static function composites() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_composites';
	}

	public static function composite_interpretations() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_composite_interpretations';
	}

	public static function composite_norms() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_composite_norms';
	}

	public static function results() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_results';
	}

	public static function result_scores() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_result_scores';
	}

	/**
	 * Load the full structure of a test (dimensions + questions + options + interpretations).
	 */
	public static function get_full_test( $test_id ) {
		global $wpdb;

		$test = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::tests() . " WHERE id = %d", $test_id ) );
		if ( ! $test ) {
			return null;
		}

		$dimensions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::dimensions() . " WHERE test_id = %d ORDER BY sort_order ASC, id ASC", $test_id ) );

		foreach ( $dimensions as &$dim ) {
			$dim->interpretations = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::interpretations() . " WHERE dimension_id = %d ORDER BY range_min ASC", $dim->id ) );
			$dim->norms           = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::norms() . " WHERE dimension_id = %d ORDER BY sort_order ASC, id ASC", $dim->id ) );
			$dim->questions       = array();
		}
		unset( $dim );

		$questions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::questions() . " WHERE test_id = %d ORDER BY sort_order ASC, id ASC", $test_id ) );

		$dims_by_id = array();
		foreach ( $dimensions as $dim ) {
			$dims_by_id[ $dim->id ] = $dim;
		}

		foreach ( $questions as $q ) {
			$q->options = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::options() . " WHERE question_id = %d ORDER BY sort_order ASC, id ASC", $q->id ) );
			// Extra dimensions: for questions where a single answer must contribute
			// to more than one scale at once (overlapping keying, as in MMPI/Millon-style instruments).
			$q->extra_dimensions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::question_dimensions() . " WHERE question_id = %d", $q->id ) );
			if ( $q->dimension_id && isset( $dims_by_id[ $q->dimension_id ] ) ) {
				$dims_by_id[ $q->dimension_id ]->questions[] = $q;
			}
		}

		$test->dimensions = array_values( $dims_by_id );
		$test->questions  = $questions;

		// Load composite factors (e.g. a NEO higher-order domain) with their member subscales.
		$composites = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::composites() . " WHERE test_id = %d ORDER BY sort_order ASC, id ASC", $test_id ) );
		foreach ( $composites as &$comp ) {
			$comp->interpretations = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::composite_interpretations() . " WHERE composite_id = %d ORDER BY range_min ASC", $comp->id ) );
			$comp->norms           = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::composite_norms() . " WHERE composite_id = %d ORDER BY sort_order ASC, id ASC", $comp->id ) );
			$comp->member_dimension_ids = array();
			foreach ( $test->dimensions as $dim ) {
				if ( isset( $dim->composite_id ) && intval( $dim->composite_id ) === intval( $comp->id ) ) {
					$comp->member_dimension_ids[] = $dim->id;
				}
			}
		}
		unset( $comp );
		$test->composites = $composites;

		return $test;
	}
}
