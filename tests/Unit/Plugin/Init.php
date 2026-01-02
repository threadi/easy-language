<?php
/**
 * Tests for class easyLanguage\Plugin\Init.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\Plugin\Init.
 */
class Init extends easyLanguageTests {

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_add_row_meta_links_without_errors(): void {
		$list_of_link = \easyLanguage\Plugin\Init::get_instance()->add_row_meta_links( array( 'test' => 'test' ), 'test_file' );
		$this->assertIsArray( $list_of_link );
	}
}
