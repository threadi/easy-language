<?php
/**
 * Regression tests for the fixes in the language icons.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\D;

use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Language_Icon;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the fixes in the language icons.
 */
class Icon_Fixes extends easyLanguageTests {
	/**
	 * The name of the icon file used in the tests.
	 *
	 * @var string
	 */
	private string $file = '';

	/**
	 * The path of the source of the icon file.
	 *
	 * @var string
	 */
	private string $source = '';

	/**
	 * Prepare an icon file with a unique name.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->file   = 'el_test_icon_' . wp_rand() . '.png';
		$dir          = trailingslashit( get_temp_dir() ) . 'el-test-icons/';
		wp_mkdir_p( $dir );
		$this->source = $dir . $this->file;
		copy( Helper::get_plugin_path() . 'gfx/de_at.png', $this->source );

		add_filter( 'easy_language_icon_path', array( $this, 'get_icon_path' ) );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'easy_language_icon_path', array( $this, 'get_icon_path' ) );
		remove_filter( 'wp_handle_sideload_prefilter', array( $this, 'fail_upload' ) );
		wp_delete_file( $this->source );
		parent::tear_down();
	}

	/**
	 * Return the path to our test icon.
	 *
	 * @return string
	 */
	public function get_icon_path(): string {
		return $this->source;
	}

	/**
	 * Let the upload fail.
	 *
	 * @param array<string,mixed> $file The file.
	 *
	 * @return array<string,mixed>
	 */
	public function fail_upload( array $file ): array {
		$file['error'] = 'el-test upload failed';
		return $file;
	}

	/**
	 * Return the IDs of the attachments assigned to the given language code.
	 *
	 * @param string $language_code The language code.
	 *
	 * @return array<int,int>
	 */
	private function get_icons_of_language( string $language_code ): array {
		global $wpdb;
		return array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT `post_id` FROM %i WHERE `meta_key` = %s AND `meta_value` LIKE %s', $wpdb->postmeta, 'easy_language_code', '%' . $wpdb->esc_like( '"' . $language_code . '"' ) . '%' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * A failed import does not mark any other post as icon and removes the temporary file.
	 *
	 * @return void
	 */
	public function test_failed_import(): void {
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'fail_upload' ) );

		$icon = new Language_Icon();
		$icon->set_file( $this->file );
		$icon->save( 'el_FAIL' );

		$this->assertSame( array(), $this->get_icons_of_language( 'el_FAIL' ) );
		$this->assertFileDoesNotExist( trailingslashit( get_temp_dir() ) . $this->file );
	}

	/**
	 * An attachment of the user with the same name is not used as icon (and would be removed during uninstallation).
	 *
	 * @return void
	 */
	public function test_foreign_attachment_is_not_used(): void {
		$foreign_id = self::factory()->attachment->create(
			array(
				'post_name'      => pathinfo( $this->file, PATHINFO_FILENAME ),
				'post_title'     => 'Own image of the user',
				'post_mime_type' => 'image/png',
			)
		);
		$this->assertInstanceOf( \WP_Post::class, Helper::get_attachment_by_post_name( pathinfo( $this->file, PATHINFO_FILENAME ) ) );

		$icon = new Language_Icon();
		$icon->set_file( $this->file );
		$icon->save( 'el_OWN' );

		// the image of the user is not marked.
		$this->assertEmpty( get_post_meta( $foreign_id, 'easy_language_icon', true ) );
		$this->assertEmpty( get_post_meta( $foreign_id, 'easy_language_code', true ) );

		// a new attachment has been created as icon.
		$this->assertNotSame( $foreign_id, $icon->get_id() );
		$this->assertSame( array( $icon->get_id() ), $this->get_icons_of_language( 'el_OWN' ) );
		$this->assertEquals( 1, get_post_meta( $icon->get_id(), 'easy_language_icon', true ) );

		// the icon of this plugin is used again for other languages.
		$icon2 = new Language_Icon();
		$icon2->set_file( $this->file );
		$icon2->save( 'el_OWN2' );
		$this->assertSame( $icon->get_id(), $icon2->get_id() );

		wp_delete_attachment( $icon->get_id(), true );
	}
}
