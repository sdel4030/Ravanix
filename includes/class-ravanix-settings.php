<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Manages the plugin's general settings (stored in wp_options)
 */
class Ravanix_Settings {

	const OPTION_KEY = 'ravanix_settings';

	public static function defaults() {
		return array(
			'enable_cpt'    => 0,
			'cpt_slug'      => 'questionnaire',
			'cpt_singular'  => 'Questionnaire',
			'cpt_plural'    => 'Questionnaires',
			'cpt_menu_icon' => 'dashicons-clipboard',
			// Off by default: deleting the plugin's data is a deliberate, explicit
			// choice the site admin must make in advance, never an implicit side
			// effect of clicking "Delete" on the Plugins screen. See uninstall.php.
			'delete_data_on_uninstall' => 0,
			// Empty by default: the informed-consent step only appears on a test
			// once this default (or that test's own override; see the test's
			// consent_mode/consent_text columns) is a non-empty, rich-text notice.
			'consent_text' => '',
			// Off by default, per WordPress.org Guideline 10 ("Powered by" /
			// credit links and displays must be optional and default to not
			// show; the site owner must explicitly opt in). Never shown unless
			// the admin explicitly enables it here.
			'show_branding' => 0,
			// The seed color the frontend's Material Design 3-inspired color
			// tokens are derived from (see the --rs-brand custom property in
			// ravanix-frontend.css); this default matches that file's own fallback.
			'brand_color'   => '#4a6fa5',
			'participant_fields' => array(
				'full_name' => array( 'enabled' => 0, 'required' => 0, 'label' => __( 'Full name', 'ravanix' ) ),
				'gender'    => array( 'enabled' => 0, 'required' => 0, 'label' => __( 'Gender', 'ravanix' ) ),
				'education' => array( 'enabled' => 0, 'required' => 0, 'label' => __( 'Education', 'ravanix' ) ),
				'mobile'    => array( 'enabled' => 0, 'required' => 0, 'label' => __( 'Mobile number', 'ravanix' ) ),
				'email'     => array( 'enabled' => 0, 'required' => 0, 'label' => __( 'Email address', 'ravanix' ) ),
				'age'       => array( 'enabled' => 0, 'required' => 0, 'label' => __( 'Age', 'ravanix' ) ),
			),
		);
	}

	public static function get() {
		$saved   = get_option( self::OPTION_KEY, array() );
		$merged  = wp_parse_args( $saved, self::defaults() );

		// Deep-merges the participant fields so a partially stored data structure
		// doesn't break things. Important note: the "label" is always read fresh
		// from defaults() (not from the stored value), since this text must be
		// translated according to the current request's language every time; if
		// we let it come from the database, whatever text was stored once (e.g.
		// in Persian, on the first settings save) would stay fixed forever and
		// never again be translated for an English-speaking visitor.
		$default_fields = self::defaults()['participant_fields'];
		$saved_fields    = isset( $saved['participant_fields'] ) && is_array( $saved['participant_fields'] ) ? $saved['participant_fields'] : array();
		foreach ( $default_fields as $key => $def ) {
			$merged['participant_fields'][ $key ] = wp_parse_args( $saved_fields[ $key ] ?? array(), $def );
			$merged['participant_fields'][ $key ]['label'] = $def['label'];
		}

		return $merged;
	}

	public static function get_field( $key ) {
		$settings = self::get();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Given a hex color, returns a version guaranteed to have at least
	 * $min_ratio contrast against white (WCAG 2's actual relative-luminance
	 * formula, not an HSL-lightness approximation -- different hues need
	 * different amounts of darkening to reach the same real contrast ratio).
	 * If the color already qualifies, it is returned completely unchanged.
	 *
	 * Used to turn an admin's freely-chosen "brand color" (Ravanix Settings ->
	 * Branding) into one that is always safe to use both as a background
	 * behind white button text AND, since WCAG contrast is symmetric
	 * (contrast(A,B) == contrast(B,A)), as the color's own foreground/text/icon
	 * use directly on a white-ish surface (question numbers, outlined/text
	 * buttons, etc.) -- one guarantee covers both front-end uses at once.
	 * See ravanix.php's enqueue_frontend_assets(), where this is applied to
	 * the --rs-brand custom property every MD3 color role in
	 * ravanix-frontend.css derives from.
	 *
	 * @param string $hex       e.g. '#4a6fa5'.
	 * @param float  $min_ratio WCAG AA for normal text is 4.5.
	 * @return string A hex color (possibly darkened; never lightened).
	 */
	public static function ensure_min_contrast_with_white( $hex, $min_ratio = 4.5 ) {
		$hex = sanitize_hex_color( $hex );
		if ( ! $hex ) {
			return '#4a6fa5';
		}

		list( $r, $g, $b ) = self::hex_to_rgb( $hex );
		if ( self::contrast_ratio_with_white( $r, $g, $b ) >= $min_ratio ) {
			return $hex; // Already accessible: the admin's exact chosen color is used unchanged.
		}

		// Progressively scale toward black (never toward white/more-saturated)
		// until the target ratio is reached; darkening a color only ever
		// increases its contrast against white, so this converges reliably.
		// 40 steps is far more than enough headroom between "just barely
		// failing" and pure black, and this runs once per page load at most
		// (not per request in a hot loop), so the cost is negligible.
		for ( $i = 1; $i <= 40; $i++ ) {
			$factor = 1 - ( $i / 40 );
			$dr     = (int) round( $r * $factor );
			$dg     = (int) round( $g * $factor );
			$db     = (int) round( $b * $factor );
			if ( self::contrast_ratio_with_white( $dr, $dg, $db ) >= $min_ratio ) {
				return sprintf( '#%02x%02x%02x', $dr, $dg, $db );
			}
		}
		return '#000000'; // Never actually reached in practice: black has ~21:1 contrast with white.
	}

