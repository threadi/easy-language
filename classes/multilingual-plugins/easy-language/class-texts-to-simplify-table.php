<?php
/**
 * File for output collected simplification texts.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Helper;
use easyLanguage\Languages;
use WP_List_Table;

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
			'date'            => __( 'Date', 'easy-language' ),
			'used_in'         => __( 'Used in', 'easy-language' ),
			'source_language' => __( 'Source language', 'easy-language' ),
			'target_language' => __( 'Target language', 'easy-language' ),
			'original'        => __( 'Original', 'easy-language' ),
		);
	}

	/**
	 * Get the table data.
	 *
	 * @return array
	 */
	private function table_data(): array {
		// order table.
		$order_by = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! in_array( $order_by, array_keys( $this->get_sortable_columns() ), true ) ) {
			$order_by = 'date';
		}
		$order = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = 'desc';
		}

		// get results.
		return DB::get_instance()->get_entries(
			Init::get_instance()->get_filter_for_entries_to_simplify(),
			array(
				'order_by' => $order_by,
				'order'    => $order,
			)
		);
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
	 * @param  array|object $item        Data.
	 * @param  String       $column_name - Current column name.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		// bail if item is not an entry-text.
		if ( ! ( $item instanceof Text ) ) {
			return '';
		}

		// get languages-object.
		$languages_obj = Languages::get_instance();

		/**
		 * Filter the column name.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param string $column_name The column name.
		 * @param Text $item The item object.
		 */
		$column_name = apply_filters( 'easy_language_simplification_table_to_simplify', $column_name, $item );

		// bail if column-name is not set.
		if ( false === $column_name ) {
			return '';
		}

		// add option to delete a single simplification item.
		$delete_link = add_query_arg(
			array(
				'action' => 'easy_language_delete_text_for_simplification',
				'id'     => $item->get_id(),
				'nonce'  => wp_create_nonce( 'easy-language-delete-text-for-simplification' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create deletion dialog.
		$dialog_simplification = array(
			'title'   => __( 'Delete this not simplified text?', 'easy-language' ),
			'texts'   => array(
				__( '<p>The deletion of this text will not alter any contents.<br>The deleted text will not automatically be simplified.</p>', 'easy-language' ),
			),
			'buttons' => array(
				array(
					'action'  => 'location.href="' . $delete_link . '";',
					'variant' => 'primary',
					'text'    => __( 'Yes', 'easy-language' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'No', 'easy-language' ),
				),
			),
		);

		// show content depending on column.
		switch ( $column_name ) {
			case 'options':
				$options = array(
					'<span class="dashicons dashicons-translation" title="' . __( 'Simplify now only with Easy Language Pro.', 'easy-language' ) . '">&nbsp;</span>',
					'<a href="' . esc_url( $delete_link ) . '" class="dashicons dashicons-trash easy-dialog-for-wordpress" data-dialog="' . esc_attr( wp_json_encode( $dialog_simplification ) ) . '" title="' . __( 'Delete this text.', 'easy-language' ) . '">&nbsp;</a>',
				);

				$item_id = $item->get_id();

				/**
				 * Filter additional options.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param array $options List of options.
				 * @param int $item_id The ID of the object.
				 */
				$options = apply_filters( 'easy_language_simplification_to_simplify_table_options', $options, $item_id );

				// return html-output.
				return implode( '', $options );

			// get date of this entry.
			case 'date':
				return Helper::get_format_date_time( $item->get_date() );

			// get source language.
			case 'source_language':
				$language  = $item->get_source_language();
				$languages = $languages_obj->get_possible_source_languages();
				if ( ! empty( $languages[ $language ] ) ) {
					return $languages[ $language ]['label'];
				}
				return __( 'Unknown', 'easy-language' );

			// get target languages.
			case 'target_language':
				return implode( ',', $item->get_target_languages() );

			// show original text.
			case 'original':
				return wp_strip_all_tags( $item->get_original() );

			// show hint for pro in used in column.
			case 'used_in':
				return '<span class="pro-marker">' . __( 'Only in Pro', 'easy-language' ) . '</span>';
		}

		// or return nothing.
		return '';
	}

	/**
	 * Add delete button on top of table.
	 *
	 * @param string $which The position.
	 * @return void
	 */
	public function extra_tablenav( $which ): void {
		if ( 'top' === $which ) {
			if ( ! empty( $this->items ) ) {
				// define delete all URL.
				$url = add_query_arg(
					array(
						'action' => 'easy_language_delete_all_to_simplified_texts',
						'nonce'  => wp_create_nonce( 'easy-language-delete-all-to-simplified_texts' ),
					),
					get_admin_url() . 'admin.php'
				);
				?><a href="<?php echo esc_url( $url ); ?>" class="button"><?php echo esc_html__( 'Delete all', 'easy-language' ); ?></a>
				<?php
			} else {
				?>
				<span class="button disabled"><?php echo esc_html__( 'Delete all', 'easy-language' ); ?></span>
														<?php
			}
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
