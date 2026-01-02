<?php
/**
 * Tests for class easyLanguage\Apis\Capito\Simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Apis\Capito;

use easyLanguage\Tests\easyLanguageTests;
use WP_HTTP_Requests_Response;

/**
 * Object to test functions in class easyLanguage\Apis\Capito\Simplifications.
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
	 * @var \easyLanguage\Apis\Capito\Simplifications
	 */
	private \easyLanguage\Apis\Capito\Simplifications $object;

	/**
	 * Prepare the test environment for this object.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// prevent external requests from Personio APIs.
		add_filter( 'pre_http_request', array( self::class, 'filter_requests' ), 10, 3 );

		// get the simplification object.
		$this->object = \easyLanguage\Apis\Capito\Simplifications::get_instance();
		$this->object->set_api( $this->get_api_obj() );
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
		if( 'PUT' === $parsed_args['method'] && str_starts_with( $url, ( new Simplifications )->get_api_obj()->get_api_url() ) ) {
			// get our example response.
			$json = \easylanguage\Plugin\Helper::get_wp_filesystem()->get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'capito.json' );

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
	 * @return \easyLanguage\Apis\Capito\Capito
	 */
	public function get_api_obj(): \easyLanguage\Apis\Capito\Capito {
		return \easyLanguage\Apis\Capito\Capito::get_instance();
	}
}
