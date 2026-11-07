<?php
/**
 * File for DB-handling.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Log;

/**
 * DB-object for our own plugin.
 */
class Db {
	/**
	 * Name of the transient which caches the counts of texts for the progress in the admin bar.
	 */
	public const TRANSIENT_PROCESS_COUNTS = 'easy_language_simplification_process_counts';

	/**
	 * Instance of this object.
	 *
	 * @var ?Db
	 */
	private static ?Db $instance = null;

	/**
	 * List of text-objects.
	 *
	 * @var array<string,Text>
	 */
	private array $texts = array();

	/**
	 * List of simplification-objects.
	 *
	 * @var array<string,Text>
	 */
	private array $simplifications = array();

	/**
	 * Whether the tables of this object exist (null if not checked in this request).
	 *
	 * @var ?bool
	 */
	private ?bool $tables_exist = null;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Db {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create tables.
	 *
	 * @return void
	 */
	public function create_table(): void {
		global $wpdb;

		// prepare the creations.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		// table for original-texts to translate.
		$sql = 'CREATE TABLE ' . $this->get_table_name_originals() . " (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `original` longtext DEFAULT '' NOT NULL,
            `field` varchar(32) DEFAULT '' NOT NULL,
            `html` varchar(32) DEFAULT '' NOT NULL,
            `hash` varchar(32) DEFAULT '' NOT NULL,
            `lang` varchar(20) DEFAULT '' NOT NULL,
            `state` varchar(40) DEFAULT '' NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";
		dbDelta( $sql );

		// table for objects which are using original-texts.
		$sql = 'CREATE TABLE ' . $this->get_table_name_originals_objects() . " (
            `oid` mediumint(9) NOT NULL,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `object_type` varchar(100) DEFAULT '' NOT NULL,
            `object_id` int(100) DEFAULT 0 NOT NULL,
            `order` int(100) DEFAULT 0 NOT NULL,
            `blog_id` int(100) DEFAULT 0 NOT NULL,
            `page_builder` varchar(100) DEFAULT '' NOT NULL,
            `state` varchar(40) DEFAULT '' NOT NULL
        ) $charset_collate;";
		dbDelta( $sql );

		// table for language- and api-specific simplifications of texts.
		$sql = 'CREATE TABLE ' . $this->get_table_name_simplifications() . " (
            `oid` mediumint(9) NOT NULL,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `simplification` longtext DEFAULT '' NOT NULL,
            `hash` varchar(32) DEFAULT '' NOT NULL,
            `language` varchar(20) DEFAULT '' NOT NULL,
            `used_api` varchar(40) DEFAULT '' NOT NULL,
            `jobid` int(11) DEFAULT 0 NOT NULL,
            `user_id` int(11) DEFAULT 0 NOT NULL
        ) $charset_collate;";
		dbDelta( $sql );

