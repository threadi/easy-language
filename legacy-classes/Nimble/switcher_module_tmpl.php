<?php
/**
 * Template for the language switcher module in Nimble Builder.
 *
 * Nimble Builder loads this file via load_template() as template of the module.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\PageBuilder\Nimble;

// get the model of the actual module from Nimble Builder.
$easy_language_nimble_model = is_callable( '\Nimble\Nimble_Manager' ) ? call_user_func( '\Nimble\Nimble_Manager' )->model : array();

// get its values.
$easy_language_nimble_values = is_array( $easy_language_nimble_model ) && isset( $easy_language_nimble_model['value'] ) && is_array( $easy_language_nimble_model['value'] ) ? $easy_language_nimble_model['value'] : array();

// output the switcher.
echo Nimble::get_instance()->render( $easy_language_nimble_values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
