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

// do nothing if the PHP version is not 8.2 or newer.
if ( PHP_VERSION_ID < 80200 ) { // @phpstan-ignore smaller.alwaysFalse
	return;
}

// save plugin-path (the main plugin file, not this uninstall file).
const EASY_LANGUAGE = __DIR__ . '/easy-language.php';

// set the version number.
const EASY_LANGUAGE_VERSION = '@@VersionNumber@@';

// embed the necessary files.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/constants.php';

/**
 * Run uninstall-routines in all sites.
 */
Uninstall::get_instance()->run( true );

/**
 * Remove the encryption key of this plugin (e.g. from wp-config.php) in all sites.
 */
Uninstall::get_instance()->remove_encryption_keys();
