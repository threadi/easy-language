<?php
/**
 * Tests for class easyLanguage\Apis\Capito\Capito.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Apis\Capito;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\Apis\Capito\Capito.
 */
class Capito extends easyLanguageTests {
	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// set a pseudo-key.
		update_option( 'easy_language_capito_api_key', 'example-key' );

		// switch to German.
		switch_to_locale( 'de_DE' );

		// update the target languages.
		update_option( 'easy_language_capito_target_languages', array( 'de_b1' => '1' ) );
	}

	/**
	 * Test if the returning variable is an object.
	 *
	 * @return void
	 */
	public function test_get_simplifications_obj(): void {
		$simplifications_obj = \easyLanguage\Apis\Capito\Capito::get_instance()->get_simplifications_obj();
		$this->assertIsObject( $simplifications_obj );
		$this->assertInstanceOf( \easyLanguage\Apis\Capito\Simplifications::class, $simplifications_obj );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_active_target_languages(): void {
		$active_target_languages = \easyLanguage\Apis\Capito\Capito::get_instance()->get_active_target_languages();
		$this->assertIsArray( $active_target_languages );
		$this->assertNotEmpty( $active_target_languages );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_supported_source_languages(): void {
		$supported_source_languages = \easyLanguage\Apis\Capito\Capito::get_instance()->get_supported_source_languages();
		$this->assertIsArray( $supported_source_languages );
		$this->assertNotEmpty( $supported_source_languages );
	}
}
