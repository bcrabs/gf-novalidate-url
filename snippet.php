<?php
/**
 * Gravity Forms: auto-prefix "https://" on Website fields.
 *
 * Gravity Forms rejects a Website (URL) field value that does not start with
 * http:// or https:// (see GFCommon::is_valid_url() in common.php). This snippet
 * runs right before validation and prefixes "https://" to any Website field value
 * that was submitted without a URL scheme, so "example.com" validates and is
 * saved as "https://example.com".
 *
 * Tested with Gravity Forms 3.2.1. Requires Gravity Forms 1.9+ (GF_Field objects).
 *
 * Install as a mu-plugin, in the Code Snippets plugin, or in your theme's
 * functions.php (drop the opening <?php line when pasting into an existing file).
 *
 * @package   CRFTD\GF_Website_Scheme
 * @author    CRFTD <https://crftd.dev>
 * @link      https://github.com/bcrabs/gf-novalidate-url
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prefix "https://" to Website field values submitted without a URL scheme.
 *
 * Hooked to gform_pre_validation so the corrected value is what Gravity Forms
 * validates (GF_Field_Website::validate) and saves (GF_Field_Website::get_value_save_input).
 * Runs on standard, AJAX, multi-page, and GFAPI/REST API v2 submissions, since
 * all of them go through GFFormDisplay::validate().
 *
 * @param array $form The form object being validated.
 * @return array The form object, unchanged.
 */
function crftd_gf_prefix_website_scheme( $form ) {
	if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
		return $form;
	}

	foreach ( $form['fields'] as $field ) {
		// Object syntax only: array access on GF_Field has been deprecated since GF 2.0.
		if ( ! $field instanceof GF_Field || 'website' !== $field->type ) {
			continue;
		}

		$input_name = 'input_' . (int) $field->id;
		$value      = rgpost( $input_name );

		if ( ! is_string( $value ) ) {
			continue;
		}

		$value = trim( $value );

		// Empty is Gravity Forms' problem (required check), not ours.
		if ( '' === $value ) {
			continue;
		}

		// Already has a scheme (http://, https://, ftp://, etc.)? Leave it alone.
		// Gravity Forms will still reject anything other than http(s), which is correct.
		if ( preg_match( '~^[a-z][a-z0-9+.-]*://~i', $value ) ) {
			continue;
		}

		// Protocol-relative "//example.com" becomes "example.com" before prefixing.
		$value = ltrim( $value, '/' );

		// Write back to $_POST: Gravity Forms reads submitted values from there
		// during validation and when saving the entry.
		$_POST[ $input_name ] = 'https://' . $value;
	}

	return $form;
}
add_filter( 'gform_pre_validation', 'crftd_gf_prefix_website_scheme' );
