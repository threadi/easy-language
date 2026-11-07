<?php
/**
 * File for a helper to find the request handlers of this plugin in its source code.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Helpers;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;

/**
 * Find the handlers for AJAX and admin actions in the source code of this plugin.
 *
 * The handlers are read from the source code (and not from the registered hooks), so also handlers of
 * page builders or plugins, which are not active in the test environment, are checked - and every new
 * handler is checked automatically without changing the tests.
 */
trait Handler_Scanner {

	/**
	 * Return the PHP files of the plugin (without the copied dependencies).
	 *
	 * @return array<int,string>
	 */
	public static function get_plugin_files(): array {
		$files    = array();
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( TESTS_PLUGIN_DIR . '/app', FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( ! $file instanceof \SplFileInfo || 'php' !== $file->getExtension() ) {
				continue;
			}
			if ( str_contains( str_replace( '\\', '/', $file->getPathname() ), '/app/Dependencies/' ) ) {
				continue;
			}
			$files[] = $file->getPathname();
		}
		sort( $files );
		return $files;
	}

	/**
	 * Return the handlers registered with "$this" for hooks with the given prefix and our action names.
	 *
	 * @param string $prefix The prefix of the hook, e.g. "wp_ajax_" or "admin_action_".
	 *
	 * @return array<string,array{0: class-string, 1: string}> List of action name => class name and method.
	 */
	public static function get_handlers( string $prefix ): array {
		$handlers = array();
		foreach ( self::get_plugin_files() as $path ) {
			$content = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			// get the class of this file.
			if ( ! preg_match( '/^namespace\s+([\w\\\\]+);/m', $content, $namespace ) || ! preg_match( '/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $content, $class ) ) {
				continue;
			}

			// get the handlers.
			preg_match_all( "/add_action\(\s*'" . preg_quote( $prefix, '/' ) . "(easy_language_\w+)'\s*,\s*array\(\s*\\\$this\s*,\s*'(\w+)'\s*\)/", $content, $matches, PREG_SET_ORDER );
			foreach ( $matches as $match ) {
				/** @var class-string $class_name */
				$class_name            = $namespace[1] . '\\' . $class[1];
				$handlers[ $match[1] ] = array( $class_name, $match[2] );
			}
		}
		ksort( $handlers );
		return $handlers;
	}

	/**
	 * Return the source code of the given method.
	 *
	 * @param class-string $class_name The class.
	 * @param string       $method The method.
	 *
	 * @return string
	 */
	public static function get_method_source( string $class_name, string $method ): string {
		$reflection = new ReflectionMethod( $class_name, $method );
		$lines      = file( (string) $reflection->getFileName() );
		if ( ! is_array( $lines ) ) {
			return '';
		}
		return implode( '', array_slice( $lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
	}

	/**
	 * Return the instance of the given class (all handlers of this plugin are singletons).
	 *
	 * @param class-string $class_name The class.
	 *
	 * @return object
	 */
	public static function get_handler_instance( string $class_name ): object {
		return call_user_func( array( $class_name, 'get_instance' ) );
	}
}
