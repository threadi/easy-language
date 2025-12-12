<?php
/**
 * File for initializing polylang support.
 *
 * @package easy-language
 */

namespace easyLanguage\ThirdPartySupport\Polylang;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Base;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Languages;
use easyLanguage\Plugin\ThirdPartySupport_Base;
use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;

/**
 * Object to handle polylang support.
 */
class Init extends Base implements ThirdPartySupport_Base {

	/**
	 * Name of this plugin.
	 *
	 * @var string
	 */
	protected string $name = 'polylang';

	/**
	 * Title of this plugin.
	 *
	 * @var string
	 */
	protected string $title = 'Polylang';

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
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if the plugin is not enabled.
		if ( ! $this->is_active() ) {
			return;
		}

		// hooks for polylang.
		add_filter( 'pll_predefined_languages', array( $this, 'add_predefined_language' ) );
		add_filter( 'pll_predefined_flags', array( $this, 'add_flag' ) );
		add_filter( 'pll_flag', array( $this, 'get_flag' ), 10, 2 );

		// disable transients on polylang-deactivation.
		add_action( 'deactivate_polylang/polylang.php', array( $this, 'foreign_deactivate' ) );
	}

	/**
	 * Run on plugin-installation.
	 *
	 * @return void
	 */
	public function install(): void {}

	/**
	 * Add predefined languages.
	 *
	 * @param array<string,array<string>> $languages List of languages.
	 *
	 * @return array<string,array<string>>
	 */
	public function add_predefined_language( array $languages ): array {
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $language ) {
			$languages[ $language_code ] = array(
				'code'     => $language['url'],
				'locale'   => $language_code,
				'name'     => $language['label'],
				'dir'      => 'ltr',
				'flag'     => $language_code,
				'facebook' => $language_code,
			);
		}
		return $languages;
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
	 * Run on deactivation of Polylang.
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
	 * We add no simplification-scripts for Polylang.
	 *
	 * @return void
	 */
	public function get_simplifications_scripts(): void {}

	/**
	 * Add our custom flags for supported languages.
	 *
	 * @param array<string> $flags List of flags.
	 *
	 * @return array<string>
	 */
	public function add_flag( array $flags ): array {
		foreach ( Languages::get_instance()->get_possible_target_languages() as $language_code => $language ) {
			$flags[ $language_code ] = $language['label'];
		}
		return $flags;
	}

	/**
	 * Get our custom flags for supported languages.
	 *
	 * @param array<string> $flags List of flags.
	 * @param string        $code Language-code.
	 *
	 * @return array<string>
	 */
	public function get_flag( array $flags, string $code ): array {
		// short-return if it is not one of our own language-codes.
		$languages = Languages::get_instance()->get_possible_target_languages();
		if ( empty( $languages[ $code ] ) ) {
			return $flags;
		}

		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once wp_normalize_path( ABSPATH . '/wp-admin/includes/file.php' );
		}

		// set URL.
		$flags['url'] = plugins_url( 'gfx/' . $code . '.png', EASY_LANGUAGE );

		// get file for base64.
		$file = plugin_dir_path( EASY_LANGUAGE ) . 'gfx/' . $code . '.png';
		WP_Filesystem();
		$file_contents = $wp_filesystem->get_contents( $file );

		// return attribute if file-content is empty.
		if ( empty( $file_contents ) ) {
			return $flags;
		}

		// set src for flag.
		$flags['src'] = 'data:image/png;base64,' . base64_encode( $file_contents );

		// return result.
		return $flags;
	}

	/**
	 * Return list of active languages in polylang.
	 *
	 * @return array<string,string>
	 */
	public function get_active_languages(): array {
		// bail if polylang is not active.
		if ( ! function_exists( 'PLL' ) ) {
			return array();
		}

		// initialize the list to return.
		$languages = array();

		// loop through the languages activated in polylang.
		foreach ( PLL()->model->get_languages_list() as $language ) {
			$languages[ $language->get_locale() ] = '1';
		}

		// return resulting list of locales (e.g. "de_EL").
		return $languages;
	}

	/**
	 * Additional cli functions we do not use for this plugin.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return whether this object is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'polylang/polylang.php' );
	}
}
