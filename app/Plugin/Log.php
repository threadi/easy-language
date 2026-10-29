<?php
/**
 * File for handler for logging in this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Db;
use WP_User;

/**
 * Handler for logging in this plugin.
 */
class Log {

	/**
	 * Instance of this object.
	 *
	 * @var ?Log
	 */
	private static ?Log $instance = null;

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
	public static function get_instance(): Log {
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
		return Db::get_instance()->get_wpdb_prefix() . 'easy_language_log';
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
            `log` longtext DEFAULT '' NOT NULL,
            `user_id` int(11) DEFAULT 0 NOT NULL,
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
	 * @param string $entry The entry-text.
	 * @param string $state The state (error or success).
	 *
	 * @return void
	 */
	public function add_log( string $entry, string $state ): void {
		global $wpdb;

		/**
		 * If debug is not enabled, just log errors.
		 */
		if ( 'error' !== $state && 0 === absint( get_option( 'easy_language_debug_mode', 0 ) ) ) {
			return;
		}

		/**
		 * Get active user.
		 */
		$user_id = 0;
		$user    = wp_get_current_user();
		if ( $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
			$user_id = $user->ID;
		}

		/**
		 * Insert log entry.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom log table of this plugin, no WP API available; entries change with every log entry, so no object cache
		$wpdb->insert(
			$this->get_table_name(),
			array(
				'time'    => gmdate( 'Y-m-d H:i:s' ),
				'log'     => $entry,
				'state'   => $state,
				'user_id' => $user_id,
				'blog_id' => get_current_blog_id(),
			)
		);

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

	/**
	 * Return the count of entries of the actual site.
	 *
	 * @return int
	 */
	public function count_entries(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom log table of this plugin, no WP API available; entries change with every log entry, so no object cache
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `blog_id` = %d', array( $this->get_table_name(), get_current_blog_id() ) ) ) );
	}

	/**
	 * Return the entries of the actual site for this log object.
	 *
	 * @param int $limit  Max count of entries to return (0 for all).
	 * @param int $offset Count of entries to skip (only used with a limit).
	 *
	 * @return array<string,mixed>
	 */
	public function get_entries( int $limit = 0, int $offset = 0 ): array {
		global $wpdb;

		// define the allowed columns to order the table (request value => DB column).
		$allowed_order_by = array(
			'date' => 'time',
			'time' => 'time',
		);

		// order table.
		$order_by = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_string( $order_by ) || ! isset( $allowed_order_by[ $order_by ] ) ) {
			$order_by = 'date';
		}
		$order_by = $allowed_order_by[ $order_by ];
		$order    = strtoupper( (string) filter_input( INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		// get the limit (all entries if no limit is given).
		$limit_sql = '';
		$vars      = array( $this->get_table_name(), get_current_blog_id(), $order_by );
		if ( $limit > 0 ) {
			$limit_sql = ' LIMIT %d OFFSET %d';
			$vars[]    = $limit;
			$vars[]    = max( 0, $offset );
		}

		// get results in ascending order and return them.
		if ( 'ASC' === $order ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom log table of this plugin, no WP API available; entries change with every log entry, so no object cache; the limit is a fixed string with placeholders
			return $wpdb->get_results( $wpdb->prepare( 'SELECT `state`, `time` AS `date`, `log`, `user_id` FROM %i WHERE `blog_id` = %d ORDER BY %i ASC, `id` DESC' . $limit_sql, $vars ), ARRAY_A );
		}

		// get results in descending order and return them.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom log table of this plugin, no WP API available; entries change with every log entry, so no object cache; the limit is a fixed string with placeholders
		return $wpdb->get_results( $wpdb->prepare( 'SELECT `state`, `time` AS `date`, `log`, `user_id` FROM %i WHERE `blog_id` = %d ORDER BY %i DESC, `id` DESC' . $limit_sql, $vars ), ARRAY_A );
	}
}
