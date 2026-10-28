<?php
/**
 * Tests for class easyLanguage\Parser\SiteOrigin.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for SiteOrigin Page Builder.
 */
class SiteOrigin extends easyLanguageTests {
	/**
	 * The ID of the test page.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * The parser.
	 *
	 * @var \easyLanguage\Parser\SiteOrigin
	 */
	private \easyLanguage\Parser\SiteOrigin $parser;

	/**
	 * Return the editor data as SiteOrigin saves it in the post meta "panels_data".
	 *
	 * @return array<string,mixed>
	 */
	private function get_data(): array {
		return array(
			'widgets'    => array(
				array(
					'title'       => 'Über uns',
					'text'        => '<p>Das ist ein langer Text.</p>',
					'filter'      => true,
					'visual'      => true,
					'panels_info' => array(
						'class' => 'WP_Widget_Text',
						'grid'  => 0,
						'cell'  => 0,
						'id'    => 0,
					),
					'option_name' => 'widget_text',
				),
				array(
					'title'       => 'Über uns',
					'url'         => 'https://example.com/bild.jpg',
					'panels_info' => array(
						'class' => 'WP_Widget_Media_Image',
						'grid'  => 0,
						'cell'  => 1,
						'id'    => 1,
					),
					'option_name' => 'widget_media_image',
				),
			),
			'grids'      => array(
				array(
					'cells' => 2,
					'style' => array(),
				),
			),
			'grid_cells' => array(
				array(
					'grid'   => 0,
					'weight' => 0.5,
				),
				array(
					'grid'   => 0,
					'weight' => 0.5,
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
		update_post_meta( $this->post_id, 'panels_data', wp_slash( $this->get_data() ) );

		$this->parser = \easyLanguage\Parser\SiteOrigin::get_instance();
		$this->parser->set_object_id( $this->post_id );
		$this->parser->set_text( 'Über uns' );
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
	 * Return the list of active plugins with SiteOrigin.
	 *
	 * @return array<int,string>
	 */
	public function get_active_plugins(): array {
		return array( 'siteorigin-panels/siteorigin-panels.php' );
	}

	/**
	 * Mark SiteOrigin as active.
	 *
	 * @return void
	 */
	private function activate(): void {
		add_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins' ) );
	}

	/**
	 * Without SiteOrigin nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$this->assertFalse( $this->parser->is_active() );
		$this->assertSame( array(), $this->parser->get_parsed_texts() );
		$this->assertSame( 'Über uns', $this->parser->get_text_with_simplifications( 'Über uns', 'Wir' ) );
		$this->assertSame( $this->get_data(), get_post_meta( $this->post_id, 'panels_data', true ) );
	}

	/**
	 * The title and text of text widgets are found, other widgets are ignored.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		$this->activate();

		$this->assertSame(
			array(
				array(
					'text' => 'Über uns',
					'html' => false,
				),
				array(
					'text' => '<p>Das ist ein langer Text.</p>',
					'html' => true,
				),
			),
			$this->parser->get_parsed_texts()
		);
	}

	/**
	 * The title is replaced without HTML in the text widget only, the rest of the data stays unchanged.
	 *
	 * @return void
	 */
	public function test_replace_title(): void {
		$this->activate();

		$result = $this->parser->get_text_with_simplifications( '<h3>Über uns</h3>', '<p>Wer wir sind</p>' );

		$this->assertSame( '<h3><p>Wer wir sind</p></h3>', $result );

		$expected                        = $this->get_data();
		$expected['widgets'][0]['title'] = 'Wer wir sind';
		$this->assertSame( $expected, get_post_meta( $this->post_id, 'panels_data', true ) );
	}

	/**
	 * The text keeps its HTML and special characters are stored correctly.
	 *
	 * @return void
	 */
	public function test_replace_text(): void {
		$this->activate();
		$this->parser->set_text( '<p>Das ist ein langer Text.</p>' );

		$simplified = '<p>Ein "kurzer" Text mit C:\\Pfad.</p>';
		$this->parser->get_text_with_simplifications( '', $simplified );

		$expected                       = $this->get_data();
		$expected['widgets'][0]['text'] = $simplified;
		$this->assertSame( $expected, get_post_meta( $this->post_id, 'panels_data', true ) );
	}

	/**
	 * Objects with page settings of SiteOrigin use the page builder.
	 *
	 * @return void
	 */
	public function test_is_object_using_pagebuilder(): void {
		$post_object = new Post_Object( $this->post_id );
		$this->assertFalse( $this->parser->is_object_using_pagebuilder( $post_object ) );

		update_post_meta( $this->post_id, 'siteorigin_page_settings', array( 'layout' => 'default' ) );
		$this->assertTrue( $this->parser->is_object_using_pagebuilder( $post_object ) );
	}
}
