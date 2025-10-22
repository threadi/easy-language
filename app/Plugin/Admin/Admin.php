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
		add_action( 'admin_enqueue_scripts', array( $this, 'add_styles_and_js_admin' ), PHP_INT_MAX );
		add_filter( 'plugin_action_links_' . plugin_basename( EASY_LANGUAGE ), array( $this, 'add_setting_link' ) );
		add_action( 'init', array( $this, 'configure_transients' ), 5 );
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
				'title_rate_us'               => __( 'Rate us', 'easy-language' ),
				'pro_url'                     => esc_url( Helper::get_pro_url() ),
				'title_get_pro'               => __( 'Get Pro', 'easy-language' ),
			)
		);

		// add scripts and styles of enabled plugins.
		foreach ( ThirdPartySupports::get_instance()->get_available_plugins() as $plugin_obj ) {
			$plugin_obj->get_simplifications_scripts();
		}
	}

	/**
	 * Add the link to plugin-settings in the plugin-list.
	 *
	 * @param array<int,string> $links List of links to show in plugin-list on this specific plugin.
	 * @return array<int,string>
	 * @noinspection PhpUnused
	 */
	public function add_setting_link( array $links ): array {
		// adds the link to the list of links.
		$links[] = "<a href='" . esc_url( Helper::get_settings_page_url() ) . "'>" . __( 'Settings', 'easy-language' ) . '</a>';

		// return the resulting list of links.
		return $links;
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
}
