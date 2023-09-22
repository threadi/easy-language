<?php
/**
 * File for language-handling in this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

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
     * If an API is active, use their supported languages.
     * If no API is active, use settings.
	 *
	 * @return array
	 */
	public function get_active_languages(): array {
        // get active api.
        $api_obj = Apis::get_instance()->get_active_api();
        if( false !== $api_obj ) {
            // get the supported target languages of this api.
			return $api_obj->get_active_target_languages();
		}

        // if no API is active, get the list from plugin-settings.
        $list = array();
		$settings_language = array();
		foreach( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			$settings_language = $plugin_obj->get_supported_languages();
		}
        foreach( $settings_language as $language => $enabled ) {
            $list[$language] = $this->get_possible_target_languages()[$language];
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
		return array(
			'de_DE' => array(
				'label' => __( 'Deutsch', 'easy-language'),
				'enabled' => true
			),
			'de_DE-formal' => array(
				'label' => __( 'Deutsch (Sie)', 'easy-language'),
				'enabled' => true
			),
			'en_US' => array(
				'label' => __( 'English (US)', 'easy-language'),
				'enabled' => true
			),
		);
	}

    /**
     * Return possible target languages.
     *
     * @return array
     */
    public function get_possible_target_languages(): array {
        return apply_filters( 'easy_language_supported_target_languages', array(
	            'de_LS' => array(
	                'label' => __( 'Leichte Sprache', 'easy-language'),
	                'enabled' => true,
	                'description' => __( 'The Leichte Sprache used in Germany, Suisse and Austria.', 'easy-language'),
	                'url' => 'de_ls',
	            ),
	            'de_EL' => array(
		            'label' => __( 'Einfache Sprache', 'easy-language'),
		            'enabled' => true,
		            'description' => __( 'The Einfache Sprache used in Germany, Suisse and Austria.', 'easy-language'),
		            'url' => 'de_el',
	            )
	        )
        );
    }

	/**
	 * Return whether the given language is active or not.
	 *
	 * @param string $language
	 *
	 * @return bool
	 */
	public function is_language_active( string $language ): bool {
		$languages = $this->get_active_languages();
		return !empty($languages[$language]);
	}
}
