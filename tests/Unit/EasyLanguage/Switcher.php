<?php
/**
 * Tests for class easyLanguage\Plugin\Switcher.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\Plugin\Switcher.
 */
class Switcher extends easyLanguageTests {

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// make sure our CPT is registered before each test.
		\easyLanguage\EasyLanguage\Switcher::get_instance()->wp_init();
		flush_rewrite_rules();
	}

	/**
	 * Test is our cpt is registered
	 *
	 * @return void
	 */
	public function test_lel_lang_switcher_post_type_is_registered(): void {
		$this->assertTrue( post_type_exists( 'lel_lang_switcher' ), 'The "lel_lang_switcher" post type should be registered.' );
	}

	/**
	 * Test if our cpt is not public.
	 *
	 * @return void
	 */
	public function test_lel_lang_switcher_post_type_is_not_public(): void {
		$post_type_obj = get_post_type_object( 'lel_lang_switcher' );

		$this->assertNotNull( $post_type_obj );
		$this->assertFalse( $post_type_obj->public );
		$this->assertTrue( $post_type_obj->show_ui );
	}
}
