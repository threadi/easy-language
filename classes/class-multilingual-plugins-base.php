<?php
/**
 * File for interface which defines supports for different plugins.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface-definition for each API-support-object.
 */
interface Multilingual_Plugins_Base {
	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void;

	/**
	 * Run during installation of the plugin.
	 *
	 * @return void
	 */
	public function install(): void;

	/**
	 * Run during uninstallation of the plugins.
	 *
	 * @return void
	 */
	public function uninstall(): void;

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public function deactivation(): void;

	/**
	 * Return languages this plugin will support.
	 *
	 * @return array
	 */
	public function get_supported_languages(): array;

	/**
	 * Return list of active languages this plugin is using atm.
	 *
	 * Format example: array( "de_LS" => 1 )
	 *
	 * @return array
	 */
	public function get_active_languages(): array;

	/**
	 * Get styles and scripts of the plugin.
	 *
	 * @return void
	 */
	public function get_simplifications_scripts(): void;
}
