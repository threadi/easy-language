<?php
/**
 * File for the switcher-handling.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Languages;
use WP;
use WP_Block;
use WP_Block_Supports;
use WP_HTML_Tag_Processor;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Server;
use WP_Term;

/**
 * Object which handles the language switcher.
 *
 * All outputs of the switcher (blocks, classic menus, shortcode, page builder widgets) use the same
 * central functions:
 * - get_entries() collects the data (languages, URLs, states) for the actual request.
 * - get() returns the HTML-output for these entries.
 *
 * Each output only has to provide its settings as array. See normalize_settings() for the possible keys.
 */
class Switcher {

	/**
	 * Display mode: show the language names.
	 */
	public const DISPLAY_TEXT = 'text';

	/**
	 * Display mode: show the language icons.
	 */
	public const DISPLAY_ICONS = 'icons';

	/**
	 * Display mode: show a toggle between original and one simplified language.
	 */
	public const DISPLAY_TOGGLE = 'toggle';

	/**
	 * Name of the post meta for the settings of a switcher in classic menus.
	 */
	private const MENU_SETTINGS_META = 'easy_language_switcher_settings';

	/**
	 * Instance of this object.
	 *
	 * @var ?Switcher
	 */
	private static ?Switcher $instance = null;

	/**
	 * The init-object.
	 *
	 * @var Init
	 */
	private Init $init;

	/**
	 * Cache for icons per request.
	 *
	 * @var array<string,string>
	 */
	private array $icons = array();

