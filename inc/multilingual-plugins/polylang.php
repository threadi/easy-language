<?php
/**
 * File to add Polylang as supported multilingual plugin.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Helper;
use easyLanguage\Multilingual_plugins\Polylang\Init;
use easyLanguage\Multilingual_Plugins_Base;

/**
 * Register the Polylang service in this plugin.
 *
 * @param array<int,Multilingual_Plugins_Base> $plugin_list List of available multilingual-plugins.
 * @return array<int,Multilingual_Plugins_Base>
 */
function easy_language_register_plugin_polylang( array $plugin_list ): array {
	// bail if plugin is not active.
	if ( false === Helper::is_plugin_active( 'polylang/polylang.php' ) ) {
		return $plugin_list;
	}

	// get plugin-object and add it to list.
	$plugin_list[] = Init::get_instance();

	// return resulting list.
	return $plugin_list;
}
add_filter( 'easy_language_register_plugin', 'easy_language_register_plugin_polylang' );
