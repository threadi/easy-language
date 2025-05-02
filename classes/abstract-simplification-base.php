<?php
/**
 * File for simplification base entities.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Base-object for API- and plugin-main-classes.
 */
abstract class Simplification_Base {
	/**
	 * The API-object.
	 *
	 * @var Api_Base|null
	 */
	protected null|Api_Base $api = null;

	/**
	 * Return the API object.
	 *
	 * If no API is set, it returns the object of the NoApi-API.
	 *
	 * @return Api_Base
	 */
	public function get_api(): Api_Base {
		if ( is_null( $this->api ) ) {
			$this->api = Apis\No_Api\No_Api::get_instance();
		}
		return $this->api;
	}

	/**
	 * Initialize this object.
	 *
	 * @param Api_Base $api_obj The base-object.
	 * @return void
	 */
	public function set_api( Api_Base $api_obj ): void {
		$this->api = $api_obj;
	}
}
