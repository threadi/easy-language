<?php
/**
 * Hooks used only in WordPress-backend.
 *
 * @package easy-language
 */

use easyLanguage\Helper;
use easyLanguage\Multilingual_Plugins;
use easyLanguage\Transients;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add own CSS and JS for backend.
 *
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_add_styles_and_js_admin(): void {
	// admin-specific styles.
	wp_enqueue_style(
		'easy-language-admin',
		plugin_dir_url( EASY_LANGUAGE ) . '/admin/style.css',
		array(),
		filemtime( plugin_dir_path( EASY_LANGUAGE ) . '/admin/style.css' ),
	);

	// backend-JS.
	wp_enqueue_script(
		'easy-language-admin',
		plugins_url( '/admin/js.js', EASY_LANGUAGE ),
		array( 'jquery', 'easy-dialog', 'wp-i18n' ),
		filemtime( plugin_dir_path( EASY_LANGUAGE ) . '/admin/js.js' ),
		true
	);

	// add php-vars to our backend-js-script.
	wp_localize_script(
		'easy-language-admin',
		'easyLanguageJsVars',
		array(
			'ajax_url'                    => admin_url( 'admin-ajax.php' ),
			'admin_start'                 => admin_url(),
			'dismiss_nonce'               => wp_create_nonce( 'easy-language-dismiss-nonce' ),
			'reset_intro_nonce'           => wp_create_nonce( 'easy-language-reset-intro-nonce' ),
			'run_delete_data_nonce'       => wp_create_nonce( 'easy-language-delete-data-nonce' ),
			'get_delete_data_nonce'       => wp_create_nonce( 'easy-language-get-delete-data-nonce' ),
			'set_icon_for_language_nonce' => wp_create_nonce( 'easy-language-set-icon-for-language' ),
			'review_url' => 'https://wordpress.org/plugins/easy-language/#reviews',
			'title_rate_us' => __( 'Rate us', 'easy-language' ),
			'pro_url' => esc_url( Helper::get_pro_url() ),
			'title_get_pro' => __( 'Get Pro', 'easy-language' )
		)
	);

	// add only scripts and styles of enabled plugins.
	foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
		$plugin_obj->get_simplifications_scripts();
	}
}
add_action( 'admin_enqueue_scripts', 'easy_language_add_styles_and_js_admin', PHP_INT_MAX );

/**
 * Show known transients only for users with rights.
 *
 * @return void
 */
function easy_language_admin_notices(): void {
	if ( current_user_can( 'manage_options' ) ) {
		$transients_obj = Transients::get_instance();
		$transients_obj->check_transients();
	}
}
add_action( 'admin_notices', 'easy_language_admin_notices' );

/**
 * Process dismiss of notices in wp-backend.
 *
 * @return void
 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
 */
function easy_language_admin_dismiss(): void {
	// check nonce.
	check_ajax_referer( 'easy-language-dismiss-nonce', 'nonce' );

	// get values.
	$option_name        = isset( $_POST['option_name'] ) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : false;
	$dismissible_length = isset( $_POST['dismissible_length'] ) ? sanitize_text_field( wp_unslash( $_POST['dismissible_length'] ) ) : 14;

	if ( 'forever' !== $dismissible_length ) {
		// if $dismissible_length is not an integer default to 14.
		$dismissible_length = ( 0 === absint( $dismissible_length ) ) ? 14 : $dismissible_length;
		$dismissible_length = strtotime( absint( $dismissible_length ) . ' days' );
	}

	// save value.
	update_option( 'el-dismissed-' . md5( $option_name ), $dismissible_length, true );

	// remove transient.
	Transients::get_instance()->get_transient_by_name( $option_name )->delete();

	// return nothing.
	wp_die();
}
add_action( 'wp_ajax_dismiss_admin_notice', 'easy_language_admin_dismiss' );

/**
 * Add link to plugin-settings in plugin-list.
 *
 * @param array $links List of links to show in plugin-list on this specific plugin.
 * @return array
 * @noinspection PhpUnused
 */
function easy_language_admin_add_setting_link( array $links ): array {
	// create the link.
	$settings_link = "<a href='" . esc_url( Helper::get_settings_page_url() ) . "'>" . __( 'Settings', 'easy-language' ) . '</a>';

	// adds the link to the end of the array.
	$links[] = $settings_link;

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( EASY_LANGUAGE ), 'easy_language_admin_add_setting_link' );
