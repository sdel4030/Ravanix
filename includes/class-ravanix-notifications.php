<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Sends the (optional) admin and participant email notifications after a test
 * result is fully finalized.
 *
 * Hooked to 'ravanix_result_finalized', fired in Ravanix_Ajax::submit_test()
 * immediately after Ravanix_Scoring::save_result() returns -- deliberately a
 * separate, later action than the existing 'ravanix_after_save_result' (fired
 * *inside* save_result(), which Ravanix Pro uses to add T-scores/composite
 * factors/validity flags to the just-created result row). Hooking this
 * feature to 'ravanix_after_save_result' directly would make correct behavior
 * depend on hook priority ordering between Lite and Pro; hooking to
 * 'ravanix_result_finalized' instead means the result row is always
 * completely finished -- Pro's own data included, if Pro is active -- by the
 * time any notification is sent, regardless of priority.
 *
 * Deliberately does not put scores/answers into the email body by default:
 * email is unencrypted in transit and at rest in most mail systems, and this
 * data counts as sensitive psychological information (see the project's own
 * data classification for `answers_json`/result scores). Only a link to the
 * admin's own (capability-gated) result view is included for the admin
 * email; nothing at all beyond a plain thank-you is sent to the participant
 * by default. Ravanix Pro can offer a {scores_summary} placeholder via the
 * 'ravanix_notification_placeholders' filter below -- opt-in, since it only
 * appears in an email if an admin explicitly types that token into a custom
 * template -- rather than this class assuming it's ever safe to include.
 *
 * Sent as HTML (see send()), using whatever formatting an admin sets in the
 * Settings page's editor (wp_editor() -- the Classic/rich-text editor,
 * requested for better message formatting than a plain textarea allowed).
 * Because of that, every placeholder value that could ever contain
 * participant-submitted text (right now: {participant_name}, sourced from
 * the "full name" participant field) is esc_html()'d before substitution in
 * build_placeholders()/send_admin_notification() below -- the admin's own
 * template text itself is trusted HTML (it went through wp_kses_post() on
 * save, same as consent_text), but a value coming from an anonymous
 * participant's own form input is not.
 */
class Ravanix_Notifications {

	public static function init() {
		add_action( 'ravanix_result_finalized', array( __CLASS__, 'maybe_send' ), 10, 6 );
	}

