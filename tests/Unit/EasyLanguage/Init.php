<?php
/**
 * Tests for class easyLanguage\EasyLanguage\Init.
 *
 * @package easy-language
 */

namespace easyLanguage\UnitTest\EasyLanguage;

use WP_UnitTestCase;

/**
 * Object to test functions in class easyLanguage\EasyLanguage\Init.
 */
class Init extends WP_UnitTestCase {

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_is_post_type_supported(): void {
		// check for post.
		$is_post_type_supported = \easyLanguage\EasyLanguage\Init::get_instance()->is_post_type_supported( 'post' );
		$this->assertTrue( $is_post_type_supported );

		// check for page.
		$is_page_type_supported = \easyLanguage\EasyLanguage\Init::get_instance()->is_post_type_supported( 'page' );
		$this->assertTrue( $is_page_type_supported );
	}
}
