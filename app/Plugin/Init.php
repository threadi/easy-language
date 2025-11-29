<?php
/**
 * File for initialization of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use easyLanguage\Plugin\Admin\Admin;

/**
 * Object to initialize this plugin.
 */
class Init {

	/**
	 * Instance of this object.
	 *
	 * @var ?Init
	 */
	private static ?Init $instance = null;

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Init {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		// initialize the settings.
		Settings::get_instance()->init();

		// run updates.
		Update::get_instance()->init();

		// initialize our installer.
		Installer::get_instance()->init();

		// loop through the active multilingual-plugins.
		foreach ( ThirdPartySupports::get_instance()->get_available_plugins() as $plugin_obj ) {
			$plugin_obj->init();
		}

		// initialize the setup.
		Setup::get_instance()->init();

		// initialize the admin tasks.
		Admin::get_instance()->init();

		// general hooks.
		add_action( 'cli_init', array( $this, 'cli' ) );
		add_action( 'update_option_easy_language_api', array( $this, 'update_easy_language_api' ), 10, 2 );
		add_action( 'admin_action_easy_language_clear_log', array( $this, 'clear_log_by_request' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_links' ), 10, 2 );

		// ajax-hooks.
		add_action( 'wp_ajax_easy_language_reset_intro', array( $this, 'reset_intro' ) );
		add_action( 'wp_ajax_easy_language_set_icon_for_language', array( $this, 'set_icon_for_language_via_ajax' ) );
	}

	/**
	 * Initialize our main CLI functions.
	 *
	 * @return void
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 */
	public function cli(): void {
		// add the main command.
		\WP_CLI::add_command( 'easy-language', 'easyLanguage\Plugin\Cli' );

		// add cli tasks of enabled APIs.
		foreach ( Apis::get_instance()->get_available_apis() as $api_obj ) {
			$api_obj->cli();
		}

		// add cli tasks for the supported multilingual plugins.
		foreach ( ThirdPartySupports::get_instance()->get_available_plugins() as $plugin_obj ) {
			// bail if plugin is not enabled.
			if ( ! $plugin_obj->is_active() ) {
				continue;
			}

			// add its WP CLI tasks.
			$plugin_obj->cli();
		}
	}

	/**
	 * Compile the capabilities.
	 *
	 * @param string $singular The singular name.
	 * @param string $plural The plural name.
	 *
	 * @return array<string,string>
	 */
	public function get_capabilities( string $singular, string $plural ): array {
		return array(
			'edit_post'              => 'edit_' . $singular,
			'read_post'              => 'read_' . $singular,
			'delete_post'            => 'delete_' . $singular,
			'edit_posts'             => 'edit_' . $plural,
			'edit_others_posts'      => 'edit_others_' . $plural,
			'publish_posts'          => 'publish_' . $plural,
			'read_private_posts'     => 'read_private_' . $plural,
			'delete_posts'           => 'delete_' . $plural,
			'delete_private_posts'   => 'delete_private_' . $plural,
			'delete_published_posts' => 'delete_published_' . $plural,
			'delete_others_posts'    => 'delete_others_' . $plural,
			'edit_private_posts'     => 'edit_private_' . $plural,
			'edit_published_posts'   => 'edit_published_' . $plural,
			'create_posts'           => 'add_' . $plural,
		);
	}

	/**
	 * Return list of internal singular-plural-names for post-types (not the translated names).
	 *
	 * @return array<string,string>
	 */
	public function get_post_type_names(): array {
		$post_type_names = array(
			'post' => 'posts',
			'page' => 'pages',
		);

		/**
		 * Filter the post type names.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<string,string> $post_type_names List of post type names.
		 */
		return apply_filters( 'easy_language_post_type_names', $post_type_names );
	}

	/**
	 * Return list of settings for supported post-types.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_post_type_settings(): array {
		$post_type_settings = array(
			'post' => array(
				'label_singular' => __( 'Post', 'easy-language' ),
				'label_plural'   => __( 'Posts', 'easy-language' ),
				'admin_edit_url' => add_query_arg(
					array(),
					admin_url() . 'edit.php'
				),
			),
			'page' => array(
				'label_singular' => __( 'Page', 'easy-language' ),
				'label_plural'   => __( 'Pages', 'easy-language' ),
				'admin_edit_url' => add_query_arg(
					array(
						'post_type' => 'page',
					),
					admin_url() . 'edit.php'
				),
			),
		);

		/**
		 * Filter the post type names.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<string,array<string,string>> $post_type_settings List of post type settings.
		 */
		return apply_filters( 'easy_language_post_type_settings', $post_type_settings );
	}

	/**
	 * If chosen api changes, cleanup the former API (e.g. let it delete its transients) and enable the new API
	 * with individual settings.
	 * Global settings after user-interaction are run via @function easy_language_admin_validate_chosen_api().
	 *
	 * @param string $old_value The name of the former API.
	 * @param string $new_value The name of the new API.
	 *
	 * @return void
	 */
	public function update_easy_language_api( string $old_value, string $new_value ): void {
		// run disable tasks on former API.
		$old_api_obj = Apis::get_instance()->get_api_by_name( $old_value );
		if ( $old_api_obj instanceof Api_Base ) {
			$old_api_obj->disable();
		}

		// run enable tasks on new API.
		$new_api_obj = Apis::get_instance()->get_api_by_name( $new_value );
		if ( $new_api_obj instanceof Api_Base ) {
			$new_api_obj->enable();

			// validate language support on API.
			Helper::validate_language_support_on_api( $new_api_obj );
		}

		// log this event.
		if ( empty( $old_value ) ) {
			/* translators: %1$s will be replaced by the new value. */
			Log::get_instance()->add_log( sprintf( __( 'API has been initialized with %1$s', 'easy-language' ), $new_value ), 'success' );
		} else {
			/* translators: %1$s will be replaced by the old value, %2$s by the new value. */
			Log::get_instance()->add_log( sprintf( __( 'API has been changed from %1$s to %2$s', 'easy-language' ), $old_value, $new_value ), 'success' );
		}
	}

	/**
	 * Reset the intro-settings.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function reset_intro(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-reset-intro-nonce', 'nonce' );

		// delete transient for step 1.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->get_transient_by_name( 'easy_language_intro_step_1' );
		$transient_obj->delete_dismiss();
		$transient_obj->delete();

		// delete option for step 2.
		delete_option( 'easy_language_intro_step_2' );

		// Log event.
		Log::get_instance()->add_log( __( 'Intro has been reset', 'easy-language' ), 'success' );

		// return ok.
		wp_send_json( array( 'result' => 'ok' ) );
	}

	/**
	 * Set icon for language via AJAX.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function set_icon_for_language_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-set-icon-for-language', 'nonce' );

		// get icon from request.
		$icon = isset( $_POST['icon'] ) ? absint( $_POST['icon'] ) : 0;

		// get language code from request.
		$language_code = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '';

		if ( $icon > 0 && ! empty( $language_code ) ) {
			// get actual assignments of the language.
			$attachment = Helper::get_attachment_by_language_code( $language_code );

			if ( ! $attachment ) {
				return;
			}

			// remove the language from this assignment.
			$assigned_languages = get_post_meta( $attachment->ID, 'easy_language_code', true );
			if ( ! empty( $assigned_languages[ $language_code ] ) ) {
				unset( $assigned_languages[ $language_code ] );
			}
			update_post_meta( $attachment->ID, 'easy_language_code', $assigned_languages );

			// add the language to the new attachment.
			$assigned_languages = get_post_meta( $icon, 'easy_language_code', true );
			if ( ! is_array( $assigned_languages ) ) {
				$assigned_languages = array();
			}
			$assigned_languages[ $language_code ] = 1;
			update_post_meta( $icon, 'easy_language_code', $assigned_languages );

			// reset image list in db.
			delete_option( 'easy_language_icons' );

			// Log event.
			/* translators: %1$s will be replaced by a language code. */
			Log::get_instance()->add_log( sprintf( __( 'New icon set for %1$s', 'easy-language' ), $language_code ), 'success' );
		}

