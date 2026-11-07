<?php
/**
 * Tests to simplify an existing object via the API with WordPress connectors.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Scenarios;

use easyLanguage\Apis\Wp_Ai\Wp_Ai;
use easyLanguage\Tests\easyLanguageTests;
use easyLanguage\Tests\Fakes\Ai_Model;
use easyLanguage\Tests\Fakes\Ai_Provider;

/**
 * Object to test the simplification of an existing object via WordPress connectors.
 */
class AddSimplificationObjectAndSimplifyItViaWpAi extends easyLanguageTests {
	/**
	 * The ID of the example post.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * The API object.
	 *
	 * @var Wp_Ai
	 */
	private Wp_Ai $api_obj;

	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// bail if the AI client of WordPress is not available (WordPress < 7.0).
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'The WordPress connectors require WordPress 7.0 or newer.' );
		}

		// set language.
		switch_to_locale( 'de_DE' );

		// use the fake AI provider as the only connected connector.
		Ai_Provider::register();
		Ai_Provider::$configured = true;
		Ai_Model::reset();
		delete_transient( Wp_Ai::TRANSIENT_PROVIDERS );

		// activate the API with German as source and Leichte Sprache as target language.
		$this->api_obj = Wp_Ai::get_instance();
		update_option( 'easy_language_api', 'wp_ai' );
		update_option( Wp_Ai::OPTION_SOURCE_LANGUAGES, array( 'de_DE' => '1' ) );
		update_option( Wp_Ai::OPTION_TARGET_LANGUAGES, array( 'de_LS' => '1' ) );

		// use an administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// create the original post.
		$this->post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Hallo Konnektor',
				'post_content' => '<p>Das ist ein kompliziertes Beispiel für die Konnektoren von WordPress.</p>',
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
		Ai_Model::reset();
		delete_transient( Wp_Ai::TRANSIENT_PROVIDERS );
		wp_unschedule_hook( \easyLanguage\EasyLanguage\Abilities::CRON_HOOK );
		parent::tear_down();
	}

	/**
	 * Test to create and simplify an object via the API (same process as in the backend).
	 *
	 * @return void
	 */
	public function test_create_and_simplify_object(): void {
		$this->assertSame( $this->api_obj, \easyLanguage\Plugin\Apis::get_instance()->get_active_api() );

		// add the simplified object.
		$copy = \easyLanguage\Plugin\Helper::get_object( $this->post_id, 'post' )->add_simplification_object( 'de_LS', $this->api_obj, false );
		$this->assertInstanceOf( \easyLanguage\EasyLanguage\Post_Object::class, $copy );

		// run the simplification.
		$copy->process_simplifications( $this->api_obj->get_simplifications_obj(), $this->api_obj->get_active_language_mapping(), 10 );

		// both texts (title and paragraph) have been sent to the AI and are inserted.
		$this->assertCount( 2, Ai_Model::$requests );
		$this->assertEquals(
			2,
			\easyLanguage\EasyLanguage\Db::get_instance()->count_entries(
				array(
					'object_id'   => $copy->get_id(),
					'object_type' => 'post',
					'state'       => 'in_use',
				)
			)
		);
		$this->assertStringContainsString( 'Vereinfacht: ', (string) get_post_field( 'post_content', $copy->get_id() ) );
		$this->assertStringStartsWith( 'Vereinfacht: ', get_the_title( $copy->get_id() ) );
	}

	/**
	 * Test the simplification via the abilities of Easy Language (WordPress 6.9 or newer).
	 *
	 * @return void
	 */
	public function test_simplify_via_abilities(): void {
		global $wp_current_filter;

		// register all abilities.
		update_option( \easyLanguage\EasyLanguage\Abilities::OPTION_ENABLED, 1 );
		update_option( \easyLanguage\EasyLanguage\Abilities::OPTION_MODE, 'all' );
		foreach ( wp_get_abilities() as $ability ) {
			if ( str_starts_with( $ability->get_name(), 'easy-language/' ) ) {
				wp_unregister_ability( $ability->get_name() );
			}
		}
		$wp_current_filter[] = 'wp_abilities_api_init';
		\easyLanguage\EasyLanguage\Abilities::get_instance()->register_abilities();
		array_pop( $wp_current_filter );

		// create the simplification and request it via the active API (the quota is unlimited).
		$create = wp_get_ability( 'easy-language/create-simplification' )->execute(
			array(
				'object_id' => $this->post_id,
				'language'  => 'de_LS',
			)
		);
		$this->assertIsArray( $create );
		$copy_id = $create['simplification']['object_id'];

		$run = wp_get_ability( 'easy-language/run-simplification' )->execute( array( 'object_id' => $copy_id ) );
		$this->assertIsArray( $run );
		$this->assertEquals( 'scheduled', $run['status'] );
		$this->assertEquals( 'WordPress Connectors', $run['api'] );

		// run the background process.
		\easyLanguage\EasyLanguage\Abilities::get_instance()->run_scheduled_simplification( $copy_id, 'post' );

		$status = wp_get_ability( 'easy-language/get-object-simplifications' )->execute( array( 'object_id' => $this->post_id ) );
		$this->assertEquals( 0, $status['simplifications'][0]['texts']['to_simplify'] );
		$this->assertEquals( 2, $status['simplifications'][0]['texts']['in_use'] );
		$this->assertCount( 2, Ai_Model::$requests );
	}
}
