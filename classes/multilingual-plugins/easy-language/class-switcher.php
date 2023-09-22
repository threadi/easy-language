<?php
/**
 * File for the switcher-handling.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Helper;
use easyLanguage\Languages;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rewrite-Handling for this plugin.
 */
class Switcher {

	/**
	 * Instance of this object.
	 *
	 * @var ?Switcher
	 */
	private static ?Switcher $instance = null;
	private Init $init;

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
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @param $init
	 *
	 * @return void
	 */
	public function init( $init ): void {
		$this->init = $init;

		// Hooks.
		add_action( 'init', array( $this, 'wp_init' ) );
		add_action( 'init', array( $this, 'set_default' ), 20 );
		add_filter( 'wp_get_nav_menu_items', array( $this, 'set_menu_items') );
	}

	/**
	 * Run this on every request.
	 *
	 * @return void
	 */
	public function wp_init(): void {
		/**
		 * Register our own language-switcher as post-type.
		 */
		$args = array(
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'show_ui'               => true,
			'show_in_nav_menus'     => true,
			'show_in_menu'          => false,
			'show_in_admin_bar'     => false,
			'can_export'            => false,
			'public'                => false,
			'label'                 => __('Language Switcher', 'easy-language')
		);
		register_post_type( EASY_LANGUAGE_CPT_SWITCHER, $args );

		if( function_exists('register_block_type') ) {
			// register switcher block for navigation in FSE-themes.
			register_block_type( helper::get_plugin_path() . 'classes/multilingual-plugins/easy-language/blocks/navigation-switcher/', array(
				'render_callback' => array( $this, 'get' ),
				'attributes'      => array(
					'preview' => array(
						'type' => 'boolean',
						'default' => false
					),
					'show_icons' => array(
						'type' => 'boolean',
						'default' => false
					),
					'hide_actual_language' => array(
						'type' => 'boolean',
						'default' => false
					),
				),
				'parent'          => array( 'core/navigation' )
			) );

			// register switcher block outside of navigation in FSE-themes.
			register_block_type( helper::get_plugin_path() . 'classes/multilingual-plugins/easy-language/blocks/switcher/', array(
				'render_callback' => array( $this, 'get' ),
				'attributes'      => array(
					'preview' => array(
						'type' => 'boolean',
						'default' => false
					),
					'show_icons' => array(
						'type' => 'boolean',
						'default' => false
					),
					'hide_actual_language' => array(
						'type' => 'boolean',
						'default' => false
					),
				),
			) );
		}
	}

	/**
	 * One-time function to create default entry.
	 *
	 * @return void
	 */
	public function set_default(): void {
		// bail if the work has already been done.
		if ( get_option( 'easy_language_switcher_default', 0 ) == 1 ) {
			return;
		}

		// add the single entry for language-switcher to use in classic menus.
		$query = array(
			'post_title' => __( 'Language Switcher', 'easy-language' ),
			'post_content' => '',
			'post_status' => 'publish',
			'post_type' => EASY_LANGUAGE_CPT_SWITCHER
		);
		wp_insert_post($query);

		// mark that we have done the work.
		update_option( 'easy_language_switcher_default', 1 );
	}

