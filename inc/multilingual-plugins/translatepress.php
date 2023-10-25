<?php
/**
 * File for hooks regarding the translatePress-plugin.
 *
 * @package easy-language
 */

use easyLanguage\helper;
use easyLanguage\Languages;
use easyLanguage\Multilingual_plugins\TranslatePress\Init;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the TranslatePress service in this plugin.
 *
 * @param array $plugin_list List of available multilingual-plugins.
 * @return array
 */
function easy_language_register_plugin_translate_press( array $plugin_list ): array {
	// bail if plugin is not active.
	if ( false === Helper::is_plugin_active( 'translatepress-multilingual/index.php' ) ) {
		return $plugin_list;
	}

	// get plugin-object and add it to list.
	$plugin_list[] = Init::get_instance();

	// return resulting list.
	return $plugin_list;
}
add_filter( 'easy_language_register_plugin', 'easy_language_register_plugin_translate_press' );

/**
 * Add our languages to list of all languages from translatepress.
 *
 * @param array $supported_languages_list List of supported languages.
 * @return array
 */
function easy_language_trp_add_to_wp_list( array $supported_languages_list ): array {
	// remove our own filter to prevent loops.
	remove_filter( 'trp_wp_languages', 'easy_language_trp_add_to_wp_list' );

	// get possible target-languages.
	$languages = Languages::get_instance()->get_possible_target_languages();

	// add them to the list.
	foreach ( $languages as $language_code => $language ) {
		if ( empty( $supported_languages_list[ $language['url'] ] ) ) {
			$supported_languages_list[ $language['url'] ] = array(
				'language'     => $language_code,
				'english_name' => $language['label'],
				'native_name'  => $language['label'],
				'iso'          => array(
					1 => $language_code,
				),
			);
		}
	}

	// re-add our own filter.
	add_filter( 'trp_wp_languages', 'easy_language_trp_add_to_wp_list' );

	// return resulting list.
	return $supported_languages_list;
}
add_filter( 'trp_wp_languages', 'easy_language_trp_add_to_wp_list' );

/**
 * Add our automatic machine as functions.
 *
 * @param array $api_list List of supported languages.
 * @return array
 */
function easy_language_trp_add_automatic_machine( array $api_list ): array {
	$api_list['summ-ai'] = 'easyLanguage\Multilingual_plugins\TranslatePress\Translatepress_Summ_Ai_Machine_Translator';
	return $api_list;
}
add_filter( 'trp_automatic_translation_engines_classes', 'easy_language_trp_add_automatic_machine' );

/**
 * Add the automatic machine to the list in translatePress-backend.
 *
 * @param array $engines List of supported simplify engines.
 * @return mixed
 */
function easy_language_trp_add_automatic_engine( array $engines ): array {
	$engines[] = array(
		'value' => 'summ-ai',
		'label' => __( 'SUMM AI', 'easy-language' ),
	);
	return $engines;
}
add_filter( 'trp_machine_translation_engines', 'easy_language_trp_add_automatic_engine', 30 );

/**
 * Truncate any simplifications for Leichte Sprache.
 *
 * @return void
 */
function easy_language_trp_reset_simplifications(): void {
	global $wpdb;
	$trp       = TRP_Translate_Press::get_trp_instance();
	$trp_query = $trp->get_component( 'query' );

	// get possible target-languages.
	$languages = Languages::get_instance()->get_possible_target_languages();

	// add them to the list.
	foreach ( $languages as $language_code => $language ) {
		// get table names.
		$wpdb->trp_table_name         = $trp_query->get_table_name( strtolower( $language_code ) );
		$wpdb->trp_gettext_table_name = $trp_query->get_gettext_table_name( strtolower( $language_code ) );

		// check if table exist.
		if ( ! empty( $wpdb->get_results( $wpdb->prepare( 'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = %s', array( $wpdb->trp_table_name ) ) ) ) ) {
			// truncate tables.
			$wpdb->query( sprintf( 'TRUNCATE TABLE %s', $wpdb->trp_table_name ) );
			$wpdb->query( sprintf( 'TRUNCATE TABLE %s', $wpdb->trp_gettext_table_name ) );
		}
	}
}

/**
 * Check for supported languages.
 *
 * @param bool  $all_are_available Whether all languages are available.
 * @param array $languages List of languages.
 * @param array $settings List of settings.
 * @return bool
 */
function easy_language_trp_get_supported_languages( bool $all_are_available, array $languages, array $settings ): bool {
	if ( 'summ-ai' === $settings['trp_machine_translation_settings']['translation-engine'] ) {
		// remove our own filter to prevent loop.
		remove_filter( 'trp_mt_available_supported_languages', 'easy_language_trp_get_supported_languages', 10, 3 );

		// get possible target-languages.
		$languages = Languages::get_instance()->get_possible_target_languages();

		// add them to the list.
		foreach ( $languages as $language_code => $language ) {
			if ( in_array( $language_code, $languages, true ) ) {
				// re-add our own filter.
				add_filter( 'trp_mt_available_supported_languages', 'easy_language_trp_get_supported_languages', 10, 3 );

				// return true as we detected that this language is supported.
				return true;
			}
		}

		// re-add our own filter.
		add_filter( 'trp_mt_available_supported_languages', 'easy_language_trp_get_supported_languages', 10, 3 );
	}
	return $all_are_available;
}
add_filter( 'trp_mt_available_supported_languages', 'easy_language_trp_get_supported_languages', 10, 3 );

/**
 * Add settings for our individual language for language-switcher in frontend.
 *
 * @param array  $current_language The current language.
 * @param array  $published_languages The list of published languages.
 * @param string $trp_language The translatePress-language.
 * @return array
 */
function easy_language_trp_set_current_language_fields( array $current_language, array $published_languages, string $trp_language ): array {
	// get possible target-languages.
	$languages = Languages::get_instance()->get_possible_target_languages();

	// add them to the list.
	foreach ( $languages as $language_code => $language ) {
		if ( $language_code === $trp_language ) {
			$current_language = array(
				'name' => $language['label'],
				'code' => $language_code,
			);
		}
	}

	// return resulting array.
	return $current_language;
}
add_filter( 'trp_ls_floating_current_language', 'easy_language_trp_set_current_language_fields', 10, 3 );

/**
 * Change path for our own language-flag.
 *
 * @param string $flags_path Path to the flags.
 * @param string $searched_language_code Checked language-code.
 * @return string
 */
function easy_language_set_flag( string $flags_path, string $searched_language_code ): string {
	// get possible target-languages.
	$languages = Languages::get_instance()->get_possible_target_languages();

	// add them to the list.
	foreach ( $languages as $language_code => $language ) {
		if ( $language_code === $searched_language_code ) {
			$flags_path = trailingslashit(dirname(Helper::get_icon_path_for_language_code( $language_code )));
		}
	}

	// return given path.
	return $flags_path;
}
add_filter( 'trp_flags_path', 'easy_language_set_flag', 10, 2 );
