<?php
/**
 * Stability tests for the SUMM AI API.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\E;

use easyLanguage\Apis\Summ_Ai\Simplifications;
use easyLanguage\Apis\Summ_Ai\Summ_Ai;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Installer;
use easyLanguage\Tests\easyLanguageTests;
use WP_HTTP_Requests_Response;

/**
 * Object to test the stability fixes in the SUMM AI API.
 */
class Summ_Ai_Stability extends easyLanguageTests {
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
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		self::$status = 200;
		self::$body   = '';
		add_filter( 'pre_http_request', array( self::class, 'filter_requests' ), 10, 3 );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( self::class, 'filter_requests' ) );
		remove_filter( 'pre_option_easy_language_summ_ai_mode', array( self::class, 'get_paid_mode' ) );
		remove_filter( 'pre_option_easy_language_summ_ai_api_key', array( self::class, 'get_example_key' ) );
		delete_option( 'easy_language_summ_ai_paid_quota' );
		delete_option( 'easy_language_summ_ai_quota' );
		delete_option( 'easy_language_summ_ai_mode' );
		delete_option( 'easy_language_summ_ai_disable_free_requests' );
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
	 * Return the paid mode.
	 *
	 * @return string
	 */
	public static function get_paid_mode(): string {
		return 'paid';
	}

	/**
	 * Return an example API key.
	 *
	 * @return string
	 */
	public static function get_example_key(): string {
		return 'example-key';
	}

	/**
	 * Use the paid mode with an example key.
	 *
	 * @return void
	 */
	private function use_paid_mode(): void {
		add_filter( 'pre_option_easy_language_summ_ai_mode', array( self::class, 'get_paid_mode' ) );
		add_filter( 'pre_option_easy_language_summ_ai_api_key', array( self::class, 'get_example_key' ) );
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
	 * Finding 1: a (re)activation must not overwrite the chosen API.
	 *
	 * @return void
	 */
	public function test_activation_keeps_chosen_api(): void {
		$previous = get_option( 'easy_language_api' );
		update_option( 'easy_language_api', 'capito' );

		Installer::get_instance()->activation();

		$this->assertSame( 'capito', get_option( 'easy_language_api' ) );

		update_option( 'easy_language_api', $previous );
	}

	/**
	 * Finding 2: a paid quota without limit is unlimited and does not block requests.
	 *
	 * @return void
	 */
	public function test_paid_quota_without_limit_is_unlimited(): void {
		self::$body = (string) wp_json_encode( array( 'character_spent' => 2000000 ) );

		$summ_ai = Summ_Ai::get_instance();
		$quota   = $summ_ai->request_quota( 'example-key' );
		$this->assertTrue( $quota['unlimited'] );

		// saving it must not result in a division by zero.
		$summ_ai->get_quota_from_api( 'example-key' );

		$this->use_paid_mode();
		$this->assertNotEmpty( $summ_ai->get_quota()['unlimited'] );
		$this->assertFalse( Helper::is_quota_used_up( $summ_ai ) );
	}

	/**
	 * Finding 4: the free quota is only counted in free mode and not for tests.
	 *
	 * @return void
	 */
	public function test_free_quota_counter(): void {
		self::$body = (string) wp_json_encode(
			array(
				'translated_text' => 'Einfacher Text.',
				'jobid'           => 1,
			)
		);
		$text       = 'Das ist ein Beispiel Text.';

		$simplifications = Simplifications::get_instance();
		$simplifications->set_api( Summ_Ai::get_instance() );

		// free mode: counted.
		update_option( 'easy_language_summ_ai_quota', 0 );
		$this->assertNotEmpty( $simplifications->call_api( $text, 'de_DE', 'de_LS', false ) );
		$this->assertSame( strlen( $text ), absint( get_option( 'easy_language_summ_ai_quota' ) ) );

		// free mode, but test request: not counted.
		update_option( 'easy_language_summ_ai_quota', 0 );
		$simplifications->call_api( $text, 'de_DE', 'de_LS', false, true );
		$this->assertSame( 0, absint( get_option( 'easy_language_summ_ai_quota' ) ) );

		// paid mode: not counted.
		$this->use_paid_mode();
		$this->assertNotEmpty( $simplifications->call_api( $text, 'de_DE', 'de_LS', false ) );
		$this->assertSame( 0, absint( get_option( 'easy_language_summ_ai_quota' ) ) );
	}

	/**
	 * Finding 5: a temporary error during the validation of a new key keeps the old key, a rejected key is removed.
	 *
	 * @return void
	 */
	public function test_validate_api_key_keeps_old_key_on_temporary_error(): void {
		$this->prepare_settings_errors();
		self::$status = 503;
		$this->assertSame( 'old-key', Summ_Ai::get_instance()->validate_api_key( 'new-key', 'old-key' ) );

		$this->prepare_settings_errors();
		self::$status = 401;
		$this->assertSame( '', Summ_Ai::get_instance()->validate_api_key( 'new-key', 'old-key' ) );
	}

	/**
	 * Finding 7: only the log entries of the actual blog are returned.
	 *
	 * @return void
	 */
	public function test_log_entries_of_actual_blog(): void {
		global $wpdb;
		$table = \easyLanguage\EasyLanguage\Db::get_instance()->get_wpdb_prefix() . 'easy_language_summ_ai';
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'time'     => gmdate( 'Y-m-d H:i:s' ),
				'request'  => 'request-of-other-blog',
				'response' => '',
				'blog_id'  => get_current_blog_id() + 999,
			)
		);

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'time'     => gmdate( 'Y-m-d H:i:s' ),
				'request'  => 'request-of-this-blog',
				'response' => '',
				'blog_id'  => get_current_blog_id(),
			)
		);

		$requests = wp_list_pluck( Summ_Ai::get_instance()->get_log_entries(), 'request' );
		$this->assertContains( 'request-of-this-blog', $requests );
		$this->assertNotContains( 'request-of-other-blog', $requests );
	}

	/**
	 * Finding 8: the uninstallation removes the own options, but keeps the free quota counter.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_options(): void {
		update_option( 'easy_language_summ_ai_paid_quota', array( 'character_spent' => 1 ) );
		update_option( 'easy_language_summ_ai_quota', 42 );

		// prevent dropping the table (would commit the transaction of this test).
		$prevent_drop = static function ( $query ) {
			return str_starts_with( $query, 'DROP TABLE' ) ? 'SELECT 1' : $query;
		};
		add_filter( 'query', $prevent_drop );
		Summ_Ai::get_instance()->uninstall();
		remove_filter( 'query', $prevent_drop );

		$this->assertFalse( get_option( 'easy_language_summ_ai_paid_quota' ) );
		$this->assertSame( 42, absint( get_option( 'easy_language_summ_ai_quota' ) ) );
	}

	/**
	 * Finding 10: in free mode the API is not configured if free requests are disabled.
	 *
	 * @return void
	 */
	public function test_not_configured_if_free_requests_disabled(): void {
		$summ_ai = Summ_Ai::get_instance();
		$this->assertTrue( $summ_ai->is_configured() );

		$summ_ai->disable_free_requests();
		$this->assertFalse( $summ_ai->is_configured() );

		// with own key it is configured.
		$this->use_paid_mode();
		$this->assertTrue( $summ_ai->is_configured() );
	}

	/**
	 * Finding 11: a failed request is logged with its error message in the API log.
	 *
	 * @return void
	 */
	public function test_wp_error_is_logged_in_api_log(): void {
		$error = static function () {
			return new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		};
		add_filter( 'pre_http_request', $error, 5 );
		$request = new \easyLanguage\Apis\Summ_Ai\Request();
		$request->set_token( 'example-key' );
		$request->set_url( EASY_LANGUAGE_SUMM_AI_API_URL_QUOTA );
		$request->set_method( 'GET' );
		$request->send();
		remove_filter( 'pre_http_request', $error, 5 );

		global $wpdb;
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `api` = %s AND `response` LIKE %s', \easyLanguage\Plugin\Log_Api::get_instance()->get_table_name(), 'summ_ai', '%Operation timed out%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertGreaterThan( 0, $count );
	}

	/**
	 * Finding 17: the request table could save long requests and responses.
	 *
	 * @return void
	 */
	public function test_request_table_uses_longtext(): void {
		global $wpdb;
		$table   = \easyLanguage\EasyLanguage\Db::get_instance()->get_wpdb_prefix() . 'easy_language_summ_ai';
		$columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$types   = array_column( $columns, 'Type', 'Field' );
		$this->assertSame( 'longtext', strtolower( $types['request'] ) );
		$this->assertSame( 'longtext', strtolower( $types['response'] ) );
	}
}
