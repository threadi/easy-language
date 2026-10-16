<?php
/**
 * File with the render function for the language switcher widget in Pagelayer.
 *
 * Pagelayer only supports function names as render callback for its widgets.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder\Pagelayer;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\PageBuilder\Switcher_Helper;

/**
 * Set the output of the language switcher for the Pagelayer template of our widget.
 *
 * @param array<string,mixed> $el The Pagelayer element (passed by reference).
 *
 * @return void
 */
function render_switcher( array &$el ): void {
	$el['tmp']['switcher'] = Switcher_Helper::get_output(
		! empty( $el['atts']['show_icons'] ),
		! empty( $el['atts']['hide_actual_language'] )
	);
}
