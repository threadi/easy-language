<?php
/**
 * File for handler for each request to SUMM AI API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Summ_Ai;

use easyLanguage\Log_Api;
use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use WP_Error;
use wpdb;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create and send request to summ-ai API. Gets the response.
 */
class Request {

	/**
	 * HTTP-header for the request.
	 *
	 * @var array
	 */
	private array $header = array(
		'Accept'       => '*/*',
		'Content-Type' => 'application/json',
	);

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
	 * The input_text_type for the request.
	 *
	 * @var string
	 */
	private string $text_type = 'plain_text';

	/**
	 * Separator which is used to split compound, e.g. "Bundes-Kanzler".
	 *
	 * @var string
	 */
	private string $separator = 'interpunct';

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
	 * The content of this request.
	 *
	 * @var array
	 */
	private array $request;

	/**
	 * Duration of this request.
	 *
	 * @var float
	 */
	private float $duration = 0;

	/**
	 * Marker if this is just a test.
	 *
	 * @var bool
	 */
	private bool $is_test = false;

	/**
	 * Source-language for this request.
	 *
	 * @var string
	 */
	private string $source_language;

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
	 * Return whether this object has text to translate.
	 *
	 * @return bool
	 */
	public function has_text(): bool {
		return ! empty( $this->get_text() );
	}

	/**
	 * Send the request.
	 *
	 * @return void
	 * @noinspection DuplicatedCode
	 */
	public function send(): void {
		if ( empty( $this->url ) ) {
			return;
		}

		// bail if no text for simplification is given.
		if ( ! $this->has_text() ) {
			return;
		}

		// get summ_ai-object.
		$summ_ai_obj = Summ_AI::get_instance();

		// map target language.
		$request_target_language    = 'easy';
		$supported_target_languages = $summ_ai_obj->get_supported_target_languages();
		if ( ! empty( $this->target_language ) && ! empty( $supported_target_languages[ $this->target_language ] ) && ! empty( $supported_target_languages[ $this->target_language ]['api_value'] ) ) {
			$request_target_language = $supported_target_languages[ $this->target_language ]['api_value'];
		}

		// merge header-array.
		$headers = array_merge(
			$this->header,
			array(
				'Authorization' => 'Token: ' . get_option( EASY_LANGUAGE_HASH ),
			)
		);

		// collect arguments for request.
		$args = array(
			'method'      => $this->get_method(),
			'headers'     => $headers,
			'httpversion' => '1.1',
			'timeout'     => get_option( 'easy_language_api_timeout', 60 ),
			'redirection' => 10,
		);

		// set request data.
		$data = array();

		// set request-data for POST.
		if ( 'POST' === $this->get_method() ) {
			$data['input_text']            = $this->get_text();
			$data['input_text_type']       = $this->get_text_type();
			$data['user']                  = $summ_ai_obj->get_contact_email();
			$data['is_test']               = false;
			$data['separator']             = $this->get_separator();
			$data['output_language_level'] = $request_target_language;
			$args['body']                  = wp_json_encode( $data );
		}

		// secure request.
		$this->request = $args;

		// secure start-time.
		$start_time = microtime( true );

		// send request and get the result-object.
		$this->result = wp_remote_post( $this->url, $args );

		// secure end-time.
		$end_time = microtime( true );

		// save duration
		$this->duration = $end_time - $start_time;

		// secure response.
		$this->response = wp_remote_retrieve_body( $this->get_result() );

		// secure http-status.
		$this->http_status = absint( wp_remote_retrieve_response_code( $this->get_result() ) );

		// log the request (with anonymized token).
		$args['headers']['Authorization'] = 'anonymized';
		Log_Api::get_instance()->add_log( $summ_ai_obj->get_name(), $this->http_status, print_r( $args, true ), print_r( 'HTTP-Status: ' . $this->get_http_status() . '<br>' . $this->response, true ) );

		// save request and result in db.
		$this->save_in_db();
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
	 * Return the response of this request.
	 *
	 * @return int
	 */
	public function get_http_status(): int {
		return $this->http_status;
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
	 * Get the complete request results.
	 *
	 * @return array|WP_Error
	 */
	public function get_result(): WP_Error|array {
		return $this->result;
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
	 * Get text_type for request.
	 *
	 * @return string
	 */
	public function get_separator(): string {
		return $this->separator;
	}

	/**
	 * Get text_type for request.
	 *
	 * @return string
	 */
	public function get_text_type(): string {
		return $this->text_type;
	}

	/**
	 * Set text_type for request.
	 *
	 * @param string $type
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function set_text_type( string $type ): void {
		if ( in_array( $type, array( 'html', 'plain_text' ), true ) ) {
			$this->text_type = $type;
		}
	}

	/**
	 * Set target-language for this request.
	 *
	 * @param $lang
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function set_target_language( $lang ): void {
		$this->target_language = $lang;
	}

	/**
	 * Set source-language for this request.
	 *
	 * @param string $lang The source language as string (e.g. "de_EL").
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function set_source_language( string $lang ): void {
		$this->source_language = $lang;
	}

	/**
	 * Save the data of this request in DB.
	 *
	 * @return void
	 */
	private function save_in_db(): void {
		// save the text in db and return the resulting text-object.
		$query = array(
			'time'       => gmdate( 'Y-m-d H:i:s' ),
			'request'    => serialize( $this->get_request() ),
			'response'   => $this->get_response(),
			'duration'   => $this->duration,
			'httpstatus' => $this->get_http_status(),
			'quota'      => strlen( $this->get_text() ),
			'blog_id'    => get_current_blog_id(),
		);
		$this->wpdb->insert( $this->table_requests, $query );
	}

	/**
	 * Return the request-content.
	 *
	 * @return array
	 */
	private function get_request(): array {
		return $this->request;
	}

	/**
	 * Set this as test.
	 *
	 * @param bool $is_test true if this request is a test.
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function set_is_test( bool $is_test ): void {
		$this->is_test = $is_test;
	}
}
