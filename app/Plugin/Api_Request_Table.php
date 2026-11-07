<?php
/**
 * File for the handling of the request tables of the APIs.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Helper for the request tables of SUMM AI, capito and ChatGPT.
 *
 * Each of these APIs saves its requests and responses in an own table. This helper defines its structure
 * and removes old entries, so the tables do not grow without limit.
 */
class Api_Request_Table {

	/**
	 * Return the SQL statement to create or update the request table with the given name.
	 *
	 * The duration is saved with milliseconds (it is measured via microtime()).
	 *
	 * @param string $table_name The name of the table.
	 *
	 * @return string
	 */
	public static function get_create_sql( string $table_name ): string {
		global $wpdb;

		// get the charset.
		$charset_collate = $wpdb->get_charset_collate();

		// return the statement.
		return "CREATE TABLE $table_name (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `request` longtext DEFAULT '' NOT NULL,
            `response` longtext DEFAULT '' NOT NULL,
            `duration` decimal(10,3) DEFAULT 0 NOT NULL,
            `httpstatus` int(11) DEFAULT 0 NOT NULL,
            `quota` int(11) DEFAULT 0 NOT NULL,
            `blog_id` int(11) DEFAULT 0 NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";
	}

	/**
	 * Create the request table with the given name or update its structure.
	 *
	 * @param string $table_name The name of the table.
	 *
	 * @return void
	 */
	public static function create( string $table_name ): void {
		// bail if no table name is given.
		if ( '' === $table_name ) {
			return;
		}

		// create or update the table.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::get_create_sql( $table_name ) );
	}

	/**
	 * Return the max age of entries in days (the same setting as for the logs).
	 *
	 * @return int
	 */
	public static function get_max_age(): int {
		$max_age = max( 1, absint( get_option( 'easy_language_log_max_age', 50 ) ) );

		/**
		 * Filter the max age in days of the entries in the request tables of the APIs.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param int $max_age The max age in days.
		 */
		return max( 1, absint( apply_filters( 'easy_language_api_requests_max_age', $max_age ) ) );
	}

	/**
	 * Delete the entries of the actual site, which are older than the max age.
	 *
	 * @param string $table_name The name of the table.
	 *
	 * @return void
	 */
	public static function cleanup( string $table_name ): void {
		global $wpdb;

		// bail if no table name is given.
		if ( '' === $table_name ) {
			return;
		}

		// delete the old entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom request table of the API, no WP API available; entries change with every request, so no object cache
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE `blog_id` = %d AND `time` < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)', array( $table_name, get_current_blog_id(), self::get_max_age() ) ) );
	}
}
