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

class Ravanix_Admin_Handlers {

	public function __construct() {
		add_action( 'admin_post_ravanix_save_test', array( $this, 'save_test' ) );
		add_action( 'admin_post_ravanix_delete_test', array( $this, 'delete_test' ) );

		add_action( 'admin_post_ravanix_save_dimension', array( $this, 'save_dimension' ) );
		add_action( 'admin_post_ravanix_delete_dimension', array( $this, 'delete_dimension' ) );

		add_action( 'admin_post_ravanix_save_question', array( $this, 'save_question' ) );
		add_action( 'admin_post_ravanix_delete_question', array( $this, 'delete_question' ) );

		add_action( 'admin_post_ravanix_save_interpretation', array( $this, 'save_interpretation' ) );
		add_action( 'admin_post_ravanix_delete_interpretation', array( $this, 'delete_interpretation' ) );

		add_action( 'admin_post_ravanix_delete_result', array( $this, 'delete_result' ) );

		add_action( 'admin_post_ravanix_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_ravanix_bulk_import_questions', array( $this, 'bulk_import_questions' ) );
		add_action( 'admin_post_ravanix_assign_dimension_questions', array( $this, 'assign_dimension_questions' ) );
		add_action( 'admin_post_ravanix_bulk_tests_action', array( $this, 'bulk_tests_action' ) );
		add_action( 'admin_post_ravanix_bulk_results_action', array( $this, 'bulk_results_action' ) );
	}

