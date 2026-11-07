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
	 * Return whether our classic widget is usable in widget areas.
	 *
	 * WordPress removes widgets from the widget factory, if "widgets_init" runs more than once (as in this test
	 * environment), but keeps them in the list of widgets for the widget areas. So both lists are checked.
	 *
	 * @return bool
	 */
	private function is_classic_widget_registered(): bool {
		global $wp_widget_factory, $wp_registered_widgets;

		// check the widget factory.
		if ( ( $wp_widget_factory->widgets[ SiteOrigin::PLAIN_WIDGET_CLASS ] ?? null ) instanceof \WP_Widget ) {
			return true;
		}

		// check the widgets for the widget areas.
		foreach ( $wp_registered_widgets as $widget ) {
			if ( isset( $widget['callback'][0] ) && is_a( $widget['callback'][0], SiteOrigin::PLAIN_WIDGET_CLASS ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Return the output of the classic widget.
	 *
	 * @param array<string,mixed> $instance The widget instance.
	 *
	 * @return string
	 */
	private function get_widget_output( array $instance ): string {
		global $wp_widget_factory;

		// the_widget() needs the widget in the widget factory (see is_classic_widget_registered()).
		if ( ! ( $wp_widget_factory->widgets[ SiteOrigin::PLAIN_WIDGET_CLASS ] ?? null ) instanceof \WP_Widget ) {
			register_widget( SiteOrigin::PLAIN_WIDGET_CLASS );
		}

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
	 * The classic widget is registered without any page builder, before WordPress registers the widgets for the
	 * widget areas.
	 *
	 * @return void
	 */
	public function test_widget_is_registered_without_page_builder(): void {
		global $wp_widget_factory;

		$priority = has_action( 'widgets_init', array( SiteOrigin::get_instance(), 'register_plain_widget' ) );
		$this->assertIsInt( $priority );
		$this->assertLessThan( has_action( 'widgets_init', array( $wp_widget_factory, '_register_widgets' ) ), $priority );

		$this->assertTrue( $this->is_classic_widget_registered() );
	}

	/**
	 * The widget is not registered, if another widget uses its ID (e.g. the widget of the Widgets Bundle).
	 *
	 * @return void
	 */
	public function test_widget_is_not_registered_if_id_is_used(): void {
		global $wp_widget_factory;

		// another widget with our ID.
		$other = new class() extends \WP_Widget {
			/**
			 * Constructor.
			 */
			public function __construct() {
				parent::__construct( SiteOrigin::WIDGET_ID, 'Other' );
			}
		};
		unregister_widget( SiteOrigin::PLAIN_WIDGET_CLASS );
		register_widget( $other );

		SiteOrigin::get_instance()->register_plain_widget();
		$this->assertArrayNotHasKey( SiteOrigin::PLAIN_WIDGET_CLASS, $wp_widget_factory->widgets );

		// without the other widget our widget is registered (also if it is registered again).
		unregister_widget( $other );
		SiteOrigin::get_instance()->register_plain_widget();
		SiteOrigin::get_instance()->register_plain_widget();
		$this->assertInstanceOf( SiteOrigin::PLAIN_WIDGET_CLASS, $wp_widget_factory->widgets[ SiteOrigin::PLAIN_WIDGET_CLASS ] ?? null );
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
