<?php
/**
 * Tests for the REST API endpoint of the language switcher.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\Tests\Switcher_Tests;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Object to test the endpoint "easy-language/v1/switcher-objects" for the search of pages and terms.
 */
class Switcher_Rest extends Switcher_Tests {

	/**
	 * The route of the endpoint.
	 */
	private const ROUTE = '/easy-language/v1/switcher-objects';

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// initialize the REST server with our routes.
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		// ignore the known notice for array settings without schema for their items (not part of these tests).
		// TODO remove this after the settings have been fixed.
		unset( $this->caught_doing_it_wrong['register_setting'] );
	}

	/**
	 * Clean up the REST server.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	/**
	 * Send a request to our endpoint.
	 *
	 * @param array<string,string> $params The parameters.
	 *
	 * @return WP_REST_Response
	 */
	private function request( array $params ): WP_REST_Response {
		$request = new WP_REST_Request( 'GET', self::ROUTE );
		foreach ( $params as $name => $value ) {
			$request->set_param( $name, $value );
		}
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Return the values of the objects in the response.
	 *
	 * @param WP_REST_Response $response The response.
	 *
	 * @return array<int,string>
	 */
	private function get_values( WP_REST_Response $response ): array {
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		return wp_list_pluck( $data, 'value' );
	}

	/**
	 * Test that the endpoint is registered.
	 *
	 * @return void
	 */
	public function test_route_is_registered(): void {
		$this->assertArrayHasKey( self::ROUTE, rest_get_server()->get_routes() );
	}

	/**
	 * Test that the endpoint is only usable for users who could edit posts.
	 *
	 * @return void
	 */
	public function test_permission(): void {
		wp_set_current_user( 0 );
		$this->assertEquals( 401, $this->request( array( 'search' => 'About' ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertEquals( 403, $this->request( array( 'search' => 'About' ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertEquals( 200, $this->request( array( 'search' => 'About' ) )->get_status() );
	}

	/**
	 * Test the search for pages and terms: only originals of supported types are found.
	 *
	 * @return void
	 */
	public function test_search(): void {
		// pages: the original is found, its simplification not.
		$values = $this->get_values( $this->request( array( 'search' => 'About' ) ) );
		$this->assertContains( 'post:' . $this->ids['about'], $values );
		$this->assertNotContains( 'post:' . $this->ids['about_ls'], $values );

		// terms: the original is found, its simplification not.
		$values = $this->get_values( $this->request( array( 'search' => 'Inclusion' ) ) );
		$this->assertContains( 'term:' . $this->ids['cat'], $values );
		$this->assertNotContains( 'term:' . $this->ids['cat_ls'], $values );

		// labels contain the type of the object.
		$data = $this->request( array( 'search' => 'Contact' ) )->get_data();
		$this->assertIsArray( $data );
		$this->assertEquals( 'post:' . $this->ids['contact'], $data[0]['value'] );
		$this->assertEquals( 'Contact (Page)', $data[0]['label'] );

		// not supported post types are not found.
		self::factory()->post->create(
			array(
				'post_type'  => 'wp_block',
				'post_title' => 'About pattern',
			)
		);
		$this->assertCount( 1, $this->get_values( $this->request( array( 'search' => 'About' ) ) ) );
	}

	/**
	 * Test the labels of chosen objects.
	 *
	 * @return void
	 */
	public function test_include(): void {
		$values = $this->get_values( $this->request( array( 'include' => 'post:' . $this->ids['about'] . ',term:' . $this->ids['cat'] . ',foo:1' ) ) );
		$this->assertEquals( array( 'post:' . $this->ids['about'], 'term:' . $this->ids['cat'] ), $values );
	}

	/**
	 * Test that the labels do not reveal objects the user could not read or which are not supported.
	 *
	 * @return void
	 */
	public function test_include_does_not_reveal_other_objects(): void {
		$private_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'private',
				'post_title'  => 'Secret',
				'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ),
			)
		);
		$block_id   = self::factory()->post->create(
			array(
				'post_type'  => 'wp_block',
				'post_title' => 'Pattern',
			)
		);
		$tag_id     = self::factory()->tag->create( array( 'name' => 'Not supported' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$values = $this->get_values( $this->request( array( 'include' => 'post:' . $private_id . ',post:' . $block_id . ',term:' . $tag_id . ',post:' . $this->ids['about'] ) ) );
		$this->assertEquals( array( 'post:' . $this->ids['about'] ), $values );
	}

	/**
	 * Test that the count of objects in one request is limited.
	 *
	 * @return void
	 */
	public function test_include_is_limited(): void {
		$ids = self::factory()->post->create_many( 60, array( 'post_type' => 'page' ) );
		$include = implode(
			',',
			array_map(
				function ( $id ) {
					return 'post:' . $id;
				},
				$ids
			)
		);
		$this->assertCount( 50, $this->get_values( $this->request( array( 'include' => $include ) ) ) );
	}
}
