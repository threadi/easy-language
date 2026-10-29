<?php
/**
 * File for handler for logging API actions in this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Db;

/**
 * Handler for logging in this plugin.
 */
class Log_Api {

	/**
	 * Instance of this object.
	 *
	 * @var ?Log_Api
	 */
	private static ?Log_Api $instance = null;

	/**
	 * Constructor for Logging-Handler.
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
	public static function get_instance(): Log_Api {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return table-name.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		return Db::get_instance()->get_wpdb_prefix() . 'easy_language_api_log';
	}

	/**
	 * Create the logging-table in the database.
	 *
	 * @return void
	 */
	public function create_table(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// table for import-log.
		$sql = 'CREATE TABLE ' . $this->get_table_name() . " (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `api` varchar(40) DEFAULT '' NOT NULL,
            `http_state` varchar(3) DEFAULT '' NOT NULL,
            `request` longtext DEFAULT '' NOT NULL,
            `response` longtext DEFAULT '' NOT NULL,
            `state` varchar(40) DEFAULT '' NOT NULL,
            `blog_id` bigint(20) DEFAULT 0 NOT NULL,
            UNIQUE KEY id (id),
            KEY blog_id (blog_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Remove the log-table on uninstallation.
	 *
	 * @return void
	 */
	public function delete_table(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removes the custom table of this plugin during uninstallation, no WP API available for custom tables
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $this->get_table_name() ) );
	}

	/**
	 * Add a single log-entry.
	 *
	 * @param string $api The used API.
	 * @param int    $http_state The http-state of the request.
	 * @param string $request The request as a dump.
	 * @param string $response The response as a dump.
	 * @return void
	 */
	public function add_log( string $api, int $http_state, string $request, string $response ): void {
		global $wpdb;

		// define state depending on http-state.
		$state = 'error';
		if ( 200 === $http_state ) {
			$state = 'success';
		}

		/**
		 * If debug is not enabled, just log errors.
		 */
		if ( 'error' !== $state && 0 === absint( get_option( 'easy_language_debug_mode', 0 ) ) ) {
			return;
		}

		/**
		 * Insert log entry.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom log table of this plugin, no WP API available; entries change with every log entry, so no object cache
		$wpdb->insert(
			$this->get_table_name(),
			array(
				'time'       => gmdate( 'Y-m-d H:i:s' ),
				'api'        => $api,
				'http_state' => $http_state,
				'request'    => $request,
				'response'   => $response,
				'state'      => $state,
				'blog_id'    => get_current_blog_id(),
			)
		);

		// log error.
		if ( $wpdb->last_error ) {
			/* translators: %1$s will be replaced by the error code. */
			Log::get_instance()->add_log( sprintf( __( 'Error during adding API log entry: %1$s', 'easy-language' ), '<code>' . $wpdb->last_error . '</code>' ), 'error' );
		}

		/**
		 * Run cleanup of logs.
		 */
		$this->clean_log();
	}

	/**
	 * Delete all entries that are older than X days.
	 *
	 * @return void
	 */
	private function clean_log(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom log table of this plugin, no WP API available; entries change with every log entry, so no object cache
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE `blog_id` = %d AND `time` < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)', array( $this->get_table_name(), get_current_blog_id(), max( 1, absint( get_option( 'easy_language_log_max_age', 50 ) ) ) ) ) );
	}

	/**
	 * Delete all entries of the actual site (e.g. for a reset of the plugin in a multisite).
	 *
	 * @return void
	 */
	public function delete_entries_of_site(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom log table of this plugin, no WP API available; entries change with every log entry, so no object cache
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE `blog_id` = %d', array( $this->get_table_name(), get_current_blog_id() ) ) );
	}

	/**
	 * Assign the entries without site (from versions before 4.0.0) to the main site.
	 *
	 * @return void
	 */
	public function migrate_entries_without_site(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom log table of this plugin, no WP API available; entries change with every log entry, so no object cache
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `blog_id` = %d WHERE `blog_id` = %d', array( $this->get_table_name(), get_main_site_id(), 0 ) ) );
	}
}
