<?php
/**
 * File for interface which defines supports for different APIS.
 */

namespace easyLanguage;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Interface-definition for each API-support-object.
 */
interface Api_Base {
	/**
	 * Return list of supported source-languages.
	 *
	 * @return array
	 */
	public function get_supported_source_languages(): array;

	/**
	 * Return list of supported target-languages.
	 *
	 * @return array
	 */
	public function get_supported_target_languages(): array;

	/**
	 * Get the list of active source languages.
	 *
	 * @return array
	 */
	public function get_active_source_languages(): array;

    /**
     * Get the list of active target languages.
     *
     * @return array
     */
    public function get_active_target_languages(): array;

	/**
	 * Return the list of supported languages which could be translated with this API into each other.
	 *
	 * @return array
	 */
	public function get_mapping_languages(): array;

	/**
	 * Add settings-tab for this plugin.
	 *
	 * @param $tab
	 * @return void
	 */
	public function add_settings_tab( $tab ): void;

	/**
	 * Add settings-page for this plugin.
	 *
	 * @return void
	 */
	public function add_settings_page(): void;

	/**
	 * Add settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings(): void;

	/**
	 * Return whether this API has settings for this plugin.
	 *
	 * @return bool
	 */
	public function has_settings(): bool;

	/**
	 * Return whether this API is active regarding all its settings.
	 *
	 * @return bool
	 */
	public function is_active(): bool;

	/**
	 * Install-routines for the API, called during plugin-activation and API-change.
	 *
	 * @return void
	 */
	public function install(): void;

	/**
	 * Uninstall-routines for the API, called during plugin-activation and API-change.
	 *
	 * @return void
	 */
	public function uninstall(): void;

	/**
	 * Disable-routines for the API, called on the former API if another API is chosen.
	 *
	 * @return void
	 */
	public function disable(): void;

    /**
     * Get the API-specific translations-object.
     *
     * @return object
     */
    public function get_translations_obj(): object;

	/**
	 * Get quota as array containing 'character_spent' and 'character_limit'.
	 *
	 * @return array
	 */
	public function get_quota(): array;

	/**
	 * Get public description for API.
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * Get public logo-URL for API.
	 *
	 * @return string
	 */
	public function get_logo_url(): string;

	/**
	 * Initialize api-specific CLI-functions.
	 *
	 * @return void
	 */
	public function cli(): void;

	/**
	 * Return API URL.
	 *
	 * @return string
	 */
	public function get_api_url(): string;

	/**
	 * Return the Request-object for this API.
	 *
	 * @return mixed
	 */
	public function get_request_object();
}
