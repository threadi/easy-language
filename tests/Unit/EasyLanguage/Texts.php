<?php
/**
 * Tests for class easyLanguage\EasyLanguage\Texts.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\EasyLanguage\Texts.
 */
class Texts extends easyLanguageTests {
	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_empty_texts(): void {
		// test it.
		$texts = \easyLanguage\EasyLanguage\Texts::get_instance()->get_texts();
		$this->assertIsArray( $texts );
		$this->assertEmpty( $texts );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_texts(): void {
		// create a text example.
		$test_text = 'This is a test text.';
		$test_source_language = 'en_US';
		$test_field = 'title';
		$html_marker = false;

		// add the test entry.
		$text = \easyLanguage\EasyLanguage\Db::get_instance()->add( $test_text, $test_source_language, $test_field, $html_marker );

		// test it.
		$texts = \easyLanguage\EasyLanguage\Texts::get_instance()->get_texts();
		$this->assertIsArray( $texts );
		$this->assertNotEmpty( $texts );
		$this->assertStringContainsString( $test_text, $texts[0]->get_original() );
	}
}
