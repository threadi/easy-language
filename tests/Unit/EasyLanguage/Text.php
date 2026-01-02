<?php
/**
 * Tests for class easyLanguage\EasyLanguage\Text.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\EasyLanguage\Text.
 */
class Text extends easyLanguageTests {
	/**
	 * The test source language.
	 *
	 * @var string
	 */
	private string $test_source_language = 'de_DE';

	/**
	 * The test text.
	 *
	 * @var string
	 */
	private string $test_text = 'Das ist ein Beispiel Text.';

	/**
	 * The simplified test text.
	 *
	 * @var string
	 */
	private string $test_simplified_text = 'Das ist ein vereinfachter Beispiel Text.';

	/**
	 * The test source language.
	 *
	 * @var string
	 */
	private string $test_target_language = 'de_LS';

	/**
	 * The ID of the example post.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * The test Text object.
	 *
	 * @var \easyLanguage\EasyLanguage\Text
	 */
	private \easyLanguage\EasyLanguage\Text $object;

	/**
	 * Prepare the test environment for this object.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// create a text example.
		$test_field = 'title';
		$html_marker = false;

		// create an example original post.
		$this->post_id = self::factory()->post->create( array( 'post_content' => $this->test_text, 'post_title' => $this->test_text, 'post_type' => 'post' ) );

		// add the test entry.
		$this->object = \easyLanguage\EasyLanguage\Db::get_instance()->add( $this->test_text, $this->test_source_language, $test_field, $html_marker );
		$this->object->set_object( 'post', $this->post_id, 1, 'gutenberg' );

		// set simplication.
		$this->object->set_simplification( $this->test_simplified_text, $this->test_target_language, 'summ_ai', 1 );
	}

	/**
	 * Test if the returning variable is an integer.
	 *
	 * @return void
	 */
	public function test_get_id(): void {
		$id = $this->object->get_id();
		$this->assertIsInt( $id );
		$this->assertEquals( $this->object->get_id(), $id );
	}

	/**
	 * Test if the returning variable is an integer.
	 *
	 * @return void
	 */
	public function test_get_original(): void {
		$original = $this->object->get_original();
		$this->assertIsString( $original );
		$this->assertNotEmpty( $original );
		$this->assertEquals( $this->test_text, $original );
	}

	/**
	 * Test if the returning variable is an integer.
	 *
	 * @return void
	 */
	public function test_get_source_language(): void {
		$source_language = $this->object->get_source_language();
		$this->assertIsString( $source_language );
		$this->assertNotEmpty( $source_language );
		$this->assertEquals( $this->test_source_language, $source_language );
	}

	/**
	 * Test if the returning variable is an integer.
	 *
	 * @return void
	 */
	public function test_get_simplification(): void {
		$simplification = $this->object->get_simplification( $this->test_target_language );
		$this->assertIsString( $simplification );
		$this->assertNotEmpty( $simplification );
		$this->assertEquals( $this->test_simplified_text, wp_strip_all_tags( $simplification ) );
	}

	/**
	 * Test if the returning variable is an integer.
	 *
	 * @return void
	 */
	public function test_has_simplification_in_language(): void {
		$has_simplification_in_language = $this->object->has_simplification_in_language( $this->test_target_language );
		$this->assertIsBool( $has_simplification_in_language );
		$this->assertTrue( $has_simplification_in_language );
	}

	/**
	 * Test if delete will be successful.
	 *
	 * @return void
	 */
	public function test_delete(): void {
		$this->object->delete();
		$this->assertEmpty( \easyLanguage\EasyLanguage\Db::get_instance()->get_entries( array( 'object_id' => $this->object->get_id() ) ) );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_empty_objects(): void {
		// delete the object.
		$this->object->delete( $this->post_id );

		// test it.
		$objects = $this->object->get_objects();
		$this->assertIsArray( $objects );
		$this->assertEmpty( $objects );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_set_object(): void {
		$objects = $this->object->get_objects();
		$this->assertIsArray( $objects );
		$this->assertNotEmpty( $objects );
		$this->assertEquals( $this->post_id, $objects[0]['object_id'] );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_date(): void {
		$date = $this->object->get_date();
		$this->assertIsString( $date );
		$this->assertNotEmpty( $date );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_is_not_this_field(): void {
		$is_field = $this->object->is_field( 'not_existing_example' );
		$this->assertIsBool( $is_field );
		$this->assertFalse( $is_field );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_is_field(): void {
		$is_field = $this->object->is_field( 'title' );
		$this->assertIsBool( $is_field );
		$this->assertTrue( $is_field );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_target_languages(): void {
		$target_languages = $this->object->get_target_languages();
		$this->assertIsArray( $target_languages );
		$this->assertEmpty( $target_languages );
	}

	/**
	 * Test if the returning variable is a boolean.
	 *
	 * @return void
	 */
	public function test_is_html(): void {
		$is_html = $this->object->is_html();
		$this->assertIsBool( $is_html );
		$this->assertFalse( $is_html );
	}
}
