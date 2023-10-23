<?php
/**
 * File for initialisation of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Init the plugin.
 * This object is minify on purpose as the main functions are handled in own objects
 * depending on WordPress-settings.
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
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		// include all API-files.
		foreach ( glob( plugin_dir_path( EASY_LANGUAGE ) . 'inc/apis/*.php' ) as $filename ) {
			require_once $filename;
		}

		// include all settings-files.
		foreach ( glob( plugin_dir_path( EASY_LANGUAGE ) . 'inc/multilingual-plugins/*.php' ) as $filename ) {
			require_once $filename;
		}

		// get our own installer-handler.
		$installer_obj = Install::get_instance();
		$installer_obj->init();

		// initialize the multilingual-plugins.
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			$plugin_obj->init();
		}

		// general hooks.
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'init', array( $this, 'plugin_init' ) );
		add_action( 'cli_init', array( $this, 'cli' ) );
		add_action( 'update_option_easy_language_api', array( $this, 'update_easy_language_api' ), 10, 2 );

		// ajax-hooks.
		add_action( 'wp_ajax_easy_language_reset_intro', array( $this, 'reset_intro' ) );
	}

	/**
	 * Process on every load.
	 *
	 * @return void
	 */
	public function plugin_init(): void {
		// TODO remove if languages are in WP-repo
		load_plugin_textdomain( 'easy-language', false, dirname( plugin_basename( EASY_LANGUAGE ) ) . '/languages' );
	}

	/**
	 * Initialize our main CLI-functions.
	 *
	 * @return void
	 * @noinspection PhpUndefinedClassInspection
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 */
	public function cli(): void {
		\WP_CLI::add_command( 'easy-language', 'easyLanguage\Cli' );

		// add cli tasks of enabled APIs.
		foreach ( Apis::get_instance()->get_available_apis() as $api_obj ) {
			$api_obj->cli();
		}

		// add cli tasks for the supported multilingual plugins.
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			$plugin_obj->cli();
		}
	}

	/**
	 * Run on every admin load.
	 *
	 * @return void
	 */
	public function admin_init(): void {
		global $pagenow;

		// get all target languages of all APIs for import the assigned language-images.
		$languages = array();
		foreach( APIS::get_instance()->get_available_apis() as $api_object ) {
			$languages = array_merge( $languages, $api_object->get_supported_target_languages() );
		}
		foreach( $languages as $language_code => $settings ) {
			if( !empty($settings['img']) ) {
				// get path.
				$img_path = trailingslashit(Helper::get_plugin_path()).'gfx/'.$settings['img'];

				// check if file exists there.
				if( file_exists( $img_path ) ) {
					// check if file exist in db.
					$attachment = Helper::get_attachment_by_post_name( pathinfo($img_path, PATHINFO_FILENAME) );

					// if an attachment for this file does not exist, check also for postmeta.
					if( false === $attachment ) {
						$attachment = Helper::get_attachment_by_language_code( $language_code );
					}

					// if no attachment could be found, add it.
					if( false === $attachment ) {
						// Prepare an array of post data for the attachment.
						$attachment = array(
							'name'     => basename( $settings['img'] ),
							'tmp_name' => $img_path,
						);

						// Insert the attachment by prevent removing the original file and get its attachment ID.
						add_filter( 'pre_move_uploaded_file', '__return_false' );
						$attachment_id = media_handle_sideload( $attachment );
						remove_filter( 'pre_move_uploaded_file', '__return_false' );

						// get attachment as object.
						if( absint($attachment_id) > 0 ) {
							$attachment = get_post( $attachment_id );
						}
					}

					if( false !== $attachment ) {
						// get actual list of languages mapped to this icon.
						$language_list = get_post_meta( $attachment->ID, 'easy_language_code', true );
						if( !is_array($language_list) ) {
							$language_list = array();
						}

						if( empty($language_list[$language_code]) ) {
							// add language-code to this icon.
							$language_list[$language_code] = 1;
							update_post_meta( $attachment->ID, 'easy_language_code', $language_list );
						}
					}
				}
			}
		}

		// get transients objects-object.
		$transients_obj = Transients::get_instance();

		// loop through the active multilingual-plugins.
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			/**
			 * Show hint if this is a foreign plugin.
			 */
			if ( $plugin_obj->is_foreign_plugin() ) {
				// set transient name.
				$transient_name = 'easy_language_plugin_' . $plugin_obj->get_name();

				// get transient-object for this plugin
				$transient_obj = $transients_obj->get_transient_by_name( $transient_name );
				if ( $transient_obj->is_set() ) {
					// bail if this transient is already set.
					continue;
				}
				$transient_obj = $transients_obj->add();
				$transient_obj->set_name( $transient_name );
				$transient_obj->set_dismissible_days( 180 );

				/**
				 * Show hint if the foreign plugin does NOT support apis.
				 */
				/* translators: %1$s will be replaced by the name of the multilingual-plugin */
				$message = sprintf( __( 'You have enabled the multilingual-plugin <strong>%1$s</strong>. We have added Easy and Plain language to this plugin as additional language.', 'easy-language' ), $plugin_obj->get_title() );
				if ( false === $plugin_obj->is_supporting_apis() ) {
					/* translators: %1$s will be replaced by the name of the multilingual-plugin */
					$message .= '<br><br>' . sprintf( __( 'Due to limitations of this plugin, it is unfortunately not possible for us to provide automatic simplification for easy or plain language. If you want to use this, deactivate %1$s and use only the <i>Easy Language</i> plugin for this.', 'easy-language' ), $plugin_obj->get_title() );
				}
				$transient_obj->set_message( $message );
				$transient_obj->save();
			}
		}

		// remove first step hint if API-settings are called.
		$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_intro_step_1' );
		if ( 'options-general.php' === $pagenow && ! empty( $_GET['page'] ) && 'easy_language_settings' === $_GET['page'] && $transient_obj->is_set() ) {
			$transient_obj->delete();
		}
	}

	/**
	 * Compile the capabilities.
	 *
	 * @param string $singular
	 * @param string $plural
	 *
	 * @return string[]
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
	 * @return array
	 */
	public function get_post_type_names(): array {
		return array(
			'post' => 'posts',
			'page' => 'pages',
		);
	}

	/**
	 * Return list of settings for supported post-types.
	 *
	 * @return array
	 */
	public function get_post_type_settings(): array {
		return array(
			'post' => array(
				'label_singular' => __( 'post', 'easy-language' ),
				'label_plural'   => __( 'posts', 'easy-language' ),
				'admin_edit_url' => add_query_arg(
					array(),
					admin_url() . 'edit.php'
				),
			),
			'page' => array(
				'label_singular' => __( 'page', 'easy-language' ),
				'label_plural'   => __( 'pages', 'easy-language' ),
				'admin_edit_url' => add_query_arg(
					array(
						'post_type' => 'page',
					),
					admin_url() . 'edit.php'
				),
			),
		);
	}

	/**
	 * If chosen api changes, cleanup the former API (e.g. let it delete its transients) and enable the new API
	 * with individual settings.
	 * Global settings are run via @function easy_language_admin_validate_chosen_api().
	 *
	 * @param string $old_value The name of the former API.
	 * @param string $value The name of the new API.
	 *
	 * @return void
	 */
	public function update_easy_language_api( string $old_value, string $value ): void {
		// run disable tasks on former API.
		$old_api_obj = Apis::get_instance()->get_api_by_name( $old_value );
		if ( false !== $old_api_obj ) {
			$old_api_obj->disable();
		}

		// run enable tasks on new API.
		$new_api_obj = Apis::get_instance()->get_api_by_name( $value );
		if ( false !== $new_api_obj ) {
			$new_api_obj->enable();
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
		$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_intro_step_1' );
		$transient_obj->delete();

		// delete option for step 2.
		delete_option( 'easy_language_intro_step_2' );

		wp_send_json( array('result' => 'ok') );

		// return nothing.
		wp_die();
	}
}
