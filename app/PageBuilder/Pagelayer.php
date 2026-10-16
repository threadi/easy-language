<?php
/**
 * File for handling Pagelayer as page builder.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\Plugin\Helper;

/**
 * Object to handle Pagelayer as page builder.
 *
 * Adds a language switcher widget to Pagelayer.
 */
class Pagelayer extends PageBuilder_Base {
	/**
	 * The tag of our widget. The prefix "pl_" is used by Pagelayer for CSS classes.
	 *
	 * @var string
	 */
	private const TAG = 'pl_easy_language_switcher';

	/**
	 * Instance of this object.
	 *
	 * @var ?Pagelayer
	 */
	private static ?Pagelayer $instance = null;

	/**
	 * Constructor for this object.
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
	public static function get_instance(): Pagelayer {
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
		add_action( 'pagelayer_load_custom_widgets', array( $this, 'register_widget' ) );
		add_action( 'pagelayer_custom_editor_enqueue', array( $this, 'add_editor_scripts' ) );
		add_action( 'wp_ajax_easy_language_pagelayer_switcher', array( $this, 'render_via_ajax' ) );
	}

	/**
	 * Register our language switcher widget in Pagelayer.
	 *
	 * @return void
	 */
	public function register_widget(): void {
		// bail if the Pagelayer function is not available.
		if ( ! function_exists( 'pagelayer_add_shortcode' ) ) {
			return;
		}

		// load the render-function, as Pagelayer only supports functions as callback.
		require_once __DIR__ . '/Pagelayer/functions.php';

		// get the labels.
		$labels = Switcher_Helper::get_labels();

		// register the widget.
		pagelayer_add_shortcode(
			self::TAG,
			array(
				'name'   => Switcher_Helper::get_title(),
				'group'  => 'other',
				'icon'   => 'fas fa-language',
				'func'   => 'easyLanguage\PageBuilder\Pagelayer\render_switcher',
				'html'   => '<div class="easy-language-switcher-pagelayer">{{{switcher}}}</div>',
				'params' => array(
					'show_icons'           => array(
						'type'  => 'checkbox',
						'label' => $labels['show_icons'],
					),
					'hide_actual_language' => array(
						'type'  => 'checkbox',
						'label' => $labels['hide_actual_language'],
					),
				),
				'links'  => array(
					'margin' => array(
						'type'   => 'padding',
						'label'  => $labels['margin'],
						'screen' => 1,
						'units'  => array( 'px', 'em', '%' ),
						'css'    => array(
							'{{element}} .easy-language-switcher-pagelayer a' => 'padding-top: {{val[0]}}; padding-right: {{val[1]}}; padding-bottom: {{val[2]}}; padding-left: {{val[3]}};',
						),
					),
				),
				'styles' => array(
					'links' => $labels['spacing'],
				),
			)
		);
	}

	/**
	 * Add the script for the Pagelayer editor, which updates the widget after changes of its settings.
	 *
	 * @return void
	 */
	public function add_editor_scripts(): void {
		wp_enqueue_script(
			'easy-language-pagelayer',
			Helper::get_plugin_url() . 'admin/pagelayer/pagelayer.js',
			array( 'jquery' ),
			Helper::get_file_version( Helper::get_plugin_path() . 'admin/pagelayer/pagelayer.js' ),
			true
		);
		wp_localize_script(
			'easy-language-pagelayer',
			'easyLanguagePagelayerJsVars',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'easy-language-pagelayer-switcher' ),
			)
		);
	}

	/**
	 * Return the output of the switcher for the Pagelayer editor via AJAX.
	 *
	 * @return void
	 */
	public function render_via_ajax(): void {
		// check the nonce.
		check_ajax_referer( 'easy-language-pagelayer-switcher', 'nonce' );

		// bail if the user is not allowed to edit posts.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die();
		}

		// get the settings.
		$show_icons           = ! empty( $_POST['show_icons'] );
		$hide_actual_language = ! empty( $_POST['hide_actual_language'] );

		// output the switcher.
		echo Switcher_Helper::get_output( $show_icons, $hide_actual_language ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'pagelayer/pagelayer.php' );
	}
}
