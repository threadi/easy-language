<?php
/**
 * Tests for the choice of the API in the settings.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Tests\easyLanguageTests;
use easySettingsForWordPress\Fields\Cards;

/**
 * Object to test the choice of the API, which is shown as cards.
 */
class Api_Choice extends easyLanguageTests {
	/**
	 * Use capito as active API without saving the option (saving it would install translations).
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		add_filter( 'pre_option_easy_language_api', array( $this, 'get_capito' ) );
	}

	/**
	 * Remove the filter for the active API.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_option_easy_language_api', array( $this, 'get_capito' ) );
		parent::tear_down();
	}

	/**
	 * Return the name of capito as active API.
	 *
	 * @return string
	 */
	public function get_capito(): string {
		return \easyLanguage\Apis\Capito\Capito::get_instance()->get_name();
	}

	/**
	 * The choice of the API uses the field "Cards" with 3 columns.
	 *
	 * @return void
	 */
	public function test_field_is_cards(): void {
		$setting = \easyLanguage\Plugin\Settings::get_instance()->get_settings_object()->get_setting( 'easy_language_api' );
		$this->assertInstanceOf( \easySettingsForWordPress\Setting::class, $setting );

		$field = $setting->get_field();
		$this->assertInstanceOf( Cards::class, $field );
		$this->assertSame( 3, $field->get_columns() );

		// the page is loaded again after saving a new API, so the badge and the tabs of the APIs are actual.
		$this->assertTrue( $setting->should_reload_on_save() );
	}

	/**
	 * Each available API is an option with title, logo and description, the active API has a badge.
	 *
	 * @return void
	 */
	public function test_options(): void {
		$options = \easyLanguage\Plugin\Settings::get_instance()->get_api_options();

		// each available API is an option.
		$this->assertCount( count( \easyLanguage\Plugin\Apis::get_instance()->get_available_apis() ), $options );

		foreach ( \easyLanguage\Plugin\Apis::get_instance()->get_available_apis() as $api_obj ) {
			$this->assertArrayHasKey( $api_obj->get_name(), $options );
			$option = $options[ $api_obj->get_name() ];
			$this->assertSame( $api_obj->get_title(), $option['label'] );
			$this->assertSame( $api_obj->get_logo_url(), $option['icon'] );
			$this->assertSame( $api_obj->get_description(), $option['description'] );
		}

		// only the active API has the badge.
		$this->assertSame( 'Active', $options['capito']['badge'] );
		foreach ( $options as $name => $option ) {
			if ( 'capito' === $name ) {
				continue;
			}
			$this->assertSame( '', $option['badge'] );
		}
	}

	/**
	 * The DataView gets the cards (not the HTML as label of radio buttons).
	 *
	 * @return void
	 */
	public function test_dataview_configuration(): void {
		\easyLanguage\Plugin\Settings::get_instance()->refresh_api_options();

		$configuration = \easyLanguage\Plugin\Settings::get_instance()->get_settings_object()->get_setting( 'easy_language_api' )->get_dataview();

		$this->assertSame( 'esfw-cards', $configuration['type'] );
		$this->assertSame( 3, $configuration['columns'] );
		$this->assertContains( 'capito', array_column( $configuration['options'], 'value' ) );
		$this->assertTrue( $configuration['reload_on_save'] );
	}
}
