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

class Ravanix_Activator {

	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$p               = $wpdb->prefix;

		$sql = array();

		// Tests table
		$sql[] = "CREATE TABLE {$p}ravanix_tests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			slug VARCHAR(255) NULL,
			description LONGTEXT NULL,
			instructions TEXT NULL,
			tags VARCHAR(500) NULL,
			categories VARCHAR(500) NULL,
			cpt_post_id BIGINT UNSIGNED NULL,
			text_direction VARCHAR(10) NOT NULL DEFAULT 'rtl',
			rank_results TINYINT(1) NOT NULL DEFAULT 0,
			randomize_questions TINYINT(1) NOT NULL DEFAULT 0,
			randomize_options TINYINT(1) NOT NULL DEFAULT 0,
			featured_image_id BIGINT UNSIGNED NULL,
			questions_per_page SMALLINT NULL,
			execution_limit VARCHAR(20) NOT NULL DEFAULT 'unlimited',
			retake_cooldown_days SMALLINT NULL,
			access_code VARCHAR(100) NULL,
			woocommerce_product_id BIGINT UNSIGNED NULL,
			scoring_method VARCHAR(20) NOT NULL DEFAULT 'sum',
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			require_login TINYINT(1) NOT NULL DEFAULT 1,
			consent_mode VARCHAR(20) NOT NULL DEFAULT 'default',
			consent_text LONGTEXT NULL,
			notify_admin_mode VARCHAR(20) NOT NULL DEFAULT 'default',
			notify_admin_emails_override VARCHAR(500) NULL,
			notify_admin_subject_override VARCHAR(255) NULL,
			notify_admin_body_override TEXT NULL,
			notify_admin_condition VARCHAR(20) NOT NULL DEFAULT 'always',
			notify_participant_mode VARCHAR(20) NOT NULL DEFAULT 'default',
			notify_participant_subject_override VARCHAR(255) NULL,
			notify_participant_body_override TEXT NULL,
			enable_save_resume TINYINT(1) NOT NULL DEFAULT 0,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY slug (slug(191)),
			KEY status (status)
		) {$charset_collate};";

		// Dimensions / subscales table.
		// validity_threshold (legacy) / validity_logic_type: a dimension with
		// is_validity_scale set is flagged (Ravanix Pro's
		// Ravanix_Pro_Scoring::check_validity()) when one or more rules in
		// ravanix_validity_rules (below) evaluate true, combined by AND or OR
		// per validity_logic_type -- a richer version of the single
		// always-">=" validity_threshold column, kept for backward
		// compatibility exactly like the questions table's legacy branch
		// columns: a dimension with no rows in that table still falls back to
		// validity_threshold as a single ">=" rule (see
		// Ravanix_Pro_Scoring::get_validity_rules()), so nothing already
		// configured stops working after this upgrade.
		$sql[] = "CREATE TABLE {$p}ravanix_dimensions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			test_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(255) NOT NULL,
			code VARCHAR(50) NOT NULL,
			description LONGTEXT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			interpretation_basis VARCHAR(10) NOT NULL DEFAULT 'raw',
			is_validity_scale TINYINT(1) NOT NULL DEFAULT 0,
			validity_threshold FLOAT NULL,
			validity_logic_type VARCHAR(10) NOT NULL DEFAULT 'and',
			composite_id BIGINT UNSIGNED NULL,
			PRIMARY KEY (id),
			KEY test_id (test_id)
		) {$charset_collate};";

		// Flat AND/OR validity-scale rules (see the docblock on
		// validity_logic_type above). One row is one "[operator] [value]"
		// test against the dimension's score (raw or T-score, per that
		// dimension's own interpretation_basis -- same value a normal
		// interpretation range would be written against). 'between' is the
		// only operator that uses value2; every other operator ignores it.
		$sql[] = "CREATE TABLE {$p}ravanix_validity_rules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			dimension_id BIGINT UNSIGNED NOT NULL,
			operator VARCHAR(20) NOT NULL DEFAULT 'gte',
			value FLOAT NOT NULL DEFAULT 0,
			value2 FLOAT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY dimension_id (dimension_id)
		) {$charset_collate};";

		// Composite factors table (e.g. a NEO primary factor made up of 6 subscales)
		$sql[] = "CREATE TABLE {$p}ravanix_composites (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			test_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(255) NOT NULL,
			code VARCHAR(50) NOT NULL,
			description LONGTEXT NULL,
			combine_method VARCHAR(10) NOT NULL DEFAULT 'sum',
			interpretation_basis VARCHAR(10) NOT NULL DEFAULT 'raw',
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY test_id (test_id)
		) {$charset_collate};";

		// Interpretation ranges table for composite factors (like ravanix_interpretations, but for the primary factor)
		$sql[] = "CREATE TABLE {$p}ravanix_composite_interpretations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			composite_id BIGINT UNSIGNED NOT NULL,
			range_min FLOAT NOT NULL,
			range_max FLOAT NOT NULL,
			level_label VARCHAR(100) NOT NULL,
			level_color VARCHAR(20) NOT NULL DEFAULT '#4a90d9',
			description TEXT NULL,
			PRIMARY KEY (id),
			KEY composite_id (composite_id)
		) {$charset_collate};";

		// Norm tables for composite factors (like ravanix_norms, but for the primary factor)
		// Provenance metadata (norm_set_name through methodology_notes): a
		// mean/SD pair alone is scientifically incomplete without knowing
		// what reference population it came from -- see the project's own
		// norm-metadata policy. All optional/nullable since a norm entered
		// before this metadata existed, or one an admin genuinely doesn't
		// have full provenance for, must keep working exactly as before.
		$sql[] = "CREATE TABLE {$p}ravanix_composite_norms (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			composite_id BIGINT UNSIGNED NOT NULL,
			group_label VARCHAR(255) NOT NULL,
			gender VARCHAR(10) NOT NULL DEFAULT 'all',
			min_age SMALLINT NULL,
			max_age SMALLINT NULL,
			mean FLOAT NOT NULL DEFAULT 0,
			sd FLOAT NOT NULL DEFAULT 1,
			norm_set_name VARCHAR(255) NULL,
			source_reference TEXT NULL,
			norm_version VARCHAR(50) NULL,
			population VARCHAR(255) NULL,
			country VARCHAR(100) NULL,
			language VARCHAR(100) NULL,
			sample_size INT UNSIGNED NULL,
			collection_year SMALLINT UNSIGNED NULL,
			methodology_notes TEXT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY composite_id (composite_id)
		) {$charset_collate};";

		// Questions table
		// branch_condition_question_id/branch_condition_value: legacy simple
		// single-condition skip logic, kept exactly as-is for backward
		// compatibility -- when branch_condition_question_id is set, this
		// question is only shown if the referenced question's submitted
		// answer equals branch_condition_value; NULL (the default) means
		// "always shown". A question with one or more rows in
		// ravanix_question_conditions (below) uses those instead -- see that
		// table's own docblock -- and branch_logic_type says whether they
		// combine with AND or OR. Every existing question on an upgraded
		// site has no rows there yet, so it keeps behaving exactly as before
		// via the legacy columns; see Ravanix_Branching::get_conditions().
		$sql[] = "CREATE TABLE {$p}ravanix_questions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			test_id BIGINT UNSIGNED NOT NULL,
			dimension_id BIGINT UNSIGNED NULL,
			question_text TEXT NOT NULL,
			question_type VARCHAR(20) NOT NULL DEFAULT 'likert5',
			is_reverse TINYINT(1) NOT NULL DEFAULT 0,
			weight FLOAT NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			branch_condition_question_id BIGINT UNSIGNED NULL,
			branch_condition_value VARCHAR(255) NULL,
			branch_logic_type VARCHAR(10) NOT NULL DEFAULT 'and',
			PRIMARY KEY (id),
			KEY test_id (test_id),
			KEY dimension_id (dimension_id),
			KEY branch_condition_question_id (branch_condition_question_id)
		) {$charset_collate};";

		// Flat AND/OR branching conditions ("Branching & Skip Logic" in
		// docs/marketing, "Conditional Logic" in the admin UI -- see the
		// project's own terminology note). Each row is one
		// "[source_question] [operator] [value]" test; every row for a given
		// question_id combines with that question's branch_logic_type (AND
		// or OR) -- deliberately a flat list, not a nested boolean tree, per
		// the project's own branching policy. A question with zero rows here
		// falls back to its legacy branch_condition_question_id/value columns
		// on the questions table (see that table's docblock).
		$sql[] = "CREATE TABLE {$p}ravanix_question_conditions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			question_id BIGINT UNSIGNED NOT NULL,
			source_question_id BIGINT UNSIGNED NOT NULL,
			operator VARCHAR(20) NOT NULL DEFAULT 'equals',
			value VARCHAR(255) NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY question_id (question_id),
			KEY source_question_id (source_question_id)
		) {$charset_collate};";

		// Answer options table (for multiple-choice/custom types; Likert options are generated automatically)
		// The dimension_id column is only populated for "multiple-choice with a
		// separate dimension per option" (forced_choice) questions; it stays empty for other types.
		$sql[] = "CREATE TABLE {$p}ravanix_options (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			question_id BIGINT UNSIGNED NOT NULL,
			dimension_id BIGINT UNSIGNED NULL,
			option_text VARCHAR(255) NOT NULL,
			option_value FLOAT NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY question_id (question_id),
			KEY dimension_id (dimension_id)
		) {$charset_collate};";

		// Extra dimensions per question table: for questions whose single answer must
		// score on more than one scale at once (overlapping keying, as with MMPI/Millon-style
		// instruments, where an item usually belongs to several clinical scales at once).
		// The dimension_id column in ravanix_questions still holds the "primary/first"
		// dimension; this table only stores the extra dimensions for that same question,
		// each with its own scoring direction (reverse or not) and importance weight.
		$sql[] = "CREATE TABLE {$p}ravanix_question_dimensions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			question_id BIGINT UNSIGNED NOT NULL,
			dimension_id BIGINT UNSIGNED NOT NULL,
			is_reverse TINYINT(1) NOT NULL DEFAULT 0,
			weight FLOAT NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY question_id (question_id),
			KEY dimension_id (dimension_id)
		) {$charset_collate};";

		// Interpretation ranges table for each dimension
		$sql[] = "CREATE TABLE {$p}ravanix_interpretations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			dimension_id BIGINT UNSIGNED NOT NULL,
			range_min FLOAT NOT NULL,
			range_max FLOAT NOT NULL,
			level_label VARCHAR(100) NOT NULL,
			level_color VARCHAR(20) NOT NULL DEFAULT '#4a90d9',
			description TEXT NULL,
			PRIMARY KEY (id),
			KEY dimension_id (dimension_id)
		) {$charset_collate};";

		// Norm tables, used to compute T- and Z-scores split by age/gender.
		// See the matching metadata columns on ravanix_composite_norms above
		// for what norm_set_name through methodology_notes are for.
		$sql[] = "CREATE TABLE {$p}ravanix_norms (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			dimension_id BIGINT UNSIGNED NOT NULL,
			group_label VARCHAR(255) NOT NULL,
			gender VARCHAR(10) NOT NULL DEFAULT 'all',
			min_age SMALLINT NULL,
			max_age SMALLINT NULL,
			mean FLOAT NOT NULL DEFAULT 0,
			sd FLOAT NOT NULL DEFAULT 1,
			norm_set_name VARCHAR(255) NULL,
			source_reference TEXT NULL,
			norm_version VARCHAR(50) NULL,
			population VARCHAR(255) NULL,
			country VARCHAR(100) NULL,
			language VARCHAR(100) NULL,
			sample_size INT UNSIGNED NULL,
			collection_year SMALLINT UNSIGNED NULL,
			methodology_notes TEXT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY dimension_id (dimension_id)
		) {$charset_collate};";

		// Results table (one record per test attempt). elapsed_ms is the
		// total time from when the participant opened the test to submission
		// (already collected for the existing minimum-completion-time
		// anti-spam check -- see Ravanix_Access::check_honeypot_and_timing()
		// -- just persisted here too now); is_quality_flagged/quality_notes
		// are Ravanix Pro's response-quality analysis (unusually fast
		// completion, straight-lining, etc. -- see the project's own
		// distinction between this and is_validity_flagged/validity_notes
		// above: a validity scale is a property of the instrument itself,
		// response quality is about how this particular attempt was
		// answered, regardless of which instrument it is). Lite always
		// leaves is_quality_flagged/quality_notes at their defaults; only
		// Pro (via the ravanix_after_save_result action, same as its norm
		// scoring) ever sets them, and only when Pro is active.
		// consent_version is a
		// short content hash of the exact consent text shown at submission
		// time (Ravanix_Settings::get_effective_consent_text()) -- not an
		// admin-assigned number, since consent text has no separate
		// versioning UI of its own. This still answers the provenance
		// question that matters: two results with the same hash were shown
		// the identical wording; a different hash means the site-wide or
		// per-test consent text changed between them. consented_at is set to
		// the same moment as submitted_at (see Ravanix_Scoring::save_result()) --
		// the point the server actually captured the agreement -- not
		// necessarily when the participant first read it, if they used
		// Save & Resume across multiple sessions.
		$sql[] = "CREATE TABLE {$p}ravanix_results (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			test_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			guest_name VARCHAR(255) NULL,
			guest_ip VARCHAR(64) NULL,
			guest_token VARCHAR(64) NULL,
			participant_meta LONGTEXT NULL,
			answers_json LONGTEXT NULL,
			is_validity_flagged TINYINT(1) NOT NULL DEFAULT 0,
			validity_notes TEXT NULL,
			elapsed_ms BIGINT UNSIGNED NULL,
			is_quality_flagged TINYINT(1) NOT NULL DEFAULT 0,
			quality_notes TEXT NULL,
			consent_agreed TINYINT(1) NOT NULL DEFAULT 0,
			consent_version VARCHAR(64) NULL,
			consented_at DATETIME NULL,
			test_version_id BIGINT UNSIGNED NULL,
			submitted_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY test_id (test_id),
			KEY user_id (user_id),
			KEY test_version_id (test_version_id)
		) {$charset_collate};";

		// Immutable snapshots of a test's scoring-relevant definition (Test ->
		// Version 1, 2, 3 -> Result, per the project's test-versioning
		// architecture). A row here is created lazily -- the first time a
		// result is saved against a definition whose scoring-relevant content
		// hash doesn't match the test's latest known version -- rather than
		// eagerly on every admin save, so editing/iterating on a test before
		// anyone has actually taken it never creates orphaned version rows
		// nothing will ever reference. See
		// Ravanix_DB::build_scoring_snapshot()/get_or_create_version() for
		// exactly what is and isn't considered "scoring-relevant" (matches the
		// project's own list: question text, answer options, dimensions,
		// weights, reverse keys, interpretations, norms, composites, branching,
		// validity rules -- but not title/description/consent/notification/
		// access settings, which don't change what a stored answer means).
		$sql[] = "CREATE TABLE {$p}ravanix_test_versions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			test_id BIGINT UNSIGNED NOT NULL,
			version_number INT UNSIGNED NOT NULL,
			structure_hash VARCHAR(64) NOT NULL,
			snapshot_json LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY test_id (test_id),
			KEY test_hash (test_id, structure_hash),
			UNIQUE KEY test_version_number (test_id, version_number)
		) {$charset_collate};";

		// Server-side Save & Resume drafts: one row per (test_id, user_id) pair,
		// upserted every time a logged-in participant explicitly saves their
		// progress. Only for logged-in users on purpose -- a guest has no stable
		// identity across devices/browsers for a server-side draft to be looked
		// up by, so guests keep using the existing browser-local (localStorage)
		// autosave only. Deleted once the test is fully submitted, or when the
		// participant explicitly starts over.
		$sql[] = "CREATE TABLE {$p}ravanix_drafts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			test_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			answers_json LONGTEXT NULL,
			participant_json LONGTEXT NULL,
			page INT NOT NULL DEFAULT 0,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY test_user (test_id, user_id)
		) {$charset_collate};";

		// Scores-per-dimension table for each result. norm_metadata_json is a
		// JSON snapshot of the matched norm's provenance (Ravanix Pro's
		// Ravanix_Pro_Scoring::build_norm_metadata()) taken at scoring time,
		// same reasoning as norm_group_label already being a snapshot rather
		// than a live reference: the norm this result was actually scored
		// against must stay exactly as it was even if that norm entry is
		// later edited or deleted.
		$sql[] = "CREATE TABLE {$p}ravanix_result_scores (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			result_id BIGINT UNSIGNED NOT NULL,
			dimension_id BIGINT UNSIGNED NOT NULL,
			raw_score FLOAT NOT NULL DEFAULT 0,
			min_score FLOAT NOT NULL DEFAULT 0,
			max_score FLOAT NOT NULL DEFAULT 0,
			percentage FLOAT NOT NULL DEFAULT 0,
			z_score FLOAT NULL,
			t_score FLOAT NULL,
			percentile FLOAT NULL,
			norm_group_label VARCHAR(255) NULL,
			norm_metadata_json TEXT NULL,
			level_label VARCHAR(100) NULL,
			level_color VARCHAR(20) NULL,
			description TEXT NULL,
			PRIMARY KEY (id),
			KEY result_id (result_id),
			KEY dimension_id (dimension_id)
		) {$charset_collate};";

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		update_option( 'ravanix_db_version', RAVANIX_VERSION );

		// Idempotent -- also re-runs on every version upgrade (maybe_upgrade()
		// calls this same method), so if a future Ravanix version adds a new
		// capability, every existing site's Administrator role picks it up
		// automatically. See Ravanix_Roles::ensure_roles()'s own docblock.
		Ravanix_Roles::ensure_roles();

		// Initialize plugin settings if not already set
		if ( false === get_option( Ravanix_Settings::OPTION_KEY, false ) ) {
			update_option( Ravanix_Settings::OPTION_KEY, Ravanix_Settings::defaults() );
		}

		if ( class_exists( 'Ravanix_CPT' ) ) {
			// Registering the post type itself is cheap (no DB access) and must
			// happen synchronously so rewrite rules/admin screens are correct
			// immediately; only the potentially-expensive per-test resync loop
			// below is ever deferred.
			Ravanix_CPT::register_post_type();
		}

		// Install sample data only the first time (must happen after the custom post
		// type is registered, so the post type already exists when syncing the sample test to a WordPress post).
		// This only ever touches one sample test, so it is always cheap and never deferred.
		if ( ! get_option( 'ravanix_sample_data_installed' ) ) {
			require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-sample-data.php';
			Ravanix_Sample_Data::install();
			update_option( 'ravanix_sample_data_installed', 1 );
		}

		// The two tasks below -- resyncing every existing test with the CPT, and
		// flushing rewrite rules -- are the "heavy" part of activation: on a site
		// with many questionnaires this loop (one sync per test) can be slow
		// enough to risk a PHP timeout, and it re-runs on *every* plugin update
		// (maybe_upgrade() calls activate() again whenever the stored db version
		// differs), not just on first install. On a small/typical site it is
		// fast enough to just do inline, so only sites above the threshold pay
		// for the extra moving part of a background task.
		if ( self::existing_test_count() > self::HEAVY_ACTIVATION_TEST_THRESHOLD ) {
			self::schedule_async_activation_tasks();
		} else {
			self::run_async_activation_tasks();
		}
	}

	/**
	 * Above this many existing tests, CPT resync + rewrite flushing are moved
	 * to a background task (see activate() above) instead of running inline
	 * during activation/upgrade.
	 */
	const HEAVY_ACTIVATION_TEST_THRESHOLD = 20;

	/**
	 * Cheap COUNT(*) used only to decide whether activation's CPT-resync step
	 * is light enough to run inline. Safe to call even before this method's
	 * caller has confirmed the tables exist, since activate() always runs
	 * dbDelta() first.
	 */
	private static function existing_test_count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Ravanix_DB::tests() );
	}

	/**
	 * Schedules run_async_activation_tasks() to run once in the background,
	 * preferring Action Scheduler (bundled with WooCommerce and widely
	 * available on real-world WordPress sites) and falling back to a plain
	 * wp-cron single event when it isn't present. Either way the callback is
	 * registered on the 'ravanix_async_activation_tasks' hook in ravanix.php.
	 */
	public static function schedule_async_activation_tasks() {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			if ( ! function_exists( 'as_next_scheduled_action' ) || ! as_next_scheduled_action( 'ravanix_async_activation_tasks' ) ) {
				as_schedule_single_action( time(), 'ravanix_async_activation_tasks', array(), 'ravanix' );
			}
			return;
		}
		if ( ! wp_next_scheduled( 'ravanix_async_activation_tasks' ) ) {
			wp_schedule_single_event( time(), 'ravanix_async_activation_tasks' );
		}
	}

	/**
	 * Re-syncs every existing test with the custom post type (if CPT mode is
	 * enabled) and flushes rewrite rules. Runs either inline from activate()
	 * (small sites) or once in the background via
	 * schedule_async_activation_tasks() (large sites) -- see the threshold
	 * check in activate(). This ensures that when the post_content format
	 * changes (e.g. to fix a site-search visibility issue), sites that
	 * already had tests get updated too, without needing to manually re-save
	 * every test.
	 */
	public static function run_async_activation_tasks() {
		if ( class_exists( 'Ravanix_CPT' ) && Ravanix_CPT::is_enabled() ) {
			global $wpdb;
			$existing_tests = $wpdb->get_results( 'SELECT * FROM ' . Ravanix_DB::tests() );
			foreach ( $existing_tests as $existing_test ) {
				$cpt_post_id = Ravanix_CPT::sync_test_to_post( $existing_test );
				if ( $cpt_post_id && intval( $existing_test->cpt_post_id ) !== intval( $cpt_post_id ) ) {
					$wpdb->update( Ravanix_DB::tests(), array( 'cpt_post_id' => $cpt_post_id ), array( 'id' => $existing_test->id ) );
				}
			}
		}
		flush_rewrite_rules();
	}

	/**
	 * Runs on the plugins_loaded hook and checks whether the installed database
	 * version matches the plugin version; if needed (e.g. after a plugin update
	 * without a deactivate/reactivate cycle), updates the tables.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'ravanix_db_version', '' );
		if ( $installed !== RAVANIX_VERSION ) {
			self::activate();
		}
	}
}
