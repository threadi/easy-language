<?php
/**
 * File to load plugins we support.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Handle the plugins support.
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
		$plugin_list = array();

		/**
		 * Filter the available plugins.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $plugin_list List of plugins.
		 */
		return apply_filters( 'easy_language_register_plugin', $plugin_list );
	}
}
