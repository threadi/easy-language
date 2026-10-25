<?php
/**
 * File to handle support for the page builder Brizy.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// deny direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\PageBuilder\Brizy\Extension;
use easyLanguage\PageBuilder\Brizy\Placeholder_Provider;
use easyLanguage\Plugin\Helper;
use WP_Post;
use WP_Query;

/**
 * Object to handle support for the page builder Brizy.
 *
 * The language switcher is added as Brizy "third party element" (see brizy/src/index.js). The element outputs
 * the Brizy placeholder "{{easy_language_switcher ...}}", which is replaced by Brizy with the output of our
 * central switcher via our own placeholder provider.
 */
class Brizy extends PageBuilder_Base {
	/**
	 * The name of our Brizy placeholder.
	 */
	public const PLACEHOLDER = 'easy_language_switcher';

	/**
	 * Instance of this object.
	 *
	 * @var ?Brizy
	 */
	private static ?Brizy $instance = null;

	/**
	 * The extension object for Brizy.
	 *
	 * @var Extension|null
	 */
	private ?Extension $extension = null;

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
	public static function get_instance(): Brizy {
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
		add_filter( 'brizy_extensions', array( $this, 'add_extension' ) );
		add_filter( 'brizy_providers', array( $this, 'add_placeholder_provider' ) );
		add_action( 'brizy_editor_enqueue_scripts', array( $this, 'add_editor_data' ) );
		add_filter( 'brizy_editor_page_context', array( $this, 'add_editor_page_style' ) );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return defined( 'BRIZY_VERSION' ) || Helper::is_plugin_active( 'brizy/brizy.php' );
	}

	/**
	 * Return our extension object for Brizy.
	 *
	 * @return Extension
	 */
	private function get_extension(): Extension {
		if ( is_null( $this->extension ) ) {
			$this->extension = new Extension();
		}
		return $this->extension;
	}

	/**
	 * Add our extension with the language switcher element to Brizy.
	 *
	 * @param mixed $extensions List of extensions.
	 *
	 * @return mixed
	 */
	public function add_extension( mixed $extensions ): mixed {
		// bail if Brizy is not active or the list is not an array.
		if ( ! $this->is_active() || ! is_array( $extensions ) || ! interface_exists( 'Brizy_Editor_ThirdParty_ExtensionInterface' ) ) {
			return $extensions;
		}

		// add our extension.
		$extensions[] = $this->get_extension();

		// return the resulting list.
		return $extensions;
	}

	/**
	 * Add our placeholder provider to Brizy.
	 *
	 * @param mixed $providers List of placeholder providers.
	 *
	 * @return mixed
	 */
	public function add_placeholder_provider( mixed $providers ): mixed {
		// bail if Brizy is not active or the list is not an array.
		if ( ! $this->is_active() || ! is_array( $providers ) || ! class_exists( 'Brizy_Content_Providers_AbstractProvider' ) ) {
			return $providers;
		}

		// add our provider.
		$providers[] = new Placeholder_Provider();

		// return the resulting list.
		return $providers;
	}

	/**
	 * Add our style for the icon of the element to the page of the Brizy editor.
	 *
	 * The list of elements is shown on the outer page of the editor, which only loads the styles of this context.
	 *
	 * @param mixed $context The context of the editor page.
	 *
	 * @return mixed
	 */
	public function add_editor_page_style( mixed $context ): mixed {
		// bail if Brizy is not active or the context is unexpected.
		if ( ! $this->is_active() || ! is_array( $context ) || ! isset( $context['styles'] ) || ! is_array( $context['styles'] ) ) {
			return $context;
		}

		// add our style.
		$file                = Helper::get_plugin_path() . 'brizy/editor-icon.css';
		$context['styles'][] = add_query_arg( array( 'ver' => Helper::get_file_version( $file ) ), Helper::get_plugin_url() . 'brizy/editor-icon.css' );

		// return the resulting context.
		return $context;
	}

