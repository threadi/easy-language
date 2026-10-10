<?php
/**
 * File for handling of the installation and activation of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Db;
use WP_Role;
use WP_Site;

/**
 * Installer-object.
 */
class Installer {

	/**
	 * Instance of this object.
	 *
	 * @var ?Installer
	 */
	private static ?Installer $instance = null;

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
	public static function get_instance(): Installer {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the installer-object on activation or deactivation of this plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		register_activation_hook( EASY_LANGUAGE, array( $this, 'activation' ) );
		register_deactivation_hook( EASY_LANGUAGE, array( $this, 'deactivation' ) );

		// run activation tasks for new sites in a multisite.
		add_action( 'wp_initialize_site', array( $this, 'initialize_site' ), 200 );
	}

	/**
	 * Get list of blogs in a multisite-installation.
	 *
	 * @return array<string,mixed>
	 */
	private function get_blogs(): array {
		if ( false === is_multisite() ) {
			return array();
		}

		// Get DB-connection.
		global $wpdb;

		// get blogs in this site-network.
		return $wpdb->get_results(
			"
            SELECT blog_id
            FROM {$wpdb->blogs}
            WHERE site_id = '{$wpdb->siteid}'
            AND spam = '0'
            AND deleted = '0'
            AND archived = '0'
        "
		);
	}

	/**
	 * Run this on activation of the plugin.
	 *
	 * @param bool $network_wide True if the plugin is activated network-wide in a multisite.
	 *
	 * @return void
	 */
	public function activation( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			// loop through the blogs.
			foreach ( $this->get_blogs() as $blog_id ) {
				// switch to the blog.
				switch_to_blog( $blog_id->blog_id );

				// run tasks for activation in this single blog.
				$this->activation_tasks();

				// switch back to original blog.
				restore_current_blog();
			}
		} else {
			// simply run the tasks on single-site-install or in the actual site.
			$this->activation_tasks();
		}
	}

	/**
	 * Run the activation tasks for a new site in a multisite, if this plugin is network-wide active.
	 *
	 * @param WP_Site $site The new site.
	 *
	 * @return void
	 */
	public function initialize_site( WP_Site $site ): void {
		// load necessary functions.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// bail if this plugin is not active network-wide.
		if ( ! is_plugin_active_for_network( plugin_basename( EASY_LANGUAGE ) ) ) {
			return;
		}

		// switch to the new site.
		switch_to_blog( absint( $site->blog_id ) );

		// run tasks for activation in this site.
		$this->activation_tasks();

		// switch back to the original site.
		restore_current_blog();
	}

	/**
	 * Define the tasks to run during activation.
	 *
	 * @return void
	 */
	private function activation_tasks(): void {
		// run normal plugin init.
		Init::get_instance()->init();

		// create the log table if it does not exist.
		Log::get_instance()->create_table();

		// create the API-log table if it does not exist.
		Log_Api::get_instance()->create_table();

		// create simplification tables if they do not exist.
		Db::get_instance()->create_table();

		// add the settings.
		Settings::get_instance()->add_the_settings();

		// set the actual plugin version if it does not exist (prevents running updates on new installations).
		if ( false === get_option( 'easyLanguageVersion' ) ) {
			add_option( 'easyLanguageVersion', EASY_LANGUAGE_VERSION, '', true );
		}

		// generate random-installation-hash if it does not already exist (will never be removed or changed).
		if ( ! get_option( EASY_LANGUAGE_HASH ) ) {
			update_option( EASY_LANGUAGE_HASH, hash( 'sha256', wp_rand() . get_option( 'home' ) ) );
		}

		// add user role for easy-language-translator if it does not exist.
		$translator_role = get_role( 'el_simplifier' );
		if ( null === $translator_role ) {
			$translator_role = add_role( 'el_simplifier', __( 'Editor for Easy Language', 'easy-language' ) );
		}
		if ( $translator_role instanceof WP_Role ) {
			$translator_role->add_cap( 'read' );
		}

		// get admin-role.
		$admin_role = get_role( 'administrator' );

		// loop through the capabilities and add them to the translator and administrator role.
		foreach ( Init::get_instance()->get_capabilities( 'el_simplifier', 'el_simplifier' ) as $capability ) {
			if ( $translator_role instanceof WP_Role ) {
				$translator_role->add_cap( $capability );
			}
			if ( $admin_role instanceof WP_Role ) {
				$admin_role->add_cap( $capability );
			}
		}

		// get the available APIs to call its install-routines.
		$apis_obj = Apis::get_instance();
		foreach ( $apis_obj->get_available_apis() as $api_obj ) {
			$api_obj->install();
		}

		// get the plugin-supports by call its install-routines.
		foreach ( ThirdPartySupports::get_instance()->get_available_plugins() as $plugin_obj ) {
			$plugin_obj->install();
		}

		// enable the default API to set the default settings.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( $api_obj instanceof Base ) {
			$api_obj->enable();
		}

		// initiate the settings.
		Settings::get_instance()->get_settings_object()->activation();
	}

	/**
	 * On plugin deactivation.
	 *
	 * @param bool $network_wide True if the plugin is deactivated network-wide in a multisite.
	 *
	 * @return void
	 */
	public function deactivation( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			// loop through the blogs.
			foreach ( $this->get_blogs() as $blog_id ) {
				// switch to the blog.
				switch_to_blog( $blog_id->blog_id );

				// run tasks for deactivation in this single blog.
				$this->deactivation_tasks();

				// switch back to original blog.
				restore_current_blog();
			}
		} else {
			// simply run the tasks on single-site-install or in the actual site.
			$this->deactivation_tasks();
		}
	}

	/**
	 * Define the tasks to run during deactivation.
	 *
	 * @return void
	 */
	private function deactivation_tasks(): void {
		// get the active APIs to call its install-routines.
		$apis_obj = Apis::get_instance();
		foreach ( $apis_obj->get_available_apis() as $api_obj ) {
			$api_obj->deactivate();
		}

		// get the plugin-supports by call its install-routines.
		foreach ( ThirdPartySupports::get_instance()->get_available_plugins() as $plugin_obj ) {
			$plugin_obj->deactivation();
		}

		/**
		 * Remove our capabilities from other roles.
		 */
		global $wp_roles;
		foreach ( $wp_roles->roles as $role_name => $settings ) {
			// get the role by its name.
			$role = get_role( $role_name );

			// bail if role could not be found.
			if ( ! $role instanceof WP_Role ) {
				continue;
			}

			// remove our caps from this role.
			foreach ( Init::get_instance()->get_capabilities( 'el_simplifier', 'el_simplifier' ) as $capability ) {
				$role->remove_cap( $capability );
			}
		}
	}
}
