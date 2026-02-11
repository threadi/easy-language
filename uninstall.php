<?php
/**
 * Tasks to run during uninstallation of this plugin.
 *
 * @package easy-language
 */

use easyLanguage\Plugin\Uninstall;

// if uninstall.php is not called by WordPress itself, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// prevent also other direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

/**
 * Run install-routines.
 */
Uninstall::get_instance()->run();
