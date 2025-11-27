<?php
/**
 * File for output collected simplification texts.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage\Tables;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Db;
use easyLanguage\EasyLanguage\Init;
use easyLanguage\EasyLanguage\Text;
use easyLanguage\Plugin\Api_Base;
use easyLanguage\Plugin\Apis;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Languages;
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
	 * @return array<string,string>
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
	 * @return array<Text>
	 */
	private function table_data(): array {
		// order table.
		$order_by = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! array_key_exists( $order_by, $this->get_sortable_columns() ) ) {
			$order_by = 'date';
		}
		$order = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = 'desc';
		}

		// get results.
		return Db::get_instance()->get_entries(
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
	 * @return array<string,mixed>
	 */
	public function get_hidden_columns(): array {
		return array();
	}

	/**
	 * Define the sortable columns.
	 *
	 * @return array<string,mixed>
	 */
	public function get_sortable_columns(): array {
		return array( 'date' => array( 'date', false ) );
	}

	/**
	 * Define what data to show on each column of the table.
	 *
	 * @param  Text   $item        Data.
	 * @param  String $column_name - Current column name.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
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
		if ( empty( $column_name ) ) {
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
					'<a href="' . esc_url( $delete_link ) . '" class="dashicons dashicons-trash easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog_simplification ) ) . '" title="' . __( 'Delete this text.', 'easy-language' ) . '">&nbsp;</a>',
				);

				// get the item ID.
				$item_id = $item->get_id();

				// get actual API and check if it is configured.
				$api_object = Apis::get_instance()->get_active_api();

				// remove the first entry from the options-array to replace it with our own.
				unset( $options[0] );

				// if no API is active, show hint.
				if ( ! $api_object instanceof Api_Base ) {
					$options[0] = '<span class="dashicons dashicons-translation" title="' . esc_attr( __( 'No API active!', 'easy-language' ) ) . '">&nbsp;</a>';
				}

				// if API is not configured, show hint.
				if ( $api_object instanceof Api_Base && false === $api_object->is_configured() ) {
					/* translators: %1$s will be replaced by the name of the API */
					$options[0] = '<span class="dashicons dashicons-translation" title="' . esc_attr( sprintf( __( 'API %1$s is not configured.', 'easy-language' ), esc_html( $api_object->get_title() ) ) ) . '">&nbsp;</a>';
				}

				// if API is configured, show option to simplify the item.
				if ( $api_object instanceof Api_Base && false !== $api_object->is_configured() ) {
					// add option to delete a single simplification item.
					$do_simplification = add_query_arg(
						array(
							'action' => 'easy_language_get_simplification_of_entry',
							'id'     => $item_id,
							'nonce'  => wp_create_nonce( 'easy-language-get-simplification-of-entry' ),
						),
						get_admin_url() . 'admin.php'
					);

					// create dialog.
					$dialog_config = array(
						'title'   => __( 'Simplify this text?', 'easy-language' ),
						'texts'   => array(
							__( '<p>Simplifying texts via API could cause costs.<br><strong>Are you sure your want to simplify this single text?</strong></p>', 'easy-language' ),
						),
						'buttons' => array(
							array(
								'action'  => 'location.href="' . $do_simplification . '";',
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

					$options[0] = '<a href="' . esc_url( $do_simplification ) . '" class="dashicons dashicons-translation easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog_config ) ) . '" title="' . esc_attr( __( 'Simplify now', 'easy-language' ) ) . '">&nbsp;</a>';
				}

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

			// show hint where this text is used.
			case 'used_in':
				// collect the texts.
				$texts = array();

				// get all objects this text is used.
				foreach ( $item->get_objects() as $object ) {
					$object = Helper::get_object( absint( $object['object_id'] ), $object['object_type'] );
					if ( false !== $object ) {
						$texts[] = '<a href="' . esc_url( $object->get_edit_link() ) . '">' . esc_html( $object->get_title() ) . '</a><br>';
					}
				}
				return implode( '', $texts );
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
