<?php
/**
 * File to handle the simplifications table in settings page.
 *
 * @package easy-language
 */

use easyLanguage\Helper;
use easyLanguage\Multilingual_plugins\Easy_Language\Texts_In_Use_Table;
use easyLanguage\Multilingual_plugins\Easy_Language\Texts_To_Simplify_Table;

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
	// get the active tab from the $_GET param.
	$subtab = isset( $_GET['subtab'] ) ? sanitize_text_field( wp_unslash( $_GET['subtab'] ) ) : '';

	?>
	<div class="wrap">
		<div id="icon-users" class="icon32"></div>
		<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url( Helper::get_settings_page_url() ); ?>&amp;tab=simplifications" class="nav-tab<?php if( empty($subtab) ) { ?> nav-tab-active<?php } ?>"><?php esc_html_e( 'in use', 'easy-language' ); ?></a>
			<a href="<?php echo esc_url( Helper::get_settings_page_url() ); ?>&amp;tab=simplifications&amp;subtab=to_simplify" class="nav-tab<?php if( 'to_simplify' === $subtab ) { ?> nav-tab-active<?php } ?>"><?php esc_html_e( 'to simplify', 'easy-language' ); ?></a>
		</nav>

		<div class="tab-content">
			<?php
			// get the content of the actual sub tab.
			do_action( 'easy_language_settings_simplifications_' . $subtab . '_page' );
			?>
		</div>
	</div>
	<?php
}
add_action( 'easy_language_settings_simplifications_page', 'easy_language_pro_add_simplifications', 15 );

/**
 * Show simplified texts in use.
 *
 * @return void
 */
function easy_language_settings_simplifications_in_use(): void {
	$translations = new Texts_In_Use_Table();
	$translations->prepare_items();
	?>
	<h2><?php echo esc_html__( 'Simplified texts in use', 'easy-language' ); ?></h2>
	<p><?php echo esc_html__( 'This table contains all simplified texts. The original texts will not be simplified a second time.', 'easy-language' ); ?></p>
	<?php $translations->display();
}
add_action( 'easy_language_settings_simplifications__page', 'easy_language_settings_simplifications_in_use' );

/**
 * Show texts which will be simplified.
 *
 * @return void
 */
function easy_language_settings_simplifications_to_simplify(): void {
	$translations = new Texts_To_Simplify_Table();
	$translations->prepare_items();
	?>
	<h2><?php echo esc_html__( 'Texts to simplify', 'easy-language' ); ?></h2>
	<p><?php echo esc_html__( 'This table contains texts which will be simplified. They are processed by a background-process.', 'easy-language' ); ?></p>
	<?php $translations->display();
}
add_action( 'easy_language_settings_simplifications_to_simplify_page', 'easy_language_settings_simplifications_to_simplify' );