		// check the existence of the tables again on the next request for it.
		$this->tables_exist = null;
	}

	/**
	 * Delete tables.
	 *
	 * @return void
	 */
	public function delete_tables(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removes the custom table of this plugin during uninstallation, no WP API available for custom tables
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $this->get_table_name_originals() ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removes the custom table of this plugin during uninstallation, no WP API available for custom tables
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $this->get_table_name_originals_objects() ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removes the custom table of this plugin during uninstallation, no WP API available for custom tables
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $this->get_table_name_simplifications() ) );

		// the tables are gone now.
		$this->tables_exist = false;
	}

	/**
	 * Return whether the tables of this object exist in the database.
	 *
	 * The result is cached for the actual request, so the check runs only once per request.
	 * The tables could be missing if the plugin has not been activated via WordPress (e.g. files
	 * copied into the plugin directory or a database with another prefix).
	 *
	 * @return bool
	 */
	public function tables_exist(): bool {
		// use the cached result.
		if ( is_bool( $this->tables_exist ) ) {
			return $this->tables_exist;
		}

		global $wpdb;

		// get all tables of this plugin with one request.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- checks the existence of the custom tables of this plugin, the result is cached in this object for the request
		$existing_tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->get_wpdb_prefix() . 'easy_language_' ) . '%' ) );

		// check if our tables are in this list.
		$this->tables_exist = empty(
			array_diff(
				array_map(
					'strtolower',
					array(
						$this->get_table_name_originals(),
						$this->get_table_name_originals_objects(),
						$this->get_table_name_simplifications(),
					)
				),
				array_map( 'strtolower', array_map( 'strval', $existing_tables ) )
			)
		);

		// return the result.
		return $this->tables_exist;
	}

	/**
	 * Delete the cached counts of texts, so the progress in the admin bar is up to date on the next request.
	 *
	 * Must be called on every change which affects the count of texts, their state or their simplifications.
	 *
	 * @return void
	 */
	public function clear_process_counts(): void {
		delete_transient( self::TRANSIENT_PROCESS_COUNTS );
	}

	/**
	 * Return whether at least one text exists in the database.
	 *
	 * @return bool
	 */
	public function has_entries(): bool {
		// bail if our tables do not exist.
		if ( ! $this->tables_exist() ) {
			return false;
		}

		global $wpdb;

		// check for a single entry.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table of this plugin, no WP API available; content changes with every simplification, so no object cache
		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT `id` FROM %i LIMIT 1', $this->get_table_name_originals() ) );
	}

	/**
	 * Adds new text for simplification in the database. Returns the text-object for the given text.
	 *
	 * @param string $text The original text.
	 * @param string $source_language The language of this text.
	 * @param string $field The field.
	 * @param bool   $html True if this text contains html-code.
	 *
	 * @return false|Text
	 */
	public function add( string $text, string $source_language, string $field, bool $html ): false|Text {
		global $wpdb;

		// bail if the text is empty.
		if ( empty( $text ) ) {
			return false;
		}

		// if no language is set, get the WP-language.
		if ( empty( $source_language ) ) {
			$source_language = Helper::get_wp_lang();
		}

		// save the text in db and return the resulting text-object.
		$query = array(
			'time'     => gmdate( 'Y-m-d H:i:s' ),
			'original' => $text,
			'hash'     => $this->get_string_hash( $text ),
			'lang'     => $source_language,
			'state'    => 'to_simplify',
			'html'     => $html,
			'field'    => $field,
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table of this plugin, no WP API available; content changes with every simplification, so no object cache
		$wpdb->insert( $this->get_table_name_originals(), $query );

		// log error.
		if ( $wpdb->last_error ) {
			Log::get_instance()->add_log( __( 'Error during adding entry in DB: ', 'easy-language' ) . $wpdb->last_error, 'error' );
		}

		// get DB-id.
		$id = $wpdb->insert_id;
		if ( absint( $id ) ) {
			// the counts of texts have been changed.
			$this->clear_process_counts();

			// optimized the text-object.
			$text_obj = new Text( $id );
			$text_obj->set_original( $text );
			$text_obj->set_source_language( $source_language );

			// return the text-object.
			return $text_obj;
		}

		// return error.
		return false;
	}

	/**
	 * Return all actual entries as Easy_Language_Text-object-array.
	 *
	 * @param array<string,mixed> $filter Set filter (optional).
	 * @param array<string,mixed> $order Order list (optional).
	 * @param int                 $limit Limit the list (optional).
	 * @param int                 $offset Offset for the list, only used together with $limit (optional).
	 *
	 * @return array<Text> Array of Text-objects
	 */
	public function get_entries( array $filter = array(), array $order = array(), int $limit = 0, int $offset = 0 ): array {
		global $wpdb;

		// bail if our tables do not exist.
		if ( ! $this->tables_exist() ) {
			return array();
		}

		// get the query parts depending on the given filter.
		$query_parts = $this->get_query_parts( $filter, $order );

		// get the vars for the prepared statement in the order of their placeholders (after the table name).
		$vars = $query_parts['vars'];

		// limit the list of entries.
		$sql_limit = '';
		if ( absint( $limit ) > 0 ) {
			$sql_limit = ' LIMIT %d';
			$vars[]    = absint( $limit );

			// add the offset.
			if ( absint( $offset ) > 0 ) {
				$sql_limit .= ' OFFSET %d';
				$vars[]     = absint( $offset );
			}
		}

		// define the statement.
		$sql = 'SELECT `id`, `original`, `lang`' . $query_parts['select'] . ' FROM %i AS o' . $query_parts['join'] . $query_parts['where'] . $query_parts['order'] . $sql_limit;

		// get the table names for the statement.
		$tables = array( $this->get_table_name_originals() );

		// use a sub-query for joined tables, if the object columns are not needed, to get each text only once (the joins could return multiple rows for one text).
		if ( ! empty( $query_parts['join'] ) && empty( $filter['not_locked'] ) && empty( $filter['object_state'] ) ) {
			$sql      = 'SELECT o.`id`, o.`original`, o.`lang` FROM %i AS o WHERE o.`id` IN ( SELECT o.`id` FROM %i AS o' . $query_parts['join'] . $query_parts['where'] . ' )' . $query_parts['order'] . $sql_limit;
			$tables[] = $this->get_table_name_originals();
		}

		// get entries.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql consists only of fixed SQL fragments with placeholders (see get_query_parts(), ORDER BY from a whitelist), all values are passed as prepare() arguments.; custom table of this plugin, no WP API available; content changes with every simplification, so no object cache
		$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$tables, ...$vars ), ARRAY_A );

		// bail if results are empty.
		if ( empty( $results ) ) {
			return array();
		}

		// initialize the return array.
		$return = array();

		// list of added text IDs to add each text only once.
		$added_ids = array();

		// loop through results and add them to the list.
		foreach ( $results as $result ) {
			// bail if this text has already been added (e.g. as it is used by multiple objects).
			if ( isset( $added_ids[ absint( $result['id'] ) ] ) ) {
				continue;
			}

			// only add post-type-objects if not_locked is set and if they are not locked.
			$add = true;
			if ( ! empty( $filter['not_locked'] ) ) {
				$object = Helper::get_object( absint( $result['object_id'] ), $result['object_type'] );
				if ( $object ) {
					$add = ! $object->is_locked();
				}
			}
			if ( ! empty( $filter['object_state'] ) && false !== $add ) {
				$object = Helper::get_object( absint( $result['object_id'] ), $result['object_type'] );
				if ( $object ) {
					$add = $object->has_state( $filter['object_state'] );
				}
			}

			// add entry to the list.
			if ( $add ) {
				// create the Text-object for this text.
				$obj = new Text( $result['id'] );
				$obj->set_original( $result['original'] );
				$obj->set_source_language( $result['lang'] );

				// add the Text-object to the list.
				$return[] = $obj;

				// mark the text as added.
				$added_ids[ absint( $result['id'] ) ] = true;
			}
		}

		// return the list.
		return $return;
	}

	/**
	 * Return the count of entries matching the given filter.
	 *
	 * @param array<string,mixed> $filter Set filter (optional).
	 *
	 * @return int
	 */
	public function count_entries( array $filter = array() ): int {
		global $wpdb;

		// use the full loading if filters are used which could only be checked via PHP.
		if ( ! empty( $filter['not_locked'] ) || ! empty( $filter['object_state'] ) ) {
			return count( $this->get_entries( $filter ) );
		}

		// bail if our tables do not exist.
		if ( ! $this->tables_exist() ) {
			return 0;
		}

		// get the query parts depending on the given filter.
		$query_parts = $this->get_query_parts( $filter, array() );

		// define the statement.
		// count each text only once, as the joins could return multiple rows for one text.
		$sql = 'SELECT COUNT(DISTINCT o.`id`) FROM %i AS o' . $query_parts['join'] . $query_parts['where'];

		// get the count.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql consists only of fixed SQL fragments with placeholders (see get_query_parts()), all values are passed as prepare() arguments.; custom table of this plugin, no WP API available; content changes with every simplification, so no object cache
		$count = $wpdb->get_var( $wpdb->prepare( $sql, $this->get_table_name_originals(), ...$query_parts['vars'] ) );

		// return the count.
		return absint( $count );
	}

	/**
	 * Return the IDs of all entries.
	 *
	 * @return array<int,int>
	 */
	public function get_entry_ids(): array {
		global $wpdb;

		// bail if our tables do not exist.
		if ( ! $this->tables_exist() ) {
			return array();
		}

		// get the IDs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table of this plugin, no WP API available; content changes with every simplification, so no object cache
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT `id` FROM %i ORDER BY `id` ASC', $this->get_table_name_originals() ) );

		// return them as integers.
		return array_map( 'absint', $ids );
	}

	/**
	 * Return the source and target languages used by entries matching the given filter.
	 *
	 * @param array<string,mixed> $filter Set filter (optional).
	 *
	 * @return array<string,array<int,string>>
	 */
	public function get_languages_of_entries( array $filter = array() ): array {
		global $wpdb;

		// bail if our tables do not exist.
		if ( ! $this->tables_exist() ) {
			return array(
				'source' => array(),
				'target' => array(),
			);
		}

		// get the query parts depending on the given filter.
		$query_parts = $this->get_query_parts( $filter, array() );

		// get the source languages.
		$sql = 'SELECT DISTINCT o.`lang` FROM %i AS o' . $query_parts['join'] . $query_parts['where'];
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql consists only of fixed SQL fragments with placeholders (see get_query_parts()), all values are passed as prepare() arguments.; custom table of this plugin, no WP API available; content changes with every simplification, so no object cache
		$source_languages = $wpdb->get_col( $wpdb->prepare( $sql, $this->get_table_name_originals(), ...$query_parts['vars'] ) );

		// get the target languages.
		$sql = 'SELECT DISTINCT sl.`language` FROM %i AS sl WHERE sl.`oid` IN ( SELECT o.`id` FROM %i AS o' . $query_parts['join'] . $query_parts['where'] . ' )';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql consists only of fixed SQL fragments with placeholders (see get_query_parts()), all values are passed as prepare() arguments.; custom table of this plugin, no WP API available; content changes with every simplification, so no object cache
		$target_languages = $wpdb->get_col( $wpdb->prepare( $sql, $this->get_table_name_simplifications(), $this->get_table_name_originals(), ...$query_parts['vars'] ) );

		// return the resulting lists.
		return array(
			'source' => array_map( 'strval', $source_languages ),
			'target' => array_map( 'strval', $target_languages ),
		);
	}

	/**
	 * Return the parts of the SQL-statement for the given filter and order.
	 *
	 * The returned SQL fragments contain only fixed SQL and placeholders. Table names are used via the
	 * placeholder %i. The values for all placeholders in "join" and "where" are returned in "vars"
	 * in the order of their placeholders ("join" first, then "where").
	 *
	 * @param array<string,mixed> $filter The filter.
	 * @param array<string,mixed> $order The order.
	 *
	 * @return array{select: string, join: string, where: string, order: string, vars: array<int,mixed>}
	 */
	private function get_query_parts( array $filter, array $order ): array {
		global $wpdb;

		// set the filter depending on parameters this function receives.
		$sql_select = '';
		$sql_join   = array();
		$sql_where  = ' WHERE 1 = %d';

		// set ordering: default goes for title first, then other fields (to show fast proceed as titles are smaller than other texts).
		$sql_order = " ORDER BY IF( o.field = 'title', 0, 1 ) ASC";

		// allowed orderings by date (request value => SQL fragment).
		$allowed_date_orders = array(
			'asc'  => ' ORDER BY o.time ASC',
			'desc' => ' ORDER BY o.time DESC',
		);
		if ( ! empty( $order ) && ! empty( $order['order_by'] ) && 'date' === $order['order_by'] && ! empty( $order['order'] ) && is_string( $order['order'] ) && isset( $allowed_date_orders[ $order['order'] ] ) ) {
			$sql_order = $allowed_date_orders[ $order['order'] ];
		}

		// init vars-array for prepared statement (only for placeholders in WHERE, the JOIN-vars are added at the end).
		$vars = array( '1' );

		if ( ! empty( $filter ) ) {
			if ( ! empty( $filter['id'] ) ) {
				$sql_where .= ' AND o.id = %d';
				$vars[]     = $filter['id'];
			}
			if ( ! empty( $filter['original'] ) ) {
				$sql_where .= ' AND o.original = %s';
				$vars[]     = $filter['original'];
			}
			if ( ! empty( $filter['hash'] ) ) {
				$sql_where .= ' AND o.hash = %s';
				$vars[]     = $filter['hash'];
			}
			if ( ! empty( $filter['state'] ) ) {
				$sql_where .= ' AND o.state = %s';
				$vars[]     = $filter['state'];
			}
			if ( ! empty( $filter['lang'] ) ) {
				if ( ! empty( $filter['target_lang'] ) ) {
					$sql_join[ $this->get_table_name_simplifications() ] = ' LEFT JOIN %i s ON s.oid = o.id';
					$sql_where .= ' AND (s.language = %s OR o.lang = %s)';
					$vars[]     = $filter['target_lang'];
				} else {
					$sql_where .= ' AND o.lang = %s';
				}
				$vars[] = $filter['lang'];
			}
			if ( ! empty( $filter['field'] ) ) {
				$sql_where .= ' AND o.field = %s';
				$vars[]     = $filter['field'];
			}
			if ( ! empty( $filter['object_id'] ) ) {
				$sql_join[ $this->get_table_name_originals_objects() ] = ' INNER JOIN %i oo ON oo.oid = o.id';
				$sql_select .= ', oo.object_id';
				$sql_where  .= ' AND oo.object_id = %d';
				$vars[]      = absint( $filter['object_id'] );
				if ( ! empty( $filter['object_state'] ) ) {
					$sql_where .= ' AND oo.state = %s';
					$vars[]     = $filter['object_state'];
				}
			}
			if ( ! empty( $filter['object_type'] ) ) {
				$sql_join[ $this->get_table_name_originals_objects() ] = ' INNER JOIN %i oo ON oo.oid = o.id';
				$sql_select .= ', oo.object_type';
				$sql_where  .= ' AND oo.object_type = %s';
				$vars[]      = $filter['object_type'];
			}
			if ( ! empty( $filter['simplification_hash'] ) && ! empty( $filter['simplification_lang'] ) ) {
				$sql_join[ $this->get_table_name_simplifications() ] = ' INNER JOIN %i s ON s.oid = o.id';
				$sql_where .= ' AND s.hash = %s AND s.language = %s';
				$vars[]     = $filter['simplification_hash'];
				$vars[]     = $filter['simplification_lang'];
			}
			if ( ! empty( $filter['not_locked'] ) ) {
				$sql_join[ $this->get_table_name_originals_objects() ] = ' INNER JOIN %i oo ON oo.oid = o.id';
				$sql_select .= ', oo.object_id, oo.object_type';
			}
			if ( ! empty( $filter['not_prevented'] ) ) {
				$sql_join[ $this->get_table_name_originals_objects() ] = ' INNER JOIN %i oo ON oo.oid = o.id';
				$sql_select .= ', oo.object_id, oo.object_type';

				// exclude texts of post-objects where the automatic simplification is prevented.
				$sql_where .= ' AND NOT EXISTS ( SELECT 1 FROM %i pm INNER JOIN %i pp ON pp.ID = pm.post_id WHERE pm.post_id = oo.object_id AND pp.post_type = oo.object_type AND oo.blog_id = %d AND pm.meta_key = %s AND pm.meta_value = %s )';
				$vars[]     = $wpdb->postmeta;
				$vars[]     = $wpdb->posts;
				$vars[]     = absint( get_current_blog_id() );
				$vars[]     = 'easy_language_prevent_automatic_mode';
				$vars[]     = '1';
			}
			if ( ! empty( $filter['object_state'] ) ) {
				$sql_join[ $this->get_table_name_originals_objects() ] = ' INNER JOIN %i oo ON oo.oid = o.id';
				$sql_select .= ', oo.object_id, oo.object_type';
			}
			if ( ! empty( $filter['object_not_state'] ) ) {
				$sql_join[ $this->get_table_name_originals_objects() ] = ' INNER JOIN %i oo ON oo.oid = o.id';
				$sql_select .= ', oo.object_id, oo.object_type';

				// exclude texts of post-objects with the given post-state.
				$sql_where .= ' AND NOT EXISTS ( SELECT 1 FROM %i ps WHERE ps.ID = oo.object_id AND ps.post_type = oo.object_type AND oo.blog_id = %d AND ps.post_status = %s )';
				$vars[]     = $wpdb->posts;
				$vars[]     = absint( get_current_blog_id() );
				$vars[]     = $filter['object_not_state'];
			}
			if ( ! empty( $filter['has_simplification'] ) ) {
				$sql_join[ $this->get_table_name_simplifications() ] = ' INNER JOIN %i s ON s.oid = o.id';
			}

			// use only the objects of the actual blog if the objects are joined (they are saved for all blogs in one table).
			if ( isset( $sql_join[ $this->get_table_name_originals_objects() ] ) ) {
				$sql_where .= ' AND oo.blog_id = %d';
				$vars[]     = absint( get_current_blog_id() );
			}
		}

		// return the parts.
		return array(
			'select' => $sql_select,
			'join'   => implode( ' ', $sql_join ),
			'where'  => $sql_where,
			'order'  => $sql_order,
			// each JOIN contains exactly one placeholder for its table name, which is the key of the JOIN.
			'vars'   => array_merge( array_keys( $sql_join ), $vars ),
		);
	}

	/**
	 * Reset all simplifications.
	 *
	 * The texts which have been simplified are marked to simplify them again.
	 *
	 * Hint: on multisite the tables are used by all blogs and the simplifications are not saved per blog,
	 * so this resets the simplifications of all blogs.
	 *
	 * @return void
	 */
	public function reset_simplifications(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- custom table of this plugin, no WP API available
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $this->get_table_name_simplifications() ) );

		// mark the simplified texts to simplify them again, as they have no simplification anymore.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table of this plugin, no WP API available; content changes with every simplification, so no object cache
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `state` = %s WHERE `state` IN ( %s, %s )', $this->get_table_name_originals(), 'to_simplify', 'in_use', 'processing' ) );

		// the counts of simplified texts have been changed.
		$this->clear_process_counts();
	}

	/**
	 * Return the text-object for the given text in the given language.
	 *
	 * @param string $text The original text we search.
	 * @param string $source_language The source language we search.
	 * @return bool|Text
	 */
	public function get_entry_by_text( string $text, string $source_language ): bool|Text {
		// return the already loaded object.
		if ( ! empty( $this->texts[ $this->get_string_hash( $text . $source_language ) ] ) ) {
			return $this->texts[ $this->get_string_hash( $text . $source_language ) ];
		}

		// get object via DB-request.
		$query     = array(
			'hash' => $this->get_string_hash( $text ),
			'lang' => $source_language,
		);
		$text_objs = $this->get_entries( $query );
		if ( ! empty( $text_objs ) ) {
			// secure text-object.
			$this->texts[ $this->get_string_hash( $text . $source_language ) ] = $text_objs[0];

			// return text-object.
			return $text_objs[0];
		}

		// return false if no entry could be found for this text.
		return false;
	}

	/**
	 * Return the text-object for the given simplification in the given language.
	 *
	 * @param string $simplification The searched simplification.
	 * @param string $language The language we use to search.
	 *
	 * @return bool|Text
	 */
	public function get_entry_by_simplification( string $simplification, string $language ): bool|Text {
		// check if the object has already been loaded.
		if ( empty( $this->simplifications[ $this->get_string_hash( $simplification . $language ) ] ) ) {
			// get object via DB-request.
			$query     = array(
				'simplification_hash' => $this->get_string_hash( $simplification ),
				'simplification_lang' => $language,
			);
			$text_objs = $this->get_entries( $query );
			if ( ! empty( $text_objs ) ) {
				// secure text-object.
				$this->simplifications[ $this->get_string_hash( $simplification . $language ) ] = $text_objs[0];

				// return text-object.
				return $text_objs[0];
			}

			// return false if no simplification could be found for this simplification.
			return false;
		}
		return $this->simplifications[ $this->get_string_hash( $simplification . $language ) ];
	}

	/**
	 * Return a hash for a given string.
	 *
	 * @param string $text The text to hash.
	 *
	 * @return string
	 */
	public function get_string_hash( string $text ): string {
		return md5( $text );
	}

	/**
	 * Return the table name for the original text.
	 *
	 * @return string
	 */
	public function get_table_name_originals(): string {
		return $this->get_wpdb_prefix() . 'easy_language_originals';
	}

	/**
	 * Return the table name for the objects where the original texts are used.
	 *
	 * @return string
	 */
	public function get_table_name_originals_objects(): string {
		return $this->get_wpdb_prefix() . 'easy_language_originals_objects';
	}

	/**
	 * Return the table name for simplifications.
	 *
	 * @return string
	 */
	public function get_table_name_simplifications(): string {
		return $this->get_wpdb_prefix() . 'easy_language_simplifications';
	}

	/**
	 * Return WP-DB-prefix.
	 *
	 * On multisite return the prefix of the main blog as we save all simplifications in the main db
	 * to prevent double simplifications.
	 *
	 * @return string
	 */
	public function get_wpdb_prefix(): string {
		global $wpdb;
		$prefix = $wpdb->prefix;
		if ( is_multisite() ) {
			$prefix = $wpdb->base_prefix;
		}
		return $prefix;
	}
}
