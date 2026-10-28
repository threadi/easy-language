<?php
/**
 * Tests for class easyLanguage\Plugin\Uninstall.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Apis\No_Api\No_Api;
use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Plugin\Helper;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the uninstallation of the plugin.
 *
 * The uninstallation drops tables, which commits the transaction of the test in MySQL. So this test saves
 * the affected tables before and restores them afterward, so the following tests find the plugin and the
 * data as before.
 */
class Uninstall extends easyLanguageTests {
	/**
	 * The saved tables: name => CREATE statement.
	 *
	 * @var array<string,string>
	 */
	private array $saved_tables = array();

	/**
	 * The saved state of the static properties of the plugin classes (e.g. their singleton objects and caches).
	 *
	 * @var array<int,array{0: \ReflectionProperty, 1: object|null, 2: mixed}>
	 */
	private array $saved_properties = array();

	/**
	 * Save the static properties of the loaded classes of the plugin and of Easy Settings, including the properties
	 * of their objects (two levels deep), so caches like the existence of the tables could be restored.
	 *
	 * @return void
	 */
	private function save_object_state(): void {
		$this->saved_properties = array();
		$seen                   = array();
		foreach ( get_declared_classes() as $class_name ) {
			if ( ! $this->is_own_class( $class_name ) ) {
				continue;
			}
			foreach ( ( new \ReflectionClass( $class_name ) )->getProperties( \ReflectionProperty::IS_STATIC ) as $property ) {
				if ( $property->getDeclaringClass()->getName() !== $class_name ) {
					continue;
				}
				$property->setAccessible( true );
				if ( ! $property->isInitialized() ) {
					continue;
				}
				$value                    = $property->getValue();
				$this->saved_properties[] = array( $property, null, $value );
				if ( is_object( $value ) ) {
					$this->save_properties_of_object( $value, $seen, 2 );
				}
			}
		}
	}

	/**
	 * Save the properties of the given object and of the own objects in them.
	 *
	 * @param object            $obj   The object.
	 * @param array<int,bool>   $seen  The IDs of the objects already saved.
	 * @param int               $depth How deep to save objects in properties.
	 *
	 * @return void
	 */
	private function save_properties_of_object( object $obj, array &$seen, int $depth ): void {
		if ( isset( $seen[ spl_object_id( $obj ) ] ) || ! $this->is_own_class( get_class( $obj ) ) ) {
			return;
		}
		$seen[ spl_object_id( $obj ) ] = true;

		// collect the properties of the class and its parents.
		$reflection = new \ReflectionClass( $obj );
		do {
			foreach ( $reflection->getProperties() as $property ) {
				if ( $property->isStatic() || $property->getDeclaringClass()->getName() !== $reflection->getName() ) {
					continue;
				}
				$property->setAccessible( true );
				if ( ! $property->isInitialized( $obj ) ) {
					continue;
				}
				$value                    = $property->getValue( $obj );
				$this->saved_properties[] = array( $property, $obj, $value );
				if ( $depth > 0 && is_object( $value ) ) {
					$this->save_properties_of_object( $value, $seen, $depth - 1 );
				}
			}
			$reflection = $reflection->getParentClass();
		} while ( $reflection );
	}

	/**
	 * Restore the saved properties.
	 *
	 * @return void
	 */
	private function restore_object_state(): void {
		foreach ( $this->saved_properties as $entry ) {
			list( $property, $obj, $value ) = $entry;
			if ( $property->isReadOnly() ) {
				continue;
			}
			if ( null === $obj ) {
				$property->setValue( null, $value );
			} else {
				$property->setValue( $obj, $value );
			}
		}
		$this->saved_properties = array();
	}

	/**
	 * Return whether the given class belongs to this plugin or its packages.
	 *
	 * @param string $class_name The class name.
	 *
	 * @return bool
	 */
	private function is_own_class( string $class_name ): bool {
		return str_starts_with( $class_name, 'easyLanguage\\' ) && ! str_starts_with( $class_name, 'easyLanguage\\Tests\\' )
			|| str_starts_with( $class_name, 'easySettingsForWordPress\\' );
	}