		// create dialog.
		$dialog_config = array(
			'detail' => array(
				'title'   => __( 'Icon replaced', 'easy-language' ),
				'texts'   => array(
					'<p><strong>' . __( 'The icon has been replaced.', 'easy-language' ) . '</strong></p>',
				),
				'buttons' => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'easy-language' ),
					),
				),
			),
		);

		// return JSON with dialog.
		wp_send_json( $dialog_config );
	}

	/**
	 * Clear log by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function clear_log_by_request(): void {
		// check nonce.
		check_admin_referer( 'easy-language-clear-log', 'nonce' );

		// get db object.
		global $wpdb;

		// clear the log.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . Log::get_instance()->get_table_name() . '` WHERE 1 = %d', array( 1 ) ) );

		// redirect user back.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Add links in row meta.
	 *
	 * @param array<string,string> $links List of links.
	 * @param string               $file The requested plugin file name.
	 *
	 * @return array<string,string>
	 */
	public function add_row_meta_links( array $links, string $file ): array {
		// bail if this is not our plugin.
		if ( EASY_LANGUAGE !== WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $file ) {
			return $links;
		}

		// add our custom links.
		$row_meta = array(
			'support' => '<a href="' . esc_url( Helper::get_plugin_support_url() ) . '" target="_blank" title="' . esc_attr__( 'Support Forum', 'easy-language' ) . '">' . esc_html__( 'Support Forum', 'easy-language' ) . '</a>',
		);

		/**
		 * Filter the links in row meta of our plugin in plugin list.
		 *
		 * @since 2.6.0 Available since 2.6.0.
		 * @param array<string,string> $row_meta List of links.
		 */
		$row_meta = apply_filters( 'easy_language_plugin_row_meta', $row_meta );

		// return the resulting list of links.
		return array_merge( $links, $row_meta );
	}
}
