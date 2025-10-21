<?php
/**
 * File for output any actual supported language and their icons.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin\Tables;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Languages;
use WP_List_Table;

/**
 * Handler for log-output in backend.
 */
class Language_Icons_Table extends WP_List_Table {
	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'language' => __( 'Language', 'easy-language' ),
			'icon'     => __( 'Icon', 'easy-language' ),
		);
	}

	/**
	 * Get the table data
	 *
	 * @return array<int,array<string,string>>
	 */
	private function table_data(): array {
		$languages = array();
		foreach ( array_merge( Languages::get_instance()->get_active_languages(), Languages::get_instance()->get_possible_source_languages() ) as $language_code => $language ) {
			$language['code'] = $language_code;
			$languages[]      = $language;
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
	 * @return array<string>
	 */
	public function get_hidden_columns(): array {
		return array();
	}

	/**
	 * Define the sortable columns
	 *
	 * @return array<string>
	 */
	public function get_sortable_columns(): array {
		return array();
	}

	/**
	 * Define what data to show on each column of the table.
	 *
	 * @param  array<string,mixed> $item        Data.
	 * @param  String              $column_name - Current column name.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			// show language.
			case 'language':
				return esc_html( $item['label'] );

			// show icon and replace-option.
			case 'icon':
				$output  = wp_kses_post( Helper::get_icon_img_for_language_code( $item['code'] ) );
				$output .= ' <a href="#" class="replace-icon">' . esc_html__( 'Replace', 'easy-language' ) . '</a>';
				$output .= '<span data-language-code="' . esc_attr( $item['code'] ) . '"></span>';
				return $output;

			// return any value.
			default:
				return '';
		}
	}

	/**
	 * Message to be displayed when there are no items
	 *
	 * @since 3.1.0
	 */
	public function no_items(): void {
		echo esc_html__( 'No icons found.', 'easy-language' );
	}
}
