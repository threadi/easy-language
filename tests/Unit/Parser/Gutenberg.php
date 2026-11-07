<?php
/**
 * Tests for class easyLanguage\Parser\Gutenberg.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for the block editor.
 */
class Gutenberg extends easyLanguageTests {
	/**
	 * Content as the block editor saves it: with line breaks between the blocks (freeform parts without block name),
	 * nested blocks and blocks without text.
	 */
	private const CONTENT = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Eine Überschrift</h2>\n<!-- /wp:heading -->\n\n"
		. "<!-- wp:paragraph -->\n<p>Ein erster Absatz.</p>\n<!-- /wp:paragraph -->\n\n"
		. "<!-- wp:group -->\n<div class=\"wp-block-group\"><!-- wp:paragraph -->\n<p>Ein Absatz in einer Gruppe.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n"
		. "<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li>Erster Punkt</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>Zweiter Punkt</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n\n"
		. "<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"bild.jpg\" alt=\"Ein Bild\"/></figure>\n<!-- /wp:image -->\n\n"
		. "<!-- wp:separator -->\n<hr class=\"wp-block-separator\"/>\n<!-- /wp:separator -->";

	/**
	 * The parser.
	 *
	 * @var \easyLanguage\Parser\Gutenberg
	 */
	private \easyLanguage\Parser\Gutenberg $parser;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->parser = \easyLanguage\Parser\Gutenberg::get_instance();
		$this->parser->set_text( self::CONTENT );
		$this->parser->set_object_id( 0 );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'easy_language_gutenberg_blocks' );
		parent::tear_down();
	}

	/**
	 * The parser for the block editor is always active.
	 *
	 * @return void
	 */
	public function test_is_active(): void {
		$this->assertTrue( $this->parser->is_active() );
	}

	/**
	 * The texts of paragraphs, headings and list items are found, also inside of other blocks. Blocks without text
	 * and the parts between the blocks are ignored.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		$this->assertSame(
			array(
				'<h2 class="wp-block-heading">Eine Überschrift</h2>',
				'<p>Ein erster Absatz.</p>',
				'<p>Ein Absatz in einer Gruppe.</p>',
				'<li>Erster Punkt</li>',
				'<li>Zweiter Punkt</li>',
			),
			array_column( $this->parser->get_parsed_texts(), 'text' )
		);

		// all of them contain HTML.
		$this->assertSame( array( true ), array_values( array_unique( array_column( $this->parser->get_parsed_texts(), 'html' ) ) ) );
	}

	/**
	 * Content without blocks (e.g. of the classic editor) returns no texts and no errors.
	 *
	 * @return void
	 */
	public function test_parsed_texts_without_blocks(): void {
		$this->parser->set_text( "<p>Klassischer Inhalt</p>\n\n<p>Zweiter Absatz</p>" );
		$this->assertSame( array(), $this->parser->get_parsed_texts() );

		$this->parser->set_text( '' );
		$this->assertSame( array(), $this->parser->get_parsed_texts() );
	}

	/**
	 * Additional blocks could be supported via filter, also with a callback for their text.
	 *
	 * @return void
	 */
	public function test_parsed_texts_with_filter(): void {
		add_filter(
			'easy_language_gutenberg_blocks',
			static function ( array $blocks ): array {
				$blocks['core/image'] = array(
					'html'     => false,
					'callback' => static function ( string $block_name, array $block ): string {
						return preg_match( '/alt="([^"]*)"/', $block['innerHTML'], $matches ) ? $matches[1] : '';
					},
				);
				return $blocks;
			}
		);

		// the text of the image is added (the entry contains also the settings of the block, e.g. the callback).
		$texts = array_values(
			array_filter(
				$this->parser->get_parsed_texts(),
				static function ( array $entry ): bool {
					return 'Ein Bild' === $entry['text'];
				}
			)
		);
		$this->assertCount( 1, $texts );
		$this->assertFalse( $texts[0]['html'] );
	}

	/**
	 * The text is replaced and the block structure stays intact.
	 *
	 * @return void
	 */
	public function test_replace(): void {
		$this->parser->set_text( '<p>Ein erster Absatz.</p>' );
		$result = $this->parser->get_text_with_simplifications( self::CONTENT, '<p>Ein leichter Absatz.</p>' );

		$this->assertStringContainsString( '<p>Ein leichter Absatz.</p>', $result );
		$this->assertStringNotContainsString( '<p>Ein erster Absatz.</p>', $result );

		// the other blocks are unchanged.
		$this->assertCount( count( parse_blocks( self::CONTENT ) ), parse_blocks( $result ) );
		$this->assertStringContainsString( '<p>Ein Absatz in einer Gruppe.</p>', $result );
	}

	/**
	 * Only objects with blocks are detected as objects of the block editor.
	 *
	 * @return void
	 */
	public function test_is_object_using_pagebuilder(): void {
		$with_blocks    = self::factory()->post->create( array( 'post_content' => self::CONTENT ) );
		$without_blocks = self::factory()->post->create( array( 'post_content' => '<p>Klassisch</p>' ) );

		$this->assertTrue( $this->parser->is_object_using_pagebuilder( new Post_Object( $with_blocks ) ) );
		$this->assertFalse( $this->parser->is_object_using_pagebuilder( new Post_Object( $without_blocks ) ) );
	}
}
