<?php
/**
 * File for the language switcher module for Beaver Builder.
 *
 * Beaver Builder uses the name of this file as slug of the module and loads its output from "includes/frontend.php"
 * beside it. The class is in the global namespace like all modules of Beaver Builder.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\PageBuilder\BeaverBuilder;

/**
 * Object for the language switcher module in Beaver Builder.
 *
 * The settings form is registered by \easyLanguage\PageBuilder\BeaverBuilder::register_module().
 *
 * @noinspection PhpUnused
 */
class Easy_Language_Beaver_Builder_Switcher extends FLBuilderModule {
	/**
	 * Constructor for this module.
	 */
	public function __construct() {
		$beaver_builder = BeaverBuilder::get_instance();
		parent::__construct(
			array(
				'name'            => __( 'Language Switcher', 'easy-language' ),
				'description'     => '',
				'category'        => __( 'Easy Language', 'easy-language' ),
				'icon'            => $beaver_builder->get_module_icon(),
				'partial_refresh' => true,
				// our output depends on the actual request, so it should not be exported to the post content.
				'editor_export'   => false,
				// set the paths explicitly as the plugin directory could be a symlink outside of ABSPATH.
				'dir'             => $beaver_builder->get_module_dir(),
				'url'             => $beaver_builder->get_module_url(),
			)
		);
	}
}
