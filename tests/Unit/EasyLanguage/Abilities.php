<?php
/**
 * Tests for class easyLanguage\EasyLanguage\Abilities.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\Tests\easyLanguageTests;
use WP_Error;

/**
 * Object to test the abilities of Easy Language (WordPress Abilities API since 6.9).
 */
class Abilities extends easyLanguageTests {
	/**
	 * The names of the read-only abilities.
	 *
	 * @var array<int,string>
	 */
	private array $read_abilities = array(
		'easy-language/get-configuration',
		'easy-language/get-object-simplifications',
		'easy-language/find-objects',
		'easy-language/list-texts',
	);

	/**
	 * The names of the abilities which change data.
	 *
	 * @var array<int,string>
	 */
	private array $write_abilities = array(
		'easy-language/create-simplification',
		'easy-language/save-text-simplification',
		'easy-language/set-text-state',
		'easy-language/set-automatic-prevention',
		'easy-language/run-simplification',
	);

	/**
	 * The ID of the example original post.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * The target language.
	 *
	 * @var string
	 */
	private string $target_language = 'de_LS';

	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// bail if the Abilities API is not available (WordPress < 6.9).
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'The Abilities API requires WordPress 6.9 or newer.' );
		}

		// use an administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// enable all abilities.
		update_option( \easyLanguage\EasyLanguage\Abilities::OPTION_ENABLED, 1 );
		update_option( \easyLanguage\EasyLanguage\Abilities::OPTION_MODE, 'all' );
		$this->reregister_abilities();

		// create an original post.
		$this->post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Hallo Abilities',
				'post_content' => '<!-- wp:paragraph --><p>Das ist ein komplizierter Text.</p><!-- /wp:paragraph -->',
				'post_type'    => 'post',
			)
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		// no TRUNCATE via Db::reset_simplifications() here: in MySQL it commits the test transaction,
		// the texts of this test would then remain for other tests. The rollback of WordPress removes them.

		// reset the settings.
		update_option( \easyLanguage\EasyLanguage\Abilities::OPTION_ENABLED, 1 );
		update_option( \easyLanguage\EasyLanguage\Abilities::OPTION_MODE, 'all' );
		$this->reregister_abilities();

		parent::tear_down();
	}

	/**
	 * Register our abilities again with the actual settings.
	 *
	 * Abilities may only be registered during the action "wp_abilities_api_init", which fires only once.
	 *
	 * @return void
	 */
	private function reregister_abilities(): void {
		global $wp_current_filter;

		// remove our abilities.
		foreach ( $this->get_registered_names() as $name ) {
			wp_unregister_ability( $name );
		}

		// register them again as if the action would be running.
		$wp_current_filter[] = 'wp_abilities_api_init';
		\easyLanguage\EasyLanguage\Abilities::get_instance()->register_abilities();
		array_pop( $wp_current_filter );
	}

	/**
	 * Return the names of our registered abilities.
	 *
	 * @return array<int,string>
	 */
	private function get_registered_names(): array {
		$names = array();
		foreach ( wp_get_abilities() as $ability ) {
			if ( str_starts_with( $ability->get_name(), 'easy-language/' ) ) {
				$names[] = $ability->get_name();
			}
		}
		sort( $names );
		return $names;
	}

	/**
	 * Execute an ability and return its result.
	 *
	 * @param string $name The name without namespace.
	 * @param mixed  $input The input.
	 *
	 * @return mixed
	 */
	private function run_ability( string $name, mixed $input = null ): mixed {
		$ability = wp_get_ability( 'easy-language/' . $name );
		$this->assertNotNull( $ability, 'Ability ' . $name . ' is not registered.' );
		return $ability->execute( $input );
	}

	/**
	 * Create a simplification of the example post and return its ID.
	 *
	 * @return int
	 */
	private function create_simplification(): int {
		$result = $this->run_ability(
			'create-simplification',
			array(
				'object_id' => $this->post_id,
				'language'  => $this->target_language,
			)
		);
		$this->assertIsArray( $result );
		return absint( $result['simplification']['object_id'] );
	}

	/**
	 * Test the registration in mode "all".
	 *
	 * @return void
	 */
	public function test_mode_all_registers_all_abilities(): void {
		$expected = array_merge( $this->read_abilities, $this->write_abilities );
		sort( $expected );
		$this->assertEquals( $expected, $this->get_registered_names() );
		$this->assertTrue( wp_has_ability_category( 'easy-language' ) );
	}

	/**
	 * Test the registration in mode "read".
	 *
	 * @return void
	 */
	public function test_mode_read_registers_only_read_abilities(): void {
		update_option( \easyLanguage\EasyLanguage\Abilities::OPTION_MODE, 'read' );
		$this->reregister_abilities();

		$expected = $this->read_abilities;
		sort( $expected );
		$this->assertEquals( $expected, $this->get_registered_names() );

		// each of them must be marked as read only.
		foreach ( $this->read_abilities as $name ) {
			$this->assertTrue( wp_get_ability( $name )->get_meta()['annotations']['readonly'] );
		}
	}

	/**
	 * Test that disabled abilities are not registered.
	 *
	 * @return void
	 */
	public function test_disabled_registers_nothing(): void {
		update_option( \easyLanguage\EasyLanguage\Abilities::OPTION_ENABLED, 0 );
		$this->reregister_abilities();
		$this->assertEmpty( $this->get_registered_names() );
	}

	/**
	 * Test that the abilities are available for REST and MCP.
	 *
	 * @return void
	 */
	public function test_abilities_are_public(): void {
		foreach ( $this->get_registered_names() as $name ) {
			$meta = wp_get_ability( $name )->get_meta();
			$this->assertTrue( $meta['show_in_rest'], $name );
			$this->assertTrue( $meta['mcp']['public'], $name );
		}
	}

	/**
	 * Test that missing settings are saved with their defaults (otherwise they could not be saved via the settings page).
	 *
	 * @return void
	 */
	public function test_default_options_are_added(): void {
		delete_option( \easyLanguage\EasyLanguage\Abilities::OPTION_ENABLED );
		delete_option( \easyLanguage\EasyLanguage\Abilities::OPTION_MODE );

		\easyLanguage\EasyLanguage\Abilities::get_instance()->add_default_options();

		$this->assertEquals( 1, get_option( \easyLanguage\EasyLanguage\Abilities::OPTION_ENABLED, false ) );
		$this->assertEquals( 'read', get_option( \easyLanguage\EasyLanguage\Abilities::OPTION_MODE, false ) );

		// existing values must not be changed.
		update_option( \easyLanguage\EasyLanguage\Abilities::OPTION_MODE, 'all' );
		\easyLanguage\EasyLanguage\Abilities::get_instance()->add_default_options();
		$this->assertEquals( 'all', get_option( \easyLanguage\EasyLanguage\Abilities::OPTION_MODE ) );
	}

	/**
	 * Test the permissions for users without the capability to simplify.
	 *
	 * @return void
	 */
	public function test_permissions_for_users_without_capability(): void {
		foreach ( array( 'editor', 'subscriber' ) as $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

			$this->assertInstanceOf( WP_Error::class, wp_get_ability( 'easy-language/get-configuration' )->execute(), $role );
			$this->assertInstanceOf( WP_Error::class, wp_get_ability( 'easy-language/get-object-simplifications' )->execute( array( 'object_id' => $this->post_id ) ), $role );
		}
	}

	/**
	 * Test the configuration.
	 *
	 * @return void
	 */
	public function test_get_configuration(): void {
		$result = $this->run_ability( 'get-configuration' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'summ_ai', $result['api']['name'] );
		$this->assertContains( $this->target_language, wp_list_pluck( $result['target_languages'], 'code' ) );
		$this->assertContains( 'post', wp_list_pluck( $result['post_types'], 'name' ) );
		$this->assertEquals( 'all', $result['abilities_mode'] );
	}

	/**
	 * Test that post types which are not registered in WordPress are ignored.
	 *
	 * @return void
	 */
	public function test_unknown_post_types_are_ignored(): void {
		$post_types = get_option( 'easy_language_post_types' );
		update_option( 'easy_language_post_types', array_merge( (array) $post_types, array( 'tour' => '1' ) ) );

		// the configuration must not contain it.
		$result = $this->run_ability( 'get-configuration' );
		$this->assertNotContains( 'tour', wp_list_pluck( $result['post_types'], 'name' ) );

		// the checks must show it as warning.
		$labels = wp_list_pluck( \easyLanguage\EasyLanguage\Abilities::get_instance()->get_checks(), 'message', 'label' );
		$this->assertArrayHasKey( 'Unknown post types', $labels );
		$this->assertStringContainsString( 'tour', $labels['Unknown post types'] );
		$this->assertStringNotContainsString( 'tour', $labels['Supported post types'] );

		update_option( 'easy_language_post_types', $post_types );
	}

	/**
	 * Test the creation of a simplification.
	 *
	 * @return void
	 */
	public function test_create_simplification(): void {
		$copy_id = $this->create_simplification();

		// the copy must be a simplified object of the original.
		$copy = new \easyLanguage\EasyLanguage\Post_Object( $copy_id );
		$this->assertTrue( $copy->is_simplified() );
		$this->assertEquals( $this->post_id, $copy->get_original_object_as_int() );

		// the copy must be locked (this needs a function which is not loaded outside of wp-admin).
		$this->assertNotEmpty( get_post_meta( $copy_id, '_edit_lock', true ) );

		// the status must show the new simplification with its open texts.
		$result = $this->run_ability( 'get-object-simplifications', array( 'object_id' => $this->post_id ) );
		$this->assertTrue( $result['simplifications'][0]['exists'] );
		$this->assertEquals( $copy_id, $result['simplifications'][0]['object_id'] );
		$this->assertEquals( 2, $result['simplifications'][0]['texts']['to_simplify'] );
	}

	/**
	 * Test the errors during creation of a simplification.
	 *
	 * @return void
	 */
	public function test_create_simplification_errors(): void {
		// language not active.
		$result = $this->run_ability(
			'create-simplification',
			array(
				'object_id' => $this->post_id,
				'language'  => 'xx_XX',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'easy_language_language_not_active', $result->get_error_code() );

		// already exists.
		$copy_id = $this->create_simplification();
		$result  = $this->run_ability(
			'create-simplification',
			array(
				'object_id' => $this->post_id,
				'language'  => $this->target_language,
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'easy_language_simplification_exists', $result->get_error_code() );

		// the copy itself can not be simplified.
		$result = $this->run_ability(
			'create-simplification',
			array(
				'object_id' => $copy_id,
				'language'  => $this->target_language,
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'easy_language_not_simplifiable', $result->get_error_code() );
	}

	/**
	 * Test to save own simplifications for all texts of an object.
	 *
	 * @return void
	 */
	public function test_save_text_simplification(): void {
		$copy_id = $this->create_simplification();

		// get the texts to simplify.
		$texts = $this->run_ability(
			'list-texts',
			array(
				'object_id' => $copy_id,
				'state'     => 'to_simplify',
			)
		);
		$this->assertEquals( 2, $texts['total'] );
		$this->assertCount( 2, $texts['items'] );

		// save a simplification for each.
		foreach ( $texts['items'] as $item ) {
			$result = $this->run_ability(
				'save-text-simplification',
				array(
					'object_id'      => $copy_id,
					'text_id'        => $item['text_id'],
					'simplification' => $item['html'] ? '<p>Ein einfacher Text.</p><script>alert(1)</script>' : 'Hallo',
				)
			);
			$this->assertIsArray( $result );
			$this->assertFalse( $result['used_existing'] );
		}
		$this->assertEquals( 0, $result['texts_to_simplify'] );

		// the copy must contain the simplifications without the script.
		$content = (string) get_post_field( 'post_content', $copy_id );
		$this->assertStringContainsString( 'Ein einfacher Text.', $content );
		$this->assertStringNotContainsString( '<script', $content );
		$this->assertEquals( 'Hallo', get_the_title( $copy_id ) );

		// the original must not be changed.
		$this->assertStringContainsString( 'Das ist ein komplizierter Text.', (string) get_post_field( 'post_content', $this->post_id ) );
		$this->assertEquals( 'Hallo Abilities', get_the_title( $this->post_id ) );

		// saving again without overwrite uses the existing simplification.
		$title_item = current( wp_list_filter( $texts['items'], array( 'html' => false ) ) );
		$result     = $this->run_ability(
			'save-text-simplification',
			array(
				'object_id'      => $copy_id,
				'text_id'        => $title_item['text_id'],
				'simplification' => 'Anderer Titel',
			)
		);
		$this->assertTrue( $result['used_existing'] );
		$this->assertEquals( 'Hallo', get_the_title( $copy_id ) );

		// with overwrite it is replaced.
		$result = $this->run_ability(
			'save-text-simplification',
			array(
				'object_id'      => $copy_id,
				'text_id'        => $title_item['text_id'],
				'simplification' => 'Anderer Titel',
				'overwrite'      => true,
			)
		);
		$this->assertFalse( $result['used_existing'] );
		$this->assertEquals( 'Anderer Titel', ( new \easyLanguage\EasyLanguage\Text( $title_item['text_id'] ) )->get_raw_simplification( $this->target_language ) );
	}

	/**
	 * Test to save a simplification of a text which is not used by the object.
	 *
	 * @return void
	 */
	public function test_save_text_simplification_errors(): void {
		$copy_id = $this->create_simplification();

		// text is not used by this object.
		$result = $this->run_ability(
			'save-text-simplification',
			array(
				'object_id'      => $copy_id,
				'text_id'        => 999999,
				'simplification' => 'Hallo',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'easy_language_text_not_found', $result->get_error_code() );

		// the original is not a simplified object.
		$result = $this->run_ability(
			'save-text-simplification',
			array(
				'object_id'      => $this->post_id,
				'text_id'        => 1,
				'simplification' => 'Hallo',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'easy_language_not_simplified', $result->get_error_code() );
	}

	/**
	 * Test to set the state of a text and the automatic prevention.
	 *
	 * @return void
	 */
	public function test_set_text_state_and_automatic_prevention(): void {
		$copy_id = $this->create_simplification();
		$texts   = $this->run_ability( 'list-texts', array( 'object_id' => $copy_id ) );
		$text_id = $texts['items'][0]['text_id'];

		$result = $this->run_ability(
			'set-text-state',
			array(
				'object_id' => $copy_id,
				'text_id'   => $text_id,
				'state'     => 'ignore',
			)
		);
		$this->assertEquals( 'ignore', $result['state'] );

		$result = $this->run_ability(
			'set-automatic-prevention',
			array(
				'object_id' => $copy_id,
				'prevent'   => false,
			)
		);
		$this->assertFalse( $result['prevent'] );

		// not allowed on an original.
		$result = $this->run_ability(
			'set-automatic-prevention',
			array(
				'object_id' => $this->post_id,
				'prevent'   => true,
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test the filters of find-objects.
	 *
	 * @return void
	 */
	public function test_find_objects(): void {
		$args = array(
			'post_type' => 'post',
			'language'  => $this->target_language,
			'per_page'  => 100,
		);

		// before: the post is missing a simplification.
		$missing = $this->run_ability( 'find-objects', array_merge( $args, array( 'filter' => 'missing' ) ) );
		$this->assertContains( $this->post_id, wp_list_pluck( $missing['items'], 'object_id' ) );

		// after: it is simplified and not missing anymore.
		$this->create_simplification();
		$missing    = $this->run_ability( 'find-objects', array_merge( $args, array( 'filter' => 'missing' ) ) );
		$simplified = $this->run_ability( 'find-objects', array_merge( $args, array( 'filter' => 'simplified' ) ) );
		$this->assertNotContains( $this->post_id, wp_list_pluck( $missing['items'], 'object_id' ) );
		$this->assertContains( $this->post_id, wp_list_pluck( $simplified['items'], 'object_id' ) );

		// not outdated yet.
		$outdated = $this->run_ability( 'find-objects', array_merge( $args, array( 'filter' => 'outdated' ) ) );
		$this->assertNotContains( $this->post_id, wp_list_pluck( $outdated['items'], 'object_id' ) );

		// after a change of the original it is outdated (the plugin sets this marker on save_post of supported post types;
		// these hooks are registered on plugin load, which happens in the test environment before the post types are configured).
		( new \easyLanguage\EasyLanguage\Post_Object( $this->post_id ) )->mark_as_changed_in_language( $this->target_language );
		$outdated = $this->run_ability( 'find-objects', array_merge( $args, array( 'filter' => 'outdated' ) ) );
		$this->assertContains( $this->post_id, wp_list_pluck( $outdated['items'], 'object_id' ) );
		$this->assertTrue( current( wp_list_filter( $outdated['items'], array( 'object_id' => $this->post_id ) ) )['languages'][ $this->target_language ]['outdated'] );
	}

	/**
	 * Test that the total of list-texts matches the texts the user can see.
	 *
	 * @return void
	 */
	public function test_list_texts_total_matches_visible_items(): void {
		$this->create_simplification();

		// add a text which is only used by an object of a post type which is not supported.
		register_post_type( 'el_unsupported', array( 'public' => true ) );
		$unsupported_id = self::factory()->post->create( array( 'post_type' => 'el_unsupported' ) );
		$text           = \easyLanguage\EasyLanguage\Db::get_instance()->add( 'Ein Text eines nicht unterstützten Typs.', 'de_DE', 'post_content', false );
		$this->assertInstanceOf( \easyLanguage\EasyLanguage\Text::class, $text );
		$text->set_object( 'el_unsupported', $unsupported_id, 1, 'gutenberg' );

		// get all visible texts page by page.
		$result = $this->run_ability( 'list-texts', array( 'per_page' => 1 ) );
		$items  = $result['items'];
		for ( $page = 2; $page <= $result['total_pages']; $page++ ) {
			$items = array_merge(
				$items,
				$this->run_ability(
					'list-texts',
					array(
						'per_page' => 1,
						'page'     => $page,
					)
				)['items']
			);
		}

		// the total must match the visible items and the hidden text must not be listed.
		$this->assertCount( $result['total'], $items );
		$this->assertNotContains( $text->get_id(), wp_list_pluck( $items, 'text_id' ) );

		unregister_post_type( 'el_unsupported' );
	}
}
