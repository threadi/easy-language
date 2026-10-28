<?php
/**
 * Tests for the trait easyLanguage\Parser\Shortcode_Replacement.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Parser;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the replacement of texts in shortcodes (used by WPBakery and Salient WPBakery).
 */
class Shortcode_Replacement extends easyLanguageTests {
	/**
	 * The supported shortcodes and their text attributes (like WPBakery uses them).
	 *
	 * @var array<string,array<int,string>>
	 */
	private array $shortcodes = array(
		'vc_column_text' => array(),
		'vc_btn'         => array( 'title' ),
	);

	/**
	 * Replace the text with an object which uses the trait.
	 *
	 * @param string $content         The complete content.
	 * @param string $original_text   The original text.
	 * @param string $simplified_part The simplified text.
	 *
	 * @return string
	 */
	private function replace( string $content, string $original_text, string $simplified_part ): string {
		$obj = new class() {
			use \easyLanguage\Parser\Shortcode_Replacement;

			/**
			 * Run the replacement.
			 *
			 * @param string                          $content         The complete content.
			 * @param string                          $original_text   The original text.
			 * @param string                          $simplified_part The simplified text.
			 * @param array<string,array<int,string>> $shortcodes      The supported shortcodes.
			 *
			 * @return string
			 */
			public function run( string $content, string $original_text, string $simplified_part, array $shortcodes ): string {
				return $this->replace_text_in_shortcodes( $content, $original_text, $simplified_part, $shortcodes );
			}
		};
		return $obj->run( $content, $original_text, $simplified_part, $this->shortcodes );
	}

	/**
	 * The content of a shortcode is only replaced if it is exactly the original text.
	 *
	 * @return void
	 */
	public function test_content_is_replaced_only_if_identical(): void {
		$content = '[vc_column_text]Ein Text.[/vc_column_text][vc_column_text]Ein Text. Und mehr.[/vc_column_text]';

		$this->assertSame(
			'[vc_column_text]Einfacher Text.[/vc_column_text][vc_column_text]Ein Text. Und mehr.[/vc_column_text]',
			$this->replace( $content, 'Ein Text.', 'Einfacher Text.' )
		);
	}

	/**
	 * Texts outside of supported shortcodes and in other attributes stay unchanged.
	 *
	 * @return void
	 */
	public function test_other_content_stays_unchanged(): void {
		$content = 'Mehr erfahren [vc_custom_heading text="Mehr erfahren"][vc_btn title="Mehr erfahren" link="Mehr erfahren"]';

		$this->assertSame(
			'Mehr erfahren [vc_custom_heading text="Mehr erfahren"][vc_btn title="Weiter lesen" link="Mehr erfahren"]',
			$this->replace( $content, 'Mehr erfahren', 'Weiter lesen' )
		);
	}

	/**
	 * Escaped shortcodes like [[vc_btn]] are not changed.
	 *
	 * @return void
	 */
	public function test_escaped_shortcode_stays_unchanged(): void {
		$content = '[[vc_btn title="Klick"]]';

		$this->assertSame( $content, $this->replace( $content, 'Klick', 'Hier klicken' ) );
	}

	/**
	 * Characters which would break the shortcode are masked in attributes.
	 *
	 * @return void
	 */
	public function test_attribute_value_is_masked(): void {
		$result = $this->replace( '[vc_btn title="Klick" link="#"]', 'Klick', 'Sag "Ja" [jetzt]' );

		$this->assertSame( '[vc_btn title="Sag &quot;Ja&quot; &#91;jetzt&#93;" link="#"]', $result );

		// the shortcode could still be parsed and returns the masked value.
		preg_match( '/' . get_shortcode_regex( array( 'vc_btn' ) ) . '/s', $result, $matches );
		$atts = shortcode_parse_atts( $matches[3] );
		$this->assertSame( 'Sag &quot;Ja&quot; &#91;jetzt&#93;', $atts['title'] );
		$this->assertSame( '#', $atts['link'] );
	}

	/**
	 * Single quoted, unquoted and uppercase attributes are replaced with their quote kept.
	 *
	 * @return void
	 */
	public function test_attribute_variants(): void {
		$this->assertSame( "[vc_btn title='Sag &#039;Ja&#039;']", $this->replace( "[vc_btn title='Klick']", 'Klick', "Sag 'Ja'" ) );
		$this->assertSame( '[vc_btn title="Los"]', $this->replace( '[vc_btn title=Klick]', 'Klick', 'Los' ) );
		$this->assertSame( '[vc_btn TITLE="Los"]', $this->replace( '[vc_btn TITLE="Klick"]', 'Klick', 'Los' ) );
	}

	/**
	 * Nested shortcodes keep their structure.
	 *
	 * @return void
	 */
	public function test_nested_structure_stays_intact(): void {
		$content = '[vc_row][vc_column width="1/2"][vc_column_text css=".x{}"]<p>Alt</p>[/vc_column_text][/vc_column][/vc_row]';

		$this->assertSame(
			'[vc_row][vc_column width="1/2"][vc_column_text css=".x{}"]<p>Neu</p>[/vc_column_text][/vc_column][/vc_row]',
			$this->replace( $content, '<p>Alt</p>', '<p>Neu</p>' )
		);
	}

	/**
	 * An empty original text changes nothing.
	 *
	 * @return void
	 */
	public function test_empty_original_text(): void {
		$content = '[vc_column_text][/vc_column_text][vc_btn title=""]';

		$this->assertSame( $content, $this->replace( $content, '', 'Neu' ) );
	}
}
