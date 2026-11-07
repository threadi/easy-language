<?php
/**
 * Tests for the reset and uninstallation of the plugin in a multisite.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\D;

use easyLanguage\EasyLanguage\Db;
use easyLanguage\Plugin\Log;
use easyLanguage\Plugin\Log_Api;
use easyLanguage\Plugin\Uninstall;

/**
 * Object to test the reset of a single site and the uninstallation.
 */
class Uninstall_Reset extends Uninstall_Base {
	/**
	 * Insert a text with a simplification, which is used by the given sites.
	 *
	 * @param string          $text     The text.
	 * @param array<int,int>  $blog_ids The IDs of the sites using this text.
	 *
	 * @return int The ID of the text.
	 */
	private function add_text( string $text, array $blog_ids ): int {
		global $wpdb;
		$db = Db::get_instance();
		$wpdb->insert( $db->get_table_name_originals(), array( 'original' => $text, 'hash' => md5( $text ), 'lang' => 'de_DE', 'state' => 'simplified' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$id = (int) $wpdb->insert_id;
		$wpdb->insert( $db->get_table_name_simplifications(), array( 'oid' => $id, 'simplification' => 'simple ' . $text, 'language' => 'de_LS' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		foreach ( $blog_ids as $blog_id ) {
			$wpdb->insert( $db->get_table_name_originals_objects(), array( 'oid' => $id, 'object_type' => 'page', 'object_id' => 4711, 'blog_id' => $blog_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
		return $id;
	}

	/**
	 * Return the count of rows in the given table matching the given column value.
	 *
	 * @param string     $table  The table.
	 * @param string     $column The column.
	 * @param int        $value  The value.
	 *
	 * @return int
	 */
	private function count_rows( string $table, string $column, int $value ): int {
		global $wpdb;
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE %i = %d', $table, $column, $value ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * The reset of one site in a multisite keeps the shared tables and the data of the other sites.
	 *
	 * @return void
	 */
	public function test_reset_of_site_keeps_data_of_other_sites(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		global $wpdb;
		$db         = Db::get_instance();
		$this_blog  = get_current_blog_id();
		$other_blog = $this_blog + 1000;

		// texts: only of this site, shared by both sites and only of the other site.
		$own    = $this->add_text( 'Text only in this site', array( $this_blog ) );
		$shared = $this->add_text( 'Text in both sites', array( $this_blog, $other_blog ) );
		$other  = $this->add_text( 'Text only in other site', array( $other_blog ) );

		// log entries of both sites.
		foreach ( array( $this_blog, $other_blog ) as $blog_id ) {
			$wpdb->insert( Log::get_instance()->get_table_name(), array( 'time' => gmdate( 'Y-m-d H:i:s' ), 'log' => 'log ' . $blog_id, 'state' => 'error', 'blog_id' => $blog_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( Log_Api::get_instance()->get_table_name(), array( 'time' => gmdate( 'Y-m-d H:i:s' ), 'api' => 'capito', 'state' => 'error', 'blog_id' => $blog_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		// do not delete unused texts via Text::delete() (it counts only the objects of the actual site, see report).
		update_option( 'easy_language_delete_unused_simplifications', 0 );

		// reset the actual site (as via settings or WP CLI).
		Uninstall::get_instance()->run();

		// the shared tables still exist.
		$tables = array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->base_prefix . 'easy_language_' ) . '%' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( array( $db->get_table_name_originals(), $db->get_table_name_originals_objects(), $db->get_table_name_simplifications(), Log::get_instance()->get_table_name(), Log_Api::get_instance()->get_table_name() ) as $table ) {
			$this->assertContains( $table, $tables );
		}

		// the data of this site is removed.
		$this->assertSame( 0, $this->count_rows( $db->get_table_name_originals_objects(), 'blog_id', $this_blog ) );
		$this->assertSame( 0, $this->count_rows( $db->get_table_name_originals(), 'id', $own ) );
		$this->assertSame( 0, $this->count_rows( $db->get_table_name_simplifications(), 'oid', $own ) );
		$this->assertSame( 0, $this->count_rows( Log::get_instance()->get_table_name(), 'blog_id', $this_blog ) );
		$this->assertSame( 0, $this->count_rows( Log_Api::get_instance()->get_table_name(), 'blog_id', $this_blog ) );

		// the data of the other site is kept.
		$this->assertSame( 2, $this->count_rows( $db->get_table_name_originals_objects(), 'blog_id', $other_blog ) );
		foreach ( array( $shared, $other ) as $id ) {
			$this->assertSame( 1, $this->count_rows( $db->get_table_name_originals(), 'id', $id ) );
			$this->assertSame( 1, $this->count_rows( $db->get_table_name_simplifications(), 'oid', $id ) );
		}
		$this->assertSame( 1, $this->count_rows( Log::get_instance()->get_table_name(), 'blog_id', $other_blog ) );
		$this->assertSame( 1, $this->count_rows( Log_Api::get_instance()->get_table_name(), 'blog_id', $other_blog ) );
	}

	/**
	 * The network-wide uninstallation removes the shared tables.
	 *
	 * @return void
	 */
	public function test_network_wide_uninstall_removes_tables(): void {
		global $wpdb;

		Uninstall::get_instance()->run( true );

		$this->assertSame( array(), (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->base_prefix . 'easy_language_' ) . '%' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
