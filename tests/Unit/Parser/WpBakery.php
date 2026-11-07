<?php
/**
 * Tests for class easyLanguage\Parser\WpBakery.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for WPBakery.
 */
class WpBakery extends easyLanguageTests {
	/**
	 * The plugin which must be active for this parser.
	 */
	private const PLUGIN = 'js_composer/js_composer.php';

	/**
	 * Content as WPBakery saves it in post_content.
	 */
	private const CONTENT = '[vc_row][vc_column][vc_column_text css=".vc_custom_1{margin:0}"]<p>Das ist ein langer Text.</p>[/vc_column_text][vc_btn title="Mehr erfahren" link="url:%2Fkontakt%2F"][block_title title="Unsere Leistungen"][vc_toggle title="Was kostet das?"]Nichts.[/vc_toggle][vc_custom_heading text="Nicht unterstützt"][/vc_column][/vc_row]';

	/**
	 * The parser.
	 *
	 * @var \easyLanguage\Parser\WpBakery
	 */
	private \easyLanguage\Parser\WpBakery $parser;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->parser = \easyLanguage\Parser\WpBakery::get_instance();
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
		parent::tear_down();
	}

	/**
	 * Return the list of active plugins with WPBakery.
	 *
	 * @return array<int,string>
	 */
	public function get_active_plugins(): array {
		return array( self::PLUGIN );
	}

	/**
	 * Mark WPBakery as active.
	 *
	 * @return void
	 */
	private function activate(): void {
		add_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins' ) );
	}

	/**
	 * Without WPBakery nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$this->assertFalse( $this->parser->is_active() );
		$this->assertSame( array(), $this->parser->get_parsed_texts() );
		$this->parser->set_text( 'Mehr erfahren' );
		$this->assertSame( self::CONTENT, $this->parser->get_text_with_simplifications( self::CONTENT, 'Weiter' ) );
	}

	/**
	 * The texts of the supported shortcodes are found, with HTML only for the text block.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		$this->activate();
		$this->assertTrue( $this->parser->is_active() );

		$texts = $this->parser->get_parsed_texts();

		$this->assertContains( array( 'text' => '<p>Das ist ein langer Text.</p>', 'html' => true ), $texts );
		$this->assertContains( array( 'text' => 'Mehr erfahren', 'html' => false ), $texts );
		$this->assertContains( array( 'text' => 'Unsere Leistungen', 'html' => false ), $texts );
		$this->assertContains( array( 'text' => 'Was kostet das?', 'html' => false ), $texts );
		$this->assertContains( array( 'text' => 'Nichts.', 'html' => false ), $texts );

		// unsupported shortcodes and other attributes are not part of the texts.
		$this->assertNotContains( 'Nicht unterstützt', array_column( $texts, 'text' ) );
		$this->assertNotContains( 'url:%2Fkontakt%2F', array_column( $texts, 'text' ) );
		$this->assertCount( 5, $texts );
	}

	/**
	 * The text block content is replaced, the shortcodes stay intact.
	 *
	 * @return void
	 */
	public function test_replace_text_block(): void {
		$this->activate();
		$this->parser->set_text( '<p>Das ist ein langer Text.</p>' );

		$result = $this->parser->get_text_with_simplifications( self::CONTENT, '<p>Das ist kurz.</p>' );

		$this->assertSame( str_replace( '<p>Das ist ein langer Text.</p>', '<p>Das ist kurz.</p>', self::CONTENT ), $result );
	}

	/**
	 * An attribute is replaced, the other attributes stay unchanged.
	 *
	 * @return void
	 */
	public function test_replace_attribute(): void {
		$this->activate();
		$this->parser->set_text( 'Mehr erfahren' );

		$result = $this->parser->get_text_with_simplifications( self::CONTENT, 'Mehr "lesen"' );

		$this->assertStringContainsString( '[vc_btn title="Mehr &quot;lesen&quot;" link="url:%2Fkontakt%2F"]', $result );

		// the parser finds the simplified (unmasked) text in the result.
		$this->parser->set_text( $result );
		$this->assertContains( 'Mehr "lesen"', array_column( $this->parser->get_parsed_texts(), 'text' ) );
		$this->assertCount( 5, $this->parser->get_parsed_texts() );
	}

	/**
	 * Only objects marked by WPBakery use the page builder.
	 *
	 * @return void
	 */
	public function test_is_object_using_pagebuilder(): void {
		$post_id     = self::factory()->post->create( array( 'post_content' => self::CONTENT ) );
		$post_object = new Post_Object( $post_id );

		// not active.
		update_post_meta( $post_id, '_wpb_vc_js_status', 'true' );
		$this->assertFalse( $this->parser->is_object_using_pagebuilder( $post_object ) );

		// active, but not saved with WPBakery.
		$this->activate();
		update_post_meta( $post_id, '_wpb_vc_js_status', 'false' );
		$this->assertFalse( $this->parser->is_object_using_pagebuilder( $post_object ) );

		// active and saved with WPBakery.
		update_post_meta( $post_id, '_wpb_vc_js_status', 'true' );
		$this->assertTrue( $this->parser->is_object_using_pagebuilder( $post_object ) );
	}

	/**
	 * The edit link opens the frontend editor of WPBakery.
	 *
	 * @return void
	 */
	public function test_edit_link(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->parser->set_object_id( $post_id );

		$link = $this->parser->get_edit_link();

		$this->assertStringContainsString( 'post.php', $link );
		$this->assertStringContainsString( 'vc_action=vc_inline', $link );
		$this->assertStringContainsString( 'post_id=' . $post_id, $link );
		$this->assertStringContainsString( 'post_type=page', $link );
	}
}
