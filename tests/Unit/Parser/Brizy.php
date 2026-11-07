<?php
/**
 * Tests for the parser for Brizy.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the parser for Brizy, which stores its editor data as base64-encoded JSON in the post meta "brizy".
 */
class Brizy extends easyLanguageTests {
	/**
	 * Return the editor data like Brizy saves it.
	 *
	 * @param string $text The text of the first rich text element.
	 *
	 * @return array<string,mixed>
	 */
	private function get_editor_data( string $text = '<p>Ein komplizierter Text.</p>' ): array {
		$rich_text = static function ( string $id, string $content ): array {
			return array(
				'type'  => 'RichText',
				'value' => array(
					'_id'     => $id,
					'text'    => $content,
					'_styles' => array( 'richText' ),
				),
			);
		};

		return array(
			'type'  => 'Page',
			'items' => array(
				array(
					'type'  => 'Section',
					'value' => array(
						'_id'   => 'sec1',
						'items' => array(
							array(
								'type'  => 'SectionItem',
								'value' => array(
									'_id'   => 'item1',
									'items' => array(
										array(
											'type'  => 'Wrapper',
											'value' => array(
												'_id'   => 'wrap1',
												'items' => array( $rich_text( 'rt1', $text ) ),
											),
										),
										array(
											'type'  => 'Wrapper',
											'value' => array(
												'_id'   => 'wrap2',
												'items' => array( $rich_text( 'rt2', '<p>Ein anderer Text.</p>' ) ),
											),
										),
										array(
											'type'  => 'Button',
											'value' => array(
												'_id'  => 'btn1',
												'text' => '<p>Ein komplizierter Text.</p>',
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
	 * Return the meta "brizy" like Brizy saves it.
	 *
	 * @param array<string,mixed> $editor_data The editor data.
	 *
	 * @return array<string,mixed>
	 */
	private function get_meta( array $editor_data ): array {
		return array(
			'brizy-post' => array(
				'editor_version' => '2.4.40',
				'editor_data'    => base64_encode( (string) wp_json_encode( $editor_data ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'compiled_html'  => '',
			),
		);
	}

	/**
	 * Return the decoded editor data of a post.
	 *
	 * @param int $post_id The ID of the post.
	 *
	 * @return mixed
	 */
	private function get_stored_editor_data( int $post_id ): mixed {
		$meta = get_post_meta( $post_id, 'brizy', true );
		return json_decode( base64_decode( $meta['brizy-post']['editor_data'] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * Activate Brizy.
	 *
	 * @return void
	 */
	private function activate(): void {
		update_option( 'active_plugins', array( 'brizy/brizy.php' ) );
	}

	/**
	 * Return the parser for a new post with Brizy data.
	 *
	 * @param string $text The text to parse.
	 *
	 * @return \easyLanguage\Parser\Brizy
	 */
	private function get_parser( string $text ): \easyLanguage\Parser\Brizy {
		$post_id = self::factory()->post->create( array( 'post_content' => '<p>Ein komplizierter Text.</p>' ) );
		update_post_meta( $post_id, 'brizy', $this->get_meta( $this->get_editor_data() ) );

		$parser = \easyLanguage\Parser\Brizy::get_instance();
		$parser->set_object_id( $post_id );
		$parser->set_text( $text );
		return $parser;
	}

	/**
	 * Without Brizy nothing is parsed or replaced.
	 *
	 * @return void
	 */
	public function test_not_active(): void {
		$parser = $this->get_parser( '<p>Ein komplizierter Text.</p>' );

		$this->assertFalse( $parser->is_active() );
		$this->assertSame( array(), $parser->get_parsed_texts() );
		$this->assertSame( 'Inhalt', $parser->get_text_with_simplifications( 'Inhalt', 'Einfach' ) );
		$this->assertSame( $this->get_editor_data(), $this->get_stored_editor_data( $parser->get_object_id() ) );
	}

	/**
	 * The texts of the rich text elements are found in the nested items.
	 *
	 * @return void
	 */
	public function test_parsed_texts(): void {
		$this->activate();

		$this->assertSame(
			array(
				array(
					'text' => '<p>Ein komplizierter Text.</p>',
					'html' => true,
				),
				array(
					'text' => '<p>Ein anderer Text.</p>',
					'html' => true,
				),
			),
			$this->get_parser( '' )->get_parsed_texts()
		);
	}

	/**
	 * The simplification is saved in the editor data, which stays valid, the other elements are unchanged.
	 *
	 * @return void
	 */
	public function test_replacement(): void {
		$this->activate();
		$parser     = $this->get_parser( '<p>Ein komplizierter Text.</p>' );
		$simplified = '<p>Ein "einfacher" Text mit Ümlaut & \\ Backslash.</p>';

		$this->assertSame( $simplified, $parser->get_text_with_simplifications( '<p>Ein komplizierter Text.</p>', $simplified ) );
		$this->assertSame( $this->get_editor_data( $simplified ), $this->get_stored_editor_data( $parser->get_object_id() ) );

		// the other data of Brizy is kept.
		$meta = get_post_meta( $parser->get_object_id(), 'brizy', true );
		$this->assertSame( '2.4.40', $meta['brizy-post']['editor_version'] );
	}

	/**
	 * Invalid editor data is not changed.
	 *
	 * @return void
	 */
	public function test_invalid_editor_data(): void {
		$this->activate();
		$parser = $this->get_parser( '<p>Ein komplizierter Text.</p>' );
		update_post_meta( $parser->get_object_id(), 'brizy', array( 'brizy-post' => array( 'editor_data' => base64_encode( 'kein JSON' ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$this->assertSame( array(), $parser->get_parsed_texts() );
		$this->assertSame( 'Inhalt', $parser->get_text_with_simplifications( 'Inhalt', 'Einfach' ) );
	}

	/**
	 * An object uses Brizy if Brizy is enabled for it.
	 *
	 * @return void
	 */
	public function test_object_using_pagebuilder(): void {
		$this->activate();

		$post_id = self::factory()->post->create();
		$this->assertFalse( \easyLanguage\Parser\Brizy::get_instance()->is_object_using_pagebuilder( new Post_Object( $post_id ) ) );

		update_post_meta( $post_id, 'brizy_enabled', 1 );
		$this->assertTrue( \easyLanguage\Parser\Brizy::get_instance()->is_object_using_pagebuilder( new Post_Object( $post_id ) ) );
	}
}
