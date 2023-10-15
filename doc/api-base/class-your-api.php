<?php
/**
 * File for handler for things the Test Api supports.
 *
 * @package easy-language-test-apo
 */

namespace easyLanguage\Apis\Your_Api;

use easyLanguage\Base;
use easyLanguage\Api_Base;

/**
 * Define what SUMM AI supports and what not.
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
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
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
				'url' => 'fr_fr',
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
	 * Return active target languages.
	 *
	 * @return array
	 */
	public function get_active_target_languages(): array {
		// get actual enabled target-languages.
		$target_languages = get_option( 'easy_language_target_languages', array() );
		if( !is_array($target_languages) ) {
			$target_languages = array();
		}

		// define resulting list
		$list = array();

		foreach( $this->get_supported_target_languages() as $language_code => $language ) {
			if( !empty($target_languages[$language_code]) ) {
				$list[$language_code] = $language;
			}
		}

		return $list;
	}

	/**
	 * We to not add settings for this plugin.
	 *
	 * @param string $tab The tab.
	 * @return void
	 */
	public function add_settings_tab( $tab ): void {}

	/**
	 * We to not add settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {}

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
	 * @return Simplifications
	 */
	public function get_simplifications_obj(): Simplifications {
		// get the object.
		$obj = Simplifications::get_instance();

		// initialize it.
		$obj->init( $this );

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
	 * @return Request
	 */
	public function get_request_object() {
		return new Request();
	}
}
