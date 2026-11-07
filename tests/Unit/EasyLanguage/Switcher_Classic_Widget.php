<?php
/**
 * Tests for the classic widget of the language switcher.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\PageBuilder\SiteOrigin;
use easyLanguage\Tests\Switcher_Tests;

/**
 * Object to test the classic widget, which is available in all widget areas.
 */
class Switcher_Classic_Widget extends Switcher_Tests {

	/**
	 * Return the registered widgets with our widget ID.
	 *
	 * @return array<int,\WP_Widget>
	 */
	private function get_registered_switcher_widgets(): array {
		global $wp_widget_factory;
		return array_values(
			array_filter(
				$wp_widget_factory->widgets,
				function ( $widget ) {
					return $widget instanceof \WP_Widget && SiteOrigin::WIDGET_ID === $widget->id_base;
				}
			)
		);
	}

	/**
	 * Return the output of the classic widget.
	 *
	 * @param array<string,mixed> $instance The widget instance.
	 *
	 * @return string
	 */
	private function get_widget_output( array $instance ): string {
		ob_start();
		the_widget(
			SiteOrigin::PLAIN_WIDGET_CLASS,
			$instance,
			array(
				'before_widget' => '<section class="widget-area-test">',
				'after_widget'  => '</section>',
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * The classic widget is registered without any page builder.
	 *
	 * @return void
	 */
	public function test_widget_is_registered_without_page_builder(): void {
		$widgets = $this->get_registered_switcher_widgets();
		$this->assertCount( 1, $widgets );
		$this->assertInstanceOf( SiteOrigin::PLAIN_WIDGET_CLASS, $widgets[0] );
	}

	/**
	 * The widget is registered only once (e.g. if the Widgets Bundle has registered our widget ID before).
	 *
	 * @return void
	 */
	public function test_widget_is_not_registered_twice(): void {
		SiteOrigin::get_instance()->register_plain_widget();
		SiteOrigin::get_instance()->register_plain_widget();

		$this->assertCount( 1, $this->get_registered_switcher_widgets() );
	}

	/**
	 * The classic widget shows the switcher in a widget area.
	 *
	 * @return void
	 */
	public function test_widget_output(): void {
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );

		$html = $this->get_widget_output( array() );

		$this->assertStringStartsWith( '<section class="widget-area-test">', $html );
		$this->assertStringContainsString( 'easy-language-switcher-widget', $html );
		$this->assertStringContainsString( (string) get_permalink( $this->ids['about_ls'] ), $html );
		$this->assertStringEndsWith( '</section>', $html );
	}

	/**
	 * The settings of the widget are used (e.g. hide the actual language).
	 *
	 * @return void
	 */
	public function test_widget_uses_settings(): void {
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );

		// without the setting the actual language is shown.
		$this->assertStringContainsString( 'easy-language-switcher-active', $this->get_widget_output( array() ) );

		// with the setting the actual language is hidden, the other language is still shown.
		$html = $this->get_widget_output( array( 'hide_actual_language' => true ) );
		$this->assertStringNotContainsString( 'easy-language-switcher-active', $html );
		$this->assertStringContainsString( (string) get_permalink( $this->ids['about_ls'] ), $html );
	}

	/**
	 * In layouts of Page Builder by SiteOrigin the widget uses the output of the page builder.
	 *
	 * @return void
	 */
	public function test_widget_in_page_builder_layout(): void {
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );

		$html = $this->get_widget_output( array( 'panels_info' => array( 'class' => SiteOrigin::PLAIN_WIDGET_CLASS ) ) );

		$this->assertStringContainsString( 'easy-language-switcher-siteorigin', $html );
	}

	/**
	 * The widget form saves only known values.
	 *
	 * @return void
	 */
	public function test_widget_update_sanitizes_values(): void {
		$widget   = new \easyLanguage\PageBuilder\SiteOrigin\Switcher_Widget();
		$instance = $widget->update(
			array(
				'hide_actual_language' => '1',
				'unknown_field'        => 'value',
				'icon_size'            => '<b>24px</b>',
			),
			array()
		);

		$this->assertTrue( $instance['hide_actual_language'] );
		$this->assertArrayNotHasKey( 'unknown_field', $instance );
		$this->assertSame( '24px', $instance['icon_size'] );
	}
}
