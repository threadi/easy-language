<?php
/**
 * File for a simple lock between parallel processes.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Lock between parallel processes (e.g. overlapping WP-Cron runs), based on a unique option name.
 *
 * The lock is set atomically via INSERT IGNORE, so only one process gets it. A lock older than the given
 * timeout is treated as orphaned (e.g. if the process has been stopped) and is removed.
 */
class Lock {

	/**
	 * Try to set the lock with the given name.
	 *
	 * @param string $name The name of the option used as lock.
	 * @param int    $timeout Seconds after which an existing lock is treated as orphaned.
	 *
	 * @return bool True if the lock has been set by this process, false if another process holds it.
	 */
	public static function acquire( string $name, int $timeout ): bool {
		global $wpdb;

		// remove an orphaned lock.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the lock must be checked directly in the database, a cached value could be outdated
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE `option_name` = %s AND CAST(`option_value` AS UNSIGNED) < %d', array( $wpdb->options, $name, time() - max( 1, $timeout ) ) ) );

		// set the lock (fails if the lock exists, as the option name is unique).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the lock must be set atomically in the database
		$result = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s)', array( $wpdb->options, $name, (string) time(), 'off' ) ) );

		// clear the cache for this option.
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		// return whether the lock has been set by this process.
		return 1 === $result;
	}

	/**
	 * Release the lock with the given name.
	 *
	 * @param string $name The name of the option used as lock.
	 *
	 * @return void
	 */
	public static function release( string $name ): void {
		delete_option( $name );
	}
}
