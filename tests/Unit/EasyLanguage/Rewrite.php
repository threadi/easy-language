<?php
/**
 * Tests for class easyLanguage\EasyLanguage\Rewrite.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\EasyLanguage\Rewrite as Rewrite_Object;
use easyLanguage\Plugin\Languages;
use easyLanguage\Tests\Switcher_Tests;

/**
 * Object to test the URLs of simplified objects.
 */
class Rewrite extends Switcher_Tests {

	/**
	 * Return the URL part of the language "de_LS".
	 *
	 * @return string
	 */
	private function get_url_part(): string {
		$languages = Languages::get_instance()->get_active_languages();
		if ( empty( $languages['de_LS']['url'] ) ) {
			$this->markTestSkipped( 'Language de_LS is not active.' );
		}
		return (string) $languages['de_LS']['url'];
	}

	/**
	 * Register the rules of this plugin with the actual settings and rebuild the rewrite rules.
	 *
	 * @return void
	 */
	private function refresh_rules(): void {
		Rewrite_Object::get_instance()->set_rules();
		flush_rewrite_rules( false );
	}

	/**
	 * The permalink of a simplified page starts with the URL part of its language.
	 *
	 * @return void
	 */
	public function test_permalink_of_simplified_page(): void {
		$url = $this->get_url_part();

		$this->assertStringStartsWith( home_url( '/' . $url . '/' ), (string) get_permalink( $this->ids['about_ls'] ) );

		// the original is not changed.
		$this->assertStringStartsNotWith( home_url( '/' . $url . '/' ), (string) get_permalink( $this->ids['about'] ) );
	}

	/**
	 * The permalink of a simplified post starts with the URL part of its language.
	 *
	 * @return void
	 */
	public function test_permalink_of_simplified_post(): void {
		$url  = $this->get_url_part();
		$copy = $this->simplify( $this->ids['post'] );

		$this->assertStringStartsWith( home_url( '/' . $url . '/' ), (string) get_permalink( $copy ) );
	}

	/**
	 * The URL of a simplified page leads to this page and sets the language.
	 *
	 * @return void
	 */
	public function test_url_of_simplified_page_is_resolved(): void {
		$this->get_url_part();

		$this->refresh_rules();
		$this->go_to( (string) get_permalink( $this->ids['about_ls'] ) );

		$this->assertFalse( is_404() );
		$this->assertSame( $this->ids['about_ls'], get_queried_object_id() );
		$this->assertNotEmpty( get_query_var( 'lang' ) );
	}

	/**
	 * The URL of a child page with the language part leads to the simplified child page.
	 *
	 * @return void
	 */
	public function test_url_of_simplified_child_page_is_resolved(): void {
		$this->get_url_part();
		$child_ls = $this->simplify( $this->ids['child'] );

		$this->refresh_rules();
		$this->go_to( (string) get_permalink( $child_ls ) );

		$this->assertFalse( is_404() );
		$this->assertSame( $child_ls, get_queried_object_id() );
	}

	/**
	 * The front page of a language leads to the front page with the language.
	 *
	 * @return void
	 */
	public function test_language_front_page_is_resolved(): void {
		$url = $this->get_url_part();

		$this->refresh_rules();
		$this->go_to( home_url( '/' . $url . '/' ) );

		$this->assertFalse( is_404() );
		$this->assertSame( $url, get_query_var( 'lang' ) );
	}

	/**
	 * The rewrite rules of objects get a rule with the language part in front of each rule.
	 *
	 * @return void
	 */
	public function test_rules_on_objects(): void {
		global $wp_rewrite;
		$url = $this->get_url_part();

		$rules = array(
			'(.?.+?)/?$'                   => 'index.php?pagename=$matches[1]',
			'(.?.+?)/page/?([0-9]{1,})/?$' => 'index.php?pagename=$matches[1]&paged=$matches[2]',
		);

		$result = Rewrite_Object::get_instance()->set_rules_on_objects( $rules );

		// the original rules are kept.
		foreach ( $rules as $regex => $query ) {
			$this->assertSame( $query, $result[ $regex ] );
		}

		// each rule got a rule with the language part, in which all matches are moved by one.
		$this->assertCount( 2 * count( $rules ), $result );
		$language_rules = array_diff_key( $result, $rules );
		foreach ( $language_rules as $regex => $query ) {
			$this->assertStringStartsWith( $wp_rewrite->root . '(', $regex );
			$this->assertStringContainsString( $url, $regex );
			$this->assertStringStartsWith( 'index.php?lang=$matches[1]&', $query );
		}
		$this->assertContains( 'index.php?lang=$matches[1]&pagename=$matches[2]&paged=$matches[3]', $language_rules );
		$this->assertContains( 'index.php?lang=$matches[1]&pagename=$matches[2]', $language_rules );
	}

