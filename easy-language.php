<?php
/**
 * Plugin Name:       Easy Language
 * Description:       Provides the possibility to simplify texts to easy and plain language.
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Version:           @@VersionNumber@@
 * Author:            laOlaWeb
 * Author URI:        https://laolaweb.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       easy-language
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

// do nothing if the PHP version is not 8.1 or newer.
if ( PHP_VERSION_ID < 80100 ) { // @phpstan-ignore smaller.alwaysFalse
	return;
}

// save plugin-path.
const EASY_LANGUAGE = __FILE__;

// set the version number.
const EASY_LANGUAGE_VERSION = '@@VersionNumber@@';

// embed the necessary files.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/constants.php';

// initialize the plugin.
easyLanguage\Plugin\Init::get_instance()->init();
