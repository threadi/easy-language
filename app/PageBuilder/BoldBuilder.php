<?php
/**
 * File for handling Bold Page Builder as page builder.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\Plugin\Helper;

/**
 * Object to handle Bold Page Builder as page builder.
 *
 * Adds a language switcher element to Bold Page Builder.
 */
class BoldBuilder extends PageBuilder_Base {
	/**
	 * The shortcode of our element.
	 *
	 * @var string
	 */
	private const SHORTCODE = 'easy_language_switcher_bold';

	/**
	 * Instance of this object.
	 *
	 * @var ?BoldBuilder
	 */
	private static ?BoldBuilder $instance = null;

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
	public static function get_instance(): BoldBuilder {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * Bold Page Builder loads its own elements on "init" with priority 10.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_element' ), 20 );
	}

	/**
	 * Register our language switcher element in Bold Page Builder.
	 *
	 * @return void
	 */
	public function register_element(): void {
		// bail if Bold Page Builder is not active.
		if ( ! function_exists( 'bt_bb_map' ) ) {
			return;
		}

		// add the shortcode for the output (must exist before the element is mapped).
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );

		// get the labels.
		$labels = Switcher_Helper::get_labels();

		// map the element.
		bt_bb_map(
			self::SHORTCODE,
			array(
				'name'                    => Switcher_Helper::get_title(),
				'description'             => __( 'Shows links to the languages of the actual page.', 'easy-language' ),
				'icon'                    => 'bt_bb_icon_bt_bb_custom_menu',
				'show_settings_on_create' => false,
				'params'                  => array(
					array(
						'param_name' => 'show_icons',
						'type'       => 'checkbox',
						'value'      => array( __( 'Yes', 'easy-language' ) => 'yes' ),
						'heading'    => $labels['show_icons'],
						'preview'    => true,
					),
					array(
						'param_name' => 'hide_actual_language',
						'type'       => 'checkbox',
						'value'      => array( __( 'Yes', 'easy-language' ) => 'yes' ),
						'heading'    => $labels['hide_actual_language'],
						'preview'    => true,
					),
					array(
						'param_name'  => 'margin',
						'type'        => 'textfield',
						'heading'     => $labels['margin'],
						'description' => $labels['margin_description'],
						'placeholder' => '0px',
						'group'       => $labels['spacing'],
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
		// get the attributes.
		$attributes = shortcode_atts(
			array(
				'show_icons'           => '',
				'hide_actual_language' => '',
				'margin'               => '',
			),
			is_array( $attributes ) ? $attributes : array(),
			self::SHORTCODE
		);

		// return the output.
		return Switcher_Helper::get_output(
			'yes' === $attributes['show_icons'],
			'yes' === $attributes['hide_actual_language'],
			(string) $attributes['margin'],
			'bt_bb_easy_language_switcher'
		);
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'bold-page-builder/bold-builder.php' );
	}
}
