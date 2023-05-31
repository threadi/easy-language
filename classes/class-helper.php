<?php
/**
 * File with general helper-functions for this plugin.
 *
 * @package lw-easy-language
 */

namespace easyLanguage;

/**
 * Helper for this plugin.
 */
class Helper {

	/**
	 * Checks whether a given plugin is active.
	 *
	 * Used because WP's own function is_plugin_active() is not accessible everywhere.
	 *
	 * @param string $plugin Path to the plugin.
	 * @return bool
	 */
	public static function is_plugin_active( string $plugin ): bool {
		return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true );
	}

	/**
	 * Get list of supported languages.
	 *
	 * @return array
	 */
	public static function get_supported_languages(): array {
		return array( 'de_ls', 'en_ls', 'fr_ls' );
	}

	/**
	 * Get data of a requested and supported language.
	 *
	 * @param string $language_code The language-code.
	 *
	 * @return array
	 */
	public static function get_language_data_by_code( string $language_code ): array {
		$array = array(
			'de_ls' => array(
				'short'                => 'ls',
				'label'                => 'Leichte Sprache',
				'html'                 => 'de-DE',
				'translatable_sources' => array(
					'de_at',
					'de_de',
					'de_ch',
				),
			),
			'en_ls' => array(
				'short'                => 'ls',
				'label'                => 'Easy Language',
				'html'                 => 'en-US',
				'translatable_sources' => array(
					'en_US',
				),
			),
			'fr_ls' => array(
				'short'                => 'ls',
				'label'                => 'FALC',
				'html'                 => 'fr-FR',
				'translatable_sources' => array(
					'fr_FR',
				),
			),
		);

		// return the data of the requestes language.
		if ( ! empty( $array[ $language_code ] ) ) {
			return $array[ $language_code ];
		}

		// return empty array.
		return array();
	}

	/**
	 * Get the active language in frontend.
	 *
	 * @return string
	 */
	public static function get_active_language(): string {
		if ( is_admin() ) {
			return '';
		}

		// get language from parameter.
		$lang = get_query_var( 'lang' );

		// if there is no language in query-var, use the WP-locale.
		if ( empty( $lang ) ) {
			$lang = helper::get_wp_lang();
		}

		// return language.
		return $lang;
	}

	/**
	 * Return the active Wordpress-language depending on our own support.
	 * If language is unknown for our plugin, use english.
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public static function get_wp_lang(): string {
		$wp_lang = get_bloginfo( 'language' );

		/**
		 * Consider the main language set in Polylang for the web page
		 */
		if ( self::is_plugin_active( 'polylang/polylang.php' ) && function_exists( 'pll_default_language' ) ) {
			$wp_lang = pll_default_language();
		}

		/**
		 * Consider the main language set in WPML for the web page
		 */
		if ( self::is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			$wp_lang = apply_filters( 'wpml_current_language', null );
		}

		// if language not set, use default language.
		if ( empty( $wp_lang ) ) {
			$wp_lang = EASY_LANGUAGE_LANGUAGE_EMERGENCY;
		}

		// return language in format ab_CD (e.g. en_US).
		return str_replace( '-', '_', $wp_lang );
	}

	/**
	 * Return URL of our plugin.
	 *
	 * @return string
	 */
	public static function get_plugin_url(): string {
		return plugin_dir_url( EASY_LANGUAGE );
	}
}
