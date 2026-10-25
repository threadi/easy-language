<?php
/**
 * File for the element "Language Switcher" in Bold Page Builder.
 *
 * Bold Page Builder expects elements as global classes named like their shortcode.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

// the class name must be the name of the shortcode in lowercase.
// phpcs:disable PEAR.NamingConventions.ValidClassName

/**
 * Object for the element "Language Switcher" in Bold Page Builder.
 *
 * The shortcode and the mapping are registered by the parent class with the class name as shortcode.
 *
 * @noinspection PhpUnused
 */
class easy_language_bb_switcher extends BT_BB_Element {
	/**
	 * Return the output of the element.
	 *
	 * @param array<string,mixed>|string $atts The attributes of the shortcode.
	 * @param string|null                $content The content of the shortcode (not used).
	 *
	 * @return string
	 */
	public function handle_shortcode( $atts, $content ): string {
		// WordPress uses an empty string if no attributes are set.
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}

		// get the general attributes of Bold Page Builder elements.
		$general = shortcode_atts(
			apply_filters( 'bt_bb_extract_atts_' . $this->shortcode, array() ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			$atts,
			$this->shortcode
		);

		// get the output of the switcher.
		$switcher = \easyLanguage\PageBuilder\BoldBuilder::get_instance()->render_switcher( $atts );

		// bail if the switcher should not be visible (like Bold Page Builder does for unpublished elements).
		if ( '' === $switcher ) {
			return '';
		}

		// collect the classes.
		$class = array( $this->shortcode );
		if ( ! empty( $general['el_class'] ) ) {
			$class[] = (string) $general['el_class'];
		}

		// add the responsive classes (e.g. for the animations).
		$data_override_class = array();
		do_action( $this->shortcode . '_before_extra_responsive_param' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		foreach ( $this->extra_responsive_data_override_param as $param ) {
			if ( ! array_key_exists( $param, $atts ) ) {
				continue;
			}
			$this->responsive_data_override_class(
				$class,
				$data_override_class,
				apply_filters( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
					$this->shortcode . '_responsive_data_override',
					array(
						'prefix' => $this->prefix,
						'param'  => $param,
						'value'  => $atts[ $param ],
					)
				)
			);
		}
		$class = apply_filters( $this->shortcode . '_class', $class, $atts ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

		// collect the attributes of the wrapper.
		$attributes = ' class="' . esc_attr( implode( ' ', (array) $class ) ) . '"';
		if ( ! empty( $general['el_id'] ) ) {
			$attributes .= ' id="' . esc_attr( (string) $general['el_id'] ) . '"';
		}
		$style = apply_filters( $this->shortcode . '_style', (string) ( $general['el_style'] ?? '' ), $atts ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		if ( ! empty( $style ) ) {
			$attributes .= ' style="' . esc_attr( (string) $style ) . '"';
		}
		$attributes .= ' data-bt-override-class="' . esc_attr( (string) wp_json_encode( $data_override_class, JSON_FORCE_OBJECT ) ) . '"';

		// create the output.
		$output = '<div' . $attributes . '>' . $switcher . '</div>';

		// return the output after the filters of Bold Page Builder (e.g. for the front end editor).
		$output = apply_filters( 'bt_bb_general_output', $output, $atts ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		return apply_filters( $this->shortcode . '_output', $output, $atts ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
	}

	/**
	 * Map the element with its settings.
	 *
	 * @return void
	 */
	public function map_shortcode(): void {
		bt_bb_map( $this->shortcode, \easyLanguage\PageBuilder\BoldBuilder::get_instance()->get_element_mapping() );
	}
}
