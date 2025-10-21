<?php
/**
 * File to load other plugins we support.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Handle the plugins support.
 */
class ThirdPartySupports {

	/**
	 * Instance of this object.
	 *
	 * @var ?ThirdPartySupports
	 */
	private static ?ThirdPartySupports $instance = null;

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
	public static function get_instance(): ThirdPartySupports {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return available multilingual plugin-supports.
	 *
	 * @return array<int,ThirdPartySupport_Base>
	 */
	public function get_available_plugins(): array {
		// create the list of plugins.
		$plugin_list = array();
		foreach ( $this->get_plugins() as $plugin_class_name ) {
			// create the classname.
			$classname = $plugin_class_name . '::get_instance';

			// bail if classname is not callable.
			if ( ! is_callable( $classname ) ) {
				continue;
			}

			// get the object.
			$obj = $classname();

			// bail if the object is not the handler base.
			if ( ! $obj instanceof ThirdPartySupport_Base ) {
				continue;
			}

			// add this object to the list.
			$plugin_list[] = $obj;
		}

		// return the list.
		return $plugin_list;
	}

	/**
	 * Return list of available third party plugins.
	 *
	 * @return array<int,string>
	 */
	private function get_plugins(): array {
		// create the list of plugins.
		$plugins = array(
			'easyLanguage\ThirdPartySupport\Polylang\Polylang',
			'easyLanguage\ThirdPartySupport\Sublanguage\Sublanguage',
			'easyLanguage\ThirdPartySupport\TranslatePress\TranslatePress',
			'easyLanguage\ThirdPartySupport\Wpml\Wpml',
		);

		/**
		 * Filter the list of third party plugins.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array<int,string> $plugins List of plugins.
		 */
		return apply_filters( 'easy_language_third_party_plugins', $plugins );
	}
}
