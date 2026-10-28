<?php
/**
 * Tests for the parser for Breakdance.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for Breakdance, which stores its tree as JSON in the post meta "_breakdance_data".
 */
class Breakdance extends easyLanguageTests {
	/**
	 * Load the fakes for the data functions of Breakdance.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		require_once dirname( __DIR__, 2 ) . '/Fakes/Parser/Breakdance/functions.php';
	}

	/**
	 * Return a tree like Breakdance saves it.
	 *
	 * @param string $text The text of the text element.
	 *
	 * @return array<string,mixed>
	 */
	private function get_tree( string $text = '<p>Ein komplizierter Text.</p>' ): array {
		return array(
			'root'       => array(
				'id'       => 1,
				'data'     => array(
					'type'       => 'root',
					'properties' => null,
				),
				'children' => array(
					array(
						'id'       => 100,
						'data'     => array(
							'type'       => 'EssentialElements\\Section',
							'properties' => array( 'design' => array( 'spacing' => array( 'padding' => '10px' ) ) ),
						),
						'children' => array(
							array(
								'id'       => 101,
								'data'     => array(
									'type'       => 'EssentialElements\\PostTitle',
									'properties' => array( 'content' => array( 'content' => array( 'text' => 'Unsere Leistungen' ) ) ),
								),
								'children' => array(),
							),
							array(
								'id'       => 102,
								'data'     => array(
									'type'       => 'EssentialElements\\Text',
									'properties' => array( 'content' => array( 'content' => array( 'text' => $text ) ) ),
								),
								'children' => array(),
							),
							array(
								'id'       => 103,
								'data'     => array(
									'type'       => 'EssentialElements\\Button',
									'properties' => array( 'content' => array( 'content' => array( 'text' => '<p>Ein komplizierter Text.</p>' ) ) ),
								),
								'children' => array(),
							),
						),
					),
				),
			),
			'_nextNodeId' => 104,
			'status'      => 'exported',
		);
	}

	/**
	 * Activate Breakdance.
	 *
	 * @return void
	 */
	private function activate(): void {
		update_option( 'active_plugins', array( 'breakdance/plugin.php' ) );
	}

	/**
	 * Return the parser for a new post with a Breakdance tree.
	 *
	 * @param string $text The text to parse.
	 *
	 * @return \easyLanguage\Parser\Breakdance
	 */
	private function get_parser( string $text ): \easyLanguage\Parser\Breakdance {
		$post_id = self::factory()->post->create( array( 'post_content' => '<p>Ein komplizierter Text.</p>' ) );
		\Breakdance\Data\set_meta( $post_id, '_breakdance_data', array( 'tree_json_string' => (string) wp_json_encode( $this->get_tree() ) ) );

		$parser = \easyLanguage\Parser\Breakdance::get_instance();
		$parser->set_object_id( $post_id );
		$parser->set_text( $text );
		return $parser;
	}

	/**
	 * Without Breakdance nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$parser = $this->get_parser( '<p>Ein komplizierter Text.</p>' );
		$before = get_post_meta( $parser->get_object_id(), '_breakdance_data', true );

		$this->assertFalse( $parser->is_active() );
		$this->assertSame( array(), $parser->get_parsed_texts() );
		$this->assertSame( 'Inhalt', $parser->get_text_with_simplifications( 'Inhalt', 'Einfach' ) );
		$this->assertSame( $before, get_post_meta( $parser->get_object_id(), '_breakdance_data', true ) );
	}

	/**
	 * The texts of the supported elements are found in the nested tree.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		$this->activate();

		$this->assertSame(
			array(
				array(
					'text' => 'Unsere Leistungen',
					'html' => false,
				),
				array(
					'text' => '<p>Ein komplizierter Text.</p>',
					'html' => true,
				),
			),
			$this->get_parser( '' )->get_parsed_texts()
		);
	}

	/**
	 * The simplification is saved in the tree of Breakdance, which stays valid JSON.
	 *
	 * @return void
	 */
	public function test_replacement(): void {
		$this->activate();
		$parser     = $this->get_parser( '<p>Ein komplizierter Text.</p>' );
		$simplified = '<p>Ein "einfacher" Text mit \\ Backslash.</p>';

		$this->assertSame( $simplified, $parser->get_text_with_simplifications( '<p>Ein komplizierter Text.</p>', $simplified ) );

		// the tree contains the simplification only in the supported element, the unsupported button is unchanged.
		$expected = $this->get_tree( $simplified );
		$this->assertSame( $expected, \Breakdance\Data\get_tree( $parser->get_object_id() ) );
	}

	/**
	 * Nothing is saved if the text is not part of the tree.
	 *
	 * @return void
	 */
	public function test_replacement_of_unknown_text(): void {
		$this->activate();
		$parser = $this->get_parser( 'Unbekannt' );
		$before = get_post_meta( $parser->get_object_id(), '_breakdance_data', true );

		$parser->get_text_with_simplifications( 'Inhalt', 'Einfach' );

		$this->assertSame( $before, get_post_meta( $parser->get_object_id(), '_breakdance_data', true ) );
	}

	/**
	 * An object uses Breakdance if it has Breakdance data.
	 *
	 * @return void
	 */
	public function test_object_using_pagebuilder(): void {
		$this->assertFalse( \easyLanguage\Parser\Breakdance::get_instance()->is_object_using_pagebuilder( new Post_Object( self::factory()->post->create() ) ) );

		$parser = $this->get_parser( '' );
		$this->assertTrue( $parser->is_object_using_pagebuilder( new Post_Object( $parser->get_object_id() ) ) );
	}
}
