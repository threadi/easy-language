<?php
/**
 * Tests for class easyLanguage\EasyLanguage\Parsers.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\EasyLanguage\Parsers.
 */
class Parsers extends easyLanguageTests {
	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_parsers_as_objects(): void {
		$parsers = \easyLanguage\EasyLanguage\Parsers::get_instance()->get_parsers_as_objects();
		$this->assertIsArray( $parsers );
		$this->assertNotEmpty( $parsers );
	}
}
