<?php
/**
 * Tests for class easyLanguage\Plugin\Apis.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\Plugin\Apis.
 */
class Apis extends easyLanguageTests {

	/**
	 * Test if the active api is set to capito.
	 *
	 * @return void
	 */
	public function test_get_active_api_is_capito(): void {
		update_option( 'easy_language_api', \easyLanguage\Apis\Capito\Capito::get_instance()->get_name() );
		$active_api = \easyLanguage\Plugin\Apis::get_instance()->get_active_api();
		$this->assertIsObject( $active_api );
		$this->assertInstanceOf( '\easyLanguage\Apis\Capito\Capito', $active_api );
	}

	/**
	 * Test if the active api is set to ChatGPT.
	 *
	 * @return void
	 */
	public function test_get_active_api_is_chatgpt(): void {
		update_option( 'easy_language_api', \easyLanguage\Apis\ChatGpt\ChatGpt::get_instance()->get_name() );
		$active_api = \easyLanguage\Plugin\Apis::get_instance()->get_active_api();
		$this->assertIsObject( $active_api );
		$this->assertInstanceOf( '\easyLanguage\Apis\ChatGpt\ChatGpt', $active_api );
	}

	/**
	 * Test if the active api is set to SUMM AI.
	 *
	 * @return void
	 */
	public function test_get_active_api_is_summ_ai(): void {
		update_option( 'easy_language_api', \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_name() );
		$active_api = \easyLanguage\Plugin\Apis::get_instance()->get_active_api();
		$this->assertIsObject( $active_api );
		$this->assertInstanceOf( '\easyLanguage\Apis\Summ_Ai\Summ_Ai', $active_api );
	}

	/**
	 * Test if the returned api is the expected one.
	 *
	 * @return void
	 */
	public function test_get_api_by_name_capito(): void {
		$active_api = \easyLanguage\Plugin\Apis::get_instance()->get_api_by_name( \easyLanguage\Apis\Capito\Capito::get_instance()->get_name() );
		$this->assertIsObject( $active_api );
		$this->assertInstanceOf( '\easyLanguage\Apis\Capito\Capito', $active_api );
	}

	/**
	 * Test if the returned api is the expected one.
	 *
	 * @return void
	 */
	public function test_get_api_by_name_chatgpt(): void {
		$active_api = \easyLanguage\Plugin\Apis::get_instance()->get_api_by_name( \easyLanguage\Apis\ChatGpt\ChatGpt::get_instance()->get_name() );
		$this->assertIsObject( $active_api );
		$this->assertInstanceOf( '\easyLanguage\Apis\ChatGpt\ChatGpt', $active_api );
	}

	/**
	 * Test if the returned api is the expected one.
	 *
	 * @return void
	 */
	public function test_get_api_by_name_summ_ai(): void {
		$active_api = \easyLanguage\Plugin\Apis::get_instance()->get_api_by_name( \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_name() );
		$this->assertIsObject( $active_api );
		$this->assertInstanceOf( '\easyLanguage\Apis\Summ_Ai\Summ_Ai', $active_api );
	}

	/**
	 * Test if the returned array is an array.
	 *
	 * @return void
	 */
	public function test_get_available_apis(): void {
		$available_apis = \easyLanguage\Plugin\Apis::get_instance()->get_available_apis();
		$this->assertIsArray( $available_apis );
		$this->assertNotEmpty( $available_apis );
	}
}
