<?php
/**
 * File to enable easy-language as service for the plugin.
 *
 * @package easy-language
 */

use easyLanguage\Multilingual_plugins\Easy_Language\Init;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the easy language service in this plugin.
 *
 * @param array $plugin_list List of available multilingual-plugins.
 * @return array
 */
function easy_language_register_plugin_easy_language( array $plugin_list ): array {
	$plugin_list[] = Init::get_instance();
	return $plugin_list;
}
add_filter( 'easy_language_register_plugin', 'easy_language_register_plugin_easy_language', PHP_INT_MAX );