	/**
	 * @param int    $result_id
	 * @param object $test             Full test object (Ravanix_DB::get_full_test()).
	 * @param array  $scores           Per-dimension scores (Ravanix_Scoring::calculate()'s return value); unused directly here, see class docblock -- available to filter callbacks via the 'ravanix_notification_settings' filter below.
	 * @param array  $participant_meta Same shape Ravanix_Ajax builds: array of [ key => ['label'=>.., 'value'=>.., 'key'?=>..] ].
	 * @param int    $user_id          0 for a guest submission.
	 * @param string $guest_name       Only meaningful when $user_id is 0.
	 */
	public static function maybe_send( $result_id, $test, $scores, $participant_meta, $user_id, $guest_name ) {
		$settings = Ravanix_Settings::get();

		/**
		 * Lets an extension (Ravanix Pro's per-test notification overrides)
		 * replace these site-wide settings with test-specific ones before this
		 * class acts on them -- e.g. a different admin recipient for this
		 * particular test, a custom subject/body, or turning a notification
		 * off/on conditionally (such as only when this result's validity scales
		 * were flagged). Returning the array unchanged is a no-op; Lite itself
		 * never adds a callback to this filter.
		 *
		 * @param array  $settings         Site-wide notification settings (Ravanix_Settings::get()'s relevant keys).
		 * @param object $test             Full test object.
		 * @param int    $result_id
		 * @param array  $scores           Per-dimension scores for this result.
		 * @param array  $participant_meta
		 * @param int    $user_id
		 * @param string $guest_name
		 */
		$settings = apply_filters( 'ravanix_notification_settings', $settings, $test, $result_id, $scores, $participant_meta, $user_id, $guest_name );

		// A failed/misconfigured notification must never break the participant's
		// already-successful submission (the result is already safely saved by
		// this point) -- so every failure mode here is swallowed, not thrown.
		try {
			if ( ! empty( $settings['notify_admin_enabled'] ) ) {
				self::send_admin_notification( $result_id, $test, $participant_meta, $user_id, $guest_name, $settings );
			}
			if ( ! empty( $settings['notify_participant_enabled'] ) ) {
				self::send_participant_notification( $result_id, $test, $participant_meta, $settings );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// No PII (participant name/email/answers) in this log line, per the
				// project's logging policy -- only the result ID and error message.
				error_log( 'Ravanix: notification error for result #' . intval( $result_id ) . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated behind WP_DEBUG.
			}
		}
	}

	private static function send_admin_notification( $result_id, $test, $participant_meta, $user_id, $guest_name, $settings ) {
		$to = self::parse_recipient_list( $settings['notify_admin_emails'] ?? '' );
		if ( empty( $to ) ) {
			$to = array( get_option( 'admin_email' ) );
		}

		// esc_html() here, not just at final render: $participant_name is the
		// one value in this array that can come straight from the
		// participant's own submitted "full name" field (see resolve_participant_name()),
		// and this is about to go into an HTML email body.
		$participant_name = esc_html( self::resolve_participant_name( $participant_meta, $user_id, $guest_name ) );
		$vars             = self::build_placeholders( $test, array(
			'participant_name'  => $participant_name,
			'submitted_at'      => esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			'admin_result_link' => esc_url( admin_url( 'admin.php?page=ravanix-results&result_id=' . intval( $result_id ) ) ),
		), 'admin', $result_id );

		$subject = self::render( ! empty( $settings['notify_admin_subject'] ) ? $settings['notify_admin_subject'] : self::default_admin_subject(), $vars );
		$body    = self::render( ! empty( $settings['notify_admin_body'] ) ? $settings['notify_admin_body'] : self::default_admin_body(), $vars );

		self::send( $to, $subject, $body );
	}

	private static function send_participant_notification( $result_id, $test, $participant_meta, $settings ) {
		// Only ever sent if this specific submission actually collected an email
		// address (the site's "Participant info fields" setting) -- never
		// inferred, guessed, or looked up from elsewhere.
		if ( empty( $participant_meta['email']['value'] ) ) {
			return;
		}
		$to = sanitize_email( $participant_meta['email']['value'] );
		if ( ! $to || ! is_email( $to ) ) {
			return;
		}

		$vars = self::build_placeholders( $test, array(), 'participant', $result_id );

		$subject = self::render( ! empty( $settings['notify_participant_subject'] ) ? $settings['notify_participant_subject'] : self::default_participant_subject(), $vars );
		$body    = self::render( ! empty( $settings['notify_participant_body'] ) ? $settings['notify_participant_body'] : self::default_participant_body(), $vars );

		self::send( array( $to ), $subject, $body );
	}

	/**
	 * Fire-and-forget wp_mail() wrapper: a delivery failure only ever gets
	 * logged (see maybe_send()'s try/catch), never surfaced to the participant,
	 * since by this point their submission has already succeeded regardless of
	 * whether an admin's mail server happens to be misconfigured.
	 *
	 * Sent as HTML: $body is either wp_kses_post()-sanitized content from the
	 * Settings page's rich-text editor (or a per-test override in Ravanix
	 * Pro), or one of this class's own default_*_body() templates -- either
	 * way already-safe markup, not raw user input. wpautop() is applied on
	 * top since an admin who clears the editor down to plain text (or a site
	 * still holding a pre-upgrade plain-text template saved before this
	 * feature existed) should still get sensible paragraph breaks rather than
	 * one unbroken line -- the same function WordPress itself uses for the
	 * same reason wherever else this plugin renders admin-authored long text
	 * (e.g. a test's own description on the frontend).
	 */
	private static function send( $to, $subject, $body ) {
		$to = array_values( array_filter( array_map( 'sanitize_email', (array) $to ), 'is_email' ) );
		if ( empty( $to ) ) {
			return;
		}
		wp_mail( $to, $subject, wpautop( $body ), array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	private static function parse_recipient_list( $raw ) {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}
		$emails = array_map( 'trim', explode( ',', $raw ) );
		return array_values( array_filter( $emails, 'is_email' ) );
	}

	private static function resolve_participant_name( $participant_meta, $user_id, $guest_name ) {
		if ( ! empty( $participant_meta['full_name']['value'] ) ) {
			return $participant_meta['full_name']['value'];
		}
		if ( $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				return $user->display_name;
			}
		}
		return $guest_name ? $guest_name : __( 'Guest', 'ravanix' );
	}

	private static function build_placeholders( $test, $extra, $context = '', $result_id = 0 ) {
		$vars = array_merge(
			array(
				// esc_html() even though these two are admin-authored (a test
				// title, a site name), not participant input: cheap insurance
				// against a title containing "<" or "&" breaking the HTML
				// email it's about to be substituted into.
				'test_title' => esc_html( $test->title ),
				'site_name'  => esc_html( get_bloginfo( 'name' ) ),
			),
			$extra
		);

		/**
		 * Lets an extension add extra {placeholder} tokens available for use in
		 * a notification's subject/body. Lite itself never adds a callback here;
		 * Ravanix Pro uses this to offer {scores_summary} -- deliberately opt-in
		 * (it only appears in an email if an admin explicitly types that token
		 * into a custom subject/body template), not merged in automatically, per
		 * this class's own docblock on why scores aren't emailed by default.
		 *
		 * @param array  $vars      Token => replacement-text pairs.
		 * @param object $test      Full test object.
		 * @param int    $result_id
		 * @param string $context   'admin' or 'participant' -- which email this is for.
		 */
		return apply_filters( 'ravanix_notification_placeholders', $vars, $test, $result_id, $context );
	}

	/**
	 * Whitelist placeholder substitution -- deliberately strtr(), not a
	 * template engine or eval: the input is admin-authored plain text (from
	 * Settings), and the only thing that should ever change per-email is the
	 * small fixed set of {tokens} listed here.
	 */
	private static function render( $template, $vars ) {
		$map = array();
		foreach ( $vars as $key => $value ) {
			$map[ '{' . $key . '}' ] = (string) $value;
		}
		return strtr( (string) $template, $map );
	}

	public static function default_admin_subject() {
		return __( 'New Ravanix response: {test_title}', 'ravanix' );
	}

	public static function default_admin_body() {
		return __(
			'<p>A new response has been submitted.</p><p>Test: {test_title}<br>Participant: {participant_name}<br>Submitted: {submitted_at}</p><p>View in admin:<br>{admin_result_link}</p>',
			'ravanix'
		);
	}

	public static function default_participant_subject() {
		return __( 'Thank you for completing {test_title}', 'ravanix' );
	}

	public static function default_participant_body() {
		return __(
			'<p>Thank you for completing "{test_title}" on {site_name}.</p><p>Your response has been recorded.</p>',
			'ravanix'
		);
	}
}
