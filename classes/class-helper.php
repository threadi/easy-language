<?php
/**
 * File with general helper-functions for this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;
use WP_Admin_Bar;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper for this plugin.
 */
class Helper {
	/**
	 * Checks whether a given plugin is active.
	 *
	 * Used because WP's own function is_plugin_active() is not accessible everywhere.
	 *
	 * @param string $plugin Path to the plugin.
	 * @return bool
	 */
	public static function is_plugin_active( string $plugin ): bool {
		return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true );
	}

	/**
	 * Return the lokal URL of our plugin.
	 *
	 * @return string
	 */
	public static function get_plugin_url(): string {
		return plugin_dir_url( EASY_LANGUAGE );
	}

	/**
	 * Check if WP CLI is used for actual request.
	 *
	 * @return bool
	 */
	public static function is_cli(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Return the current frontend language.
	 *
	 * @return string
	 */
	public static function get_current_language(): string {
		$lang = get_query_var( 'lang' );

		// if there is no language in query-var, use the WP-locale.
		if ( empty( $lang ) ) {
			$lang = helper::get_wp_lang();
		}

		// return the current language as language-code (e.g. "de_de").
		return $lang;
	}

	/**
	 * Format a given datetime with WP-settings and functions.
	 *
	 * @param string $date
	 *
	 * @return string
	 */
	public static function get_format_date_time( string $date ): string {
		$dt = get_date_from_gmt( $date );
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $dt ) );
	}

	/**
	 * Return the settings-page-URL.
	 *
	 * @return string
	 */
	public static function get_settings_page_url(): string {
		return add_query_arg( array( 'page' => 'easy_language_settings' ), admin_url() . 'options-general.php' );
	}

	/**
	 * Return the API-logs-URL.
	 *
	 * @return string
	 */
	public static function get_api_logs_page_url(): string {
		return add_query_arg( array( 'page' => 'easy_language_settings', 'tab' => 'api_logs' ), admin_url() . 'options-general.php' );
	}

	/**
	 * Return the active Wordpress-language depending on our own support.
	 * If language is unknown for our plugin, use english.
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public static function get_wp_lang(): string {
		$wpLang = get_bloginfo( 'language' );

		/**
		 * Consider the main language set in Polylang for the web page
		 */
		if ( self::is_plugin_active( 'polylang/polylang.php' ) && function_exists( 'pll_default_language' ) ) {
			$wpLang = pll_default_language();
		}

		/**
		 * Consider the main language set in WPML for the web page
		 */
		if ( self::is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			$wpLang = apply_filters( 'wpml_default_language', null );
		}

		// if language not set, use default language.
		if ( empty( $wpLang ) ) {
			$wpLang = EASY_LANGUAGE_LANGUAGE_EMERGENCY;
		}

		// return language in format ab_CD (e.g. en_US).
		return str_replace( '-', '_', $wpLang );
	}

	/**
	 * Copy complete taxonomy and post-meta from old to new cpt entry.
	 *
	 * @param int $old_id ID of original post-id.
	 * @param int $new_id ID of new post-id.
	 * @return void
	 */
	public static function copy_cpt( int $old_id, int $new_id ): void {
		// prevent copy to its own.
		if ( $old_id === $new_id ) {
			return;
		}

		// copy all assigned taxonomies.
		$taxonomies = get_object_taxonomies( get_post_type( $old_id ) ); // returns array of taxonomy names for post type, ex array("category", "post_tag");
		if ( $taxonomies ) {
			foreach ( $taxonomies as $taxonomy ) {
				$post_terms = wp_get_object_terms( $old_id, $taxonomy, array( 'fields' => 'slugs' ) );
				wp_set_object_terms( $new_id, $post_terms, $taxonomy );
			}
		}

		// duplicate all post meta.
		$post_meta = get_post_meta( $old_id );
		if ( is_array( $post_meta ) ) {
			foreach ( $post_meta as $meta_key => $meta_values ) {
				// ignore some keys.
				if ( in_array( $meta_key, array( '_edit_lock', '_edit_last', '_wp_page_template', '_wp_old_slug' ), true ) ) {
					continue;
				}

				// loop through the values of the key and add them to the new id.
				foreach ( $meta_values as $meta_value ) {
					add_post_meta( $new_id, $meta_key, wp_slash( maybe_unserialize( $meta_value ) ) );
				}
			}
		}
	}

	/**
	 * Return plugin path.
	 *
	 * @return string
	 */
	public static function get_plugin_path(): string {
		return plugin_dir_path( EASY_LANGUAGE );
	}

	/**
	 * Return the name of this plugin.
	 *
	 * @return string
	 */
	public static function get_plugin_name(): string {
		$plugin_data = get_plugin_data( EASY_LANGUAGE );
		return $plugin_data['Name'];
	}

	/**
	 * Check if Settings-Errors-entry already exists in array.
	 *
	 * @param $entry
	 * @param $array
	 * @return false
	 */
	public static function check_if_setting_error_entry_exists_in_array( $entry, $array ): bool {
		foreach ( $array as $item ) {
			if ( $item['setting'] == $entry ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validate multiple checkboxes.
	 *
	 * @param $values
	 * @return array|null
	 */
	public static function settings_validate_multiple_checkboxes( $values ): ?array {
		$filter = current_filter();
		if ( ! empty( $filter ) ) {
			$filter = str_replace( 'sanitize_option_', '', $filter );
			if ( empty( $values ) && ! empty( $_REQUEST[ $filter . '_ro' ] ) ) {
				$values = (array) $_REQUEST[ $filter . '_ro' ];
			}
		}
		return $values;
	}

	/**
	 * Validate multiple text fields.
	 *
	 * @param $values
	 *
	 * @return array|null
	 * @noinspection PhpUnused
	 */
	public static function settings_validate_multiple_text_field( $values ): ?array {
		$filter = current_filter();
		if ( ! empty( $filter ) ) {
			$filter = str_replace( 'sanitize_option_', '', $filter );
			if ( empty( $values ) && ! empty( $_REQUEST[ $filter . '_ro' ] ) ) {
				$values = (array) $_REQUEST[ $filter . '_ro' ];
			}
		}

		// return resulting values as array.
		return $values;
	}

	/**
	 * Validate multiple radio-fields.
	 *
	 * @param $values
	 * @return string|null
	 */
	public static function settings_validate_multiple_radios( $values ): ?string {
		$filter = current_filter();
		if ( ! empty( $filter ) ) {
			$filter = str_replace( 'sanitize_option_', '', $filter );
			if ( empty( $values ) && ! empty( $_REQUEST[ $filter . '_ro' ] ) ) {
				$values = sanitize_text_field( $_REQUEST[ $filter . '_ro' ] );
			}
		}
		return $values;
	}

	/**
	 * Validate select field.
	 *
	 * @param $value
	 * @return string|null
	 */
	public static function settings_validate_select_field( $value ): ?string {
		$filter = current_filter();
		if ( ! empty( $filter ) ) {
			$filter = str_replace( 'sanitize_option_', '', $filter );
			if ( empty( $values ) && ! empty( $_REQUEST[ $filter . '_ro' ] ) ) {
				$value = sanitize_text_field( $_REQUEST[ $filter . '_ro' ] );
			}
		}
		return $value;
	}

	/**
	 * Return the URL for plugin-support.
	 *
	 * @return string
	 */
	public static function get_support_url(): string {
		return 'https://laolaweb.com/kontakt/';
	}

	/**
	 * Return the URL for laolaweb.com where we can order the Pro-Version.
	 *
	 * @return string
	 */
	public static function get_pro_url(): string {
		return 'https://laolaweb.com/plugins/leichte-sprache-fuer-wordpress/';
	}

	/**
	 * Get attachment-object by given filename.
	 *
	 * @param string $post_name The searched filename.
	 *
	 * @return WP_Post|false
	 */
	public static function get_attachment_by_post_name( string $post_name ): WP_Post|false {
		$query           = array(
			'posts_per_page' => 1,
			'post_type'      => 'attachment',
			'name'           => trim( $post_name ),
			'post_status' => 'inherit',
		);
		$get_attachment = new WP_Query( $query );

		if ( ! $get_attachment || ! isset( $get_attachment->posts, $get_attachment->posts[0] ) ) {
			return false;
		}

		// return resulting object.
		return $get_attachment->posts[0];
	}

	/**
	 * Get attachment by given language-code via post-meta of the attachment.
	 *
	 * @param string $language_code The search language code.
	 *
	 * @return WP_Post|false
	 */
	public static function get_attachment_by_language_code( string $language_code ): WP_Post|false {
		$query           = array(
			'posts_per_page' => 1,
			'post_type'      => 'attachment',
			'post_status' => 'inherit',
			'meta_query' => array(
				array(
					'key' => 'easy_language_code',
					'value' => trim($language_code),
					'compare' => 'LIKE'
				)
			)
		);
		$get_attachment = new WP_Query( $query );

		if ( 0 === $get_attachment->post_count ) {
			return false;
		}

		// return resulting object.
		return $get_attachment->posts[0];
	}

	/**
	 * Get img for given language code.
	 *
	 * @param string $language_code The language we search an icon for.
	 *
	 * @return string
	 */
	public static function get_icon_img_for_language_code( string $language_code ): string {
		// get list of images from db.
		$images = (array)get_option( 'easy_language_icons', array() );

		// return image if it is in list.
		if( !empty($images[$language_code]) ) {
			return ' '.wp_kses_post($images[$language_code]);
		}

		// get it from media library if requested language is not in list.
		$attachment = self::get_attachment_by_language_code($language_code);
		if( false !== $attachment ) {
			// get image.
			$image = wp_get_attachment_image($attachment->ID);

			// add it to list in DB.
			$images[$language_code] = $image;
			update_option( 'easy_language_icons', $images );

			// return image.
			return ' ' . wp_kses_post($image);
		}

		return '';
	}

	/**
	 * Set intro step 1.
	 *
	 * @return void
	 */
	public static function set_intro_step1(): void {
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_dismissible_days( 2 );
		$transient_obj->set_name( 'easy_language_intro_step_1' );
		/* translators: %1$s will be replaced by the URL for api settings-URL. */
		$transient_obj->set_message( sprintf( __( '<strong>You have installed Easy Language - nice and thank you!</strong> Now check the <a href="%1$s">API-settings</a>, select one and start simplifying the texts in your website in easy or plain language.', 'easy-language' ), esc_url( Helper::get_settings_page_url() ) ) );
		$transient_obj->set_type( 'hint' );
		$transient_obj->save();
	}

	/**
	 * Generate the admin menu bar for supported languages.
	 *
	 * @param string $id The ID of the object.
	 * @param WP_Admin_Bar $admin_bar The admin-bar-object.
	 * @param array $target_languages The array of languages.
	 * @param Post_Object $object The object itself.
	 * @param string $object_type_name The type name of the object.
	 *
	 * @return void
	 */
	public static function generate_admin_bar_language_menu( string $id, WP_Admin_Bar $admin_bar, array $target_languages, Post_Object $object, string $object_type_name ): void {
		foreach ( $target_languages as $language_code => $target_language ) {
			/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the language-name */
			$title = sprintf(__( 'Show this %1$s in %2$s ', 'easy-language' ), esc_html($object_type_name), esc_html($target_language['label']) );

			// check if this object is already translated in this language.
			if ( false !== $object->is_simplified_in_language( $language_code ) ) {
				// generate link-target to default editor with language-marker.
				$simplified_post_object = new Post_Object( $object->get_simplification_in_language( $language_code ) );
				$url                    = $simplified_post_object->get_page_builder()->get_edit_link();
			} else {
				// create link to generate a new simplification for this object.
				$url = $object->get_simplification_link( $language_code );
				/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the language-name */
				$title = sprintf(__( 'Create a simplification of this %1$s in %2$s ', 'easy-language' ), esc_html($object_type_name), esc_html($target_language['label']) );
			}

			// add language as possible translation-target.
			if ( ! empty( $url ) ) {
				$admin_bar->add_menu(
					array(
						'id'     => $id . '-' . $language_code,
						'parent' => $id,
						'title'  => $target_language['label'],
						'href'   => $url,
						'meta'   => array(
							'title' => esc_html($title),
						),
					)
				);
			}
		}
	}

	/**
	 * Return URL path to icon by given language_code.
	 *
	 * @param string $language_code The language code we search the icon for.
	 *
	 * @return string
	 */
	public static function get_icon_path_for_language_code( string $language_code ): string {
		$attachment = self::get_attachment_by_language_code( $language_code );
		if( false !== $attachment ) {
			return wp_get_attachment_image_url( $attachment->ID );
		}
		return '';
	}

	/**
	 * Get object by given id and type.
	 *
	 * @param int $object_id The object-ID.
	 * @param string $object_type The object-type (optional).
	 * @return object|false
	 */
	public static function get_object( int $object_id, string $object_type = '' ): object|false {
		return apply_filters( 'easy_language_get_object', false, $object_id, $object_type );
	}
}
