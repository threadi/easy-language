<?php
/**
 * File for a trait to replace texts in shortcode-based page builders.
 *
 * @package easy-language
 */

namespace easyLanguage\Parser;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Trait to replace texts only in the shortcodes and attributes they were parsed from.
 */
trait Shortcode_Replacement {
	/**
	 * Replace the original text with its simplification only in the supported shortcodes.
	 *
	 * The text is only replaced if it is the complete content of a supported shortcode
	 * or the complete value of one of its supported attributes. Attribute values are masked.
	 *
	 * @param string       $original_complete The complete original content.
	 * @param string       $original_text The original text to replace.
	 * @param string       $simplified_part The simplified text.
	 * @param array<mixed> $shortcodes List of supported shortcodes with their supported attributes.
	 *
	 * @return string
	 */
	private function replace_text_in_shortcodes( string $original_complete, string $original_text, string $simplified_part, array $shortcodes ): string {
		// bail if no original text is given.
		if ( '' === $original_text ) {
			return $original_complete;
		}

		// check each supported shortcode.
		foreach ( $shortcodes as $shortcode => $attributes ) {
			// bail if the configuration is not valid.
			if ( ! is_string( $shortcode ) || '' === $shortcode ) {
				continue;
			}

			// make sure we have a list of attributes.
			if ( ! is_array( $attributes ) ) {
				$attributes = array();
			}

			$result = preg_replace_callback(
				'/' . get_shortcode_regex( array( $shortcode ) ) . '/s',
				function ( array $matches ) use ( $original_text, $simplified_part, $attributes ): string {
					return $this->replace_text_in_shortcode( $matches, $original_text, $simplified_part, $attributes );
				},
				$original_complete
			);

			// use the result only if no error occurred.
			if ( is_string( $result ) ) {
				$original_complete = $result;
			}
		}

		// return the resulting content.
		return $original_complete;
	}

	/**
	 * Replace the original text in a single found shortcode.
	 *
	 * @param array<int|string,string> $matches The matches of the shortcode regex.
	 * @param string                   $original_text The original text to replace.
	 * @param string                   $simplified_part The simplified text.
	 * @param array<mixed>             $attributes List of supported attributes of this shortcode.
	 *
	 * @return string
	 */
	private function replace_text_in_shortcode( array $matches, string $original_text, string $simplified_part, array $attributes ): string {
		// bail if this is an escaped shortcode like [[shortcode]].
		if ( '[' === $matches[1] && ']' === $matches[6] ) {
			return $matches[0];
		}

		// get the parts of the shortcode.
		$opening = '[' . $matches[2] . $matches[3] . $matches[4] . ']';
		$rest    = substr( $matches[0], strlen( $matches[1] . $opening ) );
		$atts    = $matches[3];
		$content = $matches[5] ?? '';

		// replace the content if it is exactly the original text.
		if ( '' !== $content && $content === $original_text && str_starts_with( $rest, $content ) ) {
			$rest = $simplified_part . substr( $rest, strlen( $content ) );
		}

		// replace the matching attribute values.
		if ( ! empty( $attributes ) && '' !== $atts ) {
			$new_atts = preg_replace_callback(
				get_shortcode_atts_regex(),
				function ( array $attribute ) use ( $original_text, $simplified_part, $attributes ): string {
					return $this->replace_text_in_shortcode_attribute( $attribute, $original_text, $simplified_part, $attributes );
				},
				$atts
			);
			if ( is_string( $new_atts ) ) {
				$atts = $new_atts;
			}
		}

		// return the resulting shortcode.
		return $matches[1] . '[' . $matches[2] . $atts . $matches[4] . ']' . $rest;
	}

	/**
	 * Replace the value of a single shortcode attribute if it matches the original text.
	 *
	 * @param array<int|string,string> $attribute The matches of the attribute regex.
	 * @param string                   $original_text The original text to replace.
	 * @param string                   $simplified_part The simplified text.
	 * @param array<mixed>             $attributes List of supported attributes of this shortcode.
	 *
	 * @return string
	 */
	private function replace_text_in_shortcode_attribute( array $attribute, string $original_text, string $simplified_part, array $attributes ): string {
		// get name, value and quote of this attribute (like shortcode_parse_atts() does).
		if ( ! empty( $attribute[1] ) ) {
			$name  = $attribute[1];
			$value = $attribute[2] ?? '';
			$quote = '"';
		} elseif ( ! empty( $attribute[3] ) ) {
			$name  = $attribute[3];
			$value = $attribute[4] ?? '';
			$quote = "'";
		} elseif ( ! empty( $attribute[5] ) ) {
			$name  = $attribute[5];
			$value = $attribute[6] ?? '';
			$quote = '"';
		} else {
			// bail on positional attributes.
			return $attribute[0];
		}

		// bail if this attribute is not supported.
		if ( ! in_array( strtolower( $name ), $attributes, true ) ) {
			return $attribute[0];
		}

		// bail if the value is not the original text.
		if ( stripcslashes( $value ) !== $original_text ) {
			return $attribute[0];
		}

		// mask the characters which would break the shortcode.
		$masked = str_replace(
			array( '[', ']', $quote ),
			array( '&#91;', '&#93;', '"' === $quote ? '&quot;' : '&#039;' ),
			$simplified_part
		);

		// keep the trailing whitespace of the match.
		$suffix = '';
		if ( 1 === preg_match( '/\s$/', $attribute[0], $whitespace ) ) {
			$suffix = $whitespace[0];
		}

		// return the attribute with the simplified value.
		return $name . '=' . $quote . $masked . $quote . $suffix;
	}
}
