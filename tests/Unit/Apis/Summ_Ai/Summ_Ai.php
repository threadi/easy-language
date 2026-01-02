<?php
/**
 * Tests for class easyLanguage\Apis\Summ_Ai\Summ_Ai.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Apis\Summ_Ai;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\Apis\Summ_Ai\Summ_Ai.
 */
class Summ_Ai extends easyLanguageTests {
	/**
	 * Test if the returning variable is an object.
	 *
	 * @return void
	 */
	public function test_get_simplifications_obj(): void {
		$simplifications_obj = \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_simplifications_obj();
		$this->assertIsObject( $simplifications_obj );
		$this->assertInstanceOf( \easyLanguage\Apis\Summ_Ai\Simplifications::class, $simplifications_obj );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_active_target_languages(): void {
		$active_target_languages = \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_active_target_languages();
		$this->assertIsArray( $active_target_languages );
		$this->assertNotEmpty( $active_target_languages );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_supported_source_languages(): void {
		$supported_source_languages = \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_supported_source_languages();
		$this->assertIsArray( $supported_source_languages );
		$this->assertNotEmpty( $supported_source_languages );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_free_mode(): void {
		$mode = \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_mode();
		$this->assertIsString( $mode );
		$this->assertEquals( 'free', $mode );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_paid_mode(): void {
		// set the name for the paid mode.
		$mode_name = 'paid';

		// set the mode to "paid".
		update_option( 'easy_language_summ_ai_mode', $mode_name );

		// test it.
		$mode = \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_mode();
		$this->assertIsString( $mode );
		$this->assertEquals( $mode_name, $mode );
	}
}
