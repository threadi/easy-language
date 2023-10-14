<?php
/**
 * File for initialisation of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
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
	}

	/**
	 * Process on every load.
	 *
	 * @return void
	 */
	public function plugin_init(): void {
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
					$message .= '<br><br>' . sprintf( __( 'Due to limitations of this plugin, it is unfortunately not possible for us to provide automatic translation for plain language. If you want to use this, deactivate %1$s and use only the <i>Easy Language</i> plugin for this.', 'easy-language' ), $plugin_obj->get_title() );
				}
				$transient_obj->set_message( $message );
				$transient_obj->save();
			}
		}

		// remove first step hint if API-settings are called.
		$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_intro_step_1' );
		if( 'options-general.php' === $pagenow && !empty($_GET['page']) && 'easy_language_settings' === $_GET['page'] && $transient_obj->is_set() ) {
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
			'read'                   => 'read',
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
				'label_plural' => __( 'posts', 'easy-language' ),
				'admin_edit_url' => add_query_arg(
					array(),
					admin_url().'edit.php'
				)
			),
			'page' => array(
				'label_singular' => __( 'page', 'easy-language' ),
				'label_plural' => __( 'pages', 'easy-language' ),
				'admin_edit_url' => add_query_arg(
					array(
						'post_type' => 'page'
					),
					admin_url().'edit.php'
				)
			),
		);
	}

	/**
	 * If chosen api changes, cleanup the former api (e.g. let it delete its transients).
	 *
	 * @param $old_value
	 * @param $value
	 *
	 * @return void
	 */
	public function update_easy_language_api( $old_value, $value ): void {
		$api_obj = Apis::get_instance()->get_api_by_name( $old_value );
		if ( false !== $api_obj ) {
			$api_obj->disable();
		}
	}
}
