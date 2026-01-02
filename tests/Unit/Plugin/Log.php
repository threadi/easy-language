<?php
/**
 * Tests for class easyLanguage\Plugin\Log.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in the class easyLanguage\Plugin\Log.
 */
class Log extends easyLanguageTests {

	/**
	 * Test to add a new log entry in the database.
	 *
	 * @return void
	 */
	public function test_add_success_log(): void {
		// create a text example.
		$test_text = 'This is a test text.';

		// enable debug mode.
		update_option( 'easy_language_debug_mode', 1 );

		// add the entry.
		\easyLanguage\Plugin\Log::get_instance()->add_log( $test_text, 'success' );

		// get the entry.
		$entries = \easyLanguage\Plugin\Log::get_instance()->get_entries();
		$found = false;
		foreach( $entries as $entry ) {
			if( $test_text === $entry['log'] ) {
				$found = true;
			}
		}

		$this->assertTrue( $found );
	}

	/**
	 * Test to add a new log entry in the database.
	 *
	 * @return void
	 */
	public function test_add_error_log(): void {
		// create a text example.
		$test_text = 'This is a test text.';

		// add the entry.
		\easyLanguage\Plugin\Log::get_instance()->add_log( $test_text, 'success' );

		// get the entry.
		$entries = \easyLanguage\Plugin\Log::get_instance()->get_entries();
		$found = false;
		foreach( $entries as $entry ) {
			if( $test_text === $entry['log'] ) {
				$found = true;
			}
		}

		$this->assertFalse( $found );
	}
}
