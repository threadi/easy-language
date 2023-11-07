<?php
/**
 * File for handler for logging in this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

use easyLanguage\Multilingual_plugins\Easy_Language\Db;

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
	private function __construct() {
		global $wpdb;

		// set the table-name.
		$wpdb->easy_language_log_table = DB::get_instance()->get_wpdb_prefix() . 'easy_language_log';
	}

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
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
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
		$sql = "CREATE TABLE $wpdb->easy_language_log_table (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `api` varchar(40) DEFAULT '' NOT NULL,
            `http_state` varchar(3) DEFAULT '' NOT NULL,
            `request` text DEFAULT '' NOT NULL,
            `response` text DEFAULT '' NOT NULL,
            `state` varchar(40) DEFAULT '' NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Remove log-table on uninstallation.
	 *
	 * @return void
	 */
	public function delete_table(): void {
		global $wpdb;
		$wpdb->query( sprintf( 'DROP TABLE IF EXISTS %s', $wpdb->easy_language_log_table ) );
	}

	/**
	 * Add a single log-entry.
	 *
	 * @param string $api The used API.
	 * @param int    $http_state The http-state of the request.
	 * @param string $request The request as dump.
	 * @param string $response The response as dump.
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
		if ( 0 === absint( get_option( 'easy_language_debug_mode', 0 ) ) && 'error' !== $state ) {
			return;
		}

		/**
		 * Insert log entry.
		 */
		$wpdb->insert(
			$wpdb->easy_language_log_table,
			array(
				'time'       => gmdate( 'Y-m-d H:i:s' ),
				'api'        => $api,
				'http_state' => $http_state,
				'request'    => $request,
				'response'   => $response,
				'state'      => $state,
			)
		);

		/**
		 * Run cleanup of logs.
		 */
		$this->clean_log();
	}

	/**
	 * Delete all entries which are older than X days.
	 *
	 * @return void
	 */
	private function clean_log(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $wpdb->easy_language_log_table . '` WHERE 1 = %d AND `time` < DATE_SUB(NOW(), INTERVAL %d DAY)', array( 1, absint( get_option( 'easy_language_log_max_age', 50 ) ) ) ) );
	}
}
