<?php
/**
 * Stability tests for the simplification process in class easyLanguage\EasyLanguage\Objects.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\A1;

use easyLanguage\EasyLanguage\Db;
use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;
use WP_HTTP_Requests_Response;

/**
 * Object to test the simplification process of objects.
 */
class Objects_Simplification extends easyLanguageTests {
	/**
	 * The test paragraph which is used in multiple objects.
	 *
	 * @var string
	 */
	private string $test_text = '<p>Das ist ein kompliziertes Beispiel für Text für Capito.</p>';

	/**
	 * Unique suffix for the texts of a test.
	 *
	 * @var string
	 */
	private string $suffix = '';

	/**
	 * The target language.
	 *
	 * @var string
	 */
	private string $target_language = 'de_b1';

	/**
	 * Whether the mocked API should return an error.
	 *
	 * @var bool
	 */
	private static bool $api_error = false;

	/**
	 * Count of requests to the mocked API.
	 *
	 * @var int
	 */
	private static int $api_requests = 0;

	/**
	 * The active API.
	 *
	 * @var string
	 */
	private static string $active_api = 'capito';

	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// set a pseudo-key.
		update_option( 'easy_language_capito_api_key', 'example-key' );

		// prevent external requests.
		add_filter( 'pre_http_request', array( self::class, 'filter_requests' ), 10, 3 );

		// set language.
		switch_to_locale( 'de_DE' );

		// use unique texts per test, as the DB-object caches texts by their content.
		$this->suffix    = uniqid();
		$this->test_text = '<p>Das ist ein kompliziertes Beispiel für Text für Capito ' . $this->suffix . '.</p>';

		self::$api_error    = false;
		self::$api_requests = 0;
		self::$active_api   = 'capito';

