<?php
/**
 * File for handling of the installation and activation of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use WP_Role;

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
	 * @return void
	 */
	public function activation(): void {
		if ( is_multisite() ) {
			// get original blog id.
			$original_blog_id = get_current_blog_id();

			// loop through the blogs.
			foreach ( $this->get_blogs() as $blog_id ) {
				// switch to the blog.
				switch_to_blog( $blog_id->blog_id );

				// run tasks for activation in this single blog.
				$this->activation_tasks();
			}

			// switch back to original blog.
			switch_to_blog( $original_blog_id );
		} else {
			// simply run the tasks on single-site-install.
			$this->activation_tasks();
		}
	}

	/**
	 * Define the tasks to run during activation.
	 *
	 * @return void
	 */
	private function activation_tasks(): void {
		// create log table if it does not exist.
		Log::get_instance()->create_table();

		// create API-log table if it does not exist.
		Log_Api::get_instance()->create_table();

		// create simplification tables if they do not exist.
		Db::get_instance()->create_table();

		// set debug-mode to disabled per default.
		if ( false === get_option( 'easy_language_debug_mode' ) ) {
			update_option( 'easy_language_debug_mode', '0' );
		}

		// set api to empty.
		if ( ! get_option( 'easy_language_api' ) ) {
			update_option( 'easy_language_api', '' );
		}

		// set max age for log entries.
		if ( ! get_option( 'easy_language_log_max_age' ) ) {
			update_option( 'easy_language_log_max_age', '50' );
		}

		// set API timeout.
		if ( ! get_option( 'easy_language_api_timeout' ) ) {
			update_option( 'easy_language_api_timeout', 60 );
		}

		// set text-simplification limit per process.
		if ( false === get_option( 'easy_language_api_text_limit_per_process' ) ) {
			update_option( 'easy_language_api_text_limit_per_process', 1 );
		}

		// enable deletion of unused simplification.
		if ( false === get_option( 'easy_language_delete_unused_simplifications' ) ) {
			update_option( 'easy_language_delete_unused_simplifications', 1 );
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

		// loop through the capabilities and add them to the translator.
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
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			$plugin_obj->install();
		}

		// enable the default API for a moment for set the default settings.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( $api_obj instanceof Base ) {
			$api_obj->enable();
		}
	}

	/**
	 * On plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivation(): void {
		if ( is_multisite() ) {
			// get original blog id.
			$original_blog_id = get_current_blog_id();

			// loop through the blogs.
			foreach ( $this->get_blogs() as $blog_id ) {
				// switch to the blog.
				switch_to_blog( $blog_id->blog_id );

				// run tasks for deactivation in this single blog.
				$this->deactivation_tasks();
			}

			// switch back to original blog.
			switch_to_blog( $original_blog_id );
		} else {
			// simply run the tasks on single-site-install.
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
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
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
