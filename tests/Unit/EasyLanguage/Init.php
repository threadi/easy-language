<?php
/**
 * Tests for class easyLanguage\EasyLanguage\Init.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\EasyLanguage\Init.
 */
class Init extends easyLanguageTests {

	/**
	 * Test if the returning variable is a boolean.
	 *
	 * @return void
	 */
	public function test_is_page_post_type_supported(): void {
		$is_page_type_supported = \easyLanguage\EasyLanguage\Init::get_instance()->is_post_type_supported( 'page' );
		$this->assertIsBool( $is_page_type_supported );
		$this->assertTrue( $is_page_type_supported );
	}

	/**
	 * Test if the returning variable is a boolean.
	 *
	 * @return void
	 */
	public function test_is_post_post_type_supported(): void {
		$is_post_type_supported = \easyLanguage\EasyLanguage\Init::get_instance()->is_post_type_supported( 'post' );
		$this->assertIsBool( $is_post_type_supported );
		$this->assertTrue( $is_post_type_supported );
	}
}
