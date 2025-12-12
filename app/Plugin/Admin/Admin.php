<?php
/**
 * File for initialization the admin tasks of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin\Admin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use easyLanguage\EasyLanguage\Init;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\ThirdPartySupports;

/**
 * Object to initialize the admin tasks of this plugin.
 */
class Admin {

	/**
	 * Instance of this object.
	 *
	 * @var ?Admin
	 */
	private static ?Admin $instance = null;

	/**
	 * Constructor for Init-Handler.
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
	public static function get_instance(): Admin {
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
		add_action( 'init', array( $this, 'configure_transients' ), 5 );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_styles_and_js_admin' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_additional_scripts' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_dialog' ), PHP_INT_MAX );
		add_filter( 'admin_footer_text', array( $this, 'show_plugin_hint_in_footer' ), 0 );
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
		foreach ( ThirdPartySupports::get_instance()->get_available_plugins() as $plugin_obj ) {
			// bail if this is not a foreign plugin.
			if ( ! $plugin_obj->is_foreign_plugin() ) {
				continue;
			}

			// bail if the assigned plugin is not active.
			if ( ! $plugin_obj->is_active() ) {
				continue;
			}

			/**
			 * Show hint if this is a foreign plugin.
			 */
			// set the transient name.
			$transient_name = 'easy_language_plugin_' . $plugin_obj->get_name();

			// get transient-object for this plugin.
			$transient_obj = $transients_obj->get_transient_by_name( $transient_name );
			if ( $transient_obj->is_set() ) {
				// bail if this transient is already set.
				continue;
			}
			$transient_obj = $transients_obj->add();
			$transient_obj->set_name( $transient_name );
			$transient_obj->set_dismissible_days( 180 );

