<?php
/**
 * Tests for class easyLanguage\EasyLanguage\Switcher.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\Tests\Switcher_Tests;

/**
 * Object to test functions in class easyLanguage\EasyLanguage\Switcher.
 */
class Switcher extends Switcher_Tests {

	/**
	 * Test is our cpt is registered
	 *
	 * @return void
	 */
	public function test_lel_lang_switcher_post_type_is_registered(): void {
		$this->assertTrue( post_type_exists( 'lel_lang_switcher' ), 'The "lel_lang_switcher" post type should be registered.' );
	}

	/**
	 * Test if our cpt is not public.
	 *
	 * @return void
	 */
	public function test_lel_lang_switcher_post_type_is_not_public(): void {
		$post_type_obj = get_post_type_object( 'lel_lang_switcher' );

		$this->assertNotNull( $post_type_obj );
		$this->assertFalse( $post_type_obj->public );
		$this->assertTrue( $post_type_obj->show_ui );
	}

	/**
	 * Test the sanitizing of sizes.
	 *
	 * @return void
	 */
	public function test_sanitize_size(): void {
		$switcher = \easyLanguage\EasyLanguage\Switcher::get_instance();
		$this->assertEquals( '32px', $switcher->sanitize_size( 32 ) );
		$this->assertEquals( '32px', $switcher->sanitize_size( '32' ) );
		$this->assertEquals( '1.5em', $switcher->sanitize_size( '1.5em' ) );
		$this->assertEquals( '2rem', $switcher->sanitize_size( array( 'size' => 2, 'unit' => 'rem' ) ) );
		$this->assertEquals( '', $switcher->sanitize_size( 0 ) );
		$this->assertEquals( '', $switcher->sanitize_size( '' ) );
		$this->assertEquals( '', $switcher->sanitize_size( '10px;color:red' ) );
		$this->assertEquals( '', $switcher->sanitize_size( array( 'size' => '', 'unit' => 'px' ) ) );
	}

	/**
	 * Test the sanitizing of visibility objects.
	 *
	 * @return void
	 */
	public function test_sanitize_objects(): void {
		$switcher = \easyLanguage\EasyLanguage\Switcher::get_instance();
		$this->assertEquals( array( 'post:12', 'term:5' ), $switcher->sanitize_objects( 'post:12, term:5,post:12,foo:1,post:0' ) );
		$this->assertEquals( array(), $switcher->sanitize_objects( null ) );
	}

	/**
	 * Test that the display mode is derived from the setting before 4.0.0.
	 *
	 * @return void
	 */
	public function test_display_from_show_icons(): void {
		$switcher = \easyLanguage\EasyLanguage\Switcher::get_instance();
		$this->assertEquals( 'icons', $switcher->normalize_settings( array( 'show_icons' => true ) )['display'] );
		$this->assertEquals( 'text', $switcher->normalize_settings( array() )['display'] );
		$this->assertEquals( 'toggle', $switcher->normalize_settings( array( 'display' => 'toggle', 'show_icons' => true ) )['display'] );
	}

	/**
	 * Test that the old output is only used with enabled compatibility mode.
	 *
	 * @return void
	 */
	public function test_legacy_only_in_compatibility_mode(): void {
		$switcher = \easyLanguage\EasyLanguage\Switcher::get_instance();
		$this->assertFalse( $switcher->normalize_settings( array() )['legacy'] );
		update_option( 'easy_language_switcher_compatibility', 1 );
		$this->assertTrue( $switcher->normalize_settings( array() )['legacy'] );
		$this->assertFalse( $switcher->normalize_settings( array( 'legacy' => false ) )['legacy'] );
	}

	/**
	 * Test the entries on a page with simplification.
	 *
	 * @return void
	 */
	public function test_page_with_simplification(): void {
		$this->go_to( get_permalink( $this->ids['about'] ) );
		$entries = $this->get_entries();
		$this->assertEquals( array( 'de_DE', 'de_LS' ), array_keys( $entries ) );
		$this->assertTrue( $entries['de_DE']['active'] );
		$this->assertEquals( get_permalink( $this->ids['about_ls'] ), $entries['de_LS']['url'] );

		// on the simplified page the simplified language is active.
		$this->go_to( get_permalink( $this->ids['about_ls'] ) );
		$entries = $this->get_entries();
		$this->assertTrue( $entries['de_LS']['active'] );
		$this->assertEquals( get_permalink( $this->ids['about'] ), $entries['de_DE']['url'] );
	}

