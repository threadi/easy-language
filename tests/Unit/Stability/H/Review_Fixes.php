<?php
/**
 * Tests for the fixes of the security and stability review (API responses, request tables, locks, exports).
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\H;

use easyLanguage\Apis\Capito\Capito;
use easyLanguage\Apis\ChatGpt\ChatGpt;
use easyLanguage\Apis\Summ_Ai\Summ_Ai;
use easyLanguage\Plugin\Api_Request_Table;
use easyLanguage\Plugin\Apis;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Lock;
use easyLanguage\Plugin\Uninstall;
use easyLanguage\Tests\easyLanguageTests;
use WP_HTTP_Requests_Response;

/**
 * Object to test the fixes of the review.
 */
class Review_Fixes extends easyLanguageTests {
	/**
	 * The body for simulated responses.
	 *
	 * @var string
	 */
	public static string $body = '';

	/**
	 * The arguments of the last request.
	 *
	 * @var array<string,mixed>
	 */
	public static array $last_args = array();

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		self::$body      = '';
		self::$last_args = array();
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
		unset( $_SERVER['HTTP_REFERER'], $_REQUEST['_wp_http_referer'] );
		Lock::release( 'easy_language_test_lock' );
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
	 * Simulate all requests and remember their arguments.
	 *
	 * @param mixed               $response    The response.
	 * @param array<string,mixed> $parsed_args The arguments.
	 * @param string              $url         The URL.
	 *
	 * @return mixed
	 */
	public static function filter_requests( mixed $response, array $parsed_args, string $url ): mixed {
		self::$last_args = $parsed_args;

		$requests_response              = new \WpOrg\Requests\Response();
		$requests_response->status_code = 200;
		return array(
			'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] ),
			'body'          => self::$body,
		);
	}

