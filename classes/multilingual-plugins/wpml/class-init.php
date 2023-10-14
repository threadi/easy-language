<?php
/**
 * File for initializing the WPML-support.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Wpml;

use easyLanguage\Base;
use easyLanguage\Languages;
use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use easyLanguage\Multilingual_Plugins_Base;
use easyLanguage\Transients;
use SitePress_EditLanguages;
use WPML_Flags_Factory;

/**
 * Object to handle the WPML-support.
 */
class Init extends Base implements Multilingual_Plugins_Base {

	/**
	 * Name of this plugin.
	 *
	 * @var string
	 */
	protected string $name = 'wpml';

	/**
	 * Title of this plugin.
	 *
	 * @var string
	 */
	protected string $title = 'WPML';

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
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// disable transients on WPML-deactivation.
		add_action( 'deactivate_sitepress-multilingual-cms/sitepress.php', array( $this, 'foreign_deactivate' ) );
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
	 * We do nothing: it's up to WPML to clean up the database.
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
	 * Run on deactivation of WPML.
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
	 * Return list of active languages this plugin is using atm.
	 *
	 * @return array
	 */
	public function get_active_languages(): array {
		global $sitepress;

		// initialize the list to return.
		$languages = array();

		// loop through the languages activated in WPML.
		foreach ( $sitepress->get_active_languages() as $language ) {
			$languages[ $language['code'] ] = '1';
		}

		// return resulting list of locales (e.g. "de_EL").
		return $languages;
	}

	/**
	 * We do not add any scripts for WPML.
	 *
	 * @return void
	 */
	public function get_translations_script(): void {}

	/**
	 * Task to run on admin initialization.
	 *
	 * @return void
	 */
	public function admin_init(): void {
		// bail of WPML-class does not exist.
		if ( ! class_exists( 'WPML_Flags_Factory' ) ) {
			return;
		}

		// get db-connection.
		global $wpdb, $sitepress;

		// marker that something has been changed in WPML.
		$wpml_changed = false;

		// get actual active languages.
		$old_active_languages = $sitepress->get_active_languages();
		$active_languages     = array();
		foreach ( $old_active_languages as $old_active_language ) {
			$active_languages[] = $old_active_language['code'];
		}

		// check if our languages does already exist in wpml-db.
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $language ) {
			$result = $wpdb->get_row( $wpdb->prepare( 'SELECT `id` FROM ' . DB::get_instance()->get_wpdb_prefix() . 'icl_languages WHERE code = %s', array( $language_code ) ) );
			if ( empty( $result ) ) {
				// copy flag in uploads-directory for WPML-flags.
				$wp_upload_dir = wp_upload_dir();
				$base_path     = $wp_upload_dir['basedir'] . '/';
				$path          = 'flags/';
				$flag_path     = $base_path . $path . $language_code . '.png';
				if ( ! file_exists( $flag_path ) ) {
					copy( plugin_dir_path( EASY_LANGUAGE ) . 'gfx/' . $language_code . '.png', $flag_path );
				}

				// collect possible simplifications.
				$translations = array();
				foreach ( Languages::get_instance()->get_possible_source_languages() as $source_language_code => $source_language ) {
					$translations[ $source_language_code ] = array(
						'language_code'         => $source_language_code,
						'display_language_code' => $source_language_code,
						'name'                  => $source_language['label'],
					);
				}

				// add this language in WPML.
				$flags_factory      = new WPML_Flags_Factory( $wpdb );
				$icl_edit_languages = new SitePress_EditLanguages( $flags_factory->create() );
				$data               = array(
					'code'           => $language_code,
					'english_name'   => $language['label'],
					'default_locale' => $language_code,
					'encode_url'     => 0,
					'tag'            => $language_code,
					'translations'   => $translations,
					'flag'           => $language_code . '.png',
					'flag_upload'    => true,
				);
				$icl_edit_languages->insert_one( $data );

				// add this language to active languages.
				$active_languages[] = $language_code;

				// set marker that something has been changed in WPML.
				$wpml_changed = true;
			}
		}

		// run tasks if something has been changed.
		if ( false !== $wpml_changed ) {
			// save the active languages (including our own).
			$setup_instance = wpml_get_setup_instance();
			$setup_instance->set_active_languages( $active_languages );

			// clear cache in WPML.
			icl_cache_clear();
		}
	}
}
