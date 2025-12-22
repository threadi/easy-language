<?php
/**
 * File with general helper-functions for this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Dependencies\easySettingsForWordPress\Page;
use easyLanguage\Dependencies\easySettingsForWordPress\Section;
use easyLanguage\Dependencies\easySettingsForWordPress\Tab;
use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use easyLanguage\EasyLanguage\Objects;
use easyLanguage\EasyLanguage\Parser_Base;
use easyLanguage\EasyLanguage\Post_Object;
use WP_Admin_Bar;
use WP_Error;
use WP_Post;
use WP_Post_Type;
use WP_Query;
use WP_Rewrite;

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
			$lang = self::get_wp_lang();
		}

		// return the current language as language-code (e.g. "de_de").
		return $lang;
	}

	/**
	 * Format a given datetime with WP-settings and functions.
	 *
	 * @param string $date The date as string.
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
		return add_query_arg(
			array(
				'page' => 'easy_language_settings',
				'tab'  => 'api_logs',
			),
			admin_url() . 'options-general.php'
		);
	}

	/**
	 * Return the active Wordpress-language.
	 *
	 * @return string The language in locale-format (e.g. "ab_CD").
	 */
	public static function get_wp_lang(): string {
		$wp_language = get_locale();

		/**
		 * Consider the main language set in Polylang for the web page.
		 */
		if ( function_exists( 'pll_default_language' ) && self::is_plugin_active( 'polylang/polylang.php' ) ) {
			$wp_language = pll_current_language( 'locale' );
		}

		/**
		 * Consider the main language set in WPML for the web page.
		 */
		if ( self::is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			global $sitepress;
			$wp_language = $sitepress->get_locale_from_language_code( apply_filters( 'wpml_default_language', null ) );
		}

		// if language not set, use the fallback language.
		if ( empty( $wp_language ) ) {
			$wp_language = EASY_LANGUAGE_LANGUAGE_FALLBACK;
		}

		// return language in format ab_CD (e.g. en_US).
		return $wp_language;
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

		// get the post type of the old entry.
		$post_type = get_post_type( $old_id );

		// bail if post type could not be read.
		if ( ! $post_type ) {
			return;
		}

		// copy all assigned taxonomies.
		$taxonomies = get_object_taxonomies( $post_type );
		if ( $taxonomies ) {
			foreach ( $taxonomies as $taxonomy ) {
				// get the terms of the old entry.
				$post_terms = wp_get_object_terms( $old_id, $taxonomy, array( 'fields' => 'slugs' ) );

				// bail if terms could not be read.
				if ( ! is_array( $post_terms ) ) {
					continue;
				}

				// save the terms on new entry.
				wp_set_object_terms( $new_id, $post_terms, $taxonomy );
			}
		}

		// duplicate all post meta.
		$post_meta = get_post_meta( $old_id );
		if ( is_array( $post_meta ) ) {
			foreach ( $post_meta as $meta_key => $meta_values ) {
				// ignore some keys.
				if ( in_array( $meta_key, self::get_object_keys_to_ignore(), true ) ) {
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
	 * Return plugin path with trailing slash.
	 *
	 * @return string
	 */
	public static function get_plugin_path(): string {
		return trailingslashit( plugin_dir_path( EASY_LANGUAGE ) );
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
	 * Check if settings-errors-entry already exists in array.
	 *
	 * @param string                  $entry The search entry.
	 * @param array<string|int,mixed> $error_list The list of errors.
	 * @return bool
	 */
	public static function check_if_setting_error_entry_exists_in_array( string $entry, array $error_list ): bool {
		foreach ( $error_list as $item ) {
			if ( $item['setting'] === $entry ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validate multiple text fields.
	 *
	 * @param array<string,mixed>|null $values The list of values.
	 *
	 * @return array<string,mixed>|null
	 * @noinspection PhpUnused
	 */
	public static function settings_validate_multiple_text_field( ?array $values ): ?array {
		$filter = current_filter();
		if ( ! empty( $filter ) ) {
			$filter = str_replace( 'sanitize_option_', '', $filter );
			if ( empty( $values ) ) {
				$pre_values = filter_input( INPUT_POST, $filter . '_ro', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FORCE_ARRAY );
				if ( ! empty( $pre_values ) ) {
					$values = array_map( 'sanitize_text_field', $pre_values );
				}
			}
		}

		// return resulting values as array.
		return $values;
	}

	/**
	 * Validate select field.
	 *
	 * @param ?string $value The value as string.
	 *
	 * @return string|null
	 */
	public static function settings_validate_select_field( ?string $value ): ?string {
		$filter = current_filter();
		if ( ! empty( $filter ) ) {
			$filter = str_replace( 'sanitize_option_', '', $filter );
			if ( empty( $value ) ) {
				$pre_values = filter_input( INPUT_POST, $filter . '_ro', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				if ( ! empty( $pre_values ) ) {
					$value = sanitize_text_field( $pre_values );
				}
			}
		}
		return $value;
	}

	/**
	 * Get attachment-object by the given filename.
	 *
	 * @param string $post_name The searched filename.
	 *
	 * @return WP_Post|false
	 */
	public static function get_attachment_by_post_name( string $post_name ): WP_Post|false {
		$query      = array(
			'posts_per_page' => 1,
			'post_type'      => 'attachment',
			'name'           => trim( $post_name ),
			'post_status'    => 'inherit',
		);
		$attachment = new WP_Query( $query );

		// bail on no results.
		if ( 0 === $attachment->post_count ) {
			return false;
		}

		// get first result.
		$post = $attachment->posts[0];

		// bail if attachment is not WP_Post.
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		// return resulting object.
		return $post;
	}

	/**
	 * Return the attachment object by given language-code via post-meta of the attachment.
	 *
	 * @param string $language_code The search language code.
	 *
	 * @return WP_Post|false
	 */
	public static function get_attachment_by_language_code( string $language_code ): WP_Post|false {
		$query      = array(
			'posts_per_page' => 1,
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => 'easy_language_code',
					'value'   => trim( $language_code ),
					'compare' => 'LIKE',
				),
			),
		);
		$attachment = new WP_Query( $query );

		// bail on no results.
		if ( 0 === $attachment->post_count ) {
			return false;
		}

		// get first result.
		$post = $attachment->posts[0];

		// bail if the attachment is not WP_Post.
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		// return resulting object.
		return $post;
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
		$images = (array) get_option( 'easy_language_icons', array() );

		// return image if it is in list.
		if ( ! empty( $images[ $language_code ] ) ) {
			return ' ' . wp_kses_post( $images[ $language_code ] );
		}

		// get it from media library if requested language is not in list.
		$attachment = self::get_attachment_by_language_code( $language_code );
		if ( false !== $attachment ) {
			// get image.
			$image = wp_get_attachment_image( $attachment->ID, array( 18, 18 ) );

			// add it to list in DB.
			$images[ $language_code ] = $image;
			update_option( 'easy_language_icons', $images );

			// return image.
			return ' ' . wp_kses_post( $image );
		}

		return '';
	}

	/**
	 * Generate the admin menu bar for supported languages.
	 *
	 * @param string                            $id The ID of the object.
	 * @param WP_Admin_Bar                      $admin_bar The admin-bar-object.
	 * @param array<string,array<string,mixed>> $target_languages The array of languages.
	 * @param Objects                           $my_object The object itself.
	 * @param string                            $object_type_name The type name of the object.
	 *
	 * @return void
	 */
	public static function generate_admin_bar_language_menu( string $id, WP_Admin_Bar $admin_bar, array $target_languages, Objects $my_object, string $object_type_name ): void {
		foreach ( $target_languages as $language_code => $target_language ) {
			/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the language-name */
			$title = sprintf( __( 'Show this %1$s in %2$s ', 'easy-language' ), esc_html( $object_type_name ), esc_html( $target_language['label'] ) );

			// check if this object is already translated in this language.
			if ( false !== $my_object->is_simplified_in_language( $language_code ) ) {
				// get the post object.
				$simplified_post_object = new Post_Object( $my_object->get_simplification_in_language( $language_code ) );

				// get its page builder.
				$page_builder_obj = $simplified_post_object->get_page_builder();

				// bail if pagebuilder could not be read.
				if ( ! $page_builder_obj ) {
					continue;
				}

				// get the URL.
				$url = $page_builder_obj->get_edit_link();
			} else {
				// create link to generate a new simplification for this object.
				$url = $my_object->get_simplification_link( $language_code );
				/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the language-name */
				$title = sprintf( __( 'Create a simplification of this %1$s in %2$s ', 'easy-language' ), esc_html( $object_type_name ), esc_html( $target_language['label'] ) );
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
							'title' => esc_html( $title ),
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
		// get the attachment by given language code.
		$attachment = self::get_attachment_by_language_code( $language_code );

		// bail if no attachment could be read.
		if ( ! $attachment ) {
			return '';
		}

		// get the URL.
		$url = wp_get_attachment_image_url( $attachment->ID );

		// bail if URL is empty.
		if ( ! $url ) {
			return '';
		}

		// return the URL.
		return $url;
	}

	/**
	 * Get object by given id and type.
	 *
	 * @param int    $object_id The object-ID.
	 * @param string $object_type The object-type (optional).
	 * @return Objects|false
	 */
	public static function get_object( int $object_id, string $object_type = '' ): Objects|false {
		$false = false;

		/**
		 * Filter the object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool $false Return false as default.
		 * @param int $object_id The ID of the object.
		 * @param string $object_type The type of the object.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		return apply_filters( 'easy_language_get_object', $false, $object_id, $object_type ); // @phpstan-ignore return.type
	}

	/**
	 * Get language of given object depending on third-party-plugins.
	 *
	 * @param int    $object_id The ID of the object.
	 * @param string $object_type The type of object.
	 *
	 * @return string
	 */
	public static function get_lang_of_object( int $object_id, string $object_type ): string {
		// get object.
		$object = self::get_object( $object_id, $object_type );

		// bail if object could not be loaded.
		if ( ! $object ) {
			return '';
		}

		/**
		 * Consider the main language set in WPML for the web page
		 */
		if ( self::is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			$language_details = apply_filters( 'wpml_post_language_details', null, $object->get_id() );
			if ( ! empty( $language_details ) ) {
				return $language_details['locale'];
			}
		}

		// fallback and use the WordPress-language.
		return self::get_wp_lang();
	}

	/**
	 * Return the URL for main WordPress settings.
	 *
	 * @return string
	 */
	public static function get_wp_settings_url(): string {
		return admin_url() . 'options-general.php';
	}

	/**
	 * Validate the language support of the active API.
	 *
	 * @param Api_Base $api The API to check.
	 * @param string   $language The language to check (optional).
	 *
	 * @return void
	 */
	public static function validate_language_support_on_api( Api_Base $api, string $language = '' ): void {
		// get the transients-object.
		$transients_obj = Transients::get_instance();

		// get the actual language in WordPress.
		if ( empty( $language ) ) {
			$language = self::get_wp_lang();
		}

		// if the actual language in WordPress is not supported as a possible source language, show a hint.
		$source_languages = $api->get_supported_source_languages();
		if ( empty( $source_languages[ $language ] ) ) {
			// create a list of languages this API supports as the HTML list.
			$language_list = '<ul>';
			foreach ( $source_languages as $settings ) {
				$language_list .= '<li>' . $settings['label'] . '</li>';
			}
			$language_list .= '</ul>';

			// get language-name.
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
			$translations  = wp_get_available_translations();
			$language_name = $language;
			if ( ! empty( $translations[ $language ] ) ) {
				$language_name = $translations[ $language ]['native_name'];
			}
			if ( 'en_US' === $language_name ) {
				$language_name = 'English (United States)';
			}

			// create transient hint.
			$transient_obj = $transients_obj->add();
			$transient_obj->set_dismissible_days( 2 );
			$transient_obj->set_name( 'easy_language_source_language_not_supported' );
			/* translators: %1$s will be replaced by the name of the actual language, %2$s by the API title, %3$s by the URL for WordPress settings, %5$s by a list of languages, %6$s by the URL for the API settings. */
			$transient_obj->set_message( sprintf( __( '<strong>The language of your website (%1$s) is actually not supported as source language for simplifications via %2$s!</strong><br>You will not be able to use %3$s.<br>You will not be able to simplify any texts.<br>You have to <a href="%4$s">switch the language</a> in WordPress to one of the following supported source languages: %5$s Or <a href="%6$s">choose another API</a> which supports the language.', 'easy-language' ), '<em>' . esc_html( $language_name ) . '</em>', esc_html( $api->get_title() ), esc_html( $api->get_title() ), esc_url( self::get_wp_settings_url() ), wp_kses_post( $language_list ), esc_url( self::get_settings_page_url() ) ) );
			$transient_obj->set_type( 'error' );
			$transient_obj->set_hide_on( array( Setup::get_instance()->get_setup_link() ) );
			$transient_obj->save();

			// remove activation hint.
			$transients_obj->get_transient_by_name( 'easy_language_api_changed' )->delete();

			// remove intro.
			delete_option( 'easy_language_intro_step_2' );
		} else {
			$transients_obj->get_transient_by_name( 'easy_language_source_language_not_supported' )->delete();
		}
	}

	/**
	 * Return dialog for not available page builder.
	 *
	 * @param Post_Object $post_object The post object.
	 * @param Parser_Base $page_builder The page builder object.
	 *
	 * @return string
	 */
	public static function get_dialog_for_unavailable_page_builder( Post_Object $post_object, Parser_Base $page_builder ): string {
		$dialog = array(
			/* translators: %1$s will be replaced by the object-title */
			'title'   => sprintf( __( 'Used page builder is not available', 'easy-language' ), esc_html( $post_object->get_title() ) ),
			'texts'   => array(
				/* translators: %1$s will be replaced by the API-title */
				'<p>' . sprintf( __( 'This %1$s has been edited with %2$s.<br>This %3$s is currently not activated in your WordPress.<br>Therefore, unfortunately, this page cannot be simplified.', 'easy-language' ), esc_html( $post_object->get_type_name() ), esc_html( $page_builder->get_name() ), esc_html( $page_builder->get_name() ) ) . '</p>',
			),
			'buttons' => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'easy-language' ),
				),
			),
		);
		return self::get_json( $dialog );
	}

	/**
	 * Return the support forum URL.
	 *
	 * @return string
	 */
	public static function get_plugin_support_url(): string {
		return 'https://wordpress.org/support/plugin/easy-language/';
	}

	/**
	 * Get the current URL.
	 *
	 * @return string
	 */
	public static function get_current_url(): string {
		if ( ! empty( $_SERVER['REQUEST_URI'] ) && is_admin() ) {
			return admin_url( basename( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
		}

		// set return value for page url.
		$page_url = '';

		// get actual object.
		$object = get_queried_object();
		if ( $object instanceof WP_Post_Type ) {
			$page_url = get_post_type_archive_link( $object->name );
		}
		if ( $object instanceof WP_Post ) {
			$page_url = get_permalink( $object->ID );
		}

		// bail if no page url could be read.
		if ( ! $page_url ) {
			return '';
		}

		// return result.
		return $page_url;
	}

	/**
	 * Return the version of the given file.
	 *
	 * With WP_DEBUG or plugin-debug enabled its @filemtime().
	 * Without this it's the plugin-version.
	 *
	 * @param string $filepath The absolute path to the requested file.
	 *
	 * @return string
	 */
	public static function get_file_version( string $filepath ): string {
		// check for WP_DEBUG.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return (string) filemtime( $filepath );
		}

		// check for own debug.
		if ( 1 === absint( get_option( 'easy_language_debug_mode', 0 ) ) ) {
			return (string) filemtime( $filepath );
		}

		// get the plugin version which as been set in release.
		$plugin_version = EASY_LANGUAGE_VERSION;

		/**
		 * Filter the used file version (for JS- and CSS-files which get enqueued).
		 *
		 * @since 2.3.0 Available since 2.3.0.
		 *
		 * @param string $plugin_version The plugin-version.
		 * @param string $filepath The absolute path to the requested file.
		 */
		return apply_filters( 'easy_language_file_version', $plugin_version, $filepath );
	}

	/**
	 * Return object post meta keys we ignore.
	 *
	 * @return string[]
	 */
	private static function get_object_keys_to_ignore(): array {
		$keys = array( '_edit_lock', '_edit_last', '_wp_old_slug' );

		/**
		 * Filter the list of post meta keys we ignore during creating new object.
		 *
		 * @since 2.4.0 Available since 2.4.0.
		 * @param array $keys List of keys to ignore.
		 */
		return apply_filters( 'easy_language_post_meta_keys_to_ignore', $keys );
	}

	/**
	 * Create JSON from a given array.
	 *
	 * @param array<string|int,mixed>|WP_Error $source The source array.
	 * @param int                              $flag Flags to use for this JSON.
	 *
	 * @return string
	 */
	public static function get_json( array|WP_Error $source, int $flag = 0 ): string {
		// create JSON.
		$json = wp_json_encode( $source, $flag );

		// bail if creating the JSON failed.
		if ( ! $json ) {
			return '';
		}

		// return the resulting JSON-string.
		return $json;
	}

	/**
	 * Return the logo as img
	 *
	 * @param bool $big_logo True to output the big logo.
	 *
	 * @return string
	 */
	public static function get_logo_img( bool $big_logo = false ): string {
		if ( $big_logo ) {
			return '<img src="' . self::get_plugin_url() . 'gfx/easy-language-icon.png" alt="Easy Language Logo" class="logo">';
		}
		return '<img src="' . self::get_plugin_url() . 'gfx/easy-language-icon.png" alt="Easy Language Logo" class="logo">';
	}

	/**
	 * Checks if the current request is a WP REST API request.
	 *
	 * Case #1: After WP_REST_Request initialization
	 * Case #2: Support "plain" permalink settings and check if `rest_route` starts with `/`
	 * Case #3: It can happen that WP_Rewrite is not yet initialized,
	 *          so do this (wp-settings.php)
	 * Case #4: URL Path begins with wp-json/ (your REST prefix)
	 *          Also supports WP installations in sub-folders
	 *
	 * @returns boolean
	 * @author matzeeable
	 */
	public static function is_rest_request(): bool {
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) // Case #1.
			|| ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) // (#2)
					&& str_starts_with( $GLOBALS['wp']->query_vars['rest_route'], '/' ) ) ) {
			return true;
		}

		// Case #3.
		global $wp_rewrite;
		if ( is_null( $wp_rewrite ) ) {
			$wp_rewrite = new WP_Rewrite();
		}

		// Case #4.
		$rest_url    = wp_parse_url( trailingslashit( rest_url() ) );
		$current_url = wp_parse_url( add_query_arg( array() ) );
		if ( is_array( $current_url ) && is_array( $rest_url ) && isset( $current_url['path'], $rest_url['path'] ) ) {
			return str_starts_with( $current_url['path'], $rest_url['path'] );
		}
		return false;
	}

	/**
	 * Return the hidden section of the settings object.
	 *
	 * @return false|Section
	 */
	public static function get_hidden_section(): Section|false {
		// get settings object.
		$settings_obj = \easyLanguage\Dependencies\easySettingsForWordPress\Settings::get_instance();

		// create a hidden page for hidden settings.
		$hidden_page = $settings_obj->get_page( 'hidden_page' );

		// bail if page could not be found.
		if ( ! $hidden_page instanceof Page ) {
			return false;
		}

		// create a hidden tab on this page.
		$hidden_tab = $hidden_page->get_tab( 'hidden_tab' );

		// bail if tab could not be found.
		if ( ! $hidden_tab instanceof Tab ) {
			return false;
		}

		// the hidden section for any not visible settings.
		$hidden_section = $hidden_tab->get_section( 'hidden_section' );

		// bail if the section could not be found.
		if ( ! $hidden_section instanceof Section ) {
			return false;
		}

		// return the hidden section object.
		return $hidden_section;
	}
}
