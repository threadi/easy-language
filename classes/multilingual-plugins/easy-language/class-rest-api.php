<?php
/**
 * File to define REST API endpoints we use.
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use WP_REST_Server;

/**
 * Object for REST API Handling.
 */
class REST_Api {
	/**
	 * Instance of this object.
	 *
	 * @var ?REST_Api
	 */
	private static ?REST_Api $instance = null;

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): REST_Api {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 *
	 *
	 * @param Init $param
	 *
	 * @return void
	 */
	public function init( Init $param ): void {
		add_action( 'rest_api_init', array( $this, 'add_language_options_for_page_endpoint' ) );
	}

	/**
	 * Add endpoint for language options for single page.
	 *
	 * @return void
	 */
	public function add_language_options_for_page_endpoint(): void {
		register_rest_route( 'easy-language/v1', '/language-options/(?P<id>\d+)', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'get_language_options_for_page' ),
			'args' => array(
				'id' => array(
					'validate_callback' => function($param, $request, $key ) {
						return is_numeric( $param );
					}
				),
			),
			'permission_callback' => function () {
				return current_user_can( 'edit_el_simplifier' );
			}
		) );
	}

	/**
	 * Return possible language options for given object.
	 *
	 * @param $data
	 *
	 * @return array
	 */
	public function get_language_options_for_page( $data ): array {
		// get ID of requested object.
		$post_id = absint($data['post']);

		// bail if not post ID is given.
		if( 0 === $post_id ) {
			return array();
		}

		// register the requested post_id global.
		$GLOBALS['post'] = get_post($post_id);

		// collect the return.
		ob_start();
		Pagebuilder_Support::get_instance()->render_meta_box_content( get_post( $post_id) );
		$contents = ob_get_contents();
		ob_end_clean();

		// return possible options.
		return array(
			'html' => $contents
		);
	}
}
