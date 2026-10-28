<?php
/**
 * Tests for the parser for Beaver Builder.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;
use stdClass;

/**
 * Object to test the parser for Beaver Builder, which stores its layout as array of node objects in post meta.
 */
class BeaverBuilder extends easyLanguageTests {
	/**
	 * Return a node like Beaver Builder saves it.
	 *
	 * @param string              $node     The ID of the node.
	 * @param string              $type     The type of the node (row, column-group, column, module).
	 * @param string|null         $parent   The ID of the parent node.
	 * @param array<string,mixed> $settings The settings of the node.
	 *
	 * @return stdClass
	 */
	private function get_node( string $node, string $type, ?string $parent, array $settings ): stdClass {
		return (object) array(
			'node'     => $node,
			'type'     => $type,
			'parent'   => $parent,
			'position' => 0,
			'settings' => (object) $settings,
		);
	}

	/**
	 * Return the layout data like Beaver Builder saves it in "_fl_builder_data".
	 *
	 * @return array<string,stdClass>
	 */
	private function get_data(): array {
		return array(
			'5f1a' => $this->get_node( '5f1a', 'row', null, array( 'width' => 'fixed' ) ),
			'5f1b' => $this->get_node( '5f1b', 'column-group', '5f1a', array() ),
			'5f1c' => $this->get_node( '5f1c', 'column', '5f1b', array( 'size' => 100 ) ),
			'5f1d' => $this->get_node(
				'5f1d',
				'module',
				'5f1c',
				array(
					'type'    => 'heading',
					'heading' => 'Willkommen auf unserer Seite',
					'tag'     => 'h2',
				)
			),
			'5f1e' => $this->get_node(
				'5f1e',
				'module',
				'5f1c',
				array(
					'type' => 'rich-text',
					'text' => '<p>Hier steht ein komplizierter Text.</p>',
				)
			),
			'5f1f' => $this->get_node(
				'5f1f',
				'module',
				'5f1c',
				array(
					'type'  => 'callout',
					'title' => 'Hinweis',
					'text'  => '<p>Hier steht ein komplizierter Text.</p>',
				)
			),
			'5f20' => $this->get_node(
				'5f20',
				'module',
				'5f1c',
				array(
					'type' => 'photo',
					'text' => 'Kein Text-Modul',
				)
			),
		);
	}

	/**
	 * Activate Beaver Builder.
	 *
	 * @return void
	 */
	private function activate(): void {
		update_option( 'active_plugins', array( 'beaver-builder-lite-version/fl-builder.php' ) );
	}

	/**
	 * Return the parser for a new post with the layout data.
	 *
	 * @param string $text The text to parse.
	 *
	 * @return \easyLanguage\Parser\BeaverBuilder
	 */
	private function get_parser( string $text ): \easyLanguage\Parser\BeaverBuilder {
		$post_id = self::factory()->post->create( array( 'post_content' => '<p>Hier steht ein komplizierter Text.</p>' ) );
		update_post_meta( $post_id, '_fl_builder_data', $this->get_data() );

		$parser = \easyLanguage\Parser\BeaverBuilder::get_instance();
		$parser->set_object_id( $post_id );
		$parser->set_text( $text );
		return $parser;
	}

	/**
	 * Without Beaver Builder nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$parser = $this->get_parser( '<p>Hier steht ein komplizierter Text.</p>' );

		$this->assertFalse( $parser->is_active() );
		$this->assertSame( array(), $parser->get_parsed_texts() );
		$this->assertSame( 'Inhalt', $parser->get_text_with_simplifications( 'Inhalt', 'Einfach' ) );
		$this->assertEquals( $this->get_data(), get_post_meta( $parser->get_object_id(), '_fl_builder_data', true ) );
	}

	/**
	 * Both the free and the pro version of Beaver Builder are detected.
	 *
	 * @return void
	 */
	public function test_active_with_pro_version(): void {
		update_option( 'active_plugins', array( 'bb-plugin/fl-builder.php' ) );
		$this->assertTrue( \easyLanguage\Parser\BeaverBuilder::get_instance()->is_active() );
	}

	/**
	 * The texts of the supported modules are found.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		$this->activate();

		$this->assertSame(
			array(
				array(
					'text' => 'Willkommen auf unserer Seite',
					'html' => false,
				),
				array(
					'text' => '<p>Hier steht ein komplizierter Text.</p>',
					'html' => true,
				),
				array(
					'text' => '<p>Hier steht ein komplizierter Text.</p>',
					'html' => true,
				),
			),
			$this->get_parser( '' )->get_parsed_texts()
		);
	}

	/**
	 * The simplification is saved in the modules with the text, the other nodes stay unchanged.
	 *
	 * @return void
	 */
	public function test_replacement(): void {
		$this->activate();
		$parser     = $this->get_parser( '<p>Hier steht ein komplizierter Text.</p>' );
		$simplified = '<p>Hier steht ein "einfacher" Text mit \\ Backslash.</p>';

		$result = $parser->get_text_with_simplifications( '<p>Hier steht ein komplizierter Text.</p>', $simplified );

		// the post content is replaced.
		$this->assertSame( $simplified, $result );

		// the data in post meta is still a list of nodes, quotes and backslashes are kept.
		$expected                         = $this->get_data();
		$expected['5f1e']->settings->text = $simplified;
		$expected['5f1f']->settings->text = $simplified;
		$this->assertEquals( $expected, get_post_meta( $parser->get_object_id(), '_fl_builder_data', true ) );
	}

	/**
	 * Nothing is saved if the text is not part of the layout.
	 *
	 * @return void
	 */
	public function test_replacement_of_unknown_text(): void {
		$this->activate();
		$parser = $this->get_parser( 'Unbekannter Text' );

		$parser->get_text_with_simplifications( 'Inhalt', 'Einfach' );

		$this->assertEquals( $this->get_data(), get_post_meta( $parser->get_object_id(), '_fl_builder_data', true ) );
	}

	/**
	 * An object uses Beaver Builder if the builder is enabled for it.
	 *
	 * @return void
	 */
	public function test_object_using_pagebuilder(): void {
		$post_id = self::factory()->post->create();
		$this->assertFalse( \easyLanguage\Parser\BeaverBuilder::get_instance()->is_object_using_pagebuilder( new Post_Object( $post_id ) ) );

		update_post_meta( $post_id, '_fl_builder_enabled', 1 );
		$this->assertTrue( \easyLanguage\Parser\BeaverBuilder::get_instance()->is_object_using_pagebuilder( new Post_Object( $post_id ) ) );
	}
}
