<?php
/**
 * File for the language switcher module for Live Composer.
 *
 * Live Composer instantiates modules by their class name without namespace, so this class is in the global namespace.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\PageBuilder\LiveComposer;

/**
 * Object for the language switcher module in Live Composer.
 *
 * @noinspection PhpUnused
 */
class Easy_Language_Live_Composer_Switcher extends DSLC_Module {
	/**
	 * The ID of this module.
	 *
	 * @var string
	 */
	public $module_id;

	/**
	 * The title of this module.
	 *
	 * @var string
	 */
	public $module_title;

	/**
	 * The icon of this module.
	 *
	 * @var string
	 */
	public $module_icon;

	/**
	 * The category of this module.
	 *
	 * @var string
	 */
	public $module_category;

	/**
	 * Constructor for this module.
	 */
	public function __construct() {
		$this->module_id       = LiveComposer::MODULE_ID;
		$this->module_title    = __( 'Language Switcher', 'easy-language' );
		$this->module_icon     = 'easy-language-builder-icon';
		$this->module_category = 'General';
	}

	/**
	 * Return the options of this module.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {
		$options = LiveComposer::get_instance()->get_module_options();

		/**
		 * Filter the options of the module (like Live Composer does it for its own modules).
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param array<int,array<string,mixed>> $options The options.
		 * @param string $module_id The ID of the module.
		 */
		return apply_filters( 'dslc_module_options', $options, $this->module_id );
	}

	/**
	 * Output this module.
	 *
	 * @param array<string,mixed> $options The values of the options.
	 *
	 * @return void
	 */
	public function output( $options ) {
		echo LiveComposer::get_instance()->render( is_array( $options ) ? $options : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
