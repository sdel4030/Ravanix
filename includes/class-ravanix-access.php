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
	 * Basic anti-spam/bot measures before accepting a test's answers.
	 *
	 * Execution-limit, access-code, and WooCommerce-purchase checks are
	 * Ravanix Pro-only features, applied through it via the
	 * ravanix_extra_access_check filter (in Ravanix_Shortcodes::render_test and Ravanix_Ajax::submit_test).
	 */
class Ravanix_Access {

	/**
	 * Checks the honeypot (a hidden field bots usually fill in) and the minimum time required to complete the test
	 */
	public static function check_honeypot_and_timing( $honeypot_value, $elapsed_ms, $min_ms = 3000 ) {
		if ( ! empty( $honeypot_value ) ) {
			return false;
		}
		if ( null !== $elapsed_ms && $elapsed_ms < $min_ms ) {
			return false;
		}
		return true;
	}

	/**
	 * Rate-limits submissions by IP using a Transient (no new table needed)
	 *
	 * @return bool true if the submission is allowed
	 */
	public static function check_rate_limit( $ip, $max_requests = 8, $window_seconds = 60 ) {
		if ( empty( $ip ) ) {
			return true;
		}

		$key   = 'ravanix_rate_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= $max_requests ) {
			return false;
		}

		set_transient( $key, $count + 1, $window_seconds );
		return true;
	}

	/**
	 * Gets/creates the guest browser ID from a cookie (for guest-session identification and anti-abuse controls).
	 *
	 * This token is also the sole authorization credential a guest (non
	 * logged-in) participant has for viewing/downloading their own result
	 * later (see the ownership check next to every result-access path, e.g.
	 * Ravanix_Pro_Pdf's is_owner check), so it must be a cryptographically
	 * unpredictable value, not merely "random-looking". random_bytes() is
	 * PHP's CSPRNG (available unconditionally since PHP 7.0, well below this
	 * plugin's PHP 7.4+ requirement); a length of 32 raw bytes (64 hex chars)
	 * matches the ≥32-byte token size used elsewhere for the same reason.
	 * A previously-issued cookie value keeps working unchanged after this
	 * change — only the format of *newly minted* tokens differs, and every
	 * comparison against a stored token already uses hash_equals(), which
	 * doesn't care about the token's length or character set.
	 */
	public static function get_or_set_guest_token() {
		if ( ! empty( $_COOKIE['ravanix_guest_token'] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE['ravanix_guest_token'] ) );
		}

		try {
			$token = bin2hex( random_bytes( 32 ) );
		} catch ( Exception $e ) {
			// Practically unreachable (random_bytes() only throws if the
			// platform's CSPRNG is unavailable), but fall back rather than
			// fatal so a submission can still proceed.
			$token = wp_generate_password( 64, false, false );
		}

		if ( ! headers_sent() ) {
			setcookie( 'ravanix_guest_token', $token, time() + ( 3 * YEAR_IN_SECONDS ), COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
		}

		return $token;
	}
}
