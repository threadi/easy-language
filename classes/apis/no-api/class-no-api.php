<?php
/**
 * File for handler for things the No-API supports.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\No_Api;

use easyLanguage\Base;
use easyLanguage\Api_Base;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define what No-API supports and what not.
 */
class No_Api extends Base implements Api_Base {

	/**
	 * Set the internal name for the API.
	 *
	 * @var string
	 */
	protected string $name = 'no_api';

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
	 * Return the public description of this API.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( '<p>This is a pseudo-API.<br>It does not use any API for simplifications.</p><p>Use this "API" if you do not want to use any other API.<br>You will be able to write your own texts in Leichte and Einfache Sprache.</p><p><strong>No automatic simplifications, no quota, no costs.</strong></p>', 'easy-language' );
	}

	/**
	 * Return list of supported source-languages.
	 *
	 * @return array
	 * @noinspection DuplicatedCode
	 */
	public function get_supported_source_languages(): array {
		return array(
			'de_DE'          => array(
				'label'       => __( 'German', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in Germany.', 'easy-language' ),
			),
			'de_DE_formal'   => array(
				'label'       => __( 'German (Formal)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Formal german spoken in Germany.', 'easy-language' ),
			),
			'de_CH'          => array(
				'label'       => __( 'Suisse german', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Formal german spoken in Suisse.', 'easy-language' ),
			),
			'de_CH_informal' => array(
				'label'       => __( 'Suisse german (Informal)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in Suisse.', 'easy-language' ),
			),
			'de_AT'          => array(
				'label'       => __( 'Austria German', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'German spoken in Austria.', 'easy-language' ),
			),
			'en_UK'          => array(
				'label'       => __( 'English (UK)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'English spoken in the United Kingdom.', 'easy-language' ),
				'api_value'   => 'en',
			),
			'en_US'          => array(
				'label'       => __( 'English (US)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'English spoken in the USA.', 'easy-language' ),
				'api_value'   => 'en',
			),
		);
	}

	/**
	 * Return target languages.
	 *
	 * @return array
	 */
	public function get_supported_target_languages(): array {
		return array();
	}

	/**
	 * Return active target languages.
	 *
	 * @return array
	 */
	public function get_active_target_languages(): array {
		return array();
	}

	/**
	 * Set mapping languages.
	 *
	 * @return array[]
	 */
	public function get_mapping_languages(): array {
		return array(
			'de_DE' => array( 'de_EL' ),
		);
	}

	/**
	 * Add settings tab: none for this API.
	 *
	 * @param string $tab The tab internal name.
	 *
	 * @return void
	 */
	public function add_settings_tab( string $tab ): void {}

	/**
	 * Add settings page: none for this API.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {}

	/**
	 * Add settings: none for this API.
	 *
	 * @return void
	 */
	public function add_settings(): void {}

	/**
	 * Return whether this API is active regarding all its settings.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->get_name() === get_option( 'easy_language_api' );
	}

	/**
	 * Run installer-tasks.
	 *
	 * @return void
	 */
	public function install(): void {}

	/**
	 * Deactivate-routines for the API, called during plugin-deactivation.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// nothing to do.
	}

	/**
	 * Run uninstaller tasks.
	 *
	 * @return void
	 */
	public function uninstall(): void {}

	/**
	 * Get simplification object for this API: none for this one.
	 *
	 * @return \stdClass
	 */
	public function get_simplifications_obj(): object {
		return new \stdClass();
	}

	/**
	 * Add cli options: none for this API.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return API URL.
	 *
	 * @return string
	 */
	public function get_api_url(): string {
		return '';
	}

	/**
	 * Enable-routines for the API, called on the new API if another API is chosen.
	 *
	 * @return void
	 */
	public function enable(): void {
		// nothing to do.
	}
}
