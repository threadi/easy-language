<?php
/**
 * File for rewrite-handling of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Languages;
use WP_Post;

/**
 * Rewrite-Handling for this plugin.
 */
class Rewrite {

	/**
	 * Instance of this object.
	 *
	 * @var ?Rewrite
	 */
	private static ?Rewrite $instance = null;

	/**
	 * Init object.
	 *
	 * @var Init
	 */
	private Init $init;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Rewrite {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Initialize the optional language-prefix for any URLs.
	 *
	 * @param Init $init The init-object.
	 *
	 * @return void
	 */
	public function init( Init $init ): void {
		$this->init = $init;

		// hook to set rewrite rules.
		add_action( 'init', array( $this, 'set_rules' ) );

		// hooks to set urls.
		add_filter( 'get_page_uri', array( $this, 'post_link' ), 10, 2 );
		add_filter( 'pre_post_link', array( $this, 'post_link' ), 10, 2 );

		// misc hooks.
		add_filter( 'query_vars', array( $this, 'set_query_vars' ) );
		add_filter( 'redirect_canonical', array( $this, 'prevent_redirect_for_front_page' ), 10, 2 );

		// refresh-handling.
		add_action( 'update_option_show_on_front', array( $this, 'set_refresh' ), 10, 0 );
		add_action( 'wp', array( $this, 'do_refresh' ) );
	}

	/**
	 * Set to refresh the rewrite rules on next request.
	 *
	 * This is not run via Transient-object as we must use it via wp-hook.
	 *
	 * @return void
	 */
	public function set_refresh(): void {
		set_transient( 'easy_language_refresh_rewrite_rules', 1 );
	}

	/**
	 * Update slugs on request.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function do_refresh(): void {
		if ( is_user_logged_in() && false !== get_transient( 'easy_language_refresh_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_transient( 'easy_language_refresh_rewrite_rules' );
		}
	}

	/**
	 * Set rewrite rules.
	 *
	 * @return void
	 */
	public function set_rules(): void {
		foreach ( $this->init->get_supported_post_types() as $post_type => $enabled ) {
			add_filter( $post_type . '_rewrite_rules', array( $this, 'set_rules_on_objects' ) );
		}
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$page_id = get_option( 'page_on_front' );
			add_rewrite_rule( '^([a-zA_Z_]{5})/?$', 'index.php?lang=$matches[1]&page_id=' . absint( $page_id ), 'top' );
		}
		if ( 'posts' === get_option( 'show_on_front' ) ) {
			add_rewrite_rule( '^([a-zA_Z_]{5})/?$', 'index.php?lang=[1]', 'top' );
		}
	}

	/**
	 * The definition of the rewrite rules used by WordPress.
	 *
	 * @param array $rules The actual rewrite-rules.
	 *
	 * @return array
	 */
	public function set_rules_on_objects( array $rules ): array {
		global $wp_rewrite;

		// get the slugs for each actual enabled language.
		$slugs = array();
		foreach ( Languages::get_instance()->get_active_languages() as $language ) {
			$slugs[] = $language['url'];
		}

		// define slug for rules.
		$slug      = $wp_rewrite->root . '(' . implode( '|', $slugs ) . ')/';
		$new_rules = array();
		foreach ( $rules as $key => $rule ) {
			// bail if something went wrong.
			if ( ! is_string( $rule ) || ! is_string( $key ) ) {
				continue;
			}

			// add our custom rule to allow language-specific slugs in front of urls.
			$new_rules[ $slug . str_replace( $wp_rewrite->root, '', ltrim( $key, '^' ) ) ] = str_replace(
				array( '[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '[1]', '?' ),
				array( '[9]', '[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '?lang=$matches[1]&' ),
				$rule
			);

			// add the given rule.
			$new_rules[ $key ] = $rule;
		}

		// return resulting rules.
		return $new_rules;
	}

	/**
	 * Set the language-variable in query_vars.
	 *
	 * @param array $query_vars The actual query vars.
	 * @return array
	 */
	public function set_query_vars( array $query_vars ): array {
		$query_vars[] = filter_input( INPUT_GET, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ? 'lang' : '';
		return $query_vars;
	}

	/**
	 * Do not redirect URLs if requested URL contains a language-code from our plugin.
	 *
	 * @param string $redirect_url The URL to redirect to.
	 * @param string $requested_url The requested URL.
	 *
	 * @return string
	 */
	public function prevent_redirect_for_front_page( string $redirect_url, string $requested_url ): string {
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			if ( str_contains( $requested_url, '/' . $language_code . '/' ) ) {
				return '';
			}
		}
		return $redirect_url;
	}

	/**
	 * Add language-specific prefix to URLs of simplified pages.
	 *
	 * @param string  $url The generated url without projekt-url.
	 * @param WP_Post $post The object of the post.
	 *
	 * @return string
	 */
	public function post_link( string $url, WP_Post $post ): string {
		$false = false;
		/**
		 * Prevent the usage of the simple generation of permalinks.
		 *
		 * @since 2.3.2 Available since 2.3.2
		 * @param bool $false True to prevent the usage.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( apply_filters( 'easy_language_prevent_simple_permalinks', $false ) ) {
			return $url;
		}

		// get language of this post.
		$language = get_post_meta( $post->ID, 'easy_language_simplification_language', true );

		// bail if no language has been set.
		if ( empty( $language ) ) {
			return $url;
		}

		// get all supported languages.
		$languages = Languages::get_instance()->get_active_languages();

		// bail if there is no corresponding URL part for this language.
		if ( empty( $languages[ $language ] ) ) {
			return $url;
		}

		// return the URL prefixed with the language part.
		return $languages[ $language ]['url'] . '/' . $url;
	}
}