	/**
	 * Test a page without simplification: the language is hidden, in compatibility mode it links to the homepage.
	 *
	 * @return void
	 */
	public function test_page_without_simplification(): void {
		$this->go_to( get_permalink( $this->ids['contact'] ) );
		$this->assertEquals( array( 'de_DE' ), array_keys( $this->get_entries() ) );

		update_option( 'easy_language_switcher_compatibility', 1 );
		$entries = $this->get_entries();
		$this->assertEquals( array( 'de_DE', 'de_LS' ), array_keys( $entries ) );
		$this->assertEquals( get_home_url(), $entries['de_LS']['url'] );

		// the classic menu has always hidden these languages.
		$this->assertEquals( array( 'de_DE' ), array_keys( $this->get_entries( array( 'legacy' => false ) ) ) );
	}

	/**
	 * Test that a draft simplification is not linked.
	 *
	 * @return void
	 */
	public function test_draft_simplification_is_not_linked(): void {
		$this->go_to( get_permalink( $this->ids['draft'] ) );
		$this->assertEquals( array( 'de_DE' ), array_keys( $this->get_entries() ) );
	}

	/**
	 * Test the link-mode "link_translated" on a page without simplification.
	 *
	 * @return void
	 */
	public function test_link_translated_without_simplification(): void {
		update_option( 'easy_language_switcher_link', 'link_translated' );
		$this->go_to( get_permalink( $this->ids['contact'] ) );
		$entries = $this->get_entries();
		$this->assertEquals( get_home_url(), $entries['de_LS']['url'] );
	}

	/**
	 * Test the link-mode "do_not_link": links to the homepage, never a relative URL.
	 *
	 * @return void
	 */
	public function test_do_not_link(): void {
		update_option( 'easy_language_switcher_link', 'do_not_link' );
		$this->go_to( get_permalink( $this->ids['contact'] ) );
		$entries = $this->get_entries();
		$this->assertEquals( get_home_url(), $entries['de_LS']['url'] );
	}

	/**
	 * Test the switcher on a term archive.
	 *
	 * @return void
	 */
	public function test_term_archive(): void {
		$this->go_to( get_term_link( $this->ids['cat'], 'category' ) );
		$entries = $this->get_entries();
		$this->assertEquals( array( 'de_DE', 'de_LS' ), array_keys( $entries ) );
		$this->assertEquals( get_term_link( $this->ids['cat_ls'], 'category' ), $entries['de_LS']['url'] );
	}

	/**
	 * Test the blog homepage: the switcher does not use the first post of the list.
	 *
	 * @return void
	 */
	public function test_blog_homepage(): void {
		update_option( 'show_on_front', 'posts' );
		$this->go_to( home_url( '/' ) );
		$entries = $this->get_entries();
		$this->assertEquals( array( 'de_DE' ), array_keys( $entries ) );
		$this->assertEquals( get_home_url(), $entries['de_DE']['url'] );
	}

	/**
	 * Test the fallback on requests without object.
	 *
	 * @return void
	 */
	public function test_fallback(): void {
		$this->go_to( home_url( '/?s=test' ) );
		$this->assertNotEmpty( $this->get_entries() );
		$this->assertEmpty( $this->get_entries( array( 'fallback' => 'hide' ) ) );

		$this->go_to( get_author_posts_url( get_current_user_id() ) );
		$this->assertEmpty( $this->get_entries() );
		$this->assertNotEmpty( $this->get_entries( array( 'fallback' => 'home' ) ) );
	}

	/**
	 * Test the visibility with child pages.
	 *
	 * @return void
	 */
	public function test_visibility_with_children(): void {
		$settings = array(
			'visibility'         => 'include',
			'visibility_objects' => array( 'post:' . $this->ids['area'] ),
		);

		$this->go_to( get_permalink( $this->ids['area'] ) );
		$this->assertNotEmpty( $this->get_entries( $settings ) );

		// the simplification of the chosen page.
		$this->go_to( get_permalink( $this->ids['area_ls'] ) );
		$this->assertNotEmpty( $this->get_entries( $settings ) );

		// child pages only if requested.
		$this->go_to( get_permalink( $this->ids['child'] ) );
		$this->assertEmpty( $this->get_entries( $settings ) );
		$this->assertNotEmpty( $this->get_entries( array_merge( $settings, array( 'visibility_children' => true ) ) ) );

		// other pages.
		$this->go_to( get_permalink( $this->ids['about'] ) );
		$this->assertEmpty( $this->get_entries( $settings ) );
		$this->assertNotEmpty( $this->get_entries( array_merge( $settings, array( 'visibility' => 'exclude' ) ) ) );
	}

