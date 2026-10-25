<?php
/**
 * Tests for the settings and output of the language switcher widgets in page builders.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\Tests\Switcher_Tests;

/**
 * Object to test get_widget_fields() and render_widget() in class easyLanguage\EasyLanguage\Switcher.
 *
 * All page builder widgets are based on these functions.
 */
class Switcher_Widget extends Switcher_Tests {

	/**
	 * Test that each field definition is complete and consistent.
	 *
	 * @return void
	 */
	public function test_widget_fields_are_complete(): void {
		$switcher = Switcher::get_instance();
		$fields   = $switcher->get_widget_fields();
		$sections = $switcher->get_widget_sections();

		$this->assertNotEmpty( $fields );
		foreach ( $fields as $name => $field ) {
			$this->assertContains( $field['type'], array( 'select', 'switch', 'size', 'color', 'objects' ), 'Unknown type of field ' . $name );
			$this->assertIsString( $field['label'], 'Missing label of field ' . $name );
			$this->assertNotEmpty( $field['label'], 'Empty label of field ' . $name );
			$this->assertArrayHasKey( $field['section'], $sections, 'Unknown section of field ' . $name );
			$this->assertArrayHasKey( 'default', $field, 'Missing default of field ' . $name );

			// selects need options which contain the default.
			if ( 'select' === $field['type'] ) {
				$this->assertNotEmpty( $field['options'], 'Missing options of field ' . $name );
				$this->assertArrayHasKey( $field['default'], $field['options'], 'Default is not an option of field ' . $name );
			}

			// sizes need units.
			if ( 'size' === $field['type'] ) {
				$this->assertNotEmpty( $field['units'], 'Missing units of field ' . $name );
			}

			// bail if the field has no conditions.
			if ( empty( $field['condition'] ) ) {
				continue;
			}

			// conditions must point to existing fields and possible values.
			foreach ( $field['condition'] as $condition_field => $values ) {
				$this->assertArrayHasKey( $condition_field, $fields, 'Condition of field ' . $name . ' uses unknown field ' . $condition_field );
				$this->assertIsArray( $values );
				$this->assertNotEmpty( $values );
				if ( 'select' === $fields[ $condition_field ]['type'] ) {
					foreach ( $values as $value ) {
						$this->assertArrayHasKey( $value, $fields[ $condition_field ]['options'], 'Condition of field ' . $name . ' uses unknown value ' . $value );
					}
				}
			}
		}
	}

	/**
	 * Test that the widgets offer the same settings as the blocks.
	 *
	 * @return void
	 */
	public function test_widget_fields_cover_block_attributes(): void {
		$switcher = Switcher::get_instance();
		$fields   = $switcher->get_widget_fields();

		// these block attributes are only used by the blocks.
		$block_only = array( 'preview', 'show_icons', 'markup_version' );

		foreach ( array_keys( $switcher->get_block_attributes() ) as $attribute ) {
			if ( in_array( $attribute, $block_only, true ) ) {
				continue;
			}
			$this->assertArrayHasKey( $attribute, $fields, 'The widgets do not support the block attribute ' . $attribute );
		}
	}

	/**
	 * Test that the defaults of the fields match the defaults of the block attributes.
	 *
	 * @return void
	 */
	public function test_widget_defaults_match_block_defaults(): void {
		$switcher   = Switcher::get_instance();
		$attributes = $switcher->get_block_attributes();

		foreach ( $switcher->get_widget_fields() as $name => $field ) {
			// "display" is empty in blocks to support the setting "show_icons" of old blocks.
			if ( 'display' === $name || ! isset( $attributes[ $name ] ) ) {
				continue;
			}
			$this->assertEquals( $attributes[ $name ]['default'], $field['default'], 'Different default for ' . $name );
		}
	}

	/**
	 * Test the filter for the widget fields.
	 *
	 * @return void
	 */
	public function test_widget_fields_filter(): void {
		$callback = function ( array $fields ): array {
			unset( $fields['fallback'] );
			return $fields;
		};
		add_filter( 'easy_language_switcher_widget_fields', $callback );
		$this->assertArrayNotHasKey( 'fallback', Switcher::get_instance()->get_widget_fields() );
		remove_filter( 'easy_language_switcher_widget_fields', $callback );
		$this->assertArrayHasKey( 'fallback', Switcher::get_instance()->get_widget_fields() );
	}

