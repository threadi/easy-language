<?php
/**
 * Tests to simplify an existing object via the abilities of Easy Language.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Scenarios;

use easyLanguage\Tests\easyLanguageTests;
use WP_Error;
use WP_HTTP_Requests_Response;

/**
 * Object to test the simplification of an existing object via abilities and the SUMM AI API.
 */
class AddSimplificationObjectAndSimplifyItViaAbilities extends easyLanguageTests {
	/**
	 * The ID of the example post.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * The target language.
	 *
	 * @var string
	 */
	private string $target_language = 'de_LS';

	/**
	 * Count of requests sent to the API.
	 *
	 * @var int
	 */
	private static int $api_requests = 0;

	/**
	 * HTTP status the simulated API returns.
	 *
	 * @var int
	 */
	private static int $api_status = 200;

	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// bail if the Abilities API is not available (WordPress < 6.9).
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'The Abilities API requires WordPress 6.9 or newer.' );
		}

		// simulate the API.
		self::$api_requests = 0;
		self::$api_status   = 200;
		add_filter( 'pre_http_request', array( self::class, 'filter_requests' ), 10, 3 );

		// set language.
		switch_to_locale( 'de_DE' );

		// use an administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// enable all abilities.
		update_option( \easyLanguage\EasyLanguage\Abilities::OPTION_ENABLED, 1 );
		update_option( \easyLanguage\EasyLanguage\Abilities::OPTION_MODE, 'all' );
		$this->reregister_abilities();

		// reset the quota.
		update_option( 'easy_language_summ_ai_quota', 0 );

		// create the original post.
		$this->post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Hallo SUMM AI',
				'post_content' => '<p>Das ist ein kompliziertes Beispiel für Text für SUMM AI.</p>',
				'post_type'    => 'post',
			)
		);
	}

	/**
	 * Clean up after the test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		// no TRUNCATE via Db::reset_simplifications() here: in MySQL it commits the test transaction,
		// the texts of this test would then remain for other tests. The rollback of WordPress removes them.
		remove_filter( 'pre_http_request', array( self::class, 'filter_requests' ) );
		update_option( 'easy_language_summ_ai_quota', 0 );
		wp_unschedule_hook( \easyLanguage\EasyLanguage\Abilities::CRON_HOOK );

		parent::tear_down();
	}

	/**
	 * Register our abilities again with the actual settings.
	 *
	 * @return void
	 */
	private function reregister_abilities(): void {
		global $wp_current_filter;

		foreach ( wp_get_abilities() as $ability ) {
			if ( str_starts_with( $ability->get_name(), 'easy-language/' ) ) {
				wp_unregister_ability( $ability->get_name() );
			}
		}

		$wp_current_filter[] = 'wp_abilities_api_init';
		\easyLanguage\EasyLanguage\Abilities::get_instance()->register_abilities();
		array_pop( $wp_current_filter );
	}

	/**
	 * Execute an ability and return its result.
	 *
	 * @param string $name The name without namespace.
	 * @param mixed  $input The input.
	 *
	 * @return mixed
	 */
	private function run_ability( string $name, mixed $input = null ): mixed {
		return wp_get_ability( 'easy-language/' . $name )->execute( $input );
	}

	/**
	 * Create the simplified object and return its ID.
	 *
	 * @return int
	 */
	private function create_simplification(): int {
		$result = $this->run_ability(
			'create-simplification',
			array(
				'object_id' => $this->post_id,
				'language'  => $this->target_language,
			)
		);
		$this->assertIsArray( $result );
		$this->assertEquals( 2, $result['texts_to_simplify'] );
		return absint( $result['simplification']['object_id'] );
	}

	/**
	 * Return the status of the simplification.
	 *
	 * @return array<string,mixed>
	 */
	private function get_status(): array {
		$result = $this->run_ability( 'get-object-simplifications', array( 'object_id' => $this->post_id ) );
		return $result['simplifications'][0];
	}

	/**
	 * Test the complete process: create, schedule, run in background.
	 *
	 * @return void
	 */
	public function test_create_and_simplify_object_via_api(): void {
		$copy_id = $this->create_simplification();

		// schedule the simplification.
		$result = $this->run_ability( 'run-simplification', array( 'object_id' => $copy_id ) );
		$this->assertIsArray( $result );
		$this->assertEquals( 'scheduled', $result['status'] );
		$this->assertNotFalse( wp_next_scheduled( \easyLanguage\EasyLanguage\Abilities::CRON_HOOK, array( $copy_id, 'post' ) ) );
		$this->assertTrue( $this->get_status()['scheduled'] );

		// a second request is not allowed while it is scheduled.
		$this->assertInstanceOf( WP_Error::class, $this->run_ability( 'run-simplification', array( 'object_id' => $copy_id ) ) );

		// nothing has been sent to the API yet.
		$this->assertEquals( 0, self::$api_requests );

		// run the background process.
		\easyLanguage\EasyLanguage\Abilities::get_instance()->run_scheduled_simplification( $copy_id, 'post' );

		// all texts are simplified and the process is finished.
		$status = $this->get_status();
		$this->assertGreaterThan( 0, self::$api_requests );
		$this->assertEquals( 0, $status['texts']['to_simplify'] );
		$this->assertEquals( 2, $status['texts']['in_use'] );
		$this->assertFalse( $status['running'] );
		$this->assertStringContainsString( 'Das ist ein vereinfachter Beispiel Text.', (string) get_post_field( 'post_content', $copy_id ) );
	}

	/**
	 * Test that texts stay open and the process ends if the API returns errors.
	 *
	 * @return void
	 */
	public function test_api_error_keeps_texts_open(): void {
		$copy_id = $this->create_simplification();
		$this->run_ability( 'run-simplification', array( 'object_id' => $copy_id ) );

		// let the API fail.
		self::$api_status = 500;
		\easyLanguage\EasyLanguage\Abilities::get_instance()->run_scheduled_simplification( $copy_id, 'post' );

		$status = $this->get_status();
		$this->assertGreaterThan( 0, self::$api_requests );
		$this->assertEquals( 2, $status['texts']['to_simplify'] );
		$this->assertFalse( $status['running'] );
	}

	/**
	 * Test that nothing is scheduled and nothing is sent to the API if the quota is used up.
	 *
	 * @return void
	 */
	public function test_used_up_quota_cancels_without_request(): void {
		$copy_id = $this->create_simplification();

		foreach ( array( EASY_LANGUAGE_SUMM_AI_QUOTA, EASY_LANGUAGE_SUMM_AI_QUOTA + 1764 ) as $spent ) {
			update_option( 'easy_language_summ_ai_quota', $spent );

			$result = $this->run_ability( 'run-simplification', array( 'object_id' => $copy_id ) );
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertEquals( 'easy_language_quota_exceeded', $result->get_error_code() );
			$this->assertFalse( wp_next_scheduled( \easyLanguage\EasyLanguage\Abilities::CRON_HOOK, array( $copy_id, 'post' ) ) );
		}

		$this->assertEquals( 0, self::$api_requests );
		$this->assertEquals( 2, $this->get_status()['texts']['to_simplify'] );
	}

	/**
	 * Test that a scheduled simplification is canceled if the quota is used up before it runs.
	 *
	 * @return void
	 */
	public function test_quota_used_up_after_scheduling(): void {
		$copy_id = $this->create_simplification();

		$result = $this->run_ability( 'run-simplification', array( 'object_id' => $copy_id ) );
		$this->assertEquals( 'scheduled', $result['status'] );

		// the quota is used up in the meantime.
		update_option( 'easy_language_summ_ai_quota', EASY_LANGUAGE_SUMM_AI_QUOTA + 1 );
		\easyLanguage\EasyLanguage\Abilities::get_instance()->run_scheduled_simplification( $copy_id, 'post' );

		$status = $this->get_status();
		$this->assertEquals( 0, self::$api_requests );
		$this->assertEquals( 2, $status['texts']['to_simplify'] );
		$this->assertFalse( $status['running'] );
	}

	/**
	 * Simulate the responses of the API and count the requests.
	 *
	 * @param bool|array<string,mixed> $response The return value.
	 * @param array<string,mixed>      $parsed_args The parsed arguments.
	 * @param string                   $url The used URL.
	 *
	 * @return bool|array<string,mixed>
	 */
	public static function filter_requests( bool|array $response, array $parsed_args, string $url ): bool|array {
		// bail if this is not a request to the API.
		if ( 'POST' !== $parsed_args['method'] || ! str_starts_with( $url, \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_api_url() ) ) {
			return $response;
		}

		// count it.
		++self::$api_requests;

		// create the response.
		$requests_response              = new \WpOrg\Requests\Response();
		$requests_response->status_code = self::$api_status;
		return array(
			'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] ),
			'response'      => array(
				'code'    => self::$api_status,
				'message' => 200 === self::$api_status ? 'OK' : 'Internal Server Error',
			),
			'body'          => 200 === self::$api_status ? \easyLanguage\Plugin\Helper::get_wp_filesystem()->get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'summ_ai.json' ) : '{"error":"failed"}',
		);
	}
}