	/**
	 * Cache for URLs of simplifications per request.
	 *
	 * @var array<string,string>
	 */
	private array $simplification_urls = array();

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
	public static function get_instance(): Switcher {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @param Init $init The init-object.
	 *
	 * @return void
	 */
	public function init( Init $init ): void {
		$this->init = $init;

		// Hooks.
		add_action( 'init', array( $this, 'register_styles' ), 5 );
		add_action( 'init', array( $this, 'wp_init' ) );
		add_action( 'init', array( $this, 'set_default' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'add_editor_data' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'wp_get_nav_menu_items', array( $this, 'set_menu_items' ) );
		add_filter( 'nav_menu_link_attributes', array( $this, 'set_menu_link_attributes' ), 10, 2 );
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'add_menu_options' ), 10, 2 );
		add_action( 'wp_update_nav_menu_item', array( $this, 'save_menu_options' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_menu_scripts' ) );
		add_shortcode( 'easy-language-switcher', array( $this, 'use_shortcode' ) );
		add_filter( 'safecss_filter_attr_allow_css', array( $this, 'allow_toggle_css' ), 10, 2 );
	}

	/**
	 * Run this on every request.
	 *
	 * @return void
	 */
	public function wp_init(): void {
		// bail if our cpt is already registered.
		if ( post_type_exists( EASY_LANGUAGE_CPT_SWITCHER ) ) {
			return;
		}

		/**
		 * Register our own language-switcher as post-type for classic themes.
		 */
		$args = array(
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_nav_menus'   => true,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'can_export'          => false,
			'public'              => false,
			'label'               => __( 'Language Switcher', 'easy-language' ),
		);
		register_post_type( EASY_LANGUAGE_CPT_SWITCHER, $args );

		/**
		 * Register our own language-switcher als Blocks for FSE/Block Editor.
		 */
		if ( function_exists( 'register_block_type' ) ) {
			// register our own switcher block for navigation in FSE-themes.
			// hint: https://github.com/WordPress/gutenberg/issues/31387 .
			register_block_type(
				Helper::get_plugin_path() . 'blocks/navigation-switcher/',
				array(
					'render_callback' => array( $this, 'render_block' ),
					'attributes'      => $this->get_block_attributes(),
					'parent'          => array( 'core/navigation' ),
				)
			);

			// register our own switcher block outside of navigation in FSE-themes.
			register_block_type(
				Helper::get_plugin_path() . 'blocks/switcher/',
				array(
					'render_callback' => array( $this, 'render_block' ),
					'attributes'      => $this->get_block_attributes(),
				)
			);
		}
	}

	/**
	 * Register the styles for the switcher.
	 *
	 * They are used in frontend for all outputs and as dependency of our blocks (also in the editor).
	 *
	 * @return void
	 */
	public function register_styles(): void {
		// the icon of the widgets in page builders.
		wp_register_style(
			'easy-language-builder-icon',
			Helper::get_plugin_url() . 'css/builder-icon.css',
			array(),
			Helper::get_file_version( Helper::get_plugin_path() . 'css/builder-icon.css' )
		);

		wp_register_style(
			'easy-language-switcher',
			Helper::get_plugin_url() . 'css/switcher.css',
			array(),
			Helper::get_file_version( Helper::get_plugin_path() . 'css/switcher.css' )
		);
	}

	/**
	 * Load the styles for the switcher in frontend.
	 *
	 * They are loaded on every page as the switcher could be used in classic menus, shortcodes or widgets.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		wp_enqueue_style( 'easy-language-switcher' );
	}

	/**
	 * Return the attributes of our switcher blocks.
	 *
	 * This is the single source of the attributes for both blocks.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_block_attributes(): array {
		return array(
			'preview'              => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'show_icons'           => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'hide_actual_language' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'display'              => array(
				'type'    => 'string',
				'default' => '',
			),
			'icon_size'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'toggle_language'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'toggle_disabled'      => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'toggle_color_on'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'toggle_color_off'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'toggle_color_thumb'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'toggle_size'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'toggle_radius'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'visibility'           => array(
				'type'    => 'string',
				'default' => 'all',
			),
			'visibility_objects'   => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array(
					'type' => 'string',
				),
			),
			'visibility_children'  => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'visibility_assigned'  => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'fallback'             => array(
				'type'    => 'string',
				'default' => 'auto',
			),
			// version of the output: 1 = output before 4.0.0, 2 = actual output. New blocks get 2 via block variation.
			'markup_version'       => array(
				'type'    => 'number',
				'default' => 1,
			),
		);
	}

	/**
	 * One-time function to create a default entry.
	 *
	 * @return void
	 */
	public function set_default(): void {
		// bail if the work has already been done.
		if ( 1 === absint( get_option( 'easy_language_switcher_default', 0 ) ) ) {
			return;
		}

		// add the single entry for language-switcher to use in classic menus.
		$query = array(
			'post_title'   => __( 'Language Switcher', 'easy-language' ),
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => EASY_LANGUAGE_CPT_SWITCHER,
		);
		$post_id = wp_insert_post( $query );

		// bail if the entry could not be created (try it again on the next request).
		if ( ! is_int( $post_id ) || $post_id <= 0 ) {
			return;
		}

		// mark that we have done the work.
		update_option( 'easy_language_switcher_default', 1, true );
	}

	/**
	 * Render callback for our blocks.
	 *
	 * @param array<string,mixed> $attributes The block attributes.
	 * @param string              $content The block content (unused as our blocks are dynamic).
	 * @param WP_Block|null       $block The block object.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function render_block( array $attributes, string $content = '', ?WP_Block $block = null ): string {
		// remove internal settings which must not be set via block attributes.
		$attributes = array_intersect_key( $attributes, $this->get_block_attributes() );

		// the preview attribute is only used for the example in the block inserter.
		if ( ! $this->is_editor_request() ) {
			$attributes['preview'] = false;
		}

		// get the version of the output.
		$markup_version = absint( $attributes['markup_version'] ?? 1 );

		// blocks with old markup are using the old output.
		$attributes['legacy'] = $markup_version < 2;

		// blocks with new markup are using a wrapper with the block supports (colors, typography, …).
		if ( $markup_version >= 2 ) {
			$is_navigation                = $block instanceof WP_Block && 'easy-language/navigation-switcher' === $block->name;
			$attributes['wrapper']        = $is_navigation ? 'navigation' : 'block';
			$attributes['wrapper_class']  = $is_navigation ? 'wp-block-navigation-item easy-language-switcher' : 'easy-language-switcher';
			$attributes['wrapper_source'] = 'block';
		}

		// set the source.
		$attributes['source'] = $block instanceof WP_Block ? $block->name : 'block';

		// return the output.
		return $this->get( $attributes );
	}

	/**
	 * Return the switcher output for the actual request.
	 *
	 * This is the central function for every output of the switcher (blocks, shortcode, page builder).
	 * Only the classic menu uses get_entries() directly, as it needs menu items instead of HTML.
	 *
	 * @param array<string,mixed> $attributes The settings as array, see normalize_settings() for possible keys.
	 *
	 * @return string
	 */
	public function get( array $attributes ): string {
		// get the normalized settings.
		$settings = $this->normalize_settings( $attributes );

		// get the entries.
		$result = $this->get_entries( $settings );

		// bail if no entries are available.
		if ( empty( $result['entries'] ) ) {
			return '';
		}

		// create the output depending on the display mode.
		if ( self::DISPLAY_TOGGLE === $settings['display'] ) {
			$html = $this->get_toggle_html( $result, $settings );
		} else {
			$html = $this->get_links_html( $result, $settings );
		}

		// bail if no output has been generated.
		if ( empty( $html ) ) {
			return '';
		}

		// add the wrapper.
		$html = $this->add_wrapper( $html, $settings );

		/**
		 * Filter the output of the language switcher.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param string $html The HTML-output.
		 * @param array $settings The normalized settings.
		 * @param array $result The entries of the switcher.
		 */
		return apply_filters( 'easy_language_switcher_output', $html, $settings, $result );
	}

	/**
	 * Normalize the given settings for the switcher.
	 *
	 * Possible keys:
	 * - display: "text", "icons" or "toggle" (if empty, "show_icons" decides between text and icons)
	 * - show_icons: true to show icons (setting before 4.0.0)
	 * - hide_actual_language: true to hide the language of the actual page
	 * - icon_size: the size of the icons as CSS-value (e.g. "32px"), empty for the default size
	 * - toggle_language: the language which is used by the toggle (empty for the first active language)
	 * - toggle_disabled: true to show a greyed toggle if no simplification is available
	 * - toggle_color_on, toggle_color_off, toggle_color_thumb: colors of the toggle (CSS colors, empty for the default)
	 * - toggle_size: height of the toggle as CSS-value (the width is calculated), empty for the default
	 * - toggle_radius: rounding of the toggle as CSS-value (e.g. "0" for squared), empty for the default
	 * - visibility: "all", "include" (only on the chosen objects) or "exclude" (not on the chosen objects)
	 * - visibility_objects: list of objects as "post:ID" or "term:ID"
	 * - visibility_children: true to include the children of the chosen objects
	 * - visibility_assigned: true to include the objects which are assigned to the chosen terms
	 * - fallback: "auto" (like before 4.0.0), "home" (link the homepages) or "hide" on pages without own language versions
	 * - legacy: true to use the output before 4.0.0 (link of not simplified pages ignore the link-mode)
	 * - legacy_markup: true to use the HTML before 4.0.0 (without additional ARIA-attributes), default is "legacy"
	 * - wrapper: "" for no wrapper, "block" for a div or "navigation" for a li-element as wrapper
	 * - wrapper_class: classes for the wrapper
	 * - preview: true if the switcher is shown in an editor
	 * - source: name of the output which uses the switcher (e.g. "shortcode")
	 *
	 * @param array<string,mixed> $attributes The given settings.
	 *
	 * @return array<string,mixed>
	 */
	public function normalize_settings( array $attributes ): array {
		// bail if the settings are already normalized.
		if ( ! empty( $attributes['normalized'] ) ) {
			return $attributes;
		}

		// get the display mode.
		$display = isset( $attributes['display'] ) && is_string( $attributes['display'] ) ? $attributes['display'] : '';
		if ( ! in_array( $display, array( self::DISPLAY_TEXT, self::DISPLAY_ICONS, self::DISPLAY_TOGGLE ), true ) ) {
			$display = $this->is_true( $attributes['show_icons'] ?? false ) ? self::DISPLAY_ICONS : self::DISPLAY_TEXT;
		}

		// get the visibility.
		$visibility = isset( $attributes['visibility'] ) && in_array( $attributes['visibility'], array( 'all', 'include', 'exclude' ), true ) ? $attributes['visibility'] : 'all';

		// get the fallback.
		$fallback = isset( $attributes['fallback'] ) && in_array( $attributes['fallback'], array( 'auto', 'home', 'hide' ), true ) ? $attributes['fallback'] : 'auto';

		// get the wrapper.
		$wrapper = isset( $attributes['wrapper'] ) && in_array( $attributes['wrapper'], array( 'block', 'navigation' ), true ) ? $attributes['wrapper'] : '';

		// get the icon size: from the switcher itself or the global default.
		$icon_size = $this->sanitize_size( $attributes['icon_size'] ?? '' );
		if ( empty( $icon_size ) ) {
			$icon_size = $this->sanitize_size( absint( get_option( 'easy_language_switcher_icon_size', 0 ) ) );
		}

		// get the legacy markers: only used if the compatibility mode is enabled.
		$compatibility = 1 === absint( get_option( 'easy_language_switcher_compatibility', 0 ) );
		$legacy        = $compatibility && ( ! isset( $attributes['legacy'] ) || $this->is_true( $attributes['legacy'] ) );
		$legacy_markup = $compatibility && ( isset( $attributes['legacy_markup'] ) ? $this->is_true( $attributes['legacy_markup'] ) : $legacy );

		// collect the settings.
		$settings = array(
			'normalized'           => true,
			'display'              => $display,
			'hide_actual_language' => $this->is_true( $attributes['hide_actual_language'] ?? false ),
			'icon_size'            => $icon_size,
			'toggle_language'      => isset( $attributes['toggle_language'] ) && is_string( $attributes['toggle_language'] ) ? $attributes['toggle_language'] : '',
			'toggle_disabled'      => $this->is_true( $attributes['toggle_disabled'] ?? false ),
			'toggle_color_on'      => $this->sanitize_color( $attributes['toggle_color_on'] ?? '' ),
			'toggle_color_off'     => $this->sanitize_color( $attributes['toggle_color_off'] ?? '' ),
			'toggle_color_thumb'   => $this->sanitize_color( $attributes['toggle_color_thumb'] ?? '' ),
			'toggle_size'          => $this->sanitize_size( $attributes['toggle_size'] ?? '' ),
			'toggle_radius'        => $this->sanitize_size( $attributes['toggle_radius'] ?? '', true ),
			'visibility'           => $visibility,
			'visibility_objects'   => $this->sanitize_objects( $attributes['visibility_objects'] ?? array() ),
			'visibility_children'  => $this->is_true( $attributes['visibility_children'] ?? false ),
			'visibility_assigned'  => $this->is_true( $attributes['visibility_assigned'] ?? false ),
			'fallback'             => $fallback,
			'legacy'               => $legacy,
			'legacy_markup'        => $legacy_markup,
			'wrapper'              => $wrapper,
			'wrapper_class'        => isset( $attributes['wrapper_class'] ) && is_string( $attributes['wrapper_class'] ) ? $attributes['wrapper_class'] : '',
			'wrapper_source'       => isset( $attributes['wrapper_source'] ) && is_string( $attributes['wrapper_source'] ) ? $attributes['wrapper_source'] : '',
			'preview'              => $this->is_true( $attributes['preview'] ?? false ) || $this->is_editor_request(),
			'source'               => isset( $attributes['source'] ) && is_string( $attributes['source'] ) ? $attributes['source'] : '',
		);

		/**
		 * Filter the settings of a language switcher.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param array $settings The normalized settings.
		 * @param array $attributes The original attributes.
		 */
		return apply_filters( 'easy_language_switcher_settings', $settings, $attributes );
	}

	/**
	 * Return the entries of the switcher for the actual request.
	 *
	 * The result contains:
	 * - entries: list of languages, each with code, label, url, available, active and original.
	 * - active: the language code of the actual page.
	 * - original: the language code of the original object.
	 * - object: the original object (Objects) or false if there is none (e.g. on the blog homepage).
	 *
	 * The list is empty if the switcher should not be visible.
	 *
	 * @param array<string,mixed> $attributes The settings, see normalize_settings().
	 *
	 * @return array<string,mixed>
	 */
	public function get_entries( array $attributes ): array {
		// get the normalized settings.
		$settings = $this->normalize_settings( $attributes );

		// get the context of the actual request.
		$context = $this->get_context();

		// bail if the switcher should not be visible on this request (not in editors to show the switcher there).
		if ( ! $settings['preview'] && ! $this->is_visible( $settings, $context ) ) {
			return array();
		}

		// handle requests without supported object (archives, search, 404, blog homepage …).
		if ( false === $context['original'] ) {
			// get the fallback.
			$fallback = $settings['fallback'];
			if ( $settings['preview'] ) {
				// always show links to the homepages in editors.
				$fallback = 'home';
			}

			// bail if nothing should be shown.
			if ( 'hide' === $fallback ) {
				return array();
			}

			// "auto": show the homepage-links only on requests without any object (like before 4.0.0).
			if ( 'auto' === $fallback && 'empty' !== $context['type'] ) {
				return array();
			}

			// use the static front page as object.
			$context = $this->get_front_page_context();

			// "auto": without supported static front page, show the homepage-links only on the blog homepage itself.
			if ( 'auto' === $fallback && false === $context['original'] && ( 'posts' !== get_option( 'show_on_front' ) || ! is_front_page() ) ) {
				return array();
			}
		}

		// get the result.
		if ( false === $context['original'] ) {
			$result = $this->get_home_entries();
		} else {
			$result = $this->get_object_entries( $context );
		}

		// bail if no entries could be found.
		if ( empty( $result['entries'] ) ) {
			return array();
		}

		// filter the entries depending on the settings.
		$result['entries'] = $this->filter_entries( $result['entries'], $settings );

		/**
		 * Filter the entries of the language switcher.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param array $result The entries with additional data.
		 * @param array $settings The normalized settings.
		 */
		return apply_filters( 'easy_language_switcher_entries', $result, $settings );
	}

	/**
	 * Return the context of the actual request.
	 *
	 * Types:
	 * - "object": a supported object (post or term) is requested.
	 * - "empty": nothing is requested (blog homepage, search, 404, date archive).
	 * - "unsupported": an object is requested, which is not supported (e.g. author archive).
	 *
	 * @return array<string,mixed>
	 */
	private function get_context(): array {
		// get the requested object.
		$wp_object = get_queried_object();
		$object_id = get_queried_object_id();

		// set the default context.
		$context = array(
			'type'     => 'unsupported',
			'object'   => false,
			'original' => false,
		);

		// bail if nothing is requested.
		if ( 0 === $object_id || is_null( $wp_object ) ) {
			$context['type'] = 'empty';
			return $context;
		}

		// get our own object for the requested object.
		$object = $this->init->get_object_by_wp_object( $wp_object, $object_id );

		// bail if this is not a supported object.
		if ( ! $object instanceof Objects ) {
			return $context;
		}

		// get the original object.
		$original = $this->get_original_object( $object );

		// bail if no supported original object could be loaded.
		if ( ! $original instanceof Objects || ! $this->is_object_supported( $original ) ) {
			return $context;
		}

		// return the resulting context.
		return array(
			'type'     => 'object',
			'object'   => $object,
			'original' => $original,
		);
	}

	/**
	 * Return the context for the front page.
	 *
	 * @return array<string,mixed>
	 */
	private function get_front_page_context(): array {
		// set the default context.
		$context = array(
			'type'     => 'front',
			'object'   => false,
			'original' => false,
		);

		// bail if the front page is not a static page.
		$front_page_id = absint( get_option( 'page_on_front', 0 ) );
		if ( 0 === $front_page_id || 'page' !== get_option( 'show_on_front' ) ) {
			return $context;
		}

		// get the front page as object.
		$object = $this->init->get_object_by_wp_object( get_post( $front_page_id ), $front_page_id );

		// bail if it could not be loaded or is not supported.
		if ( ! $object instanceof Objects || ! $this->is_object_supported( $object ) ) {
			return $context;
		}

		// return the resulting context.
		$context['object']   = $object;
		$context['original'] = $object;
		return $context;
	}

	/**
	 * Return the original object of the given object.
	 *
	 * @param Objects $given_object The object.
	 *
	 * @return Objects|false
	 */
	private function get_original_object( Objects $given_object ): Objects|false {
		// return the object itself, if it is not simplified.
		if ( ! $given_object->is_simplified() ) {
			return $given_object;
		}

		// get the original object.
		$object_id = $given_object->get_original_object_as_int();
		return $this->init->get_object_by_wp_object( $this->get_wp_object( $given_object, $object_id ), $object_id );
	}

	/**
	 * Return whether the given object is supported by the plugin settings.
	 *
	 * @param Objects $given_object The object.
	 *
	 * @return bool
	 */
	private function is_object_supported( Objects $given_object ): bool {
		if ( $given_object instanceof Term_Object ) {
			return $this->init->is_taxonomy_supported( $given_object->get_type() );
		}
		return $this->init->is_post_type_supported( $given_object->get_type() );
	}

	/**
	 * Return the entries for an object.
	 *
	 * @param array<string,mixed> $context The context with the requested and the original object.
	 *
	 * @return array<string,mixed>
	 */
	private function get_object_entries( array $context ): array {
		// get the objects.
		$object   = $context['object'];
		$original = $context['original'];

		// bail if objects are not valid.
		if ( ! $object instanceof Objects || ! $original instanceof Objects ) {
			return array();
		}

		// get the languages: the original language first, then all active languages.
		$original_language = $original->get_language();
		$languages         = array_merge( $original_language, Languages::get_instance()->get_active_languages() );

		// bail if no languages are available.
		if ( empty( $languages ) ) {
			return array();
		}

		// get the language code of the original.
		$original_language_code = (string) array_key_first( $original_language );

		// get the language of the requested object.
		$active_language_code = (string) array_key_first( $object->get_language() );
		if ( ! $object->is_simplified() && is_front_page() ) {
			// consider the language from the URL on the language-specific front page.
			$language_by_url = $this->get_language_by_query_var();
			if ( ! empty( $language_by_url ) ) {
				$active_language_code = $language_by_url;
			}
		}

		// collect the entries.
		$entries = array();
		foreach ( $languages as $language_code => $settings ) {
			$language_code = (string) $language_code;

			// define the entry.
			$entry = array(
				'code'      => $language_code,
				'label'     => isset( $settings['label'] ) ? (string) $settings['label'] : $language_code,
				'available' => true,
				'legacy'    => false,
				'active'    => 0 === strcasecmp( $active_language_code, $language_code ),
				'original'  => $language_code === $original_language_code,
				'settings'  => $settings,
			);

			// get the URL.
			if ( $entry['original'] ) {
				$entry['url'] = $this->get_object_url( $original );
			} else {
				$target             = $this->get_target_url( $original, $language_code );
				$entry['url']       = $target['url'];
				$entry['available'] = $target['available'];
				$entry['legacy']    = $target['legacy'];
			}

			// bail if no URL could be found.
			if ( empty( $entry['url'] ) && $entry['available'] ) {
				continue;
			}

			// add the entry.
			$entries[ $language_code ] = $entry;
		}

		// return the result.
		return array(
			'entries'  => $entries,
			'active'   => $active_language_code,
			'original' => $original_language_code,
			'object'   => $original,
		);
	}

	/**
	 * Return the entries for the homepage if it is not a static page (e.g. the blog homepage).
	 *
	 * The homepage has no simplifications, so the simplified languages are handled like not simplified pages.
	 *
	 * @return array<string,mixed>
	 */
	private function get_home_entries(): array {
		// get the source language of this website.
		$source_language_code = Helper::get_wp_lang();
		$source_languages     = Languages::get_instance()->get_possible_source_languages();

		// collect the languages.
		$languages = array();
		if ( ! empty( $source_languages[ $source_language_code ] ) ) {
			$languages[ $source_language_code ] = $source_languages[ $source_language_code ];
		}
		$languages = array_merge( $languages, Languages::get_instance()->get_active_languages() );

		// bail if no languages are available.
		if ( empty( $languages ) ) {
			return array();
		}

		// get the actual language.
		$active_language_code = $this->get_language_by_query_var();
		if ( empty( $active_language_code ) ) {
			$active_language_code = $source_language_code;
		}

		// get the link-mode.
		$link_mode = (string) get_option( 'easy_language_switcher_link', 'hide_not_translated' );

		// collect the entries.
		$entries = array();
		foreach ( $languages as $language_code => $settings ) {
			$language_code = (string) $language_code;

			// define the entry: there is no simplified homepage, so the simplified languages link to the homepage.
			$is_original = $language_code === $source_language_code;
			$available   = $is_original || 'hide_not_translated' !== $link_mode;

			// add the entry.
			$entries[ $language_code ] = array(
				'code'      => $language_code,
				'label'     => isset( $settings['label'] ) ? (string) $settings['label'] : $language_code,
				'url'       => get_home_url(),
				'available' => $available,
				'legacy'    => ! $available,
				'active'    => 0 === strcasecmp( $active_language_code, $language_code ),
				'original'  => $is_original,
				'settings'  => $settings,
			);
		}

		// return the result.
		return array(
			'entries'  => $entries,
			'active'   => $active_language_code,
			'original' => $source_language_code,
			'object'   => false,
		);
	}

	/**
	 * Return the URL of the given object in the given language depending on the link-mode.
	 *
	 * Link-modes:
	 * - do_not_link: link to the language-specific homepage.
	 * - link_translated: link to the simplification, if it is not available, to the language-specific homepage.
	 * - hide_not_translated: link to the simplification, if it is not available, mark the entry as not available.
	 *
	 * @param Objects $original The original object.
	 * @param string  $language_code The language code.
	 *
	 * @return array{url: string, available: bool, legacy: bool}
	 */
	private function get_target_url( Objects $original, string $language_code ): array {
		// get the link-mode.
		$link_mode = (string) get_option( 'easy_language_switcher_link', 'hide_not_translated' );

		// use the URL-logic of the object itself for objects of other plugins.
		if ( ! $original instanceof Post_Object && ! $original instanceof Term_Object ) {
			$languages = Languages::get_instance()->get_active_languages();
			$url       = $original->get_language_specific_url( empty( $languages[ $language_code ]['url'] ) ? $this->get_object_url( $original ) : $languages[ $language_code ]['url'], $language_code );
			return array(
				'url'       => $url,
				'available' => ! empty( $url ),
				'legacy'    => false,
			);
		}

		// get the public URL of the simplification in this language.
		$simplification_url = $this->get_simplification_url( $original, $language_code );

		// link to the simplification.
		if ( ! empty( $simplification_url ) && 'do_not_link' !== $link_mode ) {
			return array(
				'url'       => $simplification_url,
				'available' => true,
				'legacy'    => false,
			);
		}

		// mark as not available if no simplification exists and not available simplification should not be linked.
		if ( empty( $simplification_url ) && 'hide_not_translated' === $link_mode ) {
			return array(
				// this URL is only used by the old output.
				'url'       => get_home_url(),
				'available' => false,
				'legacy'    => true,
			);
		}

		// link to the homepage if the page is not simplified (like before 4.0.0).
		if ( empty( $simplification_url ) && 'do_not_link' !== $link_mode ) {
			return array(
				'url'       => get_home_url(),
				'available' => true,
				'legacy'    => false,
			);
		}

		// link to the language-specific homepage in all other cases ("do_not_link").
		return array(
			'url'       => $this->get_language_home_url( $language_code ),
			'available' => true,
			'legacy'    => false,
		);
	}

	/**
	 * Return the public URL of the simplification of the given object in the given language.
	 *
	 * Returns an empty string if no simplification exists or if it is not public (e.g. a draft).
	 *
	 * @param Objects $original The original object.
	 * @param string  $language_code The language code.
	 *
	 * @return string
	 */
	private function get_simplification_url( Objects $original, string $language_code ): string {
		// use the cache.
		$cache_key = get_class( $original ) . ':' . $original->get_id() . ':' . $language_code;
		if ( ! isset( $this->simplification_urls[ $cache_key ] ) ) {
			$this->simplification_urls[ $cache_key ] = $this->load_simplification_url( $original, $language_code );
		}
		return $this->simplification_urls[ $cache_key ];
	}

	/**
	 * Load the public URL of the simplification of the given object in the given language.
	 *
	 * @param Objects $original The original object.
	 * @param string  $language_code The language code.
	 *
	 * @return string
	 */
	private function load_simplification_url( Objects $original, string $language_code ): string {
		// get the ID of the simplification.
		$simplification_id = $original->get_simplification_in_language( $language_code );

		// bail if no simplification exists.
		if ( 0 === $simplification_id ) {
			return '';
		}

		// for terms: return the term link.
		if ( $original instanceof Term_Object ) {
			$url = get_term_link( $simplification_id, $original->get_type() );
			return is_string( $url ) ? $url : '';
		}

		// for posts: bail if the post is not public.
		if ( ! is_post_publicly_viewable( $simplification_id ) ) {
			return '';
		}

		// return the permalink.
		$url = get_permalink( $simplification_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Return the URL of the given object.
	 *
	 * @param Objects $given_object The object.
	 *
	 * @return string
	 */
	private function get_object_url( Objects $given_object ): string {
		if ( $given_object instanceof Term_Object ) {
			$url = get_term_link( $given_object->get_id(), $given_object->get_type() );
			return is_string( $url ) ? $url : '';
		}
		$url = get_permalink( $given_object->get_id() );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Return the language-specific homepage for the given language.
	 *
	 * This is the simplification of the static front page or the homepage of the website.
	 *
	 * @param string $language_code The language code.
	 *
	 * @return string
	 */
	private function get_language_home_url( string $language_code ): string {
		$front_page_id = absint( get_option( 'page_on_front', 0 ) );
		if ( $front_page_id > 0 && 'page' === get_option( 'show_on_front' ) ) {
			$url = $this->get_simplification_url( new Post_Object( $front_page_id ), $language_code );
			if ( ! empty( $url ) ) {
				return $url;
			}
		}
		return get_home_url();
	}

	/**
	 * Return the language code which is requested via the URL (our "lang" query var).
	 *
	 * @return string
	 */
	private function get_language_by_query_var(): string {
		// get the query var.
		$lang = get_query_var( 'lang' );

		// bail if it is not set.
		if ( empty( $lang ) || ! is_string( $lang ) ) {
			return '';
		}

		// search the language with this URL-part.
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			if ( ( ! empty( $settings['url'] ) && 0 === strcasecmp( $settings['url'], $lang ) ) || 0 === strcasecmp( (string) $language_code, $lang ) ) {
				return (string) $language_code;
			}
		}

		// return empty string if no language has been found.
		return '';
	}

	/**
	 * Filter the entries depending on the settings.
	 *
	 * @param array<string,array<string,mixed>> $entries The entries.
	 * @param array<string,mixed>               $settings The normalized settings.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function filter_entries( array $entries, array $settings ): array {
		// the toggle needs all entries.
		if ( self::DISPLAY_TOGGLE === $settings['display'] ) {
			return $entries;
		}

		foreach ( $entries as $language_code => $entry ) {
			// remove not available entries (the old output show them with a link to the homepage).
			if ( ! $entry['available'] ) {
				if ( $settings['legacy'] && $entry['legacy'] ) {
					$entries[ $language_code ]['available'] = true;
				} else {
					unset( $entries[ $language_code ] );
					continue;
				}
			}

			// remove the actual language if requested.
			if ( $settings['hide_actual_language'] && $entry['active'] ) {
				unset( $entries[ $language_code ] );
				continue;
			}

			// remove entries without icon if icons should be shown.
			if ( self::DISPLAY_ICONS === $settings['display'] && empty( trim( $this->get_icon( $language_code ) ) ) ) {
				unset( $entries[ $language_code ] );
			}
		}

		// return the resulting list.
		return $entries;
	}

	/**
	 * Return the HTML for the entries as links (text or icons).
	 *
	 * @param array<string,mixed> $result The result of get_entries().
	 * @param array<string,mixed> $settings The normalized settings.
	 *
	 * @return string
	 */
	private function get_links_html( array $result, array $settings ): string {
		// variable to collect the output.
		$html = '';

		// loop through the entries.
		foreach ( $result['entries'] as $language_code => $entry ) {
			// define the title-attribute.
			/* translators: %1$s will be replaced by the name of the language */
			$attribute_title = sprintf( __( 'Show this site in %1$s.', 'easy-language' ), $entry['label'] );

			// define class and content.
			$class   = 'easy-language-switcher-title';
			$content = $entry['label'];
			if ( self::DISPLAY_ICONS === $settings['display'] ) {
				$class   = 'easy-language-switcher-icon';
				$content = $this->get_icon_html( $language_code, $settings['icon_size'] );
			}

			// add active marker.
			if ( $entry['active'] ) {
				$class .= ' easy-language-switcher-active';
			}

			/**
			 * Filter the classes for single switcher entry.
			 *
			 * @since 2.9.1 Available since 2.9.1.
			 * @param string $class The classes.
			 * @param string $language_code The language code.
			 * @param array $settings The language settings.
			 */
			$class = apply_filters( 'easy_language_switcher_entry_classes', $class, $language_code, $entry['settings'] );

			// add the class for navigation items.
			if ( 'navigation' === $settings['wrapper'] ) {
				$class .= ' wp-block-navigation-item__content';
			}

			// collect the additional attributes (not for switchers from before 4.0.0 to keep their output).
			$attributes = '';
			if ( $entry['active'] && ! $settings['legacy_markup'] ) {
				$attributes .= ' aria-current="page"';
			}
			if ( self::DISPLAY_ICONS === $settings['display'] && ! $settings['legacy_markup'] ) {
				$attributes .= ' aria-label="' . esc_attr( $entry['label'] ) . '"';
			}

			// add item to the output.
			$html .= '<a href="' . esc_url( $entry['url'] ) . '" class="' . esc_attr( $class ) . '" title="' . esc_attr( $attribute_title ) . '"' . $attributes . '>' . wp_kses_post( $content ) . '</a>';
		}

		// return the resulting links.
		return $html;
	}

	/**
	 * Return the HTML for the toggle.
	 *
	 * The toggle switches between the original and one simplified language.
	 *
	 * @param array<string,mixed> $result The result of get_entries().
	 * @param array<string,mixed> $settings The normalized settings.
	 *
	 * @return string
	 */
	private function get_toggle_html( array $result, array $settings ): string {
		// get the toggle data.
		$toggle = $this->get_toggle_data( $result, $settings );

		// bail if no toggle could be generated.
		if ( empty( $toggle ) ) {
			return '';
		}

		// define the classes.
		$class = 'easy-language-switcher-toggle';
		if ( $toggle['checked'] ) {
			$class .= ' is-checked';
		}
		if ( ! $toggle['available'] ) {
			$class .= ' is-disabled';
		}
		if ( 'navigation' === $settings['wrapper'] ) {
			$class .= ' wp-block-navigation-item__content';
		}

		// define the inner HTML.
		$inner = $this->get_toggle_inner_html( $toggle );

		// define the individual design.
		$style = $this->get_toggle_style( $settings );
		$style = empty( $style ) ? '' : ' style="' . esc_attr( $style ) . '"';

		// output a not clickable toggle if the target is not available.
		if ( ! $toggle['available'] ) {
			return '<span class="' . esc_attr( $class ) . '" title="' . esc_attr( $toggle['title'] ) . '"' . $style . '>' . $inner . '</span>';
		}

		// return the toggle as link.
		return '<a href="' . esc_url( $toggle['url'] ) . '" class="' . esc_attr( $class ) . '" title="' . esc_attr( $toggle['title'] ) . '"' . $style . '>' . $inner . '</a>';
	}

	/**
	 * Return the inline style with the individual design of the toggle as CSS variables.
	 *
	 * @param array<string,mixed> $settings The normalized settings.
	 *
	 * @return string
	 */
	private function get_toggle_style( array $settings ): string {
		$variables = array(
			'--easy-language-toggle-on'     => $settings['toggle_color_on'],
			'--easy-language-toggle-off'    => $settings['toggle_color_off'],
			'--easy-language-toggle-thumb'  => $settings['toggle_color_thumb'],
			'--easy-language-toggle-size'   => $settings['toggle_size'],
			'--easy-language-toggle-radius' => $settings['toggle_radius'],
		);
		$style     = '';
		foreach ( array_filter( $variables, fn( $value ) => '' !== $value ) as $name => $value ) {
			$style .= $name . ':' . $value . ';';
		}
		return $style;
	}

	/**
	 * Return the data for the toggle.
	 *
	 * @param array<string,mixed> $result The result of get_entries().
	 * @param array<string,mixed> $settings The normalized settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_toggle_data( array $result, array $settings ): array {
		$entries = $result['entries'];

		// get the target language of the toggle: the configured or the first not original language.
		$target_language_code = '';
		if ( ! empty( $settings['toggle_language'] ) && ! empty( $entries[ $settings['toggle_language'] ] ) && ! $entries[ $settings['toggle_language'] ]['original'] ) {
			$target_language_code = $settings['toggle_language'];
		} else {
			foreach ( $entries as $language_code => $entry ) {
				if ( ! $entry['original'] ) {
					$target_language_code = (string) $language_code;
					break;
				}
			}
		}

		// bail if no target language is available.
		if ( empty( $target_language_code ) ) {
			return array();
		}

		// get the target entry.
		$target = $entries[ $target_language_code ];

		// get the original entry.
		$original = $entries[ $result['original'] ] ?? false;

		// define the toggle data.
		$checked = $target['active'];
		$data    = array(
			'label'     => $target['label'],
			'checked'   => $checked,
			'available' => $target['available'],
			'url'       => $target['url'],
		);

		// if the toggle is checked, it links to the original.
		if ( $checked ) {
			// bail if the original is not available.
			if ( ! $original ) {
				return array();
			}
			$data['url']       = $original['url'];
			$data['available'] = true;
			/* translators: %1$s will be replaced by the name of the language */
			$data['title'] = sprintf( __( 'Show this site in %1$s.', 'easy-language' ), $original['label'] );
		} elseif ( $target['available'] ) {
			/* translators: %1$s will be replaced by the name of the language */
			$data['title'] = sprintf( __( 'Show this site in %1$s.', 'easy-language' ), $target['label'] );
		} else {
			/* translators: %1$s will be replaced by the name of the language */
			$data['title'] = sprintf( __( 'This site is not available in %1$s.', 'easy-language' ), $target['label'] );
		}

		// bail if target is not available and should not be shown.
		if ( ! $data['available'] && ! $settings['toggle_disabled'] ) {
			return array();
		}

		// define the state for screen readers.
		$data['state'] = $checked ? __( 'on', 'easy-language' ) : __( 'off', 'easy-language' );
		if ( ! $data['available'] ) {
			$data['state'] = __( 'not available', 'easy-language' );
		}

		/**
		 * Filter the data of the toggle.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param array $data The toggle data (label, checked, available, url, title, state).
		 * @param array $result The entries.
		 * @param array $settings The normalized settings.
		 */
		return apply_filters( 'easy_language_switcher_toggle', $data, $result, $settings );
	}

	/**
	 * Return the inner HTML of the toggle.
	 *
	 * The state is only visible for screen readers, the track shows it visually.
	 *
	 * @param array<string,mixed> $toggle The toggle data.
	 *
	 * @return string
	 */
	private function get_toggle_inner_html( array $toggle ): string {
		return '<span class="easy-language-switcher-toggle-track" aria-hidden="true"><span class="easy-language-switcher-toggle-thumb"></span></span><span class="easy-language-switcher-toggle-label">' . esc_html( $toggle['label'] ) . '</span><span class="easy-language-switcher-screen-reader-text">: ' . esc_html( $toggle['state'] ) . '</span>';
	}

	/**
	 * Add the wrapper to the output.
	 *
	 * @param string              $html The output.
	 * @param array<string,mixed> $settings The normalized settings.
	 *
	 * @return string
	 */
	private function add_wrapper( string $html, array $settings ): string {
		// bail if no wrapper should be used.
		if ( empty( $settings['wrapper'] ) ) {
			return $html;
		}

		// define the classes.
		$classes = trim( $settings['wrapper_class'] . ' easy-language-switcher-display-' . $settings['display'] );

		// get the attributes: from the block (with its supports) or only our classes.
		if ( 'block' === $settings['wrapper_source'] && class_exists( 'WP_Block_Supports' ) && ! empty( WP_Block_Supports::$block_to_render ) ) {
			$attributes = get_block_wrapper_attributes( array( 'class' => $classes ) );
		} else {
			$attributes = 'class="' . esc_attr( $classes ) . '"';
		}

		// return with the requested wrapper.
		if ( 'navigation' === $settings['wrapper'] ) {
			return '<li ' . $attributes . '>' . $html . '</li>';
		}
		return '<div ' . $attributes . '>' . $html . '</div>';
	}

	/**
	 * Return the icon of the given language in the given size.
	 *
	 * @param string $language_code The language code.
	 * @param string $size The size as CSS-value (empty for the default size).
	 *
	 * @return string
	 */
	private function get_icon_html( string $language_code, string $size ): string {
		// get the icon.
		$html = $this->get_icon( $language_code );

		// bail if no icon is available or no size is requested.
		if ( empty( $size ) || empty( trim( $html ) ) ) {
			return $html;
		}

		// set the size via CSS variable on the image.
		$processor = new WP_HTML_Tag_Processor( $html );
		if ( $processor->next_tag( array( 'tag_name' => 'img' ) ) ) {
			$processor->add_class( 'easy-language-icon-size' );
			$style = $processor->get_attribute( 'style' );
			$style = is_string( $style ) ? rtrim( trim( $style ), ';' ) . ';' : '';
			$processor->set_attribute( 'style', ltrim( $style . '--easy-language-icon-size:' . $size, ';' ) );
		}
		return $processor->get_updated_html();
	}

	/**
	 * Return the icon of the given language (cached per request).
	 *
	 * @param string $language_code The language code.
	 *
	 * @return string
	 */
	private function get_icon( string $language_code ): string {
		if ( ! isset( $this->icons[ $language_code ] ) ) {
			$this->icons[ $language_code ] = Helper::get_icon_img_for_language_code( $language_code );
		}
		return $this->icons[ $language_code ];
	}

	/**
	 * Return whether the switcher should be visible on the actual request.
	 *
	 * @param array<string,mixed> $settings The normalized settings.
	 * @param array<string,mixed> $context The context.
	 *
	 * @return bool
	 */
	private function is_visible( array $settings, array $context ): bool {
		$visible = true;

		// check the chosen objects.
		if ( 'all' !== $settings['visibility'] ) {
			$matches = $this->matches_objects( $settings, $context['original'] );
			$visible = 'include' === $settings['visibility'] ? $matches : ! $matches;
		}

		/**
		 * Filter whether the language switcher is visible on the actual request.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param bool $visible True if the switcher is visible.
		 * @param array $settings The normalized settings.
		 * @param Objects|false $object The original object of the actual request (false if there is none).
		 */
		return (bool) apply_filters( 'easy_language_switcher_visible', $visible, $settings, $context['original'] );
	}

	/**
	 * Return whether the given object matches the chosen objects of the visibility settings.
	 *
	 * @param array<string,mixed> $settings The normalized settings.
	 * @param Objects|false       $given_object The original object of the actual request.
	 *
	 * @return bool
	 */
	private function matches_objects( array $settings, Objects|false $given_object ): bool {
		// bail if no object is given.
		if ( ! $given_object instanceof Objects ) {
			return false;
		}

		// get the chosen posts and terms.
		$posts = array();
		$terms = array();
		foreach ( $settings['visibility_objects'] as $value ) {
			list( $type, $id ) = explode( ':', $value );
			if ( 'post' === $type ) {
				$posts[] = absint( $id );
			} else {
				$terms[] = absint( $id );
			}
		}

		// check terms.
		if ( $given_object instanceof Term_Object ) {
			// check the term itself.
			if ( in_array( $given_object->get_id(), $terms, true ) ) {
				return true;
			}

			// check its parents.
			if ( $settings['visibility_children'] ) {
				$ancestors = array_map( 'absint', get_ancestors( $given_object->get_id(), $given_object->get_type(), 'taxonomy' ) );
				return ! empty( array_intersect( $ancestors, $terms ) );
			}
			return false;
		}

		// bail for objects of other plugins.
		if ( ! $given_object instanceof Post_Object ) {
			return false;
		}

		// check the post itself.
		if ( in_array( $given_object->get_id(), $posts, true ) ) {
			return true;
		}

		// check its parents.
		if ( $settings['visibility_children'] && ! empty( array_intersect( array_map( 'absint', get_post_ancestors( $given_object->get_id() ) ), $posts ) ) ) {
			return true;
		}

		// bail if assigned terms should not be checked.
		if ( ! $settings['visibility_assigned'] ) {
			return false;
		}

		// check the assigned terms.
		foreach ( $terms as $term_id ) {
			$term = get_term( $term_id );

			// bail if the term could not be loaded.
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			// collect the term and its children, if requested.
			$term_ids = array( $term_id );
			if ( $settings['visibility_children'] ) {
				$children = get_term_children( $term_id, $term->taxonomy );
				if ( is_array( $children ) ) {
					$term_ids = array_merge( $term_ids, array_map( 'absint', $children ) );
				}
			}

			// check if the post has one of these terms.
			if ( has_term( $term_ids, $term->taxonomy, $given_object->get_id() ) ) {
				return true;
			}
		}

		// return false if nothing matches.
		return false;
	}

	/**
	 * Change the menu items if they are language switcher-items for classic themes.
	 *
	 * @param array<int|string,mixed> $items List of menu-items.
	 *
	 * @return array<int|string,mixed>
	 */
	public function set_menu_items( array $items ): array {
		// do nothing in wp-admin.
		if ( is_admin() ) {
			return $items;
		}

		// collect the resulting list of menu items.
		$new_items = array();

		// count of additional added menu items to correct the menu order of following items.
		$offset = 0;

		// loop through the possible menu-items.
		foreach ( $items as $item ) {
			// bail if this is not a language switcher item.
			if ( ! is_object( $item ) || ! isset( $item->object ) || EASY_LANGUAGE_CPT_SWITCHER !== $item->object ) {
				// correct the menu order if previous items have been added.
				if ( 0 !== $offset && is_object( $item ) ) {
					$item              = clone $item;
					$item->menu_order += $offset; // @phpstan-ignore property.notFound
				}

				// add the item to the list.
				$new_items[] = $item;
				continue;
			}

			// get the entries for this switcher.
			$settings = $this->get_menu_item_settings( absint( $item->ID ) ); // @phpstan-ignore property.notFound
			$result   = $this->get_entries( $settings );

			// remove the item if no entries are available.
			if ( empty( $result['entries'] ) ) {
				--$offset;
				continue;
			}

			// create the menu items.
			$language_items = self::DISPLAY_TOGGLE === $settings['display'] ? $this->get_menu_toggle_item( $item, $result, $settings ) : $this->get_menu_language_items( $item, $result, $settings );

			// remove the item if no language item could be created.
			if ( empty( $language_items ) ) {
				--$offset;
				continue;
			}

			// add the language items with the correct menu order.
			foreach ( $language_items as $index => $language_item ) {
				$language_item->menu_order = $item->menu_order + $offset + $index; // @phpstan-ignore property.notFound, property.notFound
				$new_items[]               = $language_item;
			}

			// update the offset for the following menu items.
			$offset += count( $language_items ) - 1;
		}

		// return resulting list of menu items.
		return $new_items;
	}

	/**
	 * Return the menu items for each language of a switcher in a classic menu.
	 *
	 * @param object              $item The menu item of the switcher.
	 * @param array<string,mixed> $result The entries.
	 * @param array<string,mixed> $settings The normalized settings.
	 *
	 * @return array<int,object>
	 */
	private function get_menu_language_items( object $item, array $result, array $settings ): array {
		$items = array();
		foreach ( $result['entries'] as $language_code => $entry ) {
			// create own object for this menu item.
			$new_item = clone $item;

			// set title for menu item.
			$new_item->title = self::DISPLAY_ICONS === $settings['display'] ? $this->get_icon_html( $language_code, $settings['icon_size'] ) : $entry['label']; // @phpstan-ignore property.notFound

			// set URL for menu item.
			$new_item->url = $entry['url']; // @phpstan-ignore property.notFound

			// set additional attributes for the link (not in compatibility mode to keep the output).
			$attributes = array();
			if ( $entry['active'] && ! $settings['legacy_markup'] ) {
				$attributes['aria-current'] = 'page';
			}
			if ( self::DISPLAY_ICONS === $settings['display'] && ! $settings['legacy_markup'] ) {
				$attributes['aria-label'] = $entry['label'];
			}
			$new_item->easy_language_link_attributes = $attributes; // @phpstan-ignore property.notFound

			// add the item to the list.
			$items[] = $new_item;
		}
		return $items;
	}

	/**
	 * Return the menu item for a toggle in a classic menu.
	 *
	 * @param object              $item The menu item of the switcher.
	 * @param array<string,mixed> $result The entries.
	 * @param array<string,mixed> $settings The normalized settings.
	 *
	 * @return array<int,object>
	 */
	private function get_menu_toggle_item( object $item, array $result, array $settings ): array {
		// get the toggle data.
		$toggle = $this->get_toggle_data( $result, $settings );

		// bail if no toggle is available.
		if ( empty( $toggle ) ) {
			return array();
		}

		// create own object for this menu item.
		$new_item        = clone $item;
		$new_item->title = $this->get_toggle_inner_html( $toggle ); // @phpstan-ignore property.notFound
		$new_item->url   = $toggle['available'] ? $toggle['url'] : ''; // @phpstan-ignore property.notFound

		// define the classes and attributes of the link.
		$class = 'easy-language-switcher-toggle';
		if ( $toggle['checked'] ) {
			$class .= ' is-checked';
		}
		$attributes = array(
			'title' => $toggle['title'],
		);
		if ( ! $toggle['available'] ) {
			$class .= ' is-disabled';
		}
		$style = $this->get_toggle_style( $settings );
		if ( ! empty( $style ) ) {
			$attributes['style'] = $style;
		}
		$attributes['class']                     = $class;
		$new_item->easy_language_link_attributes = $attributes; // @phpstan-ignore property.notFound

		// return the item.
		return array( $new_item );
	}

	/**
	 * Add our attributes to the links of our menu items in classic menus.
	 *
	 * @param array<string,string> $atts The link attributes.
	 * @param object|null          $menu_item The menu item.
	 *
	 * @return array<string,string>
	 */
	public function set_menu_link_attributes( array $atts, object|null $menu_item = null ): array {
		// bail if this is not one of our items.
		if ( ! is_object( $menu_item ) || empty( $menu_item->easy_language_link_attributes ) || ! is_array( $menu_item->easy_language_link_attributes ) ) {
			return $atts;
		}

		// add our attributes.
		foreach ( $menu_item->easy_language_link_attributes as $name => $value ) {
			if ( 'class' === $name && ! empty( $atts['class'] ) ) {
				$value = $atts['class'] . ' ' . $value;
			}
			$atts[ $name ] = $value;
		}

		// return the resulting attributes.
		return $atts;
	}

	/**
	 * Return the settings of a switcher in a classic menu.
	 *
	 * @param int $item_id The ID of the menu item.
	 *
	 * @return array<string,mixed>
	 */
	private function get_menu_item_settings( int $item_id ): array {
		// get the saved settings.
		$settings = get_post_meta( $item_id, self::MENU_SETTINGS_META, true );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// use the icon-setting before 4.0.0 if no display mode has been saved.
		$settings['show_icons'] = 'on' === get_post_meta( $item_id, 'easy-language-icons', true );

		// classic menus did hide the switcher on all requests without supported object.
		if ( empty( $settings['fallback'] ) || 'auto' === $settings['fallback'] ) {
			$settings['fallback'] = 'hide';
		}

		// classic menus did always consider the link-mode.
		$settings['legacy']        = false;
		$settings['legacy_markup'] = true;
		$settings['source']        = 'menu';

		// return the normalized settings.
		return $this->normalize_settings( $settings );
	}

	/**
	 * Return the WP-object for the given ID depending on the type of the given object.
	 *
	 * @param Objects $easy_language_object The object which defines the type (post or term).
	 * @param int     $object_id The ID of the requested WP-object.
	 *
	 * @return WP_Post|WP_Term|null
	 */
	private function get_wp_object( Objects $easy_language_object, int $object_id ): WP_Post|WP_Term|null {
		// get the term if this is a term-object.
		if ( $easy_language_object instanceof Term_Object ) {
			$term = get_term( $object_id, $easy_language_object->get_type() );

			// bail if term could not be loaded.
			if ( ! $term instanceof WP_Term ) {
				return null;
			}

			// return the term.
			return $term;
		}

		// return the post in all other cases.
		return get_post( $object_id );
	}

	/**
	 * Add options for language-switcher in classic menu.
	 *
	 * @param int          $item_id The ID of the item.
	 * @param WP_Post|null $menu_item The menu item.
	 *
	 * @return void
	 */
	public function add_menu_options( int $item_id, WP_Post|null $menu_item = null ): void {
		// bail if this is not a language switcher.
		if ( EASY_LANGUAGE_CPT_SWITCHER !== get_post_meta( $item_id, '_menu_item_object', true ) ) {
			return;
		}

		// get the settings.
		$settings = $this->get_menu_item_settings( $item_id );
		$saved    = get_post_meta( $item_id, self::MENU_SETTINGS_META, true );
		$saved    = is_array( $saved ) ? $saved : array();

		// get the field name prefix.
		$name = 'menu-item-easy-language[' . absint( $item_id ) . ']';
		$id   = 'edit-menu-item-easy-language-' . absint( $item_id );

		// output.
		?>
		<div class="easy-language-menu-switcher-settings" data-display="<?php echo esc_attr( $settings['display'] ); ?>" data-visibility="<?php echo esc_attr( $settings['visibility'] ); ?>">
			<p class="description description-wide">
				<label for="<?php echo esc_attr( $id ); ?>-display">
					<?php echo esc_html__( 'Display', 'easy-language' ); ?><br />
					<select id="<?php echo esc_attr( $id ); ?>-display" name="<?php echo esc_attr( $name ); ?>[display]" class="widefat easy-language-menu-display">
						<?php
						foreach ( $this->get_display_options() as $value => $label ) {
							?>
							<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $settings['display'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php
						}
						?>
					</select>
				</label>
			</p>
			<p class="description description-wide easy-language-menu-for-links">
				<label for="<?php echo esc_attr( $id ); ?>-hide-actual-language">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>-hide-actual-language" name="<?php echo esc_attr( $name ); ?>[hide_actual_language]" value="1"<?php checked( $settings['hide_actual_language'] ); ?>>
					<?php echo esc_html__( 'Hide actual language', 'easy-language' ); ?>
				</label>
			</p>
			<p class="description description-wide easy-language-menu-for-icons">
				<label for="<?php echo esc_attr( $id ); ?>-icon-size">
					<?php echo esc_html__( 'Icon size', 'easy-language' ); ?><br />
					<input type="text" id="<?php echo esc_attr( $id ); ?>-icon-size" name="<?php echo esc_attr( $name ); ?>[icon_size]" value="<?php echo esc_attr( isset( $saved['icon_size'] ) ? (string) $saved['icon_size'] : '' ); ?>" class="widefat" placeholder="<?php echo esc_attr__( 'e.g. 24px', 'easy-language' ); ?>">
					<span class="description"><?php echo esc_html__( 'Leave empty to use the default size from the plugin settings.', 'easy-language' ); ?></span>
				</label>
			</p>
			<p class="description description-wide easy-language-menu-for-toggle">
				<label for="<?php echo esc_attr( $id ); ?>-toggle-language">
					<?php echo esc_html__( 'Language of the toggle', 'easy-language' ); ?><br />
					<select id="<?php echo esc_attr( $id ); ?>-toggle-language" name="<?php echo esc_attr( $name ); ?>[toggle_language]" class="widefat">
						<?php
						foreach ( $this->get_toggle_language_options() as $value => $label ) {
							?>
							<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $settings['toggle_language'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php
						}
						?>
					</select>
				</label>
			</p>
			<p class="description description-wide easy-language-menu-for-toggle">
				<label for="<?php echo esc_attr( $id ); ?>-toggle-disabled">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>-toggle-disabled" name="<?php echo esc_attr( $name ); ?>[toggle_disabled]" value="1"<?php checked( $settings['toggle_disabled'] ); ?>>
					<?php echo esc_html__( 'Gray out if no simplification is available', 'easy-language' ); ?>
				</label>
			</p>
			<?php
			foreach ( $this->get_toggle_design_fields() as $field_name => $field ) {
				?>
				<p class="description description-wide easy-language-menu-for-toggle">
					<label for="<?php echo esc_attr( $id . '-' . $field_name ); ?>">
						<?php echo esc_html( $field['label'] ); ?><br />
						<input type="text" id="<?php echo esc_attr( $id . '-' . $field_name ); ?>" name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $field_name ); ?>]" value="<?php echo esc_attr( isset( $saved[ $field_name ] ) ? (string) $saved[ $field_name ] : '' ); ?>" class="widefat<?php echo $field['color'] ? ' easy-language-color-field' : ''; ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>">
					</label>
				</p>
				<?php
			}
			?>
			<p class="description description-wide">
				<label for="<?php echo esc_attr( $id ); ?>-visibility">
					<?php echo esc_html__( 'Visibility', 'easy-language' ); ?><br />
					<select id="<?php echo esc_attr( $id ); ?>-visibility" name="<?php echo esc_attr( $name ); ?>[visibility]" class="widefat easy-language-menu-visibility">
						<?php
						foreach ( $this->get_visibility_options() as $value => $label ) {
							?>
							<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $settings['visibility'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php
						}
						?>
					</select>
				</label>
			</p>
			<div class="description description-wide easy-language-menu-for-visibility">
				<?php echo esc_html__( 'Pages and terms', 'easy-language' ); ?><br />
				<?php $objects = $this->get_object_labels( $settings['visibility_objects'] ); ?>
				<div class="easy-language-object-picker">
					<input type="hidden" class="easy-language-object-picker-value" name="<?php echo esc_attr( $name ); ?>[visibility_objects]" value="<?php echo esc_attr( implode( ',', wp_list_pluck( $objects, 'value' ) ) ); ?>">
					<ul class="easy-language-object-picker-selected">
						<?php
						foreach ( $objects as $object ) {
							?>
							<li data-value="<?php echo esc_attr( $object['value'] ); ?>">
								<span><?php echo esc_html( $object['label'] ); ?></span>
								<button type="button" class="button-link easy-language-object-picker-remove" aria-label="<?php /* translators: %1$s will be replaced by the title of the object. */ echo esc_attr( sprintf( __( 'Remove %1$s', 'easy-language' ), $object['label'] ) ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
							</li>
							<?php
						}
						?>
					</ul>
					<input type="search" class="widefat easy-language-object-picker-search" placeholder="<?php echo esc_attr__( 'Search pages and terms …', 'easy-language' ); ?>" aria-label="<?php echo esc_attr__( 'Search pages and terms', 'easy-language' ); ?>">
					<ul class="easy-language-object-picker-results" role="listbox"></ul>
				</div>
			</div>
			<p class="description description-wide easy-language-menu-for-visibility">
				<label for="<?php echo esc_attr( $id ); ?>-visibility-children">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>-visibility-children" name="<?php echo esc_attr( $name ); ?>[visibility_children]" value="1"<?php checked( $settings['visibility_children'] ); ?>>
					<?php echo esc_html__( 'Include child pages and child terms', 'easy-language' ); ?>
				</label>
			</p>
			<p class="description description-wide easy-language-menu-for-visibility">
				<label for="<?php echo esc_attr( $id ); ?>-visibility-assigned">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>-visibility-assigned" name="<?php echo esc_attr( $name ); ?>[visibility_assigned]" value="1"<?php checked( $settings['visibility_assigned'] ); ?>>
					<?php echo esc_html__( 'Include contents assigned to the chosen terms', 'easy-language' ); ?>
				</label>
			</p>
			<p class="description description-wide">
				<label for="<?php echo esc_attr( $id ); ?>-fallback">
					<?php echo esc_html__( 'On pages without own language versions', 'easy-language' ); ?><br />
					<select id="<?php echo esc_attr( $id ); ?>-fallback" name="<?php echo esc_attr( $name ); ?>[fallback]" class="widefat">
						<?php
						foreach ( $this->get_fallback_options( true ) as $value => $label ) {
							?>
							<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $saved['fallback'] ?? 'auto', $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php
						}
						?>
					</select>
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Save our custom options in classic menu.
	 *
	 * @param int $menu_id The ID of the menu.
	 * @param int $item_id The ID of the menu-item.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function save_menu_options( int $menu_id, int $item_id ): void {
		// bail on save via ajax.
		if ( wp_doing_ajax() ) {
			return;
		}

		// bail if no menu form has been sent (e.g. on wp_update_nav_menu_item() in other contexts).
		if ( ! isset( $_POST['update-nav-menu-nonce'] ) ) {
			return;
		}

		// check nonce.
		check_admin_referer( 'update-nav_menu', 'update-nav-menu-nonce' );

		// bail if this is not a language switcher.
		if ( EASY_LANGUAGE_CPT_SWITCHER !== get_post_meta( $item_id, '_menu_item_object', true ) ) {
			return;
		}

		// get the values.
		$values = isset( $_POST['menu-item-easy-language'][ $item_id ] ) && is_array( $_POST['menu-item-easy-language'][ $item_id ] ) ? map_deep( wp_unslash( $_POST['menu-item-easy-language'][ $item_id ] ), 'sanitize_text_field' ) : array();

		// get the display mode.
		$display = ! empty( $values['display'] ) && array_key_exists( $values['display'], $this->get_display_options() ) ? $values['display'] : self::DISPLAY_TEXT;

		// save the icon setting like before 4.0.0 for compatibility.
		if ( self::DISPLAY_ICONS === $display ) {
			update_post_meta( $item_id, 'easy-language-icons', 'on' );
		} else {
			delete_post_meta( $item_id, 'easy-language-icons' );
		}

		// collect the settings.
		$settings = array(
			'display'              => $display,
			'hide_actual_language' => ! empty( $values['hide_actual_language'] ),
			'icon_size'            => $this->sanitize_size( $values['icon_size'] ?? '' ),
			'toggle_language'      => ! empty( $values['toggle_language'] ) && array_key_exists( $values['toggle_language'], $this->get_toggle_language_options() ) ? $values['toggle_language'] : '',
			'toggle_disabled'      => ! empty( $values['toggle_disabled'] ),
			'toggle_color_on'      => $this->sanitize_color( $values['toggle_color_on'] ?? '' ),
			'toggle_color_off'     => $this->sanitize_color( $values['toggle_color_off'] ?? '' ),
			'toggle_color_thumb'   => $this->sanitize_color( $values['toggle_color_thumb'] ?? '' ),
			'toggle_size'          => $this->sanitize_size( $values['toggle_size'] ?? '' ),
			'toggle_radius'        => $this->sanitize_size( $values['toggle_radius'] ?? '', true ),
			'visibility'           => ! empty( $values['visibility'] ) && array_key_exists( $values['visibility'], $this->get_visibility_options() ) ? $values['visibility'] : 'all',
			'visibility_objects'   => $this->sanitize_objects( $values['visibility_objects'] ?? array() ),
			'visibility_children'  => ! empty( $values['visibility_children'] ),
			'visibility_assigned'  => ! empty( $values['visibility_assigned'] ),
			'fallback'             => ! empty( $values['fallback'] ) && array_key_exists( $values['fallback'], $this->get_fallback_options( true ) ) ? $values['fallback'] : 'auto',
		);

		// save the settings.
		update_post_meta( $item_id, self::MENU_SETTINGS_META, $settings );
	}

	/**
	 * Add scripts and styles for the switcher settings in classic menus.
	 *
	 * @param string $hook The actual admin page.
	 *
	 * @return void
	 */
	public function add_menu_scripts( string $hook ): void {
		// bail if this is not the menu page.
		if ( 'nav-menus.php' !== $hook ) {
			return;
		}

		// add the script.
		wp_enqueue_script(
			'easy-language-menu-switcher',
			Helper::get_plugin_url() . 'admin/menu-switcher.js',
			array( 'wp-api-fetch', 'wp-i18n', 'wp-color-picker' ),
			Helper::get_file_version( Helper::get_plugin_path() . 'admin/menu-switcher.js' ),
			true
		);

		// add the styles.
		wp_enqueue_style(
			'easy-language-menu-switcher',
			Helper::get_plugin_url() . 'admin/menu-switcher.css',
			array( 'wp-color-picker' ),
			Helper::get_file_version( Helper::get_plugin_path() . 'admin/menu-switcher.css' )
		);
	}

	/**
	 * Add the data for our blocks in the block editor.
	 *
	 * @return void
	 */
	public function add_editor_data(): void {
		// collect the data.
		$data = array(
			'languages' => $this->get_toggle_language_options(),
		);

		// add them to the scripts of our blocks.
		foreach ( array( 'easy-language/switcher', 'easy-language/navigation-switcher' ) as $block_name ) {
			$handle = generate_block_asset_handle( $block_name, 'editorScript' );
			wp_add_inline_script( $handle, 'window.easyLanguageSwitcher = ' . wp_json_encode( $data ) . ';', 'before' );
		}
	}

	/**
	 * Register the REST API routes for the switcher.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'easy-language/v1',
			'/switcher-objects',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_objects' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' ) || current_user_can( 'edit_theme_options' );
				},
				'args'                => array(
					'search'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'include' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Return the objects for the object picker of the visibility settings.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function rest_get_objects( WP_REST_Request $request ): array {
		// return the labels of the given objects.
		$include = (string) $request->get_param( 'include' );
		if ( ! empty( $include ) ) {
			return $this->get_object_labels( array_slice( $this->sanitize_objects( $include ), 0, 50 ) );
		}

		// return the search results.
		return $this->search_objects( (string) $request->get_param( 'search' ) );
	}

	/**
	 * Search for supported posts and terms.
	 *
	 * @param string $search The search string.
	 * @param int    $limit Max count of results per type (0 for the default).
	 *
	 * @return array<int,array<string,string>>
	 */
	public function search_objects( string $search, int $limit = 0 ): array {
		// collect the results.
		$results = array();

		/**
		 * Filter the max count of results per type in the object search.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param int $limit The limit.
		 */
		$limit = absint( apply_filters( 'easy_language_switcher_objects_limit', $limit > 0 ? $limit : 20 ) );

		// search posts in supported post types.
		$post_types = array_keys( array_filter( $this->init->get_supported_post_types() ) );
		if ( ! empty( $post_types ) ) {
			$query = new WP_Query(
				array(
					'post_type'                       => $post_types,
					'post_status'                     => 'publish',
					's'                               => $search,
					'posts_per_page'                  => $limit,
					'orderby'                         => empty( $search ) ? 'title' : 'relevance',
					'order'                           => 'ASC',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- simplified objects are only recognizable via their post meta.
					'meta_query'                      => array(
						array(
							'key'     => 'easy_language_simplification_original_id',
							'compare' => 'NOT EXISTS',
						),
					),
					'do_not_use_easy_language_filter' => '1',
				)
			);
			foreach ( $query->posts as $post ) {
				if ( $post instanceof WP_Post ) {
					$results[] = $this->get_object_label( 'post:' . $post->ID );
				}
			}
		}

		// search terms in supported taxonomies.
		$taxonomies = array_keys( $this->init->get_supported_taxonomies() );
		if ( ! empty( $taxonomies ) ) {
			$terms = get_terms(
				array(
					'taxonomy'                        => $taxonomies,
					'search'                          => $search,
					'hide_empty'                      => false,
					'number'                          => $limit,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- simplified objects are only recognizable via their term meta.
					'meta_query'                      => array(
						array(
							'key'     => 'easy_language_simplification_original_id',
							'compare' => 'NOT EXISTS',
						),
					),
					'do_not_use_easy_language_filter' => '1',
				)
			);
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$results[] = $this->get_object_label( 'term:' . $term->term_id );
				}
			}
		}

		// return the results without empty entries.
		return array_values( array_filter( $results ) );
	}

	/**
	 * Return the labels for the given objects.
	 *
	 * @param array<int,string> $objects List of objects as "post:ID" or "term:ID".
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_object_labels( array $objects ): array {
		return array_values( array_filter( array_map( array( $this, 'get_object_label' ), $objects ) ) );
	}

	/**
	 * Return the label for the given object.
	 *
	 * @param string $value The object as "post:ID" or "term:ID".
	 *
	 * @return array<string,string>
	 */
	private function get_object_label( string $value ): array {
		list( $type, $id ) = array_pad( explode( ':', $value ), 2, '0' );
		$id                = absint( $id );

		// get the label for terms (only of supported taxonomies and only if the taxonomy is public or the user could assign it).
		if ( 'term' === $type ) {
			$term = get_term( $id );
			if ( ! $term instanceof WP_Term || ! $this->init->is_taxonomy_supported( $term->taxonomy ) || ( ! is_taxonomy_viewable( $term->taxonomy ) && ! current_user_can( 'assign_term', $term->term_id ) ) ) {
				return array();
			}
			$taxonomy = get_taxonomy( $term->taxonomy );
			return array(
				'value' => $value,
				'label' => html_entity_decode( $term->name . ( $taxonomy ? ' (' . $taxonomy->labels->singular_name . ')' : '' ), ENT_QUOTES, 'UTF-8' ),
			);
		}

		// get the label for posts (only of supported post types and only if the user could read it).
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || ! $this->init->is_post_type_supported( $post->post_type ) || ! current_user_can( 'read_post', $post->ID ) ) {
			return array();
		}
		$post_type = get_post_type_object( $post->post_type );
		$title     = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
		if ( empty( $title ) ) {
			/* translators: %1$d will be replaced by the ID of the object. */
			$title = sprintf( __( '(no title, ID %1$d)', 'easy-language' ), $post->ID );
		}
		return array(
			'value' => $value,
			'label' => $title . ( $post_type ? ' (' . html_entity_decode( $post_type->labels->singular_name, ENT_QUOTES, 'UTF-8' ) . ')' : '' ),
		);
	}

	/**
	 * Return the definition of all settings for widgets in page builders.
	 *
	 * Each page builder maps these definitions to its own control format and renders the widget via render_widget().
	 * So all widgets offer the same settings as the block and the Elementor widget.
	 *
	 * Keys of each field:
	 * - type: "select", "switch", "size", "color" or "objects" (list of pages and terms as "post:ID" / "term:ID")
	 * - label: the label
	 * - description: optional description
	 * - section: "settings", "visibility" or "design" (design settings of the toggle)
	 * - default: the default value
	 * - options: for "select" the options as value => label
	 * - units: for "size" the possible units
	 * - condition: optional list of field => allowed values; the field is only relevant if all conditions match
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_widget_fields(): array {
		$links      = array( self::DISPLAY_TEXT, self::DISPLAY_ICONS );
		$visibility = array( 'include', 'exclude' );
		$fields     = array(
			'display'              => array(
				'type'    => 'select',
				'label'   => __( 'Display', 'easy-language' ),
				'section' => 'settings',
				'default' => self::DISPLAY_TEXT,
				'options' => $this->get_display_options(),
			),
			'hide_actual_language' => array(
				'type'      => 'switch',
				'label'     => __( 'Hide actual language', 'easy-language' ),
				'section'   => 'settings',
				'default'   => false,
				'condition' => array( 'display' => $links ),
			),
			'icon_size'            => array(
				'type'        => 'size',
				'label'       => __( 'Icon size', 'easy-language' ),
				'description' => __( 'Leave empty to use the default size from the plugin settings.', 'easy-language' ),
				'section'     => 'settings',
				'default'     => '',
				'units'       => array( 'px', 'em', 'rem' ),
				'condition'   => array( 'display' => array( self::DISPLAY_ICONS ) ),
			),
			'toggle_language'      => array(
				'type'      => 'select',
				'label'     => __( 'Language of the toggle', 'easy-language' ),
				'section'   => 'settings',
				'default'   => '',
				'options'   => $this->get_toggle_language_options(),
				'condition' => array( 'display' => array( self::DISPLAY_TOGGLE ) ),
			),
			'toggle_disabled'      => array(
				'type'        => 'switch',
				'label'       => __( 'Gray out if no simplification is available', 'easy-language' ),
				'description' => __( 'If disabled, the toggle will be hidden on pages without simplification.', 'easy-language' ),
				'section'     => 'settings',
				'default'     => false,
				'condition'   => array( 'display' => array( self::DISPLAY_TOGGLE ) ),
			),
			'visibility'           => array(
				'type'    => 'select',
				'label'   => __( 'Visibility', 'easy-language' ),
				'section' => 'visibility',
				'default' => 'all',
				'options' => $this->get_visibility_options(),
			),
			'visibility_objects'   => array(
				'type'        => 'objects',
				'label'       => __( 'Pages and terms', 'easy-language' ),
				'description' => __( 'Search for pages, posts or terms.', 'easy-language' ),
				'section'     => 'visibility',
				'default'     => array(),
				'condition'   => array( 'visibility' => $visibility ),
			),
			'visibility_children'  => array(
				'type'      => 'switch',
				'label'     => __( 'Include child pages and child terms', 'easy-language' ),
				'section'   => 'visibility',
				'default'   => false,
				'condition' => array( 'visibility' => $visibility ),
			),
			'visibility_assigned'  => array(
				'type'      => 'switch',
				'label'     => __( 'Include contents assigned to the chosen terms', 'easy-language' ),
				'section'   => 'visibility',
				'default'   => false,
				'condition' => array( 'visibility' => $visibility ),
			),
			'fallback'             => array(
				'type'        => 'select',
				'label'       => __( 'On pages without own language versions', 'easy-language' ),
				'description' => __( 'For example archives, search results or the blog homepage.', 'easy-language' ),
				'section'     => 'visibility',
				'default'     => 'auto',
				'options'     => $this->get_fallback_options(),
			),
		);

		// add the design fields of the toggle.
		foreach ( $this->get_toggle_design_fields() as $name => $field ) {
			$fields[ $name ] = array(
				'type'      => $field['color'] ? 'color' : 'size',
				'label'     => $field['label'],
				'section'   => 'design',
				'default'   => '',
				'units'     => 'toggle_radius' === $name ? array( 'px', 'em', '%' ) : array( 'px', 'em', 'rem' ),
				'condition' => array( 'display' => array( self::DISPLAY_TOGGLE ) ),
			);
		}

		/**
		 * Filter the settings of the language switcher widgets in page builders.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param array<string,array<string,mixed>> $fields The fields.
		 */
		return apply_filters( 'easy_language_switcher_widget_fields', $fields );
	}

