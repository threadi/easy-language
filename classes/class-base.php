<?php
/**
 * File for API-handling in this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Base-object for API- and plugin-main-classes.
 */
class Base {
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
			'character_limit' => 0
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
}
