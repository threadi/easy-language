<?php
/**
 * Server side rendering of the language switcher element for Breakdance.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Variables from Breakdance.
 *
 * @var array<string,mixed> $propertiesData The settings of this element.
 */

// get the settings.
$easy_language_show_icons           = ! empty( $propertiesData['content']['settings']['show_icons'] ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
$easy_language_hide_actual_language = ! empty( $propertiesData['content']['settings']['hide_actual_language'] ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

// output the switcher.
echo \easyLanguage\PageBuilder\Switcher_Helper::get_output( $easy_language_show_icons, $easy_language_hide_actual_language ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
