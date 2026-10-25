<?php
/**
 * File to handle support for the page builder "Page Builder by SiteOrigin" and the "SiteOrigin Widgets Bundle".
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// deny direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\Plugin\Helper;

/**
 * Object to handle support for SiteOrigin.
 *
 * Adds the language switcher as widget:
 * - If the SiteOrigin Widgets Bundle is active, as native SiteOrigin widget (with its form fields, sections and
 *   conditions). The widget is located in legacy-classes/SiteOrigin/ as the Widgets Bundle expects a folder per
 *   widget with a file named like the widget ID and a header with the widget data.
 * - Otherwise, if Page Builder by SiteOrigin is active, as simple classic WordPress widget.
 *
 * Both widgets use the same values, so existing widgets are mapped to the available widget if the Widgets Bundle
 * gets activated or deactivated.
 */
class SiteOrigin extends PageBuilder_Base {
	/**
	 * The ID of our widget in the Widgets Bundle (also the name of its folder and file).
	 */
	public const WIDGET_ID = 'easy-language-switcher';

	/**
	 * The class name of our widget for the Widgets Bundle (in legacy-classes/SiteOrigin/).
	 */
	public const WIDGET_CLASS = 'Easy_Language_SiteOrigin_Switcher_Widget';

	/**
	 * The class name of our classic widget, used if the Widgets Bundle is not active.
	 */
	public const PLAIN_WIDGET_CLASS = 'easyLanguage\PageBuilder\SiteOrigin\Switcher_Widget';

	/**
	 * The prefix for the keys of sections in the form of the widget of the Widgets Bundle.
	 */
	private const SECTION_PREFIX = 'section_';

	/**
	 * The marker for our object search in the autocomplete field of the Widgets Bundle.
	 */
	private const SEARCH_MARKER = 'easyLanguageObjects';

	/**
	 * Instance of this object.
	 *
	 * @var ?SiteOrigin
	 */
	private static ?SiteOrigin $instance = null;

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
	public static function get_instance(): SiteOrigin {
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
		// the widget for the Widgets Bundle.
		add_filter( 'siteorigin_widgets_widget_folders', array( $this, 'add_widget_folder' ) );
		add_filter( 'siteorigin_widgets_default_active', array( $this, 'set_widget_active_by_default' ) );
		add_filter( 'siteorigin_widgets_widget_banner', array( $this, 'set_widget_banner' ), 10, 2 );
		add_filter( 'siteorigin_widgets_block_icon', array( $this, 'set_block_icon' ), 10, 2 );
		add_action( 'wp_ajax_so_widgets_search_posts', array( $this, 'search_objects' ), 1 );
		add_action( 'siteorigin_widgets_render_preview_' . self::WIDGET_ID, array( $this, 'enqueue_switcher_style' ) );

		// the classic widget as fallback.
		add_action( 'widgets_init', array( $this, 'register_plain_widget' ) );

		// the integration in Page Builder.
		add_filter( 'siteorigin_panels_widget_class', array( $this, 'map_widget_class' ), 20 );
		add_filter( 'siteorigin_panels_widgets', array( $this, 'set_widget_icon' ), 20 );
		add_action( 'siteorigin_panel_enqueue_admin_styles', array( $this, 'enqueue_icon_style' ) );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_page_builder_active() || $this->is_widgets_bundle_active();
	}

	/**
	 * Return whether Page Builder by SiteOrigin is active.
	 *
	 * @return bool
	 */
	private function is_page_builder_active(): bool {
		return Helper::is_plugin_active( 'siteorigin-panels/siteorigin-panels.php' );
	}

	/**
	 * Return whether the SiteOrigin Widgets Bundle is active (and its widget base class is loaded).
	 *
	 * @return bool
	 */
	public function is_widgets_bundle_active(): bool {
		return class_exists( '\SiteOrigin_Widget' );
	}

