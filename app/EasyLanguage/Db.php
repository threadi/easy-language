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
	}

	/**
	 * Delete tables.
	 *
	 * @return void
	 */
	public function delete_tables(): void {
		global $wpdb;
		$wpdb->query( sprintf( 'DROP TABLE IF EXISTS `%1$s`', $this->get_table_name_originals() ) );
		$wpdb->query( sprintf( 'DROP TABLE IF EXISTS `%1$s`', $this->get_table_name_originals_objects() ) );
		$wpdb->query( sprintf( 'DROP TABLE IF EXISTS `%1$s`', $this->get_table_name_simplifications() ) );
	}

	/**
	 * Adds new text for simplification in DB. Returns the text-object for the given text.
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
		$wpdb->insert( $this->get_table_name_originals(), $query );

		// log error.
		if ( $wpdb->last_error ) {
			Log::get_instance()->add_log( __( 'Error during adding entry in DB: ', 'easy-language' ) . $wpdb->last_error, 'error' );
		}

		// get DB-id.
		$id = $wpdb->insert_id;
		if ( absint( $id ) ) {
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
	 *
	 * @return array<Text> Array of Text-objects
	 */
	public function get_entries( array $filter = array(), array $order = array(), int $limit = 0 ): array {
		global $wpdb;

		// set the filter depending on parameters this function receives.
		$sql_select = '';
		$sql_join   = array();
		$sql_where  = ' WHERE 1 = %d';

		// set ordering: default goes for title first, then other fields (to show fast proceed as titles are smaller than other texts).
		$sql_order = " ORDER BY IF( o.field = 'title', 0, 1 ) ASC";
		if ( ! empty( $order ) && ! empty( $order['order_by'] ) && 'date' === $order['order_by'] && ! empty( $order['order'] ) && in_array(
			$order['order'],
			array(
				'asc',
				'desc',
			),
			true
		) ) {
				$sql_order = ' ORDER BY o.time ' . sanitize_text_field( $order['order'] );
		}

		// init vars-array for prepared statement.
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
					$sql_join[ $this->get_table_name_simplifications() ] = ' LEFT JOIN ' . $this->get_table_name_simplifications() . ' s ON s.oid = o.id';
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
				$sql_join[ $this->get_table_name_originals_objects() ] = ' INNER JOIN ' . $this->get_table_name_originals_objects() . ' oo ON oo.oid = o.id';
				$sql_select .= ', oo.object_id';
				$sql_where  .= ' AND oo.object_id = %d';
				$sql_where  .= ' AND oo.blog_id = %d';
				$vars[]      = absint( $filter['object_id'] );
				$vars[]      = absint( get_current_blog_id() );
				if ( ! empty( $filter['object_state'] ) ) {
					$sql_where .= ' AND oo.state = %s';
					$vars[]     = $filter['object_state'];
				}
			}
			if ( ! empty( $filter['object_type'] ) ) {
				$sql_join[ $this->get_table_name_originals_objects() ] = ' INNER JOIN ' . $this->get_table_name_originals_objects() . ' oo ON oo.oid = o.id';
				$sql_select .= ', oo.object_type';
				$sql_where  .= ' AND oo.object_type = %s';
				$sql_where  .= ' AND oo.blog_id = %d';
				$vars[]      = $filter['object_type'];
				$vars[]      = absint( get_current_blog_id() );
			}
			if ( ! empty( $filter['simplification_hash'] ) && ! empty( $filter['simplification_lang']) ) {
				$sql_join[ $this->get_table_name_simplifications() ] = ' INNER JOIN ' . $this->get_table_name_simplifications() . ' s ON s.oid = o.id';
				$sql_where .= ' AND s.hash = %s AND s.language = %s';
				$vars[]     = $filter['simplification_hash'];
				$vars[]     = $filter['simplification_lang'];
			}
			if ( ! empty( $filter['not_locked'] ) ) {
				$sql_join[ $this->get_table_name_originals_objects() ] = ' INNER JOIN ' . $this->get_table_name_originals_objects() . ' oo ON oo.oid = o.id';
				$sql_select .= ', oo.object_id, oo.object_type';
			}
			if ( ! empty( $filter['not_prevented'] ) ) {
				$sql_join[ $this->get_table_name_originals_objects() ] = ' INNER JOIN ' . $this->get_table_name_originals_objects() . ' oo ON oo.oid = o.id';
				$sql_select .= ', oo.object_id, oo.object_type';
			}
			if ( ! empty( $filter['object_state'] ) ) {
				$sql_join[ $this->get_table_name_originals_objects() ] = ' INNER JOIN ' . $this->get_table_name_originals_objects() . ' oo ON oo.oid = o.id';
				$sql_select .= ', oo.object_id, oo.object_type';
			}
			if ( ! empty( $filter['object_not_state'] ) ) {
				$sql_join[ $this->get_table_name_originals_objects() ] = ' INNER JOIN ' . $this->get_table_name_originals_objects() . ' oo ON oo.oid = o.id';
				$sql_select .= ', oo.object_id, oo.object_type';
			}
			if ( ! empty( $filter['has_simplification'] ) ) {
				$sql_join[ $this->get_table_name_simplifications() ] = ' INNER JOIN ' . $this->get_table_name_simplifications() . ' s ON s.oid = o.id';
			}
		}

		// limit the list of entries.
		$sql_limit = '';
		if ( absint( $limit ) > 0 ) {
			$sql_limit = ' LIMIT ' . absint( $limit );
		}

		// define base-statement.
		$sql = 'SELECT `id`, `original`, `lang`%1$s FROM ' . $this->get_table_name_originals() . ' AS o';

		// add additional result-rows.
		$sql = sprintf( $sql, $sql_select );

		// concat sql-statement.
		$sql .= implode( ' ', $sql_join ) . $sql_where . $sql_order . $sql_limit;

		// prepare SQL-statement.
		$prepared_sql = $wpdb->prepare( $sql, $vars );

		// get entries.
		$results = $wpdb->get_results( $prepared_sql, ARRAY_A );

		// bail if results are empty.
		if( empty( $results ) ) {
			return array();
		}

		// initialize the return array.
		$return = array();

		// loop through results and add them to the list.
		foreach ( $results as $result ) {
			// only add post-type-objects if not_locked is set and if they are not locked.
			$add = true;
			if ( ! empty( $filter['not_locked'] ) ) {
				$object = Helper::get_object( absint( $result['object_id'] ), $result['object_type'] );
				if ( $object ) {
					$add = ! $object->is_locked();
				}
			}
			if ( ! empty( $filter['not_prevented'] ) && false !== $add ) {
				$object = Helper::get_object( absint( $result['object_id'] ), $result['object_type'] );
				if ( $object ) {
					$add = ! $object->is_automatic_mode_prevented();
				}
			}
			if ( ! empty( $filter['object_state'] ) && false !== $add ) {
				$object = Helper::get_object( absint( $result['object_id'] ), $result['object_type'] );
				if ( $object ) {
					$add = $object->has_state( $filter['object_state'] );
				}
			}
			if ( ! empty( $filter['object_not_state'] ) && false !== $add ) {
				$object = Helper::get_object( absint( $result['object_id'] ), $result['object_type'] );
				if ( $object ) {
					$add = ! $object->has_state( $filter['object_not_state'] );
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
			}
		}

		// return the list.
		return $return;
	}

	/**
	 * Reset all simplifications.
	 *
	 * @return void
	 */
	public function reset_simplifications(): void {
		global $wpdb;
		$wpdb->query( sprintf( 'TRUNCATE TABLE `%1$s`', $this->get_table_name_simplifications() ) );
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
