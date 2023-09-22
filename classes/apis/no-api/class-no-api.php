<?php
/**
 * File for handler for things the No-API supports.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\No_Api;

use easyLanguage\Base;
use easyLanguage\Api_Base;
use easyLanguage\Helper;
use easyLanguage\Multilingual_Plugins;
use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use easyLanguage\Transients;
use WP_User;
use wpdb;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Define what SUMM AI supports and what not.
 */
class No_Api extends Base implements Api_Base {

	/**
	 * Set the internal name for the API.
	 *
	 * @var string
	 */
	protected string $name = 'no_ai';

	/**
	 * Set the public title for the API.
	 *
	 * @var string
	 */
	protected string $title = 'No API';

	/**
	 * Instance of this object.
	 *
	 * @var ?No_Api
	 */
	private static ?No_Api $instance = null;

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
	public static function get_instance(): No_Api {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Return the public description of the SUMM AI API.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( '<p>Do not use any API for translations.</p>', 'easy-language' );
	}

	public function get_supported_source_languages(): array {
		return array();
	}

	public function get_supported_target_languages(): array {
		return array();
	}

	public function get_active_target_languages(): array {
		return array();
	}

	public function get_mapping_languages(): array {
		return array();
	}

	public function add_settings_tab( $tab ): void {}

	public function add_settings_page(): void {}

	public function add_settings(): void {}

	/**
	 * Return whether this API is active regarding all its settings.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->get_name() === get_option( 'easy_language_api' );
	}

	public function install(): void {}

	public function uninstall(): void {}

	public function get_translations_obj(): object {
		return new \stdClass();
	}

	public function cli(): void {}
}
