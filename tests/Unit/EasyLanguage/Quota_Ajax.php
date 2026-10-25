<?php
/**
 * Tests for the quota check of simplifications started in the backend via AJAX.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

/**
 * Object to test that the backend does not start a simplification if the quota is used up.
 */
class Quota_Ajax extends WP_Ajax_UnitTestCase {
	/**
	 * Count of requests sent to the API.
	 *
	 * @var int
	 */
	private static int $api_requests = 0;

	/**
	 * Prepare the plugin once for this class.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// initialize the plugin (same as in all other test classes).
		\easyLanguage\Tests\easyLanguageTests::prepare_plugin();
	}

	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		switch_to_locale( 'de_DE' );
		$this->_setRole( 'administrator' );

		// count requests to the API.
		self::$api_requests = 0;
		add_filter( 'pre_http_request', array( self::class, 'count_requests' ), 10, 3 );

		// register our AJAX action (the plugin registers it during its initialization).
		add_action( 'wp_ajax_easy_language_run_simplification', array( \easyLanguage\EasyLanguage\Init::get_instance(), 'run_simplification_via_ajax' ) );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		// no TRUNCATE via Db::reset_simplifications() here: in MySQL it commits the test transaction,
		// the texts of this test would then remain for other tests. The rollback of WordPress removes them.
		remove_filter( 'pre_http_request', array( self::class, 'count_requests' ) );
		update_option( 'easy_language_summ_ai_quota', 0 );
		parent::tear_down();
	}

	/**
	 * Test that a used up quota cancels the simplification with a dialog and without a request to the API.
	 *
	 * @return void
	 */
	public function test_used_up_quota_cancels_simplification(): void {
		// known issue: array settings of the plugin (e.g. easy_language_post_types) are registered for REST without "items" schema.
		// WP_Ajax_UnitTestCase runs "admin_init", where these settings are registered, which triggers this notice.
		$this->setExpectedIncorrectUsage( 'register_setting' );

		// create an original and its simplified object.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Kontingent',
				'post_content' => '<p>Ein Text.</p>',
			)
		);
		$copy    = ( new \easyLanguage\EasyLanguage\Post_Object( $post_id ) )->add_simplification_object( 'de_LS', \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance(), true );
		$this->assertInstanceOf( \easyLanguage\EasyLanguage\Objects::class, $copy );

		// use up the quota (more than the limit, as SUMM AI may accept requests beyond it).
		update_option( 'easy_language_summ_ai_mode', 'free' );
		update_option( 'easy_language_summ_ai_quota', EASY_LANGUAGE_SUMM_AI_QUOTA + 1764 );

		// start the simplification like the backend does.
		$_POST['nonce']          = wp_create_nonce( 'easy-language-run-simplification-nonce' );
		$_POST['id']             = $copy->get_id();
		$_POST['type']           = 'post';
		$_POST['initialization'] = 'true';
		try {
			$this->_handleAjax( 'easy_language_run_simplification' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// the response must be the dialog for the used up quota.
		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response );
		$this->assertEquals( 'Quota used up', $response[3]['title'] );

		// nothing has been sent to the API and the texts are still open.
		$this->assertEquals( 0, self::$api_requests );
		$this->assertEquals(
			2,
			\easyLanguage\EasyLanguage\Db::get_instance()->count_entries(
				array(
					'object_id'   => $copy->get_id(),
					'object_type' => 'post',
					'state'       => 'to_simplify',
				)
			)
		);
	}

	/**
	 * Count requests to the API and block them.
	 *
	 * @param bool|array<string,mixed> $response The return value.
	 * @param array<string,mixed>      $parsed_args The parsed arguments.
	 * @param string                   $url The used URL.
	 *
	 * @return bool|array<string,mixed>
	 */
	public static function count_requests( bool|array $response, array $parsed_args, string $url ): bool|array {
		if ( str_starts_with( $url, \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_api_url() ) ) {
			++self::$api_requests;
		}
		return $response;
	}
}
