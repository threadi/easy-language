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

		// set the mode to "paid" with an API key.
		update_option( 'easy_language_summ_ai_mode', $mode_name );
		update_option( 'easy_language_summ_ai_api_key', 'example-key' );

		// test it.
		$mode = \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_mode();
		$this->assertIsString( $mode );
		$this->assertEquals( $mode_name, $mode );

		// cleanup.
		delete_option( 'easy_language_summ_ai_api_key' );
		delete_option( 'easy_language_summ_ai_mode' );
	}

	/**
	 * Test that the free mode is used if the paid mode is saved, but no API key is available.
	 *
	 * @return void
	 */
	public function test_paid_mode_without_key_falls_back_to_free(): void {
		update_option( 'easy_language_summ_ai_mode', 'paid' );
		delete_option( 'easy_language_summ_ai_api_key' );

		$summ_ai = \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance();
		$this->assertEquals( 'free', $summ_ai->get_mode() );
		$this->assertTrue( $summ_ai->is_free_mode() );
		$this->assertEquals( EASY_LANGUAGE_SUMM_AI_FREE_API_URL, $summ_ai->get_api_url() );

		delete_option( 'easy_language_summ_ai_mode' );
	}
}
