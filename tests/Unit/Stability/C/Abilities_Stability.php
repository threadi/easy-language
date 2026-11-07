<?php
/**
 * Stability tests for class easyLanguage\EasyLanguage\Abilities.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\C;

use easyLanguage\EasyLanguage\Abilities;
use easyLanguage\EasyLanguage\Db;
use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;
use WP_Error;
use WP_HTTP_Requests_Response;

/**
 * Object to test the stability fixes in the abilities.
 */
class Abilities_Stability extends easyLanguageTests {
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
		add_filter( 'pre_http_request', array( self::class, 'filter_requests' ), 10, 3 );

		switch_to_locale( 'de_DE' );

		// use an administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// enable all abilities.
		update_option( Abilities::OPTION_ENABLED, 1 );
		update_option( Abilities::OPTION_MODE, 'all' );
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
		remove_filter( 'pre_http_request', array( self::class, 'filter_requests' ) );
		remove_all_filters( 'pre_option_easy_language_api' );
		remove_filter( 'option_easy_language_languages', array( $this, 'remove_target_language' ) );
		remove_all_filters( 'pre_schedule_event' );
		update_option( 'easy_language_summ_ai_quota', 0 );
		wp_unschedule_hook( Abilities::CRON_HOOK );

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
		Abilities::get_instance()->register_abilities();
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
		return absint( $result['simplification']['object_id'] );
	}

	/**
	 * Return the texts counts of the simplification.
	 *
	 * @return array<string,int>
	 */
	private function get_texts_counts(): array {
		$result = $this->run_ability( 'get-object-simplifications', array( 'object_id' => $this->post_id ) );
		return $result['simplifications'][0]['texts'];
	}

	/**
	 * Return "no_api" as active API.
	 *
	 * @return string
	 */
	public function use_no_api(): string {
		return 'no_api';
	}

	/**
	 * Remove the target language from the active languages.
	 *
	 * @param mixed $languages The languages.
	 *
	 * @return mixed
	 */
	public function remove_target_language( mixed $languages ): mixed {
		if ( is_array( $languages ) ) {
			unset( $languages[ $this->target_language ] );
		}
		return $languages;
	}

	/**
	 * Finding 1: a scheduled simplification must not run if the API has been switched to "No API" in the meantime.
	 *
	 * @return void
	 */
	public function test_scheduled_simplification_is_canceled_without_api(): void {
		$copy_id = $this->create_simplification();
		$result  = $this->run_ability( 'run-simplification', array( 'object_id' => $copy_id ) );
		$this->assertEquals( 'scheduled', $result['status'] );

		// switch to "No API" before the cron runs.
		add_filter( 'pre_option_easy_language_api', array( $this, 'use_no_api' ) );
		Abilities::get_instance()->run_scheduled_simplification( $copy_id, 'post' );
		remove_filter( 'pre_option_easy_language_api', array( $this, 'use_no_api' ) );

		// nothing has been changed.
		$counts = $this->get_texts_counts();
		$this->assertEquals( 0, self::$api_requests );
		$this->assertEquals( 2, $counts['to_simplify'] );
		$this->assertEquals( 0, $counts['in_use'] );
	}

	/**
	 * Finding 2: nothing is scheduled if the object could not be simplified with the API because of its quota state.
	 *
	 * @return void
	 */
	public function test_run_simplification_refuses_bad_quota_state(): void {
		$copy_id = $this->create_simplification();

		// only a few characters are left in the quota.
		update_option( 'easy_language_summ_ai_quota', EASY_LANGUAGE_SUMM_AI_QUOTA - 5 );

		$result = $this->run_ability( 'run-simplification', array( 'object_id' => $copy_id ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'easy_language_quota_state', $result->get_error_code() );
		$this->assertEquals( 'above_limit', $result->get_error_data()['quota_state']['status'] );
		$this->assertFalse( wp_next_scheduled( Abilities::CRON_HOOK, array( $copy_id, 'post' ) ) );
	}

	/**
	 * Finding 3: the requesting user is not saved if the scheduling failed.
	 *
	 * @return void
	 */
	public function test_user_option_is_removed_if_scheduling_fails(): void {
		$copy_id = $this->create_simplification();

		add_filter(
			'pre_schedule_event',
			function () {
				return new WP_Error( 'test_failed', 'failed' );
			}
		);
		$result = $this->run_ability( 'run-simplification', array( 'object_id' => $copy_id ) );
		$this->assertInstanceOf( WP_Error::class, $result );

		$object = new Post_Object( $copy_id );
		$this->assertFalse( get_option( $object->get_marker_option_name( 'easy_language_ability_user' ) ) );
	}

	/**
	 * Finding 4: the end trigger runs if the last texts have been simplified while others were simplified before.
	 *
	 * @return void
	 */
	public function test_end_trigger_runs_if_some_texts_were_simplified_before(): void {
		$copy_id = $this->create_simplification();

		// simplify one text manually.
		$texts = $this->run_ability(
			'list-texts',
			array(
				'object_id' => $copy_id,
				'state'     => 'to_simplify',
			)
		);
		$this->assertEquals( 2, $texts['total'] );
		$result = $this->run_ability(
			'save-text-simplification',
			array(
				'object_id'      => $copy_id,
				'text_id'        => $texts['items'][0]['text_id'],
				'simplification' => 'Ein einfacher Text.',
			)
		);
		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['texts_to_simplify'] );

		// schedule the rest.
		$result = $this->run_ability( 'run-simplification', array( 'object_id' => $copy_id ) );
		$this->assertEquals( 'scheduled', $result['status'] );

		// count the end triggers (save_post after the process has been finished).
		$object   = new Post_Object( $copy_id );
		$triggers = 0;
		$callback = function () use ( $object, &$triggers ) {
			if ( 0 === absint( $object->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING ) ) ) {
				++$triggers;
			}
		};
		add_action( 'save_post_post', $callback );
		Abilities::get_instance()->run_scheduled_simplification( $copy_id, 'post' );
		remove_action( 'save_post_post', $callback );

		$this->assertEquals( 0, $this->get_texts_counts()['to_simplify'] );
		$this->assertGreaterThan( 0, $triggers );
	}

	/**
	 * Finding 5: the totals of find-objects match the posts the user could edit.
	 *
	 * @return void
	 */
	public function test_find_objects_totals_match_editable_posts(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		get_user_by( 'id', $author )->add_cap( 'edit_el_simplifier' );

		// some posts of others and one own post.
		self::factory()->post->create_many( 3, array( 'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ) ) );
		$own_id = self::factory()->post->create( array( 'post_author' => $author ) );

		wp_set_current_user( $author );
		$result = $this->run_ability(
			'find-objects',
			array(
				'post_type' => 'post',
				'filter'    => 'all',
				'per_page'  => 1,
			)
		);
		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['total'] );
		$this->assertEquals( 1, $result['total_pages'] );
		$this->assertEquals( array( $own_id ), wp_list_pluck( $result['items'], 'object_id' ) );
	}

	/**
	 * Finding 6 (guard, no code change needed): a simplified object of an inactive language could not be changed.
	 *
	 * @return void
	 */
	public function test_inactive_language_of_simplified_object(): void {
		$copy_id = $this->create_simplification();
		$texts   = $this->run_ability( 'list-texts', array( 'object_id' => $copy_id ) );

		// deactivate the language.
		add_filter( 'option_easy_language_languages', array( $this, 'remove_target_language' ) );

		$result = $this->run_ability(
			'set-text-state',
			array(
				'object_id' => $copy_id,
				'text_id'   => $texts['items'][0]['text_id'],
				'state'     => 'ignore',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'easy_language_language_not_active', $result->get_error_code() );
	}

	/**
	 * Finding 7: the input schema of get-configuration does not contain an empty list as properties.
	 *
	 * @return void
	 */
	public function test_get_configuration_schema_has_no_empty_properties_list(): void {
		$schema = wp_get_ability( 'easy-language/get-configuration' )->get_input_schema();
		$this->assertStringNotContainsString( '"properties":[]', (string) wp_json_encode( $schema ) );
		$this->assertIsArray( $this->run_ability( 'get-configuration' ) );
	}

	/**
	 * Finding 8: list-texts without object scans only a limited count of texts.
	 *
	 * @return void
	 */
	public function test_list_texts_without_object_is_limited(): void {
		global $wpdb;

		$db = Db::get_instance();

		// add many texts of a not existing object directly in the DB.
		$values = array();
		for ( $i = 0; $i < 1005; $i++ ) {
			$values[] = $wpdb->prepare( '(%s, %s, %s, %s, %s, %s, %s)', gmdate( 'Y-m-d H:i:s' ), 'Text ' . $i, 'post_content', '', md5( 'Text ' . $i ), 'de_DE', 'to_simplify' );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'INSERT INTO `' . $db->get_table_name_originals() . '` (`time`, `original`, `field`, `html`, `hash`, `lang`, `state`) VALUES ' . implode( ',', $values ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$first_id = absint( $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ) );
		$values   = array();
		for ( $i = 0; $i < 1005; $i++ ) {
			$values[] = $wpdb->prepare( '(%d, %s, %s, %d, %d, %d, %s, %s)', $first_id + $i, gmdate( 'Y-m-d H:i:s' ), 'post', 999999, 1, get_current_blog_id(), 'gutenberg', '' );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'INSERT INTO `' . $db->get_table_name_originals_objects() . '` (`oid`, `time`, `object_type`, `object_id`, `order`, `blog_id`, `page_builder`, `state`) VALUES ' . implode( ',', $values ) );

		$result = $this->run_ability( 'list-texts', array( 'per_page' => 10 ) );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['truncated'] );
	}

	/**
	 * Finding 9: a text which is also used by objects the user could not edit could not be changed.
	 *
	 * @return void
	 */
	public function test_shared_text_could_not_be_changed_by_others(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		get_user_by( 'id', $author )->add_cap( 'edit_el_simplifier' );

		// the original and its simplification belong to the author.
		wp_update_post(
			array(
				'ID'          => $this->post_id,
				'post_author' => $author,
			)
		);
		$copy_id = $this->create_simplification();
		wp_update_post(
			array(
				'ID'          => $copy_id,
				'post_author' => $author,
			)
		);
		$texts   = $this->run_ability( 'list-texts', array( 'object_id' => $copy_id ) );
		$text_id = absint( $texts['items'][0]['text_id'] );

		// the text is also used by a post of another user.
		$other_id = self::factory()->post->create( array( 'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ) ) );
		$text     = current( Db::get_instance()->get_entries( array( 'id' => $text_id ) ) );
		$text->set_object( 'post', $other_id, 1, 'gutenberg' );

		wp_set_current_user( $author );
		$input = array(
			'object_id' => $copy_id,
			'text_id'   => $text_id,
		);

		$result = $this->run_ability( 'set-text-state', array_merge( $input, array( 'state' => 'ignore' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'easy_language_text_shared', $result->get_error_code() );

		$result = $this->run_ability( 'save-text-simplification', array_merge( $input, array( 'simplification' => 'Einfach.' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'easy_language_text_shared', $result->get_error_code() );

		$this->assertEquals( 'to_simplify', $text->get_state() );
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

		++self::$api_requests;

		$requests_response              = new \WpOrg\Requests\Response();
		$requests_response->status_code = 200;
		return array(
			'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] ),
			'response'      => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'          => \easyLanguage\Plugin\Helper::get_wp_filesystem()->get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'summ_ai.json' ),
		);
	}
}
