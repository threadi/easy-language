<?php
/**
 * Regression tests for stability fixes in the text handling (Texts, Text, Post_Object, Term_Object).
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\A2;

use easyLanguage\Apis\Summ_Ai\Summ_Ai;
use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use easyLanguage\EasyLanguage\Db;
use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\EasyLanguage\Term_Object;
use easyLanguage\EasyLanguage\Text;
use easyLanguage\EasyLanguage\Texts;
use easyLanguage\Tests\easyLanguageTests;
use RuntimeException;
use WP_Post;

/**
 * Object to test the stability fixes of package A2.
 */
class Texts_Stability extends easyLanguageTests {
	/**
	 * Name of the test post type.
	 */
	private const CPT = 'el_a2_cpt';

	/**
	 * The ID of an administrator.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Count of requests to external APIs.
	 *
	 * @var int
	 */
	private int $requests = 0;

	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->requests = 0;

		// use a supported source language.
		switch_to_locale( 'de_DE' );

		register_post_type( self::CPT, array( 'public' => true ) );

		add_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );
		add_filter( 'pre_http_request', array( $this, 'block_requests' ) );

		update_option( 'easy_language_taxonomies', array( 'category' => '1' ) );
		update_option(
			'easy_language_post_types',
			array(
				'post'    => '1',
				'page'    => '1',
				self::CPT => '1',
			)
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		restore_previous_locale();
		remove_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );
		remove_filter( 'pre_http_request', array( $this, 'block_requests' ) );
		remove_filter( 'pre_option_easy_language_api', array( $this, 'use_summ_ai' ) );
		unregister_post_type( self::CPT );
		update_option( 'easy_language_summ_ai_quota', 0 );
		Transients::get_instance()->get_transient_by_name( 'easy_language_simplification_not_possible' )->delete();
		$_GET     = array();
		$_REQUEST = array();
		unset( $_SERVER['HTTP_REFERER'] );
		parent::tear_down();
	}

	/**
	 * Throw an exception instead of a redirect.
	 *
	 * @param string $location The target.
	 *
	 * @return never
	 * @throws RuntimeException Always.
	 */
	public function stop_redirect( string $location ): never {
		throw new RuntimeException( 'redirect:' . $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Block and count all external requests.
	 *
	 * @return \WP_Error
	 */
	public function block_requests(): \WP_Error {
		++$this->requests;
		return new \WP_Error( 'blocked', 'External requests are blocked in tests.' );
	}

	/**
	 * Use SUMM AI as active API.
	 *
	 * @return string
	 */
	public function use_summ_ai(): string {
		return 'summ_ai';
	}

	/**
	 * Run a request handler and return the result.
	 *
	 * @param string              $method The method of Texts.
	 * @param array<string,mixed> $params The GET-params.
	 *
	 * @return string
	 */
	private function run_handler( string $method, array $params ): string {
		$_GET                    = $params;
		$_REQUEST                = $params;
		$_SERVER['HTTP_REFERER'] = admin_url( 'edit.php' );
		try {
			call_user_func( array( Texts::get_instance(), $method ) );
		} catch ( RuntimeException $e ) {
			return $e->getMessage();
		} catch ( \WPDieException $e ) {
			return 'die:' . $e->getMessage();
		}
		return 'none';
	}

	/**
	 * Create an original object.
	 *
	 * @param string $post_type The post type.
	 * @param string $title The title.
	 * @param string $paragraph The paragraph text.
	 *
	 * @return int
	 */
	private function create_original( string $post_type, string $title, string $paragraph ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => $post_type,
				'post_title'   => $title,
				'post_content' => '<!-- wp:paragraph --><p>' . $paragraph . '</p><!-- /wp:paragraph -->',
			)
		);
	}

	/**
	 * Create a simplification of the given object.
	 *
	 * @param int    $post_id The original.
	 * @param string $language The target language.
	 *
	 * @return Post_Object
	 */
	private function create_copy( int $post_id, string $language = 'de_LS' ): Post_Object {
		$copy = ( new Post_Object( $post_id ) )->add_simplification_object( $language, Summ_Ai::get_instance(), true );
		$this->assertInstanceOf( Post_Object::class, $copy );
		return $copy;
	}

	/**
	 * Return whether the text is linked to the given object.
	 *
	 * @param Text   $text The text.
	 * @param int    $object_id The object ID.
	 * @param string $object_type The object type.
	 *
	 * @return bool
	 */
	private function is_linked( Text $text, int $object_id, string $object_type ): bool {
		foreach ( $text->get_objects() as $object ) {
			if ( absint( $object['object_id'] ) === $object_id && $object_type === $object['object_type'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Finding 1: deleting a term only removes the links of its texts to this term.
	 *
	 * @return void
	 */
	public function test_delete_term_keeps_links_of_other_objects(): void {
		$text = Db::get_instance()->add( 'A2 gemeinsamer Text', 'de_DE', 'title', false );
		$this->assertInstanceOf( Text::class, $text );
		$post_id = $this->create_original( 'page', 'Seite', 'Absatz' );
		$term_id = self::factory()->category->create( array( 'name' => 'A2 Kategorie' ) );
		$text->set_object( 'page', $post_id, 0, 'gutenberg' );
		$text->set_object( 'category', $term_id, 0, '' );

		Texts::get_instance()->delete_term_object( $term_id, 0, 'category' );

		$this->assertTrue( $this->is_linked( $text, $post_id, 'page' ) );
		$this->assertFalse( $this->is_linked( $text, $term_id, 'category' ) );
	}

	/**
	 * Finding 6: deleting the link to an object respects the object type.
	 *
	 * @return void
	 */
	public function test_text_delete_respects_object_type(): void {
		$text = Db::get_instance()->add( 'A2 Text mit gleicher ID', 'de_DE', 'post_content', false );
		$this->assertInstanceOf( Text::class, $text );
		$text->set_object( 'page', 4712, 0, 'gutenberg' );
		$text->set_object( 'category', 4712, 0, '' );

		$text->delete( 4712, 'page' );

		$this->assertFalse( $this->is_linked( $text, 4712, 'page' ) );
		$this->assertTrue( $this->is_linked( $text, 4712, 'category' ) );
	}

	/**
	 * Finding 6 (multisite): a text used in another blog is not deleted completely if its last link in this blog is removed.
	 *
	 * @return void
	 */
	public function test_text_delete_keeps_text_used_in_other_blog(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only for multisite.' );
		}
		update_option( 'easy_language_delete_unused_simplifications', 1 );
		$text = Db::get_instance()->add( 'A2 Text in zwei Blogs', 'de_DE', 'post_content', false );
		$this->assertInstanceOf( Text::class, $text );
		$text->set_object( 'page', 4713, 0, 'gutenberg' );
		$blog_id = self::factory()->blog->create();
		switch_to_blog( $blog_id );
		$text->set_object( 'page', 4714, 0, 'gutenberg' );
		restore_current_blog();

		$text->delete( 4713, 'page' );

		// the text still exists (its state could be read from the DB).
		$this->assertNotSame( '', ( new Text( $text->get_id() ) )->get_state() );
		delete_option( 'easy_language_delete_unused_simplifications' );
	}

	/**
	 * Finding 2: trashing an original of a custom post type trashes its copies instead of deleting them.
	 *
	 * @return void
	 */
	public function test_trash_original_cpt_trashes_copies(): void {
		$post_id = $this->create_original( self::CPT, 'A2 CPT', 'Ein CPT-Text.' );
		$copy    = $this->create_copy( $post_id );

		wp_trash_post( $post_id );

		$this->assertInstanceOf( WP_Post::class, get_post( $copy->get_id() ) );
		$this->assertSame( 'trash', get_post_status( $copy->get_id() ) );
	}

	/**
	 * Finding 3: already known texts of a new term simplification are marked to simplify.
	 *
	 * @return void
	 */
	public function test_term_simplification_marks_known_texts_to_simplify(): void {
		$term_id = self::factory()->category->create( array( 'name' => 'A2 bekannte Kategorie' ) );
		$source  = \easyLanguage\Plugin\Helper::get_lang_of_object( $term_id, 'category' );
		if ( empty( $source ) ) {
			$source = \easyLanguage\Plugin\Helper::get_wp_lang();
		}
		$text = Db::get_instance()->add( 'A2 bekannte Kategorie', $source, 'taxonomy_title', false );
		$this->assertInstanceOf( Text::class, $text );
		$text->set_state( 'in_use' );

		$copy    = ( new Term_Object( $term_id, 'category' ) )->add_simplification_object( 'de_LS', Summ_Ai::get_instance(), false );
		$this->assertInstanceOf( Term_Object::class, $copy );

		$this->assertTrue( $this->is_linked( $text, $copy->get_id(), 'category' ) );
		$this->assertSame( 'to_simplify', $text->get_state() );
	}

	/**
	 * Finding 4: a term could be simplified in a second language.
	 *
	 * @return void
	 */
	public function test_term_simplification_in_second_language(): void {
		$term_id  = self::factory()->category->create( array( 'name' => 'A2 Zwei Sprachen' ) );
		$original = new Term_Object( $term_id, 'category' );

		$first = $original->add_simplification_object( 'de_LS', Summ_Ai::get_instance(), false );
		$this->assertInstanceOf( Term_Object::class, $first );
		$this->assertSame( 'A2 Zwei Sprachen-2', $first->get_name() );

		$second = ( new Term_Object( $term_id, 'category' ) )->add_simplification_object( 'de_EL', Summ_Ai::get_instance(), false );
		$this->assertInstanceOf( Term_Object::class, $second );
		$this->assertNotSame( $first->get_id(), $second->get_id() );
	}

	/**
	 * Finding 5: the simplification of a single text does not touch a running simplification of its object.
	 *
	 * @return void
	 */
	public function test_entry_simplification_respects_running_simplification(): void {
		add_filter( 'pre_option_easy_language_api', array( $this, 'use_summ_ai' ) );
		wp_set_current_user( $this->admin_id );
		$copy    = $this->create_copy( $this->create_original( 'page', 'A2 Läuft', 'A2 Text während Lauf.' ) );
		$entries = $copy->get_entries();
		$this->assertNotEmpty( $entries );
		$entry_id = $entries[0]->get_id();

		// mark as running like a JS-simplification does.
		$started = time();
		$copy->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, $started );

		$result = $this->run_handler(
			'get_simplification_of_entry_by_request',
			array(
				'id'    => $entry_id,
				'nonce' => wp_create_nonce( 'easy-language-get-simplification-of-entry-' . $entry_id ),
			)
		);

		$this->assertStringStartsWith( 'redirect:', $result );
		$this->assertSame( $started, absint( $copy->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING ) ) );
		$this->assertSame( 0, $this->requests );
		$copy->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, 0 );
	}

	/**
	 * Finding 7: no simplification without JS if the quota is used up.
	 *
	 * @return void
	 */
	public function test_no_js_simplification_with_used_up_quota(): void {
		add_filter( 'pre_option_easy_language_api', array( $this, 'use_summ_ai' ) );
		wp_set_current_user( $this->admin_id );
		$copy = $this->create_copy( $this->create_original( 'page', 'A2 Quota', 'A2 Text ohne Quota.' ) );
		update_option( 'easy_language_summ_ai_mode', 'free' );
		update_option( 'easy_language_summ_ai_quota', EASY_LANGUAGE_SUMM_AI_QUOTA + 1764 );

		$result = $this->run_handler(
			'get_post_simplification_by_request',
			array(
				'id'    => $copy->get_id(),
				'type'  => 'page',
				'nonce' => wp_create_nonce( 'easy-language-get-simplification-' . $copy->get_id() ),
			)
		);

		$this->assertSame( 'redirect:' . admin_url( 'edit.php' ), $result );
		$this->assertSame( 0, $this->requests );
		$this->assertTrue( Transients::get_instance()->get_transient_by_name( 'easy_language_simplification_not_possible' )->is_set() );
		$this->assertEmpty( $copy->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING ) );
	}

	/**
	 * Finding 8: the title is the raw title without prefixes or entities.
	 *
	 * @return void
	 */
	public function test_title_is_raw(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Jerry\'s "Haus" -- neu',
				'post_status' => 'private',
			)
		);

		$this->assertSame( 'Jerry\'s "Haus" -- neu', ( new Post_Object( $post_id ) )->get_title() );
	}

	/**
	 * Finding 9: a new paragraph in a simplified object, which is already known from another object, is linked to it.
	 *
	 * @return void
	 */
	public function test_update_links_known_text_to_copy(): void {
		// the paragraph is known from another object.
		$this->create_copy( $this->create_original( 'page', 'A2 Erste', 'A2 Gemeinsamer Absatz.' ) );
		$copy = $this->create_copy( $this->create_original( 'page', 'A2 Zweite', 'A2 Eigener Absatz.' ) );

		// add the known paragraph to the copy.
		$content = get_post_field( 'post_content', $copy->get_id(), 'raw' ) . '<!-- wp:paragraph --><p>A2 Gemeinsamer Absatz.</p><!-- /wp:paragraph -->';
		remove_all_actions( 'save_post_page' );
		wp_update_post(
			array(
				'ID'           => $copy->get_id(),
				'post_content' => $content,
			)
		);
		Texts::get_instance()->update_simplification_of_post( $copy->get_id(), get_post( $copy->get_id() ), true );

		$texts = array_map( static fn( Text $text ) => trim( wp_strip_all_tags( $text->get_original() ) ), $copy->get_entries() );
		$this->assertContains( 'A2 Gemeinsamer Absatz.', $texts );
	}

	/**
	 * Finding 10: texts of ACF-fields are not removed from a simplified object on its update.
	 *
	 * @return void
	 */
	public function test_update_keeps_acf_texts(): void {
		$copy = $this->create_copy( $this->create_original( 'page', 'A2 ACF', 'A2 Absatz mit ACF.' ) );
		$text = Db::get_instance()->add( 'A2 ACF-Feldinhalt', \easyLanguage\Plugin\Helper::get_wp_lang(), 'acf_meta_field_a2', false );
		$this->assertInstanceOf( Text::class, $text );
		$text->set_object( 'page', $copy->get_id(), 0, '' );

		Texts::get_instance()->update_simplification_of_post( $copy->get_id(), get_post( $copy->get_id() ), true );

		$this->assertTrue( $this->is_linked( $text, $copy->get_id(), 'page' ) );
	}

	/**
	 * Finding 11: after restoring a trashed copy, the newest copy is still found.
	 *
	 * @return void
	 */
	public function test_simplification_found_after_untrash_of_older_copy(): void {
		$post_id = $this->create_original( 'page', 'A2 Papierkorb', 'A2 Absatz Papierkorb.' );
		$first   = $this->create_copy( $post_id );
		wp_trash_post( $first->get_id() );
		$second = $this->create_copy( $post_id );
		wp_untrash_post( $first->get_id() );

		$this->assertSame( $second->get_id(), ( new Post_Object( $post_id ) )->get_simplification_in_language( 'de_LS' ) );
	}

	/**
	 * Finding 12: deleting an original term deletes its simplified terms.
	 *
	 * @return void
	 */
	public function test_delete_original_term_deletes_copies(): void {
		$term_id = self::factory()->category->create( array( 'name' => 'A2 Original-Kategorie' ) );
		$copy    = ( new Term_Object( $term_id, 'category' ) )->add_simplification_object( 'de_LS', Summ_Ai::get_instance(), false );
		$this->assertInstanceOf( Term_Object::class, $copy );

		wp_delete_term( $term_id, 'category' );

		$this->assertNull( get_term( $copy->get_id(), 'category' ) );
	}

	/**
	 * Finding 13: deleting an original of a no longer supported post type deletes its copies.
	 *
	 * @return void
	 */
	public function test_delete_original_of_unsupported_post_type(): void {
		$post_id = $this->create_original( self::CPT, 'A2 Nicht mehr', 'A2 Absatz nicht mehr.' );
		$copy    = $this->create_copy( $post_id );
		update_option( 'easy_language_post_types', array( 'page' => '1' ) );

		wp_delete_post( $post_id, true );

		$this->assertNull( get_post( $copy->get_id() ) );
	}
}
