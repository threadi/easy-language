<?php
/**
 * Tests for the parser for Avada (Fusion Builder).
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;
use easyLanguage\Tests\Fakes\Parser\Theme_Switcher;

/**
 * Object to test the parser for Avada, which stores its content as shortcodes in post_content.
 */
class Avada extends easyLanguageTests {
	/**
	 * Content like Avada saves it.
	 *
	 * @var string
	 */
	private string $content = '[fusion_builder_container type="flex"][fusion_builder_row][fusion_builder_column type="1_1"][fusion_title title_type="text" size="1"]Unser Angebot[/fusion_title][fusion_text]<p>Wir bieten komplizierte Dienstleistungen an.</p>[/fusion_text][fusion_text]<p>Ein zweiter Text.</p>[/fusion_text][fusion_button link="#"]Wir bieten komplizierte Dienstleistungen an.[/fusion_button][/fusion_builder_column][/fusion_builder_row][/fusion_builder_container]';

	/**
	 * Return the parser prepared for the given content.
	 *
	 * @param string $text The text to parse.
	 *
	 * @return \easyLanguage\Parser\Avada
	 */
	private function get_parser( string $text ): \easyLanguage\Parser\Avada {
		$parser = \easyLanguage\Parser\Avada::get_instance();
		$parser->set_object_id( self::factory()->post->create( array( 'post_content' => $this->content ) ) );
		$parser->set_text( $text );
		return $parser;
	}

	/**
	 * Without Avada nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$parser = $this->get_parser( $this->content );

		$this->assertFalse( $parser->is_active() );
		$this->assertSame( array(), $parser->get_parsed_texts() );

		$parser->set_text( '<p>Wir bieten komplizierte Dienstleistungen an.</p>' );
		$this->assertSame( $this->content, $parser->get_text_with_simplifications( $this->content, '<p>Einfach.</p>' ) );
	}

	/**
	 * Avada is active with its theme or a child theme of it.
	 *
	 * @return void
	 */
	public function test_active_with_theme(): void {
		Theme_Switcher::activate( 'avada' );
		$this->assertTrue( \easyLanguage\Parser\Avada::get_instance()->is_active() );
	}

	/**
	 * Avada is active with a child theme of it.
	 *
	 * @return void
	 */
	public function test_active_with_child_theme(): void {
		Theme_Switcher::activate( 'avada-child', 'avada' );
		$this->assertTrue( \easyLanguage\Parser\Avada::get_instance()->is_active() );
	}

	/**
	 * The texts of the supported shortcodes are found, other shortcodes are ignored.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		Theme_Switcher::activate( 'avada' );

		$this->assertSame(
			array(
				array(
					'text' => 'Unser Angebot',
					'html' => false,
				),
				array(
					'text' => '<p>Wir bieten komplizierte Dienstleistungen an.</p>',
					'html' => true,
				),
				array(
					'text' => '<p>Ein zweiter Text.</p>',
					'html' => true,
				),
			),
			$this->get_parser( $this->content )->get_parsed_texts()
		);
	}

	/**
	 * The simplification replaces exactly the text in its shortcode, the rest of the content stays unchanged.
	 *
	 * @return void
	 */
	public function test_replacement(): void {
		Theme_Switcher::activate( 'avada' );
		$parser = $this->get_parser( '<p>Wir bieten komplizierte Dienstleistungen an.</p>' );

		$result = $parser->get_text_with_simplifications( $this->content, '<p>Wir helfen Ihnen.</p>' );

		$this->assertSame( str_replace( '[fusion_text]<p>Wir bieten komplizierte Dienstleistungen an.</p>[/fusion_text]', '[fusion_text]<p>Wir helfen Ihnen.</p>[/fusion_text]', $this->content ), $result );

		// the same text in an unsupported shortcode is not changed.
		$this->assertStringContainsString( '[fusion_button link="#"]Wir bieten komplizierte Dienstleistungen an.[/fusion_button]', $result );

		// the result could be parsed again.
		$parser->set_text( $result );
		$this->assertSame( array( 'Unser Angebot', '<p>Wir helfen Ihnen.</p>', '<p>Ein zweiter Text.</p>' ), array_column( $parser->get_parsed_texts(), 'text' ) );
	}

	/**
	 * Characters which would break a shortcode are kept in the content of a shortcode.
	 *
	 * @return void
	 */
	public function test_replacement_with_special_characters(): void {
		Theme_Switcher::activate( 'avada' );
		$parser = $this->get_parser( 'Unser Angebot' );

		$result = $parser->get_text_with_simplifications( $this->content, 'Das "neue" Angebot' );

		$this->assertStringContainsString( '[fusion_title title_type="text" size="1"]Das "neue" Angebot[/fusion_title]', $result );
	}

	/**
	 * An object uses Avada if its builder status is active.
	 *
	 * @return void
	 */
	public function test_object_using_pagebuilder(): void {
		$post_id = self::factory()->post->create( array( 'post_content' => $this->content ) );
		$this->assertFalse( \easyLanguage\Parser\Avada::get_instance()->is_object_using_pagebuilder( new Post_Object( $post_id ) ) );

		update_post_meta( $post_id, 'fusion_builder_status', 'active' );
		$this->assertTrue( \easyLanguage\Parser\Avada::get_instance()->is_object_using_pagebuilder( new Post_Object( $post_id ) ) );
	}
}
