<?php
/**
 * Tests for class easyLanguage\Apis\No_Api\Simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Apis\No_Api;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\Apis\No_Api\Simplifications.
 */
class Simplifications extends easyLanguageTests {
	/**
	 * Test if the returning variable is a boolean.
	 *
	 * @return void
	 */
	public function test_call_api(): void {
		$result = \easyLanguage\Apis\No_Api\Simplifications::get_instance()->call_api( '', '', '', false );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

}
