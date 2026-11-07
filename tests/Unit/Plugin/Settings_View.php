<?php
/**
 * Tests for the choice of the view of the settings and for the settings in the REST API, which the DataView uses.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Tests\easyLanguageTests;
use easySettingsForWordPress\Fields\Select;
use WP_REST_Request;

/**
 * Object to test the view of the settings.
 */
class Settings_View extends easyLanguageTests {
	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( 'easy_language_settings_view' );
		parent::tear_down();
	}

	/**
	 * The modern view (DataView) is the default.
	 *
	 * @return void
	 */
	public function test_default_is_dataview(): void {
		delete_option( 'easy_language_settings_view' );
		$this->assertSame( 'dataview', \easyLanguage\Plugin\Settings::get_instance()->get_view() );
	}

	/**
	 * The classic view could be chosen.
	 *
	 * @return void
	 */
	public function test_classic_could_be_chosen(): void {
		update_option( 'easy_language_settings_view', 'classic' );
		$this->assertSame( 'classic', \easyLanguage\Plugin\Settings::get_instance()->get_view() );
	}

	/**
	 * Unknown values use the default.
	 *
	 * @return void
	 */
	public function test_unknown_view_uses_default(): void {
		update_option( 'easy_language_settings_view', 'unknown' );
		$this->assertSame( 'dataview', \easyLanguage\Plugin\Settings::get_instance()->get_view() );
	}

	/**
	 * The setting is shown in the advanced settings, only known views could be saved and the page is loaded again after saving.
	 *
	 * @return void
	 */
	public function test_setting(): void {
		$setting = \easyLanguage\Plugin\Settings::get_instance()->get_settings_object()->get_setting( 'easy_language_settings_view' );
		$this->assertInstanceOf( \easySettingsForWordPress\Setting::class, $setting );
		$this->assertSame( 'dataview', $setting->get_default() );
		$this->assertTrue( $setting->should_reload_on_save() );

		$field = $setting->get_field();
		$this->assertInstanceOf( Select::class, $field );
		$this->assertSame( array( 'dataview', 'classic' ), array_keys( $field->get_options() ) );
		$this->assertSame( 'classic', $field->default_sanitize_callback( 'classic' ) );
		$this->assertSame( '', $field->default_sanitize_callback( 'unknown' ) );
	}

	/**
	 * The DataView reads the settings via the REST API: settings with checkboxes must return their stored values
	 * (and not null, which would also be saved again by the DataView).
	 *
	 * @return void
	 */
	public function test_checkboxes_in_rest_api(): void {
		update_option( 'easy_language_post_types', array( 'page' => '1', 'post' => '1' ) );
		update_option( 'easy_language_languages', array( 'de_LS' => '1' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/settings' ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( array( 'page' => '1', 'post' => '1' ), $data['easy_language_post_types'] );
		$this->assertEquals( array( 'de_LS' => '1' ), $data['easy_language_languages'] );
	}

	/**
	 * Saving the values from the REST API again (like the DataView does) keeps the chosen checkboxes.
	 *
	 * @return void
	 */
	public function test_checkboxes_saved_via_rest_api(): void {
		update_option( 'easy_language_post_types', array( 'page' => '1', 'post' => '1' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$request->set_body_params( array( 'easy_language_post_types' => array( 'page' => '1', 'post' => '1' ) ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$this->assertEquals( array( 'page' => 1, 'post' => 1 ), get_option( 'easy_language_post_types' ) );
	}
}
