<?php
/**
 * File to handle the simplifications table in settings page.
 *
 * @package easy-language
 */

use easyLanguage\Apis;
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
	$subtab = filter_input( INPUT_GET, 'subtab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	if ( ! is_null( $subtab ) ) {
		$subtab = 'to_simplify';
	}

	?>
	<div class="wrap">
		<div id="icon-users" class="icon32"></div>
		<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url( Helper::get_settings_page_url() ); ?>&amp;tab=simplifications" class="nav-tab
								<?php
								if ( empty( $subtab ) ) {
									?>
				nav-tab-active<?php } ?>"><?php esc_html_e( 'in use', 'easy-language' ); ?></a>
			<a href="<?php echo esc_url( Helper::get_settings_page_url() ); ?>&amp;tab=simplifications&amp;subtab=to_simplify" class="nav-tab
								<?php
								if ( 'to_simplify' === $subtab ) {
									?>
				nav-tab-active<?php } ?>"><?php esc_html_e( 'to simplify', 'easy-language' ); ?></a>
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
	?>
	<h2><?php echo esc_html__( 'Simplified texts in use', 'easy-language' ); ?></h2>
	<p><?php echo esc_html__( 'This table contains all simplified texts. The original texts will not be simplified a second time.', 'easy-language' ); ?></p>
	<?php
	$simplifications_table = new Texts_In_Use_Table();
	$simplifications_table->prepare_items();
	$simplifications_table->views();
	$simplifications_table->display();
}
add_action( 'easy_language_settings_simplifications__page', 'easy_language_settings_simplifications_in_use' );

/**
 * Show texts which will be simplified.
 *
 * @return void
 */
function easy_language_settings_simplifications_to_simplify(): void {
	// get API-object.
	$api_obj = Apis::get_instance()->get_active_api();

	// show hint if not API is active.
	if ( false === $api_obj ) {
		?>
		<h2><?php echo esc_html__( 'Texts to simplify', 'easy-language' ); ?></h2><p><?php echo esc_html__( 'No API active which could simplify texts.', 'easy-language' ); ?></p>
		<?php
		return;
	}

	// get table object to show text to simplify.
	?>
	<h2><?php echo esc_html__( 'Texts to simplify', 'easy-language' ); ?></h2>
	<p>
		<?php
		/* translators: %1$s will be replaced by the API-title */
		echo esc_html( sprintf( __( 'This table contains texts which will be simplified via %1$s. They are processed by a background-process.', 'easy-language' ), esc_html( $api_obj->get_title() ) ) );
		?>
	</p>
	<?php
	$simplifications_table = new Texts_To_Simplify_Table();
	$simplifications_table->prepare_items();
	$simplifications_table->views();
	$simplifications_table->display();
}
add_action( 'easy_language_settings_simplifications_to_simplify_page', 'easy_language_settings_simplifications_to_simplify' );
