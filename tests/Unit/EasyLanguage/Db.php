<?php
/**
 * Tests for class easyLanguage\EasyLanguage\Db.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\EasyLanguage\Db.
 */
class Db extends easyLanguageTests {
	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_add_english_title_text(): void {
		// create a text example.
		$test_text = 'This is a test text.';
		$test_source_language = 'en_US';
		$test_field = 'title';
		$html_marker = false;

		// test it.
		$text = \easyLanguage\EasyLanguage\Db::get_instance()->add( $test_text, $test_source_language, $test_field, $html_marker );
		$this->assertIsObject( $text );
		$this->assertInstanceOf( '\easyLanguage\EasyLanguage\Text', $text );
		$this->assertEquals( $test_text, $text->get_original() );
		$this->assertEquals( $test_source_language, $text->get_source_language() );
	}

	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_add_english_flow_text(): void {
		// create a text example.
		$test_text = 'This is a test text.';
		$test_source_language = 'en_US';
		$test_field = 'post_content';
		$html_marker = false;

		// test it.
		$text = \easyLanguage\EasyLanguage\Db::get_instance()->add( $test_text, $test_source_language, $test_field, $html_marker );
		$this->assertIsObject( $text );
		$this->assertInstanceOf( '\easyLanguage\EasyLanguage\Text', $text );
		$this->assertEquals( $test_text, $text->get_original() );
		$this->assertEquals( $test_source_language, $text->get_source_language() );
	}

	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_add_german_title_text(): void {
		// create a text example.
		$test_text = 'Das ist ein Beispiel Text.';
		$test_source_language = 'de_DE';
		$test_field = 'title';
		$html_marker = false;

		// test it.
		$text = \easyLanguage\EasyLanguage\Db::get_instance()->add( $test_text, $test_source_language, $test_field, $html_marker );
		$this->assertIsObject( $text );
		$this->assertInstanceOf( '\easyLanguage\EasyLanguage\Text', $text );
		$this->assertEquals( $test_text, $text->get_original() );
		$this->assertEquals( $test_source_language, $text->get_source_language() );
	}

	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_add_german_flow_text(): void {
		// create a text example.
		$test_text = 'Das ist ein Beispiel Text.';
		$test_source_language = 'de_DE';
		$test_field = 'post_content';
		$html_marker = false;

		// test it.
		$text = \easyLanguage\EasyLanguage\Db::get_instance()->add( $test_text, $test_source_language, $test_field, $html_marker );
		$this->assertIsObject( $text );
		$this->assertInstanceOf( '\easyLanguage\EasyLanguage\Text', $text );
		$this->assertEquals( $test_text, $text->get_original() );
		$this->assertEquals( $test_source_language, $text->get_source_language() );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_empty_entries_without_filter(): void {
		$entries = \easyLanguage\EasyLanguage\Db::get_instance()->get_entries();
		$this->assertIsArray( $entries );
		$this->assertEmpty( $entries );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_entries_without_filter(): void {
		// create a text example.
		$test_text = 'Das ist ein Beispiel Text.';
		$test_source_language = 'de_DE';
		$test_field = 'post_content';
		$html_marker = false;

		// add an entry.
		\easyLanguage\EasyLanguage\Db::get_instance()->add( $test_text, $test_source_language, $test_field, $html_marker );

		// test it.
		$entries = \easyLanguage\EasyLanguage\Db::get_instance()->get_entries();
		$this->assertIsArray( $entries );
		$this->assertNotEmpty( $entries );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_entries_with_filter(): void {
		// create a text example.
		$test_text = 'Das ist ein Beispiel Text.';
		$test_source_language = 'de_DE';
		$test_field = 'post_content';
		$html_marker = false;

		// add an entry.
		$text = \easyLanguage\EasyLanguage\Db::get_instance()->add( $test_text, $test_source_language, $test_field, $html_marker );

		// test it.
		$entries = \easyLanguage\EasyLanguage\Db::get_instance()->get_entries( array( 'id' => $text->get_id() ) );
		$this->assertIsArray( $entries );
		$this->assertNotEmpty( $entries );
		$this->assertEquals( $text->get_id(), $entries[0]->get_id() );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_entry_by_text(): void {
		// create a text example.
		$test_text = 'Das ist ein Beispiel Text.';
		$test_source_language = 'de_DE';
		$test_field = 'post_content';
		$html_marker = false;

		// add an entry.
		$test_text_obj = \easyLanguage\EasyLanguage\Db::get_instance()->add( $test_text, $test_source_language, $test_field, $html_marker );

		// test it.
		$text_obj = \easyLanguage\EasyLanguage\Db::get_instance()->get_entry_by_text( $test_text, $test_source_language );
		$this->assertIsObject( $text_obj );
		$this->assertInstanceOf( '\easyLanguage\EasyLanguage\Text', $text_obj );
		$this->assertEquals( $test_text_obj->get_id(), $text_obj->get_id() );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_entry_by_simplification(): void {
		// create a text example.
		$test_text = 'Das ist ein Beispiel Text.';
		$simplified_test_text = 'Das ist ein vereinfachter Beispiel Text.';
		$test_source_language = 'de_DE';
		$test_target_language = 'de_LS';
		$test_field = 'post_content';
		$html_marker = false;

		// add an entry.
		$test_text_obj = \easyLanguage\EasyLanguage\Db::get_instance()->add( $test_text, $test_source_language, $test_field, $html_marker );

		// set its simplification.
		$test_text_obj->set_simplification( $simplified_test_text, $test_target_language, \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_name(), 1 );

		// test it.
		$text_obj = \easyLanguage\EasyLanguage\Db::get_instance()->get_entry_by_simplification( $simplified_test_text, $test_target_language );
		$this->assertIsObject( $text_obj );
		$this->assertInstanceOf( '\easyLanguage\EasyLanguage\Text', $text_obj );
		$this->assertEquals( $test_text_obj->get_id(), $text_obj->get_id() );
	}
}
