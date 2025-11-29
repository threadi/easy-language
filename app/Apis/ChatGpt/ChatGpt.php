<?php
/**
 * File for handler for things the ChatGpt supports.
 *
 * @source https://platform.openai.com/docs/api-reference/chat/create
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\ChatGpt;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Dependencies\easySettingsForWordPress\Fields\Checkboxes;
use easyLanguage\Dependencies\easySettingsForWordPress\Fields\FieldTable;
use easyLanguage\Dependencies\easySettingsForWordPress\Fields\Select;
use easyLanguage\Dependencies\easySettingsForWordPress\Fields\Text;
use easyLanguage\Dependencies\easySettingsForWordPress\Page;
use easyLanguage\Dependencies\easySettingsForWordPress\Settings;
use easyLanguage\Plugin\Api_Requests;
use easyLanguage\Plugin\Api_Simplifications;
use easyLanguage\Plugin\Api_Base;
use easyLanguage\Plugin\Base;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Language_Icon;
use easyLanguage\Plugin\Log;
use easyLanguage\Plugin\ThirdPartySupports;
use easyLanguage\EasyLanguage\Db;
use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use wpdb;

/**
 * Define what ChatGpt supports and what not.
 */
class ChatGpt extends Base implements Api_Base {

	/**
	 * Set the internal name for the API.
	 *
	 * @var string
	 */
	protected string $name = 'chatgpt';

	/**
	 * Set the public title for the API.
	 *
	 * @var string
	 */
	protected string $title = 'ChatGpt';

	/**
	 * Instance of this object.
	 *
	 * @var ?ChatGpt
	 */
	private static ?ChatGpt $instance = null;

	/**
	 * Name for database-table with request-response.
	 *
	 * @var string
	 */
	private string $table_requests;

