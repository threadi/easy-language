<?php
/**
 * Stability tests for class easyLanguage\EasyLanguage\Init.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\B;

use easyLanguage\EasyLanguage\Init;
use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;
use WP_HTTP_Requests_Response;

/**
 * Object to test fixes in easyLanguage\EasyLanguage\Init.
 */
class Init_Stability extends easyLanguageTests {
	/**
	 * The name of the API to use as active API.
	 *
	 * @var string
	 */
	private string $active_api = '';

	/**
	 * Count of outgoing requests.
	 *
	 * @var int
	 */
	private int $requests = 0;

	/**
	 * The created simplified objects.
	 *
	 * @var array<int,Post_Object>
	 */
	private array $copies = array();

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->requests = 0;
		add_filter( 'pre_http_request', array( $this, 'filter_requests' ), 10, 3 );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'filter_requests' ) );
		remove_filter( 'pre_option_easy_language_api', array( $this, 'get_active_api' ) );
		// remove the texts and markers of the created objects, as the texts are not part of the transaction.
		foreach ( $this->copies as $copy ) {
			foreach ( $copy->get_entries() as $entry ) {
				$entry->delete();
			}
			$copy->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING );
		}
		$this->copies = array();
		\easyLanguage\EasyLanguage\Db::get_instance()->reset_simplifications();
		restore_previous_locale();

		// restore the schedule of the automatic simplification.
		wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
		wp_schedule_event( time(), 'easy_language_15minutly', 'easy_language_automatic_simplification' );
		parent::tear_down();
	}

	/**
	 * Return the active API name.
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
	 * Simulate the responses of SUMM AI and count the requests.
	 *
	 * @param bool|array $response    The return value.
	 * @param array      $parsed_args The parsed arguments.
	 * @param string     $url         The used URL.
	 *
	 * @return array|\WP_Error
	 */
	public function filter_requests( bool|array $response, array $parsed_args, string $url ): array|\WP_Error {
		++$this->requests;
		if ( 'POST' !== $parsed_args['method'] ) {
			return new \WP_Error( 'blocked', 'blocked' );
		}
		$requests_response              = new \WpOrg\Requests\Response();
		$requests_response->status_code = 200;
		return array(
			'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] ),
			'body'          => \easyLanguage\Plugin\Helper::get_wp_filesystem()->get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'summ_ai.json' ),
			'headers'       => array(),
			'response'      => array( 'code' => 200 ),
		);
	}

	/**
	 * Create a simplified copy of a new post via SUMM AI.
	 *
	 * @return Post_Object
	 */
	private function create_simplified_copy(): Post_Object {
		$this->use_api( 'summ_ai' );
		switch_to_locale( 'de_DE' );
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<p>Das ist ein kompliziertes Beispiel für Text.</p>',
				'post_title'   => 'Hallo',
			)
		);
		$object  = new Post_Object( $post_id );
		$copy    = $object->add_simplification_object( 'de_LS', \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance(), false );
		$this->assertInstanceOf( Post_Object::class, $copy );
		$this->copies[] = $copy;
		return $copy;
	}

	/**
	 * The dialog without configured API must have a list of buttons (also with the settings button for admins).
	 *
	 * @return void
	 */
	public function test_first_simplify_dialog_buttons_are_a_list(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post_object = new Post_Object( self::factory()->post->create() );
		$dialog      = array(
			'title'   => 'Test',
			'texts'   => array( '<p>Test</p>' ),
			'buttons' => array(
				array( 'text' => 'auto' ),
				array( 'text' => 'manually' ),
				array( 'text' => 'cancel' ),
				array( 'text' => 'settings' ),
			),
		);

		$result = Init::get_instance()->change_first_simplify_dialog( $dialog, \easyLanguage\Apis\No_Api\No_Api::get_instance(), $post_object );

		$this->assertTrue( array_is_list( $result['buttons'] ) );
		$this->assertCount( 3, $result['buttons'] );
		$this->assertSame( 'manually', $result['buttons'][0]['text'] );
		$this->assertStringStartsWith( '[', (string) wp_json_encode( $result['buttons'] ) );
	}

	/**
	 * The automatic simplification must not run for an object, which is simplified in another process, and
	 * must not overwrite its running marker.
	 *
	 * @return void
	 */
	public function test_automatic_simplification_skips_running_object(): void {
		update_option( 'easy_language_automatic_simplification_enabled', 1 );
		$copy = $this->create_simplified_copy();
		$this->assertNotEmpty( $copy->get_entries() );

		// mark it as running in another process.
		$running = time() - 5;
		$copy->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, $running );
		$this->requests = 0;

		Init::get_instance()->run_automatic_simplification();

		$this->assertSame( 0, $this->requests );
		$this->assertSame( $running, absint( $copy->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING ) ) );
	}

	/**
	 * The automatic simplification removes its own running marker after the simplification.
	 *
	 * @return void
	 */
	public function test_automatic_simplification_restores_running_marker(): void {
		update_option( 'easy_language_automatic_simplification_enabled', 1 );
		$copy = $this->create_simplified_copy();
		$copy->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING );
		$this->requests = 0;

		Init::get_instance()->run_automatic_simplification();

		$this->assertGreaterThan( 0, $this->requests );
		$this->assertNull( $copy->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING ) );
	}

	/**
	 * The SUMM AI source language must be the new locale.
	 *
	 * @return void
	 */
	public function test_option_locale_changed_uses_new_value(): void {
		Init::get_instance()->option_locale_changed( 'en_US', 'de_DE' );
		$this->assertSame( array( 'de_DE' => '1' ), get_option( 'easy_language_summ_ai_source_languages' ) );
	}

	/**
	 * The body class must be separated from other classes.
	 *
	 * @return void
	 */
	public function test_add_body_class_with_space(): void {
		update_option( 'easy_language_intro_step_2', 1 );
		$this->assertSame( 'foo easy-language-intro-step-2', Init::get_instance()->add_body_class( 'foo' ) );
	}

	/**
	 * Saving the interval with the same value must not reschedule the event, an unknown interval must not remove it.
	 *
	 * @return void
	 */
	public function test_set_automatic_interval_reschedules_only_on_change(): void {
		wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
		$timestamp = time() + 600;
		wp_schedule_event( $timestamp, 'hourly', 'easy_language_automatic_simplification' );

		// same value: no change.
		$this->assertSame( 'hourly', Init::get_instance()->set_automatic_interval( 'hourly' ) );
		$this->assertSame( $timestamp, wp_next_scheduled( 'easy_language_automatic_simplification' ) );

		// unknown interval: event remains.
		Init::get_instance()->set_automatic_interval( 'easy_language_unknown_interval' );
		$this->assertSame( $timestamp, wp_next_scheduled( 'easy_language_automatic_simplification' ) );
		$this->assertSame( 'hourly', wp_get_schedule( 'easy_language_automatic_simplification' ) );

		// other registered interval: rescheduled.
		Init::get_instance()->set_automatic_interval( 'daily' );
		$this->assertSame( 'daily', wp_get_schedule( 'easy_language_automatic_simplification' ) );
	}

	/**
	 * The deactivation removes the automatic simplification and the ability user markers.
	 *
	 * @return void
	 */
	public function test_deactivation_clears_cron_and_markers(): void {
		update_option( 'easy_language_state_on_deactivation', 'disabled' );
		update_option( 'easy_language_ability_user_' . md5( 'test' ), 1, false );
		if ( ! wp_next_scheduled( 'easy_language_automatic_simplification' ) ) {
			wp_schedule_event( time(), 'hourly', 'easy_language_automatic_simplification' );
		}

		Init::get_instance()->deactivation();

		$this->assertFalse( wp_next_scheduled( 'easy_language_automatic_simplification' ) );
		$this->assertFalse( get_option( 'easy_language_ability_user_' . md5( 'test' ) ) );
	}

	/**
	 * The uninstallation removes simplified posts of unsupported post types and simplified terms incl. their meta.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_simplified_terms_and_posts(): void {
		// simplified post of a post type which is not supported (and not registered).
		$post_id = self::factory()->post->create( array( 'post_type' => 'el_not_registered' ) );
		update_post_meta( $post_id, 'easy_language_simplification_original_id', 1 );

		// simplified term.
		$original = self::factory()->term->create( array( 'taxonomy' => 'category' ) );
		$term_id  = self::factory()->term->create( array( 'taxonomy' => 'category' ) );
		update_term_meta( $term_id, 'easy_language_simplification_original_id', $original );
		update_term_meta( $term_id, 'easy_language_simplification_language', 'de_LS' );
		update_term_meta( $original, 'easy_language_simplified_in', array( 'de_LS' => $term_id ) );
		update_term_meta( $original, 'easy_language_de_LS_changed', '1' );

		// ability marker.
		update_option( 'easy_language_ability_user_' . md5( 'test' ), 1, false );

		Init::get_instance()->uninstall();

		$this->assertNull( get_post( $post_id ) );
		$this->assertNull( term_exists( $term_id, 'category' ) );
		$this->assertNotNull( term_exists( $original, 'category' ) );
		wp_cache_flush();
		$this->assertSame( '', get_term_meta( $original, 'easy_language_simplified_in', true ) );
		$this->assertSame( '', get_term_meta( $original, 'easy_language_de_LS_changed', true ) );
		$this->assertFalse( get_option( 'easy_language_ability_user_' . md5( 'test' ) ) );
	}
}