	/**
	 * Add the settings of the switcher for our element to the Brizy editor.
	 *
	 * @return void
	 */
	public function add_editor_data(): void {
		// bail if Brizy is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		// get the handle of our script (Brizy creates it from the name of the extension and the script URL).
		$scripts = $this->get_extension()->getEditorScripts();
		$handle  = 'brizy-extension-' . md5( $this->get_extension()->getName() . reset( $scripts ) );

		// bail if our script is not enqueued.
		if ( ! wp_script_is( $handle ) ) {
			return;
		}

		// get the switcher object.
		$switcher = Switcher::get_instance();

		// get the placeholders of the design fields (used as default for colors, as Brizy has no empty colors).
		$design_fields = $switcher->get_toggle_design_fields();

		// collect the fields as list (to keep the order of fields and options in JS).
		$fields = array();
		foreach ( $switcher->get_widget_fields() as $name => $field ) {
			$entry = array(
				'name'        => $name,
				'type'        => $field['type'],
				'label'       => $field['label'],
				'description' => $field['description'] ?? '',
				'placeholder' => $field['placeholder'] ?? ( $design_fields[ $name ]['placeholder'] ?? '' ),
				'section'     => $field['section'] ?? 'settings',
				'default'     => $field['default'] ?? '',
				'units'       => $field['units'] ?? array(),
				'condition'   => $field['condition'] ?? array(),
				'choices'     => array(),
			);
			foreach ( (array) ( $field['options'] ?? array() ) as $value => $label ) {
				$entry['choices'][] = array(
					'title' => $label,
					'value' => (string) $value,
				);
			}
			$fields[] = $entry;
		}

		// collect the data.
		$data = array(
			'title'    => __( 'Language Switcher', 'easy-language' ),
			'category' => __( 'Easy Language', 'easy-language' ),
			'keywords' => implode( ', ', array( __( 'Easy Language', 'easy-language' ), __( 'Menu', 'easy-language' ) ) ),
			'fields'   => $fields,
			'sections' => $switcher->get_widget_sections(),
			'restUrl'  => rest_url( 'easy-language/v1/switcher-objects' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		);

		// add them to our script.
		wp_add_inline_script( $handle, 'window.easyLanguageBrizy = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	/**
	 * Return the output of our placeholder.
	 *
	 * The attributes are the values of the Brizy element (without our prefix), already URL-decoded by Brizy.
	 *
	 * @param array<string,mixed> $attributes The attributes of the placeholder.
	 *
	 * @return string
	 */
	public function render_placeholder( array $attributes ): string {
		global $wp_query, $post;

		// check if this is the preview in the Brizy editor (Brizy loads it via AJAX).
		$preview = ! empty( $attributes['preview'] ) && ( wp_doing_ajax() || Helper::is_rest_request() ) && current_user_can( 'edit_posts' );

		// use the edited post as requested object for the preview (Brizy only sets the global post in its AJAX request).
		$restore = false;
		$object  = null;
		$id      = null;
		if ( $preview && $wp_query instanceof WP_Query && $post instanceof WP_Post ) {
			$object                      = $wp_query->queried_object;
			$id                          = $wp_query->queried_object_id;
			$wp_query->queried_object    = $post;
			$wp_query->queried_object_id = $post->ID;
			$restore                     = true;
		}

		// get the output.
		$html = Switcher::get_instance()->render_widget( $this->get_values( $attributes ), 'brizy', $preview );

		// restore the requested object.
		if ( $restore && $wp_query instanceof WP_Query ) { // @phpstan-ignore instanceof.alwaysTrue
			$wp_query->queried_object    = $object;
			$wp_query->queried_object_id = $id;
		}

		// return the output.
		return $html;
	}

	/**
	 * Convert the values of the Brizy element to the values of the switcher.
	 *
	 * Brizy saves colors in separate values for the hex code and the opacity, the selected objects as JSON.
	 *
	 * @param array<string,mixed> $attributes The values of the Brizy element.
	 *
	 * @return array<string,mixed>
	 */
	public function get_values( array $attributes ): array {
		$values = array();
		foreach ( Switcher::get_instance()->get_widget_fields() as $name => $field ) {
			// convert colors.
			if ( 'color' === $field['type'] ) {
				$color = $this->get_color( $attributes[ $name . 'Hex' ] ?? '', $attributes[ $name . 'Opacity' ] ?? 1 );
				if ( '' !== $color ) {
					$values[ $name ] = $color;
				}
				continue;
			}

			// bail if no value is set.
			if ( ! isset( $attributes[ $name ] ) ) {
				continue;
			}

			// convert lists of objects.
			$value = $attributes[ $name ];
			if ( 'objects' === $field['type'] && is_string( $value ) && str_starts_with( trim( $value ), '[' ) ) {
				$value = json_decode( $value, true );
				$value = is_array( $value ) ? $value : array();
			}

			// add the value.
			$values[ $name ] = $value;
		}

		// return the resulting values.
		return $values;
	}

	/**
	 * Return a CSS color from the hex code and the opacity of a Brizy color picker.
	 *
	 * @param mixed $hex The hex code.
	 * @param mixed $opacity The opacity (0 to 1).
	 *
	 * @return string
	 */
	private function get_color( mixed $hex, mixed $opacity ): string {
		// bail if the hex code is not valid.
		if ( ! is_string( $hex ) || ! preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', trim( $hex ), $matches ) ) {
			return '';
		}

		// get the full hex code.
		$code = strtolower( $matches[1] );
		if ( 3 === strlen( $code ) ) {
			$code = $code[0] . $code[0] . $code[1] . $code[1] . $code[2] . $code[2];
		}

		// return the hex code if the color is not transparent.
		$opacity = is_numeric( $opacity ) ? max( 0.0, min( 1.0, (float) $opacity ) ) : 1.0;
		if ( $opacity >= 1.0 ) {
			return '#' . $code;
		}

		// return the color with opacity.
		return sprintf( 'rgba(%d, %d, %d, %s)', hexdec( substr( $code, 0, 2 ) ), hexdec( substr( $code, 2, 2 ) ), hexdec( substr( $code, 4, 2 ) ), round( $opacity, 2 ) );
	}
}