	/**
	 * Return the titles of the sections of the widget settings.
	 *
	 * @return array<string,string>
	 */
	public function get_widget_sections(): array {
		return array(
			'settings'   => __( 'Settings', 'easy-language' ),
			'visibility' => __( 'Visibility', 'easy-language' ),
			'design'     => __( 'Colors of the toggle', 'easy-language' ),
		);
	}

	/**
	 * Return the options for the visibility objects for page builders with select fields.
	 *
	 * The options are only loaded in the backend (where page builders show their editors) to prevent queries in frontend.
	 *
	 * @return array<string,string>
	 */
	public function get_widget_object_options(): array {
		// bail in frontend.
		if ( ! is_admin() && ! wp_doing_ajax() && ! $this->is_rest_request() ) {
			return array();
		}

		// use the cache.
		static $options = null;
		if ( is_array( $options ) ) {
			return $options;
		}

		// get the options.
		$options = array();
		foreach ( $this->search_objects( '', 500 ) as $object ) {
			$options[ $object['value'] ] = $object['label'];
		}
		return $options;
	}

	/**
	 * Return whether the actual request is any REST API request.
	 *
	 * @return bool
	 */
	private function is_rest_request(): bool {
		return function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request();
	}

	/**
	 * Return the output of a widget in a page builder.
	 *
	 * Converts the values of the page builder (e.g. "yes"/"on" for switches, arrays for sizes) via the field
	 * definitions of get_widget_fields() and uses the output of new blocks (wrapper and actual link behavior).
	 *
	 * @param array<string,mixed> $values The values of the widget.
	 * @param string              $source The name of the page builder.
	 * @param bool                $preview True if the widget is shown in the editor of the page builder.
	 *
	 * @return string
	 */
	public function render_widget( array $values, string $source, bool $preview = false ): string {
		// collect the settings.
		$settings = array();
		foreach ( $this->get_widget_fields() as $name => $field ) {
			$value = $values[ $name ] ?? $field['default'];
			if ( 'switch' === $field['type'] ) {
				$value = $this->is_true( $value );
			}
			$settings[ $name ] = $value;
		}

		// add the settings for the output.
		$settings['legacy']        = false;
		$settings['wrapper']       = 'block';
		$settings['wrapper_class'] = 'easy-language-switcher easy-language-switcher-' . sanitize_html_class( $source );
		$settings['preview']       = $preview;
		$settings['source']        = $source;

		// return the output.
		return $this->get( $settings );
	}

