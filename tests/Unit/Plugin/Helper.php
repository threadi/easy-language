<?php
/**
 * Tests for class easyLanguage\Plugin\Helper.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\EasyLanguage\Term_Object;
use easyLanguage\Plugin\Helper as Plugin_Helper;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the functions in class easyLanguage\Plugin\Helper.
 */
class Helper extends easyLanguageTests {
	/**
	 * The request URI before the test.
	 *
	 * @var string
	 */
	private string $request_uri = '';

	/**
	 * The cached language of WordPress before the test.
	 *
	 * @var string|null
	 */
	private ?string $locale = null;

	/**
	 * Secure the request URI.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->locale      = $GLOBALS['locale'] ?? null;
		$this->request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( 'easy_language_icons' );
		delete_option( 'easy_language_taxonomies' );
		unset( $_SERVER['HTTP_REFERER'] );
		$_SERVER['REQUEST_URI'] = $this->request_uri;
		$_REQUEST               = array();
		set_current_screen( 'front' );
		set_query_var( 'lang', '' );

		// get_locale() caches the language in the global, so restore it for the following tests.
		if ( null === $this->locale ) {
			unset( $GLOBALS['locale'] );
		} else {
			$GLOBALS['locale'] = $this->locale; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
		parent::tear_down();
	}

	/**
	 * Create an image attachment with the given language codes as icon.
	 *
	 * @param array<string,int> $languages The language codes.
	 * @param string            $name      The post name of the attachment.
	 *
	 * @return int
	 */
	private function create_icon( array $languages, string $name ): int {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_name'      => $name,
				'post_mime_type' => 'image/png',
				'file'           => $name . '.png',
			)
		);
		update_post_meta( $attachment_id, 'easy_language_code', $languages );
		return $attachment_id;
	}

	/**
	 * A plugin is active if it is in the list of active plugins.
	 *
	 * @return void
	 */
	public function test_is_plugin_active(): void {
		$active = get_option( 'active_plugins', array() );
		update_option( 'active_plugins', array( 'example/example.php' ) );

		$this->assertTrue( Plugin_Helper::is_plugin_active( 'example/example.php' ) );
		$this->assertFalse( Plugin_Helper::is_plugin_active( 'example/other.php' ) );

		update_option( 'active_plugins', $active );
	}

	/**
	 * The URLs of the plugin point to the right pages.
	 *
	 * @return void
	 */
	public function test_urls(): void {
		$this->assertSame( admin_url() . 'options-general.php?page=easy_language_settings', Plugin_Helper::get_settings_page_url() );
		$this->assertSame( admin_url() . 'options-general.php?page=easy_language_settings&tab=api_logs', Plugin_Helper::get_api_logs_page_url() );
		$this->assertSame( admin_url() . 'options-general.php', Plugin_Helper::get_wp_settings_url() );
		$this->assertSame( 'https://wordpress.org/support/plugin/easy-language/', Plugin_Helper::get_plugin_support_url() );
		$this->assertStringEndsWith( '/', Plugin_Helper::get_plugin_url() );
		$this->assertFileExists( Plugin_Helper::get_plugin_path() . 'easy-language.php' );
		$this->assertStringContainsString( Plugin_Helper::get_plugin_url() . 'gfx/easy-language-icon.png', Plugin_Helper::get_logo_img() );
	}

	/**
	 * Without referer the settings page is used.
	 *
	 * @return void
	 */
	public function test_get_referer_url(): void {
		$this->assertSame( Plugin_Helper::get_settings_page_url(), Plugin_Helper::get_referer_url() );

		$_SERVER['HTTP_REFERER'] = admin_url( 'edit.php?post_type=page' );
		$this->assertSame( admin_url( 'edit.php?post_type=page' ), Plugin_Helper::get_referer_url() );
	}

	/**
	 * The language of WordPress is used, with a fallback for an empty locale.
	 *
	 * @return void
	 */
	public function test_get_wp_lang(): void {
		$this->assertSame( get_locale(), Plugin_Helper::get_wp_lang() );

		add_filter( 'locale', '__return_empty_string' );
		$this->assertSame( EASY_LANGUAGE_LANGUAGE_FALLBACK, Plugin_Helper::get_wp_lang() );
		remove_filter( 'locale', '__return_empty_string' );
	}

	/**
	 * The current language is the query var "lang" or the language of WordPress.
	 *
	 * @return void
	 */
	public function test_get_current_language(): void {
		set_query_var( 'lang', '' );
		$this->assertSame( Plugin_Helper::get_wp_lang(), Plugin_Helper::get_current_language() );

		set_query_var( 'lang', 'de_LS' );
		$this->assertSame( 'de_LS', Plugin_Helper::get_current_language() );
	}

	/**
	 * Dates are formatted with the format of the website.
	 *
	 * @return void
	 */
	public function test_get_format_date_time(): void {
		update_option( 'date_format', 'd.m.Y' );
		update_option( 'time_format', 'H:i' );
		update_option( 'timezone_string', 'UTC' );

		$this->assertSame( '24.12.2026 18:30', Plugin_Helper::get_format_date_time( '2026-12-24 18:30:00' ) );
	}

	/**
	 * Terms and meta are copied to the new object, but not the ignored meta keys.
	 *
	 * @return void
	 */
	public function test_copy_cpt(): void {
		$category = self::factory()->category->create( array( 'slug' => 'kopie-kategorie' ) );
		$old_id   = self::factory()->post->create();
		$new_id   = self::factory()->post->create();
		wp_set_object_terms( $old_id, array( $category ), 'category' );
		add_post_meta( $old_id, 'my_meta', 'value' );
		add_post_meta( $old_id, 'my_array', array( 'a' => 'b' ) );
		add_post_meta( $old_id, 'my_slashed', wp_slash( 'C:\\path' ) );
		add_post_meta( $old_id, '_edit_lock', '123:1' );
		add_post_meta( $old_id, 'filtered', 'x' );

		// ignore another key via filter.
		$ignore = static fn( array $keys ): array => array_merge( $keys, array( 'filtered' ) );
		add_filter( 'easy_language_post_meta_keys_to_ignore', $ignore );
		Plugin_Helper::copy_cpt( $old_id, $new_id );
		remove_filter( 'easy_language_post_meta_keys_to_ignore', $ignore );

		$this->assertSame( array( 'kopie-kategorie' ), wp_get_object_terms( $new_id, 'category', array( 'fields' => 'slugs' ) ) );
		$this->assertSame( 'value', get_post_meta( $new_id, 'my_meta', true ) );
		$this->assertSame( array( 'a' => 'b' ), get_post_meta( $new_id, 'my_array', true ) );
		$this->assertSame( 'C:\\path', get_post_meta( $new_id, 'my_slashed', true ) );
		$this->assertSame( '', get_post_meta( $new_id, '_edit_lock', true ) );
		$this->assertSame( '', get_post_meta( $new_id, 'filtered', true ) );
	}

	/**
	 * An object is not copied to itself and unknown objects are ignored.
	 *
	 * @return void
	 */
	public function test_copy_cpt_to_itself(): void {
		$post_id = self::factory()->post->create();
		add_post_meta( $post_id, 'my_meta', 'value' );

		Plugin_Helper::copy_cpt( $post_id, $post_id );
		$this->assertSame( array( 'value' ), get_post_meta( $post_id, 'my_meta' ) );

		// unknown source: nothing happens.
		Plugin_Helper::copy_cpt( 999999, $post_id );
		$this->assertSame( array( 'value' ), get_post_meta( $post_id, 'my_meta' ) );
	}

	/**
	 * The check for an entry in a list of settings errors.
	 *
	 * @return void
	 */
	public function test_check_if_setting_error_entry_exists_in_array(): void {
		$errors = array(
			array( 'setting' => 'easy_language_api' ),
			array( 'setting' => 'other' ),
		);
		$this->assertTrue( Plugin_Helper::check_if_setting_error_entry_exists_in_array( 'other', $errors ) );
		$this->assertFalse( Plugin_Helper::check_if_setting_error_entry_exists_in_array( 'missing', $errors ) );
		$this->assertFalse( Plugin_Helper::check_if_setting_error_entry_exists_in_array( 'other', array() ) );
	}

	/**
	 * Given values are kept by the validation of readonly fields.
	 *
	 * @return void
	 */
	public function test_settings_validate_fields(): void {
		$this->assertSame( array( 'a', 'b' ), Plugin_Helper::settings_validate_multiple_text_field( array( 'a', 'b' ) ) );
		$this->assertNull( Plugin_Helper::settings_validate_multiple_text_field( null ) );
		$this->assertSame( 'value', Plugin_Helper::settings_validate_select_field( 'value' ) );
		$this->assertNull( Plugin_Helper::settings_validate_select_field( null ) );
	}

	/**
	 * Attachments are found by their name.
	 *
	 * @return void
	 */
	public function test_get_attachment_by_post_name(): void {
		$attachment_id = $this->create_icon( array(), 'mein-icon' );

		$attachment = Plugin_Helper::get_attachment_by_post_name( ' mein-icon ' );
		$this->assertInstanceOf( \WP_Post::class, $attachment );
		$this->assertSame( $attachment_id, $attachment->ID );
		$this->assertFalse( Plugin_Helper::get_attachment_by_post_name( 'unbekannt' ) );
	}

	/**
	 * Attachments are found by the exact language code (not by a code which only starts with it).
	 *
	 * @return void
	 */
	public function test_get_attachment_by_language_code(): void {
		$icon = $this->create_icon( array( 'xx_LS' => 1 ), 'icon-ls' );
		$this->create_icon( array( 'xx_LS_test' => 1 ), 'icon-ls-test' );

		$attachment = Plugin_Helper::get_attachment_by_language_code( 'xx_LS' );
		$this->assertInstanceOf( \WP_Post::class, $attachment );
		$this->assertSame( $icon, $attachment->ID );
		$this->assertFalse( Plugin_Helper::get_attachment_by_language_code( 'xx_EL' ) );
		$this->assertFalse( Plugin_Helper::get_attachment_by_language_code( 'xx' ) );
	}

	/**
	 * Icons are returned from the cached list, else from the media library (and added to the list).
	 *
	 * @return void
	 */
	public function test_get_icon_img_for_language_code(): void {
		// from the cached list.
		update_option( 'easy_language_icons', array( 'xx_EL' => '<img src="cached.png" alt="">' ) );
		$this->assertSame( ' <img src="cached.png" alt="">', Plugin_Helper::get_icon_img_for_language_code( 'xx_EL' ) );

		// from the media library.
		$this->create_icon( array( 'xx_LS' => 1 ), 'icon-leicht' );
		$img = Plugin_Helper::get_icon_img_for_language_code( 'xx_LS' );
		$this->assertStringContainsString( 'icon-leicht', $img );
		$this->assertArrayHasKey( 'xx_LS', get_option( 'easy_language_icons' ) );

		// unknown language.
		$this->assertSame( '', Plugin_Helper::get_icon_img_for_language_code( 'xx_XX' ) );
	}

	/**
	 * The path (URL) of an icon is returned for known languages.
	 *
	 * @return void
	 */
	public function test_get_icon_path_for_language_code(): void {
		$this->create_icon( array( 'xx_LS' => 1 ), 'icon-pfad' );

		$this->assertStringContainsString( 'icon-pfad.png', Plugin_Helper::get_icon_path_for_language_code( 'xx_LS' ) );
		$this->assertSame( '', Plugin_Helper::get_icon_path_for_language_code( 'xx_XX' ) );
	}

	/**
	 * Objects are returned for supported post types and taxonomies only.
	 *
	 * @return void
	 */
	public function test_get_object(): void {
		update_option( 'easy_language_taxonomies', array( 'category' => '1' ) );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertInstanceOf( Post_Object::class, Plugin_Helper::get_object( $page_id ) );
		$this->assertInstanceOf( Post_Object::class, Plugin_Helper::get_object( $page_id, 'page' ) );

		$term_id = self::factory()->category->create();
		$this->assertInstanceOf( Term_Object::class, Plugin_Helper::get_object( $term_id, 'category' ) );

		// not supported post type.
		$attachment_id = $this->create_icon( array(), 'kein-objekt' );
		$this->assertFalse( Plugin_Helper::get_object( $attachment_id ) );

		// unknown object.
		$this->assertFalse( Plugin_Helper::get_object( 999999 ) );
	}

	/**
	 * A term of a not supported taxonomy is not returned as post with the same ID.
	 *
	 * @return void
	 */
	public function test_get_object_does_not_mix_terms_and_posts(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		// the taxonomy is not supported and no term with this ID exists.
		$this->assertFalse( Plugin_Helper::get_object( $page_id, 'category' ) );
	}

	/**
	 * The language of an object is the language of WordPress (without multilingual plugins).
	 *
	 * @return void
	 */
	public function test_get_lang_of_object(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( Plugin_Helper::get_wp_lang(), Plugin_Helper::get_lang_of_object( $page_id, 'page' ) );
		$this->assertSame( '', Plugin_Helper::get_lang_of_object( 999999, 'page' ) );
	}

	/**
	 * A hint is shown if the language of the website is not supported as source language by the API.
	 *
	 * @return void
	 */
	public function test_validate_language_support_on_api(): void {
		// prevent the request for the available translations of WordPress.
		set_site_transient( 'available_translations', array( 'fr_FR' => array( 'native_name' => 'Français' ) ) );

		$api        = \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance();
		$transients = \easyLanguage\Dependencies\easyTransientsForWordPress\Transients::get_instance();
		update_option( 'easy_language_intro_step_2', 1 );

		// not supported language.
		Plugin_Helper::validate_language_support_on_api( $api, 'fr_FR' );
		$transient = $transients->get_transient_by_name( 'easy_language_source_language_not_supported' );
		$this->assertTrue( $transient->is_set() );
		$this->assertStringContainsString( 'Français', $transient->get_message() );
		$this->assertFalse( get_option( 'easy_language_intro_step_2' ) );

		// supported language: the hint is removed.
		Plugin_Helper::validate_language_support_on_api( $api, 'de_DE' );
		$this->assertFalse( $transients->get_transient_by_name( 'easy_language_source_language_not_supported' )->is_set() );

		delete_site_transient( 'available_translations' );
	}

	/**
	 * The dialog for an unavailable page builder names the page builder.
	 *
	 * @return void
	 */
	public function test_get_dialog_for_unavailable_page_builder(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$json   = Plugin_Helper::get_dialog_for_unavailable_page_builder( new Post_Object( $page_id ), \easyLanguage\Parser\Elementor::get_instance() );
		$dialog = json_decode( $json, true );
		$this->assertIsArray( $dialog );
		$this->assertSame( 'Used page builder is not available', $dialog['title'] );
		$this->assertStringContainsString( 'Elementor', $dialog['texts'][0] );
		$this->assertSame( 'closeDialog();', $dialog['buttons'][0]['action'] );
	}

	/**
	 * The current URL in the backend and in the frontend.
	 *
	 * @return void
	 */
	public function test_get_current_url(): void {
		// backend.
		set_current_screen( 'edit-page' );
		$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=page';
		$this->assertSame( admin_url( 'edit.php?post_type=page' ), Plugin_Helper::get_current_url() );

		// frontend without queried object.
		set_current_screen( 'front' );
		$this->go_to( home_url( '/' ) );
		$this->assertSame( '', Plugin_Helper::get_current_url() );

		// frontend on a page.
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page_id ) );
		$this->assertSame( get_permalink( $page_id ), Plugin_Helper::get_current_url() );
	}

	/**
	 * The version of files is their modification time in debug mode.
	 *
	 * @return void
	 */
	public function test_get_file_version(): void {
		$file = Plugin_Helper::get_plugin_path() . 'easy-language.php';

		// the test environment uses WP_DEBUG, so the modification time is used.
		$this->assertTrue( WP_DEBUG );
		$this->assertSame( (string) filemtime( $file ), Plugin_Helper::get_file_version( $file ) );
	}

	/**
	 * Arrays are returned as JSON, an empty string if this is not possible.
	 *
	 * @return void
	 */
	public function test_get_json(): void {
		$this->assertSame( '{"a":"b"}', Plugin_Helper::get_json( array( 'a' => 'b' ) ) );
		$this->assertSame( '{"a":"\/"}', Plugin_Helper::get_json( array( 'a' => '/' ) ) );
		$this->assertSame( '{"a":"/"}', Plugin_Helper::get_json( array( 'a' => '/' ), JSON_UNESCAPED_SLASHES ) );
		$this->assertSame( '', Plugin_Helper::get_json( array( 'a' => NAN ) ) );
	}

	/**
	 * Requests to the REST API are recognized.
	 *
	 * @return void
	 */
	public function test_is_rest_request(): void {
		$this->assertFalse( Plugin_Helper::is_rest_request() );

		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/pages';
		$this->assertTrue( Plugin_Helper::is_rest_request() );
		unset( $GLOBALS['wp']->query_vars['rest_route'] );
	}

	/**
	 * The hidden section for settings exists.
	 *
	 * @return void
	 */
	public function test_get_hidden_section(): void {
		$section = Plugin_Helper::get_hidden_section();
		$this->assertInstanceOf( \easySettingsForWordPress\Section::class, $section );
		$this->assertSame( 'hidden_section', $section->get_name() );
	}

	/**
	 * The filesystem object is returned, also for local files.
	 *
	 * @return void
	 */
	public function test_get_wp_filesystem(): void {
		$this->assertInstanceOf( \WP_Filesystem_Base::class, Plugin_Helper::get_wp_filesystem() );
		$this->assertInstanceOf( \WP_Filesystem_Direct::class, Plugin_Helper::get_wp_filesystem( true ) );
	}

	/**
	 * The admin bar offers to create a simplification or to edit an existing one.
	 *
	 * @return void
	 */
	public function test_generate_admin_bar_language_menu(): void {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$page_id  = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:paragraph --><p>Ein Text.</p><!-- /wp:paragraph -->',
			)
		);
		$original = new Post_Object( $page_id );
		$copy     = $original->add_simplification_object( 'de_LS', \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance(), true );
		$this->assertInstanceOf( Post_Object::class, $copy );
		$languages = array(
			'de_LS' => array( 'label' => 'Leichte Sprache' ),
			'de_EL' => array( 'label' => 'Einfache Sprache' ),
		);

		$admin_bar = new \WP_Admin_Bar();
		Plugin_Helper::generate_admin_bar_language_menu( 'easy-language', $admin_bar, $languages, new Post_Object( $page_id ), 'page' );

		// the existing simplification is edited.
		$existing = $admin_bar->get_node( 'easy-language-de_LS' );
		$this->assertNotNull( $existing );
		$this->assertStringContainsString( 'post=' . $copy->get_id(), $existing->href );
		$this->assertStringStartsWith( 'Show this page', $existing->meta['title'] );

		// a new simplification could be created.
		$new = $admin_bar->get_node( 'easy-language-de_EL' );
		$this->assertNotNull( $new );
		$this->assertSame( $original->get_simplification_link( 'de_EL' ), $new->href );
		$this->assertStringStartsWith( 'Create a simplification', $new->meta['title'] );
	}
}
