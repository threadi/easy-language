<?php
/**
 * Plugin Name:       Easy Language
 * Description:       Provides possibility to simplify texts to easy and plain language.
 * Requires at least: 4.9.24
 * Requires PHP:      8.0
 * Version:           @@VersionNumber@@
 * Author:            laOlaWeb
 * Author URI:        https://laolaweb.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       easy-language
 *
 * @package easy-language
 */

// do nothing if PHP-version is not 8.0 or newer.
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	return;
}

// save plugin-path.
const EASY_LANGUAGE = __FILE__;

// set version number.
define( 'EASY_LANGUAGE_VERSION', '@@VersionNumber@@' );

// embed necessary files.
require_once __DIR__ . '/inc/autoload.php';
require_once __DIR__ . '/inc/constants.php';

// include admin-related files.
if ( is_admin() ) {
	include_once 'inc/admin.php';
	// include all settings-files.
	foreach ( glob( plugin_dir_path( EASY_LANGUAGE ) . 'inc/settings/*.php' ) as $filename ) {
		include $filename;
	}
}

// initialize the plugin.
easyLanguage\Init::get_instance()->init();
