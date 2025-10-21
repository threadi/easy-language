<?php
/**
 * File for icon management for this plugin.
 *
 * @package easy-language
 */

use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Tables\Language_Icons_Table;

/**
 * Add settings-tab for this plugin.
 *
 * @param string $tab The actually called tab.
 *
 * @return void
 */
function easy_language_settings_add_icons_tab( string $tab ): void {
	// check active tab.
	$active_class = '';
	if ( 'icons' === $tab ) {
		$active_class = ' nav-tab-active';
	}

	// output tab.
	echo '<a href="' . esc_url( Helper::get_settings_page_url() ) . '&tab=icons" class="nav-tab' . esc_attr( $active_class ) . '">' . esc_html__( 'Icons', 'easy-language' ) . '</a>';
}
add_action( 'easy_language_settings_add_tab', 'easy_language_settings_add_icons_tab', 40 );

/**
 * Page for icon management settings.
 *
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_admin_add_icon_management(): void {
	// check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// embed media library.
	wp_enqueue_media();

	// if WP_List_Table is not loaded automatically, we need to load it.
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	$log = new Language_Icons_Table();
	$log->prepare_items();
	?>
	<div class="wrap easy-language-icons">
		<div id="icon-users" class="icon32"></div>
		<h2><?php echo esc_html__( 'Icons for languages', 'easy-language' ); ?></h2>
		<p><?php echo esc_html__( 'Manage the icons used by the language-switcher in the frontend of your website.', 'easy-language' ); ?></p>
		<?php $log->display(); ?>
	</div>
	<?php
}
add_action( 'easy_language_settings_icons_page', 'easy_language_admin_add_icon_management' );
