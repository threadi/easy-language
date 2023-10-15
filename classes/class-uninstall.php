<?php
/**
 * File for handling of uninstallation of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

use easyLanguage\Multilingual_plugins\Easy_Language\Db;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uninstall-object.
 */
class Uninstall {

	/**
	 * Instance of this object.
	 *
	 * @var ?Uninstall
	 */
	private static ?Uninstall $instance = null;

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {
	}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Uninstall {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Get list of blogs in a multisite-installation.
	 *
	 * @return array
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
	 * Run uninstall routines for this plugin.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( is_multisite() ) {
			// get original blog id.
			$original_blog_id = get_current_blog_id();

			// loop through the blogs.
			foreach ( $this->get_blogs() as $blog_id ) {
				// switch to the blog.
				switch_to_blog( $blog_id->blog_id );

				// run tasks for uninstalling in this blog.
				$this->tasks();
			}

			// switch back to original blog.
			switch_to_blog( $original_blog_id );
		} else {
			// simply run the tasks on single-site-install.
			$this->tasks();
		}
	}

	/**
	 * The tasks to run during uninstallation.
	 *
	 * @return void
	 */
	private function tasks(): void {
		/**
		 * Call uninstall-routines of the available APIs.
		 */
		foreach ( Apis::get_instance()->get_available_apis() as $apis ) {
			$apis->uninstall();
		}

		/**
		 * Call uninstall-routines of the available plugins.
		 */
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			$plugin_obj->uninstall();
		}

		/**
		 * Delete managed transients.
		 */
		$transients_obj = Transients::get_instance();
		foreach ( $transients_obj->get_transients() as $transient ) {
			$transient->delete();
		}

		/**
		 * Delete manual transients.
		 */
		foreach ( EASY_LANGUAGE_TRANSIENTS as $transient_name => $settings ) {
			delete_transient( $transient_name );
		}

		/**
		 * Delete DB-tables.
		 */
		Log_Api::get_instance()->delete_table();
		Db::get_instance()->delete_tables();

		/**
		 * Remove settings.
		 */
		foreach ( $this->get_options() as $option_name ) {
			delete_option( $option_name );
		}

		/**
		 * Remove our role.
		 */
		remove_role( 'el_translator' );

		/**
		 * Remove our capabilities from other roles.
		 */
		global $wp_roles;
		foreach ( $wp_roles->roles as $role_name => $settings ) {
			$role = get_role( $role_name );
			foreach ( Init::get_instance()->get_capabilities( 'el_translate', 'el_translate' ) as $capability ) {
				$role->remove_cap( $capability );
			}
		}
	}

	/**
	 * Return list of options this plugin is using.
	 *
	 * @return array
	 */
	private function get_options(): array {
		return array(
			'easy_language_debug_mode',
			'easy_language_api',
			'easy_language_log_max_age',
			'easy_language_api_timeout',
		);
	}
}
