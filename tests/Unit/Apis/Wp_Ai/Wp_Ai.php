<?php
/**
 * Tests for the API via WordPress connectors.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Apis\Wp_Ai;

use easyLanguage\Apis\Wp_Ai\Wp_Ai as Wp_Ai_Api;
use easyLanguage\Tests\easyLanguageTests;
use easyLanguage\Tests\Fakes\Ai_Image_Provider;
use easyLanguage\Tests\Fakes\Ai_Model;
use easyLanguage\Tests\Fakes\Ai_Provider;

/**
 * Object to test the API via WordPress connectors.
 */
class Wp_Ai extends easyLanguageTests {
	/**
	 * The API object.
	 *
	 * @var Wp_Ai_Api
	 */
	private Wp_Ai_Api $api_obj;

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

		// use the fake AI provider as the only connected connector.
		Ai_Provider::register();
		Ai_Image_Provider::register();
		Ai_Provider::$configured       = true;
		Ai_Image_Provider::$configured = false;
		Ai_Model::reset();

		$this->api_obj = Wp_Ai_Api::get_instance();
		delete_transient( Wp_Ai_Api::TRANSIENT_PROVIDERS );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		Ai_Provider::$configured       = true;
		Ai_Image_Provider::$configured = false;
		Ai_Model::reset();
		delete_transient( Wp_Ai_Api::TRANSIENT_PROVIDERS );
		parent::tear_down();
	}

	/**
	 * Test that the API is registered.
	 *
	 * @return void
	 */
	public function test_api_is_registered(): void {
		$api = \easyLanguage\Plugin\Apis::get_instance()->get_api_by_name( 'wp_ai' );
		$this->assertInstanceOf( Wp_Ai_Api::class, $api );
		$this->assertInstanceOf( \easyLanguage\Apis\Wp_Ai\Simplifications::class, $api->get_simplifications_obj() );
	}

	/**
	 * Test the availability with a connected connector.
	 *
	 * @return void
	 */
	public function test_available_with_connector(): void {
		$this->assertTrue( $this->api_obj->is_available() );
		$this->assertTrue( $this->api_obj->is_configured() );
		$this->assertArrayHasKey( Ai_Provider::ID, $this->api_obj->get_text_providers() );

		// the description shows the connector.
		$description = $this->api_obj->get_description();
		$this->assertStringContainsString( Ai_Provider::NAME, $description );
		$this->assertStringNotContainsString( 'easy-language-api-unavailable', $description );
	}

	/**
	 * Test the availability without a connected connector.
	 *
	 * @return void
	 */
	public function test_not_available_without_connector(): void {
		Ai_Provider::$configured = false;

		$this->assertFalse( $this->api_obj->is_available() );
		$this->assertFalse( $this->api_obj->is_configured() );
		$this->assertEmpty( $this->api_obj->get_text_providers() );

		// the description shows the hint with the link to the connectors.
		$description = $this->api_obj->get_description();
		$this->assertStringContainsString( 'easy-language-api-unavailable', $description );
		$this->assertStringContainsString( 'options-connectors.php', $description );
	}

	/**
	 * Test that the cached list of connectors is updated if a connector is connected or removed.
	 *
	 * @return void
	 */
	public function test_cache_follows_connected_connectors(): void {
		$this->assertTrue( $this->api_obj->is_available() );

		// remove the connection: the API is not available anymore without waiting for the cache.
		Ai_Provider::$configured = false;
		$this->assertFalse( $this->api_obj->is_available() );

		// connect again.
		Ai_Provider::$configured = true;
		$this->assertTrue( $this->api_obj->is_available() );
	}

	/**
	 * Test that a connected connector without text generation (e.g. only for images) is not enough.
	 *
	 * @return void
	 */
	public function test_connector_without_text_generation(): void {
		Ai_Provider::$configured       = false;
		Ai_Image_Provider::$configured = true;

		$this->assertFalse( $this->api_obj->is_available() );
		$this->assertArrayNotHasKey( Ai_Image_Provider::ID, $this->api_obj->get_text_providers() );
	}

	/**
	 * Test that the cached list of connectors is not used if other connectors are connected.
	 *
	 * @return void
	 */
	public function test_cache_follows_changed_connectors(): void {
		// cache the list with the text connector.
		$this->assertArrayHasKey( Ai_Provider::ID, $this->api_obj->get_text_providers() );

		// replace it with a connector for images only.
		Ai_Provider::$configured       = false;
		Ai_Image_Provider::$configured = true;
		$this->assertEmpty( $this->api_obj->get_text_providers() );
		$this->assertFalse( $this->api_obj->is_available() );
	}

	/**
	 * Test that the settings are created after WordPress has passed the API keys of its connectors to its AI client.
	 *
	 * The keys of connectors, which are saved in the database, are passed on "init" with priority 20.
	 * Before that, the connectors are not connected for the AI client.
	 *
	 * @return void
	 */
	public function test_settings_are_created_after_connectors(): void {
		// the test environment of WordPress removes this hook (see tests/phpunit/includes/functions.php),
		// so use the priority WordPress uses in wp-includes/connectors.php.
		$connectors_priority = has_action( 'init', '_wp_connectors_pass_default_keys_to_ai_client' );
		if ( false === $connectors_priority ) {
			$connectors_priority = 20;
		}

		// the settings tab of this API.
		$this->assertGreaterThan( $connectors_priority, has_action( 'init', array( $this->api_obj, 'add_settings' ) ) );

		// the descriptions in the boxes for the choice of the API are created again after "init".
		$this->assertNotFalse( has_action( 'admin_init', array( \easyLanguage\Plugin\Settings::get_instance(), 'refresh_api_options' ) ) );
	}

	/**
	 * Test that the box of the API shows the actual state after the descriptions are created again.
	 *
	 * @return void
	 */
	public function test_box_shows_actual_state(): void {
		$settings = \easyLanguage\Plugin\Settings::get_instance();

		// not connected yet (like on "init" with priority 10 for connectors with their key in the database).
		Ai_Provider::$configured = false;
		$settings->refresh_api_options();
		$field = $settings->get_settings_object()->get_setting( 'easy_language_api' )->get_field();
		$this->assertStringContainsString( 'easy-language-api-unavailable', $field->get_options()['wp_ai'] );

		// connected later: the box must be available after the refresh.
		Ai_Provider::$configured = true;
		$settings->refresh_api_options();
		$this->assertStringNotContainsString( 'easy-language-api-unavailable', $field->get_options()['wp_ai'] );
		$this->assertStringContainsString( Ai_Provider::NAME, $field->get_options()['wp_ai'] );
	}

	/**
	 * Test that the API could only be activated if a connector is connected.
	 *
	 * @return void
	 */
	public function test_activation_requires_connector(): void {
		update_option( 'easy_language_api', 'summ_ai' );

		// without a connector the actual API remains.
		Ai_Provider::$configured = false;
		$this->assertEquals( 'summ_ai', \easyLanguage\Plugin\Settings::get_instance()->sanitize_api( 'wp_ai' ) );

		// with a connector it could be activated.
		Ai_Provider::$configured = true;
		$this->assertEquals( 'wp_ai', \easyLanguage\Plugin\Settings::get_instance()->sanitize_api( 'wp_ai' ) );

		// other APIs are not affected.
		$this->assertEquals( 'chatgpt', \easyLanguage\Plugin\Settings::get_instance()->sanitize_api( 'chatgpt' ) );
	}

	/**
	 * Test that the quota is unlimited.
	 *
	 * @return void
	 */
	public function test_quota_is_unlimited(): void {
		$quota = $this->api_obj->get_quota();
		$this->assertTrue( $quota['unlimited'] );
		$this->assertFalse( \easyLanguage\Plugin\Helper::is_quota_used_up( $this->api_obj ) );
	}

	/**
	 * Test the languages.
	 *
	 * @return void
	 */
	public function test_languages(): void {
		// only Einfache and Leichte Sprache are possible target languages.
		$this->assertEquals( array( 'de_EL', 'de_LS' ), array_keys( $this->api_obj->get_supported_target_languages() ) );

		// German is a source language for both.
		$this->assertEquals( array( 'de_LS', 'de_EL' ), $this->api_obj->get_mapping_languages()['de_DE'] );

		// the defaults are saved if the settings are missing.
		delete_option( Wp_Ai_Api::OPTION_SOURCE_LANGUAGES );
		delete_option( Wp_Ai_Api::OPTION_TARGET_LANGUAGES );
		$this->api_obj->add_default_options();
		$this->assertEquals( array( 'de_LS' ), array_keys( $this->api_obj->get_active_target_languages() ) );
		$this->assertArrayHasKey( 'de_DE', $this->api_obj->get_active_source_languages() );

		// the chosen languages are used.
		update_option( Wp_Ai_Api::OPTION_TARGET_LANGUAGES, array( 'de_EL' => '1' ) );
		$this->assertEquals( array( 'de_EL' ), array_keys( $this->api_obj->get_active_target_languages() ) );
		$this->assertEquals( array( 'de_DE' => array( 'de_EL' ) ), $this->api_obj->get_active_language_mapping() );
	}

	/**
	 * Test the choice of the connector.
	 *
	 * @return void
	 */
	public function test_provider_choice(): void {
		// only available connectors are allowed.
		$this->assertEquals( Ai_Provider::ID, $this->api_obj->sanitize_provider( Ai_Provider::ID ) );
		$this->assertEquals( '', $this->api_obj->sanitize_provider( 'unknown-connector' ) );

		// a chosen connector is used.
		update_option( Wp_Ai_Api::OPTION_PROVIDER, Ai_Provider::ID );
		$this->assertEquals( Ai_Provider::ID, $this->api_obj->get_provider() );

		// a chosen connector which is not connected anymore is ignored (automatic choice).
		Ai_Provider::$configured = false;
		$this->assertEquals( '', $this->api_obj->get_provider() );
	}

	/**
	 * Test the instruction for the AI.
	 *
	 * @return void
	 */
	public function test_instruction(): void {
		$easy = $this->api_obj->get_instruction( 'de_DE', 'de_LS', false );
		$this->assertStringContainsString( 'Leichter Sprache', $easy );
		$this->assertStringContainsString( 'ohne HTML', $easy );

		$plain = $this->api_obj->get_instruction( 'de_CH', 'de_EL', true );
		$this->assertStringContainsString( 'Einfacher Sprache', $plain );
		$this->assertStringContainsString( 'Schweizer Hochdeutsch', $plain );
		$this->assertStringContainsString( 'HTML-Struktur', $plain );

		// the instruction could be changed via filter.
		$filter = static fn() => 'Eigene Anweisung';
		add_filter( 'easy_language_wp_ai_instruction', $filter );
		$this->assertEquals( 'Eigene Anweisung', $this->api_obj->get_instruction( 'de_DE', 'de_LS', false ) );
		remove_filter( 'easy_language_wp_ai_instruction', $filter );
	}
}
