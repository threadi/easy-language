<?php
/**
 * File for API-handling in this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Define what SUMM AI supports and what not.
 */
class Apis {

	/**
	 * Instance of this object.
	 *
	 * @var ?Apis
	 */
	private static ?Apis $instance = null;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {
		// hooks.
		add_action( 'admin_action_easy_language_export_api_log', array( $this, 'export_api_log' ) );
		add_action( 'admin_action_easy_language_clear_api_log', array( $this, 'clear_api_log' ) );
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Apis {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return available APIs for simplifications with this plugin.
	 *
	 * @return array<int,Api_Base>
	 */
	public function get_available_apis(): array {
		$apis = array();

		/**
		 * Filter the list of APIs.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<int,Api_Base> $apis List of APIs
		 */
		return apply_filters( 'easy_language_register_api', $apis );
	}

	/**
	 * Return only active APIs. Should be one.
	 *
	 * @return false|Base
	 */
	public function get_active_api(): false|Base {
		foreach ( $this->get_available_apis() as $api_obj ) {
			// bail if this is not a Base object.
			if ( ! $api_obj instanceof Base ) {
				continue;
			}

			// bail if this API is not active.
			if ( ! $api_obj->is_active() ) {
				continue;
			}

			// return this object as active API.
			return $api_obj;
		}
		return false;
	}

	/**
	 * Get API-object by its name,
	 *
	 * @param string $name The name of the requested API.
	 *
	 * @return false|Base
	 */
	public function get_api_by_name( string $name ): false|Base {
		foreach ( $this->get_available_apis() as $api_obj ) {
			// bail if this is not a Base object.
			if ( ! $api_obj instanceof Base ) {
				continue;
			}

			// bail if names do not match.
			if ( $name !== $api_obj->get_name() ) {
				continue;
			}

			// return this object as matching object.
			return $api_obj;
		}
		return false;
	}

	/**
	 * Export API log for specific API.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function export_api_log(): void {
		// check nonce.
		check_admin_referer( 'easy-language-export-api-log', 'nonce' );

		// get name of the api to export.
		$export_api = isset( $_GET['api'] ) ? sanitize_text_field( wp_unslash( $_GET['api'] ) ) : '';

		// bail if no api is given.
		if ( empty( $export_api ) ) {
			// redirect user back.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// get api object.
		$api_object = $this->get_api_by_name( $export_api );

		// bail if no API object could be loaded.
		if ( ! $api_object instanceof Base ) {
			// redirect user back.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// get the entries.
		$entries = array(
			array(
				__( 'Date', 'easy-language' ),
				__( 'Request', 'easy-language' ),
				__( 'Response', 'easy-language' ),
			),
		);
		foreach ( $api_object->get_log_entries() as $entry ) {
			$entries[] = array(
				$entry->time,
				$entry->request,
				$entry->response,
			);
		}

		// set header for response as CSV-download.
		header( 'Content-type: application/csv' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( gmdate( 'YmdHi' ) . '_' . get_option( 'blogname' ) . '.csv' ) );
		$fp = fopen( 'php://output', 'w' );
		if ( ! $fp ) {
			exit;
		}
		foreach ( $entries as $row ) {
			fputcsv( $fp, $row );
		}
		exit;
	}

	/**
	 * Clear API log for specific API.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function clear_api_log(): void {
		// check nonce.
		check_admin_referer( 'easy-language-clear-api-log', 'nonce' );

		// get name of the api to clear.
		$clear_api = isset( $_GET['api'] ) ? sanitize_text_field( wp_unslash( $_GET['api'] ) ) : '';

		// bail if no api is given.
		if ( empty( $clear_api ) ) {
			// redirect user back.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// get api object.
		$api_object = $this->get_api_by_name( $clear_api );

		// bail if no API object could be loaded.
		if ( ! $api_object instanceof Base ) {
			// redirect user back.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// get db object.
		global $wpdb;

		// delete the entries.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Log_Api::get_instance()->get_table_name() . ' WHERE `api` = %s', array( $api_object->get_name() ) ) );

		// redirect user back.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}
}