	/**
	 * Database-object.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Language-specific support-URL.
	 *
	 * @var array<string,string>
	 */
	protected array $support_url = array(
		'de_DE' => 'https://help.openai.com/',
		'en_US' => 'https://help.openai.com/',
		'en_UK' => 'https://help.openai.com/',
	);

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {
		global $wpdb;

		// get the db-connection.
		$this->wpdb = $wpdb;

		// table for requests and responses.
		$this->table_requests = Db::get_instance()->get_wpdb_prefix() . 'easy_language_chatgpt';

		// add settings.
		add_action( 'init', array( $this, 'add_settings' ), 20 );

		// add hook für schedules.
		$simplifications_obj = Simplifications::get_instance();
		$simplifications_obj->set_api( $this );
		$callable = array( $simplifications_obj, 'run' );
		if ( is_callable( $callable ) ) {
			add_action( 'easy_language_chatgpt_automatic', $callable );
		}

		// add hook to remove token.
		add_action( 'admin_action_easy_language_chatgpt_remove_token', array( $this, 'remove_token' ) );
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): ChatGpt {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the public description of the ChatGpt API.
	 *
	 * @return string
	 */
	public function get_description(): string {
		/* translators: %1$d will be replaced by the link to Chatgpt */
		$text = sprintf( __( '<p><a href="%1$s" target="_blank"><strong>ChatGpt</strong> (opens new window)</a> is an AI-tool for any conversations.<br>It helps you to answer questions you have.</p><p>This API tries to simplify texts based on its on artificial intelligence.<br>The results are based on no standards for Easy or Plain language.</p><p>The number of simplifications with ChatGpt is limited to <strong>tokens</strong>.</p>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
		/* translators: %1$s will be replaced by a URL. */
		$text .= '<p><strong>' . __( 'You will use tokens on ChatGpt for every simplification. Please consult your ChatGpt-account about the usage.', 'easy-language' ) . ' ' . sprintf( __( 'You can see the prices for the usage <a href="%1$s" target="_blank">here</a>.', 'easy-language' ), $this->get_prices_url() ) . '</strong></p>';

		// wrapper for buttons.
		$text .= '<p>';

		// help-button.
		$text .= '<a href="' . esc_url( $this->get_language_specific_support_page() ) . '" target="_blank" class="button button-primary" title="' . esc_attr( __( 'Get help for this API', 'easy-language' ) ) . '"><span class="dashicons dashicons-editor-help"></span></a>';

		// Show setting-button if this API is enabled.
		if ( $this->is_active() ) {
			$text .= '<a href="' . esc_url( $this->get_settings_url() ) . '" class="button button-primary" title="' . esc_attr__( 'Go to settings', 'easy-language' ) . '"><span class="dashicons dashicons-admin-generic"></span></a>';
		}

		$text .= '</p>';

		// return resulting text.
		return $text;
	}

	/**
	 * Return the URL of the public logo for this API.
	 *
	 * @return string
	 */
	public function get_logo_url(): string {
		return Helper::get_plugin_url() . 'app/Apis/ChatGpt/gfx/logo.png';
	}

	/**
	 * Return list of supported source-languages.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_supported_source_languages(): array {
		$source_languages = array(
			'de_DE'          => array(
				'label'       => __( 'German', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in germany.', 'easy-language' ),
				'api_value'   => 'de',
			),
			'de_DE_formal'   => array(
				'label'       => __( 'German (Formal)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Formal german spoken in germany.', 'easy-language' ),
				'api_value'   => 'de',
			),
			'de_CH'          => array(
				'label'       => __( 'German (CH)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in suisse.', 'easy-language' ),
				'api_value'   => 'de',
			),
			'de_CH_informal' => array(
				'label'       => __( 'German (CH, informal)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in suisse.', 'easy-language' ),
				'api_value'   => 'de',
			),
			'de_AT'          => array(
				'label'       => __( 'German (AT)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in austria.', 'easy-language' ),
				'api_value'   => 'de',
			),
		);

		$source_languages['en_CA'] = array(
			'label'       => __( 'English (Canada)', 'easy-language-pro' ),
			'enable'      => true,
			'description' => __( 'English spoken in Canada.', 'easy-language-pro' ),
			'icon'        => 'icon-en-ca',
			'img'         => 'en_ca.png',
			'img_icon'    => Helper::get_icon_img_for_language_code( 'en_CA' ),
		);
		$source_languages['en_UK'] = array(
			'label'       => __( 'English (UK)', 'easy-language-pro' ),
			'enable'      => true,
			'description' => __( 'English spoken in the United Kingdom.', 'easy-language-pro' ),
			'icon'        => 'icon-en-uk',
			'img'         => 'en_uk.png',
			'img_icon'    => Helper::get_icon_img_for_language_code( 'en_UK' ),
		);
		$source_languages['en_US'] = array(
			'label'       => __( 'English (United States)', 'easy-language-pro' ),
			'enable'      => true,
			'description' => __( 'English spoken in the United States.', 'easy-language-pro' ),
			'icon'        => 'icon-en-us',
			'img'         => 'en_us.png',
			'img_icon'    => Helper::get_icon_img_for_language_code( 'en_US' ),
		);
		$source_languages['fr_FR'] = array(
			'label'       => __( 'Français', 'easy-language-pro' ),
			'enable'      => true,
			'description' => __( 'Français spoken in France.', 'easy-language-pro' ),
			'icon'        => 'icon-fr-fr',
			'img'         => 'fr_fr.png',
			'img_icon'    => Helper::get_icon_img_for_language_code( 'fr_FR' ),
		);

		/**
		 * Filter ChatGpt source languages.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $source_languages List of source languages.
		 */
		return apply_filters( 'easy_language_chatgpt_source_languages', $source_languages );
	}

	/**
	 * Return the languages this API supports.
	 *
	 * @return array<string,array<string,mixed>>
	 * @noinspection DuplicatedCode
	 */
	public function get_supported_target_languages(): array {
		$target_languages = array(
			'de_EL' => array(
				'label'       => __( 'Einfache Sprache', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'The Einfache Sprache used in Germany, Suisse and Austria.', 'easy-language' ),
				'url'         => 'de_el',
				'api_value'   => 'plain',
				'icon'        => 'icon-de-el',
				'img'         => 'de_EL.svg',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_EL' ) : '',
			),
			'de_LS' => array(
				'label'       => __( 'Leichte Sprache', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'The Leichte Sprache used in Germany, Suisse and Austria.', 'easy-language' ),
				'url'         => 'de_ls',
				'api_value'   => 'easy',
				'icon'        => 'icon-de-ls',
				'img'         => 'de_LS.svg',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_LS' ) : '',
			),
		);

		/**
		 * Filter ChatGpt target languages.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $target_languages List of target languages.
		 */
		return apply_filters( 'easy_language_chatgpt_target_languages', $target_languages );
	}

	/**
	 * Return supported target languages.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_active_target_languages(): array {
		// get actual enabled target-languages, if token is given.
		$target_languages = array();
		if ( $this->is_chatgpt_token_set() ) {
			$target_languages = get_option( 'easy_language_chatgpt_target_languages', array() );
			if ( ! is_array( $target_languages ) ) {
				$target_languages = array();
			}
		}

		// define resulting list.
		$list = array();

		foreach ( $this->get_supported_target_languages() as $language_code => $language ) {
			if ( ! empty( $target_languages[ $language_code ] ) ) {
				$list[ $language_code ] = $language;
			}
		}

		// return resulting list.
		return $list;
	}

	/**
	 * Return the list of supported languages which could be translated with this API.
	 *
	 * Left source, right possible target languages.
	 *
	 * @return array<string,mixed>
	 */
	public function get_mapping_languages(): array {
		$language_mappings = array(
			'de_DE'          => array( 'de_LS', 'de_EL' ),
			'de_DE_formal'   => array( 'de_LS', 'de_EL' ),
			'de_CH'          => array( 'de_LS', 'de_EL' ),
			'de_CH_informal' => array( 'de_LS', 'de_EL' ),
			'de_AT'          => array( 'de_LS', 'de_EL' ),
			'en_CA'          => array( 'en_ER', 'en_PE' ),
			'en_UK'          => array( 'en_ER', 'en_PE' ),
			'en_US'          => array( 'en_ER', 'en_PE' ),
		);

		/**
		 * Filter ChatGpt language mappings.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $language_mappings List of mappings.
		 */
		return apply_filters( 'easy_language_chatgpt_mapping_languages', $language_mappings );
	}

	/**
	 * Add chatgpt settings.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( 'easy_language_settings' );

		// bail if the page is not available.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add tab.
		$chatgpt_tab = $settings_page->add_tab( 'chatgpt', 20 );
		$chatgpt_tab->set_title( __( 'ChatGPT', 'easy-language' ) );
		$chatgpt_tab->set_tab_class( ! $this->is_active() ? 'hidden' : '' );

		// add section.
		$chatgpt_tab_main = $chatgpt_tab->add_section( 'settings_section_chatgpt', 10 );
		$chatgpt_tab_main->set_title( __( 'ChatGPT Settings', 'easy-language' ) );

		// Set description for token field if it has not been set.
		/* translators: %1$s will be replaced by the ChatGPT URL */
		$description = sprintf( __( 'Get your ChatGPT API Token <a href="%1$s" target="_blank">here (opens new window)</a>.<br>If you have any questions about the token provided by ChatGpt, please contact their support: <a href="%2$s" target="_blank">%2$s (opens new window)</a>', 'easy-language' ), esc_url( $this->get_api_management_url() ), esc_url( $this->get_language_specific_support_page() ) );
		if ( false !== $this->is_chatgpt_token_set() ) {
			// set link to remove the token.
			$remove_token_url = add_query_arg(
				array(
					'action' => 'easy_language_chatgpt_remove_token',
					'nonce'  => wp_create_nonce( 'easy-language-chatgpt-remove-token' ),
				),
				get_admin_url() . 'admin.php'
			);

			// Show other description if token is set.
			/* translators: %1$s will be replaced by the Chatgpt URL */
			$description  = sprintf( __( 'If you have any questions about the token provided by ChatGPT, please contact their support: <a href="%1$s" target="_blank">%1$s (opens new window)</a>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
			$description .= '<br><a href="' . esc_url( $remove_token_url ) . '" class="button button-secondary easy-language-settings-button">' . __( 'Remove token', 'easy-language' ) . '</a>';
		}

		// if foreign simplification-plugin with API-support is used, hide the language-settings.
		$foreign_translation_plugin_with_api_support = false;
		foreach ( ThirdPartySupports::get_instance()->get_available_plugins() as $plugin_obj ) {
			if ( $plugin_obj->is_foreign_plugin() && $plugin_obj->is_supporting_apis() ) {
				$foreign_translation_plugin_with_api_support = true;
			}
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_chatgpt_api_key' );
		$setting->set_section( $chatgpt_tab_main );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'string' );
		$setting->set_default( '' );
		$setting->set_save_callback( array( $this, 'validate_api_key' ) );
		$field = new Text();
		$field->set_title( __( 'ChatGPT API Key', 'easy-language' ) );
		$field->set_placeholder( __( 'Enter your key here', 'easy-language' ) );
		$field->set_description( $description );
		$setting->set_field( $field );

		// Define list of models this plugin supports atm.
		$models = array(
			'gpt-5.1'       => 'gpt-5.1',
			'gpt-5'         => 'gpt-5',
			'gpt-4o'        => 'gpt-4o',
			'gpt-4'         => 'gpt-4',
			'gpt-3.5-turbo' => 'gpt-3.5-turbo',
		);

		/**
		 * Filter the available ChatGpt-models.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $models List of ChatGpt models.
		 */
		$models = apply_filters( 'easy_language_chatgpt_models', $models );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_chatgpt_model' );
		$setting->set_section( $chatgpt_tab_main );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'string' );
		$setting->set_default( 'gpt-5.1' );
		$field = new Select();
		$field->set_title( __( 'Choose language model', 'easy-language' ) );
		$field->set_description( __( 'The choice of language model determines the quality of the texts generated by ChatGPT.', 'easy-language' ) );
		$field->set_options( $models );
		$field->set_readonly( false === $this->is_chatgpt_token_set() || $foreign_translation_plugin_with_api_support );
		$field->set_sanitize_callback( array( 'easyLanguage\Plugin\Helper', 'settings_validate_select_field' ) );
		$setting->set_field( $field );

		// get the actual language of the website for default setting.
		$language  = Helper::get_wp_lang();
		$languages = array( $language => '1' );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_chatgpt_source_languages' );
		$setting->set_section( $chatgpt_tab_main );
		$setting->set_type( 'array' );
		$setting->set_default( $languages );
		$field = new Checkboxes();
		$field->set_title( __( 'Choose source languages', 'easy-language' ) );
		$field->set_description( __( 'These are the possible source languages for ChatGPT-simplifications. This language has to be the language which you use for any texts in your website.', 'easy-language' ) );
		$field->set_readonly( false === $this->is_chatgpt_token_set() || $foreign_translation_plugin_with_api_support );
		$field->set_options( $this->get_supported_source_languages() );
		$field->set_sanitize_callback( array( \easyLanguage\Plugin\Settings::get_instance(), 'sanitize_checkboxes' ) );
		$setting->set_field( $field );

		// get default translation languages.
		$language  = Helper::get_wp_lang();
		$languages = array( 'de_b1' => '1' );
		if ( false !== str_contains( $language, 'en_' ) ) {
			$languages = array( 'en_b1' => '1' );
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_chatgpt_target_languages' );
		$setting->set_section( $chatgpt_tab_main );
		$setting->set_type( 'array' );
		$setting->set_default( $languages );
		$field = new Checkboxes();
		$field->set_title( __( 'Choose target languages', 'easy-language' ) );
		$field->set_description( __( 'These are the possible target languages for ChatGPT-simplifications.', 'easy-language' ) );
		$field->set_readonly( false === $this->is_chatgpt_token_set() || $foreign_translation_plugin_with_api_support );
		$field->set_options( $this->get_supported_target_languages() );
		$field->set_sanitize_callback( array( \easyLanguage\Plugin\Settings::get_instance(), 'sanitize_checkboxes' ) );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_chatgpt_target_languages_prompts' );
		$setting->set_section( $chatgpt_tab_main );
		$setting->set_type( 'array' );
		$setting->set_default( $languages );
		$field = new FieldTable();
		$field->set_title( __( 'Choose target languages', 'easy-language' ) );
		$field->set_description( __( 'These are the possible target languages for SUMM AI-simplifications.', 'easy-language' ) );
		$field->set_columns(
			array(
				__( 'Define prompts', 'easy-language' ),
			)
		);

		// get the hidden section for all settings in this field table.
		$hidden_section = $chatgpt_tab->add_section( 'summ_ai_main_hidden', 10 );
		$hidden_section->set_hidden( true );

		// set the default language prompts.
		$language_prompts = array(
			'de_EL' => 'Vereinfache bitte den folgenden deutschen Text in Einfache Sprache.',
			'de_LS' => 'Vereinfache bitte den folgenden deutschen Text in Leichte Sprache. Verwende dabei pro Absatz eine Zeile.',
		);

		$row = 0;
		foreach ( $this->get_supported_target_languages() as $language_code => $settings ) {
			// add entry as a new row.
			$field->add_row();

			// add setting.
			$language = $settings_obj->add_setting( 'easy_language_chatgpt_target_languages_prompts_' . $language_code );
			$language->set_type( 'string' );
			$language->set_default( $language_prompts[ $language_code ] );
			$language->set_section( $hidden_section );
			$prompt_field = new Text();
			$prompt_field->set_title( $settings['label'] );
			$prompt_field->set_description( $settings['description'] );
			$prompt_field->set_setting( $language );
			$prompt_field->set_with_label( true );
			$prompt_field->set_readonly( ! $this->is_chatgpt_token_set() || $foreign_translation_plugin_with_api_support );
			$language->set_field( $prompt_field );
			$field->add_setting( $language, $row, 0 );

			// next row.
			++$row;
		}
		$setting->set_field( $field );
	}

	/**
	 * Return whether this API is active regarding all its settings.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->get_name() === get_option( 'easy_language_api' );
	}

	/**
	 * Install-routines for the API, called during plugin-activation and API-change.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// table for original-texts to translate.
		$sql = "CREATE TABLE $this->table_requests (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `request` text DEFAULT '' NOT NULL,
            `response` text DEFAULT '' NOT NULL,
            `duration` int(11) DEFAULT 0 NOT NULL,
            `httpstatus` int(11) DEFAULT 0 NOT NULL,
            `quota` int(11) DEFAULT 0 NOT NULL,
            `blog_id` int(11) DEFAULT 0 NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Deactivate-routines for the API, called during plugin-deactivation.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// nothing to do.
	}

	/**
	 * Install-routines for the API, called during plugin-activation and API-change.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		/**
		 * Delete our table.
		 */
		$sql = 'DROP TABLE IF EXISTS ' . $this->table_requests;
		$this->wpdb->query( $sql );
	}

	/**
	 * Return the simplifications-object.
	 *
	 * @return Api_Simplifications
	 */
	public function get_simplifications_obj(): Api_Simplifications {
		// get the object.
		$obj = Simplifications::get_instance();

		// set the API.
		$obj->set_api( $this );

		// return resulting object.
		return $obj;
	}

	/**
	 * Initialize api-specific CLI-functions for this API: none.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return API URL.
	 *
	 * @return string
	 */
	public function get_api_url(): string {
		return EASY_LANGUAGE_CHATGPT_API_URL;
	}

	/**
	 * Return list of transients this plugin is using, e.g. for clean uninstallation.
	 *
	 * @return array<string>
	 */
	private function get_transients(): array {
		return array();
	}

	/**
	 * Delete our own transients during disabling this API.
	 *
	 * @return void
	 */
	public function disable(): void {
		$transients_obj = Transients::get_instance();
		foreach ( $this->get_transients() as $transient_name ) {
			$transient_obj = $transients_obj->get_transient_by_name( $transient_name );
			$transient_obj->delete();
		}
	}

	/**
	 * Return true if token is set.
	 *
	 * @return bool
	 */
	public function is_chatgpt_token_set(): bool {
		return ! empty( $this->get_token() );
	}

	/**
	 * Get the token.
	 *
	 * @return string
	 */
	public function get_token(): string {
		return (string) get_option( 'easy_language_chatgpt_api_key', '' );
	}

	/**
	 * Return request object.
	 *
	 * @return Api_Requests
	 */
	public function get_request_object(): Api_Requests {
		return new Request();
	}

	/**
	 * Validate the API token.
	 *
	 * @param ?string $value The value to validate.
	 * @return ?string
	 * @noinspection PhpUnused
	 */
	public function validate_api_key( ?string $value ): ?string {
		$errors = get_settings_errors();

		/**
		 * If a result-entry already exists, do nothing here.
		 *
		 * @see https://core.trac.wordpress.org/ticket/21989
		 */
		if ( Helper::check_if_setting_error_entry_exists_in_array( 'easy_language_chatgpt_api_key', $errors ) ) {
			return $value;
		}

		// if no token has been entered, show hint.
		if ( empty( $value ) ) {
			add_settings_error( 'easy_language_chatgpt_api_key', 'easy_language_chatgpt_api_key', __( 'You did not enter an API token. All simplification options via the ChatGpt API have been disabled.', 'easy-language' ) );
		} elseif ( 0 !== strcmp( $value, get_option( 'easy_language_chatgpt_api_key', '' ) ) ) {
			// if token has been changed, delete settings hint.
			Transients::get_instance()->get_transient_by_name( 'easy_language_api_changed' )->delete();

			// Log event.
			Log::get_instance()->add_log( __( 'Token for ChatGpt has been changed.', 'easy-language' ), 'success' );
		}

		// show intro if it has not been shown until now.
		if ( ! empty( $value ) && ! get_option( 'easy_language_intro_step_2' ) ) {
			update_option( 'easy_language_intro_step_2', 1 );
		}

		// return the entered token.
		return $value;
	}

	/**
	 * Remove token via click.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function remove_token(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-chatgpt-remove-token', 'nonce' );

		// delete settings.
		delete_option( 'easy_language_chatgpt_api_key' );

		// Remove intro-hint if it is enabled.
		if ( 1 === absint( get_option( 'easy_language_intro_step_2', 0 ) ) ) {
			delete_option( 'easy_language_intro_step_2' );
		}

		// redirect user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Get quota as array containing 'character_spent' and 'character_limit'.
	 *
	 * ChatGpt uses tokens which we can't request. So we set this to unlimited.
	 *
	 * @return array<string,mixed>
	 */
	public function get_quota(): array {
		// return initial values.
		return array(
			'character_spent' => 0,
			'character_limit' => -1,
			'unlimited'       => true,
		);
	}

	/**
	 * API has settings.
	 *
	 * @return bool
	 */
	public function has_settings(): bool {
		return true;
	}

	/**
	 * Return active source languages of this API.
	 *
	 * @return array<string,mixed>
	 */
	public function get_active_source_languages(): array {
		// get actual enabled source-languages.
		$source_languages = get_option( 'easy_language_chatgpt_source_languages', array() );
		if ( ! is_array( $source_languages ) ) {
			$source_languages = array();
		}

		// define resulting list.
		$list = array();

		foreach ( $this->get_supported_source_languages() as $language_code => $language ) {
			if ( ! empty( $source_languages[ $language_code ] ) ) {
				$list[ $language_code ] = $language;
			}
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return language-specific request text for the API.
	 *
	 * @param string $target_language The target-language.
	 *
	 * @return string
	 */
	public function get_request_text_by_language( string $target_language ): string {
		return get_option( 'easy_language_chatgpt_target_languages_prompts_' . $target_language, '' );
	}

	/**
	 * Return true if token is set.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return $this->is_chatgpt_token_set();
	}

	/**
	 * Enable-routines for the API, called on the new API if another API is chosen.
	 *
	 * @return void
	 */
	public function enable(): void {
		// save language-icons in db.
		foreach ( $this->get_supported_target_languages() as $language_code => $settings ) {
			$icon_obj = new Language_Icon();
			$icon_obj->set_file( $settings['img'] );
			$icon_obj->save( $language_code );
		}
	}

	/**
	 * Return the log entries of this API.
	 *
	 * @return array<int,mixed>
	 */
	public function get_log_entries(): array {
		$results = $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT `time`, `request`, `response` FROM ' . $this->table_requests . ' WHERE 1 = %d', array( 1 ) ) ); // @phpstan-ignore argument.type
		if ( is_null( $results ) ) {
			return array();
		}
		return $results;
	}

	/**
	 * Return the API management URL.
	 *
	 * @return string
	 */
	private function get_api_management_url(): string {
		return 'https://platform.openai.com/api-keys';
	}

	/**
	 * Return the URL for the price list.
	 *
	 * @return string
	 */
	private function get_prices_url(): string {
		return 'https://openai.com/chatgpt/pricing/';
	}
}
