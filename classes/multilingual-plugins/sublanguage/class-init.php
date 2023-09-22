<?php
/**
 * File for initializing the easy-language-own translations.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Sublanguage;

use easyLanguage\Apis;
use easyLanguage\Base;
use easyLanguage\Helper;
use easyLanguage\Languages;
use easyLanguage\Multilingual_Plugins_Base;
use easyLanguage\Transients;
use WP_Admin_Bar;
use WP_Query;

/**
 * Rewrite-Handling for this plugin.
 */
class Init extends Base implements Multilingual_Plugins_Base {

    /**
     * Name of this plugin.
     *
     * @var string
     */
    protected string $name = 'sublanguage';

	/**
	 * Title of this plugin.
	 *
	 * @var string
	 */
	protected string $title = 'Sublanguage';

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
        // add hooks.
        add_action( 'admin_init', array( $this, 'wp_init' ) );

	    // disable transients on sublanguage-deactivation.
	    add_action( 'deactivate_sitepress-multilingual-cms/sitepress.php', array( $this, 'foreign_deactivate') );
    }

    /**
     * Run on plugin-installation.
     *
     * @return void
     */
    public function install(): void {}

    /**
     * Run on each request to add our supported languages.
     *
     * @return void
     */
    public function wp_init(): void {
        foreach( Languages::get_instance()->get_active_languages() as $language_code => $language ) {
            $query = array(
                'post_type' => 'language',
                'title' => $language['label'],
                'fields' => 'ids'
            );
            $results = new WP_Query( $query );
            if( 0 === $results->post_count ) {
                $array = array(
                    'post_type' => 'language',
                    'post_title' => $language['label'],
                    'post_content' => $language_code,
                    'post_status' => 'publish'
                );
                wp_insert_post( $array );
            }
        }
    }

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
		$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_plugin_'.$this->get_name() );

		// delete it.
		$transient_obj->delete();
	}
}