		// use capito with the target language as active API.
		add_filter( 'pre_option_easy_language_api', array( self::class, 'get_active_api' ) );
		add_filter( 'pre_option_easy_language_capito_target_languages', array( self::class, 'get_capito_target_languages' ) );
	}

	/**
	 * Return the active API.
	 *
	 * @return string
	 */
	public static function get_active_api(): string {
		return self::$active_api;
	}

	/**
	 * Return the active target languages for capito.
	 *
	 * @return array<string,string>
	 */
	public static function get_capito_target_languages(): array {
		return array( 'de_b1' => '1' );
	}

	/**
	 * Clean up after the test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		parent::tear_down();

		// reset the simplifications.
		Db::get_instance()->reset_simplifications();

		// remove the filter.
		remove_filter( 'pre_http_request', array( self::class, 'filter_requests' ) );
		remove_filter( 'pre_option_easy_language_api', array( self::class, 'get_active_api' ) );
		remove_filter( 'pre_option_easy_language_capito_target_languages', array( self::class, 'get_capito_target_languages' ) );
	}

	/**
	 * Return the Capito API object.
	 *
	 * @return \easyLanguage\Apis\Capito\Capito
	 */
	private function get_api_obj(): \easyLanguage\Apis\Capito\Capito {
		return \easyLanguage\Apis\Capito\Capito::get_instance();
	}

	/**
	 * Return the language mapping used for the tests.
	 *
	 * @return array<string,array<int,string>>
	 */
	private function get_mapping(): array {
		return array( 'de_DE' => array( $this->target_language ) );
	}

	/**
	 * Create an original post with the given title and the test text and return its simplified copy.
	 *
	 * @param string $title The title.
	 * @param string $content The content.
	 *
	 * @return Post_Object
	 */
	private function create_copy( string $title, string $content = '' ): Post_Object {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => empty( $content ) ? $this->test_text : $content,
				'post_title'   => $title . ' ' . $this->suffix,
				'post_type'    => 'post',
			)
		);
		$object  = \easyLanguage\Plugin\Helper::get_object( $post_id, 'post' );
		$this->assertInstanceOf( Post_Object::class, $object );
		$copy_obj = $object->add_simplification_object( $this->target_language, $this->get_api_obj(), false );
		$this->assertInstanceOf( Post_Object::class, $copy_obj );
		return $copy_obj;
	}

	/**
	 * Return the entries of an object with the given state.
	 *
	 * @param Post_Object $object The object.
	 * @param string      $state The state.
	 *
	 * @return array<\easyLanguage\EasyLanguage\Text>
	 */
	private function get_entries_by_state( Post_Object $object, string $state ): array {
		return Db::get_instance()->get_entries(
			array(
				'object_id'   => $object->get_id(),
				'object_type' => $object->get_type(),
				'state'       => $state,
			)
		);
	}

	/**
	 * A text used by two copies must be replaced in both copies if it is simplified via one of them.
	 *
	 * @return void
	 */
	public function test_shared_text_is_replaced_in_all_copies(): void {
		$copy_a = $this->create_copy( 'Titel A' );
		$copy_b = $this->create_copy( 'Titel B' );

		// simplify copy A.
		$copy_a->process_simplifications( $this->get_api_obj()->get_simplifications_obj(), $this->get_mapping(), 0 );

		// copy A must be simplified.
		$this->assertStringContainsString( 'vereinfachter', get_post_field( 'post_content', $copy_a->get_id() ) );

		// copy B must be simplified too, as the shared text is now marked as in use.
		$this->assertStringNotContainsString( 'kompliziertes', get_post_field( 'post_content', $copy_b->get_id() ) );
		$this->assertStringContainsString( 'vereinfachter', get_post_field( 'post_content', $copy_b->get_id() ) );

		// the title of copy B is not shared and must still be to simplify.
		$this->assertCount( 1, $this->get_entries_by_state( $copy_b, 'to_simplify' ) );
	}

	/**
	 * A text used by a trashed copy must not be replaced in it.
	 *
	 * @return void
	 */
	public function test_shared_text_is_not_replaced_in_trashed_copy(): void {
		$copy_a = $this->create_copy( 'Titel A' );
		$copy_b = $this->create_copy( 'Titel B' );
		wp_trash_post( $copy_b->get_id() );

		// simplify copy A.
		$copy_a->process_simplifications( $this->get_api_obj()->get_simplifications_obj(), $this->get_mapping(), 0 );

		$this->assertStringContainsString( 'kompliziertes', get_post_field( 'post_content', $copy_b->get_id() ) );
	}

	/**
	 * A run without limit on a partly simplified copy must end the process.
	 *
	 * @return void
	 */
	public function test_unlimited_run_on_partly_simplified_copy_ends_process(): void {
		$copy_a = $this->create_copy( 'Titel A' );

		// mark the title as simplified.
		foreach ( $this->get_entries_by_state( $copy_a, 'to_simplify' ) as $entry ) {
			if ( $entry->is_field( 'title' ) ) {
				$entry->set_state( 'in_use' );
			}
		}

		$copy_a->process_simplifications( $this->get_api_obj()->get_simplifications_obj(), $this->get_mapping(), 0 );

		$this->assertEquals( 1, absint( $copy_a->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX ) ) );
		$this->assertEquals( 0, absint( $copy_a->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING ) ) );
		$this->assertEquals( 1, self::$api_requests );
	}

	/**
	 * Texts must not be marked as simplified if no language mapping applies.
	 *
	 * @return void
	 */
	public function test_texts_stay_to_simplify_without_matching_mapping(): void {
		$copy_a = $this->create_copy( 'Titel A' );

		$copy_a->process_simplifications( $this->get_api_obj()->get_simplifications_obj(), array( 'en_US' => array( $this->target_language ) ), 0 );

		$this->assertEquals( 0, self::$api_requests );
		$this->assertCount( 2, $this->get_entries_by_state( $copy_a, 'to_simplify' ) );
		$this->assertEmpty( $this->get_entries_by_state( $copy_a, 'in_use' ) );
		$this->assertNotEmpty( $copy_a->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS ) );
	}

	/**
	 * A batched run must stop on an API error instead of sending the same text again.
	 *
	 * @return void
	 */
	public function test_batched_run_stops_on_api_error(): void {
		$copy_a = $this->create_copy( 'Titel A' );

		self::$api_error = true;

		// run the first batch.
		$copy_a->process_simplifications( $this->get_api_obj()->get_simplifications_obj(), $this->get_mapping(), 1 );

		// the process must be ended with an error dialog.
		$this->assertEquals( 0, absint( $copy_a->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING ) ) );
		$results = $copy_a->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS );
		$this->assertIsArray( $results );
		$this->assertEquals( 'wp-dialog-error', $results['className'] );

		// the texts are still to simplify.
		$this->assertCount( 2, $this->get_entries_by_state( $copy_a, 'to_simplify' ) );
	}

	/**
	 * The max-requests-limit of an API must only count the texts which are still to simplify.
	 *
	 * @return void
	 */
	public function test_max_requests_limit_counts_only_texts_to_simplify(): void {
		// create a copy with 16 paragraphs + title.
		$content = '';
		for ( $i = 1; $i <= 16; $i++ ) {
			$content .= '<!-- wp:paragraph --><p>Das ist der Absatz Nummer ' . $i . ' als Beispiel ' . $this->suffix . '.</p><!-- /wp:paragraph -->';
		}
		$copy_a = $this->create_copy( 'Titel A', $content );

		// mark all texts as simplified.
		$entries = $this->get_entries_by_state( $copy_a, 'to_simplify' );
		$this->assertGreaterThan( 15, count( $entries ) );
		foreach ( $entries as $entry ) {
			$entry->set_state( 'in_use' );
		}

		// use SUMM AI (15 requests per minute) as active API.
		self::$active_api = 'summ_ai';

		$copy_a->process_simplifications( $this->get_api_obj()->get_simplifications_obj(), $this->get_mapping(), 0 );

		// the limit-dialog must not be shown.
		$results = $copy_a->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS );
		$this->assertIsArray( $results );
		$this->assertStringNotContainsString( 'could handle in a short time', implode( ' ', $results['texts'] ) );
	}

	/**
	 * A text must not be left in "processing" if the API returned a simplification which could not be replaced.
	 *
	 * @return void
	 */
	public function test_text_not_left_processing_if_not_replaced(): void {
		$copy_a = $this->create_copy( 'Titel A' );
		$copy_b = $this->create_copy( 'Titel B', '<p>Ein ganz anderer Text ' . $this->suffix . '.</p>' );

		// get the title-entry of copy A.
		$entry = null;
		foreach ( $this->get_entries_by_state( $copy_a, 'to_simplify' ) as $item ) {
			if ( $item->is_field( 'title' ) ) {
				$entry = $item;
			}
		}
		$this->assertNotNull( $entry );

		// process it on copy B, which does not use this text, so nothing could be replaced.
		$copy_b->process_simplification( $this->get_api_obj()->get_simplifications_obj(), $this->get_mapping(), $entry );

		$this->assertEquals( 1, self::$api_requests );
		$this->assertEquals( 'to_simplify', $entry->get_state() );
	}

	/**
	 * A text used by a copy where the automatic mode is prevented must not be replaced in it.
	 *
	 * @return void
	 */
	public function test_shared_text_is_not_replaced_in_prevented_copy(): void {
		$copy_a = $this->create_copy( 'Titel A' );
		$copy_b = $this->create_copy( 'Titel B' );
		update_post_meta( $copy_b->get_id(), 'easy_language_prevent_automatic_mode', 1 );

		// simplify copy A.
		$copy_a->process_simplifications( $this->get_api_obj()->get_simplifications_obj(), $this->get_mapping(), 0 );

		$this->assertStringContainsString( 'vereinfachter', get_post_field( 'post_content', $copy_a->get_id() ) );
		$this->assertStringContainsString( 'kompliziertes', get_post_field( 'post_content', $copy_b->get_id() ) );
	}

	/**
	 * Texts which could not be simplified do not block the automatic simplification of the following texts.
	 *
	 * @return void
	 */
	public function test_automatic_simplification_skips_texts_which_could_not_be_simplified(): void {
		$this->create_copy( 'Titel A' );

		update_option( 'easy_language_automatic_simplification_enabled', 1 );
		update_option( 'easy_language_automatic_item_count', 1 );
		delete_option( 'easy_language_automatic_simplification_skipped' );

		// let the API fail for every text.
		self::$api_error = true;

		// first run: the first text fails and is skipped.
		\easyLanguage\EasyLanguage\Init::get_instance()->run_automatic_simplification();
		$this->assertCount( 1, (array) get_option( 'easy_language_automatic_simplification_skipped', array() ) );

		// second run: the next text is used, not the failed one again.
		\easyLanguage\EasyLanguage\Init::get_instance()->run_automatic_simplification();
		$this->assertCount( 2, (array) get_option( 'easy_language_automatic_simplification_skipped', array() ) );

		delete_option( 'easy_language_automatic_simplification_skipped' );
		delete_option( 'easy_language_automatic_simplification_enabled' );
	}

	/**
	 * Filter any outgoing requests to simulate the responses.
	 *
	 * @param bool|array $false The return value.
	 * @param array      $parsed_args The parsed arguments.
	 * @param string     $url The used URL.
	 *
	 * @return bool|array
	 */
	public static function filter_requests( bool|array $false, array $parsed_args, string $url ): bool|array {
		if ( str_starts_with( $url, \easyLanguage\Apis\Capito\Capito::get_instance()->get_api_url() ) ) {
			++self::$api_requests;

			$requests_response              = new \WpOrg\Requests\Response();
			$requests_response->status_code = self::$api_error ? 500 : 200;

			return array(
				'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] ),
				'body'          => self::$api_error ? '{"error":"failed"}' : wp_json_encode( array( 'content' => 'Das ist ein vereinfachter Beispiel Text.' ) ),
				'response'      => array( 'code' => self::$api_error ? 500 : 200 ),
			);
		}

		return $false;
	}
}
