<?php
/**
 * File to handle the main object for each test class.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests;

use WP_UnitTestCase;

/**
 * Object to handle the preparations for each test class.
 */
abstract class easyLanguageTests extends WP_UnitTestCase {

	/**
	 * Prepare the test environment for each test class.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// prepare to load just one time.
		if ( ! did_action('easy_language_test_preparation_loaded') ) {

			// initialize the plugin.
			\easyLanguage\Plugin\Installer::get_instance()->activation();

			// run initialization.
			do_action( 'init' );

			// mark as loaded.
			do_action('easy_language_test_preparation_loaded');
		}
	}
}
