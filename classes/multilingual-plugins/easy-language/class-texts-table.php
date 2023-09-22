<?php
/**
 * File for output collected translations.
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
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler for log-output in backend.
 */
class Texts_Table extends WP_List_Table {

	/**
	 * database-object
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Name for own database-table with translations.
	 *
	 * @var string
	 */
	private string $table_translations;

	/**
	 * Name for own database-table with translations.
	 *
	 * @var string
	 */
	private string $table_originals;

	/**
	 * Constructor for Logging-Handler.
	 */
	public function __construct() {
		global $wpdb;

		// get the db-connection.
		$this->wpdb = $wpdb;

		// set the table-name for translations.
		$this->table_translations = DB::get_instance()->get_wpdb_prefix() . 'easy_language_translations';

		// set the table-name for original.
		$this->table_originals = DB::get_instance()->get_wpdb_prefix() . 'easy_language_originals';

		// call parent constructor.
		parent::__construct( array('plural' => 'text-translations-table') );
	}

	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'options' => '',
			'date' => __('date', 'easy-language'),
			'used_api' => __('used API', 'easy-language'),
			'user' => __('requesting user', 'easy-language'),
			'source_language' => __('source language', 'easy-language'),
			'target_language' => __('target language', 'easy-language'),
			'original' => __('original', 'easy-language'),
			'translation' => __('translation', 'easy-language'),
		);
	}

	/**
	 * Get the table data
	 *
	 * @return array
	 */
	private function table_data(): array {
		$sql = '
            SELECT o.`id`, t.`time` AS `date`, t.`translation`, t.`language` as `target_language`,
                   o.`original`, o.`lang` as `source_language`, t.`used_api`, t.`user_id`
            FROM `'.$this->table_translations.'` AS t
            JOIN  `'.$this->table_originals.'` AS o ON (t.oid = o.id)
            ORDER BY t.`time` DESC';
		return $this->wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get the log-table for the table-view.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = [$columns, $hidden, $sortable];
		$this->items = $this->table_data();
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
		return ['date' => ['date', false]];
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  array $item        Data
	 * @param  String $column_name - Current column name
	 *
	 * @return mixed
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function column_default( $item, $column_name ) {
		// get languages-object.
		$languages_obj = Languages::get_instance();

		// show content depending on column.
		switch( $column_name ) {
			// show date.
			case 'date':
				return Helper::get_format_date_time($item[ $column_name ]);

			// show translation or original as not formatted text.
			case 'translation':
			case 'original':
				return wp_strip_all_tags($item[ $column_name ]);

			// show source language.
			case 'source_language':
				$language = $item[ $column_name ];
				$languages = $languages_obj->get_possible_source_languages();
				if( !empty($languages[$language]) ) {
					return $languages[ $language ]['label'];
				}
				return '';

			// show target language.
			case 'target_language':
				$language = $item[ $column_name ];
				$languages = $languages_obj->get_possible_target_languages();
				if( !empty($languages[$language]) ) {
					return $languages[ $language ]['label'];
				}
				return '';

			// show options.
			case 'options':
				$delete_link = add_query_arg(
					array(
						'action'   => 'easy_language_delete_translation',
						'id'     => $item['id'],
						'nonce'    => wp_create_nonce( 'easy-language-delete-translation' )
					),
					get_admin_url() . 'admin.php'
				);
				return '<a href="'.esc_url($delete_link).'" class="dashicons dashicons-trash">&nbsp;</a>';

			// show used API.
			case 'used_api':
				$api = Apis::get_instance()->get_api_by_name( $item[$column_name] );
				if( $api ) {
					return $api->get_title();
				}
				return '';

			// show requesting user.
			case 'user':
				if( $item['user_id'] > 0 ) {
					$user = new WP_User( $item['user_id'] );
					if( current_user_can('edit_users') ) {
						return '<a href="'.get_edit_user_link($user->ID).'">'.esc_html($user->display_name).'</a>';
					}
					else {
						return esc_html($user->display_name);
					}
				}

				// return hint that this translation was run without login.
				return '<i>'.__( 'without login', 'easy-language' ).'</i>';

			// fallback if no column has been matched.
			default:
				return print_r( $item, true ) ;
		}
	}
}
