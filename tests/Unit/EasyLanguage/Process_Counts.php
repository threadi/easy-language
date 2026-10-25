<?php
/**
 * Tests for the cached counts of texts, which are shown as progress in the admin bar.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\EasyLanguage\Db;
use easyLanguage\Tests\easyLanguageTests;
use WP_Admin_Bar;

/**
 * Object to test that the cached counts are deleted on every change of the texts.
 */
class Process_Counts extends easyLanguageTests {
	/**
	 * ID of the node in the admin bar.
	 */
	private const NODE_ID = 'easy-language-simplification-process';

	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		switch_to_locale( 'de_DE' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// the progress is only shown with enabled automatic simplification and a configured API.
		update_option( 'easy_language_automatic_simplification_enabled', 1 );
		update_option( 'easy_language_api', 'summ_ai' );
		update_option( 'easy_language_summ_ai_mode', 'free' );
	}

	/**
	 * Fill the cache with a value and run the given change.
	 *
	 * @param callable $change The change.
	 *
	 * @return bool True if the cache has been deleted by the change.
	 */
	private function is_cache_cleared_by( callable $change ): bool {
		set_transient(
			Db::TRANSIENT_PROCESS_COUNTS,
			array(
				'all'         => 99,
				'simplified'  => 0,
				'to_simplify' => 99,
			),
			MINUTE_IN_SECONDS
		);
		$change();
		return false === get_transient( Db::TRANSIENT_PROCESS_COUNTS );
	}

	/**
	 * Test that each change of texts deletes the cached counts.
	 *
	 * @return void
	 */
	public function test_changes_clear_the_cache(): void {
		$db   = Db::get_instance();
		$text = null;

		$this->assertTrue(
			$this->is_cache_cleared_by(
				function () use ( $db, &$text ) {
					$text = $db->add( 'Ein Text für den Zähler.', 'de_DE', 'post_content', false );
				}
			),
			'Db::add()'
		);
		$this->assertInstanceOf( \easyLanguage\EasyLanguage\Text::class, $text );

		$post_id = self::factory()->post->create();
		$this->assertTrue( $this->is_cache_cleared_by( fn() => $text->set_object( 'post', $post_id, 1, 'gutenberg' ) ), 'Text::set_object()' );
		$this->assertTrue( $this->is_cache_cleared_by( fn() => $text->set_state( 'in_use' ) ), 'Text::set_state()' );
		$this->assertTrue( $this->is_cache_cleared_by( fn() => $text->set_simplification( 'Ein einfacher Text.', 'de_LS', 'no_api', 0 ) ), 'Text::set_simplification()' );
		$this->assertTrue( $this->is_cache_cleared_by( fn() => $text->delete_simplification( 'de_LS' ) ), 'Text::delete_simplification()' );
		$this->assertTrue( $this->is_cache_cleared_by( fn() => $text->delete( $post_id ) ), 'Text::delete()' );
	}

	/**
	 * Test that the progress disappears from the admin bar as soon as all texts are simplified.
	 *
	 * @return void
	 */
	public function test_progress_disappears_without_delay(): void {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

		// create a simplified object with texts to simplify.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Fortschritt',
				'post_content' => '<p>Ein Text für den Fortschritt.</p>',
			)
		);
		$copy    = ( new \easyLanguage\EasyLanguage\Post_Object( $post_id ) )->add_simplification_object( 'de_LS', \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance(), false );
		$this->assertInstanceOf( \easyLanguage\EasyLanguage\Objects::class, $copy );

		// the progress is shown.
		$admin_bar = new WP_Admin_Bar();
		\easyLanguage\EasyLanguage\Init::get_instance()->show_simplification_process( $admin_bar );
		$this->assertNotNull( $admin_bar->get_node( self::NODE_ID ) );

		// simplify all texts.
		foreach ( $copy->get_entries() as $entry ) {
			$entry->set_state( 'in_use' );
		}

		// the progress disappears immediately (not only after the cache has expired).
		$admin_bar = new WP_Admin_Bar();
		\easyLanguage\EasyLanguage\Init::get_instance()->show_simplification_process( $admin_bar );
		$this->assertNull( $admin_bar->get_node( self::NODE_ID ) );
	}
}
