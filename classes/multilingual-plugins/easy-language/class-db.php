<?php
/**
 * File for DB-handling.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Helper;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * @var array
	 */
	private array $texts = array();

	/**
	 * Constructor for this object.
	 */
	private function __construct() {
		global $wpdb;

		// set the table-name for original-texts.
		$wpdb->easy_language_originals = $this->get_wpdb_prefix() . 'easy_language_originals';

		// set the table-name for objects which are using original-texts.
		$wpdb->easy_language_originals_objects = $this->get_wpdb_prefix() . 'easy_language_originals_objects';

		// set the table-name for simplifications.
		$wpdb->easy_language_simplifications = $this->get_wpdb_prefix() . 'easy_language_simplifications';
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
			$current_blog = get_current_blog_id();
			switch_to_blog( get_current_site()->blog_id );
			switch_to_blog( $current_blog );
		}
		return $prefix;
	}

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
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
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
		$sql = "CREATE TABLE $wpdb->easy_language_originals (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `original` text DEFAULT '' NOT NULL,
            `field` varchar(32) DEFAULT '' NOT NULL,
            `hash` varchar(32) DEFAULT '' NOT NULL,
            `lang` varchar(5) DEFAULT '' NOT NULL,
            `state` varchar(40) DEFAULT '' NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";
		dbDelta( $sql );

		// table for objects which are using original-texts.
		$sql = "CREATE TABLE $wpdb->easy_language_originals_objects (
            `oid` mediumint(9) NOT NULL,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `object_type` varchar(100) DEFAULT '' NOT NULL,
            `object_id` int(100) DEFAULT 0 NOT NULL,
            `blog_id` int(100) DEFAULT 0 NOT NULL,
            `page_builder` varchar(100) DEFAULT '' NOT NULL,
            `state` varchar(40) DEFAULT '' NOT NULL
        ) $charset_collate;";
		dbDelta( $sql );

		// table for language- and api-specific simplifications of texts.
		$sql = "CREATE TABLE $wpdb->easy_language_simplifications (
            `oid` mediumint(9) NOT NULL,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `translation` text DEFAULT '' NOT NULL,
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
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->easy_language_originals );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->easy_language_originals_objects );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->easy_language_simplifications );
	}

	/**
	 * Adds new text for simplification in DB. Returns the text-object for the given text.
	 *
	 * @param string $text The original text.
	 * @param string $source_language The language of this text.
	 * @param string $field The field.
	 *
	 * @return false|Text
	 */
	public function add( string $text, string $source_language, string $field ): false|Text {
		global $wpdb;

		// bail if text is empty.
		if ( empty( $text ) ) {
			return false;
		}

		// if no language is set, get the WP-language.
		if ( empty( $source_language ) ) {
			$source_language = helper::get_wp_lang();
		}

		// save the text in db and return the resulting text-object.
		$query = array(
			'time'     => gmdate( 'Y-m-d H:i:s' ),
			'original' => $text,
			'hash'     => $this->get_string_hash( $text ),
			'lang'     => $source_language,
			'state'    => 'to_simplify',
			'field'    => $field,
		);
		$wpdb->insert( $wpdb->easy_language_originals, $query );

		// get DB-id.
		$id = $wpdb->insert_id;
		if ( absint( $id ) ) {
			// return text-object.
			return new Text( $id );
		}

		// return error.
		return false;
	}

	/**
	 * Return all actual entries as Easy_Language_Text-object-array.
	 *
	 * @param array $filter Optional filter.
	 * @return array
	 */
	public function get_entries( array $filter = array() ): array {
		global $wpdb;

		// initialize return array.
		$return = array();

		// set filter depending on parameters this function receives.
		$sql_select = '';
		$sql_join   = array();
		$sql_where  = ' WHERE 1 = %d';
		$vars       = array( '1' );
		if ( ! empty( $filter ) ) {
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
				$sql_where .= ' AND o.lang = %s';
				$vars[]     = $filter['lang'];
			}
			if ( ! empty( $filter['field'] ) ) {
				$sql_where .= ' AND o.field = %s';
				$vars[]     = $filter['field'];
			}
			if ( ! empty( $filter['object_id'] ) ) {
				$sql_join[ $wpdb->easy_language_originals_objects ] = ' INNER JOIN ' . $wpdb->easy_language_originals_objects . ' oo ON oo.oid = o.id';
				$sql_select                                        .= ', oo.object_id';
				$sql_where .= ' AND oo.object_id = %d';
				$sql_where .= ' AND oo.blog_id = %d';
				$vars[]     = absint( $filter['object_id'] );
				$vars[]     = absint( get_current_blog_id() );
				if ( ! empty( $filter['object_state'] ) ) {
					$sql_where .= ' AND oo.state = %s';
					$vars[]     = $filter['object_state'];
				}
			}
			if ( ! empty( $filter['object_type'] ) ) {
				$sql_join[ $wpdb->easy_language_originals_objects ] = ' INNER JOIN ' . $wpdb->easy_language_originals_objects . ' oo ON oo.oid = o.id';
				$sql_select                                        .= ', oo.object_type';
				$sql_where .= ' AND oo.object_type = %s';
				$sql_where .= ' AND oo.blog_id = %d';
				$vars[]     = $filter['object_type'];
				$vars[]     = absint( get_current_blog_id() );
			}
		}

		// define base-statement.
		$sql = 'SELECT `id`, `original`, `lang`%1$s FROM ' . $wpdb->easy_language_originals . ' AS o';

		// add additional result-rows.
		$sql = sprintf( $sql, $sql_select );

		// concat sql-statement.
		$sql = $sql . implode( ' ', $sql_join ) . $sql_where;

		// prepare SQL-statement.
		$prepared_sql = $wpdb->prepare( $sql, $vars );

		// get entries.
		$results = $wpdb->get_results( $prepared_sql, ARRAY_A );
		foreach ( $results as $result ) {
			// create Text-object for this text.
			$obj = new Text( $result['id'] );
			$obj->set_original( $result['original'] );
			$obj->set_source_language( $result['lang'] );

			// add the Text-object to the list.
			$return[] = $obj;
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
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->easy_language_simplifications );
	}

	/**
	 * Return the text-object for the given text in the given language
	 *
	 * @param string $text The original text we search.
	 * @param string $language The language we search.
	 * @return bool|Text
	 */
	public function get_entry_by_text( string $text, string $language ): bool|Text {
		// check if object has already been loaded.
		if ( empty( $this->texts[ md5( $text . $language ) ] ) ) {
			// get object via DB-request.
			$query     = array(
				'hash' => $this->get_string_hash( $text ),
				'lang' => $language,
			);
			$text_objs = $this->get_entries( $query );
			if ( ! empty( $text_objs ) ) {
				// secure text-object.
				$this->texts[ md5( $text . $language ) ] = $text_objs[0];

				// return text-object.
				return $text_objs[0];
			}
			return false;
		}
		return $this->texts[ md5( $text . $language ) ];
	}

	/**
	 * Get a hash for a given string.
	 *
	 * @param string $text The text to hash.
	 *
	 * @return string
	 */
	public function get_string_hash( string $text ): string {
		return md5( $text );
	}
}
