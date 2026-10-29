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
	 * Marker whether the tables, which are shared by all sites of a multisite, should be removed.
	 *
	 * @var bool
	 */
	private bool $drop_shared_tables = true;

	/**
	 * Return the list of blog IDs in a multisite-installation.
	 *
	 * Includes archived, spammed or deleted sites, as they also contain data of this plugin.
	 *
	 * @return array<int>
	 */
	private function get_blogs(): array {
		if ( false === is_multisite() ) {
			return array();
		}

		// get all sites in this network.
		return array_map(
			'absint',
			get_sites(
				array(
					'fields'     => 'ids',
					'number'     => 0,
					'network_id' => get_current_network_id(),
				)
			)
		);
	}

	/**
	 * Return whether the tables, which are shared by all sites of a multisite, will be removed by the actual run.
	 *
	 * This is false during the reset of a single site in a multisite: the shared tables are kept, only the data
	 * of the actual site is removed.
	 *
	 * @return bool
	 */
	public function is_dropping_shared_tables(): bool {
		return $this->drop_shared_tables;
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
		// remove the shared tables only on single-site-install or during the uninstallation in the complete network.
		$this->drop_shared_tables = ! is_multisite() || $network_wide;

		if ( $network_wide && is_multisite() ) {
			// loop through the blogs.
			foreach ( $this->get_blogs() as $blog_id ) {
				// switch to the blog.
				switch_to_blog( $blog_id );

				// run tasks for uninstalling in this blog.
				$this->deactivation_tasks();

				// switch back to the original blog.
				restore_current_blog();
			}
		} else {
			// simply run the tasks on single-site-install or in the actual site.
			$this->deactivation_tasks();
		}

		// reset the marker.
		$this->drop_shared_tables = true;
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
				switch_to_blog( $blog_id );
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

		// get the IDs of the texts used in this site, before their connections are removed (only needed if the shared tables are kept).
		$text_ids = $this->drop_shared_tables ? array() : $this->get_text_ids_of_site();

		// get all images which have assigned 'easy_language_icon' post-meta and delete them.
		$query                            = array(
			'posts_per_page' => -1,
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the simplified objects are linked to their originals via post meta, there is no other way to query them
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
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the simplified objects are linked to their originals via post meta, there is no other way to query them
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
		 * Delete DB-tables or, if they are used by other sites of a multisite, only the data of the actual site.
		 */
		if ( $this->drop_shared_tables ) {
			Log::get_instance()->delete_table();
			Log_Api::get_instance()->delete_table();
			Db::get_instance()->delete_tables();
		} else {
			$this->delete_data_of_site( $text_ids );
		}

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
	 * Return the IDs of the texts used in the actual site.
	 *
	 * @return array<int>
	 */
	private function get_text_ids_of_site(): array {
		global $wpdb;

		// bail if the tables for texts do not exist.
		$db_obj = Db::get_instance();
		if ( ! $db_obj->tables_exist() ) {
			return array();
		}

		// get the IDs of the texts used in this site.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table of this plugin, no WP API available; the result must be up to date during the reset
		return array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT `oid` FROM %i WHERE `blog_id` = %d', array( $db_obj->get_table_name_originals_objects(), get_current_blog_id() ) ) ) );
	}

	/**
	 * Delete the data of the actual site from the tables, which are shared by all sites of a multisite.
	 *
	 * Texts and simplifications are only removed if they are not used by another site.
	 *
	 * @param array<int> $text_ids The IDs of the texts which have been used by this site.
	 *
	 * @return void
	 */
	private function delete_data_of_site( array $text_ids ): void {
		global $wpdb;

		// delete the log entries of this site.
		Log::get_instance()->delete_entries_of_site();
		Log_Api::get_instance()->delete_entries_of_site();

		// bail if the tables for texts do not exist.
		$db_obj = Db::get_instance();
		if ( ! $db_obj->tables_exist() ) {
			return;
		}

		// delete the connections between texts and objects of this site.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table of this plugin, no WP API available
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE `blog_id` = %d', array( $db_obj->get_table_name_originals_objects(), get_current_blog_id() ) ) );

		// delete the texts and their simplifications, which are not used by any other site.
		foreach ( $text_ids as $text_id ) {
			// bail if this text is used by another site.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table of this plugin, no WP API available; the result must be up to date during the reset
			if ( absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `oid` = %d', array( $db_obj->get_table_name_originals_objects(), $text_id ) ) ) ) > 0 ) {
				continue;
			}

			// delete the text and its simplifications.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table of this plugin, no WP API available
			$wpdb->delete( $db_obj->get_table_name_originals(), array( 'id' => $text_id ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table of this plugin, no WP API available
			$wpdb->delete( $db_obj->get_table_name_simplifications(), array( 'oid' => $text_id ) );
		}

		// the counts of texts have been changed.
		$db_obj->clear_process_counts();
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
			\easyLanguage\EasyLanguage\Abilities::OPTION_ENABLED,
			\easyLanguage\EasyLanguage\Abilities::OPTION_MODE,
		);
	}
}
