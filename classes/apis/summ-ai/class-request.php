<?php
/**
 * File for handler for each request to SUMM AI API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Summ_Ai;

use easyLanguage\Log;
use easyLanguage\Log_Api;
use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use WP_Error;

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
	 * Token aka key to use for the request.
	 *
	 * @var string
	 */
	private string $token = '';

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
	 * Source-language for this request.
	 *
	 * @var string
	 */
	private string $source_language;

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
	 * Constructor.
	 */
	public function __construct() {
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
		// bail if no request URL is given.
		if ( empty( $this->url ) ) {
			return;
		}

		// bail of no token given.
		if( empty($this->get_token()) ) {
			// Log event.
			Log::get_instance()->add_log( 'SUMM AI: no API key given for simplification.', 'error' );

			return;
		}

		// bail if no text for simplification is given.
		if ( ! $this->has_text() && 'POST' === $this->get_method() ) {
			// Log event.
			Log::get_instance()->add_log( 'SUMM AI: no text given for simplification.', 'error' );

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
				'Authorization' => ($summ_ai_obj->is_free_mode() ? 'Token: ' : 'Bearer ').$this->get_token(),
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

		// collect attributes to send request-data via POST-method.
		if ( 'POST' === $this->get_method() ) {
			$data['input_text']            = $this->get_text();
			$data['input_text_type']       = $this->get_text_type();
			$data['user']                  = $summ_ai_obj->get_contact_email();
			$data['is_test']               = $this->is_test();
			$data['separator']             = $this->get_separator();
			$data['output_language_level'] = $request_target_language;
			$args['body']                  = wp_json_encode( $data );
		}

		// secure request.
		$this->request = $args;

		// secure start-time.
		$start_time = microtime( true );

		// send request and get the result-object depending on used request method.
		switch( $this->get_method() ) {
			case 'POST':
				$this->result = wp_safe_remote_post( $this->url, $args );
				break;
			case 'GET':
				$this->result = wp_safe_remote_get( $this->url, $args );
				break;
		}

		// secure end-time.
		$end_time = microtime( true );

		// save duration.
		$this->duration = $end_time - $start_time;

		// log error if something happened.
		if ( is_wp_error( $this->result ) ) {
			Log::get_instance()->add_log( sprintf( 'Error during request on API %1$s: '.$this->result->get_error_message(), esc_html($summ_ai_obj->get_name()) ), 'error' );
		}
		else {
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
	}

	/**
	 * Return whether this request is a test (which will not result in any simplification).
	 * If true the communication is just tested.
	 *
	 * @return bool
	 */
	public function is_test(): bool {
		return $this->is_test;
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
	 * @param string $type The type to use for this request.
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
	 * @param string $lang The target language.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function set_target_language( string $lang ): void {
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
		global $wpdb;

		// save the text in db and return the resulting text-object.
		$query = array(
			'time'       => gmdate( 'Y-m-d H:i:s' ),
			'request'    => wp_json_encode( $this->get_request() ),
			'response'   => $this->get_response(),
			'duration'   => $this->duration,
			'httpstatus' => $this->get_http_status(),
			'quota'      => strlen( $this->get_text() ),
			'blog_id'    => get_current_blog_id(),
		);
		$wpdb->insert( $this->table_requests, $query );

		// log error.
		if( $wpdb->last_error ) {
			Log::get_instance()->add_log( 'Error during adding API log entry: '.$wpdb->last_error, 'error' );
		}
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

	/**
	 * Get SUMM AI API token aka key.
	 *
	 * @return string
	 */
	private function get_token(): string {
		return $this->token;
	}

	/**
	 * Set SUMM AI API token.
	 *
	 * @param string $token
	 *
	 * @return void
	 */
	public function set_token( string $token ): void {
		$this->token = $token;
	}

	/**
	 * Set request method (GET, POST, HEAD ..)
	 *
	 * @param string $string
	 * @return void
	 */
	public function set_method( string $string ): void {
		if( in_array($string, array( 'GET', 'POST' ), true ) ) {
			$this->method = $string;
		}
	}
}
