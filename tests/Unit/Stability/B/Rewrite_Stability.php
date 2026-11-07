<?php
/**
 * Stability tests for class easyLanguage\EasyLanguage\Rewrite and easyLanguage\EasyLanguage\Rest_Api.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\B;

use easyLanguage\EasyLanguage\Rewrite;
use easyLanguage\Plugin\Languages;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test fixes in easyLanguage\EasyLanguage\Rewrite and Rest_Api.
 */
class Rewrite_Stability extends easyLanguageTests {
	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
		wp_schedule_event( time(), 'easy_language_15minutly', 'easy_language_automatic_simplification' );
		parent::tear_down();
	}

	/**
	 * Return the first active language with an URL part.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function get_language(): array {
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			if ( ! empty( $settings['url'] ) ) {
				return array( $language_code, $settings['url'] );
			}
		}
		$this->markTestSkipped( 'No active language with URL part.' );
	}

	/**
	 * The front page of a language (by its URL part) must not be redirected.
	 *
	 * @return void
	 */
	public function test_prevent_redirect_for_language_front_page(): void {
		list( , $url ) = $this->get_language();
		$this->assertSame( '', Rewrite::get_instance()->prevent_redirect_for_front_page( home_url( '/' ), home_url( '/' . $url . '/' ) ) );
		$this->assertSame( home_url( '/' ), Rewrite::get_instance()->prevent_redirect_for_front_page( home_url( '/' ), home_url( '/other/' ) ) );
	}

	/**
	 * With plain permalinks the post link must not be changed.
	 *
	 * @return void
	 */
	public function test_post_link_with_plain_permalinks(): void {
		list( $language_code, $url ) = $this->get_language();
		$post = self::factory()->post->create_and_get();
		update_post_meta( $post->ID, 'easy_language_simplification_language', $language_code );

		update_option( 'permalink_structure', '' );
		$this->assertSame( '', Rewrite::get_instance()->post_link( '', $post ) );

		update_option( 'permalink_structure', '/%postname%/' );
		$this->assertSame( $url . '/%postname%/', Rewrite::get_instance()->post_link( '/%postname%/', $post ) );
	}

	/**
	 * A cron event which is just a little overdue is not reported as not executed.
	 *
	 * @return void
	 */
	public function test_cron_check_with_little_overdue_schedule(): void {
		wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
		wp_schedule_event( time() - 60, 'hourly', 'easy_language_automatic_simplification' );

		$result = \easyLanguage\EasyLanguage\Rest_Api::get_instance()->check_automatic_cron();
		$this->assertSame( 'good', $result['status'] );
	}
}
