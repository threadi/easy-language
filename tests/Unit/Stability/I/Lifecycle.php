<?php
/**
 * Tests for the integration of this plugin into the lifecycle of WordPress (hooks and their order).
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\I;

use easyLanguage\PageBuilder\SiteOrigin;
use easyLanguage\Tests\easyLanguageTests;
use easyLanguage\Tests\Helpers\Handler_Scanner;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Object to test the hooks of this plugin.
 *
 * These tests find errors like the one of the classic widget: a hook, which runs at the wrong time or more than once.
 */
class Lifecycle extends easyLanguageTests {
	use Handler_Scanner;

	/**
	 * The order in which WordPress runs its main hooks during a request.
	 *
	 * @var array<int,string>
	 */
	private const HOOK_ORDER = array(
		'muplugins_loaded',
		'plugins_loaded',
		'setup_theme',
		'after_setup_theme',
		'init',
		'widgets_init',
		'wp_loaded',
		'admin_menu',
		'admin_init',
		'current_screen',
		'rest_api_init',
		'parse_request',
		'wp',
		'template_redirect',
		'wp_enqueue_scripts',
		'admin_enqueue_scripts',
		'wp_head',
		'wp_footer',
		'admin_footer',
	);

	/**
	 * Return the fully qualified class name of a file of this plugin.
	 *
	 * @param string $content The content of the file.
	 *
	 * @return string
	 */
	private function get_class_of_file( string $content ): string {
		if ( ! preg_match( '/^namespace\s+([\w\\\\]+);/m', $content, $namespace ) || ! preg_match( '/^(?:final\s+|abstract\s+)?class\s+(\w+)(?:\s+extends\s+([\w\\\\]+))?(?:\s+implements\s+([\w\\\\,\s]+?))?\s*\{/m', $content, $class ) ) {
			return '';
		}

		// get the imported classes of this file.
		$uses = array();
		preg_match_all( '/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $content, $use_matches, PREG_SET_ORDER );
		foreach ( $use_matches as $use ) {
			$uses[ $use[2] ?? '' ? $use[2] : substr( (string) strrchr( '\\' . $use[1], '\\' ), 1 ) ] = $use[1];
		}

		// do not load classes of page builders and plugins, which are not available: PHP would stop with a fatal error.
		$parents = array_filter( array_map( 'trim', array_merge( array( $class[2] ?? '' ), explode( ',', $class[3] ?? '' ) ) ) );
		foreach ( $parents as $parent ) {
			// get the possible names of the parent.
			if ( str_starts_with( $parent, '\\' ) ) {
				$candidates = array( substr( $parent, 1 ) );
			} elseif ( isset( $uses[ $parent ] ) ) {
				$candidates = array( $uses[ $parent ] );
			} else {
				$candidates = array( $namespace[1] . '\\' . $parent, $parent );
			}

			// bail if none of them is available.
			$available = false;
			foreach ( $candidates as $candidate ) {
				if ( class_exists( $candidate ) || interface_exists( $candidate ) ) {
					$available = true;
					break;
				}
			}
			if ( ! $available ) {
				return '';
			}
		}

		return $namespace[1] . '\\' . $class[1];
	}

	/**
	 * Return the source code of the method with the given name in the given content.
	 *
	 * @param string $content The content of the file.
	 * @param string $method The name of the method.
	 *
	 * @return string
	 */
	private function get_method_body( string $content, string $method ): string {
		if ( ! preg_match( '/function\s+' . preg_quote( $method, '/' ) . '\s*\(/', $content, $match, PREG_OFFSET_CAPTURE ) ) {
			return '';
		}
		$start = strpos( $content, '{', (int) $match[0][1] );
		if ( false === $start ) {
			return '';
		}
		$depth  = 0;
		$length = strlen( $content );
		for ( $i = $start; $i < $length; $i++ ) {
			if ( '{' === $content[ $i ] ) {
				++$depth;
			} elseif ( '}' === $content[ $i ] ) {
				--$depth;
				if ( 0 === $depth ) {
					return substr( $content, $start, $i - $start + 1 );
				}
			}
		}
		return '';
	}

	/**
	 * Every callback of this plugin in the hooks of WordPress exists and is public.
	 *
	 * @return void
	 */
	public function test_registered_callbacks_exist(): void {
		global $wp_filter;

		$checked = 0;
		foreach ( $wp_filter as $hook => $wp_hook ) {
			foreach ( $wp_hook->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( ! is_array( $function ) || 2 !== count( $function ) || ! is_string( $function[1] ) ) {
						continue;
					}
					$class = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
					if ( ! str_starts_with( $class, 'easyLanguage\\' ) || str_starts_with( $class, 'easyLanguage\\Dependencies\\' ) ) {
						continue;
					}
					++$checked;
					$this->assertTrue( method_exists( $class, $function[1] ), $class . '::' . $function[1] . ' for hook ' . $hook . ' does not exist.' );
					$this->assertTrue( ( new ReflectionMethod( $class, $function[1] ) )->isPublic(), $class . '::' . $function[1] . ' for hook ' . $hook . ' is not public.' );
				}
			}
		}

		// the plugin registers many hooks: if none is found, the test would check nothing.
		$this->assertGreaterThan( 50, $checked );
	}