	/**
	 * The query variable for the language is added once.
	 *
	 * @return void
	 */
	public function test_query_vars(): void {
		$vars = Rewrite_Object::get_instance()->set_query_vars( array( 'p' ) );
		$this->assertSame( array( 'p', 'lang' ), $vars );
		$this->assertSame( array( 'p', 'lang' ), Rewrite_Object::get_instance()->set_query_vars( $vars ) );
	}

	/**
	 * The post link is only changed for simplified objects with an active language.
	 *
	 * @return void
	 */
	public function test_post_link_only_for_simplified_objects(): void {
		$url     = $this->get_url_part();
		$rewrite = Rewrite_Object::get_instance();

		// original: not changed.
		$this->assertSame( '/%postname%/', $rewrite->post_link( '/%postname%/', get_post( $this->ids['about'] ) ) );

		// simplified: with the language part.
		$this->assertSame( $url . '/%postname%/', $rewrite->post_link( '/%postname%/', get_post( $this->ids['about_ls'] ) ) );

		// simplified with a relative URI (e.g. from get_page_uri()): with separator.
		$this->assertSame( $url . '/about', $rewrite->post_link( 'about', get_post( $this->ids['about_ls'] ) ) );

		// simplified in a language which is not active: not changed.
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'easy_language_simplification_language', 'xx_unknown' );
		$this->assertSame( '/%postname%/', $rewrite->post_link( '/%postname%/', get_post( $post_id ) ) );
	}

	/**
	 * The language specific URLs could be disabled via filter.
	 *
	 * @return void
	 */
	public function test_post_link_could_be_disabled(): void {
		$this->get_url_part();
		add_filter( 'easy_language_prevent_simple_permalinks', '__return_true' );
		$this->assertSame( '/%postname%/', Rewrite_Object::get_instance()->post_link( '/%postname%/', get_post( $this->ids['about_ls'] ) ) );
		remove_filter( 'easy_language_prevent_simple_permalinks', '__return_true' );
	}

	/**
	 * Redirects are only prevented for URLs with the language part as complete path segment.
	 *
	 * @return void
	 */
	public function test_prevent_redirect_only_for_language_urls(): void {
		$url = $this->get_url_part();

		$this->assertSame( '', Rewrite_Object::get_instance()->prevent_redirect_for_front_page( home_url( '/' ), home_url( '/' . $url . '/' ) ) );
		$this->assertSame( home_url( '/' ), Rewrite_Object::get_instance()->prevent_redirect_for_front_page( home_url( '/' ), home_url( '/' . $url . 'x/' ) ) );
		$this->assertSame( home_url( '/' ), Rewrite_Object::get_instance()->prevent_redirect_for_front_page( home_url( '/' ), home_url( '/x' . $url . '/' ) ) );
	}

	/**
	 * A requested refresh of the rewrite rules runs once.
	 *
	 * @return void
	 */
	public function test_refresh_runs_once(): void {
		$flushes = 0;
		$counter = static function ( $value ) use ( &$flushes ) {
			++$flushes;
			return $value;
		};
		add_filter( 'rewrite_rules_array', $counter );

		Rewrite_Object::get_instance()->set_refresh();
		$this->assertNotFalse( get_transient( 'easy_language_refresh_rewrite_rules' ) );

		Rewrite_Object::get_instance()->do_refresh();
		Rewrite_Object::get_instance()->do_refresh();

		remove_filter( 'rewrite_rules_array', $counter );

		$this->assertSame( 1, $flushes );
		$this->assertFalse( get_transient( 'easy_language_refresh_rewrite_rules' ) );
	}

	/**
	 * The change of the front page requests a refresh of the rewrite rules.
	 *
	 * @return void
	 */
	public function test_change_of_front_page_requests_refresh(): void {
		delete_transient( 'easy_language_refresh_rewrite_rules' );
		update_option( 'show_on_front', 'posts' );
		$this->assertNotFalse( get_transient( 'easy_language_refresh_rewrite_rules' ) );
	}
}
