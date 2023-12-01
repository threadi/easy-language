<?php
/**
 * File for handler for things the SUMM AI supports.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Summ_Ai;

use easyLanguage\Apis;
use easyLanguage\Base;
use easyLanguage\Api_Base;
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
 * Define what SUMM AI supports and what not.
 */
class Summ_AI extends Base implements Api_Base {

	/**
	 * Set the internal name for the API.
	 *
	 * @var string
	 */
	protected string $name = 'summ_ai';

	/**
	 * Set the public title for the API.
	 *
	 * @var string
	 */
	protected string $title = 'SUMM AI';

	/**
	 * Instance of this object.
	 *
	 * @var ?Summ_AI
	 */
	private static ?Summ_AI $instance = null;

	/**
	 * Name for database-table with request-response.
	 *
	 * @var string
	 */
	private string $table_requests;

	/**
	 * Set max text length for single entry for this API.
	 *
	 * @var int
	 */
	protected int $max_single_text_length = 10000;

	/**
	 * Set max requests per minute.
	 *
	 * @var int
	 */
	protected int $max_requests_per_minute = 15;

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
		'de_DE' => 'https://summ-ai.com/unsere-schnittstelle/',
	);

	/**
	 * API-mode: free or paid.
	 *
	 * Defaults to free.
	 *
	 * @var string
	 */
	private string $mode = 'free';

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {
		global $wpdb;

		// get the db-connection.
		$this->wpdb = $wpdb;

		// table for requests and responses.
		$this->table_requests = DB::get_instance()->get_wpdb_prefix() . 'easy_language_summ_ai';

		// add settings.
		add_action( 'easy_language_settings_add_settings', array( $this, 'add_settings'), 30 );

		// add settings tab.
		add_action( 'easy_language_settings_add_tab', array( $this, 'add_settings_tab'), 30 );

		// add settings page.
		add_action('easy_language_settings_summ_ai_page', array( $this, 'add_settings_page' ) );

		// add hook für schedule.
		add_action( 'easy_language_summ_ai_request_quota', array( $this, 'get_quota_from_api' ) );

		// add hook for token test.
		add_action( 'admin_action_easy_language_summ_ai_test_token', array( $this, 'run_token_test' ) );

		// add hook to remove token.
		add_action( 'admin_action_easy_language_summ_ai_remove_token', array( $this, 'remove_token' ) );

		// add hook to get actual quota via link.
		add_action( 'admin_action_easy_language_summ_ai_get_quota', array( $this, 'get_quota_from_api_via_link' ) );
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
	public static function get_instance(): Summ_AI {
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
		// get quota.
		$quota = $this->get_quota();

		/* translators: %1$s will be replaced by the URL for SUMM AI-product-info */
		$text = sprintf( __( '<p>Create any complicated text barrier-free and understandable with the <a href="%1$s" target="_blank"><strong>SUMM AI</strong> (opens new window)</a> AI-based tool.<br>Create simple and easy-to-understand texts on your website.</p><p>This API simplifies texts according to the official rules of the <i>Leichte Sprache e.V.</i>.<br>This specifies how texts must be written in easy language.</p>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
		if( $this->is_free_mode() ) {
			$percent = absint($quota['character_spent']) / absint($quota['character_limit']);
			if( 1 === $percent ) {
				/* translators: %1$s will be replaced by the URL where the SUMM AI API key could be requested */
				$text .= sprintf(__( '<p><strong>You have completely depleted the free quota available with the Easy Language plugin.</strong><br>Enter your SUMM AI API key <a href="%2$s">here</a> to get more.</p>', 'easy-language' ), esc_url($this->get_settings_url()) );
			}
			elseif( $percent > apply_filters( 'easy_language_quota_percent', 0.8 ) ) {
				/* translators: %1$s will be replaced by the URL where the SUMM AI API key could be requested */
				$text .= sprintf(__( '<p><strong>You have almost used up your free quota available with the Easy Language plugin.</strong><br>Enter your SUMM AI API key <a href="%2$s">here</a> to get more.</p>', 'easy-language' ), esc_url($this->get_settings_url()) );
			}
			else {
				/* translators: %1$d will be a number, %2$s will be replaced by the URL where the SUMM AI API key could be requested */
				$text .= sprintf( __( '<p><strong>You are currently using a free quota of %1$d characters available for text simplifications with the Easy Language plugin.</strong><br>Enter your SUMM AI API key <a href="%2$s">here</a> to get more.</p>', 'easy-language' ), absint( $quota['character_limit'] ), esc_url( $this->get_settings_url() ) );
			}
		}
		else {
			$text .= __( '<p><strong>You are currently using your own paid SUMM AI API key.</strong></p>', 'easy-language' );
		}
		if ( $quota['character_limit'] > 0 ) {
			$text .= $this->get_quota_table();
		}

		// wrapper for buttons.
		$text .= $this->get_description_buttons();

		// return resulting text.
		return $text;
	}

	/**
	 * Return the URL of the public logo for this API.
	 *
	 * @return string
	 */
	public function get_logo_url(): string {
		return Helper::get_plugin_url() . 'classes/apis/summ-ai/gfx/logo.jpg';
	}

	/**
	 * Return list of supported source-languages.
	 *
	 * @return array
	 * @noinspection DuplicatedCode
	 */
	public function get_supported_source_languages(): array {
		return array(
			'de_DE'          => array(
				'label'       => __( 'German', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in Germany.', 'easy-language' ),
				'icon'        => 'icon-de-de',
				'img'         => 'de_de.png',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_de' ) : '',
			),
			'de_DE_formal'   => array(
				'label'       => __( 'German (Formal)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Formal german spoken in Germany.', 'easy-language' ),
				'icon'        => 'icon-de-de',
				'img'         => 'de_de.png',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_DE_formal' ) : '',
			),
			'de_CH'          => array(
				'label'       => __( 'Suisse german', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Formal german spoken in Suisse.', 'easy-language' ),
				'icon'        => 'icon-de-ch',
				'img'         => 'de_ch.png',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_CH' ) : '',
			),
			'de_CH_informal' => array(
				'label'       => __( 'Suisse german (Informal)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in Suisse.', 'easy-language' ),
				'icon'        => 'icon-de-ch',
				'img'         => 'de_ch.png',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_CH_informal' ) : '',
			),
			'de_AT'          => array(
				'label'       => __( 'Austria German', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'German spoken in Austria.', 'easy-language' ),
				'icon'        => 'icon-de-at',
				'img'         => 'de_at.png',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_AT' ) : '',
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
	 * Return the list of supported languages which could be simplified with this API.
	 *
	 * Left source, right possible target languages.
	 *
	 * @return array
	 */
	public function get_mapping_languages(): array {
		return array(
			'de_DE' => array( 'de_LS', 'de_EL' ),
			'de_DE_formal' => array( 'de_LS', 'de_EL' ),
		);
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
		if ( ! get_option( 'easy_language_summ_ai_source_languages' ) ) {
			$language  = helper::get_wp_lang();
			$languages = array( $language => '1' );
			update_option( 'easy_language_summ_ai_source_languages', $languages );
		}

		// set target language depending on source-language and if only one target could be possible.
		if ( ! get_option( 'easy_language_summ_ai_target_languages' ) ) {
			$source_languages = get_option( 'easy_language_summ_ai_source_languages', array() );
			$languages        = array();
			$mappings         = $this->get_mapping_languages();
			foreach ( $source_languages as $source_language => $enabled ) {
				if ( ! empty( $mappings[ $source_language ] ) ) {
					foreach ( $mappings[ $source_language ] as $language ) {
						if ( 'de_EL' === $language ) {
							$languages[ $language ] = '1';
						}
					}
				}
			}
			update_option( 'easy_language_summ_ai_target_languages', $languages );
		}

		// set SUMM AI API as default API, if not already set.
		if ( ! get_option( 'easy_language_api' ) ) {
			update_option( 'easy_language_api', $this->get_name() );
		}

		// set SUMM AI API mode to free, if not already set.
		if ( ! get_option( 'easy_language_summ_ai_mode' ) ) {
			update_option( 'easy_language_summ_ai_mode', 'free' );
		}

		// set interval for quota request interval to daily.
		if( !get_option('easy_language_summ_ai_quota_interval') ) {
			update_option('easy_language_summ_ai_quota_interval', 'daily' );
		}

		// set schedule for quota request.
		wp_schedule_event( time(), get_option('easy_language_summ_ai_quota_interval', 'daily'), 'easy_language_summ_ai_request_quota' );

		// set translation mode to editor.
		if( !get_option('easy_language_summ_ai_email_mode') ) {
			update_option('easy_language_summ_ai_email_mode', 'editor');
		}

		// set summ ai api as default API.
		update_option( 'easy_language_api', $this->get_name() );

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
			'easy_language_summ_ai_source_languages',
			'easy_language_summ_ai_target_languages',
			'easy_language_summ_ai_mode',
			'easy_language_summ_ai_disable_free_requests',
			'easy_language_summ_ai_api_key',
			'easy_language_summ_api_email',
			'easy_language_summ_ai_test',
			'easy_language_summ_ai_quota',
			'easy_language_summ_ai_paid_quota',
			'easy_language_summ_ai_quota_interval',
			'easy_language_summ_ai_email_mode',
		);
	}

	/**
	 * Return list of transients this plugin is using, e.g. for clean uninstall.
	 *
	 * @return array
	 */
	private function get_transients(): array {
		return array(
			'easy_language_summ_ai_test_token',
			'easy_language_summ_ai_quota'
		);
	}

	/**
	 * Initialize api-specific CLI-functions for this API: none.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return the simplifications-object.
	 *
	 * @return object
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
	 * Return supported target languages.
	 *
	 * @return array
	 * @noinspection DuplicatedCode
	 */
	public function get_active_target_languages(): array {
		// get actual enabled target-languages.
		$target_languages = get_option( 'easy_language_summ_ai_target_languages', array() );
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
	 * Get quota as array containing 'character_spent' and 'character_limit'.
	 *
	 * @return array
	 */
	public function get_quota(): array {
		// return free quota if used.
		if( false !== $this->is_free_mode() ) {
			return array(
				'character_spent' => get_option( 'easy_language_summ_ai_quota', 0 ),
				'character_limit' => EASY_LANGUAGE_SUMM_AI_QUOTA,
			);
		}

		// return paid quota.
		return (array)get_option( 'easy_language_summ_ai_paid_quota', 0 );
	}

	/**
	 * Return API URL.
	 *
	 * @return string
	 */
	public function get_api_url(): string {
		return $this->is_free_requests_enabled() && $this->is_free_mode() ? EASY_LANGUAGE_SUMM_AI_FREE_API_URL : EASY_LANGUAGE_SUMM_AI_PAID_API_URL;
	}

	/**
	 * Return the SUMM AI API token.
	 *
	 * @return string
	 */
	public function get_token(): string {
		return $this->is_free_requests_enabled() && $this->is_free_mode() ? get_option( EASY_LANGUAGE_HASH ) : get_option( 'easy_language_summ_ai_api_key' );
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
	 * Return active source languages of this API.
	 *
	 * @return array
	 */
	public function get_active_source_languages(): array {
		// get actual enabled source-languages.
		$source_languages = get_option( 'easy_language_summ_ai_source_languages', array() );
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
	 * Return true as this API is preconfigured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return true;
	}

	/**
	 * Return whether this API has extended support in Easy Language Pro.
	 *
	 * @return bool
	 */
	public function is_extended_in_pro(): bool {
		return true;
	}

	/**
	 * Return custom pro-hint for API-chooser.
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function get_pro_hint(): string {
		/* translators: %1$s will be replaced by the link to laolaweb.com */
		return sprintf( __( 'Get support for more languages with <a href="%1$s" target="_blank" title="link opens new window">Easy Language Pro</a>', 'easy-language' ), esc_url( Helper::get_pro_url() ) );
	}

	/**
	 * Enable-routines for the API, called on the new API if another API is chosen.
	 *
	 * @return void
	 */
	public function enable(): void {
		// save language-icons in db.
		foreach ( array_merge( $this->get_supported_source_languages(), $this->get_supported_target_languages() ) as $language_code => $settings ) {
			$icon_obj = new Language_Icon();
			$icon_obj->set_file( $settings['img'] );
			$icon_obj->save( $language_code );
		}

		// set SUMM AI API mode to free, if not already set.
		if ( ! get_option( 'easy_language_summ_ai_mode' ) ) {
			update_option( 'easy_language_summ_ai_mode', 'free' );
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

	/**
	 * Disable free requests.
	 *
	 * @return void
	 */
	public function disable_free_requests(): void {
		update_option( 'easy_language_summ_ai_disable_free_requests', 1 );
	}

	/**
	 * Return whether free requests are enabled.
	 *
	 * @return bool
	 */
	public function is_free_requests_enabled(): bool {
		return 1 !== absint( get_option( 'easy_language_summ_ai_disable_free_requests', 0 ) );
	}

	/**
	 * Add settings for this API.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		/**
		 * SUMM AI Section
		 */
		add_settings_section(
			'settings_section_summ_ai',
			__( 'SUMM AI Settings', 'easy-language' ),
			'__return_true',
			'easyLanguageSummAIPage'
		);

		// Set description for token field if it has not been set.
		$description = '';
		if( $this->is_free_mode() ) {
			/* translators: %1$d will be replaced by a number */
			$description .= sprintf( __( 'You have a free quota of %1$d characters with the plugin Easy Language.', 'easy-language' ), absint($this->get_quota()['character_limit']) ).'<br>';
		}
		/* translators: %1$s will be replaced by the SUMM AI URL */
		$description .= sprintf(__('<strong>If you want more <a href="%1$s" target="_blank">get your SUMM AI API key now (opens new window)</a></strong>.<br>If you have any questions about the key provided by SUMM AI, please contact their support: <a href="%1$s" target="_blank">%1$s (opens new window)</a>', 'easy-language'), esc_url($this->get_language_specific_support_page()));
		if( false !== $this->is_summ_api_token_set() ) {
			// Set link to test the entered token.
			$url = add_query_arg(
				array(
					'action' => 'easy_language_summ_ai_test_token',
					'nonce' => wp_create_nonce( 'easy-language-summ-ai-test-token' )
				),
				get_admin_url() . 'admin.php'
			);

			// set link to remove the token.
			$remove_token_url = add_query_arg(
				array(
					'action' => 'easy_language_summ_ai_remove_token',
					'nonce' => wp_create_nonce( 'easy-language-summ-ai-remove-token' )
				),
				get_admin_url() . 'admin.php'
			);

			// Show other description if token is set.
			/* translators: %1$s will be replaced by the SUMM AI URL */
			$description = sprintf(__('If you have any questions about the key provided by SUMM AI, please contact their support: <a href="%1$s" target="_blank">%1$s (opens new window)</a>', 'easy-language'), esc_url($this->get_language_specific_support_page()));
			$description .= '<br><a href="'.esc_url($url).'" class="button button-secondary easy-language-settings-button">'.__( 'Test token', 'easy-language').'</a><a href="'.esc_url($remove_token_url).'" class="button button-secondary easy-language-settings-button">'.__( 'Remove token', 'easy-language').'</a>';
		}

		// if foreign translation-plugin with API-support is used, hide the language-settings.
		$foreign_translation_plugin_with_api_support = false;
		foreach( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if( $plugin_obj->is_foreign_plugin() && $plugin_obj->is_supporting_apis() ) {
				$foreign_translation_plugin_with_api_support = true;
			}
		}

		// SUMM API Token.
		add_settings_field(
			'easy_language_summ_ai_api_key',
			__( 'SUMM AI API Key', 'easy-language' ),
			'easy_language_admin_text_field',
			'easyLanguageSummAIPage',
			'settings_section_summ_ai',
			array(
				'label_for' => 'easy_language_summ_ai_api_key',
				'fieldId' => 'easy_language_summ_ai_api_key',
				'description' => $description,
				'placeholder' => __('Enter your key here', 'easy-language'),
				'highlight' => false === $this->is_summ_api_token_set()
			)
		);
		register_setting( 'easyLanguageSummAiFields', 'easy_language_summ_ai_api_key', array( 'sanitize_callback' => array( $this, 'validate_api_key') ) );

		// define url for general wp-settings.
		$wp_settings_url = add_query_arg(
			array(),
			'options-general.php'
		);

		// create hint for admins only.
		$hint = '';
		if( current_user_can( 'manage_options' ) ) {
			/* translators: %1$s will be replaced by the URL for general WordPress-settings */
			$hint = sprintf( __( 'You can change the admin-email in <a href="%1$s">General Settings for your WordPress</a>.', 'easy-language' ), esc_url( $wp_settings_url ) );
		}

		// Set email-mode.
		add_settings_field(
			'easy_language_summ_ai_email_mode',
			__( 'Choose email-mode', 'easy-language' ),
			'easy_language_admin_multiple_radio_field',
			'easyLanguageSummAIPage',
			'settings_section_summ_ai',
			array(
				'label_for' => 'easy_language_summ_ai_email_mode',
				'fieldId' => 'easy_language_summ_ai_email_mode',
				'options' => array(
					'custom' => array(
						'label' => __( 'Custom', 'easy-language'),
						'enabled' => true,
						'description' => __( 'Enter a custom email in the field bellow.', 'easy-language'),
					),
					'admin' => array(
						'label' => __( 'Use the admin-email', 'easy-language'),
						'enabled' => true,
						/* translators: %1$s is replaced with a hint for admins only. */
						'description' => sprintf( __( 'The WordPress-Admin-email will be used. %1$s', 'easy-language'), $hint ),
					),
					'editor' => array(
						'label' => __( 'Use editor email', 'easy-language'),
						'enabled' => true,
						'description' => __( 'The email of the actual user, which requests the simplification, will be used.', 'easy-language'),
					),
				),
				'readonly' => false === $this->is_summ_api_token_set(),
				'description_above' => true,
				'description' => __( 'An email will be used for each request to the SUMM AI API. It is used as contact or identifier email for SUMM AI if question for simplifications arise.', 'easy-language' )
			)
		);
		register_setting( 'easyLanguageSummAiFields', 'easy_language_summ_ai_email_mode', array( 'sanitize_callback' => array( $this, 'validate_multiple_radios' ) ) );

		// Contact email for SUMM AI API-requests.
		add_settings_field(
			'easy_language_summ_api_email',
			__( 'Contact email for SUMM AI', 'easy-language' ),
			'easy_language_admin_email_field',
			'easyLanguageSummAIPage',
			'settings_section_summ_ai',
			array(
				'label_for' => 'easy_language_summ_api_email',
				'fieldId' => 'easy_language_summ_api_email',
				'description' => __( 'This field is only enabled if the setting above is set to "Custom".', 'easy-language'),
				'placeholder' => __('Enter contact email here', 'easy-language'),
				'readonly' => 'custom' !== get_option( 'easy_language_summ_ai_email_mode', 'editor' )
			)
		);
		register_setting( 'easyLanguageSummAiFields', 'easy_language_summ_api_email', array( 'sanitize_callback' => array( $this, 'validate_api_email' ) ) );

		// Enable source-languages
		// -> defaults to WP-locale
		// -> if WPML, Polylang or TranslatePress is available, show additional languages
		// -> but restrict list to languages supported by SUMM AI
		add_settings_field(
			'easy_language_summ_ai_source_languages',
			__( 'Choose source languages', 'easy-language' ),
			'easy_language_admin_multiple_checkboxes_field',
			'easyLanguageSummAIPage',
			'settings_section_summ_ai',
			array(
				'label_for' => 'easy_language_summ_ai_source_languages',
				'fieldId' => 'easy_language_summ_ai_source_languages',
				'description' => __('These are the possible source languages for SUMM AI-simplifications. This language has to be the language which you use for any texts in your website.', 'easy-language'),
				'options' => $this->get_supported_source_languages(),
				'readonly' => false === $this->is_summ_api_token_set() || $foreign_translation_plugin_with_api_support,
				'pro_hint' => $this->get_pro_hint()
			)
		);
		register_setting( 'easyLanguageSummAiFields', 'easy_language_summ_ai_source_languages', array( 'sanitize_callback' => 'easyLanguage\Helper::settings_validate_multiple_checkboxes' ) );

		// Enable target languages.
		add_settings_field(
			'easy_language_summ_ai_target_languages',
			__( 'Choose target languages', 'easy-language' ),
			'easy_language_admin_multiple_checkboxes_field',
			'easyLanguageSummAIPage',
			'settings_section_summ_ai',
			array(
				'label_for' => 'easy_language_summ_ai_target_languages',
				'fieldId' => 'easy_language_summ_ai_target_languages',
				'description' => __('These are the possible target languages for SUMM AI-simplifications.', 'easy-language'),
				'options' => $this->get_supported_target_languages(),
				'readonly' => false === $this->is_summ_api_token_set() || $foreign_translation_plugin_with_api_support,
				'pro_hint' => $this->get_pro_hint()
			)
		);
		register_setting( 'easyLanguageSummAiFields', 'easy_language_summ_ai_target_languages', array( 'sanitize_callback' => array( $this, 'validate_language_settings' ) ) );

		// get possible intervals.
		$intervals = array();
		foreach ( wp_get_schedules() as $name => $schedule ) {
			$intervals[ $name ] = $schedule['display'];
		}

		// Interval for quota-request.
		add_settings_field(
			'easy_language_summ_ai_quota_interval',
			__( 'Interval for quota request', 'easy-language' ),
			'easy_language_admin_select_field',
			'easyLanguageSummAIPage',
			'settings_section_summ_ai',
			array(
				'label_for' => 'easy_language_summ_ai_quota_interval',
				'fieldId' => 'easy_language_summ_ai_quota_interval',
				'values' => $intervals,
				'readonly' => !$this->is_summ_api_token_set(),
				'description' => __( 'The actual API quota will be requested in this interval.', 'easy-language')
			)
		);
		register_setting( 'easyLanguageSummAiFields', 'easy_language_summ_ai_quota_interval', array( 'sanitize_callback' => array( $this, 'set_quota_interval' ) ) );

		// Enable test-marker.
		add_settings_field(
			'easy_language_summ_ai_test',
			__( 'Enable test-marker', 'easy-language' ),
			'easy_language_admin_checkbox_field',
			'easyLanguageSummAIPage',
			'settings_section_summ_ai',
			array(
				'label_for' => 'easy_language_summ_ai_test',
				'fieldId' => 'easy_language_summ_ai_test',
				'readonly' => !$this->is_summ_api_token_set() || $foreign_translation_plugin_with_api_support,
				'description' => __( 'If this is enabled no really simplification will be run through the API. No characters will be counted on your quota. Each text will be "simplified" with a given default-text by SUMM AI API.', 'easy-language')
			)
		);
		register_setting( 'easyLanguageSummAiFields', 'easy_language_summ_ai_test' );
	}

	/**
	 * Validate the SUMM API Token.
	 *
	 * @param $value
	 * @return ?string
	 * @noinspection PhpUnused
	 */
	public function validate_api_key( $value ): ?string {
		$errors = get_settings_errors();

		/**
		 * If a result-entry already exists, do nothing here.
		 *
		 * @see https://core.trac.wordpress.org/ticket/21989
		 */
		if( helper::check_if_setting_error_entry_exists_in_array('easy_language_summ_ai_api_key', $errors) ) {
			return $value;
		}

		// if no token has been entered, show hint.
		if( empty($value) ) {
			// set API-mode to 'free'.
			$this->set_mode( 'free' );

			// show error
			add_settings_error( 'easy_language_summ_ai_api_key', 'easy_language_summ_ai_api_key', __('You did not enter an API key. You will now use the free quota, if available.', 'easy-language') );
		}
		// if token has been changed, run tests with it.
		elseif( 0 !== strcmp($value, get_option( 'easy_language_summ_ai_api_key', '' ) ) ) {
			// switch mode to paid for test.
			$mode = $this->mode;
			$this->set_mode( 'paid' );
			$request = $this->get_test_request_response( $value );
			if( 403 === $request->get_http_status() ) {
				// show hint if token is not valid for API.
				add_settings_error( 'easy_language_summ_ai_api_key', 'easy_language_summ_ai_api_key', __('The API key does not seem to be valid.', 'easy-language') );

				// Log event.
				Log::get_instance()->add_log( sprintf( 'Token for SUMM AI has been changed, but we get an error from API by validation of the key. Please <a href="%1$s">check API log</a>.', esc_url(Helper::get_api_logs_page_url()) ), 'error' );

				// remove key.
				$value = '';

				// revert mode.
				$this->set_mode( $mode );
			}
			else {
				// get quota of the given key.
				$this->get_quota_from_api( $value );

				// set API-mode to 'paid'.
				$this->set_mode( 'paid' );

				// Log event.
				Log::get_instance()->add_log( 'Token for SUMM AI has been changed.', 'success' );
			}
		}

		// return the entered token.
		return $value;
	}

	/**
	 * Validate the insert email regarding its format.
	 *
	 * @param $value
	 * @return ?string
	 * @noinspection PhpUnused
	 */
	public function validate_api_email( $value ): ?string {
		if( !empty($value) && false === is_email( $value ) ) {
			add_settings_error( 'easy_language_summ_api_email', 'easy_language_summ_api_email', __('The given email is not a valid email-address.', 'easy-language') );
		}
		return $value;
	}

	/**
	 * Validate multiple radios.
	 *
	 * @param $value
	 *
	 * @return ?string
	 */
	public function validate_multiple_radios( $value ): ?string {
		return Helper::settings_validate_multiple_radios( $value );
	}

	/**
	 * Validate the language-settings.
	 *
	 * The source-language must be possible to simplify in the target-language.
	 *
	 * @param $values
	 *
	 * @return array|null
	 */
	public function validate_language_settings( $values ): ?array {
		$values = Helper::settings_validate_multiple_checkboxes( $values );
		if( empty($values) ) {
			add_settings_error( 'easy_language_target_languages', 'easy_language_target_languages', __('You have to set a target-language for simplifications.', 'easy-language') );
		}
		elseif( false === $this->is_language_set( $values ) ) {
			add_settings_error( 'easy_language_target_languages', 'easy_language_target_languages', __('At least one language cannot (currently) be translated into the selected target languages by the API.', 'easy-language') );
		}

		// return value.
		return $values;
	}

	/**
	 * Get quota from API.
	 *
	 * @param string $token The optional token.
	 *
	 * @return array
	 */
	public function get_quota_from_api( string $token = '' ): array {
		// get quota from api.
		$quota = $this->request_quota( $token );

		// get the transients object.
		$transients_obj = Transients::get_instance();

		// save value in db.
		if( !empty($quota) ) {
			update_option( 'easy_language_summ_ai_paid_quota', $quota );

			// show hint of 80% of limit is used.
			$percent = absint($quota['character_spent']) / absint($quota['character_limit']);
			if( 1 === $percent ) {
				// get the transients-object to add the new one.
				$transient_obj  = $transients_obj->add();
				$transient_obj->set_dismissible_days( 2 );
				$transient_obj->set_name( 'easy_language_summ_ai_quota' );
				/* translators: %1%s will be replaced by the URL for SUMM AI support */
				$transient_obj->set_message( sprintf(__( '<strong>Your quota for the SUMM AI API is completely depleted.</strong> You will not be able to request new simplifications from SUMM AI. Please contact the <a href="%1$s" target="_blank">SUMM AI support (opens new window)</a> about extending the quota.', 'easy-language' ), esc_url($this->get_language_specific_support_page()) ) );
				$transient_obj->set_type( 'error' );
				$transient_obj->save();
			}
			elseif( $percent > apply_filters( 'easy_language_quota_percent', 0.8 ) ) {
				// get the transients-object to add the new one.
				$transient_obj  = $transients_obj->add();
				$transient_obj->set_dismissible_days( 2 );
				$transient_obj->set_name( 'easy_language_summ_ai_quota' );
				/* translators: %1%s will be replaced by the URL for SUMM AI support */
				$transient_obj->set_message( sprintf(__( '<strong>More than 80 percent of your quota for the SUMM AI API has already been used.</strong> Please contact the <a href="%1$s" target="_blank">SUMM AI support (opens new window)</a> about extending the quota.', 'easy-language' ), esc_url($this->get_language_specific_support_page()) ) );
				$transient_obj->set_type( 'error' );
				$transient_obj->save();
			}
		}
		else {
			// delete quota-array in db.
			delete_option( 'easy_language_summ_ai_paid_quota' );

			// delete hint.
			$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_summ_ai_quota' );
			$transient_obj->delete();
		}

		// return quota.
		return $quota;
	}

	/**
	 * Get quota via link request.
	 *
	 * @return void
	 */
	public function get_quota_from_api_via_link(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-summ-ai-get-quota', 'nonce' );

		// get quota in paid mode.
		$mode = $this->get_mode();
		$this->set_mode('paid');
		$this->get_quota_from_api();
		$this->set_mode($mode );

		// redirect user.
		wp_redirect($_SERVER['HTTP_REFERER']);
	}

	/**
	 * Get quota.
	 *
	 * @param string $token
	 *
	 * @return array
	 */
	public function request_quota( string $token = '' ): array {
		// send request.
		$request = new Request();
		$request->set_token( empty($token) ? $this->get_token() : $token );
		$request->set_url( EASY_LANGUAGE_SUMM_AI_API_URL_QUOTA );
		$request->set_method( 'GET' );
		$request->send();

		// get the response.
		$response = $request->get_response();

		// transform it to an array and return it.
		$results = json_decode($response, true);
		if( is_array($results) ) {
			return $results;
		}

		// return empty array if request for quota does not give any result.
		return array();
	}

	/**
	 * Return the contact email.
	 *
	 * If not set in settings, use admin_email.
	 *
	 * @return string
	 */
	public function get_contact_email(): string {
		switch( get_option( 'easy_language_summ_ai_email_mode' ) ) {
			case 'custom':
				$email = get_option( 'easy_language_summ_api_email', '');
				if( empty($email) ) {
					return get_option( 'admin_email');
				}
				return $email;
			case 'editor':
				$user = wp_get_current_user();
				if( $user instanceof WP_User && !empty($user->user_email) ) {
					return $user->user_email;
				}
				return get_option( 'admin_email');
			case 'admin':
			default:
				return get_option( 'admin_email');
		}
	}

	/**
	 * Add settings-tab.
	 *
	 * @param $tab
	 *
	 * @return void
	 */
	public function add_settings_tab( $tab ): void {
		// get list of available plugins and check if they support APIs.
		$supports_api = false;
		foreach( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if( $plugin_obj->is_supporting_apis() && false === $plugin_obj->has_own_api_config() ) {
				$supports_api = true;
			}
		}

		// bail of plugin does not support api OR this API is not enabled.
		if( false === $supports_api || $this->get_name() !== get_option('easy_language_api', '') ) {
			return;
		}

		// check active tab
		$activeClass = '';
		if( 'summ_ai' === $tab ) $activeClass = ' nav-tab-active';

		// output tab
		echo '<a href="'.esc_url(helper::get_settings_page_url()).'&tab=summ_ai" class="nav-tab'.esc_attr($activeClass).'">'.__('SUMM AI', 'easy-language').'</a>';
	}

	/**
	 * Add settings page for this API.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		// bail if this API is not enabled.
		if( Apis::get_instance()->get_active_api()->get_name() !== $this->get_name() ) {
			return;
		}

		// get list of available plugins and check if they support APIs.
		$supports_api = false;
		foreach( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if( $plugin_obj->is_supporting_apis() ) {
				$supports_api = true;
			}
		}

		// bail if the plugin does not support APIs and also check user capabilities.
		if ( false === $supports_api || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<form method="POST" action="<?php echo get_admin_url(); ?>options.php">
			<?php
			settings_fields( 'easyLanguageSummAiFields' );
			do_settings_sections( 'easyLanguageSummAIPage' );
			submit_button();
			?>
		</form>
		<h2 id="statistics"><?php esc_html_e( 'SUMM AI Quota', 'easy-language'); ?></h2>
		<?php
		if( $this->is_summ_api_token_set() ) {
			/**
			 * Get and show the quota we received from API.
			 */
			$api_quota = $this->get_quota();
			if( empty($api_quota) ) {
				$quota_text = esc_html__( 'No quota consumed so far', 'easy-language' );
			}
			else {
				$quota_text = $api_quota['character_spent'].' / '.$api_quota['character_limit'];
			}

			// get the update quota link.
			$update_quota_url = add_query_arg(
				array(
					'action' => 'easy_language_summ_ai_get_quota',
					'nonce' => wp_create_nonce( 'easy-language-summ-ai-get-quota' )
				),
				get_admin_url() . 'admin.php'
			);

			// output.
			?>
			<p>
				<strong><?php echo esc_html__( 'Quota', 'easy-language' ); ?>:</strong> <?php echo $quota_text; ?>
				<a href="<?php echo esc_url($update_quota_url); ?>#statistics" class="button button-secondary"><?php echo esc_html__( 'Update now', 'easy-language' ); ?></a>
			</p>
			<?php
		}
		else {
			?><p><?php echo esc_html__( 'Info about quota will be available until the API token is set', 'easy-language' ); ?></p><?php
		}
	}

	/**
	 * Return whether the paid SUMM API token is set (true) or not (false).
	 *
	 * @return bool
	 */
	private function is_summ_api_token_set(): bool {
		return false === $this->is_free_mode() && !empty($this->get_token());
	}

	/**
	 * Return whether the customer email is set (true) or not (false).
	 *
	 * @return bool
	 */
	private function is_email_set(): bool {
		return !empty($this->get_contact_email());
	}

	/**
	 * Set the interval for the quota-schedule, if it is enabled.
	 *
	 * @param $value
	 *
	 * @return ?string
	 */
	public function set_quota_interval( $value ): ?string {
		$value = Helper::settings_validate_select_field( $value );
		if( !empty($value) ) {
			wp_clear_scheduled_hook( 'easy_language_summ_ai_request_quota' );
			wp_schedule_event(time(), $value, 'easy_language_summ_ai_request_quota');
		}

		// return setting.
		return $value;
	}

	/**
	 * Run a token test.
	 *
	 * @return void
	 */
	public function run_token_test(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-summ-ai-test-token', 'nonce' );

		// get global transients-object.
		$transients_obj = Transients::get_instance();

		// run test only is necessary values are set.
		if( $this->is_summ_api_token_set() && $this->is_email_set() ) {
			// send request.
			$request = $this->get_test_request_response();

			// add new transient for response to user.
			$transient_obj = $transients_obj->add();
			$transient_obj->set_name( 'easy_language_summ_ai_test_token' );
			if( 403 !== $request->get_http_status() ) {
				// show ok-message.
				$transient_obj->set_message( __( 'Token could be successfully verified.', 'easy-language' ) );
				$transient_obj->set_type( 'success' );
			}
			else {
				// show error.
				$transient_obj->set_message( __( 'Token could not be verified. Please take a look in the log to check the reason.', 'easy-language' ) );
				$transient_obj->set_type( 'error' );
			}
		}
		else {
			// show error via new transients object.
			$transient_obj = $transients_obj->add();
			$transient_obj->set_message( __( 'Token or contact email missing.', 'easy-language' ) );
			$transient_obj->set_type( 'error' );
		}
		$transient_obj->save();

		// redirect user.
		wp_redirect($_SERVER['HTTP_REFERER']);
	}

	/**
	 * Send test request to API.
	 *
	 * @param string $token
	 *
	 * @return Request
	 */
	private function get_test_request_response( string $token = '' ): Request {
		$request = new Request();
		$request->set_token( empty($token) ? $this->get_token() : $token );
		$request->set_url( EASY_LANGUAGE_SUMM_AI_PAID_API_URL );
		$request->set_is_test(1);
		$request->set_text('Tokentest');
		$request->send();

		// return object.
		return $request;
	}

	/**
	 * Remove token via click.
	 *
	 * @return void
	 */
	public function remove_token(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-summ-ai-remove-token', 'nonce' );

		// delete settings.
		delete_option( 'easy_language_summ_ai_api_key' );
		delete_option( 'easy_language_summ_ai_paid_quota' );

		// delete quota-hint.
		$transients_obj = Transients::get_instance();
		$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_summ_ai_quota' );
		$transient_obj->delete();

		// revert to free mode.
		$this->set_mode( 'free' );

		// redirect user.
		wp_redirect($_SERVER['HTTP_REFERER']);
	}

	/**
	 * Return whether we use the free mode.
	 *
	 * @return bool
	 */
	public function is_free_mode(): bool {
		return 'free' === $this->get_mode();
	}

	/**
	 * Get mode from DB.
	 *
	 * @return string
	 */
	public function get_mode(): string {
		$mode = get_option( 'easy_language_summ_ai_mode' );
		if( in_array( $mode, array( 'paid', 'free' ), true ) ) {
			return $mode;
		}
		return 'free';
	}

	/**
	 * Set mode for this API (paid or free).
	 *
	 * @param string $mode
	 *
	 * @return void
	 */
	private function set_mode( string $mode ): void {
		if( in_array( $mode, array( 'paid', 'free' ), true ) ) {
			// set in object.
			$this->mode = $mode;

			// save in db.
			update_option( 'easy_language_summ_ai_mode', $mode );
		}
	}

	/**
	 * Return that this API has settings if we are in paid-mode.
	 *
	 * @return bool
	 */
	public function has_settings(): bool {
		return false === $this->is_free_mode();
	}

	/**
	 * Return the settings-URL for the API.
	 *
	 * @return string
	 */
	public function get_settings_url(): string {
		return add_query_arg(
			array(
				'tab' => $this->get_name(),
			),
			Helper::get_settings_page_url()
		);
	}
}
