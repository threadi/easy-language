<?php
/**
 * File for handler for things the ChatGpt supports.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\ChatGpt;

use easyLanguage\Apis;
use easyLanguage\Api_Base;
use easyLanguage\Base;
use easyLanguage\Helper;
use easyLanguage\Language_Icon;
use easyLanguage\Log;
use easyLanguage\Multilingual_Plugins;
use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use easyLanguage\Transients;
use wpdb;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * @var array
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
		$this->table_requests = DB::get_instance()->get_wpdb_prefix() . 'easy_language_chatgpt';

		// add settings.
		add_action( 'easy_language_settings_add_settings', array( $this, 'add_settings' ), 20 );

		// add settings tab.
		add_action( 'easy_language_settings_add_tab', array( $this, 'add_settings_tab' ), 20 );

		// add settings page.
		add_action( 'easy_language_settings_chatgpt_page', array( $this, 'add_settings_page' ) );

		// add hook für schedules.
		$simplifications_obj = Simplifications::get_instance();
		$simplifications_obj->init( $this );
		add_action( 'easy_language_chatgpt_automatic', array( $simplifications_obj, 'run' ) );

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
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Return the public description of the SUMM AI API.
	 *
	 * @return string
	 */
	public function get_description(): string {
		/* translators: %1$d will be replaced by the link to Chatgpt */
		$text  = sprintf( __( '<p><a href="%1$s" target="_blank"><strong>ChatGpt</strong> (opens new window)</a> is an AI-tool for any conversations.<br>It helps you to answer questions you have.</p><p>This API tries to simplify texts based on its on artificial intelligence.<br>The results are based on no standards for Easy or Plain language.</p><p>The number of simplifications with ChatGpt is limited to <strong>tokens</strong>.</p>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
		$text .= '<p><strong>' . __( 'You will use tokens on ChatGpt for every simplification. Please consult your ChatGpt-account about the usage.', 'easy-language' ) . '</strong></p>';

		// wrapper for buttons.
		$text .= '<p>';

		// help-button.
		$text .= '<a href="' . esc_url( $this->get_language_specific_support_page() ) . '" target="_blank" class="button button-primary" title="' . esc_attr( __( 'Get help for this API', 'easy-language' ) ) . '"><span class="dashicons dashicons-editor-help"></span></a>';

		// Show setting-button if this API is enabled.
		if ( $this->is_active() ) {
			$text .= '<a href="' . esc_html( $this->get_settings_url() ) . '" class="button button-primary" title="' . esc_html__( 'Go to settings', 'easy-languag' ) . '"><span class="dashicons dashicons-admin-generic"></span></a>';
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
		return Helper::get_plugin_url() . 'classes/apis/chatgpt/gfx/logo.png';
	}


	/**
	 * Return list of supported source-languages.
	 *
	 * @return array
	 */
	public function get_supported_source_languages(): array {
		return array(
			'de_DE' => array(
				'label'       => __( 'German', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in germany.', 'easy-language' ),
				'api_value'   => 'de',
			),
			'de_DE_formal' => array(
				'label'       => __( 'German (Formal)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Formal german spoken in germany.', 'easy-language' ),
				'api_value'   => 'de',
			),
			'de_CH' => array(
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
			'de_AT' => array(
				'label'       => __( 'German (AT)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in austria.', 'easy-language' ),
				'api_value'   => 'de',
			),
		);
	}

	/**
	 * Return the languages this API supports.
	 *
	 * @return array
	 * @noinspection DuplicatedCode
	 */
	public function get_supported_target_languages(): array {
		return array(
			'de_EL' => array(
				'label'       => __( 'Einfache Sprache', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'The Einfache Sprache used in Germany, Suisse and Austria.', 'easy-language' ),
				'url'         => 'de_el',
				'api_value'   => 'plain',
				'icon'        => 'icon-de-el',
				'img'         => 'de_EL.png',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_EL' ) : '',
			),
			'de_LS' => array(
				'label'       => __( 'Leichte Sprache', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'The Leichte Sprache used in Germany, Suisse and Austria.', 'easy-language' ),
				'url'         => 'de_ls',
				'api_value'   => 'easy',
				'icon'        => 'icon-de-ls',
				'img'         => 'de_LS.png',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_LS' ) : '',
			),
		);
	}

	/**
	 * Return supported target languages.
	 *
	 * @return array
	 */
	public function get_active_target_languages(): array {
		// get actual enabled target-languages.
		$target_languages = get_option( 'easy_language_chatgpt_target_languages', array() );
		if ( ! is_array( $target_languages ) ) {
			$target_languages = array();
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
	 * @return array
	 */
	public function get_mapping_languages(): array {
		return array(
			'de_DE' => array( 'de_LS', 'de_EL' ),
			'de_DE_formal' => array( 'de_LS', 'de_EL' ),
			'de_CH' => array( 'de_LS', 'de_EL' ),
			'de_CH_informal' => array( 'de_LS', 'de_EL' ),
			'de_AT' => array( 'de_LS', 'de_EL' ),
		);
	}

	/**
	 * Add settings tab.
	 *
	 * @param string $tab The tab internal name.
	 *
	 * @return void
	 * @noinspection DuplicatedCode
	 */
	public function add_settings_tab( string $tab ): void {
		// get list of available plugins and check if they support APIs.
		$supports_api = false;
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if ( $plugin_obj->is_supporting_apis() && false === $plugin_obj->has_own_api_config() ) {
				$supports_api = true;
			}
		}

		// bail of plugin does not support API OR this API is not enabled.
		if ( false === $supports_api || $this->get_name() !== get_option( 'easy_language_api', '' ) ) {
			return;
		}

		// check active tab.
		$active_class = '';
		if ( $this->get_name() === $tab ) {
			$active_class = ' nav-tab-active';
		}

		// output tab.
		echo '<a href="' . esc_url( helper::get_settings_page_url() ) . '&tab=' . esc_attr( $this->get_name() ) . '" class="nav-tab' . esc_attr( $active_class ) . '">' . esc_html__( 'ChatGpt', 'easy-language' ) . '</a>';
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 * @noinspection DuplicatedCode
	 */
	public function add_settings_page(): void {
		// bail if this API is not enabled.
		if ( Apis::get_instance()->get_active_api()->get_name() !== $this->get_name() ) {
			return;
		}

		// get list of available plugins and check if they support APIs.
		$supports_api = false;
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if ( $plugin_obj->is_supporting_apis() ) {
				$supports_api = true;
			}
		}

		// bail if the plugin does not support APIs and also check user capabilities.
		if ( false === $supports_api || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<form method="POST" action="<?php echo esc_url( get_admin_url() ); ?>options.php">
			<?php
			settings_fields( 'easyLanguageChatGptFields' );
			do_settings_sections( 'easyLanguageChatGptPage' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Add chatgpt settings.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		/**
		 * Chatgpt settings Section
		 */
		add_settings_section(
			'settings_section_chatgpt',
			__( 'Chatgpt Settings', 'easy-language' ),
			'__return_true',
			'easyLanguageChatGptPage'
		);

		// Set description for token field if it has not been set.
		/* translators: %1$s will be replaced by the Chatgpt URL */
		$description = sprintf( __( 'Get your ChatGpt API Token <a href="%1$s" target="_blank">here (opens new window)</a>.<br>If you have any questions about the token provided by ChatGpt, please contact their support: <a href="%1$s" target="_blank">%1$s (opens new window)</a>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
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
			$description  = sprintf( __( 'If you have any questions about the token provided by ChatGpt, please contact their support: <a href="%1$s" target="_blank">%1$s (opens new window)</a>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
			$description .= '<br><a href="' . esc_url( $remove_token_url ) . '" class="button button-secondary easy-language-settings-button">' . __( 'Remove token', 'easy-language' ) . '</a>';
		}

		// if foreign simplification-plugin with API-support is used, hide the language-settings.
		$foreign_translation_plugin_with_api_support = false;
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if ( $plugin_obj->is_foreign_plugin() && $plugin_obj->is_supporting_apis() ) {
				$foreign_translation_plugin_with_api_support = true;
			}
		}

		// Chatgpt token.
		add_settings_field(
			'easy_language_chatgpt_api_key',
			__( 'Chatgpt API Key', 'easy-language' ),
			'easy_language_admin_text_field',
			'easyLanguageChatGptPage',
			'settings_section_chatgpt',
			array(
				'label_for'   => 'easy_language_chatgpt_api_key',
				'fieldId'     => 'easy_language_chatgpt_api_key',
				'description' => $description,
				'placeholder' => __( 'Enter token here', 'easy-language' ),
				'highlight'   => false === $this->is_chatgpt_token_set(),
			)
		);
		register_setting( 'easyLanguageChatGptFields', 'easy_language_chatgpt_api_key', array( 'sanitize_callback' => array( $this, 'validate_api_key' ) ) );

		// Choose language model.
		add_settings_field(
			'easy_language_chatgpt_model',
			__( 'Choose language model', 'easy-language' ),
			'easy_language_admin_select_field',
			'easyLanguageChatGptPage',
			'settings_section_chatgpt',
			array(
				'label_for'     => 'easy_language_chatgpt_model',
				'fieldId'       => 'easy_language_chatgpt_model',
				'values'        => apply_filters(
					'easy_language_chatgpt_models',
					array(
						'gpt-4'         => 'gpt-4',
						'gpt-3.5-turbo' => 'gpt-3.5-turbo',
					)
				),
				'disable_empty' => true,
				'readonly'      => ! $this->is_chatgpt_token_set(),
				'description'   => __( 'The choice of language model determines the quality of the texts generated by ChatGpt.', 'easy-language' ),
			)
		);
		register_setting( 'easyLanguageChatGptFields', 'easy_language_chatgpt_model', array( 'sanitize_callback' => 'easyLanguage\Helper::settings_validate_select_field' ) );

		// Enable source-languages
		// -> defaults to WP-locale
		// -> if WPML, Polylang or TranslatePress is available, show additional languages
		// -> but restrict list to languages supported by ChatGpt.
		add_settings_field(
			'easy_language_chatgpt_source_languages',
			__( 'Choose source languages', 'easy-language' ),
			'easy_language_admin_multiple_checkboxes_field',
			'easyLanguageChatGptPage',
			'settings_section_chatgpt',
			array(
				'label_for'   => 'easy_language_chatgpt_source_languages',
				'fieldId'     => 'easy_language_chatgpt_source_languages',
				'description' => __( 'These are the possible source languages for ChatGpt-simplifications. This language has to be the language which you use for any texts in your website.', 'easy-language' ),
				'options'     => $this->get_supported_source_languages(),
				'readonly'    => false === $this->is_chatgpt_token_set() || $foreign_translation_plugin_with_api_support,
			)
		);
		register_setting( 'easyLanguageChatGptFields', 'easy_language_chatgpt_source_languages', array( 'sanitize_callback' => 'easyLanguage\Helper::settings_validate_multiple_checkboxes' ) );

		// Enable target languages.
		add_settings_field(
			'easy_language_chatgpt_target_languages',
			__( 'Choose target languages', 'easy-language' ),
			'easy_language_admin_multiple_checkboxes_field',
			'easyLanguageChatGptPage',
			'settings_section_chatgpt',
			array(
				'label_for'   => 'easy_language_chatgpt_target_languages',
				'fieldId'     => 'easy_language_chatgpt_target_languages',
				'description' => __( 'These are the possible target languages for ChatGpt-simplifications.', 'easy-language' ),
				'options'     => $this->get_supported_target_languages(),
				'readonly'    => false === $this->is_chatgpt_token_set() || $foreign_translation_plugin_with_api_support,
			)
		);
		register_setting( 'easyLanguageChatGptFields', 'easy_language_chatgpt_target_languages', array( 'sanitize_callback' => array( $this, 'validate_language_settings' ) ) );

		// Define target-language-specific prompts.
		add_settings_field(
			'easy_language_chatgpt_target_languages_prompts',
			__( 'Define prompts', 'easy-language' ),
			'easy_language_admin_multiple_text_field',
			'easyLanguageChatGptPage',
			'settings_section_chatgpt',
			array(
				'label_for'   => 'easy_language_chatgpt_target_languages_prompts',
				'fieldId'     => 'easy_language_chatgpt_target_languages_prompts',
				'description' => __( 'The prompt defines the requirement for the ChatGpt AI to simplify the text that follows.', 'easy-language' ),
				'options'     => $this->get_supported_target_languages(),
				'readonly'    => false === $this->is_chatgpt_token_set() || $foreign_translation_plugin_with_api_support,
			)
		);
		register_setting( 'easyLanguageChatGptFields', 'easy_language_chatgpt_target_languages_prompts', array( 'sanitize_callback' => 'easyLanguage\Helper::settings_validate_multiple_text_field' ) );

		// get possible intervals.
		$intervals = array();
		foreach ( wp_get_schedules() as $name => $schedule ) {
			$intervals[ $name ] = $schedule['display'];
		}

		do_action( 'easy_language_chatgpt_automatic_interval', $intervals, $foreign_translation_plugin_with_api_support );
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

		// set source language depending on WP-locale and its support.
		if ( ! get_option( 'easy_language_chatgpt_source_languages' ) ) {
			$language  = helper::get_wp_lang();
			$languages = array( $language => '1' );
			update_option( 'easy_language_chatgpt_source_languages', $languages );
		}

		// set target language depending on source-language and if only one target could be possible.
		if ( ! get_option( 'easy_language_chatgpt_target_languages' ) ) {
			$language  = helper::get_wp_lang();
			$languages = array( 'de_EL' => '1' );
			update_option( 'easy_language_chatgpt_target_languages', $languages );
		}

		// set target language prompts.
		if ( ! get_option( 'easy_language_chatgpt_target_languages_prompts' ) ) {
			$language  = helper::get_wp_lang();
			$languages = array(
				'de_EL' => 'Vereinfache bitte den folgenden deutschen Text in Einfache Sprache.',
				'de_LS' => 'Vereinfache bitte den folgenden deutschen Text in Leichte Sprache. Verwende dabei pro Absatz eine Zeile.',
			);
			update_option( 'easy_language_chatgpt_target_languages_prompts', $languages );
		}

		// set language model.
		if ( ! get_option( 'easy_language_chatgpt_model' ) ) {
			update_option( 'easy_language_chatgpt_model', 'gpt-4' );
		}

		// set chatgpt api key to nothing but with active autoload.
		if ( ! get_option( 'easy_language_chatgpt_api_key' ) ) {
			update_option( 'easy_language_chatgpt_api_key', '', true );
		}

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
		 * Remove settings.
		 */
		foreach ( $this->get_options() as $option_name ) {
			delete_option( $option_name );
		}

		/**
		 * Delete our table.
		 */
		$sql = 'DROP TABLE IF EXISTS ' . $this->table_requests;
		$this->wpdb->query( $sql );
	}

	/**
	 * Return list of options this plugin is using, e.g. for clean uninstall.
	 *
	 * @return array
	 */
	private function get_options(): array {
		return array(
			'easy_language_chatgpt_api_key',
			'easy_language_chatgpt_source_languages',
			'easy_language_chatgpt_target_languages',
			'easy_language_chatgpt_interval',
			'easy_language_chatgpt_model',
			'easy_language_chatgpt_target_languages_prompts',
		);
	}

	/**
	 * Return the simplifications-object.
	 *
	 * @return Simplifications
	 */
	public function get_simplifications_obj(): object {
		// get the object.
		$obj = Simplifications::get_instance();

		// initialize it.
		$obj->init( $this );

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
	 * @return array
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
	 * @return Request
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function get_request_object() {
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
		if ( helper::check_if_setting_error_entry_exists_in_array( 'easy_language_chatgpt_api_key', $errors ) ) {
			return $value;
		}

		// if no token has been entered, show hint.
		if ( empty( $value ) ) {
			add_settings_error( 'easy_language_chatgpt_api_key', 'easy_language_chatgpt_api_key', __( 'You did not enter an API token. All simplification options via the ChatGpt API have been disabled.', 'easy-language' ) );
		} elseif ( 0 !== strcmp( $value, get_option( 'easy_language_chatgpt_api_key', '' ) ) ) {
			// if token has been changed, delete settings hint.
			Transients::get_instance()->get_transient_by_name( 'easy_language_api_changed' )->delete();

			// Log event.
			Log::get_instance()->add_log( 'Token for ChatGpt has been changed.', 'success' );
		}

		// show intro if it has not been shown until now.
		if ( ! empty( $value ) && ! get_option( 'easy_language_intro_step_2' ) ) {
			update_option( 'easy_language_intro_step_2', 1 );
		}

		// return the entered token.
		return $value;
	}

	/**
	 * Validate the language-settings.
	 *
	 * The source-language must be possible to simplify in the target-language.
	 *
	 * @param ?array $values The values to check.
	 *
	 * @return array|null
	 */
	public function validate_language_settings( ?array $values ): ?array {
		$values = Helper::settings_validate_multiple_checkboxes( $values );
		if ( empty( $values ) ) {
			add_settings_error( 'easy_language_chatgpt_target_languages', 'easy_language_chatgpt_target_languages', __( 'You have to set a target-language for simplifications.', 'easy-language' ) );
		} elseif ( false === $this->is_language_set( $values ) ) {
			add_settings_error( 'easy_language_chatgpt_target_languages', 'easy_language_chatgpt_target_languages', __( 'At least one language cannot (currently) be translated into the selected target languages by the API.', 'easy-language' ) );
		}

		// return value.
		return $values;
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
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
		exit;
	}

	/**
	 * Get quota as array containing 'character_spent' and 'character_limit'.
	 *
	 * ChatGpt uses tokens which we can't request. So we set this to unlimited.
	 *
	 * @return array
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
	 * @return array
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

		// return resulting list.
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
		// get the settings.
		$request_texts = get_option( 'easy_language_chatgpt_target_languages_prompts', array() );

		// return language-specific text is it exist.
		if ( ! empty( $request_texts[ $target_language ] ) ) {
			return $request_texts[ $target_language ];
		}

		// return nothing if no language-specific text exist.
		return '';
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
	 * @return array
	 */
	public function get_log_entries(): array {
		$results = $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT `time`, `request`, `response` FROM ' . $this->table_requests . ' WHERE 1 = %d', array( 1 ) ) );
		if ( is_null( $results ) ) {
			return array();
		}
		return $results;
	}
}
