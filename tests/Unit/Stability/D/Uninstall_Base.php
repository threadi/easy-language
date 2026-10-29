<?php
/**
 * Base for tests which run the uninstallation of the plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\D;

use easyLanguage\Tests\easyLanguageTests;

/**
 * The uninstallation drops tables, which commits the transaction of the test in MySQL. So this base saves
 * the affected tables before and restores them afterward (same as in tests/Unit/Plugin/Uninstall.php).
 */
abstract class Uninstall_Base extends easyLanguageTests {
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
}
