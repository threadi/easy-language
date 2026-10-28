<?php
/**
 * Tests for class easyLanguage\Parser\Kubio.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for Kubio.
 */
class Kubio extends easyLanguageTests {
	/**
	 * The text block of Kubio.
	 */
	private const TEXT_BLOCK = '<p class="h-text">Das ist ein langer Text über Kubio.</p>';

	/**
	 * Content as Kubio saves it in post_content (blocks inside a row and a column).
	 */
	private const CONTENT = '<!-- wp:kubio/section {"kubio":{"id":"s1"}} --><div class="wp-block-kubio-section"><!-- wp:kubio/row --><div class="wp-block-kubio-row"><!-- wp:kubio/column --><div class="wp-block-kubio-column"><!-- wp:kubio/text {"kubio":{"id":"t1"}} -->' . self::TEXT_BLOCK . '<!-- /wp:kubio/text --><!-- wp:kubio/button {"kubio":{"id":"b1"}} --><a class="h-button">Kontakt</a><!-- /wp:kubio/button --></div><!-- /wp:kubio/column --></div><!-- /wp:kubio/row --></div><!-- /wp:kubio/section --><!-- wp:paragraph --><p>Ein normaler Absatz.</p><!-- /wp:paragraph -->';

	/**
	 * The parser.
	 *
	 * @var \easyLanguage\Parser\Kubio
	 */
	private \easyLanguage\Parser\Kubio $parser;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->parser = \easyLanguage\Parser\Kubio::get_instance();
		$this->parser->set_text( self::CONTENT );
		$this->parser->set_object_id( 0 );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins' ) );
		remove_all_filters( 'easy_language_kubio_blocks' );
		parent::tear_down();
	}

	/**
	 * Return the list of active plugins with Kubio.
	 *
	 * @return array<int,string>
	 */
	public function get_active_plugins(): array {
		return array( 'kubio/plugin.php' );
	}

	/**
	 * The parser is only active with the plugin Kubio.
	 *
	 * @return void
	 */
	public function test_is_active(): void {
		$this->assertFalse( $this->parser->is_active() );

		add_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins' ) );
		$this->assertTrue( $this->parser->is_active() );
	}

	/**
	 * The text blocks are found, also inside of other blocks; other blocks are ignored.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		$this->assertSame(
			array(
				array(
					'html' => true,
					'text' => self::TEXT_BLOCK,
				),
			),
			$this->parser->get_parsed_texts()
		);
	}

	/**
	 * Additional blocks could be supported via filter, also with a callback for their text.
	 *
	 * @return void
	 */
	public function test_parsed_texts_with_filter(): void {
		add_filter(
			'easy_language_kubio_blocks',
			static function ( array $blocks ): array {
				$blocks['kubio/button'] = array(
					'html'     => false,
					'callback' => static fn( string $block_name, array $block ): string => wp_strip_all_tags( $block['innerHTML'] ),
				);
				return $blocks;
			}
		);

		$this->assertContains( 'Kontakt', array_column( $this->parser->get_parsed_texts(), 'text' ) );
	}

	/**
	 * The text is replaced and the block structure stays intact.
	 *
	 * @return void
	 */
	public function test_replace(): void {
		$this->parser->set_text( self::TEXT_BLOCK );

		$result = $this->parser->get_text_with_simplifications( self::CONTENT, '<p class="h-text">Ein kurzer Text.</p>' );

		$this->assertSame( str_replace( self::TEXT_BLOCK, '<p class="h-text">Ein kurzer Text.</p>', self::CONTENT ), $result );

		// the blocks could still be parsed and contain the simplified text.
		$this->parser->set_text( $result );
		$this->assertSame( array( '<p class="h-text">Ein kurzer Text.</p>' ), array_column( $this->parser->get_parsed_texts(), 'text' ) );
		$this->assertStringContainsString( '<p>Ein normaler Absatz.</p>', $result );
	}

	/**
	 * Only objects with blocks which are saved in Kubio use the page builder.
	 *
	 * @return void
	 */
	public function test_is_object_using_pagebuilder(): void {
		$post_id     = self::factory()->post->create( array( 'post_content' => self::CONTENT ) );
		$post_object = new Post_Object( $post_id );
		$this->assertFalse( $this->parser->is_object_using_pagebuilder( $post_object ) );

		update_post_meta( $post_id, 'saved_in_kubio', '1' );
		$this->assertTrue( $this->parser->is_object_using_pagebuilder( $post_object ) );

		// no blocks: not used.
		$classic_id = self::factory()->post->create( array( 'post_content' => '<p>Klassisch</p>' ) );
		update_post_meta( $classic_id, 'saved_in_kubio', '1' );
		$this->assertFalse( $this->parser->is_object_using_pagebuilder( new Post_Object( $classic_id ) ) );
	}
}
