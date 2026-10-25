<?php
/**
 * Tests for the language switcher in classic menus.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\Tests\Switcher_Tests;
use WPDieException;

/**
 * Object to test the classic menu functions in class easyLanguage\EasyLanguage\Switcher.
 */
class Switcher_Menu extends Switcher_Tests {

	/**
	 * ID of the test menu.
	 *
	 * @var int
	 */
	private int $menu_id = 0;

	/**
	 * ID of the switcher item in the test menu.
	 *
	 * @var int
	 */
	private int $switcher_item_id = 0;

	/**
	 * Set up the test environment: a menu with a link, the switcher and another link.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// create the switcher post (as during activation; the one from the installation is not part of the test transaction).
		delete_option( 'easy_language_switcher_default' );
		Switcher::get_instance()->set_default();
		$switchers = get_posts(
			array(
				'post_type'   => EASY_LANGUAGE_CPT_SWITCHER,
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		$this->assertNotEmpty( $switchers );

		// create the menu.
		$menu_id = wp_create_nav_menu( 'Switcher Test ' . wp_rand() );
		$this->assertIsInt( $menu_id );
		$this->menu_id = $menu_id;
		$this->add_link( 'First' );
		$this->switcher_item_id = (int) wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-title'     => 'Switcher',
				'menu-item-object'    => EASY_LANGUAGE_CPT_SWITCHER,
				'menu-item-object-id' => $switchers[0],
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
		$this->add_link( 'Last' );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * Add a custom link to the test menu.
	 *
	 * @param string $title The title.
	 *
	 * @return void
	 */
	private function add_link( string $title ): void {
		wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-title'  => $title,
				'menu-item-url'    => home_url( '/' . sanitize_title( $title ) . '/' ),
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			)
		);
	}

	/**
	 * Return the items of the test menu as they are used in frontend.
	 *
	 * @return array<int,object>
	 */
	private function get_items(): array {
		$items = wp_get_nav_menu_items( $this->menu_id );
		$this->assertIsArray( $items );
		return array_values( $items );
	}

	/**
	 * Test that the switcher item is replaced by one item for each language.
	 *
	 * @return void
	 */
	public function test_menu_replaces_switcher_with_languages(): void {
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );
		$items = $this->get_items();

		$this->assertEquals( array( 'First', 'German', 'Leichte Sprache', 'Last' ), wp_list_pluck( $items, 'title' ) );
		$this->assertEquals( get_permalink( $this->ids['about_ls'] ), $items[2]->url );

		// the menu order is still ascending.
		$orders = wp_list_pluck( $items, 'menu_order' );
		$sorted = $orders;
		sort( $sorted );
		$this->assertEquals( $sorted, $orders );
		$this->assertCount( count( $orders ), array_unique( $orders ) );

		// the active language is marked for screen readers.
		$attributes = Switcher::get_instance()->set_menu_link_attributes( array(), $items[1] );
		$this->assertEquals( 'page', $attributes['aria-current'] );
	}

	/**
	 * Test that the switcher item is removed on requests without languages (e.g. search).
	 *
	 * @return void
	 */
	public function test_menu_removes_switcher_without_object(): void {
		$this->go_to( home_url( '/?s=test' ) );
		$items = $this->get_items();

		$this->assertEquals( array( 'First', 'Last' ), wp_list_pluck( $items, 'title' ) );
		$this->assertLessThan( $items[1]->menu_order, $items[0]->menu_order );
	}

	/**
	 * Test that not simplified pages hide the simplified language in menus (like before 4.0.0).
	 *
	 * @return void
	 */
	public function test_menu_on_page_without_simplification(): void {
		update_option( 'easy_language_switcher_compatibility', 1 );
		$this->go_to( (string) get_permalink( $this->ids['contact'] ) );
		$this->assertEquals( array( 'First', 'German', 'Last' ), wp_list_pluck( $this->get_items(), 'title' ) );
	}

	/**
	 * Test the icon setting from before 4.0.0.
	 *
	 * @return void
	 */
	public function test_menu_with_icon_setting_before_4(): void {
		update_post_meta( $this->switcher_item_id, 'easy-language-icons', 'on' );
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );
		$items = $this->get_items();

		// the item for Leichte Sprache shows an icon (German has no icon in this test environment).
		$titles = implode( '', wp_list_pluck( $items, 'title' ) );
		$this->assertStringContainsString( '<img', $titles );
	}

	/**
	 * Test the toggle in a menu.
	 *
	 * @return void
	 */
	public function test_menu_toggle(): void {
		update_post_meta(
			$this->switcher_item_id,
			'easy_language_switcher_settings',
			array(
				'display'         => 'toggle',
				'toggle_color_on' => '#ff0000',
			)
		);
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );
		$items = $this->get_items();

		$this->assertCount( 3, $items );
		$this->assertStringContainsString( 'easy-language-switcher-toggle-track', $items[1]->title );
		$this->assertEquals( get_permalink( $this->ids['about_ls'] ), $items[1]->url );

		$attributes = Switcher::get_instance()->set_menu_link_attributes( array( 'class' => 'theme-class' ), $items[1] );
		$this->assertEquals( 'theme-class easy-language-switcher-toggle', $attributes['class'] );
		$this->assertStringContainsString( '--easy-language-toggle-on:#ff0000', $attributes['style'] );
	}

	/**
	 * Test the visibility setting in menus.
	 *
	 * @return void
	 */
	public function test_menu_visibility(): void {
		update_post_meta(
			$this->switcher_item_id,
			'easy_language_switcher_settings',
			array(
				'visibility'         => 'include',
				'visibility_objects' => array( 'post:' . $this->ids['contact'] ),
			)
		);
		$this->go_to( (string) get_permalink( $this->ids['about'] ) );
		$this->assertEquals( array( 'First', 'Last' ), wp_list_pluck( $this->get_items(), 'title' ) );
	}

	/**
	 * Test saving the settings of a switcher item via the menu form.
	 *
	 * @return void
	 */
	public function test_save_menu_options(): void {
		update_post_meta( $this->switcher_item_id, 'easy-language-icons', 'on' );

		$_POST['update-nav-menu-nonce'] = wp_create_nonce( 'update-nav_menu' );
		$_REQUEST                       = $_POST;
		$_POST['menu-item-easy-language'] = array(
			$this->switcher_item_id => array(
				'display'            => 'toggle',
				'icon_size'          => '2em',
				'toggle_color_on'    => 'red;x:y',
				'toggle_radius'      => '0',
				'visibility'         => 'include',
				'visibility_objects' => 'post:12,foo:1,term:5',
				'fallback'           => 'unknown',
			),
		);

		Switcher::get_instance()->save_menu_options( $this->menu_id, $this->switcher_item_id );

		$settings = get_post_meta( $this->switcher_item_id, 'easy_language_switcher_settings', true );
		$this->assertIsArray( $settings );
		$this->assertEquals( 'toggle', $settings['display'] );
		$this->assertEquals( '2em', $settings['icon_size'] );
		$this->assertEquals( '', $settings['toggle_color_on'] );
		$this->assertEquals( '0px', $settings['toggle_radius'] );
		$this->assertEquals( array( 'post:12', 'term:5' ), $settings['visibility_objects'] );
		$this->assertEquals( 'auto', $settings['fallback'] );

		// the icon setting from before 4.0.0 is removed if no icons are shown.
		$this->assertEquals( '', get_post_meta( $this->switcher_item_id, 'easy-language-icons', true ) );

		// and it is saved again for icons (for compatibility).
		$_POST['menu-item-easy-language'][ $this->switcher_item_id ]['display'] = 'icons';
		Switcher::get_instance()->save_menu_options( $this->menu_id, $this->switcher_item_id );
		$this->assertEquals( 'on', get_post_meta( $this->switcher_item_id, 'easy-language-icons', true ) );
	}

	/**
	 * Test that saving the settings ignores other menu items.
	 *
	 * @return void
	 */
	public function test_save_menu_options_ignores_other_items(): void {
		$items    = $this->get_items();
		$other_id = (int) $items[0]->ID;

		$_POST['update-nav-menu-nonce'] = wp_create_nonce( 'update-nav_menu' );
		$_REQUEST                       = $_POST;
		$_POST['menu-item-easy-language'] = array( $other_id => array( 'display' => 'icons' ) );

		Switcher::get_instance()->save_menu_options( $this->menu_id, $other_id );
		$this->assertEquals( '', get_post_meta( $other_id, 'easy_language_switcher_settings', true ) );
		$this->assertEquals( '', get_post_meta( $other_id, 'easy-language-icons', true ) );
	}

	/**
	 * Test that menus could be changed via code (e.g. WP-CLI or importers) without the menu form.
	 *
	 * Until 4.0.0 this stopped with "The link you followed has expired".
	 *
	 * @return void
	 */
	public function test_menu_could_be_changed_via_code(): void {
		try {
			$item_id = wp_update_nav_menu_item(
				$this->menu_id,
				0,
				array(
					'menu-item-title'  => 'Via code',
					'menu-item-url'    => home_url( '/via-code/' ),
					'menu-item-type'   => 'custom',
					'menu-item-status' => 'publish',
				)
			);
		} catch ( WPDieException $e ) {
			$this->fail( 'Changing a menu via code stopped: ' . $e->getMessage() );
		}
		$this->assertIsInt( $item_id );
		$this->assertGreaterThan( 0, $item_id );
	}

	/**
	 * Test that the settings are only shown for switcher items in the menu editor.
	 *
	 * @return void
	 */
	public function test_menu_options_only_for_switcher_items(): void {
		$items = $this->get_items();

		ob_start();
		Switcher::get_instance()->add_menu_options( (int) $items[0]->ID, get_post( (int) $items[0]->ID ) );
		$this->assertEquals( '', trim( (string) ob_get_clean() ) );

		ob_start();
		Switcher::get_instance()->add_menu_options( $this->switcher_item_id, get_post( $this->switcher_item_id ) );
		$this->assertStringContainsString( 'easy-language-menu-switcher-settings', (string) ob_get_clean() );
	}
}
