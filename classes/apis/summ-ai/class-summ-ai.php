<?php
/**
 * File for handler for things the SUMM AI supports.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Summ_Ai;

use easyLanguage\Base;
use easyLanguage\Api_Base;
use easyLanguage\Helper;
use easyLanguage\Language_Icon;
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
	 * Constructor for Init-Handler.
	 */
	private function __construct() {
		global $wpdb;

		// get the db-connection.
		$this->wpdb = $wpdb;

		// table for requests and responses.
		$this->table_requests = DB::get_instance()->get_wpdb_prefix() . 'easy_language_summ_ai';
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

		/* translators: %1$d will be replaced by a number, %2$s will be replaced by the URL for the Pro, %3$s will be replaced by the URL for SUMM AI-product-info */
		$text = sprintf( __( '<p>Make any complicated text barrier-free and understandable with the <a href="%1$s" target="_blank"><strong>SUMM AI</strong> (opens new window)</a> AI-based tool.<br>Create simple and easy-to-understand texts on your website.</p><p>This API simplifies texts according to the official rules of the Leichte Sprache e.V.<br>This specifies how texts must be written in easy language.</ p><p><strong>With the free Easy Language plugin you have a quota of %2$d characters available for text simplifications.</strong></p>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ), absint( $quota['character_limit'] ) );
		if ( $quota['character_limit'] > 0 ) {
			/* translators: %1$d will be replaced by quota for this API, %2$d will be the characters spent for this API, %3$d will be the rest quota */
			$text .= $this->get_quota_table();
		}

		// wrapper for buttons.
		$text .= '<p>';

		// help-button.
		$text .= '<a href="' . esc_url( $this->get_language_specific_support_page() ) . '" target="_blank" class="button button-primary" title="' . esc_attr( __( 'Get help for this API', 'easy-language' ) ) . '"><span class="dashicons dashicons-editor-help"></span></a>';

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
						$languages[ $language ] = '1';
					}
				}
			}
			update_option( 'easy_language_summ_ai_target_languages', $languages );
		}

		// set SUMM AI API as default API, if not already set.
		if ( ! get_option( 'easy_language_api' ) ) {
			update_option( 'easy_language_api', $this->get_name() );
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
			'easy_language_summ_ai_source_languages',
			'easy_language_summ_ai_target_languages',
		);
	}

	/**
	 * Return list of transients this plugin is using, e.g. for clean uninstall.
	 *
	 * @return array
	 */
	private function get_transients(): array {
		return array();
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
	 * We have no settings for SUMM AI in free version.
	 *
	 * @param string $tab The tab internal name.
	 *
	 * @return void
	 */
	public function add_settings_tab( string $tab ): void {}

	/**
	 * Add settings page: none for this API.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {}

	/**
	 * Add settings: none for this API.
	 *
	 * @return void
	 */
	public function add_settings(): void {}

	/**
	 * Get quota as array containing 'character_spent' and 'character_limit'.
	 *
	 * @return array
	 */
	public function get_quota(): array {
		return array(
			'character_spent' => get_option( 'easy_language_summ_ai_quota', 0 ),
			'character_limit' => EASY_LANGUAGE_SUMM_AI_QUOTA,
		);
	}

	/**
	 * Use admin email as contact email for each translation.
	 *
	 * @return string
	 */
	public function get_contact_email(): string {
		return get_option( 'admin_email' );
	}

	/**
	 * Return API URL.
	 *
	 * @return string
	 */
	public function get_api_url(): string {
		return EASY_LANGUAGE_SUMM_AI_API_URL;
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
		return sprintf( __( 'Get more quota with <a href="%1$s" target="_blank" title="link opens new window">Easy Language Pro</a> and custom <a href="%2$s" target="_blank">SUMM AI API Key</a>', 'easy-language' ), esc_url( Helper::get_pro_url() ), esc_url( $this->get_language_specific_support_page() ) );
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
