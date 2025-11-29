<?php
/**
 * File for initializing the support for plugin "Popup Maker".
 *
 * @package easy-language
 */

namespace easyLanguage\ThirdPartySupport;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Init;
use easyLanguage\Plugin\Base;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\ThirdPartySupport_Base;

/**
 * Object to handle the WPML support.
 */
class PopupMaker extends Base implements ThirdPartySupport_Base {

	/**
	 * Marker for foreign plugin (plugins which are supported by this plugin but not maintained).
	 *
	 * @var bool
	 */
	protected bool $foreign_plugin = false;

	/**
	 * Internal name of the object.
	 *
	 * @var string
	 */
	protected string $name = 'popupmaker';

	/**
	 * Title of the object.
	 *
	 * @var string
	 */
	protected string $title = 'Popup Maker';

	/**
	 * Instance of this object.
	 *
	 * @var ?PopupMaker
	 */
	private static ?PopupMaker $instance = null;

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
	public static function get_instance(): PopupMaker {
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
		add_filter( 'pum_popup_columns', array( $this, 'change_columns' ) );
	}

	/**
	 * Run on plugin-installation.
	 *
	 * @return void
	 */
	public function install(): void {}

	/**
	 * Run on plugin-deinstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {}

	/**
	 * Run on plugin-deactivation.
	 *
	 * @return void
	 */
	public function deactivation(): void {}

	/**
	 * Return list of active languages this plugin is using atm.
	 *
	 * @return array<string,string>
	 */
	public function get_active_languages(): array {
		return array();
	}

	/**
	 * Add custom scripts.
	 *
	 * @return void
	 */
	public function get_simplifications_scripts(): void	{}

	/**
	 * Additional cli functions we do not use for this plugin.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Add our custom simplify columns in the custom Popup Maker table.
	 *
	 * @param array $columns List of columns.
	 *
	 * @return array
	 */
	public function change_columns( array $columns ): array {
		// get easy language init object.
		$easy_language_init_obj = Init::get_instance();

		// bail if cpt is not supported.
		if ( ! $easy_language_init_obj->is_post_type_supported( 'popup' ) ) {
			return $columns;
		}

		// add columns and return them.
		return $easy_language_init_obj->add_post_type_columns( $columns );
	}

	/**
	 * Return whether this object is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'popup-maker/popup-maker.php' );
	}
}
