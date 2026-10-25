<?php
/**
 * Tests for class easyLanguage\Plugin\Update.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\Plugin\Update.
 */
class Update extends easyLanguageTests {

	/**
	 * Test that new installations do not use the compatibility mode of the language switcher.
	 *
	 * @return void
	 */
	public function test_new_installation_without_compatibility_mode(): void {
		delete_option( 'easy_language_switcher_compatibility' );

		// shortcodes and Elementor widgets do not set "legacy", so they follow the setting.
		$this->assertFalse( Switcher::get_instance()->normalize_settings( array() )['legacy'] );
	}

	/**
	 * Test that the update to 4.0.0 enables the compatibility mode for existing installations.
	 *
	 * @return void
	 */
	public function test_update_to_4_enables_compatibility_mode(): void {
		delete_option( 'easy_language_switcher_compatibility' );
		\easyLanguage\Plugin\Update::get_instance()->version400();

		$this->assertEquals( 1, absint( get_option( 'easy_language_switcher_compatibility' ) ) );
		$this->assertTrue( Switcher::get_instance()->normalize_settings( array() )['legacy'] );

		// blocks added since 4.0.0 and page builder widgets are not affected.
		$this->assertFalse( Switcher::get_instance()->normalize_settings( array( 'legacy' => false ) )['legacy'] );
	}

	/**
	 * Test that the update also enables the mode if the setting has been saved during a reactivation before.
	 *
	 * @return void
	 */
	public function test_update_to_4_overwrites_default_from_activation(): void {
		update_option( 'easy_language_switcher_compatibility', 0 );
		\easyLanguage\Plugin\Update::get_instance()->version400();
		$this->assertEquals( 1, absint( get_option( 'easy_language_switcher_compatibility' ) ) );
	}
}
