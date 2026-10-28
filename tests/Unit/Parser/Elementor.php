<?php
/**
 * Tests for the parser for Elementor.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for Elementor, which stores its elements as JSON in the post meta "_elementor_data".
 */
class Elementor extends easyLanguageTests {
	/**
	 * Load the fakes for the classes of Elementor.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		require_once dirname( __DIR__, 2 ) . '/Fakes/Parser/Elementor/classes.php';
	}

	/**
	 * Return a widget like Elementor saves it.
	 *
	 * @param string              $id          The ID.
	 * @param string              $widget_type The type of the widget.
	 * @param array<string,mixed> $settings    The settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_widget( string $id, string $widget_type, array $settings ): array {
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => $widget_type,
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	/**
	 * Return the elements like Elementor saves them: a container with widgets and a nested container.
	 *
	 * @param string $text The text of the first text editor.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_elements( string $text = '<p>Ein komplizierter Text.</p>' ): array {
		return array(
			array(
				'id'       => 'c1',
				'elType'   => 'container',
				'settings' => array( 'content_width' => 'boxed' ),
				'elements' => array(
					$this->get_widget( 'w1', 'heading', array( 'title' => 'Unsere Leistungen', 'header_size' => 'h2' ) ),
					$this->get_widget( 'w2', 'text-editor', array( 'editor' => $text ) ),
					array(
						'id'       => 'c2',
						'elType'   => 'container',
						'settings' => array(),
						'elements' => array(
							$this->get_widget( 'w3', 'button', array( 'text' => 'Mehr erfahren', 'link' => array( 'url' => '#' ) ) ),
							$this->get_widget(
								'w4',
								'icon-list',
								array(
									'icon_list' => array(
										array(
											'_id'  => 'i1',
											'text' => 'Erster Punkt',
										),
										array(
											'_id'  => 'i2',
											'text' => '<p>Ein komplizierter Text.</p>',
										),
									),
								)
							),
							$this->get_widget( 'w5', 'image', array( 'caption' => '<p>Ein komplizierter Text.</p>' ) ),
						),
					),
				),
			),
		);
	}

	/**
	 * Activate Elementor (it is detected by its action "elementor/loaded", which is reset after each test).
	 *
	 * @return void
	 */
	private function activate(): void {
		do_action( 'elementor/loaded' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	/**
	 * Return the parser for a new post with Elementor data.
	 *
	 * @param string $text The text to parse.
	 *
	 * @return \easyLanguage\Parser\Elementor
	 */
	private function get_parser( string $text ): \easyLanguage\Parser\Elementor {
		$post_id = self::factory()->post->create( array( 'post_content' => '<p>Ein komplizierter Text.</p>' ) );
		update_post_meta( $post_id, '_elementor_data', wp_slash( (string) wp_json_encode( $this->get_elements() ) ) );

		$parser = \easyLanguage\Parser\Elementor::get_instance();
		$parser->set_object_id( $post_id );
		$parser->set_text( $text );
		return $parser;
	}

	/**
	 * Return the decoded elements of a post.
	 *
	 * @param int $post_id The ID of the post.
	 *
	 * @return mixed
	 */
	private function get_stored_elements( int $post_id ): mixed {
		return json_decode( (string) get_post_meta( $post_id, '_elementor_data', true ), true );
	}

	/**
	 * Without Elementor nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$parser = $this->get_parser( '<p>Ein komplizierter Text.</p>' );

		$this->assertFalse( $parser->is_active() );
		$this->assertSame( array(), $parser->get_parsed_texts() );
		$this->assertSame( 'Inhalt', $parser->get_text_with_simplifications( 'Inhalt', 'Einfach' ) );
		$this->assertSame( $this->get_elements(), $this->get_stored_elements( $parser->get_object_id() ) );
	}

	/**
	 * The texts of the supported widgets are found, also in nested containers and repeater fields.
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
				array(
					'text' => 'Mehr erfahren',
					'html' => false,
				),
				array(
					'text' => 'Erster Punkt',
					'html' => false,
				),
				array(
					'text' => '<p>Ein komplizierter Text.</p>',
					'html' => false,
				),
			),
			$this->get_parser( '' )->get_parsed_texts()
		);
	}

	/**
	 * The simplification is saved in all supported widgets with this text, the JSON stays valid.
	 *
	 * @return void
	 */
	public function test_replacement(): void {
		$this->activate();
		$parser     = $this->get_parser( '<p>Ein komplizierter Text.</p>' );
		$simplified = '<p>Ein "einfacher" Text mit Ümlaut & \\ Backslash.</p>';

		$this->assertSame( $simplified, $parser->get_text_with_simplifications( '<p>Ein komplizierter Text.</p>', $simplified ) );

		// the text editor and the item of the icon list are changed, the caption of the unsupported image widget is not.
		$expected = $this->get_elements( $simplified );
		$expected[0]['elements'][2]['elements'][1]['settings']['icon_list'][1]['text'] = $simplified;
		$this->assertSame( $expected, $this->get_stored_elements( $parser->get_object_id() ) );
	}

	/**
	 * The edit link opens the Elementor editor.
	 *
	 * @return void
	 */
	public function test_edit_link(): void {
		$this->activate();
		$parser = $this->get_parser( '' );

		$this->assertSame( admin_url( 'post.php?post=' . $parser->get_object_id() . '&action=elementor' ), $parser->get_edit_link() );
	}

	/**
	 * Updating an object regenerates its CSS file of Elementor.
	 *
	 * @return void
	 */
	public function test_update_object_updates_css(): void {
		$post_id = self::factory()->post->create();

		\easyLanguage\Parser\Elementor::get_instance()->update_object( new Post_Object( $post_id ) );

		$this->assertContains( $post_id, \Elementor\Core\Files\CSS\Post::$updated );
	}

	/**
	 * An object uses Elementor if it has Elementor data.
	 *
	 * @return void
	 */
	public function test_object_using_pagebuilder(): void {
		$this->assertFalse( (bool) \easyLanguage\Parser\Elementor::get_instance()->is_object_using_pagebuilder( new Post_Object( self::factory()->post->create() ) ) );

		$parser = $this->get_parser( '' );
		$this->assertTrue( (bool) $parser->is_object_using_pagebuilder( new Post_Object( $parser->get_object_id() ) ) );
	}
}
