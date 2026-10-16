<?php
/**
 * File for handling WPBakery Page Builder as page builder.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\Plugin\Helper;

/**
 * Object to handle WPBakery Page Builder as page builder.
 *
 * Adds a language switcher element to WPBakery Page Builder.
 */
class WpBakery extends PageBuilder_Base {
	/**
	 * The shortcode of our element.
	 *
	 * @var string
	 */
	private const SHORTCODE = 'easy_language_switcher_wpbakery';

	/**
	 * Instance of this object.
	 *
	 * @var ?WpBakery
	 */
	private static ?WpBakery $instance = null;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): WpBakery {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'vc_before_init', array( $this, 'register_element' ) );
	}

	/**
	 * Register our language switcher element in WPBakery.
	 *
	 * @return void
	 */
	public function register_element(): void {
		// bail if WPBakery is not active.
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		// add the shortcode for the output.
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );

		// get the labels.
		$labels = Switcher_Helper::get_labels();

		// map the element.
		vc_map(
			array(
				'name'                    => Switcher_Helper::get_title(),
				'base'                    => self::SHORTCODE,
				'category'                => __( 'Content', 'easy-language' ),
				'description'             => __( 'Shows links to the languages of the actual page.', 'easy-language' ),
				'icon'                    => Helper::get_plugin_url() . 'gfx/easy-language-icon.png',
				'show_settings_on_create' => false,
				'params'                  => array(
					array(
						'type'       => 'checkbox',
						'heading'    => $labels['show_icons'],
						'param_name' => 'show_icons',
						'value'      => array( __( 'Yes', 'easy-language' ) => 'yes' ),
					),
					array(
						'type'       => 'checkbox',
						'heading'    => $labels['hide_actual_language'],
						'param_name' => 'hide_actual_language',
						'value'      => array( __( 'Yes', 'easy-language' ) => 'yes' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => $labels['margin'],
						'description' => $labels['margin_description'],
						'param_name'  => 'margin',
						'group'       => $labels['spacing'],
					),
					array(
						'type'       => 'css_editor',
						'heading'    => __( 'CSS box', 'easy-language' ),
						'param_name' => 'css',
						'group'      => __( 'Design Options', 'easy-language' ),
					),
				),
			)
		);
	}

	/**
	 * Return the output of the element.
	 *
	 * @param array<string,string>|string $attributes The attributes of the shortcode.
	 *
	 * @return string
	 */
	public function render( array|string $attributes ): string {
		// make sure we have an array.
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		// get the attributes with their defaults.
		if ( function_exists( 'vc_map_get_attributes' ) ) {
			$attributes = vc_map_get_attributes( self::SHORTCODE, $attributes );
		}

		// get the CSS class of the design options.
		$css_class = '';
		if ( function_exists( 'vc_shortcode_custom_css_class' ) ) {
			$css_class = (string) vc_shortcode_custom_css_class( $attributes['css'] ?? '', ' ' );
			$css_class = (string) apply_filters( 'vc_shortcodes_css_class', $css_class, self::SHORTCODE, $attributes ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		// return the output.
		return Switcher_Helper::get_output(
			! empty( $attributes['show_icons'] ) && 'yes' === $attributes['show_icons'],
			! empty( $attributes['hide_actual_language'] ) && 'yes' === $attributes['hide_actual_language'],
			! empty( $attributes['margin'] ) ? (string) $attributes['margin'] : '',
			trim( 'wpb_easy_language_switcher ' . $css_class )
		);
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'js_composer/js_composer.php' );
	}
}
