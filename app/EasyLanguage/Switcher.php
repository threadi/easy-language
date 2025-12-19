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

/**
 * Object which handles the language switcher.
 */
class Switcher {

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
		add_action( 'init', array( $this, 'wp_init' ) );
		add_action( 'init', array( $this, 'set_default' ), 20 );
		add_filter( 'wp_get_nav_menu_items', array( $this, 'set_menu_items' ) );
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'add_menu_options' ) );
		add_action( 'wp_update_nav_menu_item', array( $this, 'save_menu_options' ), 10, 2 );
		add_shortcode( 'easy-language-switcher', array( $this, 'use_shortcode' ) );
	}

	/**
	 * Run this on every request.
	 *
	 * @return void
	 */
	public function wp_init(): void {
		// bail if our cpt is already registered.
		if( post_type_exists( EASY_LANGUAGE_CPT_SWITCHER ) ) {
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
					'render_callback' => array( $this, 'get' ),
					'attributes'      => array(
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
					),
					'parent'          => array( 'core/navigation' ),
				)
			);

			// register our own switcher block outside of navigation in FSE-themes.
			register_block_type(
				Helper::get_plugin_path() . 'blocks/switcher/',
				array(
					'render_callback' => array( $this, 'get' ),
					'attributes'      => array(
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
					),
				)
			);
		}
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
		wp_insert_post( $query );

		// mark that we have done the work.
		update_option( 'easy_language_switcher_default', 1, true );
	}

	/**
	 * Return switcher for output in frontend via Block Editor/FSE.
	 *
	 * @param array<string,array<string>|bool> $attributes The settings as array.
	 *
	 * @return string
	 */
	public function get( array $attributes ): string {
		// get current object id.
		$object_id  = get_queried_object_id();
		$object_obj = get_queried_object();

		// if ID is 0, get the ID of the frontpage.
		if ( 0 === $object_id ) {
			if ( 'page' === get_option( 'show_on_front' ) ) {
				$object_id  = absint( get_option( 'page_on_front', 0 ) );
				$object_obj = get_post( $object_id );
			} elseif ( 'posts' === get_option( 'show_on_front' ) ) {
				$object_id  = absint( get_option( 'page_for_posts', 0 ) );
				$object_obj = get_post( $object_id );
			}
		}

		// get our own object for the requested object.
		$object = $this->init->get_object_by_wp_object( $object_obj, $object_id );
		if ( false === $object ) {
			return '';
		}

		// secure the simplified object.
		$simplified_obj = $object;

		// check if this object is a simplified object.
		if ( $object->is_simplified() ) {
			$object_id = $object->get_original_object_as_int();
			// get original object as base for the listing.
			$object = $this->init->get_object_by_wp_object( get_post( $object_id ), $object_id );
		}

		// bail if post type is not supported.
		if ( ! $object || ! $this->init->is_post_type_supported( $object->get_type() ) ) {
			return '';
		}

		// get active languages.
		$languages = array_merge( $object->get_language(), Languages::get_instance()->get_active_languages() );
		if ( empty( $languages ) ) {
			return '';
		}

		// get the active language marker from requested (potentiell simplified) object.
		$active_language = (string) array_key_first( $simplified_obj->get_language() );

		// remove actual language if set.
		if ( false !== $attributes['hide_actual_language'] ) {
			foreach ( $languages as $language_code => $settings ) {
				if ( 0 === strcasecmp( $active_language, $language_code ) ) {
					unset( $languages[ $language_code ] );
				}
			}
		}

		// get the permalinks.
		$permalink = get_permalink( $object->get_id() );

		// bail if permalink is unavailable.
		if ( ! $permalink ) {
			return '';
		}

		// variable to collect the output.
		$html = '';

		// loop through active languages and set their links.
		foreach ( $languages as $language_code => $settings ) {
			// get URL.
			$url = $object->get_language_specific_url( empty( $settings['url'] ) ? $permalink : $settings['url'], $language_code );

			// define title or icon.
			/* translators: %1$s will be replaced by the name of the language */
			$attribute_title = sprintf( __( 'Show this site in %1$s.', 'easy-language' ), $settings['label'] );
			$class           = 'easy-language-switcher-title';
			$title           = $settings['label'];
			if ( false !== $attributes['show_icons'] ) {
				$class = 'easy-language-switcher-icon';
				$title = Helper::get_icon_img_for_language_code( $language_code );
			}

			// add active marker.
			if ( $active_language === $language_code ) {
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
			$class = apply_filters( 'easy_language_switcher_entry_classes', $class, $language_code, $settings );

			// add item to menu.
			$html .= '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '" title="' . esc_attr( $attribute_title ) . '">' . wp_kses_post( $title ) . '</a>';
		}

		// return resulting links.
		return $html;
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

		// get current object id.
		$object_id = get_queried_object_id();

		// get our own object for the requested object.
		$object = $this->init->get_object_by_wp_object( get_queried_object(), $object_id );

		// bail if no object has been found.
		if ( false === $object ) {
			// remove our own language-switcher in this case.
			foreach ( $items as $key => $item ) {
				if ( EASY_LANGUAGE_CPT_SWITCHER === $item->object ) {
					unset( $items[ $key ] );
				}
			}

			// return resulting menu-item-list.
			return $items;
		}

		// check if this object is a simplified object.
		if ( $object->is_simplified() ) {
			$object_id = $object->get_original_object_as_int();
			// get new object as base for the listing.
			$object = $this->init->get_object_by_wp_object( get_post( $object_id ), $object_id );
			if ( false === $object ) {
				return $items;
			}
		}

		// bail if the post-type is not supported.
		if ( ! $this->init->is_post_type_supported( $object->get_type() ) ) {
			return $items;
		}

		// get active languages and bail if none available.
		$languages = array_merge( $object->get_language(), Languages::get_instance()->get_active_languages() );

		// bail if no active languages are set.
		if ( empty( $languages ) ) {
			return $items;
		}

		// get the permalinks.
		$permalink = get_permalink( $object->get_id() );

		// bail if permalink is unavailable.
		if ( ! $permalink ) {
			return $items;
		}

		// loop through the possible menu-items.
		foreach ( $items as $index => $item ) {
			if ( EASY_LANGUAGE_CPT_SWITCHER === $item->object ) {
				// get icon-setting.
				$show_icons = 'on' === get_post_meta( $item->ID, 'easy-language-icons', true );

				// get the post-item of this menu-item (this is not the page!).
				$post_id  = get_post_meta( $item->ID, '_menu_item_object_id', true );
				$post_obj = new Post_Object( $post_id );

				// do nothing if none could be loaded.
				if ( null === $post_id || EASY_LANGUAGE_CPT_SWITCHER !== $post_obj->get_type() ) {
					continue;
				}

				// get actual index.
				$new_index = $index;

				// language-counter.
				$language_counter = 0;

				// loop through the active languages and create a single menu item for each.
				foreach ( $languages as $language_code => $settings ) {
					// bail if not translated pages should not be linked.
					if ( 'hide_not_translated' === get_option( 'easy_language_switcher_link', '' )
						&& ! $object->is_simplified_in_language( $language_code )
						&& key( $object->get_language() ) !== $language_code ) {
						continue;
					}

					// bail if icon is requested, but not available for this language.
					if ( $show_icons && empty( $settings['icon'] ) ) {
						continue;
					}

					// create own object for this menu item.
					$new_item = clone $items[ $index ];
					// set title for menu item.
					$new_item->title = $show_icons ? Helper::get_icon_img_for_language_code( $language_code ) : $settings['label']; // @phpstan-ignore property.notFound
					// set the menu order.
					$new_item->menu_order = $items[ $index ]->menu_order + $language_counter; // @phpstan-ignore property.notFound
					// set URL for menu item.
					$new_item->url = $object->get_language_specific_url( empty( $settings['url'] ) ? $permalink : $settings['url'], $language_code ); // @phpstan-ignore property.notFound

					// merge items.
					array_splice( $items, $new_index, 1, array( $new_item ) );

					// update key for next language.
					++$new_index;
					++$language_counter;
				}
			}
		}

		// return resulting list of menu items.
		return $items;
	}

	/**
	 * Add options for language-switcher in classic menu.
	 *
	 * @param int $item_id The ID of the item.
	 *
	 * @return void
	 */
	public function add_menu_options( int $item_id ): void {
		// get value.
		$value = get_post_meta( $item_id, 'easy-language-icons', true );

		// set check-marker.
		$checked = '';
		if ( ! empty( $value ) && 'on' === $value ) {
			$checked = ' checked="checked"';
		}

		// output.
		?>
		<p class="field-easy-language-icons">
			<label for="edit-menu-item-easy-language-icons-<?php echo absint( $item_id ); ?>">
				<?php echo esc_html__( 'Show icons', 'easy-language' ); ?><br />
				<input type="checkbox" id="edit-menu-item-easy-language-icons-<?php echo absint( $item_id ); ?>" name="menu-item-easy-language-icons[<?php echo absint( $item_id ); ?>]"<?php echo esc_attr( $checked ); ?>>
			</label>
		</p>
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

		// check nonce.
		check_admin_referer( 'update-nav_menu', 'update-nav-menu-nonce' );

		// get value.
		$value = ! empty( $_POST['menu-item-easy-language-icons'][ $item_id ] ) ? sanitize_text_field( wp_unslash( $_POST['menu-item-easy-language-icons'][ $item_id ] ) ) : '';

		if ( ! empty( $value ) ) {
			update_post_meta( $item_id, 'easy-language-icons', sanitize_text_field( $value ) );
		} else {
			delete_post_meta( $item_id, 'easy-language-icons' );
		}
	}

	/**
	 * Use shortcode to output the language switcher.
	 *
	 * @param array<string,string> $attributes Attribute on the shortcode.
	 *
	 * @return string
	 */
	public function use_shortcode( array $attributes ): string {
		return wp_kses_post(
			$this->get(
				array(
					'hide_actual_language' => ! empty( $attributes['hide_actual_language'] ) && 'yes' === $attributes['hide_actual_language'],
					'show_icons'           => ! empty( $attributes['show_icons'] ) && 'yes' === $attributes['show_icons'],
				)
			)
		);
	}
}
