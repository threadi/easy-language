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
            UNIQUE KEY id (id)
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
		$wpdb->query( sprintf( 'DROP TABLE IF EXISTS %s', $this->get_table_name() ) );
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
		$wpdb->insert(
			$this->get_table_name(),
			array(
				'time'    => gmdate( 'Y-m-d H:i:s' ),
				'log'     => $entry,
				'state'   => $state,
				'user_id' => $user_id,
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
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $this->get_table_name() . '` WHERE 1 = %d AND `time` < DATE_SUB(NOW(), INTERVAL %d DAY)', array( 1, absint( get_option( 'easy_language_log_max_age', 50 ) ) ) ) );
	}

	/**
	 * Return base-SQL-statement to get api logs.
	 *
	 * @return string
	 */
	private function get_base_sql(): string {
		return 'SELECT `state`, `time` AS `date`, `log`, `user_id` FROM `' . $this->get_table_name() . '` WHERE 1 = %1$d';
	}

	/**
	 * Return the entries for this log object.
	 *
	 * @return array<string,mixed>
	 */
	public function get_entries(): array {
		global $wpdb;

		// order table.
		$order_by = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_null( $order_by ) ) {
			$order_by = 'date';
		}
		$order = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_null( $order ) ) {
			$order = sanitize_sql_orderby( (string) $order );
		} else {
			$order = 'DESC';
		}

		// define vars for prepared statement.
		$vars = array(
			1,
			$order_by,
			$order,
		);

		// get the SQL statement.
		$sql = $this->get_base_sql() . ' ORDER BY %2$s %3$s, `id` DESC';

		// get results and return them.
		return $wpdb->get_results( $wpdb->prepare( $sql, $vars ), ARRAY_A );
	}
}
