<?php
/**
 * File with general helper-functions for this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;
use PLL_Settings;
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
	 * @return string The language in locale-format, e.g. "ab_CD").
	 */
	public static function get_wp_lang(): string {
		$wp_language = get_option( 'WPLANG' );

		/**
		 * Consider the main language set in Polylang for the web page.
		 */
		if ( self::is_plugin_active( 'polylang/polylang.php' ) && function_exists( 'pll_default_language' ) ) {
			$wp_language = pll_current_language( 'locale' );
		}

		/**
		 * Consider the main language set in WPML for the web page.
		 */
		if ( self::is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			global $sitepress;
			$wp_language = $sitepress->get_locale_from_language_code( apply_filters( 'wpml_default_language', null ) );
		}

		// if language not set, use default language.
		if ( empty( $wp_language ) ) {
			$wp_language = EASY_LANGUAGE_LANGUAGE_EMERGENCY;
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

		// copy all assigned taxonomies.
		$taxonomies = get_object_taxonomies( get_post_type( $old_id ) );
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
	 * Check if settings-errors-entry already exists in array.
	 *
	 * @param string $entry The search entry.
	 * @param array  $error_list The list of errors.
	 * @return false
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
	 * Validate multiple checkboxes.
	 *
	 * @param ?array $values The list of values.
	 * @return array|null
	 */
	public static function settings_validate_multiple_checkboxes( ?array $values ): ?array {
		$filter = current_filter();
		if ( ! empty( $filter ) ) {
			$filter = str_replace( 'sanitize_option_', '', $filter );
			if ( empty( $values ) && ! empty( $_REQUEST[ $filter . '_ro' ] ) ) {
				$values = (array) wp_unslash( $_REQUEST[ $filter . '_ro' ] );
			}
		}
		return $values;
	}

	/**
	 * Validate multiple text fields.
	 *
	 * @param ?array $values The list of values.
	 *
	 * @return array|null
	 * @noinspection PhpUnused
	 */
	public static function settings_validate_multiple_text_field( ?array $values ): ?array {
		$filter = current_filter();
		if ( ! empty( $filter ) ) {
			$filter = str_replace( 'sanitize_option_', '', $filter );
			if ( empty( $values ) && ! empty( $_REQUEST[ $filter . '_ro' ] ) ) {
				$values = (array) wp_unslash( $_REQUEST[ $filter . '_ro' ] );
			}
		}

		// return resulting values as array.
		return $values;
	}

	/**
	 * Validate multiple radio-fields.
	 *
	 * @param ?string $values The list of values.
	 * @return string|null
	 */
	public static function settings_validate_multiple_radios( ?string $values ): ?string {
		$filter = current_filter();
		if ( ! empty( $filter ) ) {
			$filter = str_replace( 'sanitize_option_', '', $filter );
			if ( empty( $values ) && ! empty( $_REQUEST[ $filter . '_ro' ] ) ) {
				$values = sanitize_text_field( wp_unslash( $_REQUEST[ $filter . '_ro' ] ) );
			}
		}
		return $values;
	}

	/**
	 * Validate select field.
	 *
	 * @param ?string $value The value as string.
	 * @return string|null
	 */
	public static function settings_validate_select_field( ?string $value ): ?string {
		$filter = current_filter();
		if ( ! empty( $filter ) ) {
			$filter = str_replace( 'sanitize_option_', '', $filter );
			if ( empty( $values ) && ! empty( $_REQUEST[ $filter . '_ro' ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_REQUEST[ $filter . '_ro' ] ) );
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
		$query          = array(
			'posts_per_page' => 1,
			'post_type'      => 'attachment',
			'name'           => trim( $post_name ),
			'post_status'    => 'inherit',
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
		$query          = array(
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
	 * Set intro step 1.
	 *
	 * @return void
	 */
	public static function set_intro_step1(): void {
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_dismissible_days( 2 );
		$transient_obj->set_name( 'easy_language_intro_step_1' );
		/* translators: %1$s will be replaced by the URL for api settings-URL. */
		$transient_obj->set_message( sprintf( __( '<strong>You have installed Easy Language - nice and thank you!</strong> Now check the <a href="%1$s">API-settings</a>, select one and start simplifying the texts in your website in easy or plain language.', 'easy-language' ), esc_url( self::get_settings_page_url() ) ) );
		$transient_obj->set_type( 'hint' );
		$transient_obj->save();
	}

	/**
	 * Generate the admin menu bar for supported languages.
	 *
	 * @param string       $id The ID of the object.
	 * @param WP_Admin_Bar $admin_bar The admin-bar-object.
	 * @param array        $target_languages The array of languages.
	 * @param Post_Object  $my_object The object itself.
	 * @param string       $object_type_name The type name of the object.
	 *
	 * @return void
	 */
	public static function generate_admin_bar_language_menu( string $id, WP_Admin_Bar $admin_bar, array $target_languages, Post_Object $my_object, string $object_type_name ): void {
		foreach ( $target_languages as $language_code => $target_language ) {
			/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the language-name */
			$title = sprintf( __( 'Show this %1$s in %2$s ', 'easy-language' ), esc_html( $object_type_name ), esc_html( $target_language['label'] ) );

			// check if this object is already translated in this language.
			if ( false !== $my_object->is_simplified_in_language( $language_code ) ) {
				// generate link-target to default editor with language-marker.
				$simplified_post_object = new Post_Object( $my_object->get_simplification_in_language( $language_code ) );
				$url                    = $simplified_post_object->get_page_builder()->get_edit_link();
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
		$attachment = self::get_attachment_by_language_code( $language_code );
		if ( false !== $attachment ) {
			return wp_get_attachment_image_url( $attachment->ID );
		}
		return '';
	}

	/**
	 * Get object by given id and type.
	 *
	 * @param int    $object_id The object-ID.
	 * @param string $object_type The object-type (optional).
	 * @return object|false
	 */
	public static function get_object( int $object_id, string $object_type = '' ): object|false {
		$false = false;

		/**
		 * Filter the object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool $false Return false as default.
		 * @param int $object_id The ID of the object.
		 * @param string $object_type The type of the object.
		 */
		return apply_filters( 'easy_language_get_object', $false, $object_id, $object_type );
	}

	/**
	 * Get language of given object depending on third-party-plugins.
	 *
	 * @param int $object_id The ID of the object.
	 * @param string $object_type The type of object.
	 *
	 * @return string
	 */
	public static function get_lang_of_object( int $object_id, string $object_type ): string {
		// get object.
		$object = self::get_object( $object_id, $object_type );

		/**
		 * Consider the main language set in WPML for the web page
		 */
		if ( self::is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			$language_details = apply_filters( 'wpml_post_language_details', null, $object->get_id() );
			if( !empty($language_details) ) {
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
		return admin_url().'options-general.php';
	}

	/**
	 * Validate the language support of given API.
	 *
	 * @param Api_Base $api The API to check.
	 *
	 * @return void
	 */
	public static function validate_language_support_on_api( Api_Base $api ): void {
		// get the transients-object.
		$transients_obj = Transients::get_instance();

		// get the actual language in WordPress.
		$language = Helper::get_wp_lang();

		// if actual language is not supported as possible source language, show hint.
		$source_languages = $api->get_supported_source_languages();
		if( empty($source_languages[$language]) ) {
			// create list of languages this API supports as HTML-list.
			$language_list = '<ul>';
			foreach( $source_languages as $settings ) {
				$language_list .= '<li>'.esc_html($settings['label']).'</li>';
			}
			$language_list .= '</ul>';

			// get language-name.
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
			$translations = wp_get_available_translations();
			$language_name = $language;
			if( !empty($translations[$language]) ) {
				$language_name = $translations[$language]['native_name'];
			}
			if( 'en_US' === $language_name ) {
				$language_name = 'English (United States)';
			}

			// create transient hint.
			$transient_obj = $transients_obj->add();
			$transient_obj->set_dismissible_days( 2 );
			$transient_obj->set_name( 'easy_language_source_language_not_supported' );
			/* translators: %1$s will be replaced by name of the actual language, %2$s will be replaced by the API-title, %3$s will be replaced by the URL for WordPress-settings, %5$s will be replaced by a list of languages, %6$s will be replaced by the URL for the API-settings. */
			$transient_obj->set_message( sprintf( __( '<strong>The language of your website (%1$s) is not supported as source language for simplifications via %2$s!</strong><br>You will not be able to use %3$s.<br>You will not be able to simplify any texts.<br>You have to <a href="%4$s">switch the language</a> in WordPress to one of the following supported source languages: %5$s Or <a href="%6$s">choose another API</a> which supports the language.', 'easy-language' ), esc_html($language_name), esc_html( $api->get_title() ), esc_html( $api->get_title() ), esc_url( Helper::get_wp_settings_url() ), wp_kses_post( $language_list ), esc_url( Helper::get_settings_page_url() ) ) );
			$transient_obj->set_type( 'error' );
			$transient_obj->save();

			// remove activation hint.
			$transients_obj->get_transient_by_name( 'easy_language_api_changed' )->delete();

			// remove intro.
			delete_option( 'easy_language_intro_step_2' );
		}
		else {
			$transients_obj->get_transient_by_name( 'easy_language_source_language_not_supported' )->delete();
		}
	}

	/**
	 * Return dialog for not available page builder.
	 *
	 * @param $post_object
	 * @param $page_builder
	 *
	 * @return array
	 */
	public static function get_dialog_for_unavailable_page_builder( $post_object, $page_builder ): array {
		return array(
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
	}
}
