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
use easyLanguage\EasyLanguage\Text;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Languages;
use WP_List_Table;
use WP_User;

/**
 * Handler for log-output in backend.
 */
class Texts_In_Use_Table extends WP_List_Table {
	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'options'         => '',
			'date'            => __( 'Date', 'easy-language' ),
			'used_api'        => __( 'Used API', 'easy-language' ),
			'user'            => __( 'Requesting user', 'easy-language' ),
			'used_in'         => __( 'Used in', 'easy-language' ),
			'source_language' => __( 'Source language', 'easy-language' ),
			'target_language' => __( 'Target language', 'easy-language' ),
			'original'        => __( 'Original', 'easy-language' ),
			'simplification'  => __( 'Simplification', 'easy-language' ),
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

		// define query for entries.
		$query = array(
			'state'              => 'in_use',
			'object_not_state'   => 'trash',
			'has_simplification' => true,
		);

		// get language-filter.
		$lang = $this->get_lang_filter();
		if ( ! empty( $lang ) ) {
			$query['lang']        = $lang;
			$query['target_lang'] = $lang;
		}

		// return resulting entry-objects.
		return Db::get_instance()->get_entries(
			$query,
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
	 * @param Text   $item Data.
	 * @param String $column_name Current column name.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		// get languages-object.
		$languages_obj = Languages::get_instance();

		// get target languages of this item.
		$target_languages = $item->get_target_languages();

		// get object of the used api.
		$api_obj = $item->get_api();

		/**
		 * Filter the column name.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param string $column_name The column name.
		 * @param Text $item The object.
		 */
		$column_name = apply_filters( 'easy_language_simplification_table_used_in', $column_name, $item );

		// bail if column-name is not set.
		if ( empty( $column_name ) ) {
			return '';
		}

		// show content depending on column.
		switch ( $column_name ) {
			// show date.
			case 'date':
				return Helper::get_format_date_time( $item->get_date() );

			// show simplification as not formatted text.
			case 'simplification':
				$texts = '';
				foreach ( $target_languages as $language_code => $target_language ) {
					$texts .= wp_strip_all_tags( $item->get_simplification( $language_code ) );
				}
				return $texts;

			// get original as not formatted text.
			case 'original':
				return wp_strip_all_tags( $item->get_original() );

			// show source language.
			case 'source_language':
				$language  = $item->get_source_language();
				$languages = $languages_obj->get_possible_source_languages();
				if ( ! empty( $languages[ $language ] ) ) {
					return $languages[ $language ]['label'];
				}
				return __( 'Unknown', 'easy-language' );

			// show target language.
			case 'target_language':
				foreach ( $target_languages as $language_code => $language ) {
					if ( $api_obj ) {
						$languages = $api_obj->get_active_target_languages();
						if ( ! empty( $languages[ $language_code ] ) ) {
							return $languages[ $language_code ]['label'];
						}
					}
				}
				return __( 'Unknown', 'easy-language' );

			// show options.
			case 'options':
				// get the item ID.
				$item_id = $item->get_id();

				// create the option-list.
				$options = array();

				// add option to delete a single simplification item.
				$delete_link = add_query_arg(
					array(
						'action' => 'easy_language_delete_simplification',
						'id'     => $item_id,
						'nonce'  => wp_create_nonce( 'easy-language-delete-simplification' ),
					),
					get_admin_url() . 'admin.php'
				);

				// define dialog.
				$dialog = array(
					'title'   => __( 'Do you really want to delete this simplification?', 'easy-language' ),
					'texts'   => array(
						'<p>' . __( 'Only the simplification will be deleted.<br>The objects where this text is used will not be altered.<br>After deletion you could request a new simplification for the original text from API.', 'easy-language' ) . '</p>',
					),
					'buttons' => array(
						array(
							'action'  => 'location.href="' . $delete_link . '"',
							'variant' => 'primary',
							'text'    => __( 'Yes, delete it', 'easy-language' ),
						),
						array(
							'action'  => 'closeDialog();',
							'variant' => 'primary',
							'text'    => __( 'Do nothing', 'easy-language' ),
						),
					),
				);

				// add the option to the list.
				$options[] = '<a href="' . esc_url( $delete_link ) . '" class="dashicons dashicons-trash easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">&nbsp;</a>';

				/**
				 * Filter additional options.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param array $options List of options.
				 * @param int $item_id The ID of the object.
				 */
				$filtered_options = apply_filters( 'easy_language_simplification_table_options', $options, $item_id );

				// return html-output.
				return implode( '', $filtered_options );

			// show used API.
			case 'used_api':
				if ( $api_obj ) {
					return $api_obj->get_title();
				}
				return '';

			// show requesting user.
			case 'user':
				foreach ( $target_languages as $language_code => $language ) {
					$user_id = $item->get_user_for_simplification( $language_code );
					if ( $user_id > 0 ) {
						$user = new WP_User( $user_id );
						if ( current_user_can( 'edit_users' ) ) {
							return '<a href="' . get_edit_user_link( $user->ID ) . '">' . esc_html( $user->display_name ) . '</a>';
						}
						return esc_html( $user->display_name );
					}
				}

				// return hint that this translation was run without login.
				return '<i>' . __( 'without login', 'easy-language' ) . '</i>';

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

			// set default return to empty string.
			default:
				return '';
		}
	}

