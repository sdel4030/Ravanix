<?php
/**
 * Flat AND/OR branching ("Conditional Logic" in the admin UI, "Branching &
 * Skip Logic" in docs/marketing -- see the project's own terminology note).
 *
 * This is the ONE place condition evaluation is implemented in PHP; every
 * server-side consumer (submission validation in Ravanix_Ajax::submit_test(),
 * the Test Versioning snapshot in Ravanix_DB::build_scoring_snapshot(), and
 * dependency-aware randomization in Ravanix_Shortcodes) goes through
 * get_conditions()/is_active()/get_dependency_ids() below, so there is only
 * one definition of "active" to keep correct. The frontend JS
 * (ravanix-frontend.js, applyBranching()) is a separate, hand-written mirror
 * of get_conditions()'s normalization and is_active()'s AND/OR + operator
 * logic for the participant-facing show/hide -- it is UX only and is never
 * trusted as the actual boundary; submit_test() re-derives the real
 * active/inactive set from the submitted answers independently, exactly as
 * it already did before this file existed.
 *
 * Deliberately a flat condition list per question (combined by a single
 * per-question AND/OR), not a nested boolean expression tree -- see the
 * branching policy this implements.
 */
class Ravanix_Branching {

	/**
	 * The operators recommended and their PHP comparison. Only equals/
	 * not_equals make sense for free-text or unordered choice values;
	 * gt/lt/gte/lte are meaningful for numeric question types. The admin UI
	 * is expected to only offer the numeric operators for a numeric source
	 * question, but this evaluator doesn't depend on that -- given a
	 * non-numeric value with a numeric operator, the comparison below simply
	 * treats both sides as 0, so it fails closed (the condition won't
	 * spuriously match) rather than throwing or emitting a PHP warning.
	 */
	const OPERATORS = array( 'equals', 'not_equals', 'gt', 'lt', 'gte', 'lte' );

	/**
	 * Human-readable label for one operator, for the admin condition-builder
	 * dropdown. Kept here (not in the view) so there is exactly one place
	 * mapping an operator value to display text.
	 *
	 * @param string $operator One of self::OPERATORS.
	 * @return string
	 */
	public static function operator_label( $operator ) {
		switch ( $operator ) {
			case 'not_equals':
				return __( 'is not', 'ravanix' );
			case 'gt':
				return __( 'is greater than', 'ravanix' );
			case 'lt':
				return __( 'is less than', 'ravanix' );
			case 'gte':
				return __( 'is greater than or equal to', 'ravanix' );
			case 'lte':
				return __( 'is less than or equal to', 'ravanix' );
			case 'equals':
			default:
				return __( 'equals', 'ravanix' );
		}
	}

	/**
	 * Normalizes a question's branching condition(s) into one shape,
	 * regardless of whether it uses the new multi-condition table or the
	 * legacy single-condition columns:
	 *   array( array( 'source_question_id' => int, 'operator' => string, 'value' => string ), ... )
	 *
	 * $question->conditions (attached by Ravanix_DB::get_full_test()) wins
	 * whenever it has at least one row; a question only falls back to its
	 * legacy branch_condition_question_id/branch_condition_value columns
	 * when that array is empty -- see the questions table's own docblock in
	 * class-ravanix-activator.php for why both can coexist.
	 *
	 * @param object $question A row from get_full_test()->questions (or
	 *                          anything with the same conditions/legacy shape).
	 * @return array
	 */
	public static function get_conditions( $question ) {
		if ( ! empty( $question->conditions ) ) {
			$out = array();
			foreach ( $question->conditions as $c ) {
				$out[] = array(
					'source_question_id' => intval( $c->source_question_id ),
					'operator'            => in_array( $c->operator, self::OPERATORS, true ) ? $c->operator : 'equals',
					'value'               => $c->value,
				);
			}
			return $out;
		}

		if ( ! empty( $question->branch_condition_question_id ) ) {
			return array(
				array(
					'source_question_id' => intval( $question->branch_condition_question_id ),
					'operator'            => 'equals',
					'value'               => $question->branch_condition_value,
				),
			);
		}

		return array();
	}

	/**
	 * Every source_question_id a question's conditions reference, deduplicated.
	 * Used by dependency-aware randomization to keep a question after
	 * whatever it depends on, and by the admin question-editor JS.
	 *
	 * @param object $question
	 * @return int[]
	 */
	public static function get_dependency_ids( $question ) {
		$ids = array();
		foreach ( self::get_conditions( $question ) as $c ) {
			if ( $c['source_question_id'] ) {
				$ids[ $c['source_question_id'] ] = true;
			}
		}
		return array_keys( $ids );
	}

	/**
	 * Whether $question should be considered active (shown / required /
	 * scored) given the answers submitted so far, keyed by question_id --
	 * exactly $raw_answers in Ravanix_Ajax::submit_test().
	 *
	 * A question with no conditions is always active (matches every
	 * existing question on an upgraded site, which has none). Otherwise all
	 * (AND) or any (OR) of its conditions must evaluate true, per
	 * $question->branch_logic_type ('and' is the column default).
	 *
	 * @param object $question
	 * @param array  $raw_answers question_id => submitted raw value.
	 * @return bool
	 */
	public static function is_active( $question, $raw_answers ) {
		$conditions = self::get_conditions( $question );
		if ( empty( $conditions ) ) {
			return true;
		}

		$logic_type = ( isset( $question->branch_logic_type ) && 'or' === $question->branch_logic_type ) ? 'or' : 'and';

		if ( 'or' === $logic_type ) {
			foreach ( $conditions as $c ) {
				if ( self::evaluate_condition( $c, $raw_answers ) ) {
					return true;
				}
			}
			return false;
		}

		foreach ( $conditions as $c ) {
			if ( ! self::evaluate_condition( $c, $raw_answers ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array $condition   One normalized condition from get_conditions().
	 * @param array $raw_answers question_id => submitted raw value.
	 * @return bool
	 */
	private static function evaluate_condition( $condition, $raw_answers ) {
		$actual = $raw_answers[ $condition['source_question_id'] ] ?? null;
		// An unanswered (skipped/not-yet-reached) source question can never
		// satisfy any operator, not even "not_equals" -- there is nothing to
		// compare yet, so the condition simply isn't met.
		if ( null === $actual || '' === $actual ) {
			return false;
		}

		$expected = $condition['value'];

		switch ( $condition['operator'] ) {
			case 'not_equals':
				return (string) $actual !== (string) $expected;
			case 'gt':
				return floatval( $actual ) > floatval( $expected );
			case 'lt':
				return floatval( $actual ) < floatval( $expected );
			case 'gte':
				return floatval( $actual ) >= floatval( $expected );
			case 'lte':
				return floatval( $actual ) <= floatval( $expected );
			case 'equals':
			default:
				return (string) $actual === (string) $expected;
		}
	}
}
