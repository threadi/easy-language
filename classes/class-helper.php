<?php
/**
 * File with general helper-functions for this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

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
     * Return whether the actual request is a REST-API-request.
     *
     * @return bool
     */
    public static function is_rest_api(): bool {
        if ( empty( $_SERVER['REQUEST_URI'] ) ) {
            return false;
        }

        $rest_prefix         = trailingslashit( rest_get_url_prefix() );
        return str_contains(trailingslashit($_SERVER['REQUEST_URI']), $rest_prefix);
    }

    /**
     * Check if WP CLI is used for actual request.
     *
     * @return bool
     */
    public static function is_cli(): bool
    {
        return defined( 'WP_CLI' ) && WP_CLI;
    }

	/**
	 * Return the current frontend language.
	 *
	 * @return string
	 */
	public static function get_current_language(): string {
		$lang = get_query_var('lang');

		// if there is no language in query-var, use the WP-locale.
		if( empty($lang) ) {
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
		$dt = get_date_from_gmt($date);
		return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($dt));
	}

	/**
	 * Return the settings-page-URL.
	 *
	 * @return string
	 */
	public static function get_settings_page_url(): string {
		return 'options-general.php?page=easy_language_settings';
	}

	/**
	 * Return the active Wordpress-language depending on our own support.
	 * If language is unknown for our plugin, use english.
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public static function get_wp_lang(): string
	{
		$wpLang = get_bloginfo('language');

		/**
		 * Consider the main language set in Polylang for the web page
		 */
		if( self::is_plugin_active('polylang/polylang.php') && function_exists('pll_default_language') ) {
			$wpLang = pll_default_language();
		}

		/**
		 * Consider the main language set in WPML for the web page
		 */
		if( self::is_plugin_active('sitepress-multilingual-cms/sitepress.php') ) {
			$wpLang = apply_filters('wpml_default_language', NULL );
		}

		// if language not set, use default language.
		if( empty($wpLang) ) {
			$wpLang = EASY_LANGUAGE_LANGUAGE_EMERGENCY;
		}

		// return language in format ab_CD (e.g. en_US).
		return str_replace('-', '_', $wpLang);
	}

	/**
	 * Copy complete taxonomy and post-meta from old to new cpt entry.
	 *
	 * @param int $old_id ID of original post-id.
	 * @param int $new_id ID of new post-id.
	 * @return void
	 */
	public static function copy_cpt(int $old_id, int $new_id ): void {
		// copy all assigned taxonomies.
		$taxonomies = get_object_taxonomies( get_post_type( $old_id ) ); // returns array of taxonomy names for post type, ex array("category", "post_tag");
		if( $taxonomies ) {
			foreach ( $taxonomies as $taxonomy ) {
				$post_terms = wp_get_object_terms( $old_id, $taxonomy, array( 'fields' => 'slugs' ) );
				wp_set_object_terms( $new_id, $post_terms, $taxonomy );
			}
		}

		// duplicate all post meta.
		$post_meta = get_post_meta( $old_id );
		if( $post_meta ) {
			foreach ( $post_meta as $meta_key => $meta_values ) {
                // ignore some keys.
                if( in_array( $meta_key, array( '_edit_lock', '_edit_last', '_wp_page_template', '_wp_old_slug' ), true ) ) {
                    continue;
                }

                // loop through the values of the key and add them to the new id.
				foreach ( $meta_values as $meta_value ) {
					add_post_meta( $new_id, $meta_key, maybe_unserialize(wp_slash($meta_value)) );
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
		return plugin_dir_path(EASY_LANGUAGE);
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
	public static function check_if_setting_error_entry_exists_in_array( $entry, $array ): bool
	{
		foreach( $array as $item ) {
			if( $item['setting'] == $entry ) {
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
		if( !empty($filter) ) {
			$filter = str_replace('sanitize_option_', '', $filter);
			if (empty($values) && !empty($_REQUEST[$filter . '_ro'])) {
				$values = (array)$_REQUEST[$filter . '_ro'];
			}
		}
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
		if( !empty($filter) ) {
			$filter = str_replace('sanitize_option_', '', $filter);
			if (empty($values) && !empty($_REQUEST[$filter . '_ro'])) {
				$values = sanitize_text_field($_REQUEST[$filter . '_ro']);
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
		if( !empty($filter) ) {
			$filter = str_replace('sanitize_option_', '', $filter);
			if (empty($values) && !empty($_REQUEST[$filter . '_ro'])) {
				$value = sanitize_text_field($_REQUEST[$filter . '_ro']);
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
}