	/**
	 * Define filter for languages.
	 *
	 * @return array<string,string>
	 */
	protected function get_views(): array {
		// get main url.
		$url = remove_query_arg( 'lang' );

		// define initial list.
		$list = array(
			'all' => '<a href="' . esc_url( $url ) . '">' . __( 'All', 'easy-language' ) . '</a>',
		);

		// get all languages from items and add their languages to the list.
		$query           = array(
			'state'            => 'in_use',
			'object_not_state' => 'trash',
		);
		$entries         = Db::get_instance()->get_entries( $query );
		$languages_array = array();
		foreach ( $entries as $item ) {
			// get object of the used api.
			$api_obj = $item->get_api();
			if ( false !== $api_obj && empty( $languages_array[ $item->get_source_language() ] ) ) {
				// get source languages of this api.
				$source_languages = $api_obj->get_active_source_languages();

				if ( ! empty( $source_languages[ $item->get_source_language() ] ) ) {
					// add the source language to list.
					$languages_array[ $item->get_source_language() ] = $source_languages[ $item->get_source_language() ]['label'];
				}
			}
			foreach ( $item->get_target_languages() as $language_code => $target_language ) {
				$languages_array[ $language_code ] = $target_language;
			}
		}

		// convert languages to list-entries.
		foreach ( $languages_array as $language_code => $language ) {
			$url               = add_query_arg( array( 'lang' => $language_code ) );
			$list[ $language ] = '<a href="' . esc_url( $url ) . '">' . esc_html( $language ) . '</a>';
		}

		// return resulting list.
		return $list;
	}

	/**
	 * Add export-buttons on top of table.
	 *
	 * @param string $which The position.
	 * @return void
	 */
	public function extra_tablenav( $which ): void {
		if ( 'top' === $which ) {
			$language_code = $this->get_lang_filter();

			if ( ! empty( $language_code ) ) {
				// define export-URL.
				$url = add_query_arg(
					array(
						'action' => 'easy_language_export_simplifications',
						'nonce'  => wp_create_nonce( 'easy-language-export-simplifications' ),
						'lang'   => $language_code,
					),
					get_admin_url() . 'admin.php'
				);
				?><a href="<?php echo esc_url( $url ); ?>" class="button"><?php echo esc_html__( 'Export as Portable Object (po)', 'easy-language' ); ?></a>
				<?php
			} else {
				?>
				<span class="button disabled" title="<?php echo esc_attr__( 'Choose a language above to export', 'easy-language' ); ?>"><?php echo esc_html__( 'Export as Portable Object (po)', 'easy-language' ); ?></span>
				<?php
			}
		}
	}

	/**
	 * Return the lang-filter.
	 *
	 * @return string
	 */
	private function get_lang_filter(): string {
		$lang = filter_input( INPUT_GET, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		return ! is_null( $lang ) ? $lang : '';
	}

	/**
	 * Message to be displayed when there are no items.
	 *
	 * @since 3.1.0
	 */
	public function no_items(): void {
		echo esc_html__( 'No simplified texts found.', 'easy-language' );
	}
}