	/**
	 * Return the response body of the given API for the given simplified text.
	 *
	 * @param string $api The name of the API.
	 * @param string $text The simplified text.
	 *
	 * @return string
	 */
	private function get_response_body( string $api, string $text ): string {
		return (string) match ( $api ) {
			'chatgpt' => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => $text ) ) ) ) ),
			'capito'  => wp_json_encode( array( 'content' => $text ) ),
			default   => wp_json_encode(
				array(
					'translated_text' => $text,
					'jobid'           => 1,
				)
			),
		};
	}

	/**
	 * Call the API with the given name and return its result.
	 *
	 * @param string $api The name of the API.
	 * @param bool   $is_html Whether the text contains HTML.
	 *
	 * @return array<string,mixed>
	 */
	private function call_api( string $api, bool $is_html ): array {
		return match ( $api ) {
			'chatgpt' => \easyLanguage\Apis\ChatGpt\Simplifications::get_instance()->call_api( 'Ein Text.', 'de_DE', 'de_a1', $is_html ),
			'capito'  => \easyLanguage\Apis\Capito\Simplifications::get_instance()->call_api( 'Ein Text.', 'de_DE', 'de_a1', $is_html ),
			default   => \easyLanguage\Apis\Summ_Ai\Simplifications::get_instance()->call_api( 'Ein Text.', 'de_DE', 'de_LS', $is_html ),
		};
	}

	/**
	 * Return the APIs with their own request table.
	 *
	 * @return array<string,array<int,mixed>>
	 */
	public static function get_apis(): array {
		return array(
			'chatgpt' => array( 'chatgpt' ),
			'capito'  => array( 'capito' ),
			'summ_ai' => array( 'summ_ai' ),
		);
	}

	/**
	 * Prepare the simplification object of the given API.
	 *
	 * @param string $api The name of the API.
	 *
	 * @return void
	 */
	private function prepare_api( string $api ): void {
		match ( $api ) {
			'chatgpt' => \easyLanguage\Apis\ChatGpt\Simplifications::get_instance()->set_api( ChatGpt::get_instance() ),
			'capito'  => \easyLanguage\Apis\Capito\Simplifications::get_instance()->set_api( Capito::get_instance() ),
			default   => \easyLanguage\Apis\Summ_Ai\Simplifications::get_instance()->set_api( Summ_Ai::get_instance() ),
		};
	}

	/**
	 * A text without HTML must not get HTML from the API (e.g. via prompt injection), as it could be saved
	 * in fields of page builders, which are not filtered by WordPress.
	 *
	 * @dataProvider get_apis
	 *
	 * @param string $api The name of the API.
	 *
	 * @return void
	 */
	public function test_text_without_html_gets_no_html( string $api ): void {
		$this->prepare_api( $api );
		self::$body = $this->get_response_body( $api, 'Ein <script>alert(1)</script><b>leichter</b> Text.' );

		$result = $this->call_api( $api, false );

		$this->assertArrayHasKey( 'translated_text', $result );
		$this->assertStringNotContainsString( '<', (string) $result['translated_text'] );
		$this->assertStringContainsString( 'leichter', (string) $result['translated_text'] );
	}

	/**
	 * A text with HTML keeps the HTML allowed in posts, but not scripts.
	 *
	 * @dataProvider get_apis
	 *
	 * @param string $api The name of the API.
	 *
	 * @return void
	 */
	public function test_text_with_html_is_filtered( string $api ): void {
		$this->prepare_api( $api );
		self::$body = $this->get_response_body( $api, '<p>Ein <strong>leichter</strong> Text.<script>alert(1)</script></p>' );

		$result = $this->call_api( $api, true );

		$this->assertArrayHasKey( 'translated_text', $result );
		$this->assertStringContainsString( '<strong>leichter</strong>', (string) $result['translated_text'] );
		$this->assertStringNotContainsString( '<script', (string) $result['translated_text'] );
	}

	/**
	 * Requests with an API key do not follow redirects and reject unsafe URLs.
	 *
	 * @dataProvider get_apis
	 *
	 * @param string $api The name of the API.
	 *
	 * @return void
	 */
	public function test_requests_do_not_follow_redirects( string $api ): void {
		$this->prepare_api( $api );
		self::$body = $this->get_response_body( $api, 'Ein Text.' );

		$this->call_api( $api, false );

		$this->assertNotEmpty( self::$last_args );
		$this->assertSame( 0, self::$last_args['redirection'] );
		$this->assertTrue( self::$last_args['reject_unsafe_urls'] );
	}

	/**
	 * The request tables are created with a duration in milliseconds.
	 *
	 * @return void
	 */
	public function test_request_tables_save_duration_with_milliseconds(): void {
		global $wpdb;

		$this->assertStringContainsString( '`duration` decimal(10,3)', Api_Request_Table::get_create_sql( 'example_table' ) );

		foreach ( array( Summ_Ai::get_instance(), Capito::get_instance(), ChatGpt::get_instance() ) as $api_obj ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$column = $wpdb->get_row( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $api_obj->get_request_table(), 'duration' ), ARRAY_A );
			$this->assertIsArray( $column, $api_obj->get_name() );
			$this->assertStringStartsWith( 'decimal(10,3)', strtolower( (string) $column['Type'] ), $api_obj->get_name() );
		}
	}

	/**
	 * Insert an entry in the given request table.
	 *
	 * @param string $table The name of the table.
	 * @param string $request The request as marker.
	 * @param int    $age_in_days The age of the entry.
	 * @param int    $blog_id The ID of the blog.
	 *
	 * @return void
	 */
	private function insert_request( string $table, string $request, int $age_in_days, int $blog_id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'time'     => gmdate( 'Y-m-d H:i:s', time() - $age_in_days * DAY_IN_SECONDS ),
				'request'  => $request,
				'response' => '',
				'blog_id'  => $blog_id,
			)
		);
	}

	/**
	 * Return the requests saved in the given table.
	 *
	 * @param string $table The name of the table.
	 *
	 * @return array<int,string>
	 */
	private function get_requests( string $table ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT `request` FROM %i', $table ) ) );
	}

	/**
	 * The cleanup removes only old entries of the actual site.
	 *
	 * @return void
	 */
	public function test_request_table_cleanup(): void {
		$table = ChatGpt::get_instance()->get_request_table();
		$this->insert_request( $table, 'old-of-this-site', 100, get_current_blog_id() );
		$this->insert_request( $table, 'new-of-this-site', 1, get_current_blog_id() );
		$this->insert_request( $table, 'old-of-other-site', 100, get_current_blog_id() + 999 );

		Api_Request_Table::cleanup( $table );

		$requests = $this->get_requests( $table );
		$this->assertNotContains( 'old-of-this-site', $requests );
		$this->assertContains( 'new-of-this-site', $requests );
		$this->assertContains( 'old-of-other-site', $requests );

		// an empty table name does nothing.
		Api_Request_Table::cleanup( '' );
	}

	/**
	 * The max age uses the setting of the logs, could be filtered and is at least one day.
	 *
	 * @return void
	 */
	public function test_request_table_max_age(): void {
		update_option( 'easy_language_log_max_age', 7 );
		$this->assertSame( 7, Api_Request_Table::get_max_age() );

		update_option( 'easy_language_log_max_age', 0 );
		$this->assertSame( 1, Api_Request_Table::get_max_age() );

		$filter = static function () {
			return 3;
		};
		add_filter( 'easy_language_api_requests_max_age', $filter );
		$this->assertSame( 3, Api_Request_Table::get_max_age() );
		remove_filter( 'easy_language_api_requests_max_age', $filter );

		delete_option( 'easy_language_log_max_age' );
		$this->assertSame( 50, Api_Request_Table::get_max_age() );
	}

	/**
	 * Each request removes old entries of its request table.
	 *
	 * @dataProvider get_apis
	 *
	 * @param string $api The name of the API.
	 *
	 * @return void
	 */
	public function test_request_cleans_up_its_table( string $api ): void {
		$api_obj = match ( $api ) {
			'chatgpt' => ChatGpt::get_instance(),
			'capito'  => Capito::get_instance(),
			default   => Summ_Ai::get_instance(),
		};
		$this->insert_request( $api_obj->get_request_table(), 'very-old-request', 400, get_current_blog_id() );

		$this->prepare_api( $api );
		self::$body = $this->get_response_body( $api, 'Ein Text.' );
		$this->call_api( $api, false );

		$requests = $this->get_requests( $api_obj->get_request_table() );
		$this->assertNotContains( 'very-old-request', $requests );
		$this->assertNotEmpty( $requests );
	}

	/**
	 * The lock could only be set once, until it is released or orphaned.
	 *
	 * @return void
	 */
	public function test_lock(): void {
		$this->assertTrue( Lock::acquire( 'easy_language_test_lock', 60 ) );
		$this->assertFalse( Lock::acquire( 'easy_language_test_lock', 60 ) );

		Lock::release( 'easy_language_test_lock' );
		$this->assertTrue( Lock::acquire( 'easy_language_test_lock', 60 ) );

		// an orphaned lock is replaced.
		update_option( 'easy_language_test_lock', (string) ( time() - 120 ), false );
		$this->assertTrue( Lock::acquire( 'easy_language_test_lock', 60 ) );
	}

	/**
	 * Return values for the CSV export and the expected safe values.
	 *
	 * @return array<string,array<int,mixed>>
	 */
	public static function get_csv_values(): array {
		return array(
			'empty'                  => array( '', '' ),
			'text'                   => array( 'Hallo Welt', 'Hallo Welt' ),
			'number'                 => array( 42, '42' ),
			'negative in text'       => array( 'a-b', 'a-b' ),
			'formula'                => array( '=SUM(A1:A2)', "'=SUM(A1:A2)" ),
			'plus'                   => array( '+1', "'+1" ),
			'minus'                  => array( '-1', "'-1" ),
			'at'                     => array( '@SUM(1)', "'@SUM(1)" ),
			'tab'                    => array( "\tX", "'\tX" ),
			'carriage return'        => array( "\rX", "'\rX" ),
			'line break'             => array( "\nX", "'\nX" ),
			'space before formula'   => array( '  =1+1', "'  =1+1" ),
			'line break and formula' => array( "\n=1+1", "'\n=1+1" ),
			'only spaces'            => array( '   ', '   ' ),
			'array'                  => array( array( '=1' ), '' ),
			'null'                   => array( null, '' ),
		);
	}

	/**
	 * Values for the CSV export are protected against formula injection, also with leading whitespace.
	 *
	 * @dataProvider get_csv_values
	 *
	 * @param mixed  $value The value.
	 * @param string $expected The expected safe value.
	 *
	 * @return void
	 */
	public function test_csv_safe_value( mixed $value, string $expected ): void {
		$this->assertSame( $expected, Apis::get_instance()->get_csv_safe_value( $value ) );
	}

	/**
	 * Only URLs of the own website are accepted as referer.
	 *
	 * @return void
	 */
	public function test_referer_only_own_urls(): void {
		$_SERVER['HTTP_REFERER'] = admin_url( 'edit.php?post_type=page' );
		$this->assertSame( admin_url( 'edit.php?post_type=page' ), Helper::get_referer_url() );

		// a foreign host allowed via filter is not used.
		$allow = static function ( array $hosts ): array {
			$hosts[] = 'foreign-host.test';
			return $hosts;
		};
		add_filter( 'allowed_redirect_hosts', $allow );
		$_SERVER['HTTP_REFERER'] = 'https://foreign-host.test/wp-admin/';
		$this->assertSame( Helper::get_settings_page_url(), Helper::get_referer_url() );
		remove_filter( 'allowed_redirect_hosts', $allow );
	}

	/**
	 * Check the detection of own URLs.
	 *
	 * @return void
	 */
	public function test_is_own_url(): void {
		$this->assertTrue( Helper::is_own_url( home_url( '/seite/' ) ) );
		$this->assertTrue( Helper::is_own_url( admin_url( 'options-general.php' ) ) );
		$this->assertTrue( Helper::is_own_url( '/wp-admin/edit.php' ) );
		$this->assertTrue( Helper::is_own_url( strtoupper( (string) home_url() ) ) );
		$this->assertFalse( Helper::is_own_url( 'https://foreign-host.test/' ) );
		$this->assertFalse( Helper::is_own_url( '//foreign-host.test/' ) );
		$this->assertFalse( Helper::is_own_url( 'javascript:alert(1)' ) );
		$this->assertFalse( Helper::is_own_url( 'http:///foreign-host.test' ) );
	}

	/**
	 * The API key of SUMM AI is not saved in the settings of TranslatePress.
	 *
	 * @return void
	 */
	public function test_translatepress_settings_without_api_key(): void {
		$trp = \easyLanguage\ThirdPartySupport\TranslatePress\Init::get_instance();

		$_POST['trp_machine_translation_settings']['summ-ai-key'] = 'secret';
		$settings = $trp->sanitize_settings(
			array(
				'translation-engine' => 'summ_ai',
				'summ-ai-key'        => 'secret',
			)
		);
		unset( $_POST['trp_machine_translation_settings'] );

		$this->assertSame( array( 'translation-engine' => 'summ_ai' ), $settings );
	}

	/**
	 * An unencrypted API key in the settings of TranslatePress is removed (also during the update to 4.0.0).
	 *
	 * @return void
	 */
	public function test_translatepress_legacy_api_key_is_removed(): void {
		update_option(
			'trp_machine_translation_settings',
			array(
				'translation-engine' => 'summ_ai',
				'summ-ai-key'        => 'secret',
			)
		);

		\easyLanguage\ThirdPartySupport\TranslatePress\Init::get_instance()->remove_legacy_api_key();
		$this->assertSame( array( 'translation-engine' => 'summ_ai' ), get_option( 'trp_machine_translation_settings' ) );

		// without settings nothing happens.
		delete_option( 'trp_machine_translation_settings' );
		\easyLanguage\ThirdPartySupport\TranslatePress\Init::get_instance()->remove_legacy_api_key();
		$this->assertFalse( get_option( 'trp_machine_translation_settings' ) );
	}

	/**
	 * The lock of the automatic simplification is removed during uninstallation.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_lock_option(): void {
		$method = new \ReflectionMethod( Uninstall::get_instance(), 'get_options' );
		$method->setAccessible( true );
		$this->assertContains( \easyLanguage\EasyLanguage\Init::AUTOMATIC_SIMPLIFICATION_LOCK, $method->invoke( Uninstall::get_instance() ) );
	}
}
