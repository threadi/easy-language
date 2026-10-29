<?php
/**
 * Tests for class easyLanguage\Parser\VisualComposer.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for Visual Composer.
 */
class VisualComposer extends easyLanguageTests {
	/**
	 * The ID of the test page.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * The parser.
	 *
	 * @var \easyLanguage\Parser\VisualComposer
	 */
	private \easyLanguage\Parser\VisualComposer $parser;

	/**
	 * Define the prefix of Visual Composer, which the plugin defines for its meta keys.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		if ( ! defined( 'VCV_PREFIX' ) ) {
			define( 'VCV_PREFIX', 'vcv-' );
		}
	}

	/**
	 * Return the editor data as Visual Composer saves it.
	 *
	 * @return array<string,mixed>
	 */
	private function get_data(): array {
		return array(
			'elements' => array(
				'row1'  => array(
					'tag'    => 'row',
					'id'     => 'row1',
					'parent' => false,
					'order'  => 0,
				),
				'col1'  => array(
					'tag'    => 'column',
					'id'     => 'col1',
					'parent' => 'row1',
					'order'  => 0,
				),
				'text1' => array(
					'tag'    => 'textBlock',
					'id'     => 'text1',
					'parent' => 'col1',
					'order'  => 0,
					'output' => '<p>Das ist ein "langer" Text & mehr.</p>',
				),
				'text2' => array(
					'tag'    => 'textBlock',
					'id'     => 'text2',
					'parent' => 'col1',
					'order'  => 1,
					'output' => '<p>Ein zweiter Text.</p>',
				),
				'btn1'  => array(
					'tag'        => 'basicButton',
					'id'         => 'btn1',
					'parent'     => 'col1',
					'order'      => 2,
					'buttonText' => '<p>Ein zweiter Text.</p>',
				),
			),
		);
	}

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $this->post_id, 'vcv-pageContent', rawurlencode( (string) wp_json_encode( $this->get_data() ) ) );

		$this->parser = \easyLanguage\Parser\VisualComposer::get_instance();
		$this->parser->set_object_id( $this->post_id );
		$this->parser->set_text( '<p>Ein zweiter Text.</p>' );
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
	 * Return the list of active plugins with Visual Composer.
	 *
	 * @return array<int,string>
	 */
	public function get_active_plugins(): array {
		return array( 'visualcomposer/plugin-wordpress.php' );
	}

	/**
	 * Mark Visual Composer as active.
	 *
	 * @return void
	 */
	private function activate(): void {
		add_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins' ) );
	}

	/**
	 * Return the stored editor data.
	 *
	 * @return mixed
	 */
	private function get_stored_data(): mixed {
		return json_decode( rawurldecode( (string) get_post_meta( $this->post_id, 'vcv-pageContent', true ) ), true );
	}

	/**
	 * Without Visual Composer nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$this->assertFalse( $this->parser->is_active() );
		$this->assertSame( array(), $this->parser->get_parsed_texts() );
		$this->assertSame( 'x', $this->parser->get_text_with_simplifications( 'x', 'y' ) );
		$this->assertSame( $this->get_data(), $this->get_stored_data() );
	}

	/**
	 * The texts of the text blocks are found, other elements are ignored.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		$this->activate();

		$this->assertSame(
			array(
				array(
					'text' => '<p>Das ist ein "langer" Text & mehr.</p>',
					'html' => true,
				),
				array(
					'text' => '<p>Ein zweiter Text.</p>',
					'html' => true,
				),
			),
			$this->parser->get_parsed_texts()
		);
	}

	/**
	 * The text is replaced in the text block only, the encoded JSON stays valid and complete.
	 *
	 * @return void
	 */
	public function test_replace(): void {
		$this->activate();

		$simplified = '<p>Kurz & "klar" – 100%.</p>';
		$result     = $this->parser->get_text_with_simplifications( '<p>Ein zweiter Text.</p>', $simplified );

		$this->assertSame( $simplified, $result );

		$expected                                = $this->get_data();
		$expected['elements']['text2']['output'] = $simplified;
		$this->assertSame( $expected, $this->get_stored_data() );
	}

	/**
	 * Invalid editor data changes nothing.
	 *
	 * @return void
	 */
	public function test_invalid_data(): void {
		$this->activate();
		update_post_meta( $this->post_id, 'vcv-pageContent', 'kein%20JSON' );

		$this->assertSame( array(), $this->parser->get_parsed_texts() );
		$this->assertSame( 'Text', $this->parser->get_text_with_simplifications( 'Text', 'Neu' ) );
		$this->assertSame( 'kein%20JSON', get_post_meta( $this->post_id, 'vcv-pageContent', true ) );
	}

	/**
	 * Objects with editor data of Visual Composer use the page builder.
	 *
	 * @return void
	 */
	public function test_is_object_using_pagebuilder(): void {
		$this->activate();

		$this->assertTrue( $this->parser->is_object_using_pagebuilder( new Post_Object( $this->post_id ) ) );

		$other_id = self::factory()->post->create();
		$this->assertFalse( $this->parser->is_object_using_pagebuilder( new Post_Object( $other_id ) ) );
	}
}