	/**
	 * Return the fields for the design of the toggle.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_toggle_design_fields(): array {
		return array(
			'toggle_color_on'    => array(
				'label'       => __( 'Color of the toggle if on', 'easy-language' ),
				'placeholder' => '#2271b1',
				'color'       => true,
			),
			'toggle_color_off'   => array(
				'label'       => __( 'Color of the toggle if off', 'easy-language' ),
				'placeholder' => '#6c7781',
				'color'       => true,
			),
			'toggle_color_thumb' => array(
				'label'       => __( 'Color of the thumb', 'easy-language' ),
				'placeholder' => '#ffffff',
				'color'       => true,
			),
			'toggle_size'        => array(
				'label'       => __( 'Size of the toggle', 'easy-language' ),
				'placeholder' => __( 'e.g. 24px', 'easy-language' ),
				'color'       => false,
			),
			'toggle_radius'      => array(
				'label'       => __( 'Rounding of the toggle', 'easy-language' ),
				'placeholder' => __( 'e.g., 4px, 0 for squared', 'easy-language' ),
				'color'       => false,
			),
		);
	}

	/**
	 * Return the possible display modes.
	 *
	 * @return array<string,string>
	 */
	public function get_display_options(): array {
		return array(
			self::DISPLAY_TEXT   => __( 'Text', 'easy-language' ),
			self::DISPLAY_ICONS  => __( 'Icons', 'easy-language' ),
			self::DISPLAY_TOGGLE => __( 'Toggle', 'easy-language' ),
		);
	}

