<?php
/**
 * File for the module "Language Switcher" in Themify Builder.
 *
 * Themify Builder loads this file itself (registered via Themify_Builder_Model::add_module()) and expects the
 * global class name TB_<Slug>_Module.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object for the module "Language Switcher" in Themify Builder.
 *
 * The settings of the module are defined in legacy-classes/Themify/themify.js, the output in
 * legacy-classes/Themify/templates/template-easy-language-switcher.php.
 *
 * @noinspection PhpUnused
 */
class TB_Easy_Language_Switcher_Module extends Themify_Builder_Component_Module {
	/**
	 * Return the name of the module.
	 *
	 * @return string
	 */
	public static function get_module_name(): string {
		return __( 'Language Switcher', 'easy-language' );
	}

	/**
	 * Return the icon of the module.
	 *
	 * Themify Builder shows module icons as SVG sprite references ("#tf-ti-<icon>"). The symbol for our icon (the
	 * Easy Language logo) is added to its SVG sprite by legacy-classes/Themify/themify.js.
	 *
	 * @return string
	 */
	public static function get_module_icon(): string {
		return \easyLanguage\PageBuilder\Themify::ICON;
	}
}
