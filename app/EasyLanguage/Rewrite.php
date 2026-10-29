<?php
/**
 * File for rewrite-handling of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Languages;
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
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
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

		// refresh the rules at the end of "init": all post types and our rules are registered then,
		// and WordPress has not parsed the request yet. So the request, which runs the refresh,
		// is already handled with the new rules (and does not end in a 404).
		add_action( 'init', array( $this, 'do_refresh' ), PHP_INT_MAX );
	}

	/**
	 * Set to refresh the rewrite rules on the next request.
	 *
	 * This is not run via the @Transient-object as we must use it via wp-hook.
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
		// bail if no refresh is requested.
		if ( false === get_transient( 'easy_language_refresh_rewrite_rules' ) ) {
			return;
		}

		// remove the marker first, so parallel requests do not refresh again.
		delete_transient( 'easy_language_refresh_rewrite_rules' );

		// refresh the rules in the database. The .htaccess is not changed by our rules, so a soft flush is enough.
		flush_rewrite_rules( false );
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

		// get the slugs for each actual enabled language.
		$slugs = array();
		foreach ( Languages::get_instance()->get_active_languages() as $language ) {
			// bail if no URL-part is set for this language.
			if ( empty( $language['url'] ) ) {
				continue;
			}

			// add the slug to the list.
			$slugs[] = preg_quote( $language['url'], '/' );
		}

		// bail if no slugs are available.
		if ( empty( $slugs ) ) {
			return;
		}

		// define the regex for the language-specific front page.
		$regex = '^(' . implode( '|', $slugs ) . ')/?$';

		if ( 'page' === get_option( 'show_on_front' ) ) {
			$page_id = get_option( 'page_on_front' );
			add_rewrite_rule( $regex, 'index.php?lang=$matches[1]&page_id=' . absint( $page_id ), 'top' );
		}
		if ( 'posts' === get_option( 'show_on_front' ) ) {
			add_rewrite_rule( $regex, 'index.php?lang=$matches[1]', 'top' );
		}
	}

	/**
	 * The definition of the rewrite rules used by WordPress.
	 *
	 * @param array<string,string> $rules The actual rewrite-rules.
	 *
	 * @return array<string,string>
	 */
	public function set_rules_on_objects( array $rules ): array {
		global $wp_rewrite;

		// get the slugs for each actual enabled language.
		$slugs = array();
		foreach ( Languages::get_instance()->get_active_languages() as $language ) {
			// bail if no URL-part is set for this language.
			if ( empty( $language['url'] ) ) {
				continue;
			}

			// add the slug to the list.
			$slugs[] = preg_quote( $language['url'], '/' );
		}

		// bail if no slugs are available.
		if ( empty( $slugs ) ) {
			return $rules;
		}

		// define the slug for rules.
		$slug      = $wp_rewrite->root . '(' . implode( '|', $slugs ) . ')/';
		$new_rules = array();
		foreach ( $rules as $key => $rule ) {
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
	 * @param array<string> $query_vars The actual query vars.
	 * @return array<string>
	 */
	public function set_query_vars( array $query_vars ): array {
		// add our language-variable, also used by our rewrite rules.
		if ( ! in_array( 'lang', $query_vars, true ) ) {
			$query_vars[] = 'lang';
		}
		return $query_vars;
	}

	/**
	 * Do not redirect URLs if the requested URL contains a language-code from our plugin.
	 *
	 * @param string $redirect_url The URL to redirect to.
	 * @param string $requested_url The requested URL.
	 *
	 * @return string
	 */
	public function prevent_redirect_for_front_page( string $redirect_url, string $requested_url ): string {
		foreach ( Languages::get_instance()->get_active_languages() as $settings ) {
			// bail if no URL-part is set for this language.
			if ( empty( $settings['url'] ) ) {
				continue;
			}

			// do not redirect if the URL-part of this language is used in the requested URL.
			if ( str_contains( $requested_url, '/' . $settings['url'] . '/' ) ) {
				return '';
			}
		}
		return $redirect_url;
	}

	/**
	 * Add the language-specific prefix to URLs of simplified pages.
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

		// bail if no URL is given or plain permalinks are used (WordPress uses query parameters then).
		if ( '' === $url || empty( get_option( 'permalink_structure' ) ) ) {
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

		// check for "/"-separator.
		$separator = '/';
		if ( str_starts_with( $url, '/' ) ) {
			$separator = '';
		}

		// return the URL prefixed with the language part.
		return $languages[ $language ]['url'] . $separator . $url;
	}
}
