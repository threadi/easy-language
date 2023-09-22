<?php
/**
 * File for initializing the easy-language-own translations.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Polylang;

use easyLanguage\Apis;
use easyLanguage\Base;
use easyLanguage\Helper;
use easyLanguage\Languages;
use easyLanguage\Multilingual_Plugins_Base;
use easyLanguage\Transients;
use PLL_Admin_Model;
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
		// hooks for polylang.
        add_filter( 'pll_predefined_languages', array( $this, 'add_predefined_language' ) );

	    // disable transients on polylang-deactivation.
	    add_action( 'deactivate_sitepress-multilingual-cms/sitepress.php', array( $this, 'foreign_deactivate') );
    }

    /**
     * Run on plugin-installation.
     *
     * @return void
     */
    public function install(): void {
        if( class_exists('PLL_Admin_Model') ) {
            $options = get_option('polylang');
            $pll = new PLL_Admin_Model($options);
	        foreach( Languages::get_instance()->get_active_languages() as $language_code => $language ) {
		        $lang = array(
			        'name'       => $language['label'],
			        'slug'       => 'ls',
			        'locale'     => $language_code,
			        'rtl'        => 0,
			        'term_group' => 0,
		        );
		        $pll->add_language( $lang );
	        }
        }
    }

	/**
	 * Add predefined languages.
	 *
	 * @param $languages
	 *
	 * @return array
	 */
    public function add_predefined_language( $languages ): array {
	    foreach( Languages::get_instance()->get_active_languages() as $language_code => $language ) {
		    $languages[$language_code] = array(
			    'code'     => 'ls',
			    'locale'   => $language_code,
			    'name'     => $language['label'],
			    'dir'      => 'ltr',
			    'flag'     => 'ls',
			    'facebook' => $language_code,
		    );
	    }
        return $languages;
    }

	/**
	 * Return languages this plugin will support.
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
