<?php
/**
 * Tests for the output of the language switcher in the page builders.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\PageBuilder;

use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\PageBuilder\LiveComposer;
use easyLanguage\Tests\Switcher_Tests;
use WP_REST_Request;

/**
 * Object to test the language switcher of each page builder without the page builder itself.
 *
 * Each page builder saves the settings in its own format. Its render function converts them to the settings
 * of the switcher. These tests check this conversion and the output for all page builders.
 */
class Switcher_Outputs extends Switcher_Tests {

	/**
	 * Return the page builders with their output function.
	 *
	 * Each entry contains the name of the page builder in the CSS class of the output and a function, which
	 * gets the settings as the switcher uses them and returns the output in the format of the page builder.
	 *
	 * @return array<string,array{0: string}>
	 */
	public static function get_page_builders(): array {
		return array(
			'Avada'           => array( 'avada' ),
			'Fusion'          => array( 'fusion' ),
			'WPBakery'        => array( 'wpbakery' ),
			'Visual Composer' => array( 'visualcomposer' ),
			'Enfold'          => array( 'avia' ),
			'Bold'            => array( 'bold' ),
			'Themify'         => array( 'themify' ),
			'Beaver Builder'  => array( 'beaverbuilder' ),
			'Nimble'          => array( 'nimble' ),
			'Live Composer'   => array( 'live-composer' ),
			'Zion'            => array( 'zionbuilder' ),
			'Brizy'           => array( 'brizy' ),
			'Divi 5'          => array( 'divi5' ),
			'SiteOrigin'      => array( 'siteorigin' ),
		);
	}

	/**
	 * Convert a value of a switch to the format of shortcodes ("yes" or "").
	 *
	 * @param mixed $value The value.
	 *
	 * @return mixed
	 */
	private function to_shortcode_value( mixed $value ): mixed {
		return is_bool( $value ) ? ( $value ? 'yes' : '' ) : $value;
	}

	/**
	 * Return the output of the given page builder for the given settings of the switcher.
	 *
	 * @param string              $builder The name of the page builder.
	 * @param array<string,mixed> $values The settings of the switcher.
	 *
	 * @return string
	 */
	private function render( string $builder, array $values ): string {
		// settings as attributes of shortcodes.
		$shortcode = array_map( array( $this, 'to_shortcode_value' ), $values );

		switch ( $builder ) {
			case 'avada':
				return \easyLanguage\PageBuilder\Avada::get_instance()->render_shortcode( $shortcode );
			case 'fusion':
				return \easyLanguage\PageBuilder\Fusion::get_instance()->render_shortcode( $shortcode );
			case 'wpbakery':
				return \easyLanguage\PageBuilder\WpBakery::get_instance()->render_shortcode( $shortcode );
			case 'visualcomposer':
				return \easyLanguage\PageBuilder\VisualComposer::get_instance()->render_shortcode( $values );
			case 'avia':
				return \easyLanguage\PageBuilder\Avia::get_instance()->render( $shortcode );
			case 'bold':
				return \easyLanguage\PageBuilder\BoldBuilder::get_instance()->render_switcher( $shortcode );
			case 'themify':
				return \easyLanguage\PageBuilder\Themify::get_instance()->render_module( $shortcode );
			case 'beaverbuilder':
				return \easyLanguage\PageBuilder\BeaverBuilder::get_instance()->render_module( $shortcode );
			case 'nimble':
				return \easyLanguage\PageBuilder\Nimble::get_instance()->render( $values );
			case 'live-composer':
				$options = array( 'module_render_nonajax' => 1 );
				foreach ( $shortcode as $name => $value ) {
					$options[ LiveComposer::PREFIX . $name ] = $value;
				}
				return LiveComposer::get_instance()->render( $options );
			case 'zionbuilder':
				return \easyLanguage\PageBuilder\ZionBuilder::get_instance()->render( $values, false );
			case 'brizy':
				return \easyLanguage\PageBuilder\Brizy::get_instance()->render_placeholder( $shortcode );
			case 'divi5':
				$request = new WP_REST_Request( 'POST', '/easy-language/v1/divi5-switcher-preview' );
				$request->set_param( 'attrs', array( 'switcher' => array( 'advanced' => array( 'settings' => array( 'desktop' => array( 'value' => $values ) ) ) ) ) );
				return (string) \easyLanguage\PageBuilder\Divi5\Switcher_Module::get_instance()->rest_preview( $request )['html'];
			case 'siteorigin':
				return \easyLanguage\PageBuilder\SiteOrigin::get_instance()->render( $values );
		}
		return '';
	}

	/**
	 * The switcher with the default settings links to the simplification and has the class of the page builder.
	 *
	 * @dataProvider get_page_builders
	 *
	 * @param string $builder The name of the page builder.
	 *
	 * @return void
	 */
	public function test_output_with_defaults( string $builder ): void {
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );

