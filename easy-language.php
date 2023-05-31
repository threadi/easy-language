<?php
/**
 * Plugin Name:       Easy Language
 * Description:       Provides easy language for several multilingual plugins.
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Version:           @@VersionNumber@@
 * Author:            laOlaWeb
 * Author URI:        https://laolaweb.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       easy-language
 *
 * @package easy-language
 */

use easyLanguage\Helper;

/**
 * Constants.
 */
const EASY_LANGUAGE = __FILE__;

// embed necessary files.
require_once 'inc/autoload.php';

/**
 * Fallback-language.
 */
const EASY_LANGUAGE_LANGUAGE_EMERGENCY = 'en_US';

/**
 * Initialize the plugin on every request.
 *
 * @return void
 */
function easy_language_init(): void {
	load_plugin_textdomain( 'easy-language', false, dirname( plugin_basename( EASY_LANGUAGE ) ) . '/languages' );
}
add_action( 'init', 'easy_language_init' );

/**
 * Initialize WPML-Support.
 *
 * @return void
 */
function easy_language_wpml_init(): void {
	// bail if WPML is not available.
	if ( ! class_exists( 'WPML_Flags_Factory' ) ) {
		return;
	}

	global $wpdb;

	// loop through our supported easy languages.
	foreach ( helper::get_supported_languages() as $language_code ) {
		// get language-data.
		$language_data = helper::get_language_data_by_code( $language_code );
		if ( empty( $language_data ) ) {
			continue;
		}

		// check if the language does already exist in wpml-db.
		$result = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'icl_languages WHERE code = %s', $language_code ) );

		// no => than add it.
		if ( empty( $result ) ) {
			// copy flag in uploads-directory for WPML-flags.
			$wp_upload_dir = wp_upload_dir();
			$base_path     = $wp_upload_dir['basedir'] . '/';
			$path          = 'flags/';
			if ( ! file_exists( $base_path . $path . 'ls.png' ) ) {
				copy( plugin_dir_path( __FILE__ ) . 'gfx/ls.png', $base_path . $path . 'ls.png' );
			}

			// now add the language.
			$flags_factory      = new WPML_Flags_Factory( $wpdb );
			$icl_edit_languages = new SitePress_EditLanguages( $flags_factory->create() );
			$data               = array(
				'code'           => $language_code,
				'english_name'   => $language_data['label'],
				'default_locale' => $language_code,
				'encode_url'     => 0,
				'tag'            => $language_code,
				'translations'   => array(),
				'flag'           => 'ls.png',
				'flag_upload'    => true,
			);
			$icl_edit_languages->insert_one( $data );

			if ( ! empty( $language_data['translatable_sources'] ) ) {
				foreach ( $language_data['translatable_sources'] as $translatable_source ) {
					$data = array(
						'language_code'         => $translatable_source,
						'display_language_code' => $translatable_source,
						'name'                  => $translatable_source,
					);
					$wpdb->insert( $wpdb->prefix . 'icl_languages_translations', $data );
				}
			}
		}
	}
}
add_action( 'admin_init', 'easy_language_wpml_init' );

/**
 * Add new predefined language for Polylang.
 *
 * @param array $languages List of languages.
 * @return array
 */
function easy_language_polylang_add_predefined_language( array $languages ): array {
	// loop through our supported easy languages.
	foreach ( helper::get_supported_languages() as $language_code ) {
		// get language-data.
		$language_data = helper::get_language_data_by_code( $language_code );
		if ( empty( $language_data ) ) {
			continue;
		}

		// add to list.
		$languages[ $language_code ] = array(
			'code'     => $language_data['short'],
			'locale'   => $language_code,
			'name'     => $language_data['label'],
			'dir'      => 'ltr',
			'flag'     => $language_data['short'],
			'facebook' => $language_code,
		);
	}

	// return resulting list.
	return $languages;
}
add_filter( 'pll_predefined_languages', 'easy_language_polylang_add_predefined_language' );

/**
 * Set our own flag for easy language in Polylang.
 *
 * @param array  $flag The data of the flag.
 * @param string $code The language-code.
 *
 * @return array
 */
function easy_language_polylang_flag( array $flag, string $code ): array {
	// short-return if it is not our own language-code.
	if ( 'ls' !== $code ) {
		return $flag;
	}

	global $wp_filesystem;

	// set URL.
	$flag['url'] = plugins_url( 'gfx/ls.png', __FILE__ );

	// get file for base64.
	$file = plugin_dir_path( __FILE__ ) . 'gfx/ls.png';
	WP_Filesystem();
	$file_contents = $wp_filesystem->get_contents( $file );

	// return attribute if file-content is empty.
	if ( empty( $file_contents ) ) {
		return $flag;
	}

	// set src for flag.
	$flag['src'] = 'data:image/png;base64,' . base64_encode( $file_contents );

	// return result.
	return $flag;
}
add_filter( 'pll_flag', 'easy_language_polylang_flag', 10, 2 );

