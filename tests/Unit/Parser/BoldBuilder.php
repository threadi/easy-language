<?php
/**
 * Tests for the parser for Bold Page Builder.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for Bold Page Builder, which stores its content as shortcodes in post_content.
 */
class BoldBuilder extends easyLanguageTests {
	/**
	 * Content like Bold Page Builder saves it.
	 *
	 * @var string
	 */
	private string $content = '[bt_bb_section layout="boxed_1200" top_spacing="normal"][bt_bb_row][bt_bb_column width="1/1"][bt_bb_headline headline="Unsere Firma" size="large"][/bt_bb_headline][bt_bb_text]<p>Wir arbeiten seit vielen Jahren in dieser Branche.</p>[/bt_bb_text][bt_bb_text]<p>Rufen Sie uns an.</p>[/bt_bb_text][/bt_bb_column][/bt_bb_row][/bt_bb_section]';

	/**
	 * Activate Bold Page Builder.
	 *
	 * @return void
	 */
	private function activate(): void {
		update_option( 'active_plugins', array( 'bold-page-builder/bold-builder.php' ) );
	}

	/**
	 * Return the parser prepared for the given text.
	 *
	 * @param string $text The text to parse.
	 *
	 * @return \easyLanguage\Parser\BoldBuilder
	 */
	private function get_parser( string $text ): \easyLanguage\Parser\BoldBuilder {
		$parser = \easyLanguage\Parser\BoldBuilder::get_instance();
		$parser->set_object_id( self::factory()->post->create( array( 'post_content' => $this->content ) ) );
		$parser->set_text( $text );
		return $parser;
	}

	/**
	 * Without Bold Page Builder nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$parser = $this->get_parser( $this->content );

		$this->assertFalse( $parser->is_active() );
		$this->assertSame( array(), $parser->get_parsed_texts() );

		$parser->set_text( '<p>Rufen Sie uns an.</p>' );
		$this->assertSame( $this->content, $parser->get_text_with_simplifications( $this->content, '<p>Anrufen.</p>' ) );
	}

	/**
	 * The content of the text elements is found, other elements are ignored.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		$this->activate();

		$this->assertSame(
			array(
				array(
					'text' => '<p>Wir arbeiten seit vielen Jahren in dieser Branche.</p>',
					'html' => true,
				),
				array(
					'text' => '<p>Rufen Sie uns an.</p>',
					'html' => true,
				),
			),
			$this->get_parser( $this->content )->get_parsed_texts()
		);
	}

	/**
	 * The simplification replaces only the matching text element.
	 *
	 * @return void
	 */
	public function test_replacement(): void {
		$this->activate();
		$parser = $this->get_parser( '<p>Rufen Sie uns an.</p>' );

		$result = $parser->get_text_with_simplifications( $this->content, '<p>Rufen Sie an.</p>' );

		$this->assertSame( str_replace( '[bt_bb_text]<p>Rufen Sie uns an.</p>[/bt_bb_text]', '[bt_bb_text]<p>Rufen Sie an.</p>[/bt_bb_text]', $this->content ), $result );
		$this->assertSame( substr_count( $this->content, '[' ), substr_count( $result, '[' ) );
	}

	/**
	 * A text which is only part of a text element is not replaced.
	 *
	 * @return void
	 */
	public function test_partial_text_is_not_replaced(): void {
		$this->activate();
		$parser = $this->get_parser( 'Rufen Sie uns an.' );

		$this->assertSame( $this->content, $parser->get_text_with_simplifications( $this->content, 'Anrufen.' ) );
	}

	/**
	 * An object uses Bold Page Builder if it is active and the content contains its sections.
	 *
	 * @return void
	 */
	public function test_object_using_pagebuilder(): void {
		$post_object = new Post_Object( self::factory()->post->create( array( 'post_content' => $this->content ) ) );
		$this->assertFalse( \easyLanguage\Parser\BoldBuilder::get_instance()->is_object_using_pagebuilder( $post_object ) );

		$this->activate();
		$this->assertTrue( \easyLanguage\Parser\BoldBuilder::get_instance()->is_object_using_pagebuilder( $post_object ) );
		$this->assertFalse( \easyLanguage\Parser\BoldBuilder::get_instance()->is_object_using_pagebuilder( new Post_Object( self::factory()->post->create( array( 'post_content' => '<p>Ohne Builder</p>' ) ) ) ) );
	}

	/**
	 * Updating an object triggers "save_post", so Bold Page Builder updates its data.
	 *
	 * @return void
	 */
	public function test_update_object_triggers_save_post(): void {
		$post_id = self::factory()->post->create( array( 'post_content' => $this->content ) );

		$saved = array();
		add_action(
			'save_post',
			static function ( $id ) use ( &$saved ) {
				$saved[] = $id;
			}
		);

		\easyLanguage\Parser\BoldBuilder::get_instance()->update_object( new Post_Object( $post_id ) );

		$this->assertContains( $post_id, $saved );
	}
}