	/**
	 * Return the possible visibility modes.
	 *
	 * @return array<string,string>
	 */
	public function get_visibility_options(): array {
		return array(
			'all'     => __( 'Show everywhere', 'easy-language' ),
			'include' => __( 'Show only on chosen pages and terms', 'easy-language' ),
			'exclude' => __( 'Show everywhere except on chosen pages and terms', 'easy-language' ),
		);
	}

	/**
	 * Return the possible fallback modes.
	 *
	 * @param bool $is_menu True if the options are used for a classic menu.
	 *
	 * @return array<string,string>
	 */
	public function get_fallback_options( bool $is_menu = false ): array {
		return array(
			'auto' => $is_menu ? __( 'Hide (like until now)', 'easy-language' ) : __( 'Automatic (like until now)', 'easy-language' ),
			'home' => __( 'Link to the homepages', 'easy-language' ),
			'hide' => __( 'Hide', 'easy-language' ),
		);
	}

	/**
	 * Return the possible languages for the toggle.
	 *
	 * @return array<string,string>
	 */
	public function get_toggle_language_options(): array {
		$options = array(
			'' => __( 'First active language', 'easy-language' ),
		);
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			$options[ (string) $language_code ] = isset( $settings['label'] ) ? (string) $settings['label'] : (string) $language_code;
		}
		return $options;
	}

	/**
	 * Use shortcode to output the language switcher.
	 *
	 * @param array<string,string>|string $attributes Attribute on the shortcode (empty string if none is set).
	 *
	 * @return string
	 */
	public function use_shortcode( array|string $attributes ): string {
		// WordPress uses an empty string if no attributes are set.
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		// get the output.
		return wp_kses_post(
			$this->get(
				array(
					'display'              => $attributes['display'] ?? '',
					'show_icons'           => 'yes' === ( $attributes['show_icons'] ?? '' ),
					'hide_actual_language' => 'yes' === ( $attributes['hide_actual_language'] ?? '' ),
					'icon_size'            => $attributes['icon_size'] ?? '',
					'toggle_language'      => $attributes['toggle_language'] ?? '',
					'toggle_disabled'      => $attributes['toggle_disabled'] ?? '',
					'toggle_color_on'      => $attributes['toggle_color_on'] ?? '',
					'toggle_color_off'     => $attributes['toggle_color_off'] ?? '',
					'toggle_color_thumb'   => $attributes['toggle_color_thumb'] ?? '',
					'toggle_size'          => $attributes['toggle_size'] ?? '',
					'toggle_radius'        => $attributes['toggle_radius'] ?? '',
					'visibility'           => $attributes['visibility'] ?? 'all',
					'visibility_objects'   => $attributes['show_on'] ?? '',
					'visibility_children'  => $attributes['include_children'] ?? '',
					'visibility_assigned'  => $attributes['include_assigned'] ?? '',
					'fallback'             => $attributes['fallback'] ?? 'auto',
					'source'               => 'shortcode',
				)
			)
		);
	}

	/**
	 * Return whether the given value means "true" (supports bool, "yes", "1", "true" and "on").
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 */
	private function is_true( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return 1 === $value;
		}
		return is_string( $value ) && in_array( strtolower( $value ), array( 'yes', '1', 'true', 'on' ), true );
	}

	/**
	 * Sanitize a size value to a CSS-value.
	 *
	 * Supports numbers (as px), strings like "32px", "2em" and the slider-value of page builders (array with size and unit).
	 *
	 * @param mixed $value The value.
	 * @param bool  $allow_zero True to allow 0 as value (e.g. for a radius).
	 *
	 * @return string
	 */
	public function sanitize_size( mixed $value, bool $allow_zero = false ): string {
		// convert page builder values.
		if ( is_array( $value ) ) {
			$value = isset( $value['size'] ) && '' !== $value['size'] ? $value['size'] . ( $value['unit'] ?? 'px' ) : '';
		}

		// convert numbers.
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value > 0 || ( $allow_zero && 0 == $value ) ? $value . 'px' : ''; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
		}

		// bail if this is not a string.
		if ( ! is_string( $value ) ) {
			return '';
		}

		// check the value.
		$value = strtolower( str_replace( ' ', '', $value ) );
		if ( ! preg_match( '/^(\d+(?:\.\d+)?)(px|em|rem|%|vw|vh)?$/', $value, $matches ) || ( 0.0 === (float) $matches[1] && ! $allow_zero ) ) {
			return '';
		}

		// return the value with unit.
		return $matches[1] . ( $matches[2] ?? 'px' );
	}

	/**
	 * Sanitize a color value.
	 *
	 * Supports hex colors, rgb()/hsl() colors, CSS variables (e.g. from page builders) and color names.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string
	 */
	public function sanitize_color( mixed $value ): string {
		// bail if this is not a string.
		if ( ! is_string( $value ) ) {
			return '';
		}

		// check the value.
		$value = trim( $value );
		if (
			preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value )
			|| preg_match( '/^var\(--[a-z0-9_-]+\)$/i', $value )
			|| preg_match( '/^(?:rgba?|hsla?)\([0-9.,%\s\/a-z]+\)$/i', $value )
			|| preg_match( '/^[a-z]+$/i', $value )
		) {
			return $value;
		}

		// return empty string for any other value.
		return '';
	}

	/**
	 * Allow our CSS variables for the design of the toggle in inline styles filtered by kses.
	 *
	 * WordPress removes values with functions like rgb() from inline styles in content (e.g. of the shortcode).
	 *
	 * @param bool   $allow_css True if the CSS is allowed.
	 * @param string $css_test_string The CSS declaration.
	 *
	 * @return bool
	 */
	public function allow_toggle_css( bool $allow_css, string $css_test_string ): bool {
		// bail if it is already allowed or not our variable.
		if ( $allow_css || ! preg_match( '/^--easy-language-toggle-(?:on|off|thumb):(.*)$/', trim( $css_test_string ), $matches ) ) {
			return $allow_css;
		}

		// allow it if it is a valid color.
		return '' !== $this->sanitize_color( $matches[1] );
	}

	/**
	 * Sanitize the list of objects for the visibility settings.
	 *
	 * @param mixed $value List of objects as array or comma-separated string (e.g. "post:12,term:5").
	 *
	 * @return array<int,string>
	 */
	public function sanitize_objects( mixed $value ): array {
		// convert strings.
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		// bail if this is not an array.
		if ( ! is_array( $value ) ) {
			return array();
		}

		// check each entry.
		$objects = array();
		foreach ( $value as $entry ) {
			if ( ! is_string( $entry ) || ! preg_match( '/^(post|term):(\d+)$/', trim( $entry ), $matches ) || 0 === absint( $matches[2] ) ) {
				continue;
			}
			$objects[] = $matches[1] . ':' . absint( $matches[2] );
		}

		// return the unique list.
		return array_values( array_unique( $objects ) );
	}

	/**
	 * Return whether the actual request is from an editor (block editor preview or page builder).
	 *
	 * @return bool
	 */
	private function is_editor_request(): bool {
		// bail if this is not a REST request.
		if ( ! function_exists( 'wp_is_serving_rest_request' ) || ! wp_is_serving_rest_request() ) {
			return false;
		}

		// only the block renderer (used by the block editor for the preview) is an editor request.
		global $wp;
		$route = $wp instanceof WP && isset( $wp->query_vars['rest_route'] ) && is_string( $wp->query_vars['rest_route'] ) ? $wp->query_vars['rest_route'] : '';
		return str_starts_with( $route, '/wp/v2/block-renderer/' );
	}
}
