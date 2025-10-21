<?php
/**
 * File for output of logged entries for API-actions in a table in WP-admin.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin\Tables;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Helper;
use WP_List_Table;

/**
 * Handler for api-log-output in backend.
 */
class Log_Api_Table extends WP_List_Table {
	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'state'    => __( 'State', 'easy-language' ),
			'date'     => __( 'Date', 'easy-language' ),
			'api'      => __( 'API', 'easy-language' ),
			'request'  => __( 'Request', 'easy-language' ),
			'response' => __( 'Response', 'easy-language' ),
		);
	}

	/**
	 * Get the table data
	 *
	 * @return array<string,mixed>
	 */
	private function table_data(): array {
		// check nonce.
		if ( isset( $_REQUEST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'easy-language-table-log-api' ) ) {
			// redirect user back.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		global $wpdb;

		// order table.
		$order_by = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! array_key_exists( $order_by, $this->get_sortable_columns() ) ) {
			$order_by = 'date';
		}
		$order = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = 'desc';
		}

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
			$where .= ' AND `api` = "%4$s"';
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
	 * @return array<string>
	 */
	public function get_hidden_columns(): array {
		return array();
	}

	/**
	 * Define the sortable columns
	 *
	 * @return array<string,mixed>
	 */
	public function get_sortable_columns(): array {
		return array( 'date' => array( 'time', false ) );
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
			case 'date':
				return wp_kses_post( Helper::get_format_date_time( $item[ $column_name ] ) );

			case 'api':
				$api_obj = \easyLanguage\Plugin\Apis::get_instance()->get_api_by_name( $item[ $column_name ] );
				if ( $api_obj ) {
					return $api_obj->get_title();
				}
				return '';

			case 'state':
				return $this->get_status_icon( $item[ $column_name ] );

			case 'request':
			case 'response':
				return '<code>' . wp_kses_post( nl2br( $item[ $column_name ] ) ) . '</code>';
		}
		return '';
	}

	/**
	 * Define filter for languages.
	 *
	 * @return array<string,string>
	 */
	protected function get_views(): array {
		global $wpdb;

		// get main url.
		$url = remove_query_arg( 'api' );

		// define initial list.
		$list = array(
			'all' => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'All', 'easy-language' ) . '</a>',
		);

		// get all apis from entries and add them to the list.
		$sql        = $this->get_base_sql();
		$entries    = $wpdb->get_results( $wpdb->prepare( $sql, array( 1 ) ), ARRAY_A );
		$apis_array = array();
		foreach ( $entries as $item ) {
			if ( empty( $apis_array[ $item['api'] ] ) ) {
				$api_object = \easyLanguage\Plugin\Apis::get_instance()->get_api_by_name( $item['api'] );
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
		// bail if this is not for top view.
		if ( 'top' !== $which ) {
			return;
		}

		// get api.
		$api = $this->get_api_filter();

		if ( ! empty( $api ) && ! empty( $this->items ) ) {
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

			// define clear URL.
			$url = add_query_arg(
				array(
					'action' => 'easy_language_clear_api_log',
					'nonce'  => wp_create_nonce( 'easy-language-clear-api-log' ),
					'api'    => $api,
				),
				get_admin_url() . 'admin.php'
			);
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="button"><?php echo esc_html__( 'Clear log', 'easy-language' ); ?></a>
			<?php
		} else {
			?>
			<span class="button disabled" title="<?php echo esc_attr__( 'Choose an API to export above', 'easy-language' ); ?>"><?php echo esc_html__( 'Export as CSV', 'easy-language' ); ?></span>
			<span class="button disabled" title="<?php echo esc_attr__( 'Choose an API above to clear the log', 'easy-language' ); ?>"><?php echo esc_html__( 'Clear log', 'easy-language' ); ?></span>
			<?php
		}
	}

	/**
	 * Get actual API-filter-value.
	 *
	 * @return string
	 */
	private function get_api_filter(): string {
		// get API from request.
		$api = filter_input( INPUT_GET, 'api', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no APO was in request.
		if ( is_null( $api ) ) {
			return '';
		}

		// return the API from request.
		return $api;
	}

	/**
	 * Return base-SQL-statement to get api logs.
	 *
	 * @return string
	 */
	private function get_base_sql(): string {
		return 'SELECT `state`, `time` AS `date`, `request`, `response`, `api` FROM `' . \easyLanguage\Plugin\Log_Api::get_instance()->get_table_name() . '` WHERE 1 = %1$d';
	}

	/**
	 * Return HTML-code for icon of the given status.
	 *
	 * @param string $status The requested status.
	 *
	 * @return string
	 */
	private function get_status_icon( string $status ): string {
		$list = array(
			'success' => '<span class="dashicons dashicons-yes" title="' . __( 'Ended successfully', 'easy-language' ) . '"></span>',
			'info'    => '<span class="dashicons dashicons-info-outline" title="' . __( 'Just an info', 'easy-language' ) . '"></span>',
			'error'   => '<span class="dashicons dashicons-no" title="' . __( 'Error occurred', 'easy-language' ) . '"></span>',
		);

		/**
		 * Filter the list of possible states in log table.
		 *
		 * @since 2.8.0 Available since 2.8.0.
		 *
		 * @param array<string,string> $list The list of possible states.
		 */
		$list = apply_filters( 'easy_language_status_list', $list );

		// bail if status is unknown.
		if ( empty( $list[ $status ] ) ) {
			return '';
		}

		// return the HTML-code for the icon of this status.
		return $list[ $status ];
	}
}
