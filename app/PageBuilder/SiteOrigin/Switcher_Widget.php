<?php
/**
 * File for the classic language switcher widget for Page Builder by SiteOrigin without the Widgets Bundle.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder\SiteOrigin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\PageBuilder\SiteOrigin;
use WP_Widget;

/**
 * Object for the classic language switcher widget.
 *
 * Used by Page Builder by SiteOrigin (and classic widget areas) if the SiteOrigin Widgets Bundle is not active. The
 * form is kept simple: selects, checkboxes and text fields for all settings, without conditions.
 *
 * @noinspection PhpUnused
 */
class Switcher_Widget extends WP_Widget {
	/**
	 * Constructor for this widget.
	 */
	public function __construct() {
		parent::__construct(
			SiteOrigin::WIDGET_ID,
			__( 'Language Switcher', 'easy-language' ),
			array(
				'description' => __( 'Easy Language', 'easy-language' ),
				'classname'   => 'easy-language-switcher-widget',
			)
		);
	}

	/**
	 * Output the widget.
	 *
	 * @param array<string,mixed> $args The arguments of the widget area.
	 * @param array<string,mixed> $instance The widget instance.
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		// get the output of the switcher.
		$html = SiteOrigin::get_instance()->render( (array) $instance );

		// bail if the switcher should not be visible.
		if ( '' === $html ) {
			return;
		}

		// output the widget.
		echo $args['before_widget'] . $html . $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Output the form of the widget.
	 *
	 * @param array<string,mixed> $instance The widget instance.
	 *
	 * @return string
	 */
	public function form( $instance ) {
		$switcher = Switcher::get_instance();
		$values   = SiteOrigin::get_instance()->get_values( (array) $instance );
		$fields   = $switcher->get_widget_fields();

		// output the fields per section.
		foreach ( $switcher->get_widget_sections() as $section => $title ) {
			$section_fields = array_filter(
				$fields,
				function ( $field ) use ( $section ) {
					return ( $field['section'] ?? 'settings' ) === $section;
				}
			);

			// bail if this section has no fields.
			if ( empty( $section_fields ) ) {
				continue;
			}

			// output the title of the section.
			echo '<h4>' . esc_html( $title ) . '</h4>';

			// output the fields.
			foreach ( $section_fields as $name => $field ) {
				$this->render_field( $name, $field, $values[ $name ] ?? $field['default'] );
			}
		}

		return '';
	}

	/**
	 * Output a single field of the form.
	 *
	 * @param string              $name The name of the field.
	 * @param array<string,mixed> $field The definition of the field.
	 * @param mixed               $value The actual value.
	 *
	 * @return void
	 */
	private function render_field( string $name, array $field, mixed $value ): void {
		$id          = $this->get_field_id( $name );
		$field_name  = $this->get_field_name( $name );
		$description = $field['description'] ?? '';

		echo '<p>';
		switch ( $field['type'] ) {
			case 'switch':
				echo '<input type="checkbox" class="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $field_name ) . '" value="1"' . checked( in_array( $value, array( true, 1, '1', 'yes', 'on', 'true' ), true ), true, false ) . '> ';
				echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
				break;
			case 'select':
				echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
				echo '<select class="widefat" id="' . esc_attr( $id ) . '" name="' . esc_attr( $field_name ) . '">';
				foreach ( (array) $field['options'] as $option_value => $label ) {
					echo '<option value="' . esc_attr( (string) $option_value ) . '"' . selected( (string) $value, (string) $option_value, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
				break;
			default:
				// sizes, colors and objects are text fields.
				if ( 'objects' === $field['type'] ) {
					$value       = is_array( $value ) ? implode( ',', $value ) : $value;
					$description = trim( $description . ' ' . __( 'Comma separated list of pages and terms, e.g. post:12,term:5.', 'easy-language' ) );
				}
				if ( is_array( $value ) ) {
					$value = isset( $value['size'] ) ? $value['size'] . ( $value['unit'] ?? '' ) : '';
				}
				echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
				echo '<input type="text" class="widefat" id="' . esc_attr( $id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '">';
				break;
		}
		if ( ! empty( $description ) ) {
			echo '<br><small>' . esc_html( $description ) . '</small>';
		}
		echo '</p>';
	}

	/**
	 * Sanitize the values of the widget on saving.
	 *
	 * @param array<string,mixed> $new_instance The new values.
	 * @param array<string,mixed> $old_instance The old values.
	 *
	 * @return array<string,mixed>
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		foreach ( Switcher::get_instance()->get_widget_fields() as $name => $field ) {
			$value = $new_instance[ $name ] ?? '';
			switch ( $field['type'] ) {
				case 'switch':
					$instance[ $name ] = ! empty( $value );
					break;
				case 'select':
					$instance[ $name ] = is_scalar( $value ) && array_key_exists( (string) $value, (array) $field['options'] ) ? (string) $value : $field['default'];
					break;
				default:
					// sizes, colors and objects (the switcher validates them on output).
					$instance[ $name ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
					break;
			}
		}
		return $instance;
	}
}
