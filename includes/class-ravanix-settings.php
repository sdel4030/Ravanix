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

	public static function update( $data ) {
		$current = self::get();
		$updated = wp_parse_args( $data, $current );
		update_option( self::OPTION_KEY, $updated );
		return $updated;
	}
}
