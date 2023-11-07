<?php
/**
 * File for output of logged entries in a table in WP-admin.
 *
 * @package easy-language
 */

namespace easyLanguage;

use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use WP_List_Table;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handler for log-output in backend.
 */
class Log_Api_Table extends WP_List_Table {
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
			'state'    => __( 'state', 'easy-language' ),
			'date'     => __( 'date', 'easy-language' ),
			'api'      => __( 'API', 'easy-language' ),
			'request'  => __( 'request', 'easy-language' ),
			'response' => __( 'response', 'easy-language' ),
		);
	}

	/**
	 * Get the table data
	 *
	 * @return array
	 */
	private function table_data(): array {
		global $wpdb;

		// order table.
		$order_by = ( isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ), true ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'date';
		$order    = ( isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array( 'asc', 'desc' ), true ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'desc';

		// define vars for prepared statement.
		$vars = array(
			1,
			$order_by,
			$order,
		);

		// get filter.
		$api   = $this->get_api_filter();
		$where = '';
		if ( ! empty( $api ) ) {
			$where .= ' AND `api` = "%3$s"';
			$vars[] = $api;
		}

		// get statement.
		$sql = $this->get_base_sql() . $where . ' ORDER BY %2$s %3$s';

		// get results and return them.
		return $wpdb->get_results( $wpdb->prepare( $sql, $vars ), ARRAY_A );
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
		return array( 'date' => array( 'time', false ) );
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
		switch ( $column_name ) {
			case 'date':
				return wp_kses_post( Helper::get_format_date_time( $item[ $column_name ] ) );

			case 'api':
				$api_obj = Apis::get_instance()->get_api_by_name( $item[ $column_name ] );
				if ( $api_obj ) {
					return $api_obj->get_title();
				}
				return '';

			case 'state':
				return wp_kses_post( __( $item[ $column_name ], 'easy-language' ) );

			case 'request':
			case 'response':
				return wp_kses_post( nl2br( $item[ $column_name ] ) );

			default:
				return wp_kses_post( print_r( $item, true ) );
		}
	}

	/**
	 * Define filter for languages.
	 *
	 * @return array
	 */
	protected function get_views(): array {
		global $wpdb;

		// get main url.
		$url = remove_query_arg( 'api' );

		// define initial list.
		$list = array(
			'all' => '<a href="' . esc_url( $url ) . '">' . __( 'All', 'easy-language' ) . '</a>',
		);

		// get all apis from entries and add them to the list.
		$sql        = $this->get_base_sql();
		$entries    = $wpdb->get_results( $wpdb->prepare( $sql, array( 1 ) ), ARRAY_A );
		$apis_array = array();
		foreach ( $entries as $item ) {
			if ( empty( $apis_array[ $item['api'] ] ) ) {
				$api_object = Apis::get_instance()->get_api_by_name( $item['api'] );
				if ( false !== $api_object ) {
					$apis_array[ $item['api'] ] = $api_object;
				}
			}
		}

		// convert APIs to list-entries.
		foreach ( $apis_array as $api => $api_object ) {
			$url          = add_query_arg( array( 'api' => $api ) );
			$list[ $api ] = '<a href="' . esc_url( $url ) . '">' . esc_html( $api_object->get_title() ) . '</a>';
		}

		// return resulting list.
		return $list;
	}

	/**
	 * Add export-buttons on top of table.
	 *
	 * @param string $which Position.
	 * @return void
	 */
	public function extra_tablenav( $which ): void {
		if ( 'top' === $which ) {
			$api = $this->get_api_filter();

			if ( ! empty( $api ) ) {
				// define export-URL.
				$url = add_query_arg(
					array(
						'action' => 'easy_language_export_api_log',
						'nonce'  => wp_create_nonce( 'easy-language-export-api-log' ),
						'api'    => $api,
					),
					get_admin_url() . 'admin.php'
				);
				?><a href="<?php echo esc_url( $url ); ?>" class="button"><?php echo esc_html__( 'Export as CSV', 'easy-language' ); ?></a>
				<?php
			} else {
				?>
				<span class="button disabled" title="<?php echo esc_html__( 'Choose an API to export above', 'easy-language' ); ?>"><?php echo esc_html__( 'Export as CSV', 'easy-language' ); ?></span>
				<?php
			}
		}
	}

	/**
	 * Get actual API-filter-value.
	 *
	 * @return string
	 */
	private function get_api_filter(): string {
		return isset( $_GET['api'] ) ? sanitize_text_field( wp_unslash( $_GET['api'] ) ) : '';
	}

	/**
	 * Return base-SQL-statement to get api logs.
	 *
	 * @return string
	 */
	private function get_base_sql(): string {
		return 'SELECT `state`, `time` AS `date`, `request`, `response`, `api` FROM `' . $this->table_name . '` WHERE 1 = %1$d';
	}
}
