<?php
/**
 * Tests for the parser for Divi 4.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for Divi 4, which stores its content as shortcodes in post_content.
 *
 * Hint: whether Divi 4 is active is checked once per request and cached in a static variable
 * (\easyLanguage\PageBuilder\Divi::is_active()). This happens during the initialization of the plugin in the
 * test environment, where Divi is not available. That is why only the behavior without Divi could be tested here.
 */
class Divi extends easyLanguageTests {
	/**
	 * Content like Divi 4 saves it.
	 *
	 * @var string
	 */
	private string $content = '[et_pb_section fb_built="1" _builder_version="4.27.0"][et_pb_row _builder_version="4.27.0"][et_pb_column type="4_4"][et_pb_text _builder_version="4.27.0"]<p>Ein komplizierter Text.</p>[/et_pb_text][et_pb_button button_text="Jetzt anfragen" button_url="#"][/et_pb_button][/et_pb_column][/et_pb_row][/et_pb_section]';

	/**
	 * Return the parser prepared for the given text.
	 *
	 * @param string $text The text to parse.
	 *
	 * @return \easyLanguage\Parser\Divi
	 */
	private function get_parser( string $text ): \easyLanguage\Parser\Divi {
		$parser = \easyLanguage\Parser\Divi::get_instance();
		$parser->set_object_id( self::factory()->post->create( array( 'post_content' => $this->content ) ) );
		$parser->set_text( $text );
		return $parser;
	}

	/**
	 * Without Divi 4 nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$parser = $this->get_parser( $this->content );

		$this->assertFalse( $parser->is_active() );
		$this->assertSame( array(), $parser->get_parsed_texts() );

		$parser->set_text( '<p>Ein komplizierter Text.</p>' );
		$this->assertSame( $this->content, $parser->get_text_with_simplifications( $this->content, '<p>Einfach.</p>' ) );
	}

	/**
	 * Without Divi the edit link is the one of WordPress and the translate menu is not hidden.
	 *
	 * @return void
	 */
	public function test_edit_link_and_menu_without_divi(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$parser = $this->get_parser( $this->content );

		$this->assertSame( get_edit_post_link( $parser->get_object_id(), 'edit' ), $parser->get_edit_link() );
		$this->assertFalse( $parser->hide_translate_menu_in_frontend() );
	}

	/**
	 * An object uses Divi if it has been built for its post type.
	 *
	 * @return void
	 */
	public function test_object_using_pagebuilder(): void {
		$post_id = self::factory()->post->create( array( 'post_content' => $this->content ) );
		$this->assertFalse( \easyLanguage\Parser\Divi::get_instance()->is_object_using_pagebuilder( new Post_Object( $post_id ) ) );

		// built for another post type.
		update_post_meta( $post_id, '_et_pb_built_for_post_type', 'page' );
		$this->assertFalse( \easyLanguage\Parser\Divi::get_instance()->is_object_using_pagebuilder( new Post_Object( $post_id ) ) );

		// built for its post type.
		update_post_meta( $post_id, '_et_pb_built_for_post_type', 'post' );
		$this->assertTrue( \easyLanguage\Parser\Divi::get_instance()->is_object_using_pagebuilder( new Post_Object( $post_id ) ) );
	}
}
