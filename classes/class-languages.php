<?php
/**
 * File for language-handling in this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Language-Handling for this plugin.
 */
class Languages {
	/**
	 * Instance of this object.
	 *
	 * @var ?Languages
	 */
	private static ?Languages $instance = null;

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {
	}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Languages {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Get actual supported languages.
	 *
	 * If an API is active, use their supported languages if they can be configured.
	 * If no API is active, use plugin settings.
	 *
	 * @return array
	 */
	public function get_active_languages(): array {
		// get active api.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( false !== $api_obj && $api_obj->has_settings() ) {
			// get the supported target languages of this api if it has settings for it.
			return $api_obj->get_active_target_languages();
		}

		// if no API with own settings is active, get the list from plugin-settings.
		$list              = array();
		$settings_language = array();
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			$settings_language = $settings_language + $plugin_obj->get_supported_languages();
		}
		foreach ( $settings_language as $language => $enabled ) {
			$list[ $language ] = $this->get_possible_target_languages()[ $language ];
		}

		// return the list from setting it API is not active.
		return $list;
	}

	/**
	 * Return possible source languages.
	 *
	 * @return array
	 */
	public function get_possible_source_languages(): array {
		return apply_filters(
			'easy_language_possible_source_languages',
			array(
				'de_DE'          => array(
					'label'   => __( 'German', 'easy-language' ),
					'enabled' => true,
					'icon' => 'de_icon_url'
				),
				'de_DE_formal'   => array(
					'label'   => __( 'German (formal)', 'easy-language' ),
					'enabled' => true,
					'icon' => 'de_icon_url'
				),
				'de_AT'          => array(
					'label'   => __( 'German (Austria)', 'easy-language' ),
					'enabled' => true,
					'icon' => 'at_icon_url'
				),
				'de_CH'          => array(
					'label'   => __( 'German (Suisse)', 'easy-language' ),
					'enabled' => true,
					'icon' => 'ch_icon_url'
				),
				'de_CH_informal' => array(
					'label'   => __( 'German (Suisse, informal)', 'easy-language' ),
					'enabled' => true,
					'icon' => 'ch_icon_url'
				),
			)
		);
	}

	/**
	 * Return possible target languages.
	 *
	 * @return array
	 */
	public function get_possible_target_languages(): array {
		return apply_filters(
			'easy_language_supported_target_languages',
			array(
				'de_EL' => array(
					'label'       => __( 'Einfache Sprache', 'easy-language' ),
					'enabled'     => true,
					'description' => '', // __( 'The Einfache Sprache used in Germany, Suisse and Austria.', 'easy-language'),
					'url'         => 'de_el',
					'icon' => 'icon-de-el',
					'img' => 'de_EL.png',
					'img_icon' => Helper::get_icon_img_for_language_code( 'de_EL' )
				),
				'de_LS' => array(
					'label'       => __( 'Leichte Sprache', 'easy-language'),
					'enabled'     => true,
					'description' => '', // __( 'The Leichte Sprache used in Germany, Suisse and Austria.', 'easy-language'),
					'url'         => 'de_ls',
					'icon' => 'icon-de-ls',
					'img' => 'de_LS.png',
					'img_icon' => Helper::get_icon_img_for_language_code( 'de_LS' )
				),
			)
		);
	}
}
