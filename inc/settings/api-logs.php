<?php
/**
 * File to output API logs.
 *
 * @package easy-language
 */

use easyLanguage\Helper;
use easyLanguage\Log_Api_Table;

/**
 * Add tab in settings for logs of the API.
 *
 * @param string $tab The called tab.
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_settings_add_api_logs_tab( string $tab ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// check active tab.
	$active_class = '';
	if ( 'api_logs' === $tab ) {
		$active_class = ' nav-tab-active';
	}

	// output tab.
	echo '<a href="' . esc_url( Helper::get_settings_page_url() ) . '&tab=api_logs" class="nav-tab' . esc_attr( $active_class ) . '">' . esc_html__( 'API Logs', 'easy-language' ) . '</a>';
}
add_action( 'easy_language_settings_add_tab', 'easy_language_settings_add_api_logs_tab', 100, 1 );

/**
 * Show log as list.
 *
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_admin_add_menu_content_api_logs(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// if WP_List_Table is not loaded automatically, we need to load it.
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	$log = new Log_Api_Table();
	$log->prepare_items();
	?>
	<div class="wrap">
		<div id="icon-users" class="icon32"></div>
		<h2><?php echo esc_html__( 'API Logs', 'easy-language' ); ?></h2>
		<?php $log->display(); ?>
	</div>
	<?php
}
add_action( 'easy_language_settings_api_logs_page', 'easy_language_admin_add_menu_content_api_logs' );
