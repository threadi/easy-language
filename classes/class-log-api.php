<?php
/**
 * File for handler for logging in this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use wpdb;

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
	 * Database-object
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Name for own database-table.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor for Logging-Handler.
	 */
	private function __construct() {
		global $wpdb;

		// get the db-connection.
		$this->wpdb = $wpdb;

		// set the table-name.
		$this->table_name = DB::get_instance()->get_wpdb_prefix() . 'easy_language_log';
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
		$charset_collate = $this->wpdb->get_charset_collate();

		// table for import-log.
		$sql = "CREATE TABLE $this->table_name (
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
        $sql = 'DROP TABLE IF EXISTS '.$this->table_name;
        $this->wpdb->query($sql);
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
		// define state depending on http-state.
		$state = 'error';
		if ( 200 === $http_state ) {
			$state = 'success';
		}

		/**
		 * If debug is not enabled, just log errors.
		 */
		if( 0 === absint(get_option( 'easy_language_debug_mode', 0 )) && 'error' !== $state ) {
			return;
		}

		/**
		 * Insert log entry.
		 */
		$this->wpdb->insert(
			$this->table_name,
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
		$sql = sprintf( 'DELETE FROM `' . $this->table_name . '` WHERE `time` < DATE_SUB(NOW(), INTERVAL %d DAY)', get_option( 'easy_language_log_max_age', 50 ) );
		$this->wpdb->query( $sql );
	}
}
