<?php
/**
 * File for advanced settings for this plugin.
 *
 * @package easy-language
 */

use easyLanguage\Helper;

/**
 * Add tab in advanced settings.
 *
 * @param string $tab The called tab.
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_settings_add_advanced_tab( string $tab ): void {
	// check active tab.
	$active_class = '';
	if ( 'advanced' === $tab ) {
		$active_class = ' nav-tab-active';
	}

	// output tab.
	echo '<a href="' . esc_url( Helper::get_settings_page_url() ) . '&tab=advanced" class="nav-tab' . esc_attr( $active_class ) . '">' . esc_html__( 'Advanced', 'easy-language' ) . '</a>';
}
add_action( 'easy_language_settings_add_tab', 'easy_language_settings_add_advanced_tab', 60, 1 );

/**
 * Page for advanced settings.
 *
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_admin_add_menu_content_advanced_settings(): void {
	// check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	?>
	<form method="POST" action="<?php echo esc_url( get_admin_url() ); ?>options.php">
		<?php
		settings_fields( 'easyLanguageAdvancedFields' );
		do_settings_sections( 'easyLanguageAdvancedPage' );
		submit_button();
		?>
	</form>
	<?php
}
add_action( 'easy_language_settings_advanced_page', 'easy_language_admin_add_menu_content_advanced_settings' );

/**
 * Get general options.
 *
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_admin_add_settings_advanced(): void {
	/**
	 * Advanced Section
	 */
	add_settings_section(
		'settings_section_advanced',
		__( 'Advanced Settings', 'easy-language' ),
		'__return_true',
		'easyLanguageAdvancedPage'
	);

	// Set max log age in days.
	add_settings_field(
		'easy_language_log_max_age',
		__( 'Set max age for log entries', 'easy-language' ),
		'easy_language_admin_number_field',
		'easyLanguageAdvancedPage',
		'settings_section_advanced',
		array(
			'label_for'   => 'easy_language_log_max_age',
			'fieldId'     => 'easy_language_log_max_age',
			'description' => __( 'Older log-entries will be deleted automatically.', 'easy-language' ),
		)
	);
	register_setting( 'easyLanguageAdvancedFields', 'easy_language_log_max_age' );

	// Set timeout for each API-request.
	add_settings_field(
		'easy_language_api_timeout',
		__( 'Timeout for API-requests', 'easy-language' ),
		'easy_language_admin_number_field',
		'easyLanguageAdvancedPage',
		'settings_section_advanced',
		array(
			'label_for'   => 'easy_language_api_timeout',
			'fieldId'     => 'easy_language_api_timeout'
		)
	);
	register_setting( 'easyLanguageAdvancedFields', 'easy_language_api_timeout' );

	// Debug-Mode.
	add_settings_field(
		'easy_language_debug_mode',
		__( 'Debug-Mode', 'easy-language' ),
		'easy_language_admin_checkbox_field',
		'easyLanguageAdvancedPage',
		'settings_section_advanced',
		array(
			'label_for'   => 'easy_language_debug_mode',
			'fieldId'     => 'easy_language_debug_mode',
			'description' => __( 'If enabled the plugin will log every API action.', 'easy-language' ),
		)
	);
	register_setting( 'easyLanguageAdvancedFields', 'easy_language_debug_mode' );
}
add_action( 'easy_language_settings_add_settings', 'easy_language_admin_add_settings_advanced' );
