<?php
/**
 * File for initialisation of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Init the plugin.
 * This object is minify on purpose as the main functions are handled in own objects
 * depending on WordPress-settings.
 */
class Multilingual_Plugins {


	/**
	 * Instance of this object.
	 *
	 * @var ?Multilingual_Plugins
	 */
	private static ?Multilingual_Plugins $instance = null;

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Multilingual_Plugins {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Return available multilingual plugin-supports.
	 *
	 * @return array
	 */
	public function get_available_plugins(): array {
		return apply_filters( 'easy_language_register_plugin', array() );
	}

	/**
	 * Return true, if other multilingual-plugin with enabled support for the given languages is active.
	 *
	 * @param array $languages
	 *
	 * @return bool
	 */
	public function is_plugin_with_support_for_given_languages_enabled( array $languages ): bool {
		foreach ( $this->get_available_plugins() as $plugin_obj ) {
			if ( 'easy-language' !== $plugin_obj->get_name() ) {
				foreach ( $plugin_obj->get_active_languages() as $language_code => $enabled ) {
					if ( ! empty( $languages[ $language_code ] ) ) {
						return true;
					}
				}
			}
		}

		// return false if no language could be found.
		return false;
	}
}
