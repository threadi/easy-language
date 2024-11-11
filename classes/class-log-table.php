<?php
/**
 * File for output of logged entries in a table in WP-admin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_List_Table;

/**
 * Handler for log-output in backend.
 */
class Log_Table extends WP_List_Table {
	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'state'   => __( 'State', 'easy-language' ),
			'date'    => __( 'Date', 'easy-language' ),
			'user_id' => __( 'User', 'easy-language' ),
			'log'     => __( 'Entry', 'easy-language' ),
		);
	}

	/**
	 * Get the table data
	 *
	 * @return array
	 */
	private function table_data(): array {
		// check nonce.
		if ( isset( $_REQUEST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'easy-language-table-log' ) ) {
			// redirect user back.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

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

		// get statement.
		$sql = $this->get_base_sql() . ' ORDER BY %2$s %3$s';

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
	 * @param  String $column_name Current column name.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			// return the state of this event.
			case 'state':
				return __( $item[ $column_name ], 'easy-language' );

			// return the user who requested this event.
			case 'user_id':
				$user = new \WP_User( absint( $item[ $column_name ] ) );
				if ( $user->ID > 0 ) {
					// link to user-profile if actual user has the capability for it.
					if ( current_user_can( 'manage_options' ) ) {
						return '<a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '">' . esc_html( $user->display_name ) . '</a>';
					}
					return esc_html( $user->display_name );
				}

				// return unknown if no user is set for this event.
				return __( 'Unknown', 'easy-language' );

			// return the date of the event.
			case 'date':
				return wp_kses_post( Helper::get_format_date_time( $item[ $column_name ] ) );

			// return the log-entry.
			case 'log':
				return wp_kses_post( nl2br( $item[ $column_name ] ) );
		}

		return '';
	}

	/**
	 * Return base-SQL-statement to get api logs.
	 *
	 * @return string
	 */
	private function get_base_sql(): string {
		return 'SELECT `state`, `time` AS `date`, `log`, `user_id` FROM `' . Log::get_instance()->get_table_name() . '` WHERE 1 = %1$d';
	}
}
