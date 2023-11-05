<?php
/**
 * File for output collected simplification texts.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Helper;
use easyLanguage\Languages;
use WP_List_Table;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handler for log-output in backend.
 */
class Texts_To_Simplify_Table extends WP_List_Table {
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
			'used_in' 		  => __( 'used in', 'easy-language' ),
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
		// order table.
		$order_by = ( isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ) ) ) ? $_REQUEST['orderby'] : 'date';
		$order   = ( isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array( 'asc', 'desc' ) ) ) ? $_REQUEST['order'] : 'desc';

		// get results
		return DB::get_instance()->get_entries( Init::get_instance()->get_filter_for_entries_to_simplify(), array( 'order_by' => $order_by, 'order' => $order ) );
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
	 * @param  array|object  $item        Data.
	 * @param  String $column_name - Current column name.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		// bail if item is not an entry-text.
		if( !( $item instanceof Text ) ) {
			return '';
		}

		// get languages-object.
		$languages_obj = Languages::get_instance();

		// filter column name.
		$column_name = apply_filters( 'easy_language_simplification_table_to_simplify', $column_name, $item );

		// bail if column-name is not set.
		if( false === $column_name ) {
			return '';
		}

		// show content depending on column.
		switch ( $column_name ) {
			case 'options':
				$options = array(
					'<span class="dashicons dashicons-translation" title="'.__( 'Simplify now only with Easy Language Pro.', 'easy-language' ).'">&nbsp;</span>'
				);

				$filtered_options = apply_filters( 'easy_language_simplification_to_simplify_table_options', $options, $item->get_id() );

				// return html-output.
				return implode( '', $filtered_options );

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
				return implode(',', $item->get_target_languages());

			// show original text.
			case 'original':
				return wp_strip_all_tags( $item->get_original() );

			// show hint for pro in used in column.
			case 'used_in':
				return '<span class="pro-marker">'.__( 'Only in Pro', 'easy-language' ).'</span>';

			// fallback if no column has been matched.
			default:
				return print_r( $item, true );
		}
	}

	/**
	 * Message to be displayed when there are no items
	 *
	 * @since 3.1.0
	 */
	public function no_items(): void {
		echo esc_html__( 'No texts found which should be simplified.', 'easy-language' );
	}
}
