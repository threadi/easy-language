<?php
/**
 * File to add Elementor as supported page builder.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Helper;
use easyLanguage\Multilingual_plugins\Easy_Language\Init;
use easyLanguage\Multilingual_plugins\Easy_Language\PageBuilder\Elementor\Switcher_Widget;
use Elementor\Controls_Manager;
use Elementor\Core\DocumentTypes\Page;
use Elementor\Core\DocumentTypes\Post;
use easyLanguage\Multilingual_Plugins;
use easyLanguage\Multilingual_plugins\Easy_Language\PageBuilder\Elementor\Languages;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Elementor;
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;
use Elementor\Plugin;

/**
 * Add Elementor-object to list of supported pagebuilder.
 *
 * @param array<int,mixed> $pagebuilder_list List of supported pagebuilder.
 *
 * @return array<int,mixed>
 */
function easy_language_pagebuilder_elementor( array $pagebuilder_list ): array {
	// add Elementor as PageBuilder.
	$pagebuilder_list[] = Elementor::get_instance();

	// return list of supported page-builders.
	return $pagebuilder_list;
}

/**
 * Embed additional Elementor-settings only if needed.
 *
 * @return void
 */
function easy_language_pagebuilder_elementor_init(): void {
	add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_elementor' );
	if ( did_action( 'elementor/loaded' ) ) {
		add_action( 'elementor/widgets/register', 'easy_language_pagebuilder_elementor_register_widgets' );
	}
}
add_action( 'init', 'easy_language_pagebuilder_elementor_init' );

/**
 * Embed custom Elementor widgets.
 *
 * @return void
 */
function easy_language_pagebuilder_elementor_register_widgets(): void {
	Plugin::instance()->widgets_manager->register( new Switcher_Widget() );
}

/**
 * Add Elementor control under settings to switch between languages for posts.
 *
 * @param Post $page The Post-object.
 *
 * @return void
 */
function easy_language_add_elementor_page_settings_controls( Post $page ): void {
	// get the original-object.
	$post_object = new Post_Object( $page->get_id() );

	// get the post-language.
	$language_array = $post_object->get_language();
	$language       = reset( $language_array );

	if ( ! empty( $language ) ) {
		/**
		 * Add section.
		 */
		$page->start_controls_section(
			'easy_language',
			array(
				'label' => esc_html__( 'Simplify texts', 'easy-language' ),
				'tab'   => Controls_Manager::TAB_SETTINGS,
			)
		);

		/**
		 * Add our custom control in this section.
		 */
		$page->add_control(
			'menu_item_color_custom',
			array(
				'label'       => '',
				'label_block' => true,
				/* translators: %1$s will be replaced by the type of the object, %2$s will be replaced by the name of the language */
				'description' => sprintf( __( 'You are editing this %1$s in the language <strong>%2$s</strong>.', 'easy-language' ), esc_html( $post_object->get_type_name() ), esc_html( $language['label'] ) ),
				'type'        => 'easy_languages',
			)
		);
		$page->end_controls_section();
	}
}
add_action( 'elementor/element/wp-post/document_settings/after_section_end', 'easy_language_add_elementor_page_settings_controls' );

/**
 * Add Elementor control under settings to switch between languages for pages.
 *
 * @param Page $page The Page-object.
 *
 * @return void
 */
function easy_language_add_elementor_page_settings_controls_page( Page $page ): void {
	// get the original-object.
	$post_object = new Post_Object( $page->get_id() );

	// get the post-language.
	$language_array = $post_object->get_language();
	$language       = reset( $language_array );

	if ( ! empty( $language ) ) {
		/**
		 * Add section.
		 */
		$page->start_controls_section(
			'easy_language',
			array(
				'label' => esc_html__( 'Simplify texts', 'easy-language' ),
				'tab'   => Controls_Manager::TAB_SETTINGS,
			)
		);

		/**
		 * Add our custom control in this section.
		 */
		$page->add_control(
			'menu_item_color_custom',
			array(
				'label'       => '',
				'label_block' => true,
				/* translators: %1$s will be replaced by the type of the object, %2$s will be replaced by the name of the language */
				'description' => sprintf( __( 'You are editing this %1$s in the language <strong>%2$s</strong>.', 'easy-language' ), esc_html( $post_object->get_type_name() ), esc_html( $language['label'] ) ),
				'type'        => 'easy_languages',
			)
		);
		$page->end_controls_section();
	}
}
add_action( 'elementor/element/wp-page/document_settings/after_section_end', 'easy_language_add_elementor_page_settings_controls_page' );

/**
 * Register custom controls for elementor.
 *
 * @param Controls_Manager $controls_manager Object of Controls Manager from Elementor.
 *
 * @return void
 */
function easy_language_pagebuilder_elementor_add_custom_control( Controls_Manager $controls_manager ): void {
	$controls_manager->register( new Languages() );
}
add_action( 'elementor/controls/register', 'easy_language_pagebuilder_elementor_add_custom_control' );

/**
 * Add elementor-specific backend style.
 *
 * @return void
 */
function easy_language_pagebuilder_elementor_styles(): void {
	// add only scripts and styles if our own plugin is used.
	$multilingual_plugin = false;
	foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
		if ( false === $plugin_obj->is_foreign_plugin() ) {
			$multilingual_plugin = $plugin_obj;
		}
	}
	if ( false !== $multilingual_plugin ) {
		wp_register_style(
			'easy-language-elementor-admin',
			trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'classes/multilingual-plugins/easy-language/admin/elementor.css',
			array(),
			Helper::get_file_version( plugin_dir_path( EASY_LANGUAGE ) . '/classes/multilingual-plugins/easy-language/admin/elementor.css' ),
		);
		wp_enqueue_style( 'easy-language-elementor-admin' );

		// elementor-specific backend-JS.
		wp_register_script(
			'easy-language-elementor-admin',
			trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'classes/multilingual-plugins/easy-language/admin/elementor.js',
			array( 'jquery', 'easy-dialog' ),
			Helper::get_file_version( plugin_dir_path( EASY_LANGUAGE ) . '/classes/multilingual-plugins/easy-language/admin/elementor.js' ),
			true
		);
		wp_enqueue_script( 'easy-language-elementor-admin' );

		// add dialog scripts.
		Init::get_instance()->add_dialog();

		// embed simplification scripts.
		$multilingual_plugin->get_simplifications_scripts();
	}
}
add_action( 'elementor/editor/before_enqueue_styles', 'easy_language_pagebuilder_elementor_styles' );

/**
 * Add our custom tag to add language switcher.
 */
add_action(
	'elementor/dynamic_tags/register_tags',
	function ( $dynamic_tags ) {
		if ( Helper::is_plugin_active( 'elementor-pro/elementor-pro.php' ) ) {
			$dynamic_tags->register( new easyLanguage\Multilingual_plugins\Easy_Language\PageBuilder\Elementor\Switcher() );
		}
	}
);
