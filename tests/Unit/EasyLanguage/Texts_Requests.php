<?php
/**
 * Tests for the request handlers and object hooks of class easyLanguage\EasyLanguage\Texts.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\EasyLanguage\Db;
use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\EasyLanguage\Term_Object;
use easyLanguage\EasyLanguage\Texts;
use easyLanguage\Tests\easyLanguageTests;
use RuntimeException;
use WPDieException;

/**
 * Object to test the request handlers (admin actions) and the hooks for posts and terms in class Texts.
 */
class Texts_Requests extends easyLanguageTests {
	/**
	 * Prefix of the exception message, which is thrown instead of a redirect.
	 */
	private const REDIRECT = 'redirect:';

	/**
	 * The ID of an administrator.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * The ID of a subscriber.
	 *
	 * @var int
	 */
	private int $subscriber_id;

	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// stop at redirects instead of sending headers and calling exit.
		add_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );

		// block any request to the APIs.
		add_filter( 'pre_http_request', array( $this, 'block_requests' ) );

		// support categories for the tests with terms.
		update_option( 'easy_language_taxonomies', array( 'category' => '1' ) );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );
		remove_filter( 'pre_http_request', array( $this, 'block_requests' ) );
		remove_filter( 'pre_option_easy_language_api', array( $this, 'use_no_api' ) );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		unset( $_SERVER['HTTP_REFERER'] );
		delete_option( 'easy_language_taxonomies' );
		parent::tear_down();
	}

	/**
	 * Throw an exception instead of a redirect.
	 *
	 * @param string $location The target of the redirect.
	 *
	 * @return never
	 * @throws RuntimeException Always.
	 */
	public function stop_redirect( string $location ): never {
		throw new RuntimeException( self::REDIRECT . $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Block all external requests.
	 *
	 * @return \WP_Error
	 */
	public function block_requests(): \WP_Error {
		return new \WP_Error( 'blocked', 'External requests are blocked in tests.' );
	}

	/**
	 * Use the pseudo-API "No API" as active API without saving the option (saving it would install translations).
	 *
	 * @return string
	 */
	public function use_no_api(): string {
		return 'no_api';
	}

	/**
	 * Set the request parameters like a click on a link would do.
	 *
	 * @param array<string,mixed> $params The GET-parameters.
	 *
	 * @return void
	 */
	private function set_request( array $params ): void {
		$_GET                    = $params;
		$_REQUEST                = $params;
		$_SERVER['HTTP_REFERER'] = admin_url( 'edit.php' );
	}

	/**
	 * Run the given handler and return how it ended: "redirect:<url>", "die:<message>" or "none".
	 *
	 * @param callable $handler The request handler.
	 *
	 * @return string
	 */
	private function run_handler( callable $handler ): string {
		try {
			call_user_func( $handler );
		} catch ( RuntimeException $e ) {
			return $e->getMessage();
		} catch ( WPDieException $e ) {
			return 'die:' . $e->getMessage();
		}
		return 'none';
	}

	/**
	 * Create an original page and return its ID.
	 *
	 * @return int
	 */
	private function create_page(): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Komplizierte Seite',
				'post_content' => '<!-- wp:paragraph --><p>Ein komplizierter Text.</p><!-- /wp:paragraph -->',
			)
		);
	}

	/**
	 * Create a simplification of the given original page and return its object.
	 *
	 * @param int $post_id The ID of the original.
	 *
	 * @return Post_Object
	 */
	private function create_simplification( int $post_id ): Post_Object {
		$copy = ( new Post_Object( $post_id ) )->add_simplification_object( 'de_LS', \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance(), true );
		$this->assertInstanceOf( Post_Object::class, $copy );
		return $copy;
	}

	/**
	 * Adding a simplification without a valid nonce is not possible.
	 *
	 * @return void
	 */
	public function test_add_post_simplification_requires_nonce(): void {
		wp_set_current_user( $this->admin_id );
		$post_id = $this->create_page();
		$this->set_request(
			array(
				'post'     => $post_id,
				'language' => 'de_LS',
				'nonce'    => 'invalid',
			)
		);

		$this->assertStringStartsWith( 'die:', $this->run_handler( array( Texts::get_instance(), 'add_post_object_to_simplification_by_request' ) ) );
		$this->assertFalse( ( new Post_Object( $post_id ) )->is_simplified_in_language( 'de_LS' ) );
	}

	/**
	 * Users without the capability could not add a simplification, even with a valid nonce.
	 *
	 * @return void
	 */
	public function test_add_post_simplification_requires_capability(): void {
		wp_set_current_user( $this->subscriber_id );
		$post_id = $this->create_page();
		$this->set_request(
			array(
				'post'     => $post_id,
				'language' => 'de_LS',
				'nonce'    => wp_create_nonce( 'easy-language-add-simplification-' . $post_id ),
			)
		);

		$this->assertStringStartsWith( 'die:', $this->run_handler( array( Texts::get_instance(), 'add_post_object_to_simplification_by_request' ) ) );
		$this->assertFalse( ( new Post_Object( $post_id ) )->is_simplified_in_language( 'de_LS' ) );
	}

	/**
	 * An administrator adds a simplification and is forwarded to the editor of the copy.
	 *
	 * @return void
	 */
	public function test_add_post_simplification(): void {
		wp_set_current_user( $this->admin_id );
		$post_id = $this->create_page();
		$this->set_request(
			array(
				'post'     => $post_id,
				'language' => 'de_LS',
				'nonce'    => wp_create_nonce( 'easy-language-add-simplification-' . $post_id ),
			)
		);

		$result = $this->run_handler( array( Texts::get_instance(), 'add_post_object_to_simplification_by_request' ) );

		// the simplification exists.
		$original = new Post_Object( $post_id );
		$this->assertTrue( $original->is_simplified_in_language( 'de_LS' ) );
		$copy_id = $original->get_simplification_in_language( 'de_LS' );
		$this->assertGreaterThan( 0, $copy_id );

		// the user is forwarded to the editor of the copy.
		$this->assertStringStartsWith( self::REDIRECT, $result );
		$this->assertStringContainsString( 'post=' . $copy_id, $result );
	}

	/**
	 * A request without language does not create a simplification.
	 *
	 * @return void
	 */
	public function test_add_post_simplification_without_language(): void {
		wp_set_current_user( $this->admin_id );
		$post_id = $this->create_page();
		$this->set_request(
			array(
				'post'  => $post_id,
				'nonce' => wp_create_nonce( 'easy-language-add-simplification-' . $post_id ),
			)
		);

		$this->assertStringStartsWith( self::REDIRECT, $this->run_handler( array( Texts::get_instance(), 'add_post_object_to_simplification_by_request' ) ) );
		$this->assertEmpty( ( new Post_Object( $post_id ) )->get_simplifications() );
	}

	/**
	 * Terms could only be simplified with nonce and capability.
	 *
	 * @return void
	 */
	public function test_add_term_simplification_is_protected(): void {
		$term_id = self::factory()->category->create( array( 'name' => 'Schwierige Kategorie' ) );

		// invalid nonce.
		wp_set_current_user( $this->admin_id );
		$this->set_request(
			array(
				'term'     => $term_id,
				'taxonomy' => 'category',
				'language' => 'de_LS',
				'nonce'    => 'invalid',
			)
		);
		$this->assertStringStartsWith( 'die:', $this->run_handler( array( Texts::get_instance(), 'add_term_object_to_simplification_by_request' ) ) );

		// missing capability.
		wp_set_current_user( $this->subscriber_id );
		$this->set_request(
			array(
				'term'     => $term_id,
				'taxonomy' => 'category',
				'language' => 'de_LS',
				'nonce'    => wp_create_nonce( 'easy-language-add-simplification-term-' . $term_id ),
			)
		);
		$this->assertStringStartsWith( 'die:', $this->run_handler( array( Texts::get_instance(), 'add_term_object_to_simplification_by_request' ) ) );

		// nothing has been created.
		$this->assertFalse( ( new Term_Object( $term_id, 'category' ) )->is_simplified_in_language( 'de_LS' ) );
	}

	/**
	 * Terms of not supported taxonomies could not be simplified.
	 *
	 * @return void
	 */
	public function test_add_term_simplification_requires_supported_taxonomy(): void {
		update_option( 'easy_language_taxonomies', array() );
		wp_set_current_user( $this->admin_id );
		$term_id = self::factory()->category->create( array( 'name' => 'Nicht unterstützt' ) );
		$this->set_request(
			array(
				'term'     => $term_id,
				'taxonomy' => 'category',
				'language' => 'de_LS',
				'nonce'    => wp_create_nonce( 'easy-language-add-simplification-term-' . $term_id ),
			)
		);

		$this->assertStringStartsWith( 'die:', $this->run_handler( array( Texts::get_instance(), 'add_term_object_to_simplification_by_request' ) ) );
	}

	/**
	 * An administrator adds a simplification of a term and is forwarded back.
	 *
	 * @return void
	 */
	public function test_add_term_simplification(): void {
		wp_set_current_user( $this->admin_id );
		$term_id = self::factory()->category->create(
			array(
				'name'        => 'Schwierige Kategorie',
				'description' => 'Eine komplizierte Beschreibung.',
			)
		);
		$this->set_request(
			array(
				'term'     => $term_id,
				'taxonomy' => 'category',
				'language' => 'de_LS',
				'nonce'    => wp_create_nonce( 'easy-language-add-simplification-term-' . $term_id ),
			)
		);

		$result = $this->run_handler( array( Texts::get_instance(), 'add_term_object_to_simplification_by_request' ) );
		$this->assertSame( self::REDIRECT . admin_url( 'edit.php' ), $result );

		// the copy exists and is linked to its original.
		$original = new Term_Object( $term_id, 'category' );
		$this->assertTrue( $original->is_simplified_in_language( 'de_LS' ) );
		$copy_id = $original->get_simplification_in_language( 'de_LS' );
		$this->assertSame( $term_id, ( new Term_Object( $copy_id, 'category' ) )->get_original_object_as_int() );

		// name and description of the copy are texts to simplify.
		$fields = array_map(
			static fn( $entry ) => $entry->get_original(),
			Db::get_instance()->get_entries(
				array(
					'object_id'   => $copy_id,
					'object_type' => 'category',
				)
			)
		);
		$this->assertContains( 'Schwierige Kategorie', $fields );
		$this->assertStringContainsString( 'Eine komplizierte Beschreibung.', implode( ' ', $fields ) );
	}

	/**
	 * The simplification via request is protected by nonce and capability.
	 *
	 * @return void
	 */
	public function test_get_post_simplification_is_protected(): void {
		add_filter( 'pre_option_easy_language_api', array( $this, 'use_no_api' ) );
		$post_id = $this->create_page();

		// invalid nonce.
		wp_set_current_user( $this->admin_id );
		$this->set_request(
			array(
				'id'    => $post_id,
				'type'  => 'page',
				'nonce' => 'invalid',
			)
		);
		$this->assertStringStartsWith( 'die:', $this->run_handler( array( Texts::get_instance(), 'get_post_simplification_by_request' ) ) );

		// missing capability.
		wp_set_current_user( $this->subscriber_id );
		$this->set_request(
			array(
				'id'    => $post_id,
				'type'  => 'page',
				'nonce' => wp_create_nonce( 'easy-language-get-simplification-' . $post_id ),
			)
		);
		$this->assertStringStartsWith( 'die:', $this->run_handler( array( Texts::get_instance(), 'get_post_simplification_by_request' ) ) );

		// wrong type for the object.
		wp_set_current_user( $this->admin_id );
		$this->set_request(
			array(
				'id'    => $post_id,
				'type'  => 'post',
				'nonce' => wp_create_nonce( 'easy-language-get-simplification-' . $post_id ),
			)
		);
		$this->assertStringStartsWith( 'die:', $this->run_handler( array( Texts::get_instance(), 'get_post_simplification_by_request' ) ) );
	}

	/**
	 * An administrator starts the simplification of an object and is forwarded back.
	 *
	 * @return void
	 */
	public function test_get_post_simplification(): void {
		add_filter( 'pre_option_easy_language_api', array( $this, 'use_no_api' ) );
		wp_set_current_user( $this->admin_id );
		$copy = $this->create_simplification( $this->create_page() );
		$this->set_request(
			array(
				'id'    => $copy->get_id(),
				'type'  => 'page',
				'nonce' => wp_create_nonce( 'easy-language-get-simplification-' . $copy->get_id() ),
			)
		);

		$this->assertSame( self::REDIRECT . admin_url( 'edit.php' ), $this->run_handler( array( Texts::get_instance(), 'get_post_simplification_by_request' ) ) );
	}

	/**
	 * The simplification of terms via request is protected by nonce and capability.
	 *
	 * @return void
	 */
	public function test_get_term_simplification_is_protected(): void {
		add_filter( 'pre_option_easy_language_api', array( $this, 'use_no_api' ) );
		$term_id = self::factory()->category->create( array( 'name' => 'Kategorie' ) );

		// invalid nonce.
		wp_set_current_user( $this->admin_id );
		$this->set_request(
			array(
				'id'       => $term_id,
				'taxonomy' => 'category',
				'nonce'    => 'invalid',
			)
		);
		$this->assertStringStartsWith( 'die:', $this->run_handler( array( Texts::get_instance(), 'get_term_simplification_by_request' ) ) );

		// missing capability.
		wp_set_current_user( $this->subscriber_id );
		$this->set_request(
			array(
				'id'       => $term_id,
				'taxonomy' => 'category',
				'nonce'    => wp_create_nonce( 'easy-language-get-term-simplification-' . $term_id ),
			)
		);
		$this->assertStringStartsWith( 'die:', $this->run_handler( array( Texts::get_instance(), 'get_term_simplification_by_request' ) ) );

		// allowed: forwarded back.
		wp_set_current_user( $this->admin_id );
		$this->set_request(
			array(
				'id'       => $term_id,
				'taxonomy' => 'category',
				'nonce'    => wp_create_nonce( 'easy-language-get-term-simplification-' . $term_id ),
			)
		);
		$this->assertSame( self::REDIRECT . admin_url( 'edit.php' ), $this->run_handler( array( Texts::get_instance(), 'get_term_simplification_by_request' ) ) );
	}

	/**
	 * The simplification of a single text is protected and bails on unknown texts.
	 *
	 * @return void
	 */
	public function test_get_simplification_of_entry(): void {
		add_filter( 'pre_option_easy_language_api', array( $this, 'use_no_api' ) );
		wp_set_current_user( $this->admin_id );

		// invalid nonce.
		$this->set_request(
			array(
				'id'    => 1,
				'nonce' => 'invalid',
			)
		);
		$this->assertStringStartsWith( 'die:', $this->run_handler( array( Texts::get_instance(), 'get_simplification_of_entry_by_request' ) ) );

		// unknown entry: forwarded back with a log entry.
		$this->set_request(
			array(
				'id'    => 999999,
				'nonce' => wp_create_nonce( 'easy-language-get-simplification-of-entry-999999' ),
			)
		);
		$this->assertSame( self::REDIRECT . admin_url( 'edit.php' ), $this->run_handler( array( Texts::get_instance(), 'get_simplification_of_entry_by_request' ) ) );

		// a text of a page.
		$copy    = $this->create_simplification( $this->create_page() );
		$entries = $copy->get_entries();
		$this->assertNotEmpty( $entries );
		$entry_id = $entries[0]->get_id();

		// subscribers are forwarded back without simplification.
		wp_set_current_user( $this->subscriber_id );
		$this->set_request(
			array(
				'id'    => $entry_id,
				'nonce' => wp_create_nonce( 'easy-language-get-simplification-of-entry-' . $entry_id ),
			)
		);
		$this->assertSame( self::REDIRECT . admin_url( 'edit.php' ), $this->run_handler( array( Texts::get_instance(), 'get_simplification_of_entry_by_request' ) ) );

		// administrators run it and are forwarded back.
		wp_set_current_user( $this->admin_id );
		$this->set_request(
			array(
				'id'    => $entry_id,
				'nonce' => wp_create_nonce( 'easy-language-get-simplification-of-entry-' . $entry_id ),
			)
		);
		$this->assertSame( self::REDIRECT . admin_url( 'edit.php' ), $this->run_handler( array( Texts::get_instance(), 'get_simplification_of_entry_by_request' ) ) );

		// the running marker has been removed after the run.
		$this->assertFalse( $copy->is_simplification_running() );
	}

	/**
	 * The export is protected by nonce and capability and needs a language.
	 *
	 * The successful export sends the file and calls exit, so it could not be tested here.
	 *
	 * @return void
	 */
	public function test_export_is_protected(): void {
		// invalid nonce.
		wp_set_current_user( $this->admin_id );
		$this->set_request(
			array(
				'lang'  => 'de_LS',
				'nonce' => 'invalid',
			)
		);
		$this->assertStringStartsWith( 'die:', $this->run_handler( array( Texts::get_instance(), 'export_simplifications_by_request' ) ) );

		// missing capability: forwarded back without export.
		wp_set_current_user( $this->subscriber_id );
		$this->set_request(
			array(
				'lang'  => 'de_LS',
				'nonce' => wp_create_nonce( 'easy-language-export-simplifications' ),
			)
		);
		$this->assertSame( self::REDIRECT . admin_url( 'edit.php' ), $this->run_handler( array( Texts::get_instance(), 'export_simplifications_by_request' ) ) );

		// no language: forwarded back without export.
		wp_set_current_user( $this->admin_id );
		$this->set_request(
			array(
				'nonce' => wp_create_nonce( 'easy-language-export-simplifications' ),
			)
		);
		$this->assertSame( self::REDIRECT . admin_url( 'edit.php' ), $this->run_handler( array( Texts::get_instance(), 'export_simplifications_by_request' ) ) );
	}

	/**
	 * Trashing an original also trashes its simplifications, untrashing restores them.
	 *
	 * @return void
	 */
	public function test_trash_and_untrash_original(): void {
		$post_id = $this->create_page();
		$copy    = $this->create_simplification( $post_id );

		wp_trash_post( $post_id );
		$this->assertSame( 'trash', get_post_status( $copy->get_id() ) );

		wp_untrash_post( $post_id );
		$this->assertNotSame( 'trash', get_post_status( $copy->get_id() ) );
	}

	/**
	 * Trashing a simplification removes its language on the original, untrashing adds it again.
	 *
	 * @return void
	 */
	public function test_trash_and_untrash_simplification(): void {
		$post_id = $this->create_page();
		$copy    = $this->create_simplification( $post_id );
		$this->assertTrue( ( new Post_Object( $post_id ) )->is_simplified_in_language( 'de_LS' ) );

		// the original keeps its state, the language is removed.
		wp_trash_post( $copy->get_id() );
		$this->assertSame( 'publish', get_post_status( $post_id ) );
		$this->assertFalse( ( new Post_Object( $post_id ) )->is_simplified_in_language( 'de_LS' ) );

		wp_untrash_post( $copy->get_id() );
		$this->assertTrue( ( new Post_Object( $post_id ) )->is_simplified_in_language( 'de_LS' ) );
	}

	/**
	 * Deleting an original deletes its simplifications and their texts.
	 *
	 * @return void
	 */
	public function test_delete_original_deletes_simplifications(): void {
		$post_id = $this->create_page();
		$copy    = $this->create_simplification( $post_id );
		$copy_id = $copy->get_id();
		$this->assertNotEmpty( $copy->get_entries() );

		wp_delete_post( $post_id, true );

		$this->assertNull( get_post( $copy_id ) );
		$this->assertEmpty(
			Db::get_instance()->get_entries(
				array(
					'object_id'   => $copy_id,
					'object_type' => 'page',
				)
			)
		);
	}

	/**
	 * Deleting a simplification keeps the original, removes the language marker and the texts of the copy.
	 *
	 * @return void
	 */
	public function test_delete_simplification_keeps_original(): void {
		$post_id = $this->create_page();
		$copy    = $this->create_simplification( $post_id );
		$copy_id = $copy->get_id();

		wp_delete_post( $copy_id, true );

		$this->assertInstanceOf( \WP_Post::class, get_post( $post_id ) );
		$this->assertFalse( ( new Post_Object( $post_id ) )->is_simplified_in_language( 'de_LS' ) );
		$this->assertEmpty(
			Db::get_instance()->get_entries(
				array(
					'object_id'   => $copy_id,
					'object_type' => 'page',
				)
			)
		);
	}

	/**
	 * Deleting a simplification permanently (without trash) removes the markers on the original.
	 *
	 * @return void
	 */
	public function test_delete_simplification_removes_markers_on_original(): void {
		$post_id = $this->create_page();
		$copy    = $this->create_simplification( $post_id );

		wp_delete_post( $copy->get_id(), true );

		$this->assertFalse( ( new Post_Object( $post_id ) )->has_simplifications() );
		$this->assertEmpty( get_post_meta( $post_id, 'easy_language_text_language', true ) );
	}

	/**
	 * Deleting a simplified term removes its language on the original term and its texts.
	 *
	 * @return void
	 */
	public function test_delete_simplified_term(): void {
		$term_id = self::factory()->category->create( array( 'name' => 'Kategorie zum Löschen' ) );
		$copy    = ( new Term_Object( $term_id, 'category' ) )->add_simplification_object( 'de_LS', \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance(), true );
		$this->assertInstanceOf( Term_Object::class, $copy );
		$copy_id = $copy->get_id();
		$this->assertTrue( ( new Term_Object( $term_id, 'category' ) )->is_simplified_in_language( 'de_LS' ) );

		wp_delete_term( $copy_id, 'category' );

		$this->assertFalse( ( new Term_Object( $term_id, 'category' ) )->is_simplified_in_language( 'de_LS' ) );
		$this->assertEmpty(
			Db::get_instance()->get_entries(
				array(
					'object_id'   => $copy_id,
					'object_type' => 'category',
				)
			)
		);
	}

	/**
	 * The simplified title and description are written into the term.
	 *
	 * @return void
	 */
	public function test_replace_term_texts(): void {
		$term_id = self::factory()->category->create( array( 'name' => 'Alter Name' ) );
		$text    = Db::get_instance()->add( 'Alter Name', 'de_DE', 'taxonomy_title', false );
		$this->assertInstanceOf( \easyLanguage\EasyLanguage\Text::class, $text );
		$text->set_simplification( 'Neuer Name', 'de_LS', 'summ_ai', 0 );
		$description = Db::get_instance()->add( 'Alte Beschreibung', 'de_DE', 'taxonomy_description', false );
		$description->set_simplification( 'Neue Beschreibung', 'de_LS', 'summ_ai', 0 );

		Texts::get_instance()->replace_term_texts(
			$text,
			'de_LS',
			$term_id,
			array(
				array(
					'object_id'   => $term_id,
					'object_type' => 'category',
					'field'       => 'taxonomy_title',
				),
				// entries of other objects or unknown taxonomies are ignored.
				array(
					'object_id'   => $term_id + 1000,
					'object_type' => 'category',
					'field'       => 'taxonomy_title',
				),
				array(
					'object_id'   => $term_id,
					'object_type' => 'unknown_taxonomy',
					'field'       => 'taxonomy_title',
				),
			)
		);
		Texts::get_instance()->replace_term_texts(
			$description,
			'de_LS',
			$term_id,
			array(
				array(
					'object_id'   => $term_id,
					'object_type' => 'category',
					'field'       => 'taxonomy_description',
				),
			)
		);

		$term = get_term( $term_id, 'category' );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertSame( 'Neuer Name', $term->name );
		$this->assertSame( 'Neue Beschreibung', trim( $term->description ) );
	}

	/**
	 * Simplified terms are hidden in term lists in the backend, but not in the frontend or during actions.
	 *
	 * @return void
	 */
	public function test_hide_simplified_terms(): void {
		$term_id = self::factory()->category->create( array( 'name' => 'Original' ) );
		$copy    = ( new Term_Object( $term_id, 'category' ) )->add_simplification_object( 'de_LS', \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance(), true );
		$this->assertInstanceOf( Term_Object::class, $copy );

		// reset the list of simplified terms, as it is cached per request.
		$texts    = Texts::get_instance();
		$property = new \ReflectionProperty( Texts::class, 'simplified_terms' );
		$property->setValue( $texts, array() );

		$args = array(
			'taxonomy' => array( 'category' ),
			'exclude'  => array( 4711 ),
		);

		// frontend: nothing is changed.
		$this->assertSame( $args, $texts->hide_simplified_terms( $args ) );

		// backend: the copy is excluded, existing excludes are kept.
		set_current_screen( 'edit-category' );
		$result = $texts->hide_simplified_terms( $args );
		$this->assertContains( $copy->get_id(), $result['exclude'] );
		$this->assertContains( 4711, $result['exclude'] );
		$this->assertNotContains( $term_id, $result['exclude'] );

		// during an action nothing is changed.
		$_GET['action'] = 'edit';
		$this->assertSame( $args, $texts->hide_simplified_terms( $args ) );
		unset( $_GET['action'] );

		// not supported taxonomies are not changed.
		$tag_args = array( 'taxonomy' => array( 'post_tag' ) );
		$this->assertSame( $tag_args, $texts->hide_simplified_terms( $tag_args ) );

		set_current_screen( 'front' );
		$property->setValue( $texts, array() );
	}
}