	private static function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * WCAG 2's actual relative luminance formula (sRGB channels linearized via
	 * their gamma curve, then perceptually weighted -- green contributes far
	 * more to perceived brightness than blue, which is why this is
	 * meaningfully more accurate than a plain HSL-lightness check for
	 * deciding how much a given hue actually needs to be darkened).
	 */
	private static function relative_luminance( $r, $g, $b ) {
		$channel = function ( $c ) {
			$c = $c / 255;
			return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		};
		return 0.2126 * $channel( $r ) + 0.7152 * $channel( $g ) + 0.0722 * $channel( $b );
	}

	private static function contrast_ratio_with_white( $r, $g, $b ) {
		$l = self::relative_luminance( $r, $g, $b );
		return ( 1.0 + 0.05 ) / ( $l + 0.05 ); // White's own luminance is exactly 1.0.
	}

	/**
	 * Computes the correct on-primary text color for dark mode's primary,
	 * which ravanix-frontend.css derives as `color-mix(in srgb, var(--rs-brand)
	 * 65%, white)` -- i.e. deliberately lightened, matching MD3's own
	 * convention for a dark color scheme. This mirrors that exact blend in
	 * PHP, then measures real contrast against both black and white and
	 * returns whichever actually passes, rather than assuming -- a fixed
	 * percentage-blend on-primary measured at only ~2.6:1 in testing (see the
	 * matching comment in the CSS file), well under WCAG AA's 4.5:1.
	 *
	 * @param string $safe_hex Output of ensure_min_contrast_with_white(), i.e. the light-mode --rs-brand value.
	 * @return string A hex color for dark mode's --md-on-primary.
	 */
	public static function get_dark_mode_on_primary( $safe_hex ) {
		list( $r, $g, $b ) = self::hex_to_rgb( $safe_hex );
		// Simulates color-mix(in srgb, brand 65%, white): each channel moves
		// 35% of the way from its own value toward 255.
		$dr = (int) round( $r + ( 255 - $r ) * 0.35 );
		$dg = (int) round( $g + ( 255 - $g ) * 0.35 );
		$db = (int) round( $b + ( 255 - $b ) * 0.35 );

		$l                   = self::relative_luminance( $dr, $dg, $db );
		$contrast_with_white = self::contrast_ratio_with_white( $dr, $dg, $db );
		$contrast_with_black = ( $l + 0.05 ) / ( 0 + 0.05 ); // Black's own luminance is exactly 0.

		return $contrast_with_black >= $contrast_with_white ? '#1b1b1f' : '#ffffff';
	}

	/**
	 * Resolves the informed-consent text that actually applies to a given test:
	 * 'disabled' -> none; 'custom' -> the test's own text; 'default' (and any
	 * legacy/unknown value, for tests saved before this feature existed) ->
	 * the site-wide default. Used by both the frontend template (to render the
	 * consent block) and the AJAX submit handler (to enforce it server-side),
	 * so the two can never disagree about whether a given test requires consent.
	 *
	 * @param object $test The full test object (as returned by Ravanix_DB::get_full_test()).
	 * @return string The effective consent text (possibly empty, meaning no consent step).
	 */
	public static function get_effective_consent_text( $test ) {
		$mode = $test->consent_mode ?? 'default';
		if ( 'disabled' === $mode ) {
			return '';
		}
		if ( 'custom' === $mode ) {
			return $test->consent_text ?? '';
		}
		return self::get_field( 'consent_text' ) ?? '';
	}

	public static function update( $data ) {
		$current = self::get();
		$updated = wp_parse_args( $data, $current );
		update_option( self::OPTION_KEY, $updated );
		return $updated;
	}
}
