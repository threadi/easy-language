<?php
/**
 * File to handle WPML-hook.
 *
 * @package easy-language
 */

use easyLanguage\Helper;
use easyLanguage\Multilingual_plugins\Wpml\Init;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the WPML service in this plugin.
 *
 * @param array $plugin_list List of available multilingual-plugins.
 * @return array
 */
function easy_language_register_plugin_wpml( array $plugin_list ): array {
    // bail if plugin is not active.
    if( false === Helper::is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
        return $plugin_list;
    }

    // get plugin-object and add it to list.
    $plugin_list[] = Init::get_instance();

    // return resulting list.
    return $plugin_list;
}
add_filter( 'easy_language_register_plugin', 'easy_language_register_plugin_wpml');
