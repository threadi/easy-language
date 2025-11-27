<?php
/**
 * File for interface which defines supports for different APIS.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Interface-definition for each API-support-object.
 */
interface Api_Base {
	/**
	 * Return list of supported source-languages.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_supported_source_languages(): array;

	/**
	 * Return list of supported target-languages.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_supported_target_languages(): array;

	/**
	 * Get the list of active source languages.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_active_source_languages(): array;

	/**
	 * Get the list of active target languages.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_active_target_languages(): array;

	/**
	 * Return the list of supported languages which could be simplified with this API into each other.
	 *
	 * @return array<string,mixed>
	 */
	public function get_mapping_languages(): array;

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
	 * Install-routines for the API, called during plugin-activation.
	 *
	 * @return void
	 */
	public function install(): void;

	/**
	 * Deactivate-routines for the API, called during plugin-deactivation.
	 *
	 * @return void
	 */
	public function deactivate(): void;

	/**
	 * Uninstall-routines for the API, called during plugin-activation.
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
	 * Enable-routines for the API, called on the new API if another API is chosen.
	 *
	 * @return void
	 */
	public function enable(): void;

	/**
	 * Get the API-specific simplifications-object (used to run simplifications).
	 *
	 * @return Api_Simplifications
	 */
	public function get_simplifications_obj(): Api_Simplifications;

	/**
	 * Get quota as array containing 'character_spent' and 'character_limit'.
	 *
	 * @return array<string,mixed>
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
	 * Return the request-object for this API.
	 *
	 * @return Api_Requests
	 */
	public function get_request_object(): Api_Requests;

	/**
	 * Return all by this API simplified post type objects.
	 *
	 * @return array<integer>
	 */
	public function get_simplified_post_type_objects(): array;

	/**
	 * Return the settings-URL for the API.
	 *
	 * @return string
	 */
	public function get_settings_url(): string;

	/**
	 * Return whether this API is configured (true) or not (false).
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Return whether this API has extended support in Easy Language Pro.
	 *
	 * @return bool
	 */
	public function is_extended_in_pro(): bool;

	/**
	 * Return the log entries of this API.
	 *
	 * @return array<int,mixed>
	 */
	public function get_log_entries(): array;

	/**
	 * Return the title.
	 *
	 * @return string
	 */
	public function get_title(): string;

	/**
	 * Get the token.
	 *
	 * @return string
	 */
	public function get_token(): string;

	/**
	 * Return the internal name of the object.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Return language-specific request text for the API.
	 *
	 * @param string $target_language The target-language.
	 *
	 * @return string
	 */
	public function get_request_text_by_language( string $target_language ): string;

	/**
	 * Return if test mode for this API is active or not.
	 *
	 * @return bool
	 */
	public function is_test_mode_active(): bool;

	/**
	 * Disable free requests.
	 *
	 * @return void
	 */
	public function disable_free_requests(): void;

	/**
	 * Return the token field name.
	 *
	 * @return string
	 */
	public function get_token_field_name(): string;

	/**
	 * Return max requests per minute for this API.
	 *
	 * @return int
	 */
	public function get_max_requests_per_minute(): int;

	/**
	 * Return false whether this API would support translatepress-plugin.
	 *
	 * @return bool
	 */
	public function is_supporting_translatepress(): bool;

	/**
	 * Return class-name for translatepress-machine.
	 *
	 * @return string
	 */
	public function get_translatepress_machine_class(): string;

	/**
	 * Return list of active language-mappings.
	 *
	 * @return array<string,list<mixed>>
	 */
	public function get_active_language_mapping(): array;

	/**
	 * Return max text length for this API.
	 *
	 * @return int
	 */
	public function get_max_text_length(): int;
}