	/**
	 * Test the visibility with terms and assigned contents.
	 *
	 * @return void
	 */
	public function test_visibility_with_terms(): void {
		$settings = array(
			'visibility'         => 'include',
			'visibility_objects' => array( 'term:' . $this->ids['cat'] ),
		);

		$this->go_to( get_term_link( $this->ids['cat'], 'category' ) );
		$this->assertNotEmpty( $this->get_entries( $settings ) );

		$this->go_to( get_permalink( $this->ids['post'] ) );
		$this->assertEmpty( $this->get_entries( $settings ) );
		$this->assertNotEmpty( $this->get_entries( array_merge( $settings, array( 'visibility_assigned' => true ) ) ) );
	}

	/**
	 * Test the toggle.
	 *
	 * @return void
	 */
	public function test_toggle(): void {
		$switcher = \easyLanguage\EasyLanguage\Switcher::get_instance();

		// off on the original.
		$this->go_to( get_permalink( $this->ids['about'] ) );
		$html = $switcher->get( array( 'display' => 'toggle' ) );
		$this->assertStringContainsString( 'easy-language-switcher-toggle', $html );
		$this->assertStringNotContainsString( 'is-checked', $html );
		$this->assertStringContainsString( (string) get_permalink( $this->ids['about_ls'] ), $html );

		// on the simplification.
		$this->go_to( get_permalink( $this->ids['about_ls'] ) );
		$html = $switcher->get( array( 'display' => 'toggle' ) );
		$this->assertStringContainsString( 'is-checked', $html );
		$this->assertStringContainsString( (string) get_permalink( $this->ids['about'] ), $html );

		// hidden or greyed out without simplification.
		$this->go_to( get_permalink( $this->ids['contact'] ) );
		$this->assertEquals( '', $switcher->get( array( 'display' => 'toggle' ) ) );
		$html = $switcher->get(
			array(
				'display'         => 'toggle',
				'toggle_disabled' => true,
			)
		);
		$this->assertStringContainsString( 'is-disabled', $html );
		$this->assertStringNotContainsString( '<a ', $html );
	}

	/**
	 * Test the icon size.
	 *
	 * @return void
	 */
	public function test_icon_size(): void {
		$switcher = \easyLanguage\EasyLanguage\Switcher::get_instance();
		$this->assertEquals( '', $switcher->normalize_settings( array() )['icon_size'] );
		update_option( 'easy_language_switcher_icon_size', 30 );
		$this->assertEquals( '30px', $switcher->normalize_settings( array() )['icon_size'] );
		$this->assertEquals( '2em', $switcher->normalize_settings( array( 'icon_size' => '2em' ) )['icon_size'] );
	}

	/**
	 * Test the markup of blocks: old blocks without wrapper, new blocks with wrapper.
	 *
	 * @return void
	 */
	public function test_block_markup(): void {
		$this->go_to( get_permalink( $this->ids['about'] ) );
		$this->assertStringStartsWith( '<a ', do_blocks( '<!-- wp:easy-language/switcher /-->' ) );
		$this->assertStringStartsWith( '<div ', do_blocks( '<!-- wp:easy-language/switcher {"markup_version":2} /-->' ) );
		$this->assertStringStartsWith( '<li ', do_blocks( '<!-- wp:easy-language/navigation-switcher {"markup_version":2} /-->' ) );
	}

	/**
	 * Test the shortcode without attributes.
	 *
	 * @return void
	 */
	public function test_shortcode_without_attributes(): void {
		$this->go_to( get_permalink( $this->ids['about'] ) );
		$this->assertStringContainsString( 'easy-language-switcher-title', do_shortcode( '[easy-language-switcher]' ) );
	}

