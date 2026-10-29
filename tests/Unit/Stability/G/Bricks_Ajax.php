<?php
/**
 * Tests for the AJAX options of the switcher element in Bricks.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\G;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

/**
 * Object to test the AJAX options of the switcher element in Bricks.
 */
class Bricks_Ajax extends WP_Ajax_UnitTestCase {
	/**
	 * Prepare the plugin once for this class.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// initialize the plugin (same as in all other test classes).
		\easyLanguage\Tests\easyLanguageTests::prepare_plugin();
	}

	/**
	 * Test that the chosen objects could be given as comma separated list.
	 *
	 * @return void
	 */
	public function test_include_as_comma_separated_list(): void {
		$this->_setRole( 'administrator' );
		add_action( 'wp_ajax_easy_language_test_bricks_options', array( \easyLanguage\PageBuilder\Bricks::get_instance(), 'get_object_options' ) );

		$first  = self::factory()->post->create( array( 'post_title' => 'Erste Seite', 'post_type' => 'page' ) );
		$second = self::factory()->post->create( array( 'post_title' => 'Zweite Seite', 'post_type' => 'page' ) );

		$_GET['nonce']   = wp_create_nonce( 'bricks-nonce-builder' );
		$_GET['search']  = 'nothingwillmatchthis';
		$_GET['include'] = 'post:' . $first . ',post:' . $second;
		$_REQUEST        = array_merge( $_REQUEST, $_GET );

		try {
			$this->_handleAjax( 'easy_language_test_bricks_options' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'post:' . $first, $response['data'] );
		$this->assertArrayHasKey( 'post:' . $second, $response['data'] );
	}
}
