<?php
/**
 * File for handling of uninstallation of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Dependencies\easyTransientsForWordPress\Transient;
use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use easyLanguage\EasyLanguage\Db;
use easyLanguage\Plugin\Admin\Admin;
use WP_Query;
use WP_Role;

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
	public static function get_instance(): Uninstall {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the list of blogs in a multisite-installation.
	 *
	 * @return array<array<string>>
	 */
	private function get_blogs(): array {
		if ( false === is_multisite() ) {
			return array();
		}

		// Get DB-connection.
		global $wpdb;

		// get blogs in this site-network.
		return $wpdb->get_results(
			'
            SELECT blog_id
            FROM ' . $wpdb->blogs . "
            WHERE site_id = '" . $wpdb->siteid . "'
            AND spam = '0'
            AND deleted = '0'
            AND archived = '0'
            ",
			ARRAY_A
		);
	}

	/**
	 * Run uninstall routines for this plugin.
	 *
	 * Without $network_wide only the actual site will be cleaned up (e.g. for a reset of the plugin).
	 *
	 * @param bool $network_wide True to run the tasks in all sites of a multisite (only during uninstallation).
	 *
	 * @return void
	 */
	public function run( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			// loop through the blogs.
			foreach ( $this->get_blogs() as $blog_id ) {
				// switch to the blog.
				switch_to_blog( absint( $blog_id['blog_id'] ) );

				// run tasks for uninstalling in this blog.
				$this->deactivation_tasks();

				// switch back to the original blog.
				restore_current_blog();
			}
		} else {
			// simply run the tasks on single-site-install or in the actual site.
			$this->deactivation_tasks();
		}
	}

	/**
	 * Remove the encryption key of this plugin from its places.
	 *
	 * In a multisite the key could be saved per site (e.g. in the database), so this runs in each site.
	 * Only use this during the uninstallation, after all encrypted values have been deleted.
	 *
	 * @return void
	 */
	public function remove_encryption_keys(): void {
		if ( is_multisite() ) {
			foreach ( $this->get_blogs() as $blog_id ) {
				switch_to_blog( absint( $blog_id['blog_id'] ) );
				Crypt::get_instance()->uninstall();
				restore_current_blog();
			}
			return;
		}

		Crypt::get_instance()->uninstall();
	}

	/**
	 * Initialize the components of this plugin which are necessary for the uninstallation.
	 *
	 * Replaces the global "init"-action, which would also run the hooks of other plugins.
	 *
	 * @return void
	 */
	private function init_plugin(): void {
		// bail if the settings are already initialized (e.g. during a reset of the active plugin).
		if ( Settings::get_instance()->get_settings_object()->has_settings() ) {
			return;
		}

		// initialize the plugin.
		Init::get_instance()->init();

		// configure the transients.
		Admin::get_instance()->configure_transients();

		// add the main settings.
		Settings::get_instance()->add_the_settings();

		// add the settings of the APIs.
		foreach ( Apis::get_instance()->get_available_apis() as $api_obj ) {
			$api_obj->add_settings();
		}

		// add the general settings for simplifications.
		\easyLanguage\EasyLanguage\Init::get_instance()->add_settings();
	}

	/**
	 * The tasks to run during uninstallation.
	 *
	 * @return void
	 */
	private function deactivation_tasks(): void {
		// initialize the necessary components of the plugin.
		$this->init_plugin();

		// enable the settings.
		Settings::get_instance()->get_settings_object()->activation();

		// get all images which have assigned 'easy_language_icon' post-meta and delete them.
		$query                            = array(
			'posts_per_page' => -1,
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => 'easy_language_icon',
					'value'   => '1',
					'compare' => '=',
				),
			),
			'fields'         => 'ids',
		);
		$attachments_with_language_marker = new WP_Query( $query );
		foreach ( $attachments_with_language_marker->posts as $attachment_id ) {
			wp_delete_attachment( absint( $attachment_id ) );
		}

		// delete all post-meta 'easy_language_code' on images.
		$query                            = array(
			'posts_per_page' => -1,
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => 'easy_language_code',
					'compare' => 'EXIST',
				),
			),
			'fields'         => 'ids',
		);
		$attachments_with_language_marker = new WP_Query( $query );
		foreach ( $attachments_with_language_marker->posts as $attachment_id ) {
			delete_post_meta( absint( $attachment_id ), 'easy_language_code' );
		}

		/**
		 * Call uninstall-routines of all available APIs.
		 */
		foreach ( Apis::get_instance()->get_available_apis() as $apis ) {
			$apis->uninstall();
		}

		/**
		 * Call uninstall-routines of all available plugins.
		 */
		foreach ( ThirdPartySupports::get_instance()->get_available_plugins() as $plugin_obj ) {
			$plugin_obj->uninstall();
		}

		/**
		 * Delete managed transients.
		 */
		foreach ( Transients::get_instance()->get_transients( false, true ) as $transient_obj ) {
			// bail if the object is not ours.
			if ( ! $transient_obj instanceof Transient ) { // @phpstan-ignore instanceof.alwaysTrue
				continue;
			}

			// delete transient-data.
			$transient_obj->delete();
			$transient_obj->delete_dismiss();
		}

		/**
		 * Delete manuel transients.
		 */
		foreach ( EASY_LANGUAGE_TRANSIENTS as $transient_name => $settings ) {
			delete_transient( $transient_name );
		}

		/**
		 * Delete DB-tables.
		 */
		Log::get_instance()->delete_table();
		Log_Api::get_instance()->delete_table();
		Db::get_instance()->delete_tables();

		// remove setup-options.
		Setup::get_instance()->uninstall();

		// delete all settings.
		Settings::get_instance()->get_settings_object()->delete_settings();

		/**
		 * Remove custom settings.
		 */
		foreach ( $this->get_options() as $option_name ) {
			delete_option( $option_name );
		}

		// remove our transients.
		foreach ( array( 'easy_language_capito_teams', 'easy_language_capito_teams_failed', 'easy_language_simplification_process_counts', 'easy_language_refresh_rewrite_rules' ) as $transient_name ) {
			delete_transient( $transient_name );
		}

		/**
		 * Remove our role.
		 */
		remove_role( 'el_simplifier' );

		/**
		 * Remove our capabilities from other roles.
		 */
		global $wp_roles;
		foreach ( $wp_roles->roles as $role_name => $settings ) {
			// get the role object by its name.
			$role = get_role( $role_name );

			// bail if the role could not be loaded.
			if ( ! $role instanceof WP_Role ) {
				continue;
			}

			// remove our own caps from this role.
			foreach ( Init::get_instance()->get_capabilities( 'el_simplifier', 'el_simplifier' ) as $capability ) {
				$role->remove_cap( $capability );
			}
		}
	}

	/**
	 * Return list of options this plugin is using.
	 *
	 * @return array<string>
	 */
	private function get_options(): array {
		return array(
			'easyLanguageVersion',
			'easy_language_update_lock',
			'easy_language_wpml_languages_marker',
			'easy_language_sublanguage_languages_marker',
		);
	}
}
