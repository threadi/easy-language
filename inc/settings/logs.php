<?php
/**
 * File to output logs.
 *
 * @package easy-language
 */

use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Tables\Log_Table;

/**
 * Add tab in settings for logs.
 *
 * @param string $tab The called tab.
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_settings_add_logs_tab( string $tab ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// check active tab.
	$active_class = '';
	if ( 'logs' === $tab ) {
		$active_class = ' nav-tab-active';
	}

	// output tab.
	echo '<a href="' . esc_url( Helper::get_settings_page_url() ) . '&tab=logs" class="nav-tab' . esc_attr( $active_class ) . '">' . esc_html__( 'Logs', 'easy-language' ) . '</a>';
}
add_action( 'easy_language_settings_add_tab', 'easy_language_settings_add_logs_tab', 100 );

/**
 * Show log as list.
 *
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_admin_add_menu_content_logs(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// if WP_List_Table is not loaded automatically, we need to load it.
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	?>
	<div class="wrap">
		<h2><?php echo esc_html__( 'General Logs', 'easy-language' ); ?></h2>
		<?php
		$log_table = new Log_Table();
		$log_table->prepare_items();
		$log_table->views();
		$log_table->display();
		?>
	</div>
	<?php
}
add_action( 'easy_language_settings_logs_page', 'easy_language_admin_add_menu_content_logs' );
