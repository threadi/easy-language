<?php
/**
 * File for API-handling in this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Define what SUMM AI supports and what not.
 */
class Apis {

	/**
	 * Instance of this object.
	 *
	 * @var ?Apis
	 */
	private static ?Apis $instance = null;

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Apis {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Return available APIs for translations with this plugin.
	 *
	 * @return array
	 */
	public function get_available_apis(): array {
		return apply_filters('easy_language_register_api', array() );
	}

	/**
	 * Return only active APIs. Should be one.
	 *
	 * @return false|Api_Base
	 */
	public function get_active_api(): false|Api_Base {
		foreach( $this->get_available_apis() as $api_obj ) {
			if( $api_obj->is_active() ) {
				return $api_obj;
			}
		}
		return false;
	}

	/**
	 * Get API-object by its name,
	 *
	 * @param string $name The name of the requested API.
	 *
	 * @return false|Api_Base
	 */
	public function get_api_by_name( string $name ): false|Api_Base {
		foreach( $this->get_available_apis() as $api_obj ) {
			if( $name === $api_obj->get_name() ) {
				return $api_obj;
			}
		}
		return false;
	}

}