	/**
	 * Return the name of the backup table for the given table.
	 *
	 * @param string $table The table name.
	 *
	 * @return string
	 */
	private function get_backup_name( string $table ): string {
		return substr( 'bak_' . $table, 0, 64 );
	}

	/**
	 * Save the tables which are changed by the uninstallation.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		global $wpdb;

		// the test framework turns CREATE and DROP TABLE into temporary tables; this test needs the real tables.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		// collect the tables: options, posts and their meta and all tables of this plugin.
		$tables = array( $wpdb->options, $wpdb->posts, $wpdb->postmeta );
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'easy_language' ) . '%' ) ) as $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$tables[] = (string) $table;
		}

		// save structure and content of each table.
		foreach ( $tables as $table ) {
			$create = $wpdb->get_row( $wpdb->prepare( 'SHOW CREATE TABLE %i', $table ), ARRAY_N ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->saved_tables[ $table ] = (string) $create[1];
			$backup                       = $this->get_backup_name( $table );
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $backup ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $backup, $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i SELECT * FROM %i', $backup, $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		// save the state of the objects of the plugin (singletons and their caches).
		$this->save_object_state();

		// do not delete the files of attachments (e.g. the language icons), they are restored with the tables.
		add_filter( 'wp_delete_file', '__return_empty_string' );
	}

	/**
	 * Restore the saved tables and the state of the plugin.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_filter( 'wp_delete_file', '__return_empty_string' );

		// restore each saved table with its structure and content.
		foreach ( $this->saved_tables as $table => $create ) {
			$backup = $this->get_backup_name( $table );
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $create ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i SELECT * FROM %i', $table, $backup ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $backup ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->saved_tables = array();

		// restore the state of the objects of the plugin.
		$this->restore_object_state();

		// reload caches, roles and the capabilities of the current user from the restored tables.
		wp_cache_flush();
		wp_roles()->for_site();
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			wp_set_current_user( 0 );
			wp_set_current_user( $user_id );
		}

		parent::tear_down();
	}

	/**
	 * Create an original page with a simplification and some unrelated data.
	 *
	 * @return array<string,int>
	 */
	private function create_data(): array {
		// the original page and its simplification.
		$original_id      = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Original', 'post_content' => '<p>Ein komplizierter Text.</p>' ) );
		$original         = Helper::get_object( $original_id, 'page' );
		$this->assertInstanceOf( Post_Object::class, $original );
		$simplification = $original->add_simplification_object( 'de_LS', No_Api::get_instance(), true );
		$this->assertInstanceOf( Post_Object::class, $simplification );

		// a switcher for the classic menu.
		$switcher_id      = self::factory()->post->create( array( 'post_type' => EASY_LANGUAGE_CPT_SWITCHER, 'post_title' => 'Switcher' ) );

		// unrelated data.
		$unrelated_id     = self::factory()->post->create( array( 'post_type' => 'post', 'post_title' => 'Unrelated' ) );
		update_post_meta( $unrelated_id, 'unrelated_meta', 'keep' );
		update_option( 'unrelated_option', 'keep' );

		// transients and schedules of this plugin.
		set_transient( 'easy_language_refresh_rewrite_rules', 1 );
		set_transient( 'easy_language_simplification_process_counts', array( 1 ) );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'easy_language_automatic_simplification' );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'easy_language_capito_request_quota' );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'easy_language_summ_ai_request_quota' );

		return array(
			'original'       => $original_id,
			'simplification' => $simplification->get_id(),
			'switcher'       => $switcher_id,
			'unrelated'      => $unrelated_id,
		);
	}

	/**
	 * The uninstallation removes the simplified objects and the markers on the originals, but keeps the originals.
	 *
	 * @return void
	 */
	public function test_simplified_objects_are_removed(): void {
		$ids = $this->create_data();
		$this->assertNotEmpty( get_post_meta( $ids['original'], 'easy_language_simplified_in', true ) );

		\easyLanguage\Plugin\Uninstall::get_instance()->run();
		wp_cache_flush();

		$this->assertNull( get_post( $ids['simplification'] ) );
		$this->assertNull( get_post( $ids['switcher'] ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $ids['original'] ) );
		$this->assertEmpty( get_post_meta( $ids['original'], 'easy_language_simplified_in', true ) );
		$this->assertEmpty( get_post_meta( $ids['original'], 'easy_language_text_language', true ) );
	}

	/**
	 * The uninstallation removes the tables, options, transients and schedules of the plugin.
	 *
	 * @return void
	 */
	public function test_plugin_data_is_removed(): void {
		global $wpdb;
		$this->create_data();

		\easyLanguage\Plugin\Uninstall::get_instance()->run();
		wp_cache_flush();

		// no table of this plugin remains.
		$this->assertSame( array(), (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'easy_language' ) . '%' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		// the main options are removed.
		foreach ( array( 'easyLanguageVersion', 'easy_language_api', 'easy_language_post_types', 'easy_language_capito_api_key', 'easy_language_chatgpt_api_key', 'easy_language_icons' ) as $option ) {
			$this->assertFalse( get_option( $option ), 'option ' . $option . ' is not removed' );
		}

		// the transients are removed.
		$this->assertFalse( get_transient( 'easy_language_refresh_rewrite_rules' ) );
		$this->assertFalse( get_transient( 'easy_language_simplification_process_counts' ) );

		// the schedules are removed.
		foreach ( array( 'easy_language_automatic_simplification', 'easy_language_capito_request_quota', 'easy_language_summ_ai_request_quota' ) as $hook ) {
			$this->assertFalse( wp_next_scheduled( $hook ), 'schedule ' . $hook . ' is not removed' );
		}
	}

	/**
	 * No option of this plugin remains after the uninstallation.
	 *
	 * @return void
	 */
	public function test_no_options_remain(): void {
		global $wpdb;
		$this->create_data();

		\easyLanguage\Plugin\Uninstall::get_instance()->run();

		// collect all options of this plugin, except transients (they are checked separately).
		$options = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT `option_name` FROM %i WHERE ( `option_name` LIKE %s OR `option_name` LIKE %s ) AND `option_name` NOT LIKE %s', $wpdb->options, 'easy\_language%', 'easyLanguage%', '\_transient%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		// the installation hash is kept by intention (see Installer: "will never be removed or changed").
		$options = array_values( array_diff( $options, array( EASY_LANGUAGE_HASH ) ) );

		$this->assertSame( array(), $options );
	}

	/**
	 * The role and the capabilities of this plugin are removed.
	 *
	 * @return void
	 */
	public function test_role_and_capabilities_are_removed(): void {
		$this->assertNotNull( get_role( 'el_simplifier' ) );

		\easyLanguage\Plugin\Uninstall::get_instance()->run();

		$this->assertNull( get_role( 'el_simplifier' ) );
		$administrator = get_role( 'administrator' );
		$this->assertInstanceOf( \WP_Role::class, $administrator );
		foreach ( \easyLanguage\Plugin\Init::get_instance()->get_capabilities( 'el_simplifier', 'el_simplifier' ) as $capability ) {
			$this->assertFalse( $administrator->has_cap( $capability ), 'capability ' . $capability . ' is not removed' );
		}
	}

	/**
	 * Data which does not belong to this plugin is kept.
	 *
	 * @return void
	 */
	public function test_unrelated_data_is_kept(): void {
		$ids = $this->create_data();

		\easyLanguage\Plugin\Uninstall::get_instance()->run();
		wp_cache_flush();

		$this->assertInstanceOf( \WP_Post::class, get_post( $ids['unrelated'] ) );
		$this->assertSame( 'keep', get_post_meta( $ids['unrelated'], 'unrelated_meta', true ) );
		$this->assertSame( 'keep', get_option( 'unrelated_option' ) );
		$this->assertNotFalse( get_option( 'siteurl' ) );
		$this->assertNotNull( get_role( 'administrator' ) );
	}
}
