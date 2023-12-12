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

// save plugin-path.
const EASY_LANGUAGE = __FILE__;

// embed necessary files.
require_once 'inc/autoload.php';
require_once 'inc/constants.php';

// include all API-files.
foreach ( glob( plugin_dir_path( EASY_LANGUAGE ) . 'inc/apis/*.php' ) as $filename ) {
	require_once $filename;
}

// include all settings-files.
foreach ( glob( plugin_dir_path( EASY_LANGUAGE ) . 'inc/multilingual-plugins/*.php' ) as $filename ) {
	require_once $filename;
}

/**
 * Run install-routines.
 */
$uninstaller = Uninstall::get_instance();
$uninstaller->run();
