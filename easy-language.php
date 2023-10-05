<?php
/**
 * Plugin Name:       Easy Language
 * Description:       Provides possibility to translate texts to easy and plain language.
 * Requires at least: 6.0
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

use easyLanguage\Init;

// save plugin-path.
const EASY_LANGUAGE = __FILE__;

// do nothing if PHP-version is not 8.0 or newer.
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	return;
}

// embed necessary files.
require_once 'inc/autoload.php';
require_once 'inc/constants.php';

// include admin-related files.
if ( is_admin() ) {
	include_once 'inc/admin.php';
	// include all settings-files.
	foreach ( glob( plugin_dir_path( EASY_LANGUAGE ) . 'inc/settings/*.php' ) as $filename ) {
		include $filename;
	}
}

// initialize the plugin.
$lel = Init::get_instance();
$lel->init();
