<?php
/**
 * Tests for class easyLanguage\Plugin\Intervals.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\Plugin\Intervals.
 */
class Intervals extends easyLanguageTests {
	/**
	 * Test if the active api is set to capito.
	 *
	 * @return void
	 */
	public function test_get_intervals_as_objects(): void {
		$intervals = \easyLanguage\Plugin\Intervals::get_instance()->get_intervals_as_objects();
		$this->assertIsArray( $intervals );
		$this->assertNotEmpty( $intervals );
	}
}
