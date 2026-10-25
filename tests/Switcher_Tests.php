<?php
/**
 * File with the base for all tests of the language switcher.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\EasyLanguage\Term_Object;
use easyLanguage\Plugin\Apis;

/**
 * Base for the tests of the language switcher: creates pages, posts, terms and their simplifications.
 */
abstract class Switcher_Tests extends easyLanguageTests {

	/**
	 * IDs of the test objects.
	 *
	 * @var array<string,int>
	 */
	protected array $ids = array();

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// make sure our CPT is registered before each test.
		\easyLanguage\EasyLanguage\Switcher::get_instance()->wp_init();

		// use German as language of the website.
		add_filter(
			'locale',
			function () {
				return 'de_DE';
			}
		);

		// configure the plugin.
		update_option( 'easy_language_post_types', array( 'page' => 1, 'post' => 1 ) );
		update_option( 'easy_language_taxonomies', array( 'category' => 1 ) );
		update_option( 'easy_language_summ_ai_target_languages_de_LS', 1 );
		update_option( 'easy_language_switcher_link', 'hide_not_translated' );
		update_option( 'easy_language_switcher_compatibility', 0 );
		update_option( 'easy_language_switcher_icon_size', 0 );

		// create the objects.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->ids['front']   = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Start' ) );
		$this->ids['about']   = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'About' ) );
		$this->ids['contact'] = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Contact' ) );
		$this->ids['draft']   = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Draft' ) );
		$this->ids['area']    = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Area' ) );
		$this->ids['child']   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Child',
				'post_parent' => $this->ids['area'],
			)
		);
		$this->ids['cat']     = self::factory()->category->create( array( 'name' => 'Inclusion' ) );
		$this->ids['post']    = self::factory()->post->create(
			array(
				'post_title'    => 'Post',
				'post_category' => array( $this->ids['cat'] ),
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $this->ids['front'] );

		// create the simplifications.
		$this->ids['about_ls'] = $this->simplify( $this->ids['about'] );
		$this->ids['draft_ls'] = $this->simplify( $this->ids['draft'], 'draft' );
		$this->ids['area_ls']  = $this->simplify( $this->ids['area'] );
		$term                  = new Term_Object( $this->ids['cat'], 'category' );
		$term_copy             = $term->add_simplification_object( 'de_LS', Apis::get_instance()->get_active_api(), true );
		$this->ids['cat_ls']   = $term_copy instanceof Term_Object ? $term_copy->get_id() : 0;

		$this->set_permalink_structure( '/%postname%/' );
	}

	/**
	 * Create a simplification for the given post.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $status The status of the simplification.
	 *
	 * @return int
	 */
	protected function simplify( int $post_id, string $status = 'publish' ): int {
		$copy = ( new Post_Object( $post_id ) )->add_simplification_object( 'de_LS', Apis::get_instance()->get_active_api(), true );
		$this->assertInstanceOf( Post_Object::class, $copy );
		wp_update_post(
			array(
				'ID'          => $copy->get_id(),
				'post_status' => $status,
			)
		);
		return $copy->get_id();
	}

	/**
	 * Return the codes of the entries for the actual request.
	 *
	 * @param array<string,mixed> $settings The settings.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_entries( array $settings = array() ): array {
		$result = \easyLanguage\EasyLanguage\Switcher::get_instance()->get_entries( $settings );
		return $result['entries'] ?? array();
	}
}
