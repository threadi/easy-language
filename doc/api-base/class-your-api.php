<?php
/**
 * File for handler for things the Test Api supports.
 *
 * @package easy-language-test-api
 */

namespace easyLanguage\Apis\Your_Api;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Api_Base;
use easyLanguage\Plugin\Api_Requests;
use easyLanguage\Plugin\Api_Simplifications;
use easyLanguage\Plugin\Base;

/**
 * Define what Your API supports and what not.
 */
class Your_Api extends Base implements Api_Base {

	/**
	 * Set the internal name for the API.
	 *
	 * @var string
	 */
	protected string $name = 'test_api';

	/**
	 * Set the public title for the API.
	 *
	 * @var string
	 */
	protected string $title = 'Test API';

	/**
	 * Instance of this object.
	 *
	 * @var ?Your_Api
	 */
	private static ?Your_Api $instance = null;

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
	public static function get_instance(): Your_Api {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return list of supported source-languages.
	 *
	 * @return array
	 */
	public function get_supported_source_languages(): array {
		return array(
			'de_DE' => array(
				'label' => __( 'German', 'easy-language'),
				'enable' => true,
				'description' => __( 'Informal german spoken in Germany.', 'easy-language')
			),
			'de_DE_formal' => array(
				'label' => __( 'German (Formal)', 'easy-language'),
				'enable' => true,
				'description' => __( 'Formal german spoken in Germany.', 'easy-language')
			),
			'de_CH' => array(
				'label' => __( 'Suisse german', 'easy-language'),
				'enable' => true,
				'description' => __( 'Formal german spoken in Suisse.', 'easy-language')
			),
			'de_CH_informal' => array(
				'label' => __( 'Suisse german (Informal)', 'easy-language'),
				'enable' => true,
				'description' => __( 'Informal german spoken in Suisse.', 'easy-language')
			),
			'de_AT' => array(
				'label' => __( 'Austria German', 'easy-language'),
				'enable' => true,
				'description' => __( 'German spoken in Austria.', 'easy-language')
			),
			'en_GB' => array(
				'label' => __( 'British English', 'easy-language'),
				'enable' => true,
				'description' => __( 'Englisch spoken in Great Britain.', 'easy-language')
			),
			'en_US' => array(
				'label' => __( 'American English', 'easy-language'),
				'enable' => true,
				'description' => __( 'Englisch spoken in the USA.', 'easy-language')
			),
			'fr_FR' => array(
				'label' => __( 'French', 'easy-language'),
				'enable' => true,
				'description' => __( 'French spoken in France.', 'easy-language')
			)
		);
	}

	/**
	 * Return the languages this API supports.
	 *
	 * @return array
	 */
	public function get_supported_target_languages(): array {
		return array(
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
			),
			'en_EL' => array(
				'label' => __( 'easy-to-read', 'easy-language'),
				'enabled' => true,
				'description' => __( 'The easy language used in englisch-based countries.', 'easy-language'),
				'url' => 'en_el',
			),
			'fr_FA' => array(
				'label' => __( 'FALC', 'easy-language'),
				'enabled' => true,
				'description' => __( 'The FALC used in french-based countries.', 'easy-language'),
				'url' => 'fr_fa',
			),
		);
	}

	/**
	 * Return the list of supported languages which could be translated with this API into each other.
	 *
	 * Left source, right possible target languages.
	 *
	 * @return array
	 */
	public function get_mapping_languages(): array {
		return array(
			'de_DE' => array( 'de_LS', 'de_EL' ),
			'en_US' => array( 'en_EL' ),
			'en_GB' => array( 'en_EL' ),
			'fr_FR' => array( 'fr_FA' )
		);
	}

	/**
	 * Return whether this API is active regarding all its settings.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->get_name() === get_option( 'easy_language_api' );
	}

	/**
	 * Return whether this API is configured and could be used for simplifications.
	 *
	 * This example API needs no configuration (e.g., no API key), so it is always configured.
	 * Without this method the API would only be configured if it has settings (see Base::is_configured()).
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return true;
	}

	/**
	 * Install-routines for the API, called during plugin-activation and API-change.
	 *
	 * @return void
	 */
	public function install(): void {}

	/**
	 * Install-routines for the API, called during plugin-activation and API-change.
	 *
	 * @return void
	 */
	public function uninstall(): void {}

	/**
	 * Return active source languages.
	 *
	 * This example API uses all supported source languages.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_active_source_languages(): array {
		return $this->get_supported_source_languages();
	}

	/**
	 * Return active target languages.
	 *
	 * This example API uses all supported target languages.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_active_target_languages(): array {
		return $this->get_supported_target_languages();
	}

	/**
	 * We to not add settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings(): void {}

	/**
	 * We add no cli commands for this API.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return the simplification-object.
	 *
	 * @return Api_Simplifications
	 */
	public function get_simplifications_obj(): Api_Simplifications {
		// get the object.
		$obj = Simplifications::get_instance();

		// set the API.
		$obj->set_api( $this );

		// return resulting object.
		return $obj;
	}

	/**
	 * Return API URL.
	 *
	 * @return string
	 */
	public function get_api_url(): string {
		return '';
	}

	/**
	 * Return request-object for this API.
	 *
	 * @return Api_Requests
	 */
	public function get_request_object(): Api_Requests {
		return new Request();
	}

	/**
	 * Deactivate-routines for the API, called during plugin-deactivation.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// tasks to run on deactivation of this API.
	}

	/**
	 * Enable-routines for the API, called on the new API if another API is chosen.
	 *
	 * @return void
	 */
	public function enable(): void {
		// tasks to run on activation of this API.
	}

	/**
	 * Return language-specific request text for the API.
	 *
	 * @param string $target_language The target-language.
	 *
	 * @return string
	 */
	public function get_request_text_by_language( string $target_language ): string {
		return '';
	}
}
