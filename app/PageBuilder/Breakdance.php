<?php
/**
 * File for handling Breakdance as page builder.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\Plugin\Helper;

/**
 * Object to handle Breakdance as page builder.
 *
 * Adds a language switcher element to Breakdance. The element is located in admin/breakdance/elements/ and
 * loaded by Breakdance itself.
 */
class Breakdance extends PageBuilder_Base {
	/**
	 * Instance of this object.
	 *
	 * @var ?Breakdance
	 */
	private static ?Breakdance $instance = null;

	/**
	 * Constructor for this object.
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
	public static function get_instance(): Breakdance {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * Breakdance loads the elements of all save locations on "breakdance_loaded" with priority 10, so we must
	 * register our location before.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'before_breakdance_loaded', array( $this, 'register_elements' ) );
	}

	/**
	 * Register the directory with our own elements in Breakdance.
	 *
	 * @return void
	 */
	public function register_elements(): void {
		// bail if the Breakdance function is not available.
		if ( ! function_exists( '\Breakdance\ElementStudio\registerSaveLocation' ) ) {
			return;
		}

		// register the directory (relative to the plugin directory).
		\Breakdance\ElementStudio\registerSaveLocation(
			dirname( plugin_basename( EASY_LANGUAGE ) ) . '/admin/breakdance/elements',
			'EasyLanguageBreakdance',
			'element',
			'Easy Language', // not translated, as this runs before "init" and the location is hidden in Element Studio.
			false,
			true
		);
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'breakdance/plugin.php' );
	}
}
