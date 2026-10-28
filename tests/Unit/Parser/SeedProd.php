<?php
/**
 * Tests for class easyLanguage\Parser\SeedProd.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for SeedProd.
 */
class SeedProd extends easyLanguageTests {
	/**
	 * The ID of the test page.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * The parser.
	 *
	 * @var \easyLanguage\Parser\SeedProd
	 */
	private \easyLanguage\Parser\SeedProd $parser;

	/**
	 * Return the editor data as SeedProd saves it in post_content_filtered.
	 *
	 * @return array<string,mixed>
	 */
	private function get_data(): array {
		return array(
			'template_id' => 1,
			'document'    => array(
				'sections' => array(
					array(
						'id'       => 's1',
						'elType'   => 'section',
						'settings' => array( 'bgColor' => '#fff' ),
						'rows'     => array(
							array(
								'id'     => 'r1',
								'elType' => 'row',
								'cols'   => array(
									array(
										'id'     => 'c1',
										'elType' => 'col',
										'blocks' => array(
											array(
												'id'       => 'b1',
												'elType'   => 'block',
												'type'     => 'header',
												'settings' => array(
													'headerTxt' => 'Willkommen bei uns',
													'tag'       => 'h1',
												),
											),
											array(
												'id'       => 'b2',
												'elType'   => 'block',
												'type'     => 'text',
												'settings' => array( 'txt' => '<p>Das ist ein langer Text.</p>' ),
											),
											array(
												'id'       => 'b3',
												'elType'   => 'block',
												'type'     => 'button',
												'settings' => array( 'btnTxt' => 'Willkommen bei uns' ),
											),
										),
									),
								),
							),
						),
					),
				),
				'settings' => array( 'bodyColor' => '#000' ),
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

		// as administrator, like a simplification in the backend.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->post_id = self::factory()->post->create(
			array(
				'post_type'             => 'page',
				'post_content'          => '<h1>Willkommen bei uns</h1><p>Das ist ein langer Text.</p>',
				'post_content_filtered' => wp_slash( (string) wp_json_encode( $this->get_data() ) ),
			)
		);

		$this->parser = \easyLanguage\Parser\SeedProd::get_instance();
		$this->parser->set_object_id( $this->post_id );
		$this->parser->set_text( 'Willkommen bei uns' );
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
	 * Return the list of active plugins with SeedProd.
	 *
	 * @return array<int,string>
	 */
	public function get_active_plugins(): array {
		return array( 'coming-soon/coming-soon.php' );
	}

	/**
	 * Mark SeedProd as active.
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
		return json_decode( (string) get_post_field( 'post_content_filtered', $this->post_id ), true );
	}

	/**
	 * Without SeedProd nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$this->assertFalse( $this->parser->is_active() );
		$this->assertSame( array(), $this->parser->get_parsed_texts() );
		$this->assertSame( 'Willkommen bei uns', $this->parser->get_text_with_simplifications( 'Willkommen bei uns', 'Hallo' ) );
		$this->assertSame( $this->get_data(), $this->get_stored_data() );
	}

	/**
	 * The texts of the supported blocks are found.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		$this->activate();

		$this->assertSame(
			array(
				array(
					'text' => 'Willkommen bei uns',
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
	 * The text is replaced in the supported block only, the JSON stays valid.
	 *
	 * @return void
	 */
	public function test_replace(): void {
		$this->activate();

		// use characters which must be escaped in JSON and slashed for the database.
		$simplified = 'Hallo "liebe" Gäste \\ hier';
		$result     = $this->parser->get_text_with_simplifications( '<h1>Willkommen bei uns</h1>', $simplified );

		// the post_content.
		$this->assertSame( '<h1>' . $simplified . '</h1>', $result );

		// the editor data.
		$expected = $this->get_data();
		$expected['document']['sections'][0]['rows'][0]['cols'][0]['blocks'][0]['settings']['headerTxt'] = $simplified;
		$this->assertSame( $expected, $this->get_stored_data() );
	}

	/**
	 * The JSON stays valid if the replacement runs without a logged-in user (e.g. in a cron job), with HTML in it.
	 *
	 * @return void
	 */
	public function test_replace_without_user(): void {
		$this->activate();
		wp_set_current_user( 0 );
		$this->parser->set_text( '<p>Das ist ein langer Text.</p>' );

		$this->parser->get_text_with_simplifications( '', '<p>Ein <strong>kurzer</strong> Text.</p>' );

		$expected = $this->get_data();
		$expected['document']['sections'][0]['rows'][0]['cols'][0]['blocks'][1]['settings']['txt'] = '<p>Ein <strong>kurzer</strong> Text.</p>';
		$this->assertSame( $expected, $this->get_stored_data() );
	}

	/**
	 * Unknown texts change nothing.
	 *
	 * @return void
	 */
	public function test_replace_unknown_text(): void {
		$this->activate();
		$this->parser->set_text( 'Gibt es nicht' );

		$this->parser->get_text_with_simplifications( '', 'Neu' );

		$this->assertSame( $this->get_data(), $this->get_stored_data() );
	}

	/**
	 * Objects with editor data of SeedProd use the page builder.
	 *
	 * @return void
	 */
	public function test_is_object_using_pagebuilder(): void {
		$this->assertTrue( $this->parser->is_object_using_pagebuilder( new Post_Object( $this->post_id ) ) );

		$other_id = self::factory()->post->create();
		$this->assertFalse( $this->parser->is_object_using_pagebuilder( new Post_Object( $other_id ) ) );
	}
}
