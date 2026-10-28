<?php
/**
 * Helper to activate one of the test themes for the tests of the parsers.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Fakes\Parser;

/**
 * Activate a theme from tests/Data/Parser/Themes for the actual test.
 *
 * The theme is only used via filters for "stylesheet" and "template", which are removed by WP_UnitTestCase
 * after each test. Some page builders are detected by the name of the active theme (e.g. Avada, Enfold, Bricks).
 */
class Theme_Switcher {
	/**
	 * Activate the given theme from the test themes.
	 *
	 * @param string $stylesheet The directory name of the theme.
	 * @param string $template   The directory name of the parent theme (empty for no child theme).
	 *
	 * @return void
	 */
	public static function activate( string $stylesheet, string $template = '' ): void {
		// register our directory with the test themes.
		register_theme_directory( dirname( __DIR__, 2 ) . '/Data/Parser/Themes' );
		delete_site_transient( 'theme_roots' );
		search_theme_directories( true );

		// use the theme.
		$template = '' === $template ? $stylesheet : $template;
		add_filter(
			'pre_option_stylesheet',
			static function () use ( $stylesheet ) {
				return $stylesheet;
			}
		);
		add_filter(
			'pre_option_template',
			static function () use ( $template ) {
				return $template;
			}
		);
	}
}
