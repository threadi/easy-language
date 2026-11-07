<?php
/**
 * Tests for the integration of this plugin in the backend: notices, API change, setup, meta box and page builders.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\EasyLanguage\PageBuilders;
use easyLanguage\Plugin\Admin\Admin;
use easyLanguage\Plugin\Apis;
use easyLanguage\Plugin\Languages;
use easyLanguage\Plugin\Setup;
use easyLanguage\Tests\Switcher_Tests;

/**
 * Object to test the integration in the backend.
 */
class Backend_Wiring extends Switcher_Tests {

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'easy_language_page_builder_list' );
		unregister_post_type( 'el_classic_type' );
		parent::tear_down();
	}

	/**
	 * Users without the capability to see the notices could not dismiss them: the handler of the library is removed.
	 *
	 * @return void
	 */
	public function test_dismiss_of_notices_needs_capability(): void {
		$handler = array( Transients::get_instance(), 'dismiss_transient_via_ajax' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		add_action( 'wp_ajax_efw_dismiss_admin_notice', $handler );

		Admin::get_instance()->secure_dismiss_transient();

		$this->assertFalse( has_action( 'wp_ajax_efw_dismiss_admin_notice', $handler ) );
	}

	/**
	 * Notices of other plugins could not be dismissed via the handler of this plugin, also not by administrators.
	 *
	 * @return void
	 */
	public function test_dismiss_only_for_own_notices(): void {
		$handler = array( Transients::get_instance(), 'dismiss_transient_via_ajax' );
		add_action( 'wp_ajax_efw_dismiss_admin_notice', $handler );

		// without a name of an own notice in the request.
		Admin::get_instance()->secure_dismiss_transient();

		$this->assertFalse( has_action( 'wp_ajax_efw_dismiss_admin_notice', $handler ) );
	}

	/**
	 * The change of the API runs the routines of both APIs and is logged.
	 *
	 * @return void
	 */
	public function test_change_of_api_is_logged(): void {
		global $wpdb;

		// successful events are only logged in debug mode.
		update_option( 'easy_language_debug_mode', 1 );

		\easyLanguage\Plugin\Init::get_instance()->update_easy_language_api( 'summ_ai', 'no_api' );
		\easyLanguage\Plugin\Init::get_instance()->update_easy_language_api( '', 'summ_ai' );
		\easyLanguage\Plugin\Init::get_instance()->update_easy_language_api( 'unknown_api', 'also_unknown' );

		$table = \easyLanguage\Plugin\Log::get_instance()->get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertGreaterThan( 0, absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `log` LIKE %s', $table, '%summ_ai%no_api%' ) ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertGreaterThan( 0, absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `log` LIKE %s', $table, '%also_unknown%' ) ) ) );
	}

	/**
	 * The options for the setup are converted to the format of the React components.
	 *
	 * @return void
	 */
	public function test_setup_options_for_react(): void {
		$this->assertSame(
			array(
				array(
					'label' => 'Seiten',
					'value' => 'page',
				),
				array(
					'label' => 'Beiträge',
					'value' => 'post',
				),
			),
			Setup::get_instance()->convert_options_for_react(
				array(
					'page' => 'Seiten',
					'post' => 'Beiträge',
				)
			)
		);
		$this->assertSame( array(), Setup::get_instance()->convert_options_for_react( array() ) );
		$this->assertStringContainsString( 'page=easyLanguageSetup', Setup::get_instance()->get_setup_link() );
	}

	/**
	 * The setup gets a step for the API key, if the active API uses one.
	 *
	 * @return void
	 */
	public function test_setup_steps_with_api_key(): void {
		$steps   = array(
			1 => array( 'first' => array() ),
			2 => array( 'second' => array() ),
		);
		$api_obj = Apis::get_instance()->get_active_api();
		$result  = Setup::get_instance()->update_steps( $steps );

		if ( $api_obj && '' !== $api_obj->get_token_field_name() ) {
			$this->assertSame( $steps[2], $result[3] );
			$this->assertArrayHasKey( $api_obj->get_token_field_name(), $result[2] );
		} else {
			$this->assertSame( $steps, $result );
		}
		$this->assertSame( $steps[1], $result[1] );
	}

	/**
	 * Each listed page builder could be loaded, the list could be changed via filter.
	 *
	 * @return void
	 */
	public function test_page_builder_objects(): void {
		$objects = PageBuilders::get_instance()->get_page_builder_as_objects();
		$this->assertCount( count( ( new \ReflectionMethod( PageBuilders::class, 'get_page_builder' ) )->invoke( PageBuilders::get_instance() ) ), $objects );
		foreach ( $objects as $object ) {
			$this->assertInstanceOf( PageBuilder_Base::class, $object );
		}

		// unknown classes in the list are ignored.
		add_filter(
			'easy_language_page_builder_list',
			static function ( array $list ): array {
				$list[] = '\easyLanguage\PageBuilder\Not_Existing';
				return $list;
			}
		);
		$this->assertCount( count( $objects ), PageBuilders::get_instance()->get_page_builder_as_objects() );
	}

	/**
	 * The meta box is only added for supported post types.
	 *
	 * @return void
	 */
	public function test_meta_box_only_for_supported_post_types(): void {
		global $wp_meta_boxes;
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		register_post_type( 'el_classic_type', array( 'show_in_rest' => false ) );

		// not supported: no meta box.
		PageBuilders::get_instance()->add_meta_box( 'el_classic_type' );
		$this->assertEmpty( $wp_meta_boxes['el_classic_type']['side']['high']['easy-language'] ?? null );

		// supported: meta box (the post type does not use the block editor).
		update_option( 'easy_language_post_types', array( 'el_classic_type' => '1' ) );
		PageBuilders::get_instance()->add_meta_box( 'el_classic_type' );
		$this->assertSame( array( PageBuilders::get_instance(), 'render_meta_box_content' ), $wp_meta_boxes['el_classic_type']['side']['high']['easy-language']['callback'] ?? null );
	}

	/**
	 * The meta box of a simplified page shows its language.
	 *
	 * @return void
	 */
	public function test_meta_box_shows_language_of_simplified_page(): void {
		$languages = Languages::get_instance()->get_active_languages();
		$this->assertArrayHasKey( 'de_LS', $languages );

		ob_start();
		PageBuilders::get_instance()->render_meta_box_content( get_post( $this->ids['about_ls'] ) );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( esc_html( $languages['de_LS']['label'] ), $output );
	}

	/**
	 * Divi 4 gets the toggle and the field for the languages, Divi and Elementor are not active without their plugin.
	 *
	 * @return void
	 */
	public function test_divi_and_elementor_without_plugin(): void {
		$divi = \easyLanguage\PageBuilder\Divi::get_instance();
		$this->assertArrayHasKey( 'easy-language-simplifications', $divi->add_toggle( array() ) );

		$fields = $divi->add_fields( array( 'other' => array() ) );
		$this->assertArrayHasKey( 'other', $fields );
		$this->assertSame( 'easy-language-simplifications', $fields['easy-language-simplify-texts']['toggle_slug'] );
		$this->assertSame( array_keys( \easyLanguage\EasyLanguage\Init::get_instance()->get_supported_post_types() ), $fields['easy-language-simplify-texts']['depends_on_post_type'] );

		// the hierarchy of the scripts is only changed with Divi.
		$this->assertFalse( $divi->is_active() );
		$this->assertSame( 'window.', $divi->change_js_hierarchy( 'window.' ) );

		$this->assertFalse( (bool) \easyLanguage\PageBuilder\Elementor::get_instance()->is_active() );
	}
}
