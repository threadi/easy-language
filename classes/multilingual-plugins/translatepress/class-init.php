<?php
/**
 * File for initializing the easy-language-own simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\TranslatePress;

use easyLanguage\Base;
use easyLanguage\Multilingual_Plugins_Base;
use easyLanguage\Transients;
use TRP_Translate_Press;

/**
 * Rewrite-Handling for this plugin.
 */
class Init extends Base implements Multilingual_Plugins_Base {
	/**
	 * Marker for API-support.
	 *
	 * @var bool
	 */
	protected bool $supports_apis = true;

	/**
	 * Marker if plugin has own API-configuration.
	 *
	 * @var bool
	 */
	protected bool $has_own_api_config = true;

	/**
	 * Name of this plugin.
	 *
	 * @var string
	 */
	protected string $name = 'translatepress';

	/**
	 * Title of this plugin.
	 *
	 * @var string
	 */
	protected string $title = 'TranslatePress';

	/**
	 * Instance of this object.
	 *
	 * @var ?Init
	 */
	private static ?Init $instance = null;

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
	public static function get_instance(): Init {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'deactivate_translatepress-multilingual/index.php', array( $this, 'foreign_deactivate' ) );
	}

	/**
	 * Initialize our main CLI-functions.
	 *
	 * @return void
	 * @noinspection PhpUndefinedClassInspection
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 */
	public function cli(): void {
		\WP_CLI::add_command( 'easy-language', 'easyLanguage\Multilingual_plugins\TranslatePress\Cli' );
	}

	/**
	 * Run on plugin-installation.
	 *
	 * @return void
	 */
	public function install(): void {}

	/**
	 * Return supported languages.
	 *
	 * @return array
	 */
	public function get_supported_languages(): array {
		return array();
	}

	/**
	 * Run on uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {}

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public function deactivation(): void {}

	/**
	 * Run on deactivation of translatepress.
	 *
	 * @return void
	 */
	public function foreign_deactivate(): void {
		// get transient objects-object.
		$transients_obj = Transients::get_instance();

		// get transient-object for this plugin.
		$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_plugin_' . $this->get_name() );

		// delete it.
		$transient_obj->delete();
	}

	/**
	 * We do not add any styles or scripts for translatepress.
	 *
	 * @return void
	 */
	public function get_simplifications_scripts(): void {}

	/**
	 * Return list of active languages this plugin is using atm.
	 *
	 * @return array
	 */
	public function get_active_languages(): array {
		// get settings from translatepress.
		$trp       = TRP_Translate_Press::get_trp_instance();
		$trp_query = $trp->get_component( 'settings' );

		// initialize the list to return.
		$languages = array();

		// loop through the languages activated in WPML.
		foreach ( $trp_query->get_setting( 'translation-languages' ) as $language ) {
			$languages[ $language ] = '1';
		}

		// return resulting list of locales (e.g. "de_EL").
		return $languages;
	}
}