	/**
	 * Test the output of a widget with default values.
	 *
	 * @return void
	 */
	public function test_render_widget_with_defaults(): void {
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );
		$html = Switcher::get_instance()->render_widget( array(), 'test-builder' );

		// the widgets use the wrapper of new blocks with the name of the page builder.
		$this->assertStringStartsWith( '<div class="easy-language-switcher easy-language-switcher-test-builder', $html );
		$this->assertStringContainsString( 'easy-language-switcher-title', $html );
		$this->assertStringContainsString( (string) get_permalink( $this->ids['about_ls'] ), $html );
	}

	/**
	 * Test the conversion of the values of page builders.
	 *
	 * @return void
	 */
	public function test_render_widget_converts_values(): void {
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );
		$switcher = Switcher::get_instance();

		// switches as "on", "yes", "1" and true hide the actual language.
		foreach ( array( 'on', 'yes', '1', true ) as $value ) {
			$html = $switcher->render_widget( array( 'hide_actual_language' => $value ), 'test' );
			$this->assertStringNotContainsString( 'easy-language-switcher-active', $html, 'Switch value ' . wp_json_encode( $value ) . ' not converted.' );
		}

		// switches as "no", "off", "" and false show it.
		foreach ( array( 'no', 'off', '', false ) as $value ) {
			$html = $switcher->render_widget( array( 'hide_actual_language' => $value ), 'test' );
			$this->assertStringContainsString( 'easy-language-switcher-active', $html, 'Switch value ' . wp_json_encode( $value ) . ' not converted.' );
		}

		// sizes as array (e.g. Elementor) and as string.
		$html = $switcher->render_widget(
			array(
				'display'   => 'icons',
				'icon_size' => array(
					'size' => 32,
					'unit' => 'px',
				),
			),
			'test'
		);
		$this->assertStringContainsString( '--easy-language-icon-size:32px', $html );
		$html = $switcher->render_widget(
			array(
				'display'   => 'icons',
				'icon_size' => '2em',
			),
			'test'
		);
		$this->assertStringContainsString( '--easy-language-icon-size:2em', $html );

		// design of the toggle.
		$html = $switcher->render_widget(
			array(
				'display'          => 'toggle',
				'toggle_color_on'  => '#ff0000',
				'toggle_size'      => array(
					'size' => 30,
					'unit' => 'px',
				),
				'toggle_radius'    => '0',
				'toggle_color_off' => 'not a color',
			),
			'test'
		);
		$this->assertStringContainsString( '--easy-language-toggle-on:#ff0000', $html );
		$this->assertStringContainsString( '--easy-language-toggle-size:30px', $html );
		$this->assertStringContainsString( '--easy-language-toggle-radius:0px', $html );
		$this->assertStringNotContainsString( 'not a color', $html );
	}

	/**
	 * Test the visibility settings of a widget in all formats of the page builders.
	 *
	 * @return void
	 */
	public function test_render_widget_visibility(): void {
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );
		$switcher = Switcher::get_instance();

		// only on another page: as comma separated string and as array.
		foreach ( array( 'post:' . $this->ids['contact'], array( 'post:' . $this->ids['contact'] ) ) as $objects ) {
			$values = array(
				'visibility'         => 'include',
				'visibility_objects' => $objects,
			);
			$this->assertEquals( '', $switcher->render_widget( $values, 'test' ) );

			// in the editor of a page builder the widget is always visible.
			$this->assertNotEmpty( $switcher->render_widget( $values, 'test', true ) );
		}

		// everywhere except on another page.
		$this->assertNotEmpty(
			$switcher->render_widget(
				array(
					'visibility'         => 'exclude',
					'visibility_objects' => 'post:' . $this->ids['contact'],
				),
				'test'
			)
		);
	}

	/**
	 * Test that widgets always use the actual output, also in compatibility mode.
	 *
	 * @return void
	 */
	public function test_render_widget_ignores_compatibility_mode(): void {
		update_option( 'easy_language_switcher_compatibility', 1 );
		$this->go_to( (string) get_permalink( $this->ids['contact'] ) );

		// the page has no simplification: the simplified language is hidden because of the link-mode.
		$html = Switcher::get_instance()->render_widget( array(), 'test' );
		$this->assertStringNotContainsString( 'Leichte Sprache', $html );
		$this->assertStringContainsString( 'aria-current="page"', $html );
	}
}
