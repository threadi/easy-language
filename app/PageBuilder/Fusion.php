<?php
/**
 * File for handling Fusion page builder (by Agency Dominion) as page builder.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\Plugin\Helper;

/**
 * Object to handle Fusion as page builder.
 *
 * Adds a language switcher element to Fusion.
 */
class Fusion extends PageBuilder_Base {
	/**
	 * The shortcode of our element.
	 *
	 * @var string
	 */
	private const SHORTCODE = 'fsn_easy_language_switcher';

	/**
	 * Instance of this object.
	 *
	 * @var ?Fusion
	 */
	private static ?Fusion $instance = null;

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
	public static function get_instance(): Fusion {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * Fusion registers its own elements on "init" with priority 12.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_element' ), 12 );
	}

	/**
	 * Register our language switcher element in Fusion.
	 *
	 * @return void
	 */
	public function register_element(): void {
		// bail if Fusion is not active.
		if ( ! function_exists( 'fsn_map' ) ) {
			return;
		}

		// add the shortcode for the output. This must happen before the element is mapped, as Fusion replaces
		// the shortcode in its backend editor with its own handler during mapping.
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );

		// get the labels.
		$labels = Switcher_Helper::get_labels();

		// map the element.
		fsn_map(
			array(
				'name'          => Switcher_Helper::get_title(),
				'shortcode_tag' => self::SHORTCODE,
				'description'   => __( 'Shows links to the languages of the actual page.', 'easy-language' ),
				'icon'          => 'translate',
				'params'        => array(
					array(
						'type'       => 'checkbox',
						'param_name' => 'show_icons',
						'label'      => $labels['show_icons'],
					),
					array(
						'type'       => 'checkbox',
						'param_name' => 'hide_actual_language',
						'label'      => $labels['hide_actual_language'],
					),
					array(
						'type'        => 'text',
						'param_name'  => 'link_margin',
						'label'       => $labels['margin'],
						'help'        => $labels['margin_description'],
						'placeholder' => '0px',
						'section'     => 'style',
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

		// get the style classes of Fusion (for margin, padding, visibility ...).
		$css_class = function_exists( 'fsn_style_params_class' ) ? (string) fsn_style_params_class( $attributes ) : '';

		// return the output.
		return Switcher_Helper::get_output(
			! empty( $attributes['show_icons'] ),
			! empty( $attributes['hide_actual_language'] ),
			! empty( $attributes['link_margin'] ) ? (string) $attributes['link_margin'] : '',
			trim( 'fsn-easy-language-switcher ' . $css_class )
		);
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'fusion/fusion-core.php' );
	}
}
