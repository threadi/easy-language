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
use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use easyLanguage\Transients;
use wpdb;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

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
    private array $support_url = array(
        'de_DE' => 'https://summ-ai.com/unsere-schnittstelle/'
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
		$quota = $this->get_quota();
		/* translators: %1$d will be replaced by a number, %2$s will be replaced by the URL for the Pro, %3$s will be replaced by the URL for SUMM AI-product-info */
		return sprintf( __( '<p>The SUMM AI API allows you to automatically translate the entire website into plain and/or simple language via quota-limited API.</p><p>In the free Easy Language plugin you have a quota of %1$d characters you will be able to translate.</p><p>You need <a href="%2$s" target="_blank">Easy Language Pro (opens new window)</a> and a SUMM AI API key to translate more texts: <a href="%3$s" target="_blank">%3$s</a>.</p><p><strong>Actual character spent:</strong> %4$d<br><strong>Quota limit:</strong> %1$d<br><strong>Rest quota:</strong> %5$d</strong></p>', 'easy-language' ), $quota['character_limit'], 'todo', esc_url($this->get_language_specific_support_page()), $quota['character_spent'], absint($quota['character_limit'] - $quota['character_spent']) );
	}

	/**
     * Return the URL of the public logo for this API.
     *
	 * @return string
	 */
    public function get_logo_url(): string {
        return Helper::get_plugin_url().'classes/apis/summ-ai/gfx/logo.jpg';
    }

	/**
	 * Return list of supported source-languages.
	 *
	 * @return array
	 * @noinspection DuplicatedCode
	 */
	public function get_supported_source_languages(): array {
		return array(
			'de_DE' => array(
				'label' => __( 'German', 'easy-language'),
				'enable' => true,
				'description' => __( 'Informal german spoken in Germany.', 'easy-language')
			),
			'de_DE_formal' => array(
				'label' => __( 'German (Formal)', 'easy-language'),
				'enable' => true,
				'description' => __( 'Formal german spoken in Germany.', 'easy-language')
			),
			'de_CH' => array(
				'label' => __( 'Suisse german', 'easy-language'),
				'enable' => true,
				'description' => __( 'Formal german spoken in Suisse.', 'easy-language')
			),
			'de_CH_informal' => array(
				'label' => __( 'Suisse german (Informal)', 'easy-language'),
				'enable' => true,
				'description' => __( 'Informal german spoken in Suisse.', 'easy-language')
			),
			'de_AT' => array(
				'label' => __( 'Austria German', 'easy-language'),
				'enable' => true,
				'description' => __( 'German spoken in Austria.', 'easy-language')
			)
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
				'label' => __( 'Einfache Sprache', 'easy-language'),
				'enabled' => true,
				'description' => __( 'The Einfache Sprache used in Germany, Suisse and Austria.', 'easy-language'),
				'url' => 'de_el',
				'api_value' => 'plain',
			),
			'de_LS' => array(
				'label' => __( 'Leichte Sprache', 'easy-language'),
				'enabled' => true,
				'description' => __( 'The Leichte Sprache used in Germany, Suisse and Austria.', 'easy-language'),
				'url' => 'de_ls',
                'api_value' => 'easy',
			)
		);
	}

	/**
	 * Return the list of supported languages which could be translated with this API into each other.
	 *
	 * Left source, right possible target languages.
	 *
	 * @return array
	 */
	public function get_mapping_languages(): array {
		return array(
			'de_DE' => array( 'de_LS', 'de_EL' )
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
		if( !get_option('easy_language_source_languages') ) {
			$language = helper::get_wp_lang();
			$languages = array( $language => "1" );
			update_option( 'easy_language_source_languages', $languages );
		}

		// set target language depending on source-language and if only one target could be possible.
		if( !get_option('easy_language_target_languages') ) {
			$source_languages = get_option( 'easy_language_source_languages', array() );
			$languages = array();
			$mappings = $this->get_mapping_languages();
			foreach( $source_languages as $source_language => $enabled ) {
				if( !empty($mappings[$source_language]) ) {
					foreach( $mappings[$source_language] as $language ) {
						$languages[ $language ] = "1";
					}
				}
			}
			update_option( 'easy_language_target_languages', $languages );
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
	 * Install-routines for the API, called during plugin-activation and API-change.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		/**
		 * Remove settings.
		 */
		foreach( $this->get_options() as $option_name ) {
			delete_option($option_name);
		}

		/**
		 * Delete our table.
		 */
		$sql = 'DROP TABLE IF EXISTS '.$this->table_requests;
        $this->wpdb->query($sql);
	}

	/**
	 * Return list of options this plugin is using, e.g. for clean uninstall.
	 *
	 * @return array
	 */
	private function get_options(): array {
		return array(
			'easy_language_source_languages',
			'easy_language_target_languages',
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
	 * Return the translations-object.
	 *
	 * @return Translations
	 */
	public function get_translations_obj(): Translations {
		// get the object.
		$obj = Translations::get_instance();

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
		$target_languages = get_option( 'easy_language_target_languages', array() );
		if( !is_array($target_languages) ) {
			$target_languages = array();
		}

		// define resulting list
		$list = array();

		foreach( $this->get_supported_target_languages() as $language_code => $language ) {
			if( !empty($target_languages[$language_code]) ) {
				$list[$language_code] = $language;
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
        foreach( $this->get_transients() as $transient_name ) {
            $transient_obj = $transients_obj->get_transient_by_name( $transient_name );
            $transient_obj->delete();
        }
    }

	/**
	 * Return the language-specific support-URL for SUMM AI.
	 *
	 * @return string
	 */
	private function get_language_specific_support_page(): string {
		// return language-specific URL if it exists.
		if( !empty($this->support_url[helper::get_current_language()]) ) {
			return $this->support_url[helper::get_current_language()];
		}

		// otherwise return default url.
		return $this->support_url['de_DE'];
	}

	/**
	 * We have no settings for SUMM AI in free version.
	 *
	 * @param $tab
	 *
	 * @return void
	 */
	public function add_settings_tab( $tab ): void {}
	public function add_settings_page(): void {}
	public function add_settings(): void {}

	/**
	 * Get quota as array containing 'character_spent' and 'character_limit'.
	 *
	 * @return array
	 */
	public function get_quota(): array {
		return array(
			'character_spent' => get_option( 'easy_language_summ_ai_quota', 0 ),
			'character_limit' => EASY_LANGUAGE_SUMM_AI_QUOTA
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
		$source_languages = get_option( 'easy_language_source_languages', array() );
		if( !is_array($source_languages) ) {
			$source_languages = array();
		}

		// define resulting list
		$list = array();

		foreach( $this->get_supported_source_languages() as $language_code => $language ) {
			if( !empty($source_languages[$language_code]) ) {
				$list[$language_code] = $language;
			}
		}

		// return resulting list.
		return $list;
	}
}
