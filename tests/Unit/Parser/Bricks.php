<?php
/**
 * Tests for the parser for Bricks.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;
use easyLanguage\Tests\Fakes\Parser\Theme_Switcher;

/**
 * Object to test the parser for Bricks, which stores its elements as flat list in post meta.
 */
class Bricks extends easyLanguageTests {
	/**
	 * Define the constant of Bricks for the meta key of the page content.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		if ( ! defined( 'BRICKS_DB_PAGE_CONTENT' ) ) {
			define( 'BRICKS_DB_PAGE_CONTENT', '_bricks_page_content_2' );
		}
	}

	/**
	 * Return the elements like Bricks saves them: a flat list, children are referenced by their IDs.
	 *
	 * @param string $text The text of the rich text element.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_elements( string $text = '<p>Ein komplizierter Text.</p>' ): array {
		return array(
			array(
				'id'       => 'abc001',
				'name'     => 'section',
				'parent'   => 0,
				'children' => array( 'abc002' ),
				'settings' => array(),
			),
			array(
				'id'       => 'abc002',
				'name'     => 'container',
				'parent'   => 'abc001',
				'children' => array( 'abc003', 'abc004', 'abc005' ),
				'settings' => array(),
			),
			array(
				'id'       => 'abc003',
				'name'     => 'text-basic',
				'parent'   => 'abc002',
				'children' => array(),
				'settings' => array(
					'text' => 'Unsere Leistungen',
					'tag'  => 'h2',
				),
			),
			array(
				'id'       => 'abc004',
				'name'     => 'text',
				'parent'   => 'abc002',
				'children' => array(),
				'settings' => array( 'text' => $text ),
			),
			array(
				'id'       => 'abc005',
				'name'     => 'button',
				'parent'   => 'abc002',
				'children' => array(),
				'settings' => array( 'text' => '<p>Ein komplizierter Text.</p>' ),
			),
		);
	}

	/**
	 * Return the parser for a new post with Bricks elements.
	 *
	 * @param string $text The text to parse.
	 *
	 * @return \easyLanguage\Parser\Bricks
	 */
	private function get_parser( string $text ): \easyLanguage\Parser\Bricks {
		$post_id = self::factory()->post->create( array( 'post_content' => '<p>Ein komplizierter Text.</p>' ) );
		update_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, $this->get_elements() );

		$parser = \easyLanguage\Parser\Bricks::get_instance();
		$parser->set_object_id( $post_id );
		$parser->set_text( $text );
		return $parser;
	}

	/**
	 * Without the theme Bricks nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$parser = $this->get_parser( '<p>Ein komplizierter Text.</p>' );

		$this->assertFalse( $parser->is_active() );
		$this->assertSame( array(), $parser->get_parsed_texts() );
		$this->assertSame( 'Inhalt', $parser->get_text_with_simplifications( 'Inhalt', 'Einfach' ) );
		$this->assertSame( $this->get_elements(), get_post_meta( $parser->get_object_id(), BRICKS_DB_PAGE_CONTENT, true ) );
	}

	/**
	 * The texts of the supported elements are found.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		Theme_Switcher::activate( 'bricks' );

		$texts = $this->get_parser( '' )->get_parsed_texts();

		$this->assertSame( array( 'Unsere Leistungen', '<p>Ein komplizierter Text.</p>' ), array_column( $texts, 'text' ) );

		// the rich text element contains HTML.
		$this->assertTrue( $texts[1]['html'] );
	}

	/**
	 * The basic text element of Bricks contains no HTML.
	 *
	 * @return void
	 */
	public function test_basic_text_is_not_html(): void {
		Theme_Switcher::activate( 'bricks' );

		$texts = $this->get_parser( '' )->get_parsed_texts();

		$this->assertFalse( $texts[0]['html'] );
	}

	/**
	 * The simplification is saved in the elements with the text, other elements stay unchanged.
	 *
	 * @return void
	 */
	public function test_replacement(): void {
		Theme_Switcher::activate( 'bricks' );
		$parser     = $this->get_parser( '<p>Ein komplizierter Text.</p>' );
		$simplified = '<p>Ein "einfacher" Text mit \\ Backslash.</p>';

		$this->assertSame( $simplified, $parser->get_text_with_simplifications( '<p>Ein komplizierter Text.</p>', $simplified ) );
		$this->assertSame( $this->get_elements( $simplified ), get_post_meta( $parser->get_object_id(), BRICKS_DB_PAGE_CONTENT, true ) );
	}

	/**
	 * An object uses Bricks if it has Bricks content.
	 *
	 * @return void
	 */
	public function test_object_using_pagebuilder(): void {
		$this->assertFalse( \easyLanguage\Parser\Bricks::get_instance()->is_object_using_pagebuilder( new Post_Object( self::factory()->post->create() ) ) );

		$parser = $this->get_parser( '' );
		$this->assertTrue( $parser->is_object_using_pagebuilder( new Post_Object( $parser->get_object_id() ) ) );
	}

	/**
	 * The edit link opens the Bricks editor.
	 *
	 * @return void
	 */
	public function test_edit_link(): void {
		Theme_Switcher::activate( 'bricks' );
		$parser = $this->get_parser( '' );

		$this->assertSame( add_query_arg( array( 'bricks' => 'run' ), get_permalink( $parser->get_object_id() ) ), $parser->get_edit_link() );
	}
}