	/**
	 * Return the path to the folder with our widgets for the Widgets Bundle.
	 *
	 * @return string
	 */
	private function get_widget_folder(): string {
		return wp_normalize_path( Helper::get_plugin_path() . 'legacy-classes/SiteOrigin/' );
	}

	/**
	 * Add the folder with our widget to the widget folders of the Widgets Bundle.
	 *
	 * @param array<int,string> $folders List of folders.
	 *
	 * @return array<int,string>
	 */
	public function add_widget_folder( array $folders ): array {
		$folders[] = $this->get_widget_folder();
		return $folders;
	}

	/**
	 * Activate our widget in the Widgets Bundle by default (it could be deactivated in its settings).
	 *
	 * @param array<string,bool> $widgets List of widgets which are active by default.
	 *
	 * @return array<string,bool>
	 */
	public function set_widget_active_by_default( array $widgets ): array {
		$widgets[ self::WIDGET_ID ] = true;
		return $widgets;
	}

	/**
	 * Set the banner of our widget in the widget list of the Widgets Bundle.
	 *
	 * @param string              $banner The URL of the banner.
	 * @param array<string,mixed> $widget The widget data.
	 *
	 * @return string
	 */
	public function set_widget_banner( string $banner, array $widget ): string {
		// bail if this is not our widget.
		if ( empty( $widget['ID'] ) || self::WIDGET_ID !== $widget['ID'] ) {
			return $banner;
		}

		// return our logo.
		return Helper::get_plugin_url() . 'gfx/easy-language-icon.svg';
	}

