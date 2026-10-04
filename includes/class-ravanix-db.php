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

	public static function drafts() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_drafts';
	}

	public static function test_versions() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_test_versions';
	}

	public static function question_conditions() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_question_conditions';
	}

	public static function validity_rules() {
		global $wpdb;
		return $wpdb->prefix . 'ravanix_validity_rules';
	}

	/**
	 * Object-cache group used for get_full_test(). See the "Database
	 * Performance" section of the project docs for the caching strategy.
	 */
	const CACHE_GROUP = 'ravanix';

	/**
	 * Groups a flat list of rows by a foreign-key column, so that a single
	 * batched "WHERE parent_id IN (...)" query can be redistributed back to
	 * its many parents in PHP instead of running one query per parent
	 * (the N+1 pattern this whole method replaces).
	 *
	 * @param array  $rows       Rows returned by $wpdb->get_results().
	 * @param string $fk_column  The foreign-key property name on each row.
	 * @return array<int, array> Rows grouped by (int) $row->$fk_column.
	 */
	private static function group_by( $rows, $fk_column ) {
		$grouped = array();
		foreach ( $rows as $row ) {
			$key = intval( $row->$fk_column );
			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array();
			}
			$grouped[ $key ][] = $row;
		}
		return $grouped;
	}

	/**
	 * Runs a single batched "WHERE $fk_column IN (...)" query for a set of
	 * parent IDs. Returns an empty array without touching the database if
	 * $ids is empty (an empty SQL "IN ()" is invalid, and there is nothing
	 * to fetch for a parentless test anyway).
	 *
	 * @param string $table      Fully-qualified table name.
	 * @param string $fk_column  Foreign-key column to filter on.
	 * @param array  $ids        Parent IDs (e.g. all dimension IDs for this test).
	 * @param string $order_by   Optional ORDER BY clause (column names only, never user input).
	 * @return array Raw rows across all parents; group_by() them next.
	 */
	private static function batch_select( $table, $fk_column, $ids, $order_by = '' ) {
		global $wpdb;
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = "SELECT * FROM {$table} WHERE {$fk_column} IN ({$placeholders})";
		if ( $order_by ) {
			$sql .= ' ORDER BY ' . $order_by;
		}
		return $wpdb->get_results( $wpdb->prepare( $sql, ...$ids ) );
	}

	/**
	 * Load the full structure of a test (dimensions + questions + options +
	 * interpretations + norms + composites), using batched "WHERE id IN (...)"
	 * queries instead of one query per parent row, and caching the assembled
	 * result. See the project docs' "Database Performance" section:
	 * regardless of questionnaire size this always runs a fixed, small number
	 * of queries (9 on a full cache miss; 0 on a cache hit) rather than one
	 * that grows with the number of dimensions/questions/composites.
	 *
	 * The cache key embeds the test row's own updated_at, so any admin save
	 * of the test itself automatically invalidates it. Saves to a *child*
	 * entity (a dimension, question, interpretation, norm, or composite)
	 * call touch_test() to bump that same updated_at and get the same effect
	 * — see touch_test() below and its callers in Ravanix_Admin_Handlers.
	 *
	 * @param int  $test_id
	 * @param bool $bypass_cache Force a fresh read (used by admin preview/edit screens).
	 * @return object|null
	 */
	public static function get_full_test( $test_id, $bypass_cache = false ) {
		global $wpdb;
		$test_id = intval( $test_id );
		if ( ! $test_id ) {
			return null;
		}

		// Query #1 (always needed): the test row itself, which also carries
		// updated_at for the cache key below.
		$test = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tests() . ' WHERE id = %d', $test_id ) );
		if ( ! $test ) {
			return null;
		}

		$cache_key = 'full_test_' . $test_id . '_v' . $test->updated_at;

		if ( ! $bypass_cache ) {
			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( false !== $cached ) {
				return $cached;
			}
			// wp_cache_* alone is only request-scoped memory unless an external
			// object cache (Redis/Memcached) is configured; on plain/default
			// installs it would recompute every full_test load once per
			// request, which is why a transient is also kept as a persistent
			// fallback (see the project docs' "Transient fallback" note).
			if ( ! wp_using_ext_object_cache() ) {
				$transient = get_transient( 'ravanix_full_test_' . $test_id );
				if ( is_array( $transient ) && isset( $transient['cache_key'], $transient['data'] ) && $transient['cache_key'] === $cache_key ) {
					wp_cache_set( $cache_key, $transient['data'], self::CACHE_GROUP );
					return $transient['data'];
				}
			}
		}

		// Query #2: dimensions for this test.
		$dimensions = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::dimensions() . ' WHERE test_id = %d ORDER BY sort_order ASC, id ASC', $test_id ) );
		$dim_ids    = wp_list_pluck( $dimensions, 'id' );

		// Query #3 + #4 + #5: interpretations, norms, and validity rules for ALL dimensions at once.
		$interp_by_dim    = self::group_by( self::batch_select( self::interpretations(), 'dimension_id', $dim_ids, 'range_min ASC' ), 'dimension_id' );
		$norms_by_dim     = self::group_by( self::batch_select( self::norms(), 'dimension_id', $dim_ids, 'sort_order ASC, id ASC' ), 'dimension_id' );
		$validity_by_dim  = self::group_by( self::batch_select( self::validity_rules(), 'dimension_id', $dim_ids, 'sort_order ASC, id ASC' ), 'dimension_id' );

		$dims_by_id = array();
		foreach ( $dimensions as $dim ) {
			$dim->interpretations = $interp_by_dim[ $dim->id ] ?? array();
			$dim->norms           = $norms_by_dim[ $dim->id ] ?? array();
			$dim->validity_rules  = $validity_by_dim[ $dim->id ] ?? array();
			$dim->questions       = array();
			$dims_by_id[ $dim->id ] = $dim;
		}

		// Query #6: questions for this test.
		$questions = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::questions() . ' WHERE test_id = %d ORDER BY sort_order ASC, id ASC', $test_id ) );
		$q_ids     = wp_list_pluck( $questions, 'id' );

		// Query #7 + #8 + #9: options, extra dimension-mappings, and AND/OR
		// branching conditions for ALL questions at once.
		// (Extra dimensions: for questions where a single answer must contribute
		// to more than one scale at once — overlapping keying, as in MMPI/Millon-style instruments.)
		// (Conditions: see Ravanix_Branching::get_conditions() for how a
		// question with none of these falls back to its legacy single-condition columns.)
		$options_by_q    = self::group_by( self::batch_select( self::options(), 'question_id', $q_ids, 'sort_order ASC, id ASC' ), 'question_id' );
		$extra_by_q      = self::group_by( self::batch_select( self::question_dimensions(), 'question_id', $q_ids ), 'question_id' );
		$conditions_by_q = self::group_by( self::batch_select( self::question_conditions(), 'question_id', $q_ids, 'sort_order ASC, id ASC' ), 'question_id' );

		foreach ( $questions as $q ) {
			$q->options          = $options_by_q[ $q->id ] ?? array();
			$q->extra_dimensions = $extra_by_q[ $q->id ] ?? array();
			$q->conditions       = $conditions_by_q[ $q->id ] ?? array();
			if ( $q->dimension_id && isset( $dims_by_id[ $q->dimension_id ] ) ) {
				$dims_by_id[ $q->dimension_id ]->questions[] = $q;
			}
		}

		$test->dimensions = array_values( $dims_by_id );
		$test->questions  = $questions;

		// Query #10: composite factors (e.g. a NEO higher-order domain) for this test.
		$composites = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::composites() . ' WHERE test_id = %d ORDER BY sort_order ASC, id ASC', $test_id ) );
		$comp_ids   = wp_list_pluck( $composites, 'id' );

		// Query #11 + #12: composite interpretations and norms for ALL composites at once.
		// (Kept as two queries, same as the per-dimension pair above, since
		// composites are a separate, usually much smaller, entity than
		// dimensions/questions — the documented "≤ 12 queries" budget (a
		// typical test with no composites) already assumes a typical test
		// may or may not use composites at all; up to 14 with composites.)
		$comp_interp_by_id = self::group_by( self::batch_select( self::composite_interpretations(), 'composite_id', $comp_ids, 'range_min ASC' ), 'composite_id' );
		$comp_norms_by_id  = self::group_by( self::batch_select( self::composite_norms(), 'composite_id', $comp_ids, 'sort_order ASC, id ASC' ), 'composite_id' );

		foreach ( $composites as $comp ) {
			$comp->interpretations      = $comp_interp_by_id[ $comp->id ] ?? array();
			$comp->norms                = $comp_norms_by_id[ $comp->id ] ?? array();
			$comp->member_dimension_ids = array();
			foreach ( $test->dimensions as $dim ) {
				if ( isset( $dim->composite_id ) && intval( $dim->composite_id ) === intval( $comp->id ) ) {
					$comp->member_dimension_ids[] = $dim->id;
				}
			}
		}
		$test->composites = $composites;

		wp_cache_set( $cache_key, $test, self::CACHE_GROUP );
		if ( ! wp_using_ext_object_cache() ) {
			set_transient( 'ravanix_full_test_' . $test_id, array( 'cache_key' => $cache_key, 'data' => $test ), HOUR_IN_SECONDS );
		}

		return $test;
	}

	/**
	 * Bumps a test's updated_at so the next get_full_test() call for it
	 * naturally misses the versioned cache (see get_full_test() above).
	 * Called after any admin save/delete that changes a test's dimensions,
	 * questions, options, interpretations, norms, or composites — i.e.
	 * anything get_full_test() reads besides the test row itself, which
	 * already updates its own updated_at when Ravanix_Admin_Handlers saves it
	 * directly. Hooked to the 'ravanix_test_structure_changed' action
	 * (registered in ravanix.php) rather than called ad-hoc, so every current
	 * and future save path — Lite or Pro — invalidates the cache the same way.
	 *
	 * @param int $test_id
	 */
	public static function touch_test( $test_id ) {
		global $wpdb;
		$test_id = intval( $test_id );
		if ( ! $test_id ) {
			return;
		}
		$wpdb->update( self::tests(), array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $test_id ), array( '%s' ), array( '%d' ) );
		if ( ! wp_using_ext_object_cache() ) {
			delete_transient( 'ravanix_full_test_' . $test_id );
		}
	}

	/* =====================================================================
	 * Test Versioning
	 *
	 * See the docblock on the ravanix_test_versions CREATE TABLE in
	 * class-ravanix-activator.php for the overall design (lazy snapshots,
	 * why). The two methods below are its entire implementation: one
	 * decides what counts as "the definition", the other decides when a new
	 * immutable copy of it is needed. Ravanix_Scoring::save_result() is the
	 * only caller, and it is the single path every result (Lite or Pro,
	 * logged-in or guest) is saved through.
	 * ===================================================================== */

	/**
	 * Extracts only the scoring/assessment-integrity-relevant fields from an
	 * already-loaded get_full_test() object -- the same list the project's
	 * own test-versioning policy names: question text, answer options,
	 * dimensions, weights, reverse keys, interpretations, norms, composites,
	 * branching, and validity rules. Deliberately excludes everything
	 * administrative/cosmetic (title, description, instructions, tags,
	 * consent settings, notification settings, access code, execution
	 * limit, WooCommerce product, CPT id, display/randomization settings,
	 * level_color, interpretation/description prose) -- changing any of
	 * those does not change what a previously-stored answer means, so it
	 * must never trigger a new version on its own; only what is returned
	 * here is hashed to decide that.
	 *
	 * IDs are kept in the snapshot (not stripped) on purpose: they let a
	 * future "view this result against the exact definition it was scored
	 * against" screen resolve e.g. a dimension_id back to a dimension's
	 * current name/color for display, without that display concern being
	 * part of what the hash considers meaningful.
	 *
	 * @param object $full_test Result of get_full_test().
	 * @return array
	 */
	public static function build_scoring_snapshot( $full_test ) {
		$snapshot = array(
			'scoring_method' => $full_test->scoring_method,
			'dimensions'     => array(),
			'questions'      => array(),
			'composites'     => array(),
		);

		foreach ( $full_test->dimensions as $dim ) {
			$snapshot['dimensions'][] = array(
				'id'                   => intval( $dim->id ),
				'code'                 => $dim->code,
				'interpretation_basis' => $dim->interpretation_basis,
				'is_validity_scale'    => intval( $dim->is_validity_scale ),
				'validity_logic_type'  => $dim->validity_logic_type,
				'validity_rules'       => array_map(
					function( $r ) {
						return array(
							'operator' => $r->operator,
							'value'    => floatval( $r->value ),
							'value2'   => isset( $r->value2 ) && '' !== $r->value2 ? floatval( $r->value2 ) : null,
						);
					},
					// Normalized (falls back to the legacy validity_threshold
					// column when this dimension has no rows of its own yet)
					// -- see Ravanix_Pro_Scoring::get_validity_rules().
					class_exists( 'Ravanix_Pro_Scoring' ) ? Ravanix_Pro_Scoring::get_validity_rules( $dim ) : array()
				),
				'interpretations'      => array_map(
					function( $i ) {
						return array(
							'range_min'   => floatval( $i->range_min ),
							'range_max'   => floatval( $i->range_max ),
							'level_label' => $i->level_label,
						);
					},
					$dim->interpretations
				),
				'norms'                => array_map( array( __CLASS__, 'snapshot_norm_row' ), $dim->norms ),
			);
		}

		foreach ( $full_test->questions as $q ) {
			$snapshot['questions'][] = array(
				'id'                => intval( $q->id ),
				'question_text'     => $q->question_text,
				'question_type'     => $q->question_type,
				'dimension_id'      => $q->dimension_id ? intval( $q->dimension_id ) : null,
				'is_reverse'        => intval( $q->is_reverse ),
				'weight'            => floatval( $q->weight ),
				'branch_logic_type' => $q->branch_logic_type,
				'conditions'        => array_map(
					function( $c ) {
						return array(
							'source_question_id' => intval( $c->source_question_id ),
							'operator'            => $c->operator,
							'value'               => $c->value,
						);
					},
					// build_scoring_snapshot() always sees the *normalized*
					// condition list (falls back to the legacy columns when
					// $q->conditions is empty), not the raw table rows --
					// see Ravanix_Branching::get_conditions() -- so a
					// pre-existing single-condition question and a newly
					// entered equivalent one hash identically.
					Ravanix_Branching::get_conditions( $q )
				),
				'options'           => array_map(
					function( $o ) {
						return array(
							'option_text'  => $o->option_text,
							'option_value' => floatval( $o->option_value ),
						);
					},
					$q->options
				),
				'extra_dimensions'  => array_map(
					function( $ed ) {
						return array(
							'dimension_id' => intval( $ed->dimension_id ),
							'is_reverse'   => intval( $ed->is_reverse ),
							'weight'       => floatval( $ed->weight ),
						);
					},
					$q->extra_dimensions
				),
			);
		}

		foreach ( $full_test->composites as $comp ) {
			$snapshot['composites'][] = array(
				'id'                    => intval( $comp->id ),
				'code'                  => $comp->code,
				'combine_method'        => $comp->combine_method,
				'interpretation_basis'  => $comp->interpretation_basis,
				'member_dimension_ids'  => array_map( 'intval', $comp->member_dimension_ids ),
				'interpretations'       => array_map(
					function( $i ) {
						return array(
							'range_min'   => floatval( $i->range_min ),
							'range_max'   => floatval( $i->range_max ),
							'level_label' => $i->level_label,
						);
					},
					$comp->interpretations
				),
				'norms'                 => array_map( array( __CLASS__, 'snapshot_norm_row' ), $comp->norms ),
			);
		}

		return $snapshot;
	}

	/**
	 * Shared by build_scoring_snapshot() for both a dimension's norms and a
	 * composite's norms (identical shape on both tables).
	 */
	private static function snapshot_norm_row( $n ) {
		return array(
			'group_label' => $n->group_label,
			'gender'      => $n->gender,
			'min_age'     => isset( $n->min_age ) ? intval( $n->min_age ) : null,
			'max_age'     => isset( $n->max_age ) ? intval( $n->max_age ) : null,
			'mean'        => floatval( $n->mean ),
			'sd'          => floatval( $n->sd ),
		);
	}

	/**
	 * Returns the id of the test_versions row matching this test's *current*
	 * scoring-relevant definition, creating one first if the definition has
	 * changed since the last time this was called for this test (or if this
	 * test has never had a version recorded at all). Called once per
	 * save_result() -- see the class-level docblock above for why this is
	 * lazy rather than tied to admin saves.
	 *
	 * Deliberately tolerant of failure: this is provenance metadata, not a
	 * requirement for scoring to work, so on any unexpected DB error this
	 * returns null (leaving the result's test_version_id unset) rather than
	 * blocking the submission the participant is actually trying to complete.
	 *
	 * @param int $test_id
	 * @return int|null
	 */
	public static function get_or_create_version( $test_id ) {
		global $wpdb;
		$test_id = intval( $test_id );
		if ( ! $test_id ) {
			return null;
		}

		$full_test = self::get_full_test( $test_id );
		if ( ! $full_test ) {
			return null;
		}

		$snapshot = self::build_scoring_snapshot( $full_test );
		// wp_json_encode with sorted top-level keys so the hash only ever
		// changes when the *content* changes. Nested arrays don't need an
		// explicit sort: build_scoring_snapshot() always writes each one's
		// keys in the same literal order, which PHP preserves consistently
		// across calls.
		ksort( $snapshot );
		$canonical = wp_json_encode( $snapshot );
		$hash      = hash( 'sha256', $canonical );

		$latest = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, structure_hash FROM ' . self::test_versions() . ' WHERE test_id = %d ORDER BY version_number DESC LIMIT 1',
				$test_id
			)
		);

		if ( $latest && $latest->structure_hash === $hash ) {
			return intval( $latest->id );
		}

		$next_version = $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(version_number), 0) + 1 FROM ' . self::test_versions() . ' WHERE test_id = %d', $test_id ) );

		$inserted = $wpdb->insert(
			self::test_versions(),
			array(
				'test_id'        => $test_id,
				'version_number' => intval( $next_version ),
				'structure_hash' => $hash,
				'snapshot_json'  => $canonical,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		if ( $inserted ) {
			return intval( $wpdb->insert_id );
		}

		// Two submissions racing each other right after the same definition
		// change can both reach this point before either has inserted (the
		// UNIQUE (test_id, version_number) key on this table exists exactly
		// to catch that): the loser's insert fails here, not earlier. Rather
		// than surface that as "no version", check whether the winner's row
		// already has our exact hash -- if so, this is simply that same new
		// version arriving a moment late, not a real conflict.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . self::test_versions() . ' WHERE test_id = %d AND structure_hash = %s ORDER BY id DESC LIMIT 1', $test_id, $hash ) );
		if ( $row ) {
			return intval( $row->id );
		}

		return $latest ? intval( $latest->id ) : null;
	}

	/**
	 * Object-ownership integrity checks (project security policy, "Object
	 * ownership"): every admin_post save/delete handler for a test's child
	 * entities (dimension, question, interpretation, norm, composite,
	 * composite interpretation/norm) must call the matching method below
	 * before writing, instead of trusting that a posted parent ID and a
	 * posted child ID were submitted together honestly. A submitted child ID
	 * is never sufficient by itself to modify/delete a resource scoped to a
	 * parent -- the exact same principle Lite's own save_question() already
	 * applies inline to branch_condition_question_id.
	 *
	 * Each method returns true only when both IDs are positive integers and
	 * a row with that exact child/parent pair actually exists; a mismatch
	 * (including a parent that itself doesn't exist) is always false, i.e.
	 * "not found for that parent" -- callers should fail the request rather
	 * than silently proceeding.
	 */
	public static function dimension_belongs_to_test( $dimension_id, $test_id ) {
		global $wpdb;
		$dimension_id = intval( $dimension_id );
		$test_id      = intval( $test_id );
		if ( ! $dimension_id || ! $test_id ) {
			return false;
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::dimensions() . ' WHERE id = %d AND test_id = %d', $dimension_id, $test_id ) );
	}

	public static function question_belongs_to_test( $question_id, $test_id ) {
		global $wpdb;
		$question_id = intval( $question_id );
		$test_id     = intval( $test_id );
		if ( ! $question_id || ! $test_id ) {
			return false;
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::questions() . ' WHERE id = %d AND test_id = %d', $question_id, $test_id ) );
	}

	/**
	 * An interpretation range's own foreign key is dimension_id, not
	 * test_id, so this joins through the dimension to reach the test.
	 */
	public static function interpretation_belongs_to_test( $interpretation_id, $test_id ) {
		global $wpdb;
		$interpretation_id = intval( $interpretation_id );
		$test_id           = intval( $test_id );
		if ( ! $interpretation_id || ! $test_id ) {
			return false;
		}
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ri.id FROM ' . self::interpretations() . ' ri INNER JOIN ' . self::dimensions() . ' d ON d.id = ri.dimension_id WHERE ri.id = %d AND d.test_id = %d',
				$interpretation_id,
				$test_id
			)
		);
	}

	/**
	 * A norm group's own foreign key is dimension_id, not test_id (same
	 * shape as interpretations above).
	 */
	public static function norm_belongs_to_test( $norm_id, $test_id ) {
		global $wpdb;
		$norm_id = intval( $norm_id );
		$test_id = intval( $test_id );
		if ( ! $norm_id || ! $test_id ) {
			return false;
		}
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT rn.id FROM ' . self::norms() . ' rn INNER JOIN ' . self::dimensions() . ' d ON d.id = rn.dimension_id WHERE rn.id = %d AND d.test_id = %d',
				$norm_id,
				$test_id
			)
		);
	}

	public static function composite_belongs_to_test( $composite_id, $test_id ) {
		global $wpdb;
		$composite_id = intval( $composite_id );
		$test_id      = intval( $test_id );
		if ( ! $composite_id || ! $test_id ) {
			return false;
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::composites() . ' WHERE id = %d AND test_id = %d', $composite_id, $test_id ) );
	}

	public static function composite_interpretation_belongs_to_composite( $interpretation_id, $composite_id ) {
		global $wpdb;
		$interpretation_id = intval( $interpretation_id );
		$composite_id      = intval( $composite_id );
		if ( ! $interpretation_id || ! $composite_id ) {
			return false;
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::composite_interpretations() . ' WHERE id = %d AND composite_id = %d', $interpretation_id, $composite_id ) );
	}

	public static function composite_norm_belongs_to_composite( $norm_id, $composite_id ) {
		global $wpdb;
		$norm_id      = intval( $norm_id );
		$composite_id = intval( $composite_id );
		if ( ! $norm_id || ! $composite_id ) {
			return false;
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::composite_norms() . ' WHERE id = %d AND composite_id = %d', $norm_id, $composite_id ) );
	}
}
