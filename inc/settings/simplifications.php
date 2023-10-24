<?php
/**
 * File to handle the simplifications table in settings page.
 *
 * @package easy-language
 */

use easyLanguage\Helper;
use easyLanguage\Multilingual_plugins\Easy_Language\Texts_Table;

/**
 * Add settings-tab for simplification overview.
 *
 * @param string $tab The actually called tab.
 *
 * @return void
 */
function easy_language_settings_add_simplifications_tab( string $tab ): void {
	// check active tab.
	$active_class = '';
	if ( 'simplifications' === $tab ) {
		$active_class = ' nav-tab-active';
	}

	// output tab.
	echo '<a href="' . esc_url( Helper::get_settings_page_url() ) . '&tab=simplifications" class="nav-tab' . esc_attr( $active_class ) . '">' . esc_html__( 'Simplified texts', 'easy-language' ) . '</a>';
}
add_action( 'easy_language_settings_add_tab', 'easy_language_settings_add_simplifications_tab', 50 );

/**
 * Show simplification-table.
 *
 * @return void
 */
function easy_language_pro_add_simplifications(): void {
	$translations = new Texts_Table();
	$translations->prepare_items();
	?>
	<div class="wrap">
		<div id="icon-users" class="icon32"></div>
		<h2><?php echo esc_html__( 'Simplified texts', 'easy-language' ); ?></h2>
		<p><?php echo esc_html__( 'This table contains all by any API simplified texts. The original texts will not be simplified a second time.', 'easy-language' ); ?></p>
		<?php $translations->display(); ?>
	</div>
	<?php
}
add_action( 'easy_language_settings_simplifications_page', 'easy_language_pro_add_simplifications', 15 );
