<?php
/**
 * Tests for class easyLanguage\EasyLanguage\Term_Object.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\EasyLanguage\Term_Object.
 */
class Term_Object extends easyLanguageTests {
	/**
	 * The test text.
	 *
	 * @var string
	 */
	private string $test_title = 'Hallo Welt';

	/**
	 * The test text.
	 *
	 * @var string
	 */
	private string $test_text = 'Das ist ein Beispiel Text.';

	/**
	 * The test object.
	 *
	 * @var \easyLanguage\EasyLanguage\Term_Object
	 */
	private \easyLanguage\EasyLanguage\Term_Object $object;

	/**
	 * The ID of the example term.
	 *
	 * @var int
	 */
	private int $term_id;

	/**
	 * Prepare the test environment for this object.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// create an example original term.
		$this->term_id = self::factory()->term->create( array( 'name' => $this->test_title, 'taxonomy' => 'category' ) );

		// get the object.
		$this->object = new \easyLanguage\EasyLanguage\Term_Object( $this->term_id, 'category' );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_language(): void {
		$language = $this->object->get_language();
		$this->assertIsArray( $language );
		$this->assertNotEmpty( $language );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_type(): void {
		$type = $this->object->get_type();
		$this->assertIsString( $type );
		$this->assertNotEmpty( $type );
		$this->assertEquals( 'category', $type );
	}

	/**
	 * Test if the returning variable is an int for an original object.
	 *
	 * @return void
	 */
	public function test_get_original_object_as_int_for_original_object(): void {
		$original_object_as_int = $this->object->get_original_object_as_int();
		$this->assertIsInt( $original_object_as_int );
		$this->assertEquals( 0, $original_object_as_int );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_not_existing_simplification_in_language(): void {
		$simplification_in_language = $this->object->get_simplification_in_language( 'de_LS' );
		$this->assertIsInt( $simplification_in_language );
		$this->assertEquals( 0, $simplification_in_language );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_object_as_object(): void {
		$term = $this->object->get_object_as_object();
		$this->assertIsObject( $term );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertEquals( $this->term_id, $term->term_id );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_title(): void {
		$title = $this->object->get_title();
		$this->assertIsString( $title );
		$this->assertNotEmpty( $title );
		$this->assertEquals( $this->test_title, $title );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_has_changed(): void {
		$has_changed = $this->object->has_changed( 'de_DE' );
		$this->assertIsBool( $has_changed );
		$this->assertFalse( $has_changed );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_check_changed_marker(): void {
		// test 1: get actual value.
		$has_changed = $this->object->has_changed( 'de_DE' );
		$this->assertIsBool( $has_changed );
		$this->assertFalse( $has_changed );

		// test 2: set to "changed".
		$this->object->mark_as_changed_in_language( 'de_DE' );
		$has_changed = $this->object->has_changed( 'de_DE' );
		$this->assertIsBool( $has_changed );
		$this->assertTrue( $has_changed );

		// test 3: remove the marker.
		$this->object->remove_changed_marker( 'de_DE' );
		$has_changed = $this->object->has_changed( 'de_DE' );
		$this->assertIsBool( $has_changed );
		$this->assertFalse( $has_changed );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_page_builder(): void {
		$page_builder = $this->object->get_page_builder();
		$this->assertIsBool( $page_builder );
		$this->assertFalse( $page_builder );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_status(): void {
		$status = $this->object->get_status();
		$this->assertIsString( $status );
		$this->assertNotEmpty( $status );
		$this->assertEquals( 'publish', $status );
	}

	/**
	 * Test if the returning variable is a boolean.
	 *
	 * @return void
	 */
	public function test_has_simplifications(): void {
		$has_simplifications = $this->object->has_simplifications();
		$this->assertIsBool( $has_simplifications );
		$this->assertFalse( $has_simplifications );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_is_locked(): void {
		$locked = $this->object->is_locked();
		$this->assertIsBool( $locked );
		$this->assertFalse( $locked );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_is_automatic_mode_prevented(): void {
		$is_automatic_mode_prevented = $this->object->is_automatic_mode_prevented();
		$this->assertIsBool( $is_automatic_mode_prevented );
		$this->assertFalse( $is_automatic_mode_prevented );
	}
}
