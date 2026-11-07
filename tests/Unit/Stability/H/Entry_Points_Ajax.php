<?php
/**
 * Regression tests for all AJAX handlers of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\H;

use easyLanguage\Tests\Helpers\Handler_Scanner;
use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

/**
 * Object to test that every AJAX handler stops without a valid nonce.
 *
 * The handlers are read from the source code, so new handlers are checked automatically.
 */
class Entry_Points_Ajax extends WP_Ajax_UnitTestCase {
	use Handler_Scanner;

	/**
	 * Prepare the plugin once for this class.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// initialize the plugin (same as in all other test classes).
		\easyLanguage\Tests\easyLanguageTests::prepare_plugin();
	}

	/**
	 * Block all requests to external APIs.
	 *
	 * @return \WP_Error
	 */
	public static function block_requests(): \WP_Error {
		return new \WP_Error( 'blocked', 'Requests are blocked in this test.' );
	}

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		add_filter( 'pre_http_request', array( self::class, 'block_requests' ) );

		// _handleAjax() runs "admin_init": the update checks of WordPress would trigger an error on blocked requests.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( self::class, 'block_requests' ) );
		parent::tear_down();
	}

	/**
	 * Call the given AJAX action and return how it stopped.
	 *
	 * @param string $action The name of the action.
	 *
	 * @return array{stopped: bool, message: string, response: string}
	 */
	private function call_action( string $action ): array {
		$this->_last_response = '';
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieStopException $e ) {
			return array(
				'stopped'  => true,
				'message'  => $e->getMessage(),
				'response' => $this->_last_response,
			);
		} catch ( WPAjaxDieContinueException $e ) {
			return array(
				'stopped'  => false,
				'message'  => $e->getMessage(),
				'response' => $this->_last_response,
			);
		}
		return array(
			'stopped'  => false,
			'message'  => '',
			'response' => $this->_last_response,
		);
	}

	/**
	 * Every AJAX handler stops without nonce (also for administrators) or returns an error.
	 *
	 * @return void
	 */
	public function test_ajax_actions_stop_without_nonce(): void {
		$handlers = self::get_handlers( 'wp_ajax_' );
		$this->assertGreaterThanOrEqual( 15, count( $handlers ) );

		$this->_setRole( 'administrator' );
		foreach ( $handlers as $action => $handler ) {
			$callback = array( self::get_handler_instance( $handler[0] ), $handler[1] );
			add_action( 'wp_ajax_' . $action, $callback );

			// send typical parameters, but no nonce.
			$_POST = array(
				'id'   => '1',
				'type' => 'post',
			);
			unset( $_REQUEST['nonce'], $_GET['nonce'] );

			$result = $this->call_action( $action );
			remove_action( 'wp_ajax_' . $action, $callback );

			// check_ajax_referer() stops with "-1", handlers with their own handling return an error as JSON.
			if ( $result['stopped'] ) {
				$this->assertSame( '-1', $result['message'], $action . ' stopped with an unexpected message.' );
				continue;
			}
			$response = json_decode( $result['response'], true );
			$this->assertIsArray( $response, $action . ' runs without nonce.' );
			$this->assertArrayHasKey( 'success', $response, $action . ' runs without nonce.' );
			$this->assertFalse( $response['success'], $action . ' runs without nonce.' );
		}
	}

	/**
	 * Every AJAX handler with a nonce of a subscriber does not return a successful result.
	 *
	 * The nonce is taken from the source code of the handler, so only the capability check stops the request.
	 *
	 * @return void
	 */
	public function test_ajax_actions_deny_subscribers(): void {
		$this->_setRole( 'subscriber' );
		foreach ( self::get_handlers( 'wp_ajax_' ) as $action => $handler ) {
			// get the nonce action from the handler.
			if ( ! preg_match( "/check_ajax_referer\(\s*'([^']+)'\s*,\s*'([^']+)'/", self::get_method_source( $handler[0], $handler[1] ), $nonce ) ) {
				continue;
			}

			$callback = array( self::get_handler_instance( $handler[0] ), $handler[1] );
			add_action( 'wp_ajax_' . $action, $callback );

			// send a valid nonce of the subscriber.
			$_POST              = array(
				'id'   => '1',
				'type' => 'post',
			);
			$_POST[ $nonce[2] ] = wp_create_nonce( $nonce[1] );

			$result = $this->call_action( $action );
			remove_action( 'wp_ajax_' . $action, $callback );

			// a successful result is never allowed for subscribers.
			$response = json_decode( $result['response'], true );
			if ( is_array( $response ) ) {
				$this->assertNotTrue( $response['success'] ?? false, $action . ' is successful for subscribers.' );
				$this->assertNotSame( 'ok', $response['status'] ?? ( $response['result'] ?? ( $response['success'] ?? '' ) ), $action . ' is successful for subscribers.' );
			}
		}
	}
}
