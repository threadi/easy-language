<?php
/**
 * Stability tests for the APIs capito, ChatGPT, WordPress connectors and the API handling.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\E;

use easyLanguage\Apis\Capito\Capito;
use easyLanguage\Plugin\Apis;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Log_Api;
use easyLanguage\Tests\easyLanguageTests;
use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use WP_HTTP_Requests_Response;

/**
 * Object to test the stability fixes in the APIs.
 */
class Apis_Stability extends easyLanguageTests {
	/**
	 * The HTTP status for simulated responses.
	 *
	 * @var int
	 */
	public static int $status = 200;

	/**
	 * The body for simulated responses.
	 *
	 * @var string
	 */
	public static string $body = '';

	/**
	 * Count of sent requests.
	 *
	 * @var int
	 */
	public static int $requests = 0;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		self::$status   = 200;
		self::$body     = '';
		self::$requests = 0;
		add_filter( 'pre_http_request', array( self::class, 'filter_requests' ), 10, 3 );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( self::class, 'filter_requests' ) );
		delete_option( 'easy_language_capito_quota' );
		delete_option( 'easy_language_capito_account_type' );
		delete_option( 'easy_language_capito_team' );
		Transients::get_instance()->get_transient_by_name( 'easy_language_capito_quota' )->delete();
		parent::tear_down();
	}

	/**
	 * Simulate all requests.
	 *
	 * @param mixed  $response    The response.
	 * @param array  $parsed_args The arguments.
	 * @param string $url         The URL.
	 *
	 * @return mixed
	 */
	public static function filter_requests( mixed $response, array $parsed_args, string $url ): mixed {
		++self::$requests;

		// keep a simulated error.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$requests_response              = new \WpOrg\Requests\Response();
		$requests_response->status_code = self::$status;
		return array(
			'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] ),
			'body'          => self::$body,
		);
	}

	/**
	 * Load the functions for settings errors.
	 *
	 * @return void
	 */
	private function prepare_settings_errors(): void {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		global $wp_settings_errors;
		$wp_settings_errors = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Finding 3: a capito quota with no remaining characters is used up and shows the depleted hint.
	 *
	 * @return void
	 */
	public function test_capito_quota_depleted(): void {
		$capito = Capito::get_instance();

		update_option( 'easy_language_capito_quota', array( 'assistance' => array( 'subscription' => array( 'remaining' => 0 ) ) ) );
		$this->assertTrue( Helper::is_quota_used_up( $capito ) );

		update_option( 'easy_language_capito_quota', array( 'assistance' => array( 'subscription' => array( 'remaining' => 5000 ) ) ) );
		$this->assertFalse( Helper::is_quota_used_up( $capito ) );

		// the hint is shown if the API returns no remaining quota.
		self::$body = (string) wp_json_encode( array( 'assistance' => array( 'subscription' => array( 'remaining' => 0 ) ) ) );
		$capito->get_quota_from_api( 'example-token' );
		$transient = Transients::get_instance()->get_transient_by_name( 'easy_language_capito_quota' );
		$this->assertTrue( $transient->is_set() );
		$this->assertStringContainsString( 'completely depleted', $transient->get_message() );
	}

	/**
	 * Finding 5: a temporary error during the validation of a new capito token keeps the old token.
	 *
	 * @return void
	 */
	public function test_capito_validate_keeps_old_token_on_temporary_error(): void {
		$this->prepare_settings_errors();
		self::$status = 500;
		$this->assertSame( 'old-token', Capito::get_instance()->validate_api_key( 'new-token', 'old-token' ) );

		$this->prepare_settings_errors();
		self::$status = 403;
		$this->assertSame( '', Capito::get_instance()->validate_api_key( 'new-token', 'old-token' ) );
	}

	/**
	 * Finding 6: the export uses the same entries as the API log table (also for APIs without own request table).
	 *
	 * @return void
	 */
	public function test_export_uses_api_log(): void {
		Log_Api::get_instance()->add_log( 'wp_ai', 500, 'request-for-export', 'response-for-export' );

		$method = new \ReflectionMethod( Apis::get_instance(), 'get_api_log_entries_for_export' );
		$method->setAccessible( true );
		$entries = $method->invoke( Apis::get_instance(), 'wp_ai' );

		$this->assertContains( 'request-for-export', wp_list_pluck( $entries, 'request' ) );
		$this->assertNotContains( 'request-for-export', wp_list_pluck( $method->invoke( Apis::get_instance(), 'capito' ), 'request' ) );
	}

	/**
	 * Finding 7: only the log entries of the actual blog are returned (capito and ChatGPT).
	 *
	 * @return void
	 */
	public function test_log_entries_of_actual_blog(): void {
		global $wpdb;
		foreach ( array( 'easy_language_capito' => Capito::get_instance(), 'easy_language_chatgpt' => \easyLanguage\Apis\ChatGpt\ChatGpt::get_instance() ) as $table_name => $api_obj ) {
			$table = \easyLanguage\EasyLanguage\Db::get_instance()->get_wpdb_prefix() . $table_name;
			foreach ( array( 'other' => get_current_blog_id() + 999, 'this' => get_current_blog_id() ) as $marker => $blog_id ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$table,
					array(
						'time'     => gmdate( 'Y-m-d H:i:s' ),
						'request'  => 'request-of-' . $marker . '-blog',
						'response' => '',
						'blog_id'  => $blog_id,
					)
				);
			}
			$requests = wp_list_pluck( $api_obj->get_log_entries(), 'request' );
			$this->assertContains( 'request-of-this-blog', $requests );
			$this->assertNotContains( 'request-of-other-blog', $requests );
		}
	}

	/**
	 * Finding 9: disabling capito removes its quota hint.
	 *
	 * @return void
	 */
	public function test_capito_disable_removes_quota_hint(): void {
		$transient = Transients::get_instance()->add();
		$transient->set_name( 'easy_language_capito_quota' );
		$transient->set_message( 'quota hint' );
		$transient->set_type( 'error' );
		$transient->save();
		$this->assertTrue( Transients::get_instance()->get_transient_by_name( 'easy_language_capito_quota' )->is_set() );

		Capito::get_instance()->disable();

		$this->assertFalse( Transients::get_instance()->get_transient_by_name( 'easy_language_capito_quota' )->is_set() );
	}

	/**
	 * Finding 11: a failed capito or ChatGPT request is logged with its error message in the API log.
	 *
	 * @return void
	 */
	public function test_wp_error_is_logged_in_api_log(): void {
		$error = static function () {
			return new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		};
		add_filter( 'pre_http_request', $error, 5 );
		foreach ( array( 'capito' => new \easyLanguage\Apis\Capito\Request(), 'chatgpt' => new \easyLanguage\Apis\ChatGpt\Request() ) as $request ) {
			$request->set_token( 'example-token' );
			$request->set_url( 'https://example.com/api' );
			$request->set_method( 'GET' );
			$request->send();
		}
		remove_filter( 'pre_http_request', $error, 5 );

		global $wpdb;
		foreach ( array( 'capito', 'chatgpt' ) as $api_name ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `api` = %s AND `response` LIKE %s', Log_Api::get_instance()->get_table_name(), $api_name, '%Operation timed out%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->assertGreaterThan( 0, $count, $api_name );
		}
	}

	/**
	 * Finding 12: an empty list of connectors is cached only for a short time.
	 *
	 * @return void
	 */
	public function test_wp_ai_empty_provider_list_short_cache(): void {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'The WordPress connectors require WordPress 7.0 or newer.' );
		}

		\easyLanguage\Tests\Fakes\Ai_Provider::register();
		\easyLanguage\Tests\Fakes\Ai_Image_Provider::register();
		\easyLanguage\Tests\Fakes\Ai_Provider::$configured       = false;
		\easyLanguage\Tests\Fakes\Ai_Image_Provider::$configured = true;
		delete_transient( \easyLanguage\Apis\Wp_Ai\Wp_Ai::TRANSIENT_PROVIDERS );

		$this->assertEmpty( \easyLanguage\Apis\Wp_Ai\Wp_Ai::get_instance()->get_text_providers() );
		$timeout = absint( get_option( '_transient_timeout_' . \easyLanguage\Apis\Wp_Ai\Wp_Ai::TRANSIENT_PROVIDERS ) );
		$this->assertLessThanOrEqual( time() + 5 * MINUTE_IN_SECONDS, $timeout );

		\easyLanguage\Tests\Fakes\Ai_Provider::$configured       = true;
		\easyLanguage\Tests\Fakes\Ai_Image_Provider::$configured = false;
		delete_transient( \easyLanguage\Apis\Wp_Ai\Wp_Ai::TRANSIENT_PROVIDERS );
	}

	/**
	 * Finding 13: a team account without chosen team uses the own account.
	 *
	 * @return void
	 */
	public function test_capito_team_account_without_team(): void {
		update_option( 'easy_language_capito_account_type', 'team' );
		update_option( 'easy_language_capito_team', '' );
		$this->assertSame( EASY_LANGUAGE_CAPITO_API_URL . 'me', Capito::get_instance()->get_api_url() );

		update_option( 'easy_language_capito_team', 'team-1' );
		$this->assertSame( EASY_LANGUAGE_CAPITO_API_URL . 'team-1', Capito::get_instance()->get_api_url() );
	}

	/**
	 * Finding 14: an incomplete or invalid ChatGPT response is not used as simplification.
	 *
	 * @return void
	 */
	public function test_chatgpt_incomplete_response(): void {
		$token = static function () {
			return 'example-token';
		};
		add_filter( 'pre_option_easy_language_chatgpt_api_key', $token );

		$simplifications = \easyLanguage\Apis\ChatGpt\Simplifications::get_instance();
		$simplifications->set_api( \easyLanguage\Apis\ChatGpt\ChatGpt::get_instance() );

		// cut off because of the token limit.
		self::$body = (string) wp_json_encode(
			array(
				'choices' => array(
					array(
						'message'       => array( 'content' => 'Halber Te' ),
						'finish_reason' => 'length',
					),
				),
			)
		);
		$this->assertEmpty( $simplifications->call_api( 'Text', 'de_DE', 'de_a1', false ) );

		// content is not a string.
		self::$body = (string) wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => array( 'a' ) ) ) ) ) );
		$this->assertEmpty( $simplifications->call_api( 'Text', 'de_DE', 'de_a1', false ) );

		// complete response.
		self::$body = (string) wp_json_encode(
			array(
				'choices' => array(
					array(
						'message'       => array( 'content' => 'Einfacher Text.' ),
						'finish_reason' => 'stop',
					),
				),
			)
		);
		$this->assertSame( 'Einfacher Text.', $simplifications->call_api( 'Text', 'de_DE', 'de_a1', false )['translated_text'] );

		remove_filter( 'pre_option_easy_language_chatgpt_api_key', $token );
	}

	/**
	 * Finding 15: requests without token are not sent and do not result in an error.
	 *
	 * @return void
	 */
	public function test_requests_without_token(): void {
		foreach ( array( new \easyLanguage\Apis\Capito\Request(), new \easyLanguage\Apis\ChatGpt\Request() ) as $request ) {
			$request->set_url( 'https://example.com/api' );
			$request->set_method( 'GET' );
			$request->send();
			$this->assertSame( 0, $request->get_http_status() );
		}
		$this->assertSame( 0, self::$requests );
	}
}
