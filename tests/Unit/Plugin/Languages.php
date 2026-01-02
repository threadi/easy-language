<?php
/**
 * Tests for class easyLanguage\Plugin\Languages.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\Plugin\Languages.
 */
class Languages extends easyLanguageTests {

	/**
	 * Test if the active api is set to capito.
	 *
	 * @return void
	 */
	public function test_is_german_language(): void {
		$is_german_language = \easyLanguage\Plugin\Languages::get_instance()->is_german_language();
		$this->assertTrue( $is_german_language );
	}

	/**
	 * Test if we get the active languages as an array and 'de_LS' is in it.
	 *
	 * @return void
	 */
	public function test_get_active_languages(): void {
		$active_languages = \easyLanguage\Plugin\Languages::get_instance()->get_active_languages();
		$this->assertIsArray( $active_languages );
		$this->assertArrayHasKey( 'de_LS', $active_languages );
	}
}
