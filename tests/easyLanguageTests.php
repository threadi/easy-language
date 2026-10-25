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

		self::prepare_plugin();
	}

	/**
	 * Initialize the plugin once per test run.
	 *
	 * Also usable by test classes which could not extend this class (e.g. WP_Ajax_UnitTestCase).
	 *
	 * @return void
	 */
	public static function prepare_plugin(): void {
		// prepare to load just one time.
		if ( did_action( 'easy_language_test_preparation_loaded' ) ) {
			return;
		}

		// start with empty tables for texts and simplifications: the installation of the WordPress test
		// environment does not remove the tables of this plugin, so data from previous runs could remain
		// (e.g. after a TRUNCATE, which commits the actual transaction in MySQL).
		\easyLanguage\EasyLanguage\Db::get_instance()->delete_tables();

		// initialize the plugin.
		\easyLanguage\Plugin\Installer::get_instance()->activation();

		// run initialization.
		do_action( 'init' );

		// mark as loaded.
		do_action( 'easy_language_test_preparation_loaded' );
	}
}
