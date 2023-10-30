<?php
/**
 * File for output collected simplification texts.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Apis;
use easyLanguage\Helper;
use easyLanguage\Languages;
use WP_List_Table;
use WP_User;
use wpdb;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handler for log-output in backend.
 */
class Texts_To_Simplify_Table extends WP_List_Table {

	/**
	 * Database-object.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Name for own database-table with simplifications.
	 *
	 * @var string
	 */
	private string $table_translations;

	/**
	 * Name for own database-table with simplifications.
	 *
	 * @var string
	 */
	private string $table_originals;

	/**
	 * Constructor for Logging-Handler.
	 */
	public function __construct() {
		// call parent constructor.
		parent::__construct( array( 'plural' => 'text-translations-table' ) );
	}

	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'options'         => '',
			'date'            => __( 'date', 'easy-language' ),
			'source_language' => __( 'source language', 'easy-language' ),
			'target_language' => __( 'target language', 'easy-language' ),
			'original'        => __( 'original', 'easy-language' ),
		);
	}

	/**
	 * Get the table data.
	 *
	 * @return array
	 */
	private function table_data(): array {
		$query = array(
			'state' => 'to_simplify',
		);
		return DB::get_instance()->get_entries( $query );
	}

	/**
	 * Get the log-table for the table-view.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $this->table_data();
	}

	/**
	 * Define which columns are hidden.
	 *
	 * @return array
	 */
	public function get_hidden_columns(): array {
		return array();
	}

	/**
	 * Define the sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return array( 'date' => array( 'date', false ) );
	}

	/**
	 * Define what data to show on each column of the table.
	 *
	 * @param  array  $item        Data.
	 * @param  String $column_name - Current column name.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		// get languages-object.
		$languages_obj = Languages::get_instance();

		// show content depending on column.
		switch ( $column_name ) {
			case 'options':
				$do_simplification = add_query_arg(
					array(
						'action' => 'easy_language_get_simplification_of_entry',
						'id'     => $item->get_id(),
						'nonce'  => wp_create_nonce( 'easy-language-get-simplification-of-entry' ),
					),
					get_admin_url() . 'admin.php'
				);
				return '<a href="' . esc_url( $do_simplification ) . '" class="dashicons dashicons-translation">&nbsp;</a>';;

			// get date of this entry.
			case 'date':
				return Helper::get_format_date_time( $item->get_date() );

			// get source language.
			case 'source_language':
				$language = $item->get_source_language();
				$languages = $languages_obj->get_possible_source_languages();
				if ( ! empty( $languages[ $language ] ) ) {
					return $languages[ $language ]['label'];
				}
				return __( 'Unknown', 'easy-language' );

			// get target languages.
			case 'target_language':
				$item_languages = array();
				$languages = $languages_obj->get_possible_target_languages();
				foreach( $item->get_objects() as $object ) {
					$post_object = new Post_Object( $object['object_id'] );
					$language = array_key_first($post_object->get_language());
					if ( ! empty( $languages[ $language ] ) ) {
						$item_languages[$language] = $languages[ $language ]['label'];
					}
				}

				// return resulting list.
				return implode(',', $item_languages);

			case 'original':
				return wp_strip_all_tags( $item->get_original() );

			// fallback if no column has been matched.
			default:
				return print_r( $item, true );
		}
	}
}
