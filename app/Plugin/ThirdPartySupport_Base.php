<?php
/**
 * File for interface which defines supports for different plugins.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Interface-definition for each API-support-object.
 */
interface ThirdPartySupport_Base {
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
	 * @return array<string,mixed>
	 */
	public function get_supported_languages(): array;

	/**
	 * Return list of active languages this plugin is using atm.
	 *
	 * Format example: array( "de_LS" => 1 )
	 *
	 * @return array<string,string>
	 */
	public function get_active_languages(): array;

	/**
	 * Get styles and scripts of the plugin.
	 *
	 * @return void
	 */
	public function get_simplifications_scripts(): void;

	/**
	 * Initialize our main CLI-functions.
	 *
	 * @return void
	 */
	public function cli(): void;

	/**
	 * Return whether this plugin is a foreign plugin.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public function is_foreign_plugin(): bool;

	/**
	 * Return whether this plugin has its own api config.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public function has_own_api_config(): bool;

	/**
	 * Return whether this plugin supports our APIs.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public function is_supporting_apis(): bool;

	/**
	 * Return the internal name of the object.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Return the title.
	 *
	 * @return string
	 */
	public function get_title(): string;
}
