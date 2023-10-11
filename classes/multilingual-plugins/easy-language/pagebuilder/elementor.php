<?php
/**
 * File to add Elementor as supported page builder.
 *
 * @package easy-language
 */

use easyLanguage\Helper;
use Elementor\Controls_Manager;
use Elementor\Core\DocumentTypes\Page;
use Elementor\Core\DocumentTypes\Post;
use easyLanguage\Multilingual_Plugins;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Elementor\Languages;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Elementor;
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enable Elementor-Support only if it is loaded.
 */
add_action( 'init', function() {
	if ( did_action( 'elementor/loaded' ) ) {
		add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_elementor' );
	}
});

/**
 * Add Elementor-object to list of supported pagebuilder.
 *
 * @param array $list List of supported pagebuilder.
 *
 * @return array
 */
function easy_language_pagebuilder_elementor( array $list ): array {
	// add Elementor as PageBuilder.
	$list[] = Elementor::get_instance();

	// return list of supported page-builders.
	return $list;
}

/**
 * Add Elementor control under settings to switch between languages for posts.
 *
 * @param Post $page
 *
 * @return void
 */
function easy_language_add_elementor_page_settings_controls( Post $page ): void {
	// get the original-object.
	$post_object = new Post_Object( $page->get_id() );

	// get the post-language.
	$language_array = $post_object->get_language();
	$language = reset( $language_array );

	/**
	 * Add section.
	 */
	$page->start_controls_section('easy_language',
		array(
			'label' => esc_html__( 'Languages', 'easy-language' ),
			'tab' => Controls_Manager::TAB_SETTINGS,
		)
	);

	/**
	 * Add our custom control in this section.
	 */
	$page->add_control(
		'menu_item_color_custom',
		array(
			'label' => '',
			'label_block' => true,
			/* translators: %1$s will be replaced by the type of the object, %2$s will be replaced by the name of the language */
			'description' => sprintf(__('You are editing this %1$s in the language <strong>%2$s</strong>.', 'easy-language'), $post_object->get_type(), $language['label'] ),
			'type' => 'easy_languages'
		)
	);
	$page->end_controls_section();
}
add_action( 'elementor/element/wp-post/document_settings/after_section_end', 'easy_language_add_elementor_page_settings_controls' );

/**
 * Add Elementor control under settings to switch between languages for pages.
 *
 * @param Page $page
 *
 * @return void
 */
function easy_language_add_elementor_page_settings_controls_page( Page $page ): void {
	// get the original-object.
	$post_object = new Post_Object( $page->get_id() );

	// get the post-language.
	$language_array = $post_object->get_language();
	$language = reset( $language_array );

	/**
	 * Add section.
	 */
	$page->start_controls_section('easy_language',
		array(
			'label' => esc_html__( 'Languages', 'easy-language' ),
			'tab' => Controls_Manager::TAB_SETTINGS,
		)
	);

	/**
	 * Add our custom control in this section.
	 */
	$page->add_control(
		'menu_item_color_custom',
		array(
			'label' => '',
			'label_block' => true,
			/* translators: %1$s will be replaced by the type of the object, %2$s will be replaced by the name of the language */
			'description' => sprintf(__('You are editing this %1$s in the language <strong>%2$s</strong>.', 'easy-language'), $post_object->get_type(), $language['label'] ),
			'type' => 'easy_languages'
		)
	);
	$page->end_controls_section();
}
add_action( 'elementor/element/wp-page/document_settings/after_section_end', 'easy_language_add_elementor_page_settings_controls_page' );

/**
 * Register custom controls for elementor.
 *
 * @param $controls_manager
 *
 * @return void
 */
function easy_language_pagebuilder_elementor_add_custom_control( $controls_manager ): void {
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
	foreach( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
		if ( false === $plugin_obj->is_foreign_plugin() ) {
			$multilingual_plugin = $plugin_obj;
		}
	}
	if( false !== $multilingual_plugin ) {
		wp_register_style( 'easy-language-elementor-admin',
			trailingslashit(plugin_dir_url( EASY_LANGUAGE )). 'classes/multilingual-plugins/easy-language/admin/elementor.css',
			array(),
			filemtime( plugin_dir_path( EASY_LANGUAGE ) . '/classes/multilingual-plugins/easy-language/admin/elementor.css' ),
		);
		wp_enqueue_style( 'easy-language-elementor-admin' );

		// elementor-specific backend-JS.
		wp_register_script(
			'easy-language-elementor-admin',
			trailingslashit(plugin_dir_url( EASY_LANGUAGE )). 'classes/multilingual-plugins/easy-language/admin/elementor.js',
			array( 'jquery' ),
			filemtime( plugin_dir_path( EASY_LANGUAGE ) . '/classes/multilingual-plugins/easy-language/admin/elementor.js' ),
			true
		);
		wp_enqueue_script( 'easy-language-elementor-admin' );

		// embed translation scripts.
		$multilingual_plugin->get_translations_script();
	}
}
add_action( 'elementor/editor/before_enqueue_styles', 'easy_language_pagebuilder_elementor_styles' );

/**
 * Add our custom tag to add language switcher.
 */
add_action('elementor/dynamic_tags/register_tags', function ($dynamic_tags) {
	if( Helper::is_plugin_active( 'elementor-pro/elementor-pro.php' ) ) {
		$dynamic_tags->register( new easyLanguage\Multilingual_plugins\Easy_Language\Parser\Elementor\Switcher );
	}
});