	private function check( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'ravanix' ) );
		}
		check_admin_referer( $action );
	}

	private function redirect_to_test( $test_id, $tab = 'general', $extra_args = array() ) {
		$url = admin_url( 'admin.php?page=ravanix-edit-test&test_id=' . intval( $test_id ) . '&tab=' . sanitize_key( $tab ) );
		if ( ! empty( $extra_args ) ) {
			$url = add_query_arg( $extra_args, $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/* ---------------- Test ---------------- */

	public function save_test() {
		$this->check( 'ravanix_save_test' );
		global $wpdb;

		$test_id        = isset( $_POST['test_id'] ) ? intval( $_POST['test_id'] ) : 0;
		$title          = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$description    = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );
		$instructions   = sanitize_textarea_field( wp_unslash( $_POST['instructions'] ?? '' ) );
		$status         = in_array( sanitize_key( wp_unslash( $_POST['status'] ?? '' ) ), array( 'draft', 'published' ), true ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft';
		$require_login  = isset( $_POST['require_login'] ) ? 1 : 0;
		$custom_slug    = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
		$tags           = sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) );
		$categories     = sanitize_text_field( wp_unslash( $_POST['categories'] ?? '' ) );
		// Text direction is no longer a manual per-test setting; it is detected
		// automatically from WordPress's own is_rtl() (Persian/Arabic ⇒ RTL,
		// English/Latin ⇒ LTR), matching the site/admin language. The column is
		// still stored (rather than removed) for backward compatibility with
		// existing installs and with Ravanix Pro's JSON import/export format.
		$text_direction = is_rtl() ? 'rtl' : 'ltr';
		$rank_results   = isset( $_POST['rank_results'] ) ? 1 : 0;
		$randomize_questions = isset( $_POST['randomize_questions'] ) ? 1 : 0;
		$randomize_options   = isset( $_POST['randomize_options'] ) ? 1 : 0;
		$featured_image_id = ! empty( $_POST['featured_image_id'] ) ? intval( $_POST['featured_image_id'] ) : null;
		$raw_questions_per_page = isset( $_POST['questions_per_page'] ) ? sanitize_text_field( wp_unslash( $_POST['questions_per_page'] ) ) : '';
		$questions_per_page     = ( '' !== $raw_questions_per_page && intval( $raw_questions_per_page ) > 0 ) ? intval( $raw_questions_per_page ) : null;
		$consent_mode        = in_array( sanitize_key( wp_unslash( $_POST['consent_mode'] ?? '' ) ), array( 'default', 'custom', 'disabled' ), true ) ? sanitize_key( wp_unslash( $_POST['consent_mode'] ) ) : 'default';
		$consent_text        = wp_kses_post( wp_unslash( $_POST['consent_text'] ?? '' ) );
		$enable_save_resume  = isset( $_POST['enable_save_resume'] ) ? 1 : 0;
		$wp_categories  = isset( $_POST['wp_categories'] ) ? array_map( 'intval', (array) $_POST['wp_categories'] ) : array();
		$wp_categories  = array_filter( $wp_categories ); // Remove zero/invalid values
		$custom_categories = array_filter( array_map( 'trim', explode( ',', $categories ) ) );
		$all_categories = array_unique( array_merge( array_map( 'strval', $wp_categories ), $custom_categories ) );
		$categories = implode( ', ', $all_categories );

		if ( empty( $title ) ) {
			wp_die( esc_html__( 'Test title is required.', 'ravanix' ) );
		}

		// If a custom slug was entered, make sure it's unique
		if ( ! empty( $custom_slug ) ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM " . Ravanix_DB::tests() . " WHERE slug = %s AND id != %d LIMIT 1",
					$custom_slug,
					$test_id
				)
			);
			if ( $exists ) {
				$custom_slug .= '-' . wp_generate_password( 3, false );
			}
		}

		$data = array(
			'title'         => $title,
			'description'   => $description,
			'instructions'  => $instructions,
			'status'        => $status,
			'require_login' => $require_login,
			'tags'          => $tags,
			'categories'    => $categories,
			'text_direction' => $text_direction,
			'rank_results'   => $rank_results,
			'randomize_questions' => $randomize_questions,
			'randomize_options'   => $randomize_options,
			'featured_image_id' => $featured_image_id,
			'questions_per_page' => $questions_per_page,
			'consent_mode'       => $consent_mode,
			'consent_text'       => $consent_text,
			'enable_save_resume' => $enable_save_resume,
			'updated_at'    => current_time( 'mysql' ),
		);

		if ( ! empty( $custom_slug ) ) {
			$data['slug'] = $custom_slug;
		}

		if ( $test_id ) {
			$update_result = $wpdb->update( Ravanix_DB::tests(), $data, array( 'id' => $test_id ) );
			if ( false === $update_result && $wpdb->last_error ) {
				// A real database error; shown to the site admin for debugging
				wp_die( esc_html__( 'Error saving data:', 'ravanix' ) . ' ' . esc_html( $wpdb->last_error ) );
			}
		} else {
			if ( empty( $data['slug'] ) ) {
				$data['slug'] = sanitize_title( $title ) . '-' . wp_generate_password( 4, false );
			}
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( Ravanix_DB::tests(), $data );
			$test_id = $wpdb->insert_id;
		}

		// Sync with the custom post type (if enabled in Settings)
		// This block is deliberately wrapped in try/catch so any error here doesn't
		// disrupt saving the test's main information.
		try {
			$test_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . Ravanix_DB::tests() . " WHERE id = %d", $test_id ) );
			if ( $test_row ) {
				$cpt_post_id = Ravanix_CPT::sync_test_to_post( $test_row );
				if ( $cpt_post_id ) {
					$wpdb->update( Ravanix_DB::tests(), array( 'cpt_post_id' => $cpt_post_id ), array( 'id' => $test_id ) );
				}
			}
		} catch ( \Throwable $e ) {
			// Ignored; the test's main information has already been saved successfully
		}

		/**
		 * After a test's base fields are saved successfully. Ravanix Pro uses this
		 * action to read and save its own fields (execution limit, access code,
		 * WooCommerce product, etc.) from this same POST request.
		 *
		 * @param int $test_id The saved test's ID.
		 */
		do_action( 'ravanix_after_save_test', $test_id );

		$this->redirect_to_test( $test_id, 'general', array( 'saved' => 1 ) );
	}

	public function delete_test() {
		$this->check( 'ravanix_delete_test' );
		$test_id = intval( $_GET['test_id'] ?? 0 );
		$this->delete_test_by_id( $test_id );

		wp_safe_redirect( admin_url( 'admin.php?page=ravanix&deleted=1' ) );
		exit;
	}

	private function delete_test_by_id( $test_id ) {
		global $wpdb;
		$test_id = intval( $test_id );
		if ( ! $test_id ) {
			return;
		}

		$dim_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . Ravanix_DB::dimensions() . " WHERE test_id = %d", $test_id ) );
		foreach ( $dim_ids as $dim_id ) {
			$wpdb->delete( Ravanix_DB::interpretations(), array( 'dimension_id' => $dim_id ) );
		}

		$q_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . Ravanix_DB::questions() . " WHERE test_id = %d", $test_id ) );
		foreach ( $q_ids as $q_id ) {
			$wpdb->delete( Ravanix_DB::options(), array( 'question_id' => $q_id ) );
			$wpdb->delete( Ravanix_DB::question_dimensions(), array( 'question_id' => $q_id ) );
		}

		$result_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . Ravanix_DB::results() . " WHERE test_id = %d", $test_id ) );
		foreach ( $result_ids as $rid ) {
			$wpdb->delete( Ravanix_DB::result_scores(), array( 'result_id' => $rid ) );
		}

		// Delete the linked post in the custom post type (if it exists)
		$cpt_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT cpt_post_id FROM " . Ravanix_DB::tests() . " WHERE id = %d", $test_id ) );
		if ( $cpt_post_id ) {
			wp_delete_post( $cpt_post_id, true );
		}

		$wpdb->delete( Ravanix_DB::results(), array( 'test_id' => $test_id ) );
		$wpdb->delete( Ravanix_DB::questions(), array( 'test_id' => $test_id ) );
		$wpdb->delete( Ravanix_DB::dimensions(), array( 'test_id' => $test_id ) );
		$wpdb->delete( Ravanix_DB::tests(), array( 'id' => $test_id ) );
	}

	/* ---------------- Dimension / Subscale ---------------- */

	public function save_dimension() {
		$this->check( 'ravanix_save_dimension' );
		global $wpdb;

		$dim_id  = intval( $_POST['dimension_id'] ?? 0 );
		$test_id = intval( $_POST['test_id'] ?? 0 );

		// T-score interpretation and validity-scale flagging are Ravanix
		// Pro-only features (Lite has no norm tables to compute a T-score from,
		// and no validity-threshold checking logic); the fields are hidden from
		// this form when Pro isn't active (see partial-dimensions.php), and are
		// also ignored here server-side as defense in depth.
		$pro_active = class_exists( 'Ravanix_Pro_Scoring' );

		$raw_validity_threshold = isset( $_POST['validity_threshold'] ) ? sanitize_text_field( wp_unslash( $_POST['validity_threshold'] ) ) : '';

		$data = array(
			'test_id'     => $test_id,
			'name'        => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'code'        => sanitize_key( $_POST['code'] ?? '' ),
			'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'sort_order'  => intval( $_POST['sort_order'] ?? 0 ),
			'interpretation_basis' => ( $pro_active && in_array( sanitize_key( wp_unslash( $_POST['interpretation_basis'] ?? '' ) ), array( 'raw', 't_score' ), true ) ) ? sanitize_key( wp_unslash( $_POST['interpretation_basis'] ) ) : 'raw',
			'is_validity_scale'    => ( $pro_active && isset( $_POST['is_validity_scale'] ) ) ? 1 : 0,
			'validity_threshold'   => ( $pro_active && isset( $_POST['is_validity_scale'] ) && '' !== $raw_validity_threshold ) ? floatval( $raw_validity_threshold ) : null,
		);

		if ( $dim_id ) {
			$wpdb->update( Ravanix_DB::dimensions(), $data, array( 'id' => $dim_id ) );
		} else {
			$wpdb->insert( Ravanix_DB::dimensions(), $data );
		}

		$this->redirect_to_test( $test_id, 'dimensions', array( 'saved' => 1 ) );
	}

	public function delete_dimension() {
		$this->check( 'ravanix_delete_dimension' );
		global $wpdb;
		$dim_id  = intval( $_GET['dimension_id'] ?? 0 );
		$test_id = intval( $_GET['test_id'] ?? 0 );

		$wpdb->delete( Ravanix_DB::interpretations(), array( 'dimension_id' => $dim_id ) );
		$wpdb->update( Ravanix_DB::questions(), array( 'dimension_id' => null ), array( 'dimension_id' => $dim_id ) );
		$wpdb->delete( Ravanix_DB::dimensions(), array( 'id' => $dim_id ) );

		$this->redirect_to_test( $test_id, 'dimensions' );
	}

	/* ---------------- Question ---------------- */

	public function save_question() {
		$this->check( 'ravanix_save_question' );
		global $wpdb;

		$q_id    = intval( $_POST['question_id'] ?? 0 );
		$test_id = intval( $_POST['test_id'] ?? 0 );

		// Skip logic: only accept a "depends on" question ID that (a) actually
		// belongs to this same test (never another test's/site's question -- this
		// is an object-level integrity check, the same principle as an
		// authorization check, just applied to a foreign-key-like reference
		// instead of to "which user may act"), (b) is not the question
		// referencing itself, and (c) actually comes before this question in
		// display order -- a question cannot meaningfully depend on one the
		// participant hasn't been asked yet. An invalid/missing selection is
		// silently treated as "no condition" (always shown) rather than
		// rejecting the whole save, consistent with how every other optional
		// field on this form behaves.
		$branch_question_id = ! empty( $_POST['branch_condition_question_id'] ) ? intval( $_POST['branch_condition_question_id'] ) : 0;
		$this_sort_order    = intval( $_POST['sort_order'] ?? 0 );
		if ( $branch_question_id && $branch_question_id !== $q_id ) {
			$source_sort_order = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT sort_order FROM " . Ravanix_DB::questions() . " WHERE id = %d AND test_id = %d",
					$branch_question_id,
					$test_id
				)
			);
			if ( null === $source_sort_order || intval( $source_sort_order ) >= $this_sort_order ) {
				$branch_question_id = 0;
			}
		} else {
			$branch_question_id = 0;
		}
		$branch_value = $branch_question_id ? sanitize_text_field( wp_unslash( $_POST['branch_condition_value'] ?? '' ) ) : null;

		$data = array(
			'test_id'        => $test_id,
			'dimension_id'   => ! empty( $_POST['dimension_id'] ) ? intval( $_POST['dimension_id'] ) : null,
			'question_text'  => sanitize_textarea_field( wp_unslash( $_POST['question_text'] ?? '' ) ),
			'question_type'  => sanitize_key( $_POST['question_type'] ?? 'likert5' ),
			'is_reverse'     => isset( $_POST['is_reverse'] ) ? 1 : 0,
			'weight'         => floatval( $_POST['weight'] ?? 1 ),
			'sort_order'     => intval( $_POST['sort_order'] ?? 0 ),
			'branch_condition_question_id' => $branch_question_id ?: null,
			'branch_condition_value'       => $branch_value,
		);

		if ( $q_id ) {
			$wpdb->update( Ravanix_DB::questions(), $data, array( 'id' => $q_id ) );
		} else {
			$wpdb->insert( Ravanix_DB::questions(), $data );
			$q_id = $wpdb->insert_id;
		}

		// Custom options (for multiple/custom types)
		$wpdb->delete( Ravanix_DB::options(), array( 'question_id' => $q_id ) );

		if ( in_array( $data['question_type'], array( 'multiple', 'custom' ), true ) && ! empty( $_POST['option_text'] ) ) {
			$texts        = wp_unslash( $_POST['option_text'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each element is sanitize_text_field()'d below before use.
			$values       = isset( $_POST['option_value'] ) ? wp_unslash( $_POST['option_value'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each element is floatval()'d below before use.
			$option_dims  = isset( $_POST['option_dimension_id'] ) ? wp_unslash( $_POST['option_dimension_id'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each element is intval()'d below before use.
			foreach ( $texts as $i => $t ) {
				$t = sanitize_text_field( $t );
				if ( '' === trim( $t ) ) {
					continue;
				}
				$wpdb->insert(
					Ravanix_DB::options(),
					array(
						'question_id'  => $q_id,
						'dimension_id' => ! empty( $option_dims[ $i ] ) ? intval( $option_dims[ $i ] ) : null,
						'option_text'  => $t,
						'option_value' => isset( $values[ $i ] ) ? floatval( $values[ $i ] ) : 0,
						'sort_order'   => $i,
					)
				);
			}
		}

		// Extra dimensions (multi-scale overlapping keying) and the "multiple-choice
		// with a separate dimension per option" (forced_choice) question type are
		// Pro-only features; the question_dimensions table is always kept empty for
		// every question in the Lite version.
		$wpdb->delete( Ravanix_DB::question_dimensions(), array( 'question_id' => $q_id ) );

		/**
		 * After a question is saved. Ravanix Pro uses this action to save the
		 * "multiple-choice with a separate dimension per option" options and the
		 * overlapping-keying extra dimensions.
		 *
		 * @param int $q_id The saved question's ID.
		 */
		do_action( 'ravanix_after_save_question', $q_id );

		$this->redirect_to_test( $test_id, 'questions', array( 'saved' => 1 ) );
	}

	public function delete_question() {
		$this->check( 'ravanix_delete_question' );
		global $wpdb;
		$q_id    = intval( $_GET['question_id'] ?? 0 );
		$test_id = intval( $_GET['test_id'] ?? 0 );

		// Prevent a dangling branch reference: without this, any other question
		// in this test whose "show only if" condition points at $q_id would
		// become permanently, silently hidden after this delete, since its
		// condition could never be satisfied again (the question it depends on
		// no longer exists to be answered). Clearing it back to "always shown"
		// is the same safe default a newly-created question already starts with.
		$wpdb->update(
			Ravanix_DB::questions(),
			array( 'branch_condition_question_id' => null, 'branch_condition_value' => null ),
			array( 'branch_condition_question_id' => $q_id )
		);

		$wpdb->delete( Ravanix_DB::options(), array( 'question_id' => $q_id ) );
		$wpdb->delete( Ravanix_DB::question_dimensions(), array( 'question_id' => $q_id ) );
		$wpdb->delete( Ravanix_DB::questions(), array( 'id' => $q_id ) );

		$this->redirect_to_test( $test_id, 'questions' );
	}

	/* ---------------- Interpretation Range ---------------- */

	public function save_interpretation() {
		$this->check( 'ravanix_save_interpretation' );
		global $wpdb;

		$int_id  = intval( $_POST['interpretation_id'] ?? 0 );
		$test_id = intval( $_POST['test_id'] ?? 0 );

		$data = array(
			'dimension_id' => intval( $_POST['dimension_id'] ?? 0 ),
			'range_min'    => floatval( $_POST['range_min'] ?? 0 ),
			'range_max'    => floatval( $_POST['range_max'] ?? 0 ),
			'level_label'  => sanitize_text_field( wp_unslash( $_POST['level_label'] ?? '' ) ),
			'level_color'  => sanitize_hex_color( wp_unslash( $_POST['level_color'] ?? '' ) ) ?: '#4a90d9',
			'description'  => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
		);

		if ( $int_id ) {
			$wpdb->update( Ravanix_DB::interpretations(), $data, array( 'id' => $int_id ) );
		} else {
			$wpdb->insert( Ravanix_DB::interpretations(), $data );
		}

		$this->redirect_to_test( $test_id, 'interpretations', array( 'saved' => 1 ) );
	}

	public function delete_interpretation() {
		$this->check( 'ravanix_delete_interpretation' );
		global $wpdb;
		$int_id  = intval( $_GET['interpretation_id'] ?? 0 );
		$test_id = intval( $_GET['test_id'] ?? 0 );

		$wpdb->delete( Ravanix_DB::interpretations(), array( 'id' => $int_id ) );

		$this->redirect_to_test( $test_id, 'interpretations' );
	}
	/* ---------------- Results ---------------- */

	public function delete_result() {
		$this->check( 'ravanix_delete_result' );
		$result_id = intval( $_GET['result_id'] ?? 0 );
		$this->delete_result_by_id( $result_id );

		wp_safe_redirect( admin_url( 'admin.php?page=ravanix-results&deleted=1' ) );
		exit;
	}

	private function delete_result_by_id( $result_id ) {
		global $wpdb;
		$result_id = intval( $result_id );
		if ( ! $result_id ) {
			return;
		}
		$wpdb->delete( Ravanix_DB::result_scores(), array( 'result_id' => $result_id ) );
		$wpdb->delete( Ravanix_DB::results(), array( 'id' => $result_id ) );
	}

	/* ---------------- Settings ---------------- */

	public function save_settings() {
		$this->check( 'ravanix_save_settings' );

		$posted_fields = isset( $_POST['participant_fields'] ) && is_array( $_POST['participant_fields'] ) ? wp_unslash( $_POST['participant_fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- this is a checkbox array; each sub-value is only ever checked with isset() below (the correct pattern for checkboxes, which carry no value to sanitize beyond their presence).
		$default_fields = Ravanix_Settings::defaults()['participant_fields'];
		$participant_fields = array();
		foreach ( $default_fields as $key => $def ) {
			// The "label" is deliberately not stored here; since it is translatable
			// text, it must always be freshly generated at read time (via
			// Ravanix_Settings::get()), based on the current request's language, rather
			// than staying frozen in the database.
			$participant_fields[ $key ] = array(
				'enabled'  => isset( $posted_fields[ $key ]['enabled'] ) ? 1 : 0,
				'required' => isset( $posted_fields[ $key ]['required'] ) ? 1 : 0,
			);
		}

		Ravanix_Settings::update(
			array(
				'enable_cpt'         => isset( $_POST['enable_cpt'] ) ? 1 : 0,
				'cpt_slug'           => sanitize_key( $_POST['cpt_slug'] ?? 'questionnaire' ) ?: 'questionnaire',
				'cpt_singular'       => sanitize_text_field( wp_unslash( $_POST['cpt_singular'] ?? 'Questionnaire' ) ),
				'cpt_plural'         => sanitize_text_field( wp_unslash( $_POST['cpt_plural'] ?? 'Questionnaires' ) ),
				'participant_fields' => $participant_fields,
				// A deliberate, explicit opt-in; see uninstall.php. Only ever set to
				// 1 by an admin who ticked the checkbox on this exact request.
				'delete_data_on_uninstall' => isset( $_POST['delete_data_on_uninstall'] ) ? 1 : 0,
				// Site-wide default informed-consent notice; a specific test can
				// still override or disable it via its own consent_mode setting.
				'consent_text' => wp_kses_post( wp_unslash( $_POST['consent_text'] ?? '' ) ),
				// Off unless the admin explicitly ticks this box; see the matching
				// comment in Ravanix_Settings::defaults() (WordPress.org Guideline 10).
				'show_branding' => isset( $_POST['show_branding'] ) ? 1 : 0,
				'brand_color'   => sanitize_hex_color( wp_unslash( $_POST['brand_color'] ?? '' ) ) ?: '#4a6fa5',
			)
		);

		// Registers the post type immediately with the new settings and flushes
		// permalinks in this same request (no need to manually visit the "Permalinks" page)
		Ravanix_CPT::register_and_flush();

		wp_safe_redirect( admin_url( 'admin.php?page=ravanix-settings&saved=1' ) );
		exit;
	}

	/* ---------------- Bulk question import ---------------- */

	public function bulk_import_questions() {
		$this->check( 'ravanix_bulk_import_questions' );
		global $wpdb;

		$test_id       = intval( $_POST['test_id'] ?? 0 );
		$dimension_id  = ! empty( $_POST['dimension_id'] ) ? intval( $_POST['dimension_id'] ) : null;
		$question_type = sanitize_key( $_POST['question_type'] ?? 'likert5' );
		$weight        = floatval( $_POST['weight'] ?? 1 );
		$raw_lines     = wp_unslash( $_POST['bulk_questions'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each resulting line is sanitize_textarea_field()'d individually below before use.

		// If "custom multiple-choice" was selected, first extract the shared options
		$shared_options = array();
		if ( 'multiple' === $question_type && ! empty( $_POST['shared_options'] ) ) {
			$opt_lines = preg_split( '/\r\n|\r|\n/', wp_unslash( $_POST['shared_options'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each resulting option is sanitize_text_field()'d/floatval()'d individually below before use.
			foreach ( $opt_lines as $opt_line ) {
				$opt_line = trim( $opt_line );
				if ( '' === $opt_line || false === strpos( $opt_line, '|' ) ) {
					continue;
				}
				list( $opt_text, $opt_value ) = array_map( 'trim', explode( '|', $opt_line, 2 ) );
				if ( '' === $opt_text ) {
					continue;
				}
				$shared_options[] = array(
					'text'  => sanitize_text_field( $opt_text ),
					'value' => floatval( $opt_value ),
				);
			}
		}

		$lines = preg_split( '/\r\n|\r|\n/', $raw_lines );

		$max_sort = intval( $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sort_order) FROM " . Ravanix_DB::questions() . " WHERE test_id = %d", $test_id ) ) );
		$order    = $max_sort + 1;
		$imported = 0;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$is_reverse = 0;
			// A star (*) at the end of a line means reverse scoring
			if ( '*' === substr( rtrim( $line ), -1 ) ) {
				$is_reverse = 1;
				$line       = trim( substr( rtrim( $line ), 0, -1 ) );
			}

			if ( '' === $line ) {
				continue;
			}

			$wpdb->insert(
				Ravanix_DB::questions(),
				array(
					'test_id'       => $test_id,
					'dimension_id'  => $dimension_id,
					'question_text' => sanitize_textarea_field( $line ),
					'question_type' => $question_type,
					'is_reverse'    => $is_reverse,
					'weight'        => $weight,
					'sort_order'    => $order,
				)
			);

			$new_question_id = $wpdb->insert_id;

			if ( 'multiple' === $question_type && ! empty( $shared_options ) ) {
				foreach ( $shared_options as $i => $opt ) {
					$wpdb->insert(
						Ravanix_DB::options(),
						array(
							'question_id'  => $new_question_id,
							'option_text'  => $opt['text'],
							'option_value' => $opt['value'],
							'sort_order'   => $i,
						)
					);
				}
			}

			$order++;
			$imported++;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ravanix-edit-test&test_id=' . $test_id . '&tab=questions&imported=' . $imported ) );
		exit;
	}

	/**
	 * Quickly assigns questions to a dimension, based on their sequential position
	 * in the questions list (the number shown in the "Questions" tab, not the database ID).
	 */
	public function assign_dimension_questions() {
		$this->check( 'ravanix_assign_dimension_questions' );
		global $wpdb;

		$test_id      = intval( $_POST['test_id'] ?? 0 );
		$dimension_id = intval( $_POST['dimension_id'] ?? 0 );
		$raw_numbers  = sanitize_text_field( wp_unslash( $_POST['question_numbers'] ?? '' ) );

		if ( ! $test_id || ! $dimension_id ) {
			wp_die( esc_html__( 'Information is incomplete.', 'ravanix' ) );
		}

		// This test's question list, in exactly the same order shown in the "Questions" tab
		$ordered_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . Ravanix_DB::questions() . " WHERE test_id = %d ORDER BY sort_order ASC, id ASC", $test_id ) );

		$positions = array_filter( array_map( 'trim', explode( ',', $raw_numbers ) ) );

		$target_ids = array();
		foreach ( $positions as $pos ) {
			$pos = intval( $pos );
			if ( $pos >= 1 && isset( $ordered_ids[ $pos - 1 ] ) ) {
				$target_ids[] = intval( $ordered_ids[ $pos - 1 ] );
			}
		}

		// First, free all questions currently assigned to this dimension
		$wpdb->update( Ravanix_DB::questions(), array( 'dimension_id' => null ), array( 'dimension_id' => $dimension_id, 'test_id' => $test_id ) );

		// Then assign only the selected questions to this dimension
		foreach ( $target_ids as $qid ) {
			$wpdb->update( Ravanix_DB::questions(), array( 'dimension_id' => $dimension_id ), array( 'id' => $qid, 'test_id' => $test_id ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ravanix-edit-test&test_id=' . $test_id . '&tab=dimensions&assigned=' . count( $target_ids ) ) );
		exit;
	}

	/* ---------------- Bulk Actions ---------------- */

	public function bulk_tests_action() {
		$this->check( 'ravanix_bulk_tests_action' );
		global $wpdb;

		$ids       = isset( $_POST['test_ids'] ) ? array_map( 'intval', (array) $_POST['test_ids'] ) : array();
		$bulk_action = sanitize_key( $_POST['bulk_action'] ?? '' );

		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				if ( 'delete' === $bulk_action ) {
					$this->delete_test_by_id( $id );
				} elseif ( 'draft' === $bulk_action ) {
					$wpdb->update( Ravanix_DB::tests(), array( 'status' => 'draft' ), array( 'id' => $id ) );
				} elseif ( 'publish' === $bulk_action ) {
					$wpdb->update( Ravanix_DB::tests(), array( 'status' => 'published' ), array( 'id' => $id ) );
				}
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ravanix&bulk_done=1' ) );
		exit;
	}

	public function bulk_results_action() {
		$this->check( 'ravanix_bulk_results_action' );

		$ids         = isset( $_POST['result_ids'] ) ? array_map( 'intval', (array) $_POST['result_ids'] ) : array();
		$bulk_action = sanitize_key( $_POST['bulk_action'] ?? '' );
		$filter_test = intval( $_POST['filter_test_id'] ?? 0 );

		if ( ! empty( $ids ) && 'delete' === $bulk_action ) {
			foreach ( $ids as $id ) {
				$this->delete_result_by_id( $id );
			}
		}

		$redirect = admin_url( 'admin.php?page=ravanix-results&bulk_done=1' );
		if ( $filter_test ) {
			$redirect = add_query_arg( 'test_id', $filter_test, $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}
}
