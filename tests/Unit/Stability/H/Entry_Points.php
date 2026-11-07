<?php
/**
 * Regression tests for all entry points of this plugin (admin actions, REST routes) and for unsafe code patterns.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\H;

use easyLanguage\Tests\easyLanguageTests;
use easyLanguage\Tests\Helpers\Handler_Scanner;
use WP_REST_Request;
use WPDieException;

/**
 * Object to test that every entry point checks nonce and capability, and that unsafe code patterns are not used.
 *
 * The handlers are read from the source code, so new handlers are checked automatically.
 */
class Entry_Points extends easyLanguageTests {
	use Handler_Scanner;

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * Stop redirects with an exception (a handler without nonce check would otherwise end the test run via exit).
	 *
	 * @param string $location The target of the redirect.
	 *
	 * @return string
	 * @throws \RuntimeException Always.
	 */
	public function stop_redirect( string $location ): string {
		throw new \RuntimeException( 'Redirect to ' . $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * The scanner must find the handlers of this plugin (otherwise the following tests would check nothing).
	 *
	 * @return void
	 */
	public function test_handlers_are_found(): void {
		$this->assertArrayHasKey( 'easy_language_run_simplification', self::get_handlers( 'wp_ajax_' ) );
		$this->assertArrayHasKey( 'easy_language_reset', self::get_handlers( 'admin_action_' ) );
		$this->assertGreaterThanOrEqual( 15, count( self::get_handlers( 'wp_ajax_' ) ) );
		$this->assertGreaterThanOrEqual( 20, count( self::get_handlers( 'admin_action_' ) ) );
	}

	/**
	 * Every handler for AJAX and admin actions checks a nonce and a capability.
	 *
	 * @return void
	 */
	public function test_handlers_check_nonce_and_capability(): void {
		foreach ( array( 'wp_ajax_', 'admin_action_' ) as $prefix ) {
			foreach ( self::get_handlers( $prefix ) as $action => $handler ) {
				$source = self::get_method_source( $handler[0], $handler[1] );
				$this->assertMatchesRegularExpression( '/check_ajax_referer\(|check_admin_referer\(/', $source, $prefix . $action . ' does not check a nonce.' );
				$this->assertStringContainsString( 'current_user_can', $source, $prefix . $action . ' does not check a capability.' );
			}
		}
	}

	/**
	 * Every admin action stops without a nonce, also for administrators.
	 *
	 * @return void
	 */
	public function test_admin_actions_stop_without_nonce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );

		foreach ( self::get_handlers( 'admin_action_' ) as $action => $handler ) {
			// use typical parameters, but no nonce.
			$_GET     = array(
				'id'   => 1,
				'post' => 1,
				'api'  => 'summ_ai',
				'lang' => 'de_LS',
			);
			$_REQUEST = $_GET;

			$stopped = false;
			try {
				call_user_func( array( self::get_handler_instance( $handler[0] ), $handler[1] ) );
			} catch ( WPDieException $e ) {
				$stopped = true;
			}
			$this->assertTrue( $stopped, 'admin_action_' . $action . ' runs without nonce.' );
		}
	}

	/**
	 * Return the REST routes of this plugin with the path to request them.
	 *
	 * @return array<int,array{method: string, path: string}>
	 */
	private function get_rest_requests(): array {
		$requests = array();
		foreach ( rest_get_server()->get_routes( 'easy-language/v1' ) as $route => $handlers ) {
			// the index of the namespace is added by WordPress and public by design (it only lists the routes).
			if ( '/easy-language/v1' === $route ) {
				continue;
			}

			// replace the parameters in the route with a value.
			$path = (string) preg_replace( '/\(\?P<\w+>[^)]+\)/', '1', $route );
			foreach ( $handlers as $handler ) {
				foreach ( array_keys( (array) ( $handler['methods'] ?? array() ) ) as $method ) {
					$requests[] = array(
						'method' => (string) $method,
						'path'   => $path,
					);
				}
			}
		}
		return $requests;
	}

	/**
	 * No REST route of this plugin could be used by visitors or subscribers.
	 *
	 * @return void
	 */
	public function test_rest_routes_deny_visitors_and_subscribers(): void {
		$requests = $this->get_rest_requests();
		$this->assertNotEmpty( $requests );

		foreach ( array( 0, self::factory()->user->create( array( 'role' => 'subscriber' ) ) ) as $user_id ) {
			wp_set_current_user( $user_id );
			foreach ( $requests as $request ) {
				$response = rest_get_server()->dispatch( new WP_REST_Request( $request['method'], $request['path'] ) );
				$this->assertContains( $response->get_status(), array( 401, 403 ), $request['method'] . ' ' . $request['path'] . ' is usable for user ' . $user_id . '.' );
			}
		}
	}

	/**
	 * Return the plugin files, which contain the given pattern, with the matching lines.
	 *
	 * @param string $pattern The regular expression.
	 *
	 * @return array<int,string>
	 */
	private function find_in_plugin( string $pattern ): array {
		$results = array();
		foreach ( self::get_plugin_files() as $path ) {
			foreach ( (array) file( $path ) as $number => $line ) {
				if ( preg_match( $pattern, (string) $line ) ) {
					$results[] = str_replace( TESTS_PLUGIN_DIR, '', $path ) . ':' . ( $number + 1 ) . ': ' . trim( (string) $line );
				}
			}
		}
		return $results;
	}

	/**
	 * Unsafe code patterns are not used in the plugin.
	 *
	 * @return void
	 */
	public function test_no_unsafe_patterns(): void {
		// requests must use wp_safe_remote_*() to reject unsafe URLs.
		$this->assertSame( array(), $this->find_in_plugin( '/(?<!safe_)\bwp_remote_(get|post|head|request)\s*\(/' ), 'Use wp_safe_remote_*() for requests.' );

		// TLS must not be disabled.
		$this->assertSame( array(), $this->find_in_plugin( "/'sslverify'\s*=>\s*false/" ) );

		// esc_sql() does not secure identifiers, use $wpdb->prepare() with %i.
		$this->assertSame( array(), $this->find_in_plugin( '/\besc_sql\s*\(/' ) );

		// no handlers for visitors, which are not logged in (they need a review of their own).
		$this->assertSame( array(), $this->find_in_plugin( "/'wp_ajax_nopriv_/" ) );

		// no public REST routes.
		$this->assertSame( array(), $this->find_in_plugin( "/'permission_callback'\s*=>\s*'__return_true'/" ) );

		// no code execution and no unserialize of strings from outside.
		$this->assertSame( array(), $this->find_in_plugin( '/\b(eval|unserialize|shell_exec|passthru|proc_open|popen)\s*\(/' ) );
	}
}