			/**
			 * Show hint if the foreign plugin does NOT support APIs.
			 */
			/* translators: %1$s will be replaced by the name of the multilingual-plugin */
			$message = sprintf( __( 'You have enabled the multilingual-plugin <strong>%1$s</strong>. We have added Easy and Plain language to this plugin as additional language.', 'easy-language' ), $plugin_obj->get_title() );
			if ( false === $plugin_obj->is_supporting_apis() ) {
				/* translators: %1$s will be replaced by the name of the multilingual-plugin */
				$message .= '<br><br>' . sprintf( __( 'Due to limitations of %1$s, it is unfortunately not possible for us to provide automatic simplification for easy or plain language. If you want to use this, you could use the <i>Easy Language</i> plugin alongside %1$s.', 'easy-language' ), esc_html( $plugin_obj->get_title() ), esc_html( $plugin_obj->get_title() ) );
			}
			$transient_obj->set_message( $message );
			$transient_obj->save();
		}

		// remove first step hint if API-settings are called.
		$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_intro_step_1' );
		$page          = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'options-general.php' === $pagenow && ! empty( $page ) && 'easy_language_settings' === $page && $transient_obj->is_set() ) {
			$transient_obj->delete();
		}
	}

	/**
	 * Add own CSS and JS for the backend.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function add_styles_and_js_admin(): void {
		// admin-specific styles.
		wp_enqueue_style(
			'easy-language-admin',
			plugin_dir_url( EASY_LANGUAGE ) . '/admin/style.css',
			array(),
			Helper::get_file_version( plugin_dir_path( EASY_LANGUAGE ) . '/admin/style.css' ),
		);

		// backend-JS.
		wp_enqueue_script(
			'easy-language-admin',
			plugins_url( '/admin/js.js', EASY_LANGUAGE ),
			array( 'jquery', 'easy-dialog', 'wp-i18n' ),
			Helper::get_file_version( plugin_dir_path( EASY_LANGUAGE ) . '/admin/js.js' ),
			true
		);

		// add php-vars to our backend-js-script.
		wp_localize_script(
			'easy-language-admin',
			'easyLanguageJsVars',
			array(
				'ajax_url'                    => admin_url( 'admin-ajax.php' ),
				'admin_start'                 => admin_url(),
				'dismiss_nonce'               => wp_create_nonce( 'easy-language-dismiss-nonce' ),
				'reset_intro_nonce'           => wp_create_nonce( 'easy-language-reset-intro-nonce' ),
				'run_delete_data_nonce'       => wp_create_nonce( 'easy-language-delete-data-nonce' ),
				'get_delete_data_nonce'       => wp_create_nonce( 'easy-language-get-delete-data-nonce' ),
				'set_icon_for_language_nonce' => wp_create_nonce( 'easy-language-set-icon-for-language' ),
				'review_url'                  => 'https://wordpress.org/plugins/easy-language/#reviews',
				'title_rate_us'               => __( 'Add your review for this plugin', 'easy-language' ),
				'dismiss_intro_nonce'         => wp_create_nonce( 'easy-language-dismiss-intro-step-2' ),
				/* translators: %1$s will be replaced by the path to the easy language icon */
				'intro_step_2'                => sprintf( __( '<p><img src="%1$s" alt="Easy Language Logo"><strong>Start to simplify texts in your pages.</strong></p><p>Simply click here and choose which page you want to translate.</p>', 'easy-language' ), Helper::get_plugin_url() . '/gfx/easy-language-icon.png' ),
			)
		);

		// add scripts and styles of enabled plugins.
		foreach ( ThirdPartySupports::get_instance()->get_available_plugins() as $plugin_obj ) {
			$plugin_obj->get_simplifications_scripts();
		}
	}

	/**
	 * Set the base configuration for each transient.
	 *
	 * @return void
	 */
	public function configure_transients(): void {
		$transients_obj = Transients::get_instance();
		$transients_obj->set_slug( 'easy_language' );
		$transients_obj->set_capability( 'manage_options' );
		$transients_obj->set_template( 'grouped.php' );
		$transients_obj->set_display_method( 'grouped' );
		$transients_obj->set_url( Helper::get_plugin_url() . '/app/Dependencies/easyTransientsForWordPress/' );
		$transients_obj->set_path( Helper::get_plugin_path() . '/app/Dependencies/easyTransientsForWordPress/' );
		$transients_obj->set_vendor_path( Helper::get_plugin_path() . 'vendor/' );
		$transients_obj->set_translations(
			array(
				/* translators: %1$d will be replaced by the days this message will be hidden. */
				'hide_message' => __( 'Hide this message for %1$d days.', 'easy-language' ),
				'dismiss'      => __( 'Dismiss', 'easy-language' ),
			)
		);
		$transients_obj->init();
	}

	/**
	 * Embed additional required scripts.
	 *
	 * @return void
	 */
	public function enqueue_additional_scripts(): void {
		// Enabled the pointer-scripts.
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
	}

	/**
	 * Add the dialog-scripts and -styles.
	 *
	 * @return void
	 */
	public function add_dialog(): void {
		// embed the necessary scripts for dialog.
		$path = Helper::get_plugin_path() . 'vendor/threadi/easy-dialog-for-wordpress/';
		$url  = Helper::get_plugin_url() . 'vendor/threadi/easy-dialog-for-wordpress/';

		// bail if path does not exist.
		if ( ! file_exists( $path ) ) {
			return;
		}

		// embed the dialog-components JS script.
		$script_asset_path = $path . 'build/index.asset.php';

		// bail if the script does not exist.
		if ( ! file_exists( $script_asset_path ) ) {
			return;
		}

		// embed script.
		$script_asset = require $script_asset_path;
		wp_enqueue_script(
			'easy-dialog',
			$url . 'build/index.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// embed the dialog-components CSS file.
		$admin_css      = $url . 'build/style-index.css';
		$admin_css_path = $path . 'build/style-index.css';
		wp_enqueue_style(
			'easy-dialog',
			$admin_css,
			array( 'wp-components' ),
			Helper::get_file_version( $admin_css_path )
		);
	}

	/**
	 * Show hint in footer in backend on all pages this plugin adds options.
	 *
	 * @param string $content The actual footer content.
	 *
	 * @return string
	 */
	public function show_plugin_hint_in_footer( string $content ): string {
		global $pagenow;

		// get the requested page.
		$page = (string) filter_input( INPUT_GET, 'page' );

		// if this page is the settings page, show a hint.
		if ( 'easy_language_settings' === $page ) {
			/* translators: %1$s will be replaced by the plugin name. */
			return $content . ' ' . sprintf( __( 'This page is provided by the plugin %1$s.', 'easy-language' ), '<em>' . Helper::get_plugin_name() . '</em>' );
		}

		// get requested the post-type.
		$post_type = (string) filter_input( INPUT_GET, 'post_type' );

		// if this page is a supported post-type listing, show a hint.
		if ( 'edit.php' === $pagenow || Init::get_instance()->is_post_type_supported( $post_type ) ) {
			/* translators: %1$s will be replaced by the plugin name. */
			return $content . ' ' . sprintf( __( 'This page is extended by the plugin %1$s.', 'easy-language' ), '<em>' . Helper::get_plugin_name() . '</em>' );
		}

		// return the footer content.
		return $content;
	}
}
