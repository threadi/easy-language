<?php
/**
 * File to define REST API endpoints we use.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Apis;
use easyLanguage\Plugin\Helper;
use WP_Post;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Object for REST API Handling.
 */
class Rest_Api {
	/**
	 * Instance of this object.
	 *
	 * @var ?Rest_Api
	 */
	private static ?Rest_Api $instance = null;

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
	public static function get_instance(): Rest_Api {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'add_language_options_for_page_endpoint' ) );
	}

	/**
	 * Add endpoint for language options for single page.
	 *
	 * @return void
	 */
	public function add_language_options_for_page_endpoint(): void {
		// endpoint to get language options on page.
		register_rest_route(
			'easy-language/v1',
			'/language-options/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_language_options_for_page' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_el_simplifier' );
				},
			)
		);

		// bail if necessary function does not exist.
		if ( function_exists( 'wp_get_scheduled_event' ) ) {
			// endpoint to check the automatic cronjob.
			register_rest_route(
				'easy-language/v1',
				'/automatic_cron_checks/',
				array(
					'methods'             => WP_REST_SERVER::READABLE,
					'callback'            => array( $this, 'check_automatic_cron' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				)
			);
		}

		// endpoint to check for API.
		register_rest_route(
			'easy-language/v1',
			'/api_check/',
			array(
				'methods'             => WP_REST_SERVER::READABLE,
				'callback'            => array( $this, 'check_api' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Return possible language options for given object.
	 *
	 * @param WP_REST_Request $data The data from the request.
	 *
	 * @return array<string,string>
	 */
	public function get_language_options_for_page( WP_REST_Request $data ): array {
		// get ID of requested object.
		$post_id = absint( $data['post'] );

		// bail if not post ID is given.
		if ( 0 === $post_id ) {
			return array();
		}

		// get WP_Post-object.
		$wp_post_object = get_post( $post_id );

		// bail if post is not WP_Post.
		if ( ! $wp_post_object instanceof WP_Post ) {
			return array();
		}

		// register the requested post-object global.
		$GLOBALS['post'] = $wp_post_object;
		setup_postdata( $wp_post_object );

		// collect the return.
		ob_start();
		PageBuilders::get_instance()->render_meta_box_content( $wp_post_object );
		$contents = ob_get_clean();

		// bail if no content returned.
		if ( ! $contents ) {
			return array();
		}

		// return possible options.
		return array(
			'html' => $contents,
		);
	}

	/**
	 * Run check of our own automatic simplification cron for Tools > Site Health.
	 *
	 * @return array<string,mixed>
	 */
	public function check_automatic_cron(): array {
		// define default results.
		$result = array(
			'label'       => __( 'Easy Language Automatic Simplification Cron Check', 'easy-language' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Easy Language', 'easy-language' ),
				'color' => 'gray',
			),
			'description' => __( 'To use automatic simplification for texts in your website the Easy Language plugin adds a WordPress cronjob which is used to perform this tasks.<br><strong>All ok with the cronjob!</strong>', 'easy-language' ),
			'actions'     => '',
			'test'        => 'easy_language_check_automatic_cron',
		);

		// get scheduled event.
		$scheduled_event = wp_get_scheduled_event( 'easy_language_automatic_simplification' );

		// event does not exist => show error.
		if ( false === $scheduled_event ) {
			$url                   = add_query_arg(
				array(
					'action' => 'easy_language_create_automatic_cron',
					'nonce'  => wp_create_nonce( 'easy-language-create-schedules' ),
				),
				get_admin_url() . 'admin.php'
			);
			$result['status']      = 'recommended';
			$result['description'] = __( 'Cronjob to automatic simplify texts in your website does not exist!', 'easy-language' );
			/* translators: %1$s will be replaced by the URL to recreate the schedule */
			$result['actions'] = '<p><a href="' . esc_url( $url ) . '" class="button button-primary">' . esc_html__( 'Recreate the schedules', 'easy-language' ) . '</a></p>';

			// return this result.
			return $result;
		}

		// if scheduled event exist, check if next run is in the past.
		if ( $scheduled_event->timestamp < time() ) {
			$result['status'] = 'recommended';
			/* translators: %1$s will be replaced by the date of the planned next schedule run (which is in the past) */
			$result['description'] = sprintf( __( 'Cronjob to simplify texts should have been run at %1$s, but was not executed!<br><strong>Please check the cron-system of your WordPress-installation.</strong>', 'easy-language' ), Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', $scheduled_event->timestamp ) ) );

			// return this result.
			return $result;
		}

		// return result.
		return $result;
	}

	/**
	 * Check the active API.
	 *
	 * @return array<string,mixed>
	 */
	public function check_api(): array {
		// get actual API.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( false === $api_obj ) {
			// define default results.
			$result = array(
				'label'       => __( 'Easy Language API check', 'easy-language' ),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Easy Language', 'easy-language' ),
					'color' => 'gray',
				),
				'description' => __( 'To use simplification for texts in your website the Easy Language plugin uses APIs from SUMM AI, capito and ChatGpt which needs some configuration.<br><strong>No Api active!</strong>', 'easy-language' ),
				/* translators: %1$s will be replaced by the URL for API-settings */
				'actions'     => '<p><a href="' . esc_url( Helper::get_settings_page_url() ) . '" class="button button-primary">' . esc_html__( 'Choose an API', 'easy-language' ) . '</a></p>',
				'test'        => 'easy_language_check_api',
			);
		} else {
			// define default results.
			$result = array(
				'label'       => __( 'Easy Language API check', 'easy-language' ),
				'status'      => 'good',
				'badge'       => array(
					'label' => __( 'Easy Language', 'easy-language' ),
					'color' => 'gray',
				),
				/* translators: %1$s will be replaced by the API-title */
				'description' => sprintf( __( 'To use simplification for texts in your website the Easy Language plugin uses APIs from SUMM AI, capito and ChatGpt which needs some configuration.<br><strong>All ok with the actual active API %1$s!</strong>', 'easy-language' ), esc_html( $api_obj->get_title() ) ),
				'actions'     => '',
				'test'        => 'easy_language_check_api',
			);

			// switch to recommend if API is not configured.
			if ( false === $api_obj->is_configured() ) {
				$result['status'] = 'recommended';
				/* translators: %1$s will be replaced by the API-title */
				$result['description'] = sprintf( __( '<strong>The actual active API %1$s is not configured.</strong> It will not be usable to simplify texts in your website.', 'easy-language' ), esc_html( $api_obj->get_title() ) );
				/* translators: %1$s will be replaced with the API-settings-URL */
				$result['actions'] = '<p><a href="' . esc_url( $api_obj->get_settings_url() ) . '" class="button button-primary">' . esc_html__( 'Configure API now', 'easy-language' ) . '</a></p>';
			}
		}

		// return result.
		return $result;
	}
}
