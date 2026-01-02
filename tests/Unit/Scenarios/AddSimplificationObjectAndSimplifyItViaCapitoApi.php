<?php
/**
 * Tests to simplify an existing object via API.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Scenarios;

use easyLanguage\Tests\easyLanguageTests;
use WP_HTTP_Requests_Response;

/**
 * Object to test the simplification of an existing object via API.
 */
class AddSimplificationObjectAndSimplifyItViaCapitoApi extends easyLanguageTests {
	/**
	 * The test text.
	 *
	 * @var string
	 */
	private string $test_title = 'Hallo Capito';

	/**
	 * The test text.
	 *
	 * @var string
	 */
	private string $test_text = '<p>Das ist ein kompliziertes Beispiel für Text für Capito.</p>';

	/**
	 * The ID of the example post.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * The post-type.
	 *
	 * @var string
	 */
	private string $post_type = 'post';

	/**
	 * The target language.
	 *
	 * @var string
	 */
	private string $target_language = 'de_b1';

	/**
	 * The API object to use it for simplifications.
	 *
	 * @var \easyLanguage\Apis\Capito\Capito
	 */
	private \easyLanguage\Apis\Capito\Capito $api_obj;

	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// set a pseudo-key.
		update_option( 'easy_language_capito_api_key', 'example-key' );

		// prevent external requests from Personio APIs.
		add_filter( 'pre_http_request', array( self::class, 'filter_requests' ), 10, 3 );

		// set language.
		switch_to_locale( 'de_DE' );

		// create the original post with sample texts to simplify.
		$this->post_id = self::factory()->post->create( array( 'post_content' => $this->test_text, 'post_title' => $this->test_title, 'post_type' => 'post' ) );

		// get the API object.
		$this->api_obj = \easyLanguage\Apis\Capito\Capito::get_instance();
	}

	/**
	 * Clean up after the test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		parent::tear_down();

		// reset the simplifications.
		\easyLanguage\EasyLanguage\Db::get_instance()->reset_simplifications();

		// remove the filter.
		remove_filter( 'pre_http_request', array( self::class, 'filter_requests' ) );

		// delete the post.
		wp_delete_post( $this->post_id, true );
	}

	/**
	 * Test to create and simplify an object via capito.
	 *
	 * This is the same process as in the backend if you click there on the "+" under "posts".
	 *
	 * The result after this test must be the same as in the backend:
	 * - the original must be a simplified object.
	 * - the simplified object must have 2 entries for the texts (title + 1 paragraph).
	 *
	 * @return void
	 */
	public function test_create_and_simplify_object(): void {
		// get the object.
		$object = \easyLanguage\Plugin\Helper::get_object( $this->post_id, $this->post_type );

		// test it.
		$this->assertIsObject( $object );
		$this->assertInstanceOf( \easyLanguage\EasyLanguage\Post_Object::class, $object );
		$this->assertFalse( $object->is_simplified() );
		$this->assertFalse( $object->has_simplifications() );

		// add a copy of it which will be simplified later.
		$copy_obj = $object->add_simplification_object( $this->target_language, $this->api_obj, false );

		// test it.
		$this->assertIsObject( $copy_obj );
		$this->assertInstanceOf( \easyLanguage\EasyLanguage\Post_Object::class, $copy_obj );
		$this->assertEmpty( $copy_obj->get_simplifications() );

		// run simplification of X text-entries on the given object.
		$copy_obj->process_simplifications( $this->api_obj->get_simplifications_obj(), $this->api_obj->get_active_language_mapping(), 10 );

		// test it.
		$entries = $copy_obj->get_entries();
		$this->assertIsArray( $entries );
		$this->assertNotEmpty( $entries );
		$this->assertCount( 2, $entries );
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
		if( 'POST' === $parsed_args['method'] && str_starts_with( $url, ( new AddSimplificationObjectAndSimplifyItViaCapitoApi() )->get_api_obj()->get_api_url() ) ) {
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
