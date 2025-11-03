<?php
/**
 * File to handle plugin-settings.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Dependencies\easySettingsForWordPress\Fields\Button;
use easyLanguage\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use easyLanguage\Dependencies\easySettingsForWordPress\Fields\Number;
use easyLanguage\Dependencies\easySettingsForWordPress\Fields\Radio;
use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use easyLanguage\EasyLanguage\Tables\Texts_In_Use_Table;
use easyLanguage\EasyLanguage\Tables\Texts_To_Simplify_Table;
use easyLanguage\Plugin\Tables\Language_Icons_Table;
use easyLanguage\Plugin\Tables\Log_Api_Table;
use easyLanguage\Plugin\Tables\Log_Table;

/**
 * Object tot handle settings.
 */
class Settings {
	/**
	 * Instance of this object.
	 *
	 * @var ?Settings
	 */
	private static ?Settings $instance = null;

	/**
	 * Constructor for Settings-Handler.
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
	public static function get_instance(): Settings {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the settings.
	 *
	 * @return void
	 */
	public function init(): void {
		// set all settings for this plugin.
		add_action( 'init', array( $this, 'add_the_settings' ) );
	}

	/**
	 * Define the main settings for this plugin.
	 *
	 * @return void
	 */
	public function add_the_settings(): void {
		/**
		 * Configure the basic settings object.
		 */
		$settings_obj  = \easyLanguage\Dependencies\easySettingsForWordPress\Settings::get_instance();
		$settings_obj->set_slug( 'easy_language' );
		$settings_obj->set_plugin_slug( EASY_LANGUAGE );
		$settings_obj->set_menu_title( _x( 'Easy Language', 'settings menu title', 'easy-language' ) );
		$settings_obj->set_title( __( 'Easy Language settings', 'easy-language' ) );
		$settings_obj->set_menu_slug( 'easy_language_settings' );
		$settings_obj->set_menu_parent_slug( 'options-general.php' );
		$settings_obj->set_url( Helper::get_plugin_url() . '/app/Dependencies/easySettingsForWordPress/' );
		$settings_obj->set_path( Helper::get_plugin_path() . '/app/Dependencies/easySettingsForWordPress/' );
		$settings_obj->set_translations(
			array(
				'title_settings_import_file_missing' => __( 'Required file missing', 'easy-language' ),
				'text_settings_import_file_missing'  => __( 'Please choose a JSON-file with settings to import.', 'easy-language' ),
				'lbl_ok'                             => __( 'OK', 'easy-language' ),
				'lbl_cancel'                         => __( 'Cancel', 'easy-language' ),
				'import_title'                       => __( 'Import settings', 'easy-language' ),
				'dialog_import_title'                => __( 'Import plugin settings', 'easy-language' ),
				'dialog_import_text'                 => __( 'Click on the button below to chose your JSON-file with the settings.', 'easy-language' ),
				'dialog_import_button'               => __( 'Import now', 'easy-language' ),
				'dialog_import_error_title'          => __( 'Error during import', 'easy-language' ),
				'dialog_import_error_text'           => __( 'The file could not be imported!', 'easy-language' ),
				'dialog_import_error_no_file'        => __( 'No file was uploaded.', 'easy-language' ),
				'dialog_import_error_no_size'        => __( 'The uploaded file is no size.', 'easy-language' ),
				'dialog_import_error_no_json'        => __( 'The uploaded file is not a valid JSON-file.', 'easy-language' ),
				'dialog_import_error_no_json_ext'    => __( 'The uploaded file does not have the file extension <i>.json</i>.', 'easy-language' ),
				'dialog_import_error_not_saved'      => __( 'The uploaded file could not be saved. Contact your hoster about this problem.', 'easy-language' ),
				'dialog_import_error_not_our_json'   => __( 'The uploaded file is not a valid JSON-file with settings for this plugin.', 'easy-language' ),
				'dialog_import_success_title'        => __( 'Settings have been imported', 'easy-language' ),
				'dialog_import_success_text'         => __( 'Import has been run successfully.', 'easy-language' ),
				'dialog_import_success_text_2'       => __( 'The new settings are now active. Click on the button below to reload the page and see the settings.', 'easy-language' ),
				'export_title'                       => __( 'Export settings', 'easy-language' ),
				'dialog_export_title'                => __( 'Export plugin settings', 'easy-language' ),
				'dialog_export_text'                 => __( 'Click on the button below to export the actual settings.', 'easy-language' ),
				'dialog_export_text_2'               => __( 'You can import this JSON-file in other projects using this WordPress plugin or theme.', 'easy-language' ),
				'dialog_export_button'               => __( 'Export now', 'easy-language' ),
				'table_options'                      => __( 'Options', 'easy-language' ),
				'table_entry'                        => __( 'Entry', 'easy-language' ),
				'table_no_entries'                   => __( 'No entries found.', 'easy-language' ),
				'plugin_settings_title'              => __( 'Settings', 'easy-language' ),
				'file_add_file'                      => __( 'Add file', 'easy-language' ),
				'file_choose_file'                   => __( 'Choose file', 'easy-language' ),
				'file_choose_image'                  => __( 'Upload or choose image', 'easy-language' ),
				'drag_n_drop'                        => __( 'Hold to drag & drop', 'easy-language' ),
			)
		);

		// initialize the settings-object if setup has been completed or if this is a REST API request.
		if ( Helper::is_rest_request() || Setup::get_instance()->is_completed() ) {
			$settings_obj->init();
		}

		// create the settings page.
		$settings_page = $settings_obj->add_page( 'easy_language_settings' );

		/**
		 * Configure all tabs for this object.
		 */
		// the API tab.
		$api_tab = $settings_page->add_tab( 'basic', 10 );
		$api_tab->set_title( __( 'API', 'easy-language' ) );
		$settings_page->set_default_tab( $api_tab );

		// the advanced tab.
		$advanced_tab = $settings_page->add_tab( 'advanced', 40 );
		$advanced_tab->set_title( __( 'Advanced', 'easy-language' ) );

		// the simplified texts tab.
		$simplified_texts_tab = $settings_page->add_tab( 'simplified_texts', 50 );
		$simplified_texts_tab->set_title( __( 'Simplified texts', 'easy-language' ) );
		$simplified_texts_tab->set_hide_save( true );

		// the API logs tab.
		$api_logs_tab = $settings_page->add_tab( 'api_logs', 60 );
		$api_logs_tab->set_title( __( 'API Logs', 'easy-language' ) );

		// the icon tab.
		$icons_tab = $settings_page->add_tab( 'icons', 70 );
		$icons_tab->set_title( __( 'Icons', 'easy-language' ) );

		// the log tab.
		$logs_tab = $settings_page->add_tab( 'logs', 80 );
		$logs_tab->set_title( __( 'Logs', 'easy-language' ) );

		// the help tab.
		$help_tab = $settings_page->add_tab( 'help', 1000 );
		$help_tab->set_title( __( 'Need help?', 'easy-language' ) );
		$help_tab->set_url( Helper::get_plugin_support_url() );
		$help_tab->set_tab_class( 'easy-language-help-tab' );
		$help_tab->set_url_target( '_blank' );

		/**
		 * Add sub-tab for simplified texts.
		 */
		// tab for text in use.
		$simplified_texts_in_use_tab = $simplified_texts_tab->add_tab( 'simplified_texts_in_use', 10 );
		$simplified_texts_in_use_tab->set_title( __( 'Simplified texts in use', 'easy-language' ) );
		$simplified_texts_in_use_tab->set_callback( array( $this, 'render_simplified_texts_in_use' ) );
		$simplified_texts_in_use_tab->set_hide_save( true );
		$simplified_texts_tab->set_default_tab( $simplified_texts_in_use_tab );

		// tab for texts to simplify.
		$simplified_texts_to_simplify_use_tab = $simplified_texts_tab->add_tab( 'simplified_texts_to_simplify', 10 );
		$simplified_texts_to_simplify_use_tab->set_title( __( 'Texts to simplify', 'easy-language' ) );
		$simplified_texts_to_simplify_use_tab->set_callback( array( $this, 'render_simplified_texts_to_simplify' ) );
		$simplified_texts_to_simplify_use_tab->set_hide_save( true );

		/**
		 * Configure all sections for this object.
		 */
		// the API section.
		$api_tab_main = $api_tab->add_section( 'api', 10 );
		$api_tab_main->set_setting( $settings_obj );

		// the advanced section.
		$advanced_tab_main = $advanced_tab->add_section( 'advanced_main', 10 );
		$advanced_tab_main->set_title( __( 'Advanced settings', 'easy-language' ) );
		$advanced_tab_main->set_setting( $settings_obj );

		// the API logs section.
		$api_logs_tab_main = $api_logs_tab->add_section( 'api_logs_main', 10 );
		$api_logs_tab_main->set_callback( array( $this, 'render_api_logs' ) );
		$api_logs_tab_main->set_setting( $settings_obj );

		// the icons section.
		$icons_tab_main = $icons_tab->add_section( 'icons_main', 10 );
		$icons_tab_main->set_callback( array( $this, 'render_icons' ) );
		$icons_tab_main->set_setting( $settings_obj );

		// the icons section.
		$logs_tab_main = $logs_tab->add_section( 'logs_main', 10 );
		$logs_tab_main->set_callback( array( $this, 'render_logs' ) );
		$logs_tab_main->set_setting( $settings_obj );

		/**
		 * Configure all settings for this object.
		 */
		// get the list of supported APIs.
		$apis = array();
		foreach ( Apis::get_instance()->get_available_apis() as $api_obj ) {
			$apis[ $api_obj->get_name() ] = $api_obj->get_title();
		}

		// add setting.
		// TODO Styling anpassen an bisherigen Aufbau.
		$setting = $settings_obj->add_setting( 'easy_language_api' );
		$setting->set_section( $api_tab_main );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'string' );
		$setting->set_default( 'summ_ai' );
		$setting->set_save_callback( array( $this, 'save_api' ) );
		$field = new Radio();
		$field->set_title( __( 'Select API', 'easy-language' ) );
		$field->set_description( __( 'Please choose the API you want to use to simplify texts your website.', 'easy-language' ) );
		$field->set_options( $apis );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_log_max_age' );
		$setting->set_section( $advanced_tab_main );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'integer' );
		$setting->set_default( 50 );
		$field = new Number();
		$field->set_title( __( 'Set max age for log entries', 'easy-language' ) );
		$field->set_description( __( 'Older log-entries will be deleted automatically.', 'easy-language' ) );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_api_timeout' );
		$setting->set_section( $advanced_tab_main );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'integer' );
		$setting->set_default( 30 );
		$field = new Number();
		$field->set_title( __( 'Timeout for API-requests', 'easy-language' ) );
		$field->set_description( __( 'This value is in seconds. If you get a timeout from an API try to set this to a higher value.', 'easy-language' ) );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_api_text_limit_per_process' );
		$setting->set_section( $advanced_tab_main );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Number();
		$field->set_title( __( 'Simplifications per AJAX-request', 'easy-language' ) );
		$field->set_description( __( 'This number of simplifications is carried out per run on an API.', 'easy-language' ) );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_delete_unused_simplifications' );
		$setting->set_section( $advanced_tab_main );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Checkbox();
		$field->set_title( __( 'Delete unused simplified texts', 'easy-language' ) );
		$field->set_description( __( 'If this is enabled, any unused simplified texts will be deleted. To simplify the same text again, a new request must be sent to the API you are using, at the expense of your quota.<br>If disabled all simplified texts will be hold in your database. This could be at the expense of the size and performance of your database.', 'easy-language' ) );
		$setting->set_field( $field );

		// create dialog to start deletion.
		$dialog_config = array(
			'hide_title' => true,
			'texts'      => array(
				'<p><strong>' . __( 'Do you really want to delete all simplifications?', 'easy-language' ) . '</strong></p>',
			),
			'buttons'    => array(
				array(
					'action'  => 'easy_language_start_data_deletion();',
					'variant' => 'primary',
					'text'    => __( 'Yes', 'easy-language' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'No', 'easy-language' ),
				),
			)
		);

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_delete_data' );
		$setting->set_section( $advanced_tab_main );
		$setting->set_show_in_rest( false );
		$setting->prevent_export( true );
		$field = new Button();
		$field->set_title( __( 'Delete ALL simplified texts', 'easy-language' ) );
		$field->set_description( __( 'After click on this button all simplified data will be deleted. Your contents will not be changed..', 'easy-language' ) );
		$field->set_button_title( __( 'Delete now', 'easy-language' ) );
		$field->set_button_url( '#' );
		$field->add_class( 'easy-dialog-for-wordpress' );
		$field->add_data( 'dialog', Helper::get_json( $dialog_config ) );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_reset_intro' );
		$setting->set_section( $advanced_tab_main );
		$setting->set_show_in_rest( false );
		$setting->prevent_export( true );
		$field = new Button();
		$field->set_title( __( 'Plugin Intro', 'easy-language' ) );
		$field->set_description( __( 'After click on this button the intro for this plugin will be re-initialized.', 'easy-language' ) );
		$field->set_button_title( __( 'Reset Intro', 'easy-language' ) );
		$field->set_button_url( '#' );
		$field->add_class( 'easy-language-reset-intro' );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_debug_mode' );
		$setting->set_section( $advanced_tab_main );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Debug-Mode', 'easy-language' ) );
		$field->set_description( __( 'If enabled the plugin will log every API action.', 'easy-language' ) );
		$setting->set_field( $field );

		// create a hidden page for hidden settings.
		$hidden_page = $settings_obj->add_page( 'hidden_page' );

		// create a hidden tab on this page.
		$hidden_tab = $hidden_page->add_tab( 'hidden_tab', 10 );

		// the hidden section for any not visible settings.
		$hidden = $hidden_tab->add_section( 'hidden_section', 20 );
		$hidden->set_setting( $settings_obj );

		// initialize the settings.
		$settings_obj->init();
	}

	/**
	 * Validate the chosen API.
	 *
	 * @param string $value The internal name of the chosen API.
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function save_api( string $value ): string {
		// get the new post-state for objects of the former API.
		$post_state = get_option( 'easy_language_state_on_api_change', 'draft' );

		// get the actual API.
		$api = Apis::get_instance()->get_api_by_name( get_option( 'easy_language_api', '' ) );

		// if the actual API is not the new API and changing post-state is not disabled, go further.
		if ( $api && $value !== $api->get_name() && 'disabled' !== $post_state ) {
			// get the simplified objects of the former API (all of them).
			$post_type_objects = $api->get_simplified_post_type_objects();

			// loop through the object and change their state.
			foreach ( $post_type_objects as $post_type_object_id ) {
				// save the previous state.
				update_post_meta( $post_type_object_id, 'easy_language_simplification_state_changed_from', get_post_status( $post_type_object_id ) );

				// update object.
				$array = array(
					'ID'          => $post_type_object_id,
					'post_status' => $post_state,
				);
				wp_update_post( $array );
			}
		}

		// get the new API.
		$new_api = Apis::get_instance()->get_api_by_name( $value );

		// Remove intro-hint if it is enabled.
		if ( 1 === absint( get_option( 'easy_language_intro_step_2', 0 ) ) ) {
			delete_option( 'easy_language_intro_step_2' );
		}

		// Check if API has been saved first time and the new API is already configured (or no configuration at all),
		// to show intro part 2.
		if ( $new_api && ! get_option( 'easy_language_intro_step_2' ) && ( false !== $new_api->is_configured() || false === $new_api->has_settings() ) ) {
			update_option( 'easy_language_intro_step_2', 1 );
		}

		// if the new API is valid and setting has been changed.
		if ( $new_api && $api && $api->get_name() !== $new_api->get_name() ) {
			// get the simplified objects of the new API (all of them).
			$post_type_objects = $new_api->get_simplified_post_type_objects();

			// loop through the object and change their to its previous state.
			foreach ( $post_type_objects as $post_type_object_id ) {
				// get the previous state.
				$new_post_state = get_post_meta( $post_type_object_id, 'easy_language_simplification_state_changed_from', true );

				// update object.
				$array = array(
					'ID'          => $post_type_object_id,
					'post_status' => $new_post_state,
				);
				wp_update_post( $array );

				// delete the setting for previous state.
				delete_post_meta( $post_type_object_id, 'easy_language_simplification_state_changed_from' );
			}

			// Enable a hint if the user has not configured the new API yet and if this API has no translation-objects.
			if ( empty( $post_type_objects ) && false === $new_api->is_configured() ) {
				$links              = '';
				$post_type_settings = \easyLanguage\Plugin\Init::get_instance()->get_post_type_settings();
				$post_types         = Init::get_instance()->get_supported_post_types();
				$post_types_count   = count( $post_types );
				$post_types_counter = 0;
				foreach ( $post_types as $post_type => $enabled ) {
					if ( ( $post_types_count - 1 ) === $post_types_counter ) {
						$links .= ' ' . esc_html__( 'and', 'easy-language' ) . ' ';
					}
					$links .= '<a href="' . esc_url( $post_type_settings[ $post_type ]['admin_edit_url'] ) . '">' . $post_type_settings[ $post_type ]['label_plural'] . '</a>';
					++$post_types_counter;
				}
				$transient_obj = Transients::get_instance()->add();
				$transient_obj->set_dismissible_days( 90 );
				$transient_obj->set_name( 'easy_language_api_changed' );
				if ( $transient_obj->is_set() ) {
					$transient_obj->delete();
				}
				if ( $new_api->has_settings() ) {
					/* translators: %1$s will be replaced by the name of the active API, %2%s will be replaced by the settings-URL for this API, %3$s will be replaced by list of post-types and their links in wp-admin. */
					$transient_obj->set_message( sprintf( __( '<strong>You have activated %1$s as API to simplify your texts.</strong> Please check now the <a href="%2$s">API-settings</a> before you could use %1$s.', 'easy-language' ), esc_html( $new_api->get_title() ), esc_url( $new_api->get_settings_url() ) ) );
				} else {
					/* translators: %1$s will be replaced by the name of the active API, %2$s will be replaced by list of post-types and their links in wp-admin. */
					$transient_obj->set_message( sprintf( __( '<strong>You have activated %1$s as API to simplify your texts.</strong> Go now to your %2$s and simplify them with %1$s.', 'easy-language' ), esc_html( $new_api->get_title() ), wp_kses_post( $links ) ) );
				}
				$transient_obj->set_type( 'hint' );
				$transient_obj->save();
			} else {
				// delete API-settings hint.
				Transients::get_instance()->get_transient_by_name( 'easy_language_api_changed' )->delete();
			}
		}

		// return the chosen api-name.
		return $value;
	}

	/**
	 * Render the API logs as table.
	 *
	 * @return void
	 */
	public function render_api_logs(): void {
		?>
			<h2><?php echo esc_html__( 'API Logs', 'easy-language' ); ?></h2>
		<?php
		$log_table = new Log_Api_Table();
		$log_table->prepare_items();
		$log_table->views();
		$log_table->display();
	}

	/**
	 * Render the icons as table.
	 *
	 * @return void
	 */
	public function render_icons(): void {
		?>
			<h2><?php echo esc_html__( 'Icons for languages', 'easy-language' ); ?></h2>
			<p><?php echo esc_html__( 'Manage the icons used by the language-switcher in the frontend of your website.', 'easy-language' ); ?></p>
		<?php
		$log = new Language_Icons_Table();
		$log->prepare_items();
		$log->display();
	}

	/**
	 * Render the logs as table.
	 *
	 * @return void
	 */
	public function render_logs(): void {
		?>
			<h2><?php echo esc_html__( 'General Logs', 'easy-language' ); ?></h2>
		<?php
		$log = new Log_Table();
		$log->prepare_items();
		$log->views();
		$log->display();
	}

	/**
	 * Render tab for simplified texts in use.
	 *
	 * @return void
	 */
	public function render_simplified_texts_in_use(): void {
		?>
		<h2><?php echo esc_html__( 'Simplified texts in use', 'easy-language' ); ?></h2>
		<p><?php echo esc_html__( 'This table contains all simplified texts. The original texts will not be simplified a second time.', 'easy-language' ); ?></p>
		<?php
		$simplifications_table = new Texts_In_Use_Table();
		$simplifications_table->prepare_items();
		$simplifications_table->views();
		$simplifications_table->display();
	}

	/**
	 * Render tab for texts to simplify.
	 *
	 * @return void
	 */
	public function render_simplified_texts_to_simplify(): void {
		// get API-object.
		$api_obj = Apis::get_instance()->get_active_api();

		// show hint if not API is active.
		if ( false === $api_obj ) {
			?>
			<h2><?php echo esc_html__( 'Texts to simplify', 'easy-language' ); ?></h2><p><?php echo esc_html__( 'No API active which could simplify texts.', 'easy-language' ); ?></p>
			<?php
			return;
		}

		?>
		<h2><?php echo esc_html__( 'Texts to simplify', 'easy-language' ); ?></h2>
		<p>
			<?php
			/* translators: %1$s will be replaced by the API title */
			echo esc_html( sprintf( __( 'This table contains texts which will be simplified via %1$s. They are processed by a background-process.', 'easy-language' ), esc_html( $api_obj->get_title() ) ) );
			?>
		</p>
		<?php
		$simplifications_table = new Texts_To_Simplify_Table();
		$simplifications_table->prepare_items();
		$simplifications_table->views();
		$simplifications_table->display();
	}

	/**
	 * Validate multiple checkboxes.
	 *
	 * @param array<string,mixed>|null $values The list of values.
	 *
	 * @return array<string,mixed>|null
	 */
	public function sanitize_checkboxes( ?array $values ): ?array {
		$filter = current_filter();
		if ( ! empty( $filter ) ) {
			$filter = str_replace( 'sanitize_option_', '', $filter );
			if ( empty( $values ) ) {
				$pre_values = filter_input( INPUT_POST, $filter . '_ro', FILTER_SANITIZE_NUMBER_INT, FILTER_FORCE_ARRAY );
				if ( ! empty( $pre_values ) ) {
					// set the callback for array_map.
					$callback = 'sanitize_text_field';

					// run the sanitizing.
					$values = array_map( $callback, $pre_values ); // @phpstan-ignore argument.type
				}
			}
		}
		return $values;
	}
}
