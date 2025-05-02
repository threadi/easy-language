<?php
/**
 * File to add TranslatePress as supported multilingual plugin.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Apis;
use easyLanguage\Helper;
use easyLanguage\Languages;
use easyLanguage\Multilingual_plugins\TranslatePress\Init;
use easyLanguage\Multilingual_Plugins_Base;

/**
 * Register the TranslatePress service in this plugin.
 *
 * @param array<int,Multilingual_Plugins_Base> $plugin_list List of available multilingual-plugins.
 * @return array<int,Multilingual_Plugins_Base>
 */
function easy_language_register_plugin_translate_press( array $plugin_list ): array {
	// bail if plugin is not active.
	if ( false === Helper::is_plugin_active( 'translatepress-multilingual/index.php' ) ) {
		return $plugin_list;
	}

	// get plugin-object and add it to list.
	$plugin_list[] = Init::get_instance(); // @phpstan-ignore class.notFound

	// return resulting list.
	return $plugin_list;
}
add_filter( 'easy_language_register_plugin', 'easy_language_register_plugin_translate_press' );

/**
 * Add our languages to list of all languages from translatepress.
 *
 * @param array<string,array<string,mixed>> $supported_languages_list List of supported languages.
 * @return array<string,array<string,mixed>>
 */
function easy_language_trp_add_to_wp_list( array $supported_languages_list ): array {
	// remove our own filter to prevent loop.
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
 * @param array<string,string> $api_list List of supported languages.
 * @return array<string,string>
 */
function easy_language_trp_add_automatic_machine( array $api_list ): array {
	// get active API and add it if they support this plugin.
	$api_obj = Apis::get_instance()->get_active_api();

	// bail if no active API is set.
	if( ! $api_obj ) {
		return $api_list;
	}

	// bail if API does not support translatepress.
	if ( ! $api_obj->is_supporting_translatepress() ) {
		return $api_list;
	}

	// add the translatepress class to the list.
	$api_list[ $api_obj->get_name() ] = $api_obj->get_translatepress_machine_class();

	// return resulting list.
	return $api_list;
}
add_filter( 'trp_automatic_translation_engines_classes', 'easy_language_trp_add_automatic_machine' );

/**
 * Add the automatic machine to the list in translatePress-backend.
 *
 * @param array<int,array<string,string>> $engines List of supported simplify engines.
 * @return array<int,array<string,string>>
 */
function easy_language_trp_add_automatic_engine( array $engines ): array {
	// get active API and add it if they support this plugin.
	$api_obj = Apis::get_instance()->get_active_api();

	// bail if no active API is set.
	if( ! $api_obj ) {
		return $engines;
	}

	// bail if API does not support translatepress.
	if ( ! $api_obj->is_supporting_translatepress() ) {
		return $engines;
	}

	// add the engine settings.
	$engines[] = array(
		'value' => $api_obj->get_name(),
		'label' => $api_obj->get_title(),
	);

	// return the resulting list.
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

	// bail if class could not be loaded.
	if( is_null( $trp ) ) {
		return;
	}

	// get the query.
	$trp_query = $trp->get_component( 'query' );

	// get possible target-languages.
	$languages = Languages::get_instance()->get_possible_target_languages();

	// add them to the list.
	foreach ( $languages as $language_code => $language ) {
		// check if table exist.
		if ( ! empty( $wpdb->get_results( $wpdb->prepare( 'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = %s', array( $trp_query->get_table_name( strtolower( $language_code ) ) ) ) ) ) ) {
			// truncate tables.
			$wpdb->query( sprintf( 'TRUNCATE TABLE %s', esc_sql( $trp_query->get_table_name( strtolower( $language_code ) ) ) ) ); // @phpstan-ignore argument.type
			$wpdb->query( sprintf( 'TRUNCATE TABLE %s', esc_sql( $trp_query->get_gettext_table_name( strtolower( $language_code ) ) ) ) ); // @phpstan-ignore argument.type
		}
	}
}

/**
 * Check for supported languages.
 *
 * @param bool  $all_are_available Whether all languages are available.
 * @param array<string,string> $trp_languages List of languages in translatepress.
 * @param array<string,array<string,mixed>> $settings List of settings.
 * @return bool
 */
function easy_language_trp_get_supported_languages( bool $all_are_available, array $trp_languages, array $settings ): bool {
	if ( in_array( $settings['trp_machine_translation_settings']['translation-engine'], array( 'summ-ai', 'capito' ), true ) ) {
		// remove our own filter to prevent loop.
		remove_filter( 'trp_mt_available_supported_languages', 'easy_language_trp_get_supported_languages' );

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
 * @param array<string>  $current_language The current language.
 * @param array<string>  $trp_published_languages The list of published languages.
 * @param string $trp_language The translatePress-language.
 * @return array<string>
 */
function easy_language_trp_set_current_language_fields( array $current_language, array $trp_published_languages, string $trp_language ): array {
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
			$flags_path = trailingslashit( dirname( Helper::get_icon_path_for_language_code( $language_code ) ) );
		}
	}

	// return given path.
	return $flags_path;
}
add_filter( 'trp_flags_path', 'easy_language_set_flag', 10, 2 );
