<?php
/**
 * File for output any actual supported language and their icons.
 *
 * @package easy-language
 */

namespace easyLanguage;

use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use WP_List_Table;
use wpdb;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handler for log-output in backend.
 */
class Language_Icons_Table extends WP_List_Table {

	/**
	 * database-object
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Name for own database-table.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor for Logging-Handler.
	 */
	public function __construct() {
		// call parent constructor.
		parent::__construct();
	}

	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'language'    => __( 'Language', 'easy-language' ),
			'icon'     => __( 'Icon', 'easy-language' ),
		);
	}

	/**
	 * Get the table data
	 *
	 * @return array
	 */
	private function table_data(): array {
		$languages = array();
		foreach( array_merge( Languages::get_instance()->get_active_languages(), Languages::get_instance()->get_possible_source_languages() ) as $language_code => $language ) {
			$language['code'] = $language_code;
			$languages[] = $language;
		}
		return $languages;
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
	 * Define which columns are hidden
	 *
	 * @return array
	 */
	public function get_hidden_columns(): array {
		return array();
	}

	/**
	 * Define the sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return array();
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  array  $item        Data
	 * @param  String $column_name - Current column name
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			// show language.
			case 'language':
				return esc_html($item['label']);

			// show icon and replace-option.
			case 'icon':
				$output = wp_kses_post(Helper::get_icon_img_for_language_code( $item['code'] ) );
				$output .= ' <a href="#" class="replace-icon">'.esc_html__( 'Replace', 'easy-language' ).'</a>';
				$output .= '<span data-language-code="'.esc_attr($item['code']).'"></span>';
				return $output;

			// default output for any unknown column.
			default:
				return wp_kses_post( print_r( $item, true ) );
		}
	}
}