	/**
	 * Every hook in the source code uses an existing public method and filters return a value.
	 *
	 * This also checks the hooks of page builders and plugins, which are not active in the test environment.
	 *
	 * @return void
	 */
	public function test_hooks_in_source_code_use_existing_methods(): void {
		$checked = 0;
		foreach ( self::get_plugin_files() as $path ) {
			$content = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$class   = $this->get_class_of_file( $content );
			if ( '' === $class || ! class_exists( $class ) ) {
				continue;
			}

			preg_match_all( "/add_(action|filter)\(\s*(.+?)\s*,\s*array\(\s*\\\$this\s*,\s*'(\w+)'\s*\)/", $content, $matches, PREG_SET_ORDER );
			foreach ( $matches as $match ) {
				++$checked;
				$name = $class . '::' . $match[3] . ' (' . $match[2] . ')';

				$this->assertTrue( method_exists( $class, $match[3] ), $name . ' does not exist.' );

				$method = new ReflectionMethod( $class, $match[3] );
				$this->assertTrue( $method->isPublic(), $name . ' is not public.' );

				// filters must return the filtered value.
				if ( 'filter' === $match[1] ) {
					$type = $method->getReturnType();
					$this->assertFalse( $type instanceof ReflectionNamedType && in_array( $type->getName(), array( 'void', 'never' ), true ), $name . ' is a filter without return value.' );
				}
			}
		}
		$this->assertGreaterThan( 200, $checked );
	}

	/**
	 * No hook is added in a callback of a hook, which runs later (it would never be run).
	 *
	 * @return void
	 */
	public function test_hooks_are_not_added_after_they_ran(): void {
		$order = array_flip( self::HOOK_ORDER );
		foreach ( self::get_plugin_files() as $path ) {
			$content = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			// get the callbacks of this file for the main hooks of WordPress.
			preg_match_all( "/add_(?:action|filter)\(\s*'(\w+)'\s*,\s*array\(\s*\\\$this\s*,\s*'(\w+)'\s*\)(?:\s*,\s*(-?\d+))?/", $content, $matches, PREG_SET_ORDER );
			foreach ( $matches as $match ) {
				$outer_hook = $match[1];
				if ( ! isset( $order[ $outer_hook ] ) ) {
					continue;
				}
				$outer_priority = isset( $match[3] ) && '' !== $match[3] ? (int) $match[3] : 10;

				// check the hooks added in this callback.
				preg_match_all( "/add_(?:action|filter)\(\s*'(\w+)'/", $this->get_method_body( $content, $match[2] ), $inner_matches, PREG_SET_ORDER );
				foreach ( $inner_matches as $inner ) {
					$inner_hook = $inner[1];
					$message    = str_replace( TESTS_PLUGIN_DIR, '', $path ) . ': ' . $match[2] . '() on "' . $outer_hook . '" adds "' . $inner_hook . '", which has already run.';

					// a hook which runs before the actual hook.
					if ( isset( $order[ $inner_hook ] ) ) {
						$this->assertGreaterThanOrEqual( $order[ $outer_hook ], $order[ $inner_hook ], $message );
					}

					// "widgets_init" runs during "init" with priority 1.
					if ( 'init' === $outer_hook && 'widgets_init' === $inner_hook ) {
						$this->assertLessThan( 1, $outer_priority, $message );
					}
				}
			}
		}
	}

