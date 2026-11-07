<?php
/**
 * Stability tests for class easyLanguage\EasyLanguage\Switcher.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\C;

use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the stability fixes in the language switcher.
 */
class Switcher_Stability extends easyLanguageTests {
	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'wp_insert_post_empty_content', '__return_true' );
		if ( taxonomy_exists( 'el_private_tax' ) ) {
			unregister_taxonomy( 'el_private_tax' );
		}
		parent::tear_down();
	}

	/**
	 * Finding 11: the default switcher is marked as created only if it could be created.
	 *
	 * @return void
	 */
	public function test_set_default_marks_only_on_success(): void {
		delete_option( 'easy_language_switcher_default' );

		// let the creation fail.
		add_filter( 'wp_insert_post_empty_content', '__return_true' );
		Switcher::get_instance()->set_default();
		$this->assertEquals( 0, absint( get_option( 'easy_language_switcher_default', 0 ) ) );

		// next try succeeds.
		remove_filter( 'wp_insert_post_empty_content', '__return_true' );
		Switcher::get_instance()->set_default();
		$this->assertEquals( 1, absint( get_option( 'easy_language_switcher_default', 0 ) ) );
	}

	/**
	 * Finding 10: no switcher settings are shown for other menu items, even without menu item object.
	 *
	 * @return void
	 */
	public function test_menu_options_without_menu_item_object(): void {
		$menu_id = wp_create_nav_menu( 'Stability Test ' . wp_rand() );
		$this->assertIsInt( $menu_id );
		$item_id = (int) wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => 'Link',
				'menu-item-url'    => home_url( '/link/' ),
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			)
		);

		ob_start();
		Switcher::get_instance()->add_menu_options( $item_id );
		$this->assertEquals( '', trim( (string) ob_get_clean() ) );
	}

	/**
	 * Finding 12: terms of not public taxonomies are only listed for users who could assign them.
	 *
	 * @return void
	 */
	public function test_object_labels_of_private_terms(): void {
		register_taxonomy( 'el_private_tax', 'post', array( 'public' => false ) );
		update_option( 'easy_language_taxonomies', array( 'category' => 1, 'el_private_tax' => 1 ) );
		$private_term = self::factory()->term->create( array( 'taxonomy' => 'el_private_tax' ) );
		$public_term  = self::factory()->category->create();
		$objects      = array( 'term:' . $private_term, 'term:' . $public_term );

		// a subscriber sees only the public term.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertEquals( array( 'term:' . $public_term ), wp_list_pluck( Switcher::get_instance()->get_object_labels( $objects ), 'value' ) );

		// an editor sees both.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertEquals( $objects, wp_list_pluck( Switcher::get_instance()->get_object_labels( $objects ), 'value' ) );
	}
}