	/**
	 * Test that nothing is shown on search results if the static front page is not supported (like before 4.0.0).
	 *
	 * @return void
	 */
	public function test_fallback_with_unsupported_front_page(): void {
		update_option( 'easy_language_post_types', array( 'post' => 1 ) );
		$this->go_to( home_url( '/?s=test' ) );
		$this->assertEmpty( $this->get_entries() );
		$this->assertNotEmpty( $this->get_entries( array( 'fallback' => 'home' ) ) );
	}

	/**
	 * Test that switchers from before 4.0.0 keep their markup in compatibility mode.
	 *
	 * @return void
	 */
	public function test_legacy_markup(): void {
		$this->go_to( get_permalink( $this->ids['about'] ) );
		$this->assertStringContainsString( 'aria-current', do_blocks( '<!-- wp:easy-language/switcher /-->' ) );
		update_option( 'easy_language_switcher_compatibility', 1 );
		$this->assertStringNotContainsString( 'aria-current', do_blocks( '<!-- wp:easy-language/switcher /-->' ) );
		$this->assertStringContainsString( 'aria-current', do_blocks( '<!-- wp:easy-language/switcher {"markup_version":2} /-->' ) );
	}

	/**
	 * Test that internal settings could not be set via block attributes.
	 *
	 * @return void
	 */
	public function test_block_ignores_internal_settings(): void {
		$this->go_to( get_permalink( $this->ids['about'] ) );
		$html = do_blocks( '<!-- wp:easy-language/switcher {"markup_version":2,"normalized":true,"preview":true,"visibility":"include","visibility_objects":["post:1"]} /-->' );
		$this->assertEquals( '', $html );
	}

	/**
	 * Test that the object labels only contain readable objects of supported types.
	 *
	 * @return void
	 */
	public function test_object_labels(): void {
		$switcher   = \easyLanguage\EasyLanguage\Switcher::get_instance();
		$private_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'private',
				'post_title'  => 'Secret',
				'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ),
			)
		);
		$block_id   = self::factory()->post->create( array( 'post_type' => 'wp_block' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$labels = $switcher->get_object_labels( array( 'post:' . $this->ids['about'], 'post:' . $private_id, 'post:' . $block_id ) );
		$this->assertCount( 1, $labels );
		$this->assertEquals( 'post:' . $this->ids['about'], $labels[0]['value'] );
	}

	/**
	 * Test the sanitizing of colors and of sizes with 0.
	 *
	 * @return void
	 */
	public function test_sanitize_color(): void {
		$switcher = \easyLanguage\EasyLanguage\Switcher::get_instance();
		$this->assertEquals( '#ff0000', $switcher->sanitize_color( '#ff0000' ) );
		$this->assertEquals( 'rgba(0, 0, 0, 0.5)', $switcher->sanitize_color( 'rgba(0, 0, 0, 0.5)' ) );
		$this->assertEquals( 'var(--e-global-color-primary)', $switcher->sanitize_color( 'var(--e-global-color-primary)' ) );
		$this->assertEquals( 'red', $switcher->sanitize_color( 'red' ) );
		$this->assertEquals( '', $switcher->sanitize_color( 'red;background:url(x)' ) );
		$this->assertEquals( '0px', $switcher->sanitize_size( '0', true ) );
		$this->assertEquals( '', $switcher->sanitize_size( '0' ) );
	}

	/**
	 * Test the design of the toggle, also via shortcode (filtered by kses).
	 *
	 * @return void
	 */
	public function test_toggle_design(): void {
		$this->go_to( get_permalink( $this->ids['about'] ) );
		$html = do_shortcode( '[easy-language-switcher display="toggle" toggle_color_on="rgba(255, 0, 0, 0.8)" toggle_color_off="#333" toggle_size="2em" toggle_radius="0" toggle_color_thumb="red;x:y"]' );
		$this->assertStringContainsString( '--easy-language-toggle-on:rgba(255, 0, 0, 0.8)', $html );
		$this->assertStringContainsString( '--easy-language-toggle-off:#333', $html );
		$this->assertStringContainsString( '--easy-language-toggle-size:2em', $html );
		$this->assertStringContainsString( '--easy-language-toggle-radius:0px', $html );
		$this->assertStringNotContainsString( '--easy-language-toggle-thumb', $html );

		// without design no style attribute.
		$this->assertStringNotContainsString( 'style=', do_shortcode( '[easy-language-switcher display="toggle"]' ) );
	}
}
