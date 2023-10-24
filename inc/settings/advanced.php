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
			'fieldId'     => 'easy_language_api_timeout',
			'description' => __( 'This value is in seconds. If you get a timeout from an API try to set this to a higher value.', 'easy-language' ),
		)
	);
	register_setting( 'easyLanguageAdvancedFields', 'easy_language_api_timeout' );

	// Set limit for texts per requests.
	add_settings_field(
		'easy_language_api_text_limit_per_process',
		__( 'Simplifications per process', 'easy-language' ),
		'easy_language_admin_number_field',
		'easyLanguageAdvancedPage',
		'settings_section_advanced',
		array(
			'label_for'   => 'easy_language_api_text_limit_per_process',
			'fieldId'     => 'easy_language_api_text_limit_per_process',
			'description' => __( 'This value is in seconds. If you get a timeout from an API try to set this to a higher value.', 'easy-language' ),
		)
	);
	register_setting( 'easyLanguageAdvancedFields', 'easy_language_api_text_limit_per_process' );

	// Set if unused simplifications should be deleted.
	add_settings_field(
		'easy_language_delete_unused_simplifications',
		__( 'Delete unused simplified texts', 'easy-language' ),
		'easy_language_admin_checkbox_field',
		'easyLanguageAdvancedPage',
		'settings_section_advanced',
		array(
			'label_for'   => 'easy_language_delete_unused_simplifications',
			'fieldId'     => 'easy_language_delete_unused_simplifications',
			'description' => __( 'If enabled any unused simplified texts will be deleted. To simplify the same text again, a new request must be sent to the API you are using, at the expense of your quota.<br>If disabled all simplified texts will be hold in your database. This could be at the expense of the size and performance of your database.', 'easy-language' ),
		)
	);
	register_setting( 'easyLanguageAdvancedFields', 'easy_language_delete_unused_simplifications' );

	// delete data button.
	add_settings_field(
		'easy_language_delete_data',
		__( 'Delete ALL simplifications', 'easy-language' ),
		'easy_language_admin_delete_data_now',
		'easyLanguageAdvancedPage',
		'settings_section_advanced'
	);

	// intro reset button.
	add_settings_field(
		'easy_language_reset_intro',
		__( 'Plugin Intro', 'easy-language' ),
		'easy_language_admin_reset_intro_now',
		'easyLanguageAdvancedPage',
		'settings_section_advanced'
	);

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

/**
 * Show reset-intro-button.
 *
 * @return void
 */
function easy_language_admin_reset_intro_now(): void {
	?>
	<p><a href="#" class="button button-primary easy-language-reset-intro"><?php echo esc_html__( 'Reset Intro', 'easy-language' ); ?></a></p>
	<p><i><?php echo esc_html__( 'Hint', 'easy-language' ); ?>:</i> <?php echo esc_html__( 'After click on this button the intro for this plugin will be re-initialized.', 'easy-language' ); ?></p>
	<?php
}

/**
 * Show button to delete all simplifications.
 *
 * @return void
 */
function easy_language_admin_delete_data_now(): void {
	?>
	<p><a href="#" class="button button-primary easy-language-delete-data"><?php echo esc_html__( 'Delete ALL simplification texts', 'easy-language' ); ?></a></p>
	<p><i><?php echo esc_html__( 'Hint', 'easy-language' ); ?>:</i> <?php echo esc_html__( 'After click on this button the intro for this plugin will be re-initialized.', 'easy-language' ); ?></p>
	<?php
}