	/**
	 * Set the icon of our widget for the SiteOrigin widget blocks in the block editor.
	 *
	 * @param string $icon The SVG code of the icon.
	 * @param string $widget_file The path to the file of the widget.
	 *
	 * @return string
	 */
	public function set_block_icon( string $icon, string $widget_file ): string {
		// bail if this is not our widget.
		if ( wp_normalize_path( $widget_file ) !== $this->get_widget_folder() . self::WIDGET_ID . '/' . self::WIDGET_ID . '.php' ) {
			return $icon;
		}

		// return our logo.
		$svg = file_get_contents( Helper::get_plugin_path() . 'gfx/easy-language-icon.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_string( $svg ) ? $svg : $icon;
	}

	/**
	 * Register the classic widget if the Widgets Bundle is not active.
	 *
	 * Then Page Builder by SiteOrigin (and classic widget areas) could still use the language switcher.
	 *
	 * @return void
	 */
	public function register_plain_widget(): void {
		// bail if Page Builder is not active or the Widgets Bundle is active.
		if ( ! $this->is_page_builder_active() || $this->is_widgets_bundle_active() ) {
			return;
		}

		// register the widget.
		register_widget( self::PLAIN_WIDGET_CLASS );
	}

	/**
	 * Map our widget classes to the registered one.
	 *
	 * If the Widgets Bundle is activated or deactivated, the widgets in existing layouts use the other class.
	 *
	 * @param string $widget_class The class name of the widget.
	 *
	 * @return string
	 */
	public function map_widget_class( string $widget_class ): string {
		// map the classic widget to the widget of the Widgets Bundle.
		if ( ltrim( $widget_class, '\\' ) === self::PLAIN_WIDGET_CLASS && $this->is_widgets_bundle_active() ) {
			return self::WIDGET_CLASS;
		}

		// map the widget of the Widgets Bundle to the classic widget.
		if ( self::WIDGET_CLASS === $widget_class && ! class_exists( self::WIDGET_CLASS ) && $this->is_page_builder_active() ) {
			return self::PLAIN_WIDGET_CLASS;
		}

		// return the unchanged class name.
		return $widget_class;
	}

	/**
	 * Set the icon of our widgets in the dialog "Add Widget" of Page Builder.
	 *
	 * Page Builder only supports icon classes, so we use our class for the logo.
	 *
	 * @param array<string,array<string,mixed>> $widgets List of widgets.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function set_widget_icon( array $widgets ): array {
		foreach ( array( self::WIDGET_CLASS, self::PLAIN_WIDGET_CLASS ) as $widget_class ) {
			if ( isset( $widgets[ $widget_class ] ) ) {
				$widgets[ $widget_class ]['icon'] = 'easy-language-builder-icon';
			}
		}
		return $widgets;
	}

	/**
	 * Enqueue the style for the icon of our widgets in the editor of Page Builder.
	 *
	 * @return void
	 */
	public function enqueue_icon_style(): void {
		wp_enqueue_style( 'easy-language-builder-icon' );
	}

	/**
	 * Enqueue the style of the switcher for the preview of our widget in the Widgets Bundle.
	 *
	 * The preview is an AJAX request which only outputs the enqueued styles.
	 *
	 * @return void
	 */
	public function enqueue_switcher_style(): void {
		wp_enqueue_style( 'easy-language-switcher' );
	}

	/**
	 * Return the form fields of our widget in the format of the Widgets Bundle.
	 *
	 * The fields are generated from the central field definitions of the switcher. The fields of the first section
	 * are shown directly, the other sections are collapsible sections. Conditions are mapped to state emitters and
	 * state handlers of the Widgets Bundle.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_widget_form(): array {
		$switcher = Switcher::get_instance();
		$fields   = $switcher->get_widget_fields();
		$sections = $switcher->get_widget_sections();

		// get the fields, which control other fields.
		$controllers = array();
		foreach ( $fields as $field ) {
			if ( ! empty( $field['condition'] ) && is_array( $field['condition'] ) ) {
				foreach ( array_keys( $field['condition'] ) as $controller ) {
					$controllers[ $controller ] = true;
				}
			}
		}

		// collect the fields per section.
		$form = array();
		foreach ( $sections as $section => $title ) {
			$form[ $section ] = array();
		}
		foreach ( $fields as $name => $field ) {
			$option = array(
				'label'   => $field['label'],
				'default' => $field['default'],
			);

			// add the description.
			if ( ! empty( $field['description'] ) ) {
				$option['description'] = $field['description'];
			}

			// set the type specific settings.
			switch ( $field['type'] ) {
				case 'select':
					$option['type']    = 'select';
					$option['options'] = $field['options'];
					break;
				case 'switch':
					$option['type']    = 'checkbox';
					$option['default'] = (bool) $field['default'];
					break;
				case 'color':
					$option['type'] = 'color';
					break;
				case 'size':
					$option['type']  = 'measurement';
					$option['units'] = array_values( (array) $field['units'] );
					break;
				case 'objects':
					// use the autocomplete field with our own search for pages and terms (saved as comma separated list).
					$option['type']        = 'autocomplete';
					$option['source']      = 'posts';
					$option['multiple']    = true;
					$option['ajax_data']   = array( self::SEARCH_MARKER => 1 );
					$option['default']     = '';
					$option['description'] = trim( ( $field['description'] ?? '' ) . ' ' . __( 'Comma separated list of pages and terms, e.g. post:12,term:5.', 'easy-language' ) );
					break;
				default:
					$option['type'] = 'text';
					break;
			}

			// add the state emitter for fields which control other fields.
			if ( isset( $controllers[ $name ] ) ) {
				$option['state_emitter'] = $this->get_state_emitter( $name, $option['type'] );
			}

			// add the state handler for the conditions of this field.
			$state_handler = $this->get_state_handler( $field['condition'] ?? array(), $fields );
			if ( ! empty( $state_handler ) ) {
				$option['state_handler'] = $state_handler;
			}

			// add the field to its section.
			$section = isset( $field['section'] ) && is_string( $field['section'] ) && isset( $form[ $field['section'] ] ) ? $field['section'] : (string) array_key_first( $form );

			$form[ $section ][ $name ] = $option;
		}

		// build the resulting form.
		$result = array();
		$first  = true;
		foreach ( $form as $section => $section_fields ) {
			// bail if this section has no fields.
			if ( empty( $section_fields ) ) {
				continue;
			}

			// add the fields of the first section directly.
			if ( $first ) {
				$result = $section_fields;
				$first  = false;
				continue;
			}

			// add the other sections as collapsible sections (with prefix as the keys could be used by fields).
			$title              = $sections[ $section ];
			$section            = self::SECTION_PREFIX . $section;
			$result[ $section ] = array(
				'type'   => 'section',
				'label'  => $title,
				'hide'   => true,
				'fields' => $section_fields,
			);

			// show a section only if the conditions of all its fields match (if they are the same for all fields).
			$handlers = array_column( $section_fields, 'state_handler' );
			if ( count( $handlers ) === count( $section_fields ) && 1 === count( array_unique( array_map( 'wp_json_encode', $handlers ) ) ) ) {
				$result[ $section ]['state_handler'] = $handlers[0];
				foreach ( array_keys( $section_fields ) as $name ) {
					unset( $result[ $section ]['fields'][ $name ]['state_handler'] );
				}
			}
		}

		// return the resulting form.
		return $result;
	}

	/**
	 * Return the state emitter for a field which controls other fields.
	 *
	 * Select fields emit their value, checkboxes "on" or "off" (in the group named like the field).
	 *
	 * @param string $name The name of the field.
	 * @param string $type The type of the field in the Widgets Bundle.
	 *
	 * @return array<string,mixed>
	 */
	private function get_state_emitter( string $name, string $type ): array {
		if ( 'checkbox' === $type ) {
			return array(
				'callback' => 'conditional',
				'args'     => array(
					$name . '[on]: val',
					$name . '[off]: ! val',
				),
			);
		}
		return array(
			'callback' => 'select',
			'args'     => array( $name ),
		);
	}

	/**
	 * Return the state handler for the conditions of a field.
	 *
	 * @param array<string,mixed>               $condition The conditions as field name => allowed values.
	 * @param array<string,array<string,mixed>> $fields The definitions of all fields.
	 *
	 * @return array<string,array<int,string>>
	 */
	private function get_state_handler( array $condition, array $fields ): array {
		$handler = array();
		foreach ( $condition as $controller => $allowed_values ) {
			// get the states of the allowed values.
			$states = array();
			foreach ( (array) $allowed_values as $value ) {
				if ( isset( $fields[ $controller ]['type'] ) && 'switch' === $fields[ $controller ]['type'] ) {
					$value = in_array( $value, array( true, 1, '1', 'yes', 'on', 'true' ), true ) ? 'on' : 'off';
				}
				$states[] = (string) $value;
			}

			// bail if a state could not be used by the Widgets Bundle (like empty values).
			if ( empty( $states ) || count( $states ) !== count( (array) preg_grep( '/^[a-zA-Z0-9_-]+$/', $states ) ) ) {
				continue;
			}

			// add the handler: show the field for the allowed values, hide it otherwise.
			$handler[ $controller . '[' . implode( ',', array_unique( $states ) ) . ']' ] = array( 'show' );
			$handler[ '_else[' . $controller . ']' ]                                      = array( 'hide' );
		}
		return $handler;
	}

	/**
	 * Return the values of a widget instance as flat list keyed by the field names.
	 *
	 * The widget of the Widgets Bundle saves the fields of sections in sub arrays, the classic widget not.
	 *
	 * @param array<string,mixed> $instance The widget instance.
	 *
	 * @return array<string,mixed>
	 */
	public function get_values( array $instance ): array {
		$values = array();
		foreach ( array_keys( Switcher::get_instance()->get_widget_fields() ) as $name ) {
			if ( array_key_exists( $name, $instance ) ) {
				$values[ $name ] = $instance[ $name ];
				continue;
			}
			foreach ( $instance as $key => $value ) {
				if ( str_starts_with( (string) $key, self::SECTION_PREFIX ) && is_array( $value ) && array_key_exists( $name, $value ) ) {
					$values[ $name ] = $value[ $name ];
					break;
				}
			}
		}
		return $values;
	}

	/**
	 * Move flat values (e.g. from the classic widget) into the sections of the widget of the Widgets Bundle.
	 *
	 * @param array<string,mixed> $instance The widget instance.
	 *
	 * @return array<string,mixed>
	 */
	public function move_values_to_sections( array $instance ): array {
		foreach ( $this->get_widget_form() as $section => $field ) {
			// bail if this is not a section.
			if ( 'section' !== $field['type'] ) {
				continue;
			}

			// move the values of the fields of this section.
			foreach ( array_keys( $field['fields'] ) as $name ) {
				if ( ! array_key_exists( $name, $instance ) ) {
					continue;
				}
				if ( ! isset( $instance[ $section ] ) || ! is_array( $instance[ $section ] ) ) {
					$instance[ $section ] = array();
				}
				if ( ! array_key_exists( $name, $instance[ $section ] ) ) {
					$instance[ $section ][ $name ] = $instance[ $name ];
				}
				unset( $instance[ $name ] );
			}
		}
		return $instance;
	}

	/**
	 * Return whether the widget is rendered in an editor or preview of SiteOrigin.
	 *
	 * @param array<string,mixed> $instance The widget instance.
	 *
	 * @return bool
	 */
	public function is_preview( array $instance = array() ): bool {
		// previews of the Widgets Bundle (e.g. the preview button in the widget form) mark the instance.
		if ( ! empty( $instance['is_preview'] ) ) {
			return true;
		}

		// previews rendered by Page Builder (e.g. in the block editor) and the widget blocks of the Widgets Bundle.
		if ( ! empty( $GLOBALS['SITEORIGIN_PANELS_PREVIEW_RENDER'] ) || ! empty( $GLOBALS['SO_WIDGETS_BUNDLE_PREVIEW_RENDER'] ) ) {
			return true;
		}

		// bail if the user could not edit contents.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		// the live editor of Page Builder (loads the page in an iframe).
		if ( isset( $_GET['siteorigin_panels_live_editor'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		// AJAX requests of the editors which render widgets.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return wp_doing_ajax() && in_array( $action, array( 'so_panels_builder_content', 'so_panels_builder_content_json', 'so_panels_live_editor_preview', 'so_panels_layout_block_preview', 'so_widgets_preview' ), true );
	}

	/**
	 * Return the output of our widget.
	 *
	 * @param array<string,mixed> $instance The widget instance.
	 *
	 * @return string
	 */
	public function render( array $instance ): string {
		return Switcher::get_instance()->render_widget( $this->get_values( $instance ), 'siteorigin', $this->is_preview( $instance ) );
	}

	/**
	 * Search pages and terms for the autocomplete field of our widget in the Widgets Bundle.
	 *
	 * The autocomplete field only supports the sources "posts" and "terms" (via fixed AJAX actions), so we hook into
	 * the action for posts before the Widgets Bundle and only answer requests with our marker.
	 *
	 * @return void
	 */
	public function search_objects(): void {
		// bail if this is not a request of our field.
		if ( empty( $_GET[ self::SEARCH_MARKER ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// check the nonce of the Widgets Bundle.
		if ( ! check_ajax_referer( 'widgets_action', '_widgets_nonce', false ) ) {
			wp_send_json( array(), 403 );
		}

		// check the capability.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json( array(), 403 );
		}

		// get the search.
		$search = isset( $_GET['query'] ) ? sanitize_text_field( wp_unslash( $_GET['query'] ) ) : '';

		// collect the results (the label is used as HTML by the Widgets Bundle, the type shows the value to use).
		$results = array();
		foreach ( Switcher::get_instance()->search_objects( $search ) as $object ) {
			$results[] = array(
				'value' => $object['value'],
				'label' => esc_html( $object['label'] ),
				'type'  => esc_html( $object['value'] ),
			);
		}

		// return the results.
		wp_send_json( $results );
	}
}
