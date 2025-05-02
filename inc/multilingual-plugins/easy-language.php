<?php
/**
 * File to enable easy-language as service for the plugin.
 *
 * @package easy-language
 */

use easyLanguage\Multilingual_plugins\Easy_Language\Init;
use easyLanguage\Multilingual_Plugins_Base;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Register the easy language service in this plugin.
 *
 * @param array<int,Multilingual_Plugins_Base> $plugin_list List of available multilingual-plugins.
 * @return array<int,Multilingual_Plugins_Base>
 */
function easy_language_register_plugin_easy_language( array $plugin_list ): array {
	$plugin_list[] = Init::get_instance();
	return $plugin_list;
}
add_filter( 'easy_language_register_plugin', 'easy_language_register_plugin_easy_language', PHP_INT_MAX );
