<?php
/**
 * File for output of logged entries in a table in WP-admin.
 *
 * @package easy-language
 */

namespace easyLanguage;

use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use WP_List_Table;
use wpdb;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler for log-output in backend.
 */
class Log_Api_Table extends WP_List_Table {

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
		global $wpdb;

		// get the db-connection.
		$this->wpdb = $wpdb;

		// set the table-name.
		$this->table_name = DB::get_instance()->get_wpdb_prefix() . 'easy_language_log';

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
			'state' => __('state', 'easy-language'),
			'date' => __('date', 'easy-language'),
			'api' => __('API', 'easy-language'),
			'request' => __('request', 'easy-language'),
			'response' => __('response', 'easy-language')
		);
	}

	/**
	 * Get the table data
	 *
	 * @return array
	 */
	private function table_data(): array {
		$orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'date';
		$order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'desc';
		$sql = '
            SELECT `state`, `time` AS `date`, `request`, `response`, `api`
            FROM `'.$this->table_name.'`
            ORDER BY %1$s %2$s';
		return $this->wpdb->get_results( $this->wpdb->prepare( $sql, array( $orderby, $order ) ), ARRAY_A );
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
		return array( 'date' => array( 'time', false ) );
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  array $item        Data
	 * @param  String $column_name - Current column name
	 *
	 * @return string
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection PhpSwitchCanBeReplacedWithMatchExpressionInspection
	 */
	public function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'date':
				return wp_kses_post(Helper::get_format_date_time($item[ $column_name ]));

			case 'api':
			case 'state':
				return wp_kses_post($item[ $column_name ]);

			case 'request':
			case 'response':
				return wp_kses_post(nl2br($item[ $column_name ]));

			default:
				return wp_kses_post(print_r( $item, true ));
		}
	}
}
