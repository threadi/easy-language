<?php
/**
 * Tests for class easyLanguage\EasyLanguage\Post_Object.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test functions in class easyLanguage\EasyLanguage\Post_Object.
 */
class Post_Object extends easyLanguageTests {
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
	 * @var \easyLanguage\EasyLanguage\Post_Object
	 */
	private \easyLanguage\EasyLanguage\Post_Object $object;

	/**
	 * The ID of the example post.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Prepare the test environment for this object.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// create an example original post.
		$this->post_id = self::factory()->post->create( array( 'post_content' => $this->test_text, 'post_title' => $this->test_title, 'post_type' => 'post' ) );

		// get the object.
		$this->object = new \easyLanguage\EasyLanguage\Post_Object( $this->post_id );
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
		$this->assertEquals( 'post', $type );
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
	public function test_get_simplifications(): void {
		$simplifications = $this->object->get_simplifications();
		$this->assertIsArray( $simplifications );
		$this->assertEmpty( $simplifications );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_object_as_array(): void {
		$post = $this->object->get_object_as_array();
		$this->assertIsArray( $post );
		$this->assertNotEmpty( $post );
		$this->assertEquals( $this->post_id, $post['ID'] );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_object_as_object(): void {
		$post = $this->object->get_object_as_object();
		$this->assertIsObject( $post );
		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertEquals( $this->post_id, $post->ID );
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
	public function test_get_content(): void {
		$content = $this->object->get_content();
		$this->assertIsString( $content );
		$this->assertNotEmpty( $content );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_language_specific_url(): void {
		$url = $this->object->get_language_specific_url( 'de_DE', 'de_DE' );
		$this->assertIsString( $url );
		$this->assertNotEmpty( $url );
		$this->assertEquals( get_permalink( $this->post_id ), $url );
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
		$this->assertIsObject( $page_builder );
		$this->assertInstanceOf( \easyLanguage\Parser\Undetected::class, $page_builder );
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
	public function test_get_entries(): void {
		$entries = $this->object->get_entries();
		$this->assertIsArray( $entries );
		$this->assertEmpty( $entries );
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

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_automatic_mode_prevented(): void {
		// test 1: get actual value.
		$is_automatic_mode_prevented = $this->object->is_automatic_mode_prevented();
		$this->assertIsBool( $is_automatic_mode_prevented );
		$this->assertFalse( $is_automatic_mode_prevented );

		// test 2: set to prevent.
		$this->object->set_automatic_mode_prevented( true );
		$is_automatic_mode_prevented = $this->object->is_automatic_mode_prevented();
		$this->assertIsBool( $is_automatic_mode_prevented );
		$this->assertTrue( $is_automatic_mode_prevented );
	}
}