/**
 * Add easy language to list of all languages from WP.
 *
 * Is unfortunately necessary to prevent an error message at Polylang regarding languages not available at WordPress.
 *
 * @param array $list List of supported languages.
 *
 * @return array
 */
function easy_language_trp_add_to_wp_list( array $list ): array {
	// loop through our supported easy languages.
	foreach ( helper::get_supported_languages() as $language_code ) {
		// get language-data.
		$language_data = helper::get_language_data_by_code( $language_code );
		if ( empty( $language_data ) ) {
			continue;
		}

		$list[ $language_code ] = array(
			'language'     => $language_code,
			'english_name' => $language_data['label'],
			'native_name'  => $language_data['label'],
			'iso'          => array(
				$language_code,
			),
		);
	}

	// return resulting list.
	return $list;
}
add_filter( 'trp_wp_languages', 'easy_language_trp_add_to_wp_list' );

/**
 * Add easy language-translations and -settings in WP-Core.
 *
 * Is unfortunately necessary to prevent an error message at Polylang regarding languages not available at WordPress.
 *
 * @param array|bool $language_list The list of languages.
 *
 * @return array
 */
function easy_language_add_available_translations( array|bool $language_list ): array {
	// set array if not set atm.
	if ( ! is_array( $language_list ) ) {
		$language_list = array();
	}

	// loop through our supported easy languages.
	foreach ( helper::get_supported_languages() as $language_code ) {
		// get language-data.
		$language_data = helper::get_language_data_by_code( $language_code );
		if ( empty( $language_data ) ) {
			continue;
		}

		// add easy language to WP-own list.
		$language_list[ $language_code ] = array(
			'language'     => $language_code,
			'english_name' => $language_data['label'],
			'native_name'  => $language_data['label'],
			'iso'          => array(
				$language_code,
				$language_data['short'],
			),
		);
	}

	// return resulting list.
	return $language_list;
}
add_filter( 'site_transient_available_translations', 'easy_language_add_available_translations' );

/**
 * Add easy language-translations and -settings in WP-Core after API-request.
 *
 * @param array|WP_Error $res Array with response from API.
 * @param string         $type Requested type.
 *
 * @return array
 */
function easy_language_add_available_translations_after_api_request( array|WP_Error $res, string $type ): array {
	// do nothing on error.
	if ( is_wp_error( $res ) ) {
		return $res;
	}

	// do nothing if type is not core.
	if ( 'core' !== $type ) {
		return $res;
	}

	// add our languages.
	if ( ! empty( $res['translations'] ) ) {
		$res['translations'] = easy_language_add_available_translations( $res['translations'] );
	}

	// return resulting list.
	return $res;
}
add_filter( 'translations_api_result', 'easy_language_add_available_translations_after_api_request', 10, 2 );

/**
 * Change html-language-attribut if one of our supported languages is used.
 *
 * For de_ls it should be de-DE.
 * For en_ls it should be en-US.
 * For fr_ls it should be fr-FR.
 *
 * @param string $output The prepared output.
 * @param string $doctype The used doctype.
 *
 * @return string
 */
function easy_language_language_attributes( string $output, string $doctype ): string {
	// return generated output if doctype is not html.
	if ( 'html' !== $doctype ) {
		return $output;
	}

	// get active language from active multilingual-plugin.
	$active_language = helper::get_active_language();
	if ( 'ls' === $active_language ) {
		return 'lang="de-DE"';
	}

	// get language-data.
	$language_data = helper::get_language_data_by_code( $active_language );
	if ( ! empty( $language_data ) ) {
		return 'lang="' . $language_data['html'] . '"';
	}

	// return output.
	return $output;
}
add_filter( 'language_attributes', 'easy_language_language_attributes', 10, 2 );

/**
 * Change path for our own language-flag.
 *
 * @param string $flags_path Path to the flags.
 * @param string $language_code Checked language-code.
 * @return string
 */
function easy_language_set_flag( string $flags_path, string $language_code ): string {
	if ( in_array( $language_code, helper::get_supported_languages(), true ) ) {
		return helper::get_plugin_url() . 'gfx/';
	}
	return $flags_path;
}
add_filter( 'trp_flags_path', 'easy_language_set_flag', 10, 2 );
