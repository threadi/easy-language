<?php
/**
 * File for handling updates of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Helper-function for updates of this plugin.
 */
class Update {
	/**
	 * Instance of this object.
	 *
	 * @var ?Update
	 */
	private static ?Update $instance = null;

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Update {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Initialize the Updater.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'plugins_loaded', array( $this, 'run' ) );
	}

	/**
	 * Run check for updates.
	 *
	 * @return void
	 */
	public function run(): void {
		// get installed plugin-version (version of the actual files in this plugin).
		$installed_plugin_version = EASY_LANGUAGE_VERSION;

		// get db-version (version which was last installed).
		$db_plugin_version = get_option( 'easyLanguageVersion', '1.0.0' );

		// compare version if we are not in development-mode.
		if (
			(
				(
					function_exists( 'wp_is_development_mode' ) && false === wp_is_development_mode( 'plugin' )
				)
				|| ! function_exists( 'wp_is_development_mode' )
			)
			&& version_compare( $installed_plugin_version, $db_plugin_version, '>' )
		) {
			if ( version_compare( $installed_plugin_version, '2.1.0', '>=' ) ) {
				$this->version210();
			}
		}

		$this->version210();

		// save new plugin-version in DB.
		update_option( 'easyLanguageVersion', $installed_plugin_version );
	}

	/**
	 * Run on every update for 2.1.0 or newer.
	 *
	 * @return void
	 */
	private function version210(): void {
		// update schedule interval from 5minutely or minutely to 10minutely.
		if ( ! wp_next_scheduled( 'easy_language_automatic_simplification' ) ) {
			// add it.
			wp_schedule_event( time(), '10minutely', 'easy_language_automatic_simplification' );
		} else {
			$scheduled_event = wp_get_scheduled_event( 'easy_language_automatic_simplification' );
			if ( in_array( $scheduled_event->schedule, array( '5minutely', 'minutely' ), true ) ) {
				wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
				wp_schedule_event( time(), '10minutely', 'easy_language_automatic_simplification' );
			}
		}
	}
}