		$html = $this->render( $builder, array() );

		$this->assertStringContainsString( 'easy-language-switcher-' . $builder, $html );
		$this->assertStringContainsString( (string) get_permalink( $this->ids['about_ls'] ), $html );
		$this->assertStringContainsString( 'easy-language-switcher-active', $html );
	}

	/**
	 * A switch of the page builder (here: hide the actual language) is converted.
	 *
	 * @dataProvider get_page_builders
	 *
	 * @param string $builder The name of the page builder.
	 *
	 * @return void
	 */
	public function test_output_with_switch( string $builder ): void {
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );

		// enabled.
		$html = $this->render( $builder, array( 'hide_actual_language' => true ) );
		$this->assertStringNotContainsString( 'easy-language-switcher-active', $html );
		$this->assertStringContainsString( (string) get_permalink( $this->ids['about_ls'] ), $html );

		// disabled.
		$html = $this->render( $builder, array( 'hide_actual_language' => false ) );
		$this->assertStringContainsString( 'easy-language-switcher-active', $html );
	}

	/**
	 * Invalid values (e.g. from manipulated settings of a page builder) do not end up in the output.
	 *
	 * @dataProvider get_page_builders
	 *
	 * @param string $builder The name of the page builder.
	 *
	 * @return void
	 */
	public function test_output_with_invalid_values( string $builder ): void {
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );

		$html = $this->render(
			$builder,
			array(
				'display'          => '"><script>alert(1)</script>',
				'icon_size'        => 'expression(alert(1))',
				'toggle_color_on'  => 'red;background:url(javascript:alert(1))',
				'toggle_size'      => '1px;position:fixed',
				'visibility'       => '<b>all</b>',
				'fallback'         => 'javascript:alert(1)',
				'toggle_language'  => '"><img src=x onerror=alert(1)>',
				'unknown_settings' => '<script>alert(1)</script>',
			)
		);

		// no HTML from the values (escaped values in attributes would be harmless, but they are not expected either).
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringNotContainsString( 'expression(', $html );
		$this->assertStringNotContainsString( 'position:fixed', $html );

		// the switcher is still shown with the default display.
		$this->assertStringContainsString( (string) get_permalink( $this->ids['about_ls'] ), $html );
	}

	/**
	 * Return the settings definitions of the page builders, which are generated from the fields of the switcher.
	 *
	 * @return array<string,array{0: string, 1: string}>
	 */
	public static function get_definitions(): array {
		return array(
			'WPBakery'        => array( 'wpbakery', '' ),
			'Bold'            => array( 'bold', '' ),
			'Live Composer'   => array( 'live-composer', LiveComposer::PREFIX ),
			'Themify'         => array( 'themify', '' ),
			'Zion'            => array( 'zionbuilder', '' ),
			'Visual Composer' => array( 'visualcomposer', '' ),
			'Enfold'          => array( 'avia', '' ),
			'Beaver Builder'  => array( 'beaverbuilder', '' ),
			'Divi 5'          => array( 'divi5', '' ),
		);
	}

	/**
	 * Every field of the switcher is available in the settings of each page builder.
	 *
	 * If a new field is added to the switcher, this test shows each page builder, which does not offer it.
	 *
	 * @dataProvider get_definitions
	 *
	 * @param string $builder The name of the page builder.
	 * @param string $prefix The prefix of the field names in this page builder.
	 *
	 * @return void
	 */
	public function test_settings_contain_all_fields( string $builder, string $prefix ): void {
		$definition = match ( $builder ) {
			'wpbakery'       => \easyLanguage\PageBuilder\WpBakery::get_instance()->get_element_mapping(),
			'bold'           => \easyLanguage\PageBuilder\BoldBuilder::get_instance()->get_element_mapping(),
			'live-composer'  => LiveComposer::get_instance()->get_module_options(),
			'themify'        => \easyLanguage\PageBuilder\Themify::get_instance()->get_module_options(),
			'zionbuilder'    => \easyLanguage\PageBuilder\ZionBuilder::get_instance()->get_element_options(),
			'visualcomposer' => \easyLanguage\PageBuilder\VisualComposer::get_instance()->get_element_settings(),
			'avia'           => \easyLanguage\PageBuilder\Avia::get_instance()->get_popup_elements(),
			'beaverbuilder'  => \easyLanguage\PageBuilder\BeaverBuilder::get_instance()->get_module_form(),
			'divi5'          => \easyLanguage\PageBuilder\Divi5\Switcher_Module::get_instance()->get_metadata(),
			default          => array(),
		};
		$json = (string) wp_json_encode( $definition );
		$this->assertNotEmpty( $definition );

		foreach ( array_keys( Switcher::get_instance()->get_widget_fields() ) as $name ) {
			$this->assertStringContainsString( '"' . $prefix . $name . '"', $json, 'Field ' . $name . ' is missing in the settings of ' . $builder . '.' );
		}
	}
}
