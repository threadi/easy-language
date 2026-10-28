<?php
/**
 * Tests for the parser for Avia (Enfold).
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;
use easyLanguage\Tests\Fakes\Parser\Theme_Switcher;

/**
 * Object to test the parser for Avia, the builder of the theme Enfold, which stores its content as shortcodes.
 */
class Avia extends easyLanguageTests {
	/**
	 * Content like the Avia Layout Builder saves it.
	 *
	 * @var string
	 */
	private string $content = "[av_section min_height='' padding='default'][av_heading heading='Willkommen bei uns' tag='h2' style='blockquote modern-quote' size=''][/av_heading][av_textblock size='' font_color='']<p>Hier steht ein komplizierter Text.</p>[/av_textblock][av_textblock size='' font_color='']<p>Noch ein Text.</p>[/av_textblock][/av_section]";

	/**
	 * Return the parser prepared for the given text.
	 *
	 * @param string $text The text to parse.
	 *
	 * @return \easyLanguage\Parser\Avia
	 */
	private function get_parser( string $text ): \easyLanguage\Parser\Avia {
		$parser = \easyLanguage\Parser\Avia::get_instance();
		$parser->set_object_id( self::factory()->post->create( array( 'post_content' => $this->content ) ) );
		$parser->set_text( $text );
		return $parser;
	}

	/**
	 * Without Enfold nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$parser = $this->get_parser( $this->content );

		$this->assertFalse( $parser->is_active() );
		$this->assertSame( array(), $parser->get_parsed_texts() );

		$parser->set_text( '<p>Noch ein Text.</p>' );
		$this->assertSame( $this->content, $parser->get_text_with_simplifications( $this->content, '<p>Einfach.</p>' ) );
	}

	/**
	 * The heading attribute and the content of text blocks are found.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		Theme_Switcher::activate( 'enfold' );

		$this->assertSame(
			array(
				array(
					'text' => 'Willkommen bei uns',
					'html' => false,
				),
				array(
					'text' => '<p>Hier steht ein komplizierter Text.</p>',
					'html' => true,
				),
				array(
					'text' => '<p>Noch ein Text.</p>',
					'html' => true,
				),
			),
			$this->get_parser( $this->content )->get_parsed_texts()
		);
	}

	/**
	 * The content of a text block is replaced, all other shortcodes stay unchanged.
	 *
	 * @return void
	 */
	public function test_replacement_of_text_block(): void {
		Theme_Switcher::activate( 'enfold' );
		$parser = $this->get_parser( '<p>Hier steht ein komplizierter Text.</p>' );

		$result = $parser->get_text_with_simplifications( $this->content, '<p>Hier steht ein einfacher Text.</p>' );

		$this->assertSame( str_replace( '<p>Hier steht ein komplizierter Text.</p>', '<p>Hier steht ein einfacher Text.</p>', $this->content ), $result );
	}

	/**
	 * The heading attribute is replaced and quotes in the simplification do not break the shortcode.
	 *
	 * @return void
	 */
	public function test_replacement_of_heading_attribute(): void {
		Theme_Switcher::activate( 'enfold' );
		$parser = $this->get_parser( 'Willkommen bei uns' );

		$result = $parser->get_text_with_simplifications( $this->content, "Hallo, schön, dass Sie da sind. Wir sind's [Team]" );

		// the quote and the brackets are masked, the other attributes are unchanged.
		$this->assertStringContainsString( "[av_heading heading='Hallo, schön, dass Sie da sind. Wir sind&#039;s &#91;Team&#93;' tag='h2' style='blockquote modern-quote' size=''][/av_heading]", $result );

		// the shortcode could still be parsed: it returns the decoded value of the attribute.
		$parser->set_text( $result );
		$texts = array_column( $parser->get_parsed_texts(), 'text' );
		$this->assertCount( 3, $texts );
		$this->assertSame( '<p>Noch ein Text.</p>', $texts[2] );
	}

	/**
	 * An object uses Avia if its layout builder is active.
	 *
	 * @return void
	 */
	public function test_object_using_pagebuilder(): void {
		$post_id = self::factory()->post->create( array( 'post_content' => $this->content ) );
		$this->assertFalse( \easyLanguage\Parser\Avia::get_instance()->is_object_using_pagebuilder( new Post_Object( $post_id ) ) );

		update_post_meta( $post_id, '_aviaLayoutBuilder_active', 'active' );
		$this->assertTrue( \easyLanguage\Parser\Avia::get_instance()->is_object_using_pagebuilder( new Post_Object( $post_id ) ) );
	}
}