	/**
	 * Return the callbacks of this plugin for the given hook in the order of their priority.
	 *
	 * @param string $hook The name of the hook.
	 *
	 * @return array<int,callable>
	 */
	private function get_plugin_callbacks( string $hook ): array {
		global $wp_filter;
		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return array();
		}
		$result    = array();
		$callbacks = $wp_filter[ $hook ]->callbacks;
		ksort( $callbacks );
		foreach ( $callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $callback ) {
				$function = $callback['function'];
				if ( is_array( $function ) && is_object( $function[0] ) && str_starts_with( get_class( $function[0] ), 'easyLanguage\\' ) ) {
					$result[] = $function;
				}
			}
		}
		return $result;
	}

	/**
	 * Return whether the classic widget could be used in widget areas.
	 *
	 * @return bool
	 */
	private function is_classic_widget_registered(): bool {
		global $wp_widget_factory, $wp_registered_widgets;
		if ( ( $wp_widget_factory->widgets[ SiteOrigin::PLAIN_WIDGET_CLASS ] ?? null ) instanceof \WP_Widget ) {
			return true;
		}
		foreach ( $wp_registered_widgets as $widget ) {
			if ( isset( $widget['callback'][0] ) && is_a( $widget['callback'][0], SiteOrigin::PLAIN_WIDGET_CLASS ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The callbacks of this plugin for "init" and "widgets_init" could run more than once (e.g. if another plugin or
	 * a test runs "init" again): nothing is registered twice and nothing gets lost.
	 *
	 * Unexpected notices (e.g. "block type already registered") let the test fail.
	 *
	 * @return void
	 */
	public function test_init_could_run_twice(): void {
		global $wp_widget_factory;

		// keep the widget factory, as WordPress removes registered widgets from it if "widgets_init" runs again.
		$widgets_backup = $wp_widget_factory->widgets;

		$init_callbacks    = $this->get_plugin_callbacks( 'init' );
		$widgets_callbacks = $this->get_plugin_callbacks( 'widgets_init' );
		$this->assertNotEmpty( $init_callbacks );
		$this->assertNotEmpty( $widgets_callbacks );

		// run the callbacks once as at the start of a request (this could create e.g. the entry for classic menus).
		foreach ( $init_callbacks as $callback ) {
			call_user_func( $callback );
		}
		$entries_before = $this->count_menu_entries();

		// run the callbacks of this plugin twice more, as WordPress would do it on another "init".
		for ( $i = 0; $i < 2; $i++ ) {
			foreach ( $init_callbacks as $callback ) {
				call_user_func( $callback );
			}
			foreach ( $widgets_callbacks as $callback ) {
				call_user_func( $callback );
			}
			$wp_widget_factory->_register_widgets();
		}

		// the post type, the blocks and the shortcode exist.
		$this->assertTrue( post_type_exists( EASY_LANGUAGE_CPT_SWITCHER ) );
		$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( 'easy-language/switcher' ) );
		$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( 'easy-language/navigation-switcher' ) );
		$this->assertTrue( shortcode_exists( 'easy-language-switcher' ) );

		// the classic widget could still be used.
		$this->assertTrue( $this->is_classic_widget_registered() );

		// no further entry for classic menus has been created.
		$this->assertSame( $entries_before, $this->count_menu_entries() );

		// the settings are still available.
		$this->assertNotEmpty( \easyLanguage\Plugin\Settings::get_instance()->get_settings_object()->get_setting( 'easy_language_api' ) );

		// restore the widget factory for the following tests.
		$wp_widget_factory->widgets = $widgets_backup;
	}

	/**
	 * Return the number of entries for the language switcher in classic menus.
	 *
	 * @return int
	 */
	private function count_menu_entries(): int {
		return count(
			get_posts(
				array(
					'post_type'   => EASY_LANGUAGE_CPT_SWITCHER,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}

	/**
	 * The default entry for classic menus is not created again, even if it has been deleted.
	 *
	 * @return void
	 */
	public function test_default_menu_entry_is_created_once(): void {
		$switcher = \easyLanguage\EasyLanguage\Switcher::get_instance();

		// the entry is created once.
		delete_option( 'easy_language_switcher_default' );
		$before = $this->count_menu_entries();
		$switcher->set_default();
		$this->assertSame( $before + 1, $this->count_menu_entries() );
		$this->assertSame( 1, absint( get_option( 'easy_language_switcher_default' ) ) );

		// further calls do not create another entry.
		$switcher->set_default();
		$switcher->set_default();
		$this->assertSame( $before + 1, $this->count_menu_entries() );
	}
}
