<?php
/**
 * Tests for the stability of the page builder integrations.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\G;

use easyLanguage\Tests\easyLanguageTests;
use ReflectionMethod;

/**
 * Object to test the stability of the page builder integrations.
 */
class PageBuilder_Stability extends easyLanguageTests {
	/**
	 * The settings captured from the switcher.
	 *
	 * @var array<string,mixed>
	 */
	private array $captured_settings = array();

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'easy_language_switcher_widget_fields' );
		remove_all_filters( 'easy_language_switcher_settings' );
		\easyLanguage\PageBuilder\Divi5::get_instance()->disable_product_duplication_slashing();
		parent::tear_down();
	}

	/**
	 * Test that every listed page builder class exists.
	 *
	 * @return void
	 */
	public function test_all_listed_page_builder_classes_exist(): void {
		$method = new ReflectionMethod( \easyLanguage\EasyLanguage\PageBuilders::class, 'get_page_builder' );
		$method->setAccessible( true );
		foreach ( $method->invoke( \easyLanguage\EasyLanguage\PageBuilders::get_instance() ) as $class_name ) {
			$this->assertTrue( class_exists( $class_name ), $class_name . ' does not exist' );
		}
	}

	/**
	 * Test that the slashing fix of Divi 5 is not used for our own inserts.
	 *
	 * @return void
	 */
	public function test_divi5_slashing_only_during_product_duplication(): void {
		self::add_saving_utility_stub();

		$divi5   = \easyLanguage\PageBuilder\Divi5::get_instance();
		$content = '<!-- wp:divi/text {"content":"<p>Text</p>"} /-->';

		// do not filter the content (like for users with the capability for unfiltered HTML).
		kses_remove_filters();

		// our own copies are slashed: the content must stay as it is without the fix.
		$this->assertFalse( has_filter( 'wp_insert_post_data', array( $divi5, 'fix_product_duplication_slashing' ) ) );
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_title'   => 'Divi 5',
					'post_content' => $content,
					'post_status'  => 'draft',
				)
			)
		);
		$this->assertSame( $content, get_post_field( 'post_content', $post_id, 'raw' ) );

		// the duplication of WooCommerce saves unslashed content: the fix is used only there.
		$divi5->enable_product_duplication_slashing();
		$this->assertSame( 1, has_filter( 'wp_insert_post_data', array( $divi5, 'fix_product_duplication_slashing' ) ) );
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Divi 5 duplicate',
				'post_content' => $content,
				'post_status'  => 'draft',
			)
		);
		$this->assertSame( $content, get_post_field( 'post_content', $post_id, 'raw' ) );

		// after the duplication the fix is removed.
		$divi5->disable_product_duplication_slashing();
		kses_init_filters();
		$this->assertFalse( has_filter( 'wp_insert_post_data', array( $divi5, 'fix_product_duplication_slashing' ) ) );
	}

	/**
	 * Add a stub for the saving utility of Divi 5.
	 *
	 * @return void
	 */
	private static function add_saving_utility_stub(): void {
		if ( class_exists( '\ET\Builder\VisualBuilder\Saving\SavingUtility' ) ) {
			return;
		}
		eval( 'namespace ET\Builder\VisualBuilder\Saving; class SavingUtility { public static function maybe_add_slash( $content ) { return wp_slash( $content ); } }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
	}

	/**
	 * Test that the dynamic tag of Elementor is not registered if Elementor Pro is in the list but not loaded.
	 *
	 * @return void
	 */
	public function test_elementor_dynamic_tag_without_loaded_pro(): void {
		if ( ! class_exists( '\Elementor\Core\DynamicTags\Manager' ) ) {
			eval( 'namespace Elementor\Core\DynamicTags; class Manager { public array $tags = array(); public function register( $tag ) { $this->tags[] = $tag; } }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}

		// mark Elementor Pro as active.
		update_option( 'active_plugins', array( 'elementor-pro/elementor-pro.php' ) );

		$manager = new \Elementor\Core\DynamicTags\Manager();
		\easyLanguage\PageBuilder\Elementor::get_instance()->add_dynamic_tags( $manager );
		$this->assertSame( array(), $manager->tags );

		update_option( 'active_plugins', array() );
	}

	/**
	 * Test that frontend AJAX requests of visitors are not handled as previews in Live Composer and Nimble Builder.
	 *
	 * @return void
	 */
	public function test_ajax_preview_requires_capability(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );

		$live_composer = new ReflectionMethod( \easyLanguage\PageBuilder\LiveComposer::class, 'is_preview' );
		$live_composer->setAccessible( true );
		$nimble = new ReflectionMethod( \easyLanguage\PageBuilder\Nimble::class, 'is_preview' );
		$nimble->setAccessible( true );

		// visitors.
		wp_set_current_user( 0 );
		$this->assertFalse( $live_composer->invoke( \easyLanguage\PageBuilder\LiveComposer::get_instance(), array() ) );
		$this->assertFalse( $nimble->invoke( \easyLanguage\PageBuilder\Nimble::get_instance() ) );

		// editors.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertTrue( $live_composer->invoke( \easyLanguage\PageBuilder\LiveComposer::get_instance(), array() ) );
		$this->assertTrue( $nimble->invoke( \easyLanguage\PageBuilder\Nimble::get_instance() ) );
	}

	/**
	 * Test that the values of Divi 5 with variables could be read without Divi 5.
	 *
	 * @return void
	 */
	public function test_divi5_values_without_divi(): void {
		$attrs  = array(
			'switcher' => array(
				'advanced' => array(
					'settings' => array(
						'desktop' => array(
							'value' => array(
								'display' => 'text',
								'color'   => '$variable({"type":"color","value":{"name":"gcid-primary"}})$',
							),
						),
					),
				),
			),
		);
		$values = \easyLanguage\PageBuilder\Divi5\Switcher_Module::get_instance()->get_values( $attrs );
		$this->assertSame( 'text', $values['display'] );
		$this->assertSame( $attrs['switcher']['advanced']['settings']['desktop']['value']['color'], $values['color'] );
	}

	/**
	 * Test that the sidebar is not enqueued for users without the capability to simplify.
	 *
	 * @return void
	 */
	public function test_sidebar_only_for_users_which_could_simplify(): void {
		global $wp_version;

		// bail if the WordPress version does not support the sidebar.
		if ( ! version_compare( $wp_version, '7.0', '>=' ) ) {
			$this->markTestSkipped( 'The sidebar requires WordPress 7.0 or newer.' );
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		set_current_screen( 'post' );

		$block_editor = \easyLanguage\PageBuilder\BlockEditor::get_instance();
		$block_editor->register_sidebar();

		// editors are not allowed to simplify.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$block_editor->enqueue_sidebar();
		$this->assertFalse( wp_script_is( 'easy-language-sidebar', 'enqueued' ) );

		// administrators are allowed.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$block_editor->enqueue_sidebar();
		$this->assertTrue( wp_script_is( 'easy-language-sidebar', 'enqueued' ) );

		wp_dequeue_script( 'easy-language-sidebar' );
		set_current_screen( 'front' );
	}

	/**
	 * Test that switches with true as default could be disabled in Bold Page Builder and Live Composer.
	 *
	 * @return void
	 */
	public function test_switch_with_true_default_could_be_disabled(): void {
		add_filter(
			'easy_language_switcher_widget_fields',
			static function ( array $fields ): array {
				$fields['hide_actual_language']['default'] = true;
				return $fields;
			}
		);
		add_filter(
			'easy_language_switcher_settings',
			function ( array $settings ): array {
				$this->captured_settings = $settings;
				return $settings;
			}
		);

		// Bold Page Builder saves unchecked checkboxes as empty value.
		$this->captured_settings = array();
		\easyLanguage\PageBuilder\BoldBuilder::get_instance()->render_switcher( array( 'hide_actual_language' => '' ) );
		$this->assertArrayHasKey( 'hide_actual_language', $this->captured_settings );
		$this->assertFalse( $this->captured_settings['hide_actual_language'] );

		// Live Composer saves unchecked checkboxes as false.
		$this->captured_settings = array();
		\easyLanguage\PageBuilder\LiveComposer::get_instance()->render( array( \easyLanguage\PageBuilder\LiveComposer::PREFIX . 'hide_actual_language' => false ) );
		$this->assertArrayHasKey( 'hide_actual_language', $this->captured_settings );
		$this->assertFalse( $this->captured_settings['hide_actual_language'] );

		// without a value the default is used.
		$this->captured_settings = array();
		\easyLanguage\PageBuilder\BoldBuilder::get_instance()->render_switcher( array() );
		$this->assertTrue( $this->captured_settings['hide_actual_language'] );
	}
}
