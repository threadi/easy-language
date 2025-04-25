<?php
/**
 * Tasks to run during uninstallation of this plugin.
 *
 * @package easy-language
 */

use easyLanguage\Uninstall;

// if uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// prevent also other direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// do nothing if PHP-version is not 8.0 or newer.
if ( PHP_VERSION_ID < 80000 ) { // @phpstan-ignore smaller.alwaysFalse
	return;
}

// save plugin-path.
const EASY_LANGUAGE = __FILE__;

// embed necessary files.
require_once __DIR__ . '/inc/autoload.php';
require_once __DIR__ . '/inc/constants.php';

// get API directory files.
$api_directory_files = glob( plugin_dir_path( EASY_LANGUAGE ) . 'inc/apis/*.php' );

// include all API-files, if they are given.
if( is_array( $api_directory_files ) ) {
	foreach ( $api_directory_files as $filename ) {
		require_once $filename;
	}
}

// get plugin directory files.
$plugin_directory_files = glob( plugin_dir_path( EASY_LANGUAGE ) . 'inc/multilingual-plugins/*.php' );

// include all settings-files, if they are given.
if( is_array( $plugin_directory_files ) ) {
	foreach ( $plugin_directory_files as $filename ) {
		require_once $filename;
	}
}

/**
 * Run install-routines.
 */
$uninstaller = Uninstall::get_instance();
$uninstaller->run();
