<?php
/**
 * Tests for the parser for Divi 5.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for Divi 5, which stores its content as blocks with the content in their attributes.
 */
class Divi5 extends easyLanguageTests {
	/**
	 * Return content like Divi 5 saves it: blocks, the texts are in the attributes (with escaped HTML).
	 *
	 * @param string $first_text  The text of the first text module.
	 * @param string $second_text The text of the second text module.
	 *
	 * @return string
	 */
	private function get_content( string $first_text = '<p>Ein komplizierter Text.</p>', string $second_text = '<p>Ein anderer Text.</p>' ): string {
		$text_block = static function ( string $text ): string {
			return serialize_block(
				array(
					'blockName'    => 'divi/text',
					'attrs'        => array(
						'content' => array(
							'innerContent' => array(
								'desktop' => array(
									'value' => $text,
								),
							),
						),
						'builderVersion' => '5.0.0',
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			);
		};

		return '<!-- wp:divi/placeholder --><!-- wp:divi/section {"builderVersion":"5.0.0"} --><!-- wp:divi/row {"builderVersion":"5.0.0"} --><!-- wp:divi/column {"builderVersion":"5.0.0"} -->'
			. $text_block( $first_text )
			. $text_block( $second_text )
			. '<!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';
	}

	/**
	 * Return the parser prepared for the given text.
	 *
	 * @param string $text The text to parse.
	 *
	 * @return \easyLanguage\Parser\Divi5
	 */
	private function get_parser( string $text ): \easyLanguage\Parser\Divi5 {
		$parser = \easyLanguage\Parser\Divi5::get_instance();
		$parser->set_object_id( self::factory()->post->create( array( 'post_content' => wp_slash( $this->get_content() ) ) ) );
		$parser->set_text( $text );
		return $parser;
	}

	/**
	 * Divi 5 is not active in the test environment.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$this->assertFalse( \easyLanguage\Parser\Divi5::get_instance()->is_active() );
	}

	/**
	 * The texts of the nested text modules are found in their attributes.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		$texts = $this->get_parser( $this->get_content() )->get_parsed_texts();

		$this->assertSame( array( '<p>Ein komplizierter Text.</p>', '<p>Ein anderer Text.</p>' ), array_column( $texts, 'text' ) );
		$this->assertSame( array( true, true ), array_column( $texts, 'html' ) );
	}

	/**
	 * The simplification replaces the text in the attribute of its module, the other module stays unchanged.
	 *
	 * @return void
	 */
	public function test_replacement(): void {
		$parser = $this->get_parser( '<p>Ein komplizierter Text.</p>' );

		$result = $parser->get_text_with_simplifications( $this->get_content(), '<p>Ein "einfacher" Text & mehr.</p>' );

		// the result is the same as content saved with the simplified text.
		$this->assertSame( $this->get_content( '<p>Ein "einfacher" Text & mehr.</p>' ), $result );

		// the blocks are still valid.
		$parser->set_text( $result );
		$this->assertSame( array( '<p>Ein "einfacher" Text & mehr.</p>', '<p>Ein anderer Text.</p>' ), array_column( $parser->get_parsed_texts(), 'text' ) );
	}

	/**
	 * Other blocks than the ones of Divi 5 are not changed.
	 *
	 * @return void
	 */
	public function test_other_blocks_are_unchanged(): void {
		$paragraph = '<!-- wp:paragraph --><p>Ein komplizierter Text.</p><!-- /wp:paragraph -->';
		$content   = $paragraph . $this->get_content();
		$parser    = $this->get_parser( '<p>Ein komplizierter Text.</p>' );

		$result = $parser->get_text_with_simplifications( $content, '<p>Einfach.</p>' );

		$this->assertStringStartsWith( $paragraph, $result );
	}

	/**
	 * The heading of Divi 5 is found in its attributes like the text module.
	 *
	 * @return void
	 */
	public function test_heading_in_attributes(): void {
		// Divi 5 saves all modules as self-closing blocks with their content in the attributes.
		$content = '<!-- wp:divi/heading {"title":{"innerContent":{"desktop":{"value":"Unsere Leistungen"}}},"builderVersion":"5.0.0"} /-->';

		$texts = $this->get_parser( $content )->get_parsed_texts();

		$this->assertSame( array( 'Unsere Leistungen' ), array_column( $texts, 'text' ) );
	}

	/**
	 * The text of a Divi 5 heading is replaced in its attributes, other attributes stay unchanged.
	 *
	 * @return void
	 */
	public function test_heading_replaced_in_attributes(): void {
		$content = '<!-- wp:divi/heading {"title":{"innerContent":{"desktop":{"value":"Unsere Leistungen"}}},"builderVersion":"5.0.0"} /-->';
		$parser  = $this->get_parser( 'Unsere Leistungen' );

		$blocks = parse_blocks( $parser->get_text_with_simplifications( $content, 'Was wir machen' ) );

		$this->assertSame( 'Was wir machen', $blocks[0]['attrs']['title']['innerContent']['desktop']['value'] );
		$this->assertSame( '5.0.0', $blocks[0]['attrs']['builderVersion'] );
	}

	/**
	 * The text of a Divi 5 button is found and replaced in its attributes, the link stays unchanged.
	 *
	 * @return void
	 */
	public function test_button_in_attributes(): void {
		$content = '<!-- wp:divi/button {"button":{"innerContent":{"desktop":{"value":{"text":"Jetzt anfragen","linkUrl":"https://example.org/kontakt/"}}}}} /-->';

		$texts = $this->get_parser( $content )->get_parsed_texts();
		$this->assertSame( array( 'Jetzt anfragen' ), array_column( $texts, 'text' ) );

		$blocks = parse_blocks( $this->get_parser( 'Jetzt anfragen' )->get_text_with_simplifications( $content, 'Fragen Sie uns' ) );
		$this->assertSame( 'Fragen Sie uns', $blocks[0]['attrs']['button']['innerContent']['desktop']['value']['text'] );
		$this->assertSame( 'https://example.org/kontakt/', $blocks[0]['attrs']['button']['innerContent']['desktop']['value']['linkUrl'] );
	}

	/**
	 * An object uses Divi 5 if its content contains one of the supported blocks.
	 *
	 * @return void
	 */
	public function test_object_using_pagebuilder(): void {
		$parser = \easyLanguage\Parser\Divi5::get_instance();

		$divi = new Post_Object( self::factory()->post->create( array( 'post_content' => wp_slash( $this->get_content() ) ) ) );
		$this->assertTrue( $parser->is_object_using_pagebuilder( $divi ) );

		$gutenberg = new Post_Object( self::factory()->post->create( array( 'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->' ) ) );
		$this->assertFalse( $parser->is_object_using_pagebuilder( $gutenberg ) );

		$classic = new Post_Object( self::factory()->post->create( array( 'post_content' => '<p>Text</p>' ) ) );
		$this->assertFalse( $parser->is_object_using_pagebuilder( $classic ) );
	}
}
