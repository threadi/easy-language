<?php
/**
 * File for API- and plugin-handling in this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base-object for API- and plugin-main-classes.
 */
abstract class Base_Abstract {
	/**
	 * Marker for foreign plugin (plugins which are supported by this plugin but not maintained).
	 *
	 * @var bool
	 */
	protected bool $foreign_plugin = true;

	/**
	 * Marker for API-support.
	 *
	 * @var bool
	 */
	protected bool $supports_apis = false;

	/**
	 * Marker if plugin has own API-configuration.
	 *
	 * @var bool
	 */
	protected bool $has_own_api_config = false;

	/**
	 * Internal name of the API.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Title of the API.
	 *
	 * @var string
	 */
	protected string $title = '';

	/**
	 * Set max text length for single entry for this API.
	 *
	 * @var int
	 */
	protected int $max_single_text_length = PHP_INT_MAX;

	/**
	 * Set max requests per minute for this API.
	 *
	 * @var int
	 */
	protected int $max_requests_per_minute = PHP_INT_MAX;

	/**
	 * Language-specific support-URL.
	 *
	 * @var array
	 */
	protected array $support_url = array(
		'de_DE' => 'https://laolaweb.com/',
	);

	/**
	 * Return the internal name of the API.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the public title of the object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Return the public title of the object.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return '';
	}

	/**
	 * Return the public title of the object.
	 *
	 * @return string
	 */
	public function get_logo_url(): string {
		return '';
	}

	/**
	 * Return whether this plugin supports our APIs.
	 *
	 * @return bool
	 */
	public function is_supporting_apis(): bool {
		return $this->supports_apis;
	}

	/**
	 * Get quota as array containing 'character_spent' and 'character_limit'.
	 *
	 * @return array
	 */
	public function get_quota(): array {
		return array(
			'character_spent' => 0,
			'character_limit' => 0,
		);
	}

	/**
	 * Return whether this plugin is a foreign plugin.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public function is_foreign_plugin(): bool {
		return $this->foreign_plugin;
	}

	/**
	 * Return whether this plugin has its own api config.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public function has_own_api_config(): bool {
		return $this->has_own_api_config;
	}

	/**
	 * Do nothing during disabling an API.
	 *
	 * @return void
	 */
	public function disable(): void {}

	/**
	 * API has no settings per default.
	 *
	 * @return bool
	 */
	public function has_settings(): bool {
		return false;
	}

	/**
	 * Return if test mode for this API is active or not.
	 *
	 * @return bool
	 */
	public function is_test_mode_active(): bool {
		return false;
	}

	/**
	 * Return request object.
	 *
	 * @return bool
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function get_request_object() {
		return false;
	}

	/**
	 * Return list of active language-mappings.
	 *
	 * @return array
	 */
	public function get_active_language_mapping(): array {
		$result = array();

		// get actual enabled source-languages.
		$source_languages = $this->get_active_source_languages();

		// get actual enabled target-languages.
		$target_languages = $this->get_active_target_languages();

		// get mapping.
		$mappings = $this->get_mapping_languages();

		/**
		 * Loop through the source-languages,
		 * check the mapping target-languages for each
		 * and if they are active - if yes, add them to list.
		 */
		foreach ( $source_languages as $source_language => $enabled ) {
			if ( ! empty( $mappings[ $source_language ] ) ) {
				foreach ( $mappings[ $source_language ] as $language ) {
					if ( ! empty( $target_languages[ $language ] ) ) {
						$result[ $source_language ][] = $language;
					}
				}
			}
		}

		// return resulting list.
		return $result;
	}

	/**
	 * Return active source languages.
	 *
	 * @return array
	 */
	public function get_active_source_languages(): array {
		return array();
	}

	/**
	 * Return active target languages.
	 *
	 * @return array
	 */
	public function get_active_target_languages(): array {
		return array();
	}

