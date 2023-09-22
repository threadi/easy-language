<?php
/**
 * File for initializing the easy-language-own translations.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Wpml;

use easyLanguage\Base;
use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use easyLanguage\Multilingual_Plugins_Base;
use easyLanguage\Transients;
use SitePress_EditLanguages;
use WPML_Flags_Factory;

/**
 * Rewrite-Handling for this plugin.
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
        // bail of WPML-class does not exist.
        if( !class_exists('WPML_Flags_Factory') ) {
            return;
        }

        // get db-connection.
        global $wpdb;

        // check if ls does already exist in wpml-db.
        $result = $wpdb->get_row( 'SELECT id FROM ' . DB::get_instance()->get_wpdb_prefix() . "icl_languages WHERE code = 'ls'" );
        if ( empty( $result ) ) {
            // no => than add it.
            $flags_factory      = new WPML_Flags_Factory( $wpdb );
            $icl_edit_languages = new SitePress_EditLanguages( $flags_factory->create() );
            $data               = array(
                'code'           => 'ls',
                'english_name'   => 'Leichte Sprache',
                'default_locale' => 'ls_ls',
                'encode_url'     => 0,
                'tag'            => 'ls',
                'translations'   => array(),
            );
            $icl_edit_languages->insert_one( $data );
        }

		// disable transients on WPML-deactivation.
	    add_action( 'deactivate_sitepress-multilingual-cms/sitepress.php', array( $this, 'foreign_deactivate') );
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
	 * Run on deactivation of WPML.
	 *
	 * @return void
	 */
	public function foreign_deactivate(): void {
		// get transient objects-object.
		$transients_obj = Transients::get_instance();

		// get transient-object for this plugin.
		$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_plugin_'.$this->get_name() );

		// delete it.
		$transient_obj->delete();
	}
}