	/**
	 * Return switcher for output in frontend via Block Editor/FSE.
	 *
	 * @param array $attributes The settings as array.
	 *
	 * @return string
	 */
	public function get( array $attributes ): string {
		// get current object id.
		$object_id = get_queried_object_id();
		$object_obj =  get_queried_object();

		// if ID is 0, get the ID of the frontpage.
		if( 0 === $object_id ) {
			if( 'page' === get_option('show_on_front') ) {
				$object_id = absint( get_option( 'page_on_front', 0 ) );
				$object_obj = get_post( $object_id );
			}
			elseif( 'posts' === get_option('show_on_front') ) {
				$object_id = absint( get_option( 'page_for_posts', 0 ) );
				$object_obj = get_post( $object_id );
			}
		}

		// get our own object for the requested object.
		$object = $this->init->get_object_by_wp_object( $object_obj, $object_id );
		if( false === $object ) {
			return '';
		}

		// check if this object is a translated object.
		if( $object->is_translated() ) {
			$object_id = $object->get_original_object_as_int();
			// get original object as base for the listing.
			$object = $this->init->get_object_by_wp_object( get_post( $object_id ), $object_id );
		}

		// bail if post type is not supported.
		if( false === $object || false === $this->init->is_post_type_supported( $object->get_type() ) ) {
			return '';
		}

		// get active languages.
		$languages = array_merge( $object->get_language(), Languages::get_instance()->get_active_languages() );
		if( empty($languages) ) {
			return '';
		}

		// remove actual language if set.
		if( false !== $attributes['hide_actual_language'] ) {
			foreach( $languages as $language_code => $settings ) {
				if ( 0 === strcasecmp( Helper::get_current_language(), $language_code) ) {
					unset( $languages[ $language_code ] );
				}
			}
		}

		// variable to collect the output.
		$html = '';

		// loop through active languages and set their links.
		foreach( $languages as $language_code => $settings ) {
			// get URL.
			$url = $object->get_language_specific_url( empty($settings['url']) ? get_permalink($object->get_id()) : $settings['url'], $language_code );

			// define title or icon.
			/* translators: %1$s will be replaced by the name of the language */
			$attribute_title = sprintf(__( 'Show this site in %1$s', 'easy-language' ), $settings['label']);
			$class = 'easy-language-switcher-title';
			$title = $settings['label'];
			if( false !== $attributes['show_icons'] ) {
				$class = 'easy-language-switcher-icon easy-language-switcher-icon-'.strtolower($language_code);
				$title = '';
			}

			// add item to menu.
			$html .= '<a href="' . esc_url( $url ) . '" class="'.esc_attr( $class ).'" title="'.esc_attr($attribute_title).'">' . esc_html( $title ) . '</a>';
		}

		// return resulting links.
		return $html;
	}

	/**
	 * Change the menu items if they are language switcher-items for classic themes.
	 *
	 * @param $items
	 *
	 * @return array
	 */
	public function set_menu_items( $items ): array {
		// do nothing in wp-admin.
		if( is_admin() ) {
			return $items;
		}

		// get current object id
		$object_id = get_queried_object_id();

		// get our own object for the requested object.
		$object = $this->init->get_object_by_wp_object( get_queried_object(), $object_id );

		// bail if no object has been found.
		if( false === $object ) {
			// remove our own language-switcher in this case.
			foreach( $items as $key => $item ) {
				if ( $item->object == EASY_LANGUAGE_CPT_SWITCHER ) {
					unset( $items[$key] );
				}
			}

			// return resulting menu-item-list.
			return $items;
		}

		// check if this object is a translated object.
		if( $object->is_translated() ) {
			$object_id = $object->get_original_object_as_int();
			// get new object as base for the listing.
			$object = $this->init->get_object_by_wp_object( get_post( $object_id ), $object_id );
		}

		// bail if post type is not supported.
		if( false === $this->init->is_post_type_supported( $object->get_type() ) ) {
			return $items;
		}

		// get active languages.
		$languages = array_merge( $object->get_language(), Languages::get_instance()->get_active_languages() );
		if( empty($languages) ) {
			return $items;
		}

		// loop through the possible items.
		foreach( $items as $index => $item ) {
			if( $item->object == EASY_LANGUAGE_CPT_SWITCHER ) {
				// get the post-item of this menu-item (this is not the page!).
				$post_id = get_post_meta( $item->ID, '_menu_item_object_id', true );
				$post_obj = new Post_Object( $post_id );

				// do nothing if none could be loaded.
				if( null === $post_id || EASY_LANGUAGE_CPT_SWITCHER !== $post_obj->get_type() ) {
					continue;
				}

				// get actual index.
				$new_index = $index;

				// loop through the active languages and create a single menu item for each.
				foreach( $languages as $language_code => $settings ) {
					// create own object for this menu item.
					$new_item = clone $items[ $index ];
					// set title for menu item.
					$new_item->title = $settings['label'];
					$new_item->menu_order = $items[ $index ]->menu_order + 1;
					// set URL for menu item.
					$new_item->url = $object->get_language_specific_url( empty($settings['url']) ? get_permalink($object->get_id()) : $settings['url'], $language_code );
					array_splice($items, $new_index, 1, array( $new_item ) );

					// update key for next language.
					$new_index = $new_index + 1;
				}
			}
		}

		// return resulting list of menu items.
		return $items;
	}
}