	/**
	 * Return all by this API simplified post type objects.
	 *
	 * @return array
	 */
	public function get_simplified_post_type_objects(): array {
		$post_types       = \easyLanguage\Multilingual_plugins\Easy_Language\Init::get_instance()->get_supported_post_types();
		$post_types_array = array();
		foreach ( $post_types as $post_type => $enabled ) {
			$post_types_array[] = $post_type;
		}
		$query   = array(
			'post_type'                       => $post_types_array,
			'posts_per_page'                  => -1,
			'post_status'                     => array( 'any', 'trash' ),
			'fields'                          => 'ids',
			'meta_query'                      => array(
				array(
					'key'     => 'easy_language_api',
					'value'   => $this->get_name(),
					'compare' => '=',
				),
			),
			'do_not_use_easy_language_filter' => true,
		);
		$results = new WP_Query( $query );
		return $results->posts;
	}

	/**
	 * Return the settings-URL for the API.
	 *
	 * @return string
	 */
	public function get_settings_url(): string {
		if ( false === $this->has_settings() ) {
			return '';
		}
		return add_query_arg(
			array(
				'tab' => $this->get_name(),
			),
			Helper::get_settings_page_url()
		);
	}

	/**
	 * Return true if API has settings.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return $this->has_settings();
	}

	/**
	 * Return the language-specific support-URL for the API.
	 *
	 * @return string
	 */
	public function get_language_specific_support_page(): string {
		// return language-specific URL if it exists.
		if ( ! empty( $this->support_url[ helper::get_current_language() ] ) ) {
			return $this->support_url[ helper::get_current_language() ];
		}

		// otherwise return default url.
		return $this->support_url['de_DE'];
	}

	/**
	 * Get overview about quota as html-table.
	 *
	 * @return string
	 */
	protected function get_quota_table(): string {
		// get quota.
		$quota = $this->get_quota();

		// define table.
		$table  = '<table>';
		$table .= '<tr><th>' . esc_html__( 'Quota', 'easy-language' ) . ':</th><td>' . absint( $quota['character_limit'] ) . '</td></tr>';
		$table .= '<tr><th>' . esc_html__( 'Character spent', 'easy-language' ) . ':</th><td>' . absint( $quota['character_spent'] ) . '</td></tr>';
		$table .= '<tr><th>' . esc_html__( 'Rest quota', 'easy-language' ) . ':</th><td>' . absint( $quota['character_limit'] ) - absint( $quota['character_spent'] ) . '</td></tr>';
		$table .= '</table>';

		// output the resulting table.
		return $table;
	}

	/**
	 * Return whether a valid language-combination is set.
	 *
	 * Any active source language must be translatable to any active target-language.
	 *
	 * @param array $target_languages List of target languages to check.
	 * @return bool true if valid language-combination exist
	 */
	protected function is_language_set( array $target_languages = array() ): bool {
		if ( empty( $target_languages ) ) {
			// get actual enabled source-languages.
			$target_languages = $this->get_active_target_languages();
		}

		if ( ! is_array( $target_languages ) ) {
			$target_languages = array();
		}

		// get mappings.
		$mappings = $this->get_mapping_languages();

		// get actual enabled source-languages.
		$source_languages = $this->get_active_source_languages();
		if ( ! is_array( $source_languages ) ) {
			$source_languages = array();
		}

		// check if all source-languages mapping all target-languages.
		$match = array();
		foreach ( $source_languages as $source_language => $enabled ) {
			foreach ( $target_languages as $value => $enabled2 ) {
				if ( 1 === absint( $enabled ) && 1 === absint( $enabled2 ) && ! empty( $mappings[ $source_language ] ) && false !== in_array( $value, $mappings[ $source_language ], true ) ) {
					$match[] = $source_language;
				}
			}
		}

		// return false if no valid combination has been found.
		return ! empty( $match );
	}

	/**
	 * Return whether this API has extended support in Easy Language Pro.
	 *
	 * @return bool
	 */
	public function is_extended_in_pro(): bool {
		return false;
	}

	/**
	 * Return max text length for this API.
	 *
	 * @return int
	 */
	public function get_max_text_length(): int {
		return $this->max_single_text_length;
	}

	/**
	 * Return max requests per minute for this API.
	 *
	 * @return int
	 */
	public function get_max_requests_per_minute(): int {
		return $this->max_requests_per_minute;
	}

	/**
	 * Return supported languages.
	 *
	 * @return array
	 */
	public function get_supported_languages(): array {
		return array();
	}

	/**
	 * Return the log entries of this API.
	 *
	 * @return array
	 */
	public function get_log_entries(): array {
		return array();
	}
}
