<?php
/**
 * File for handler for each request to Your-API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Your_Api;

use easyLanguage\Log_Api;
use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use WP_Error;
use wpdb;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Create and send request to summ-ai API. Gets the response.
 */
class Request {

	/**
	 * URL for the request.
	 *
	 * @var string
	 */
	private string $url = '';

	/**
	 * Collect the response-body.
	 *
	 * @var string
	 */
	private string $response = '';

	/**
	 * Gets the resulting http-status.
	 *
	 * @var int
	 */
	private int $http_status = 0;

	/**
	 * Gets the text to translate.
	 *
	 * @var string
	 */
	private string $text = '';

	/**
	 * Complete result of the request.
	 *
	 * @var array|WP_Error
	 */
	private array|WP_Error $result;

	/**
	 * The request method. Defaults to POST.
	 *
	 * @var string
	 */
	private string $method = 'POST';

	/**
	 * Target-language for this request.
	 *
	 * @var string
	 */
	private string $target_language;

	/**
	 * Name for database-table with request-response.
	 *
	 * @var string
	 */
	private string $table_requests;

	/**
	 * Database-object
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		// get the db-connection.
		$this->wpdb = $wpdb;

		// table for requests and responses.
		$this->table_requests = DB::get_instance()->get_wpdb_prefix() . 'easy_language_summ_ai';
	}

	/**
	 * Returns the actual text to translate.
	 *
	 * @return string
	 */
	public function get_text(): string {
		return $this->text;
	}

	/**
	 * Set the text to use for the request.
	 *
	 * @param string $text The text to translate.
	 * @return void
	 */
	public function set_text( string $text ): void {
		$this->text = $text;
	}

	/**
	 * Send the request.
	 *
	 * @return void
	 */
	public function send(): void {
		/**
		 * Implement your API-magic here.
		 */
	}

	/**
	 * Return the response of the request.
	 *
	 * @return string
	 */
	public function get_response(): string {
		return $this->response;
	}

	/**
	 * Set the url for the request.
	 *
	 * @param string $url The target-URL for the request.
	 * @return void
	 */
	public function set_url( string $url ): void {
		$this->url = $url;
	}

	/**
	 * Return the method for this request.
	 *
	 * @return string
	 */
	public function get_method(): string {
		return $this->method;
	}

	/**
	 * Set target-language for this request.
	 *
	 * @param $lang
	 *
	 * @return void
	 */
	public function set_target_language( $lang ): void {
		$this->target_language = $lang;
	}

	/**
	 * Set source-language for this request.
	 *
	 * @param $lang
	 *
	 * @return void
	 */
	public function set_source_language( $lang ): void {
		$this->source_language = $lang;
	}
}
