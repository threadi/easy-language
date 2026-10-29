<?php
/**
 * File for handling updates of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Apis\Summ_Ai\Summ_Ai;
use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use easyLanguage\EasyLanguage\Db;
use easyLanguage\EasyLanguage\Rewrite;

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
	 * Name of the option used as lock during updates.
	 */
	private const LOCK_OPTION = 'easy_language_update_lock';

	/**
	 * Seconds after which a lock is treated as orphaned.
	 */
	private const LOCK_TIMEOUT = 10 * MINUTE_IN_SECONDS;

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
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the Updater.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'run' ) );
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
			// bail if an update is already running in another process.
			if ( ! $this->set_lock() ) {
				return;
			}

			try {
				$this->run_updates( $installed_plugin_version, (string) $db_plugin_version );
			} finally {
				// release the lock in every case.
				$this->release_lock();
			}
		}
	}

	/**
	 * Run the updates from the given db-version to the given installed version.
	 *
	 * @param string $installed_plugin_version The version of the plugin files.
	 * @param string $db_plugin_version        The version which was last installed.
	 *
	 * @return void
	 */
	private function run_updates( string $installed_plugin_version, string $db_plugin_version ): void {
		// create the tables of this plugin if they are missing (e.g. if the plugin has not been activated via WordPress).
		if ( ! Db::get_instance()->tables_exist() ) {
			Log::get_instance()->create_table();
			Log_Api::get_instance()->create_table();
			Db::get_instance()->create_table();
		}

		// encrypt the saved API keys, which are not encrypted yet.
		Crypt::get_instance()->migrate();

		if ( version_compare( $installed_plugin_version, '2.1.0', '>=' ) ) {
			$this->version210();
		}

		if ( version_compare( $db_plugin_version, '2.1.0', '<' ) ) {
			$this->version210();
		}
		if ( version_compare( $db_plugin_version, '2.3.0', '<' ) ) {
			$this->version230();
		}
		if ( version_compare( $db_plugin_version, '2.4.0', '<' ) ) {
			$this->version240();
		}
		if ( version_compare( $db_plugin_version, '2.5.0', '<' ) ) {
			$this->version250();
		}
		if ( version_compare( $db_plugin_version, '2.9.0', '<' ) ) {
			$this->version290();
		}
		if ( version_compare( $db_plugin_version, '3.0.0', '<' ) ) {
			$this->version300();
		}
		if ( version_compare( $db_plugin_version, '3.0.2', '<' ) ) {
			$this->version302();
		}
		if ( version_compare( $db_plugin_version, '3.3.0', '<' ) ) {
			$this->version330();
		}
		if ( version_compare( $db_plugin_version, '4.0.0', '<' ) ) {
			$this->version400();
		}

		// log this event.
		/* translators: %1$s and %2$s are replaced by the old and new version. */
		Log::get_instance()->add_log( sprintf( __( 'Easy Language has been updated from %1$s to %2$s.', 'easy-language' ), $db_plugin_version, $installed_plugin_version ), 'success' );

		// save the new plugin-version in DB.
		delete_option( 'easyLanguageVersion' );
		add_option( 'easyLanguageVersion', $installed_plugin_version, '', true );
	}

	/**
	 * Run on every update for 2.1.0 or newer.
	 *
	 * @return void
	 */
	private function version210(): void {
		// get the interval to use for the automatic simplification.
		$interval = $this->get_automatic_simplification_interval();

		// update schedule interval from old, not registered intervals to a registered interval.
		if ( ! wp_next_scheduled( 'easy_language_automatic_simplification' ) ) {
			// add it.
			$this->schedule_automatic_simplification( $interval );
		} else {
			$scheduled_event = wp_get_scheduled_event( 'easy_language_automatic_simplification' );
			if ( $scheduled_event && in_array( $scheduled_event->schedule, array( '5minutely', 'minutely', '10minutely' ), true ) && array_key_exists( $interval, wp_get_schedules() ) ) {
				wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
				$this->schedule_automatic_simplification( $interval );
			}
		}

		// set setup to complete if an API key is set or texts are simplified.
		$setup_obj = Setup::get_instance();
		if ( ! $setup_obj->is_completed() ) {
			$setup_completed = false;

			// check active API.
			$api_obj = Apis::get_instance()->get_active_api();
			if ( $api_obj ) {
				$token_field_name = $api_obj->get_token_field_name();
				if ( ! empty( $token_field_name ) && ! empty( get_option( $token_field_name ) ) ) {
					$setup_completed = true;
				}
			}

			// check if any texts are set (without loading all of them).
			if ( Db::get_instance()->has_entries() ) {
				$setup_completed = true;
			}

			// set setup to complete.
			if ( $setup_completed ) {
				$setup_obj = Setup::get_instance();
				$setup_obj->set_completed( $setup_obj->get_setup_name() );
			}
		}

		// remote now unused transients.
		Transients::get_instance()->delete_transient( Transients::get_instance()->get_transient_by_name( 'easy_language_intro_step_1' ) );
	}

	/**
	 * On update to version 2.3.0 or newer.
	 *
	 * @return void
	 */
	public function version230(): void {
		// get the actual value for setup and save it in the new field, if not already set.
		if ( ! get_option( 'esfw_completed' ) ) {
			update_option( 'esfw_completed', get_option( 'wp_easy_setup_completed' ) );
		}

		// remove our schedules.
		wp_unschedule_hook( 'easy_language_capito_request_quota' );
		wp_unschedule_hook( 'easy_language_summ_ai_request_quota' );

		// re-initialize the active API for clean usage.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( $api_obj instanceof Base ) {
			$api_obj->enable();
		}
	}

	/**
	 * On update to version 2.4.0 or newer.
	 *
	 * @return void
	 */
	public function version240(): void {
		// remove old separator setting.
		delete_option( 'easy_language_summ_ai_separator' );

		// set separator setting for each activated target language.
		if ( ! get_option( 'easy_language_summ_ai_target_languages_separator' ) ) {
			$target_languages = Summ_Ai::get_instance()->get_supported_target_languages();
			$separators       = array();
			foreach ( $target_languages as $target_language => $settings ) {
				$separators[ $target_language ] = $settings['separator'];
			}
			update_option( 'easy_language_summ_ai_target_languages_separator', $separators );
		}

		// set the new_lines setting for each activated target language.
		if ( ! get_option( 'easy_language_summ_ai_target_languages_new_lines' ) ) {
			$target_languages = Summ_Ai::get_instance()->get_supported_target_languages();
			$new_lines        = array();
			foreach ( $target_languages as $target_language => $settings ) {
				$new_lines[ $target_language ] = $settings['new_lines'] ? 1 : 0;
			}
			update_option( 'easy_language_summ_ai_target_languages_new_lines', $new_lines );
		}
	}

	/**
	 * On update to version 2.5.0 or newer.
	 *
	 * @return void
	 */
	public function version250(): void {
		// set embolden negative setting for each activated target language.
		if ( ! get_option( 'easy_language_summ_ai_target_languages_embolden_negative' ) ) {
			$target_languages   = Summ_Ai::get_instance()->get_supported_target_languages();
			$embolden_negatives = array();
			foreach ( $target_languages as $target_language => $settings ) {
				$embolden_negatives[ $target_language ] = $settings['embolden_negative'] ? 1 : 0;
			}
			update_option( 'easy_language_summ_ai_target_languages_embolden_negative', $embolden_negatives );
		}
	}

	/**
	 * On update to version 2.9.0 or newer.
	 *
	 * @return void
	 */
	public function version290(): void {
		// set the new option to "user".
		update_option( 'easy_language_capito_account_type', 'user' );
	}

	/**
	 * On update to version 3.0.0 or newer.
	 *
	 * @return void
	 */
	public function version300(): void {
		/**
		 * Migrate SUMM AI target language settings.
		 */
		$target_languages = get_option( 'easy_language_summ_ai_target_languages' );
		if ( is_array( $target_languages ) ) {
			foreach ( $target_languages as $target_language => $settings ) {
				update_option( 'easy_language_summ_ai_target_languages_' . $target_language, $settings );
			}
		}
		$target_languages_separator = get_option( 'easy_language_summ_ai_target_languages_separator' );
		if ( is_array( $target_languages_separator ) ) {
			foreach ( $target_languages_separator as $target_language => $settings ) {
				update_option( 'easy_language_summ_ai_target_languages_' . $target_language . '_separator', $settings );
			}
		}
		$target_languages_new_lines = get_option( 'easy_language_summ_ai_target_languages_new_lines' );
		if ( is_array( $target_languages_new_lines ) ) {
			foreach ( $target_languages_new_lines as $target_language => $settings ) {
				update_option( 'easy_language_summ_ai_target_languages_' . $target_language . '_new_line', $settings );
			}
		}
		$target_languages_embolden_negative = get_option( 'easy_language_summ_ai_target_languages_embolden_negative' );
		if ( is_array( $target_languages_embolden_negative ) ) {
			foreach ( $target_languages_embolden_negative as $target_language => $settings ) {
				update_option( 'easy_language_summ_ai_target_languages_' . $target_language . '_embolden_negative', $settings );
			}
		}

		// delete the old settings.
		delete_option( 'easy_language_summ_ai_target_languages' );
		delete_option( 'easy_language_summ_ai_target_languages_separator' );
		delete_option( 'easy_language_summ_ai_target_languages_new_lines' );
		delete_option( 'easy_language_summ_ai_target_languages_embolden_negative' );

		// migrate the interval settings for our schedules.
		$this->migrate_interval_setting( 'easy_language_capito_quota_interval' );
		$this->migrate_interval_setting( 'easy_language_summ_ai_quota_interval' );
		$this->migrate_interval_setting( 'easy_language_automatic_simplification' );
	}

	/**
	 * Add the prefix of our own intervals to the value of an interval setting.
	 *
	 * Only changes the value if the prefix is missing and the prefixed value is a registered interval.
	 *
	 * @param string $option The option name.
	 *
	 * @return void
	 */
	private function migrate_interval_setting( string $option ): void {
		// get the actual value.
		$value = get_option( $option, '' );

		// bail if value is not a string or empty.
		if ( ! is_string( $value ) || '' === $value ) {
			return;
		}

		// get the prefix of our intervals.
		$prefix = Intervals::get_instance()->get_prefix();

		// bail if the value has already the prefix.
		if ( str_starts_with( $value, $prefix ) ) {
			return;
		}

		// bail if the prefixed value is not a registered interval.
		if ( ! array_key_exists( $prefix . $value, Intervals::get_instance()->get_intervals_for_settings() ) ) {
			return;
		}

		// save the prefixed value.
		update_option( $option, $prefix . $value );
	}

	/**
	 * Return the registered interval to use for the automatic simplification.
	 *
	 * Uses the saved setting, if it is a registered interval, otherwise our 15-minute interval.
	 *
	 * @return string
	 */
	private function get_automatic_simplification_interval(): string {
		// get the list of registered intervals.
		$schedules = wp_get_schedules();

		// get the saved setting.
		$value = get_option( 'easy_language_automatic_simplification', '' );
		if ( is_string( $value ) && '' !== $value ) {
			// use the setting if it is a registered interval.
			if ( array_key_exists( $value, $schedules ) ) {
				return $value;
			}

			// use the setting with our prefix if this is a registered interval (for settings before 3.0.0).
			$prefixed_value = Intervals::get_instance()->get_prefix() . $value;
			if ( array_key_exists( $prefixed_value, $schedules ) ) {
				return $prefixed_value;
			}
		}

		// return our default interval.
		return Intervals::get_instance()->get_prefix() . '15minutly';
	}

	/**
	 * Schedule the automatic simplification with the given interval and log any error.
	 *
	 * @param string $interval The interval to use.
	 *
	 * @return void
	 */
	private function schedule_automatic_simplification( string $interval ): void {
		$result = wp_schedule_event( time(), $interval, 'easy_language_automatic_simplification', array(), true );

		// bail if scheduling was successful.
		if ( ! is_wp_error( $result ) ) {
			return;
		}

		// log the error.
		/* translators: %1$s will be replaced by an error message. */
		Log::get_instance()->add_log( sprintf( __( 'Automatic simplification could not be scheduled: %1$s', 'easy-language' ), $result->get_error_message() ), 'error' );
	}

	/**
	 * On update to version 3.3.0 or newer.
	 *
	 * Anonymizes the API tokens in the saved requests of our APIs.
	 *
	 * @return void
	 */
	public function version330(): void {
		global $wpdb;

		// get the table names of our APIs (see the Request classes of each API).
		$prefix = Db::get_instance()->get_wpdb_prefix();
		$tables = array(
			$prefix . 'easy_language_capito',
			$prefix . 'easy_language_chatgpt',
			$prefix . 'easy_language_summ_ai',
		);

		// define the search pattern for not anonymized entries.
		$like            = '%' . $wpdb->esc_like( '"Authorization":"' ) . '%';
		$like_anonymized = '%' . $wpdb->esc_like( '"Authorization":"anonymized"' ) . '%';

		foreach ( $tables as $table ) {
			// bail if table does not exist.
			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				continue;
			}

			// loop through the entries in batches.
			$last_id = 0;
			do {
				// @phpstan-ignore argument.type
				$entries = $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `request` FROM %i WHERE `id` > %d AND `request` LIKE %s AND `request` NOT LIKE %s ORDER BY `id` ASC LIMIT %d', array( $table, $last_id, $like, $like_anonymized, 500 ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

				// bail if no entries found.
				if ( ! is_array( $entries ) || empty( $entries ) ) {
					break;
				}

				// get the count of entries in this batch.
				$entries_count = count( $entries );

				foreach ( $entries as $entry ) {
					$last_id = absint( $entry['id'] );

					// replace the value of the Authorization header (also on not valid JSON, e.g. truncated entries).
					$request = preg_replace( '/"Authorization":"(?:[^"\\\\]|\\\\.)*"/', '"Authorization":"anonymized"', (string) $entry['request'] );

					// bail if nothing has been changed.
					if ( ! is_string( $request ) || $request === $entry['request'] ) {
						continue;
					}

					// save the anonymized request.
					$wpdb->update( $table, array( 'request' => $request ), array( 'id' => $last_id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				}
			} while ( 500 === $entries_count );
		}
	}

	/**
	 * Set the lock for running updates.
	 *
	 * Removes an orphaned lock, which is older than 10 minutes.
	 *
	 * Hint: we do not use add_option() as it does not fail on existing entries in the database.
	 *
	 * @return bool True if the lock has been set, false if another process is running the update.
	 */
	private function set_lock(): bool {
		global $wpdb;

		// remove an orphaned lock.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $wpdb->options . '` WHERE `option_name` = %s AND CAST(`option_value` AS UNSIGNED) < %d', array( self::LOCK_OPTION, time() - self::LOCK_TIMEOUT ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		// set the lock (fails if the lock exists, as the option name is unique).
		$result = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO `' . $wpdb->options . '` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s)', array( self::LOCK_OPTION, (string) time(), 'no' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		// clear the cache for this option.
		wp_cache_delete( self::LOCK_OPTION, 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		// return whether the lock has been set by this process.
		return 1 === $result;
	}

	/**
	 * Release the lock for running updates.
	 *
	 * @return void
	 */
	private function release_lock(): void {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * On update to version 3.0.2 or newer.
	 *
	 * @return void
	 */
	public function version302(): void {
		Rewrite::get_instance()->set_refresh();
	}

	/**
	 * To run on update to (exact) version 4.0.0.
	 *
	 * Existing installations keep the output of their existing language switchers (shortcodes, Elementor widgets
	 * and blocks inserted before this version) via the compatibility mode.
	 *
	 * @return void
	 */
	public function version400(): void {
		// enable the compatibility mode for the language switcher (this runs only once per site via the DB version).
		update_option( 'easy_language_switcher_compatibility', 1, true );
	}
}
