<?php
/**
 * Tests for class easyLanguage\Apis\Capito\ChatGpt.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Apis\ChatGpt;

use easyLanguage\Tests\easyLanguageTests;
use WP_HTTP_Requests_Response;

/**
 * Object to test functions in class easyLanguage\Apis\Capito\ChatGpt.
 */
class Simplifications extends easyLanguageTests {
	/**
	 * The test source language.
	 *
	 * @var string
	 */
	private string $test_source_language = 'de_DE';

	/**
	 * The test text.
	 *
	 * @var string
	 */
	private string $test_text = 'Das ist ein Beispiel Text.';

	/**
	 * The test source language.
	 *
	 * @var string
	 */
	private string $test_target_language = 'de_a1';

	/**
	 * The object to test.
	 *
	 * @var \easyLanguage\Apis\ChatGpt\Simplifications
	 */
	private \easyLanguage\Apis\ChatGpt\Simplifications $object;

	/**
	 * Prepare the test environment for this object.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// prevent external requests from Personio APIs.
		add_filter( 'pre_http_request', array( self::class, 'filter_requests' ), 10, 3 );

		// use an example token, as requests without token are not sent.
		add_filter( 'pre_option_easy_language_chatgpt_api_key', array( self::class, 'get_example_token' ) );

		// get the simplification object.
		$this->object = \easyLanguage\Apis\ChatGpt\Simplifications::get_instance();
		$this->object->set_api( $this->get_api_obj() );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_option_easy_language_chatgpt_api_key', array( self::class, 'get_example_token' ) );
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
	 * Test if the returning variable is a boolean.
	 *
	 * @return void
	 */
	public function test_call_api(): void {
		$result = $this->object->call_api( $this->test_text, $this->test_source_language, $this->test_target_language, false );
		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
		$this->assertArrayHasKey( 'translated_text', $result );
		$this->assertArrayHasKey( 'jobid', $result );
	}

	/**
	 * The prompt and the text are separated by an empty line.
	 *
	 * @return void
	 */
	public function test_request_text_separates_prompt_and_text(): void {
		// the prompt ends without space, like after saving the settings.
		$this->assertSame( "Vereinfache bitte den Text.\n\nDas ist ein Text.", $this->object->get_request_text( 'Vereinfache bitte den Text.', 'Das ist ein Text.' ) );

		// whitespace at the end of the prompt is not doubled.
		$this->assertSame( "Vereinfache bitte den Text.\n\nDas ist ein Text.", $this->object->get_request_text( "Vereinfache bitte den Text. \n", 'Das ist ein Text.' ) );

		// without prompt only the text is sent.
		$this->assertSame( 'Das ist ein Text.', $this->object->get_request_text( '', 'Das ist ein Text.' ) );
	}

	/**
	 * The request to the API contains the prompt and the text, separated by an empty line.
	 *
	 * @return void
	 */
	public function test_request_contains_separated_prompt(): void {
		update_option( 'easy_language_chatgpt_target_languages_prompts_' . $this->test_target_language, 'Vereinfache bitte den Text.' );

		// get the body of the request.
		$body = '';
		$catch = static function ( $response, $parsed_args ) use ( &$body ) {
			$body = (string) ( $parsed_args['body'] ?? '' );
			return $response;
		};
		add_filter( 'pre_http_request', $catch, 5, 2 );
		$this->object->call_api( $this->test_text, $this->test_source_language, $this->test_target_language, false );
		remove_filter( 'pre_http_request', $catch, 5 );

		delete_option( 'easy_language_chatgpt_target_languages_prompts_' . $this->test_target_language );

		$this->assertStringContainsString( wp_json_encode( "Vereinfache bitte den Text.\n\n" . $this->test_text ), $body );
	}

	/**
	 * Filter any outgoing requests to simulate the responses.
	 *
	 * @param bool|array $false The return value.
	 * @param array $parsed_args The parsed arguments.
	 * @param string $url The used URL.
	 *
	 * @return bool|array
	 */
	public static function filter_requests( bool|array $false, array $parsed_args, string $url ): bool|array {
		// create a local response for the POST request.
		if( 'POST' === $parsed_args['method'] && str_starts_with( $url, ( new Simplifications )->get_api_obj()->get_api_url() ) ) {
			// get our example response.
			$json = \easylanguage\Plugin\Helper::get_wp_filesystem()->get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'chatgpt.json' );

			// create the response object.
			$requests_response = new \WpOrg\Requests\Response();
			$requests_response->status_code = 200;

			// create the header response.
			return array(
				'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] ),
				'body' => $json
			);
		}

		// return the given value.
		return $false;
	}

	/**
	 * Return the API object.
	 *
	 * @return \easyLanguage\Apis\ChatGpt\ChatGpt
	 */
	public function get_api_obj(): \easyLanguage\Apis\ChatGpt\ChatGpt {
		return \easyLanguage\Apis\ChatGpt\ChatGpt::get_instance();
	}
}
