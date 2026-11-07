<?php
/**
 * Tests for the handling of errors in the requests to the APIs.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Apis;

use easyLanguage\Apis\Capito\Capito;
use easyLanguage\Apis\ChatGpt\ChatGpt;
use easyLanguage\Apis\Summ_Ai\Summ_Ai;
use easyLanguage\Plugin\Log_Api;
use easyLanguage\Tests\easyLanguageTests;
use WP_Error;
use WP_HTTP_Requests_Response;

/**
 * Object to test that errors of the APIs never end in a simplification, a PHP error or a lost log entry.
 */
class Request_Errors extends easyLanguageTests {
	/**
	 * The simulated response: a WP_Error or an array with status and body.
	 *
	 * @var WP_Error|array{status: int, body: string}
	 */
	private static WP_Error|array $response;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		add_filter( 'pre_http_request', array( self::class, 'filter_requests' ), 10, 3 );
		add_filter( 'pre_option_easy_language_chatgpt_api_key', array( self::class, 'get_example_token' ) );
		add_filter( 'pre_option_easy_language_capito_api_key', array( self::class, 'get_example_token' ) );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( self::class, 'filter_requests' ) );
		remove_filter( 'pre_option_easy_language_chatgpt_api_key', array( self::class, 'get_example_token' ) );
		remove_filter( 'pre_option_easy_language_capito_api_key', array( self::class, 'get_example_token' ) );
		delete_option( 'easy_language_summ_ai_disable_free_requests' );
		parent::tear_down();
	}

	/**
	 * Return an example token for the requests.
	 *
	 * @return string
	 */
	public static function get_example_token(): string {
		return 'example-token';
	}

	/**
	 * Return the simulated response.
	 *
	 * @param mixed               $response    The response.
	 * @param array<string,mixed> $parsed_args The arguments.
	 * @param string              $url         The URL.
	 *
	 * @return mixed
	 */
	public static function filter_requests( mixed $response, array $parsed_args, string $url ): mixed {
		if ( self::$response instanceof WP_Error ) {
			return self::$response;
		}
		$requests_response              = new \WpOrg\Requests\Response();
		$requests_response->status_code = self::$response['status'];
		return array(
			'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] ),
			'body'          => self::$response['body'],
		);
	}

	/**
	 * Return the possible errors.
	 *
	 * @return array<string,array{0: string, 1: WP_Error|array{status: int, body: string}}>
	 */
	public static function get_errors(): array {
		$list = array();
		foreach ( array( 'summ_ai', 'capito', 'chatgpt' ) as $api ) {
			$list[ $api . ': no connection' ]       = array( $api, new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ) );
			$list[ $api . ': server error' ]        = array(
				$api,
				array(
					'status' => 500,
					'body'   => 'Internal Server Error',
				),
			);
			$list[ $api . ': not authorized' ]      = array(
				$api,
				array(
					'status' => 401,
					'body'   => '{"detail":"Invalid token"}',
				),
			);
			$list[ $api . ': invalid JSON' ]        = array(
				$api,
				array(
					'status' => 200,
					'body'   => '<html>Maintenance</html>',
				),
			);
			$list[ $api . ': empty response' ]      = array(
				$api,
				array(
					'status' => 200,
					'body'   => '',
				),
			);
			$list[ $api . ': unexpected JSON' ]     = array(
				$api,
				array(
					'status' => 200,
					'body'   => '{"result":{"text":"Ein Text."}}',
				),
			);
			$list[ $api . ': text is not a string' ] = array(
				$api,
				array(
					'status' => 200,
					'body'   => '{"translated_text":["Ein Text."],"content":["Ein Text."],"choices":[{"message":{"content":["Ein Text."]}}]}',
				),
			);
		}
		return $list;
	}

	/**
	 * Call the API with the given name.
	 *
	 * @param string $api The name of the API.
	 *
	 * @return array<string,mixed>
	 */
	private function call_api( string $api ): array {
		return match ( $api ) {
			'capito'  => Capito::get_instance()->get_simplifications_obj()->call_api( 'Ein Text.', 'de_DE', 'de_a1', false ),
			'chatgpt' => ChatGpt::get_instance()->get_simplifications_obj()->call_api( 'Ein Text.', 'de_DE', 'de_a1', false ),
			default   => Summ_Ai::get_instance()->get_simplifications_obj()->call_api( 'Ein Text.', 'de_DE', 'de_LS', false ),
		};
	}

	/**
	 * Return the count of error entries of the given API in the API log.
	 *
	 * @param string $api The name of the API.
	 *
	 * @return int
	 */
	private function count_log_errors( string $api ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `api` = %s AND `state` = %s AND `blog_id` = %d', Log_Api::get_instance()->get_table_name(), $api, 'error', get_current_blog_id() ) ) );
	}

	/**
	 * An error of the API results in no simplification and no PHP error.
	 *
	 * @dataProvider get_errors
	 *
	 * @param string                                       $api The name of the API.
	 * @param WP_Error|array{status: int, body: string} $response The simulated response.
	 *
	 * @return void
	 */
	public function test_error_results_in_no_simplification( string $api, WP_Error|array $response ): void {
		self::$response = $response;
		$this->assertSame( array(), $this->call_api( $api ) );
	}

	/**
	 * Failed requests (no connection or HTTP status not 200) are logged in the API log, also without debug mode.
	 *
	 * @dataProvider get_errors
	 *
	 * @param string                                       $api The name of the API.
	 * @param WP_Error|array{status: int, body: string} $response The simulated response.
	 *
	 * @return void
	 */
	public function test_failed_request_is_logged( string $api, WP_Error|array $response ): void {
		if ( is_array( $response ) && 200 === $response['status'] ) {
			$this->markTestSkipped( 'Requests with status 200 are only logged in debug mode.' );
		}
		update_option( 'easy_language_debug_mode', 0 );
		self::$response = $response;
		$before         = $this->count_log_errors( $api );

		$this->call_api( $api );

		$this->assertGreaterThan( $before, $this->count_log_errors( $api ) );
	}

	/**
	 * The API key is never saved in the API log or in the request table, also on errors.
	 *
	 * @dataProvider get_errors
	 *
	 * @param string                                       $api The name of the API.
	 * @param WP_Error|array{status: int, body: string} $response The simulated response.
	 *
	 * @return void
	 */
	public function test_api_key_is_not_logged( string $api, WP_Error|array $response ): void {
		global $wpdb;
		update_option( 'easy_language_debug_mode', 1 );
		self::$response = $response;

		$this->call_api( $api );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertSame( '0', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `request` LIKE %s', Log_Api::get_instance()->get_table_name(), '%example-token%' ) ) );
		foreach ( array( Summ_Ai::get_instance(), Capito::get_instance(), ChatGpt::get_instance() ) as $api_obj ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->assertSame( '0', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `request` LIKE %s', $api_obj->get_request_table(), '%example-token%' ) ) );
		}
		update_option( 'easy_language_debug_mode', 0 );
	}

	/**
	 * SUMM AI could disable the free requests via its response (e.g. if the free quota is used up for all).
	 *
	 * @return void
	 */
	public function test_summ_ai_disables_free_requests(): void {
		delete_option( 'easy_language_summ_ai_disable_free_requests' );
		self::$response = array(
			'status' => 200,
			'body'   => '{"translated_text":"Ein leichter Text.","jobid":1,"disabled":true}',
		);

		$result = $this->call_api( 'summ_ai' );

		$this->assertSame( 'Ein leichter Text.', $result['translated_text'] ?? '' );
		$this->assertSame( 1, absint( get_option( 'easy_language_summ_ai_disable_free_requests' ) ) );
		$this->assertFalse( Summ_Ai::get_instance()->is_free_requests_enabled() );
	}

	/**
	 * Without API key no request is sent.
	 *
	 * @return void
	 */
	public function test_no_request_without_api_key(): void {
		remove_filter( 'pre_option_easy_language_chatgpt_api_key', array( self::class, 'get_example_token' ) );
		delete_option( 'easy_language_chatgpt_api_key' );

		$requests = 0;
		$counter  = static function ( $response ) use ( &$requests ) {
			++$requests;
			return $response;
		};
		add_filter( 'pre_http_request', $counter, 5 );
		self::$response = array(
			'status' => 200,
			'body'   => '{"choices":[{"message":{"content":"Ein Text."}}]}',
		);

		$this->assertSame( array(), $this->call_api( 'chatgpt' ) );
		remove_filter( 'pre_http_request', $counter, 5 );

		$this->assertSame( 0, $requests );
	}
}
