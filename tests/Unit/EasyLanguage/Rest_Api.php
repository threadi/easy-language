<?php
/**
 * Tests for class easyLanguage\EasyLanguage\Rest_Api.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Object to test the REST API endpoints of the plugin.
 */
class Rest_Api extends easyLanguageTests {
	/**
	 * The name of the API to use as active API.
	 *
	 * @var string
	 */
	private string $active_api = '';

	/**
	 * Set up the REST server with our routes.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		add_filter( 'pre_http_request', array( $this, 'block_requests' ) );
	}

	/**
	 * Clean up.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		remove_filter( 'pre_http_request', array( $this, 'block_requests' ) );
		remove_filter( 'pre_option_easy_language_api', array( $this, 'get_active_api' ) );
		wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
		parent::tear_down();
	}

	/**
	 * Block all external requests.
	 *
	 * @return \WP_Error
	 */
	public function block_requests(): \WP_Error {
		return new \WP_Error( 'blocked', 'External requests are blocked in tests.' );
	}

	/**
	 * Return the API to use as active API (without saving the option, which would install translations).
	 *
	 * @return string
	 */
	public function get_active_api(): string {
		return $this->active_api;
	}

	/**
	 * Use the given API as active API.
	 *
	 * @param string $name The name of the API.
	 *
	 * @return void
	 */
	private function use_api( string $name ): void {
		$this->active_api = $name;
		add_filter( 'pre_option_easy_language_api', array( $this, 'get_active_api' ) );
	}

	/**
	 * Send a request.
	 *
	 * @param string $method The HTTP method.
	 * @param string $route  The route.
	 *
	 * @return WP_REST_Response
	 */
	private function request( string $method, string $route ): WP_REST_Response {
		return rest_get_server()->dispatch( new WP_REST_Request( $method, $route ) );
	}

	/**
	 * All endpoints are registered.
	 *
	 * @return void
	 */
	public function test_routes_are_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/easy-language/v1/language-options/(?P<id>\d+)', $routes );
		$this->assertArrayHasKey( '/easy-language/v1/automatic_cron_checks', $routes );
		$this->assertArrayHasKey( '/easy-language/v1/api_check', $routes );
	}

	/**
	 * Only administrators could use the checks for Site Health.
	 *
	 * @return void
	 */
	public function test_checks_require_manage_options(): void {
		foreach ( array( '/easy-language/v1/automatic_cron_checks', '/easy-language/v1/api_check' ) as $route ) {
			// visitors.
			wp_set_current_user( 0 );
			$this->assertSame( 401, $this->request( 'GET', $route )->get_status(), $route );

			// editors.
			wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
			$this->assertSame( 403, $this->request( 'GET', $route )->get_status(), $route );

			// administrators.
			wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
			$this->assertSame( 200, $this->request( 'GET', $route )->get_status(), $route );
		}
	}

	/**
	 * The language options could only be loaded by users who could simplify and edit the object.
	 *
	 * @return void
	 */
	public function test_language_options_permissions(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$route   = '/easy-language/v1/language-options/' . $post_id;

		// visitors.
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'POST', $route )->get_status() );

		// subscribers.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, $this->request( 'POST', $route )->get_status() );

		// editors could edit the page, but have no simplification rights.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, $this->request( 'POST', $route )->get_status() );

		// simplifiers without the right to edit this page.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'el_simplifier' ) ) );
		$other_post = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'private',
				'post_author' => self::factory()->user->create( array( 'role' => 'administrator' ) ),
			)
		);
		$this->assertSame( 403, $this->request( 'POST', '/easy-language/v1/language-options/' . $other_post )->get_status() );

		// unknown objects.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 403, $this->request( 'POST', '/easy-language/v1/language-options/999999' )->get_status() );

		// only POST is allowed.
		$this->assertSame( 404, $this->request( 'GET', $route )->get_status() );
	}

	/**
	 * The language options of an original contain the languages it could be simplified in.
	 *
	 * @return void
	 */
	public function test_language_options_for_original(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:paragraph --><p>Ein Text.</p><!-- /wp:paragraph -->',
			)
		);

		$response = $this->request( 'POST', '/easy-language/v1/language-options/' . $post_id );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'html', $data );
		$this->assertNotEmpty( $data['html'] );
	}

	/**
	 * The language options of a simplification name the language of it.
	 *
	 * @return void
	 */
	public function test_language_options_for_simplification(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:paragraph --><p>Ein Text.</p><!-- /wp:paragraph -->',
			)
		);
		$copy    = ( new Post_Object( $post_id ) )->add_simplification_object( 'de_LS', \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance(), true );
		$this->assertInstanceOf( Post_Object::class, $copy );

		$data = $this->request( 'POST', '/easy-language/v1/language-options/' . $copy->get_id() )->get_data();
		$this->assertIsArray( $data );
		$languages = $copy->get_language();
		$this->assertStringContainsString( '<strong>' . esc_html( $languages['de_LS']['label'] ) . '</strong>', $data['html'] );
	}

	/**
	 * The cron check reports a missing schedule with a link to recreate it.
	 *
	 * @return void
	 */
	public function test_cron_check_without_schedule(): void {
		wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );

		$result = \easyLanguage\EasyLanguage\Rest_Api::get_instance()->check_automatic_cron();
		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'action=easy_language_create_automatic_cron', $result['actions'] );
		$this->assertSame( 'easy_language_check_automatic_cron', $result['test'] );
	}

	/**
	 * The cron check is fine for a schedule in the future.
	 *
	 * @return void
	 */
	public function test_cron_check_with_schedule(): void {
		wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'easy_language_automatic_simplification' );

		$result = \easyLanguage\EasyLanguage\Rest_Api::get_instance()->check_automatic_cron();
		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( '', $result['actions'] );
	}

	/**
	 * The cron check reports a schedule which should have been run in the past.
	 *
	 * @return void
	 */
	public function test_cron_check_with_missed_schedule(): void {
		wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
		wp_schedule_event( time() - DAY_IN_SECONDS, 'hourly', 'easy_language_automatic_simplification' );

		$result = \easyLanguage\EasyLanguage\Rest_Api::get_instance()->check_automatic_cron();
		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'cron-system', $result['description'] );
	}

	/**
	 * The API check reports a configured active API as good.
	 *
	 * @return void
	 */
	public function test_api_check_with_configured_api(): void {
		$this->use_api( 'summ_ai' );

		$result = \easyLanguage\EasyLanguage\Rest_Api::get_instance()->check_api();
		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'SUMM AI', $result['description'] );
		$this->assertSame( '', $result['actions'] );
	}

	/**
	 * The API check reports an active API without configuration with a link to its settings.
	 *
	 * @return void
	 */
	public function test_api_check_with_not_configured_api(): void {
		$this->use_api( 'chatgpt' );
		$api = \easyLanguage\Apis\ChatGpt\ChatGpt::get_instance();
		$this->assertFalse( $api->is_configured() );

		$result = \easyLanguage\EasyLanguage\Rest_Api::get_instance()->check_api();
		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'is not configured', $result['description'] );
		$this->assertStringContainsString( esc_url( $api->get_settings_url() ), $result['actions'] );
	}

	/**
	 * The API check reports that no API is active.
	 *
	 * @return void
	 */
	public function test_api_check_without_api(): void {
		$this->use_api( 'unknown' );

		$result = \easyLanguage\EasyLanguage\Rest_Api::get_instance()->check_api();
		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'No Api active!', $result['description'] );
		$this->assertStringContainsString( 'page=easy_language_settings', $result['actions'] );
	}
}
