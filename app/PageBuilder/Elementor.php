<?php
/**
 * File to handle support for the page builder Elementor.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// deny direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Init;
use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\PageBuilder\Elementor\Languages;
use easyLanguage\PageBuilder\Elementor\Switcher;
use easyLanguage\PageBuilder\Elementor\Switcher_Widget;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\ThirdPartySupports;
use Elementor\Controls_Manager;
use Elementor\Core\DocumentTypes\Page;
use Elementor\Core\DocumentTypes\Post;
use Elementor\Core\DynamicTags\Manager;
use Elementor\Plugin;

/**
 * Object to handle support for the page builder Elementor.
 */
class Elementor extends PageBuilder_Base {
	/**
	 * Instance of this object.
	 *
	 * @var ?Elementor
	 */
	private static ?Elementor $instance = null;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Elementor {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'elementor_init' ) );
		add_action( 'elementor/element/wp-post/document_settings/after_section_end', array( $this, 'add_settings_controls' ) );
		add_action( 'elementor/element/wp-page/document_settings/after_section_end', array( $this, 'add_settings_controls_on_page' ) );
		add_action( 'elementor/controls/register', array( $this, 'add_custom_control' ) );
		add_action( 'elementor/editor/before_enqueue_styles', array( $this, 'add_styles' ) );
		add_action( 'elementor/dynamic_tags/register_tags', array( $this, 'add_dynamic_tags' ) );
	}

	/**
	 * Embed additional Elementor settings only if needed.
	 *
	 * @return void
	 */
	public function elementor_init(): void {
		// bail if Elementor is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		// register widgets.
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Embed custom Elementor widgets.
	 *
	 * @return void
	 */
	public function register_widgets(): void {
		Plugin::instance()->widgets_manager->register( new Switcher_Widget() );
	}

	/**
	 * Add Elementor control under settings to switch between languages for posts.
	 *
	 * @param Post $page The Post-object.
	 *
	 * @return void
	 */
	public function add_settings_controls( Post $page ): void {
		// get the original-object.
		$post_object = new Post_Object( $page->get_id() );

		// get the post-language.
		$language_array = $post_object->get_language();
		$language       = reset( $language_array );

		if ( ! empty( $language ) ) {
			/**
			 * Add a section.
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

	/**
	 * Add Elementor control under settings to switch between languages for pages.
	 *
	 * @param Page $page The Page-object.
	 *
	 * @return void
	 */
	public function add_settings_controls_on_page( Page $page ): void {
		// get the original-object.
		$post_object = new Post_Object( $page->get_id() );

		// get the post-language.
		$language_array = $post_object->get_language();
		$language       = reset( $language_array );

		if ( ! empty( $language ) ) {
			/**
			 * Add a section.
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

	/**
	 * Register custom controls for elementor.
	 *
	 * @param Controls_Manager $controls_manager Object of Controls Manager from Elementor.
	 *
	 * @return void
	 */
	public function add_custom_control( Controls_Manager $controls_manager ): void {
		$controls_manager->register( new Languages() );
	}

	/**
	 * Add elementor-specific backend style.
	 *
	 * @return void
	 */
	public function add_styles(): void {
		// add only scripts and styles if our own plugin is used.
		$multilingual_plugin = false;
		foreach ( ThirdPartySupports::get_instance()->get_available_plugins() as $plugin_obj ) {
			if ( false === $plugin_obj->is_foreign_plugin() ) {
				$multilingual_plugin = $plugin_obj;
			}
		}
		if ( false !== $multilingual_plugin ) {
			wp_register_style(
				'easy-language-elementor-admin',
				trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'admin/elementor.css',
				array(),
				Helper::get_file_version( plugin_dir_path( EASY_LANGUAGE ) . 'admin/elementor.css' ),
			);
			wp_enqueue_style( 'easy-language-elementor-admin' );

			// elementor-specific backend-JS.
			wp_register_script(
				'easy-language-elementor-admin',
				trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'admin/elementor.js',
				array( 'jquery', 'easy-dialog' ),
				Helper::get_file_version( plugin_dir_path( EASY_LANGUAGE ) . 'admin/elementor.js' ),
				true
			);
			wp_enqueue_script( 'easy-language-elementor-admin' );

			// add dialog scripts.
			Init::get_instance()->add_dialog();

			// embed simplification scripts.
			$multilingual_plugin->get_simplifications_scripts();
		}
	}

	/**
	 * Add our custom tag to add a language switcher.
	 *
	 * @param Manager $dynamic_tags Manager for dynamic tags.
	 * @return void
	 */
	public function add_dynamic_tags( Manager $dynamic_tags ): void {
		// bail if elementor pro is not active.
		if ( ! Helper::is_plugin_active( 'elementor-pro/elementor-pro.php' ) ) {
			return;
		}

		// register our own switcher.
		$dynamic_tags->register( new Switcher() );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return did_action( 'elementor/loaded' );
	}
}
