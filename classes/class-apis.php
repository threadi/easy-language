<?php
/**
 * File for API-handling in this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * Constructor for Init-Handler.
	 */
	private function __construct() {
		// hooks.
		add_action( 'admin_action_easy_language_export_api_log', array( $this, 'export_api_log' ) );
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Apis {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Return available APIs for simplifications with this plugin.
	 *
	 * @return array
	 */
	public function get_available_apis(): array {
		return apply_filters( 'easy_language_register_api', array() );
	}

	/**
	 * Return only active APIs. Should be one.
	 *
	 * @return false|Base
	 */
	public function get_active_api(): false|Base {
		foreach ( $this->get_available_apis() as $api_obj ) {
			if ( $api_obj->is_active() ) {
				return $api_obj;
			}
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
			if ( $name === $api_obj->get_name() ) {
				return $api_obj;
			}
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
		check_ajax_referer( 'easy-language-export-api-log', 'nonce' );

		// get name of the api to export.
		$export_api = isset( $_GET['api'] ) ? sanitize_text_field( wp_unslash( $_GET['api'] ) ) : '';

		if ( ! empty( $export_api ) ) {
			// get api object.
			$api_object = $this->get_api_by_name( $export_api );
			if ( false !== $api_object ) {
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
				foreach ( $entries as $row ) {
					fputcsv( $fp, $row );
				}
				exit;
			}
		}

		// redirect user back.
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
		exit;
	}
}
