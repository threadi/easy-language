<?php
/**
 * Regression tests for the fixes in the logging of the plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\D;

use easyLanguage\Plugin\Log;
use easyLanguage\Plugin\Log_Api;
use easyLanguage\Plugin\Tables\Log_Api_Table;
use easyLanguage\Plugin\Update;
use easyLanguage\Tests\easyLanguageTests;
use ReflectionMethod;

/**
 * Object to test the fixes in the logging.
 */
class Log_Fixes extends easyLanguageTests {
	/**
	 * Insert a log entry directly.
	 *
	 * @param string $text    The text.
	 * @param int    $blog_id The site.
	 * @param int    $age     The age in seconds.
	 *
	 * @return void
	 */
	private function insert_log( string $text, int $blog_id, int $age = 0 ): void {
		global $wpdb;
		$wpdb->insert( Log::get_instance()->get_table_name(), array( 'time' => gmdate( 'Y-m-d H:i:s', time() - $age ), 'log' => $text, 'state' => 'error', 'blog_id' => $blog_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Return the texts of the log entries visible in the actual site.
	 *
	 * @return array<int,string>
	 */
	private function get_log_texts(): array {
		return array_column( Log::get_instance()->get_entries(), 'log' );
	}

	/**
	 * New log entries are saved for the actual site, only the entries of the actual site are shown and cleared.
	 *
	 * @return void
	 */
	public function test_log_entries_per_site(): void {
		global $wpdb;
		$other = 'el-test other site ' . uniqid();

		Log::get_instance()->add_log( 'el-test own entry', 'error' );
		$this->insert_log( $other, get_current_blog_id() + 1000 );

		// the new entry is saved with the actual site.
		$blog_id = $wpdb->get_var( $wpdb->prepare( 'SELECT `blog_id` FROM %i WHERE `log` = %s', Log::get_instance()->get_table_name(), 'el-test own entry' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertSame( get_current_blog_id(), absint( $blog_id ) );

		// only the entries of this site are visible.
		$this->assertContains( 'el-test own entry', $this->get_log_texts() );
		$this->assertNotContains( $other, $this->get_log_texts() );

		// only the entries of this site are deleted.
		Log::get_instance()->delete_entries_of_site();
		$this->assertSame( array(), $this->get_log_texts() );
		$this->assertSame( '1', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `log` = %s', Log::get_instance()->get_table_name(), $other ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * A max age of 0 does not delete all entries immediately, the cleanup affects only the actual site.
	 *
	 * @return void
	 */
	public function test_cleanup_uses_minimal_max_age(): void {
		update_option( 'easy_language_log_max_age', 0 );
		$other = 'el-test other site old ' . uniqid();

		// an entry of this site and of another site, which are 2 hours old.
		$this->insert_log( 'el-test 2 hours old', get_current_blog_id(), 2 * HOUR_IN_SECONDS );
		$this->insert_log( $other, get_current_blog_id() + 1000, 3 * DAY_IN_SECONDS );

		// adding an entry runs the cleanup.
		Log::get_instance()->add_log( 'el-test trigger', 'error' );

		$this->assertContains( 'el-test 2 hours old', $this->get_log_texts() );

		// the entries of other sites are not affected by the cleanup of this site.
		global $wpdb;
		$this->assertSame( '1', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `log` = %s', Log::get_instance()->get_table_name(), $other ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		// older entries of this site are removed.
		update_option( 'easy_language_log_max_age', 1 );
		$this->insert_log( 'el-test 2 days old', get_current_blog_id(), 2 * DAY_IN_SECONDS );
		Log::get_instance()->add_log( 'el-test trigger 2', 'error' );
		$this->assertNotContains( 'el-test 2 days old', $this->get_log_texts() );
	}

	/**
	 * The entries could be loaded in pages.
	 *
	 * @return void
	 */
	public function test_entries_with_limit(): void {
		Log::get_instance()->delete_entries_of_site();
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->insert_log( 'el-test ' . $i, get_current_blog_id(), 10 * $i );
		}

		$this->assertSame( 5, Log::get_instance()->count_entries() );
		$this->assertSame( array( 'el-test 1', 'el-test 2' ), array_column( Log::get_instance()->get_entries( 2 ), 'log' ) );
		$this->assertSame( array( 'el-test 5' ), array_column( Log::get_instance()->get_entries( 2, 4 ), 'log' ) );
		$this->assertCount( 5, Log::get_instance()->get_entries() );
	}

	/**
	 * The API log saves the site, the table shows only the APIs and entries of the actual site in pages.
	 *
	 * @return void
	 */
	public function test_api_log_per_site(): void {
		global $wpdb;
		$table = Log_Api::get_instance()->get_table_name();
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE `blog_id` = %d', $table, get_current_blog_id() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		// an entry for this site via the API and one of another site.
		Log_Api::get_instance()->add_log( 'summ_ai', 500, 'request', 'response' );
		$wpdb->insert( $table, array( 'time' => gmdate( 'Y-m-d H:i:s' ), 'api' => 'capito', 'state' => 'error', 'blog_id' => get_current_blog_id() + 1000 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		$this->assertSame( (string) get_current_blog_id(), $wpdb->get_var( $wpdb->prepare( 'SELECT `blog_id` FROM %i WHERE `api` = %s', $table, 'summ_ai' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		// prepare the table.
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		set_current_screen( 'settings_page_easy_language_settings' );
		$log_table = new Log_Api_Table();
		$log_table->prepare_items();

		// only the entry of this site is shown.
		$this->assertCount( 1, $log_table->items );
		$this->assertSame( 1, $log_table->get_pagination_arg( 'total_items' ) );

		// only the API of this site is in the views.
		$method = new ReflectionMethod( Log_Api_Table::class, 'get_views' );
		$method->setAccessible( true );
		$views = $method->invoke( $log_table );
		$this->assertArrayHasKey( 'summ_ai', $views );
		$this->assertArrayNotHasKey( 'capito', $views );

		set_current_screen( 'front' );
	}

	/**
	 * The update to 4.0.0 assigns the existing log entries to the main site.
	 *
	 * @return void
	 */
	public function test_update_assigns_old_entries_to_main_site(): void {
		global $wpdb;
		$this->insert_log( 'el-test without site', 0 );
		$id = (int) $wpdb->insert_id;

		Update::get_instance()->version400();

		$this->assertSame( (string) get_main_site_id(), $wpdb->get_var( $wpdb->prepare( 'SELECT `blog_id` FROM %i WHERE `id` = %d', Log::get_instance()->get_table_name(), $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
