<?php
/**
 * Fires only when the plugin is removed via "Delete" on the Plugins screen
 * (never on simple deactivation). By default this file does nothing at all:
 * it only deletes data on a site where the admin has explicitly opted in via
 * the "Delete data on uninstall" checkbox in Ravanix Settings beforehand. See
 * class-ravanix-settings.php (delete_data_on_uninstall, default 0) and
 * admin/views/settings.php for where that choice is made.
 *
 * Ravanix Pro has no database tables or options of its own — every field it
 * adds lives in these same Lite-owned tables/columns — so there is nothing
 * separate for Ravanix Pro to clean up here.
 *
 * Every query below interpolates a table name (never a value) built only from
 * $wpdb->prefix plus a fixed string constant from the hardcoded array below —
 * never from user input. $wpdb->prepare() cannot parameterize a table/column
 * identifier (only values via %s/%d); WordPress's %i identifier placeholder
 * would be the modern way to satisfy this sniff, but it only exists since
 * WP 6.2, and this plugin still supports WP 5.8+ (see readme.txt), so using
 * %i here would break the query on any site between 5.8 and 6.1. The
 * safe, correct option on a plugin with this version floor is a scoped,
 * explained phpcs:ignore rather than an unsafe or version-incompatible query.
 */
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deletes all Ravanix data on a single site, but only if that site's own
 * "delete_data_on_uninstall" setting is enabled. No-ops otherwise.
 */
function ravanix_uninstall_single_site() {
	$settings = get_option( 'ravanix_settings', array() );

	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return; // Not opted in on this site: leave every table/option untouched.
	}

	global $wpdb;
	$p = $wpdb->prefix;

	// Delete the linked custom-post-type entries for every test (if the CPT
	// display feature was ever enabled). Their featured images are attachments
	// in the Media Library and are deliberately left alone: they are
	// independent WordPress objects that may be reused elsewhere on the site.
	$cpt_post_ids = $wpdb->get_col( "SELECT cpt_post_id FROM {$p}ravanix_tests WHERE cpt_post_id IS NOT NULL" );
	foreach ( $cpt_post_ids as $post_id ) {
		$post_id = intval( $post_id );
		if ( $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	// Drop every Ravanix custom table. Table names are fixed, predefined
	// strings ($wpdb->prefix + a constant from this hardcoded allowlist), never user input.
	$tables = array(
		'ravanix_drafts',
		'ravanix_result_scores',
		'ravanix_results',
		'ravanix_composite_norms',
		'ravanix_composite_interpretations',
		'ravanix_composites',
		'ravanix_norms',
		'ravanix_interpretations',
		'ravanix_question_dimensions',
		'ravanix_options',
		'ravanix_questions',
		'ravanix_dimensions',
		'ravanix_tests',
	);
	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$p}{$table}" );
	}

	delete_option( 'ravanix_settings' );
	delete_option( 'ravanix_db_version' );
	delete_option( 'ravanix_sample_data_installed' );

	// Best-effort cleanup of any not-yet-expired rate-limit transients; these
	// also expire on their own, so this is just tidiness, not a correctness requirement.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_ravanix\_rate\_%' OR option_name LIKE '\_transient\_timeout\_ravanix\_rate\_%'" );
}

if ( is_multisite() ) {
	$ravanix_site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $ravanix_site_ids as $ravanix_site_id ) {
		switch_to_blog( $ravanix_site_id );
		ravanix_uninstall_single_site();
		restore_current_blog();
	}
} else {
	ravanix_uninstall_single_site();
}
