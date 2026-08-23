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
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY slug (slug(191)),
			KEY status (status)
		) {$charset_collate};";

		// Dimensions / subscales table
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
			composite_id BIGINT UNSIGNED NULL,
			PRIMARY KEY (id),
			KEY test_id (test_id)
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
		$sql[] = "CREATE TABLE {$p}ravanix_composite_norms (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			composite_id BIGINT UNSIGNED NOT NULL,
			group_label VARCHAR(255) NOT NULL,
			gender VARCHAR(10) NOT NULL DEFAULT 'all',
			min_age SMALLINT NULL,
			max_age SMALLINT NULL,
			mean FLOAT NOT NULL DEFAULT 0,
			sd FLOAT NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY composite_id (composite_id)
		) {$charset_collate};";

		// Questions table
		$sql[] = "CREATE TABLE {$p}ravanix_questions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			test_id BIGINT UNSIGNED NOT NULL,
			dimension_id BIGINT UNSIGNED NULL,
			question_text TEXT NOT NULL,
			question_type VARCHAR(20) NOT NULL DEFAULT 'likert5',
			is_reverse TINYINT(1) NOT NULL DEFAULT 0,
			weight FLOAT NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY test_id (test_id),
			KEY dimension_id (dimension_id)
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

		// Norm tables, used to compute T- and Z-scores split by age/gender
		$sql[] = "CREATE TABLE {$p}ravanix_norms (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			dimension_id BIGINT UNSIGNED NOT NULL,
			group_label VARCHAR(255) NOT NULL,
			gender VARCHAR(10) NOT NULL DEFAULT 'all',
			min_age SMALLINT NULL,
			max_age SMALLINT NULL,
			mean FLOAT NOT NULL DEFAULT 0,
			sd FLOAT NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY dimension_id (dimension_id)
		) {$charset_collate};";

		// Results table (one record per test attempt)
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
			submitted_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY test_id (test_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		// Scores-per-dimension table for each result
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

		// Initialize plugin settings if not already set
		if ( false === get_option( Ravanix_Settings::OPTION_KEY, false ) ) {
			update_option( Ravanix_Settings::OPTION_KEY, Ravanix_Settings::defaults() );
		}

		if ( class_exists( 'Ravanix_CPT' ) ) {
			Ravanix_CPT::register_post_type();

			// Re-sync all existing tests with the custom post type; this ensures that
			// when the post_content format changes (e.g. to fix a site-search
			// visibility issue), sites that already had tests get updated too, without
			// needing to manually re-save every test.
			if ( Ravanix_CPT::is_enabled() ) {
				global $wpdb;
				$existing_tests = $wpdb->get_results( 'SELECT * FROM ' . Ravanix_DB::tests() );
				foreach ( $existing_tests as $existing_test ) {
					$cpt_post_id = Ravanix_CPT::sync_test_to_post( $existing_test );
					if ( $cpt_post_id && intval( $existing_test->cpt_post_id ) !== intval( $cpt_post_id ) ) {
						$wpdb->update( Ravanix_DB::tests(), array( 'cpt_post_id' => $cpt_post_id ), array( 'id' => $existing_test->id ) );
					}
				}
			}
		}

		// Install sample data only the first time (must happen after the custom post
		// type is registered, so the post type already exists when syncing the sample test to a WordPress post)
		if ( ! get_option( 'ravanix_sample_data_installed' ) ) {
			require_once RAVANIX_PLUGIN_DIR . 'includes/class-ravanix-sample-data.php';
			Ravanix_Sample_Data::install();
			update_option( 'ravanix_sample_data_installed', 1 );
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
