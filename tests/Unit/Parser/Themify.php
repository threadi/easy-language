<?php
/**
 * Tests for class easyLanguage\Parser\Themify.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for Themify Builder.
 */
class Themify extends easyLanguageTests {
	/**
	 * The ID of the test page.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * The parser.
	 *
	 * @var \easyLanguage\Parser\Themify
	 */
	private \easyLanguage\Parser\Themify $parser;

	/**
	 * Load the fake of the Themify data manager.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		require_once dirname( __DIR__, 2 ) . '/Fakes/Parser/Themify/ThemifyBuilder_Data_Manager.php';
	}

	/**
	 * Return the builder data as Themify saves it: rows with columns with modules, also in sub rows.
	 *
	 * @return array<int,mixed>
	 */
	private function get_data(): array {
		return array(
			array(
				'element_id' => 'row1',
				'cols'       => array(
					array(
						'element_id' => 'col1',
						'grid_class' => 'col-full',
						'modules'    => array(
							array(
								'mod_name'     => 'fancy-heading',
								'element_id'   => 'm1',
								'mod_settings' => array(
									'heading'     => 'Willkommen',
									'sub_heading' => 'Schön, dass Sie da sind',
									'heading_tag' => 'h1',
								),
							),
							array(
								'mod_name'     => 'text',
								'element_id'   => 'm2',
								'mod_settings' => array(
									'content_text' => '<p>Das ist ein langer Text.</p>',
								),
							),
							array(
								'mod_name'     => 'buttons',
								'element_id'   => 'm3',
								'mod_settings' => array(
									'content_button' => array(
										array(
											'label' => 'Kontakt',
											'link'  => '/kontakt/',
										),
										array(
											'label' => 'Willkommen',
											'link'  => '/start/',
										),
									),
								),
							),
							array(
								'mod_name'     => 'image',
								'element_id'   => 'm4',
								'mod_settings' => array(
									'title_image' => 'Willkommen',
								),
							),
							array(
								'element_id' => 'sub1',
								'cols'       => array(
									array(
										'element_id' => 'subcol1',
										'modules'    => array(
											array(
												'mod_name'     => 'text',
												'element_id'   => 'm5',
												'mod_settings' => array(
													'content_text' => '<p>Text in einer Unterzeile.</p>',
												),
											),
										),
									),
								),
							),
						),
					),
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
		\ThemifyBuilder_Data_Manager::save_data( $this->get_data(), $this->post_id );

		$this->parser = \easyLanguage\Parser\Themify::get_instance();
		$this->parser->set_object_id( $this->post_id );
		$this->parser->set_text( 'Willkommen' );
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
	 * Return the list of active plugins with Themify Builder.
	 *
	 * @return array<int,string>
	 */
	public function get_active_plugins(): array {
		return array( 'themify-builder/themify-builder.php' );
	}

	/**
	 * Mark Themify Builder as active.
	 *
	 * @return void
	 */
	private function activate(): void {
		add_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins' ) );
	}

	/**
	 * Without Themify Builder nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$this->assertFalse( $this->parser->is_active() );
		$this->assertSame( array(), $this->parser->get_parsed_texts() );
		$this->assertSame( 'Willkommen', $this->parser->get_text_with_simplifications( 'Willkommen', 'Hallo' ) );
		$this->assertSame( $this->get_data(), \ThemifyBuilder_Data_Manager::get_data( $this->post_id ) );
	}

	/**
	 * The texts of the supported modules are found, also in lists and sub rows.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		$this->activate();

		$texts = array_values( $this->parser->get_parsed_texts() );

		$this->assertEqualsCanonicalizing(
			array(
				array(
					'text' => 'Willkommen',
					'html' => false,
				),
				array(
					'text' => 'Schön, dass Sie da sind',
					'html' => false,
				),
				array(
					'text' => '<p>Das ist ein langer Text.</p>',
					'html' => true,
				),
				array(
					'text' => 'Kontakt',
					'html' => false,
				),
				array(
					'text' => '<p>Text in einer Unterzeile.</p>',
					'html' => true,
				),
			),
			$texts
		);
	}

	/**
	 * The text is replaced in all supported modules (also in button lists), not in other modules.
	 *
	 * @return void
	 */
	public function test_replace(): void {
		$this->activate();

		$result = $this->parser->get_text_with_simplifications( '<h1>Willkommen</h1>', 'Hallo' );

		$this->assertSame( '<h1>Hallo</h1>', $result );

		$expected = $this->get_data();
		$expected[0]['cols'][0]['modules'][0]['mod_settings']['heading']                     = 'Hallo';
		$expected[0]['cols'][0]['modules'][2]['mod_settings']['content_button'][1]['label'] = 'Hallo';
		$this->assertSame( $expected, \ThemifyBuilder_Data_Manager::get_data( $this->post_id ) );
	}

	/**
	 * Texts in sub rows are replaced, too.
	 *
	 * @return void
	 */
	public function test_replace_in_sub_row(): void {
		$this->activate();
		$this->parser->set_text( '<p>Text in einer Unterzeile.</p>' );

		$this->parser->get_text_with_simplifications( '', '<p>Kurz.</p>' );

		$expected = $this->get_data();
		$expected[0]['cols'][0]['modules'][4]['cols'][0]['modules'][0]['mod_settings']['content_text'] = '<p>Kurz.</p>';
		$this->assertSame( $expected, \ThemifyBuilder_Data_Manager::get_data( $this->post_id ) );
	}

	/**
	 * Objects with Themify Builder data use the page builder.
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
