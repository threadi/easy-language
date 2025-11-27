<?php
/**
 * File for initializing the sublanguage-support.
 *
 * @package easy-language
 */

namespace easyLanguage\ThirdPartySupport\Sublanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Base;
use easyLanguage\Plugin\Languages;
use easyLanguage\Plugin\ThirdPartySupport_Base;
use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use WP_Post;
use WP_Query;

/**
 * Object to handle the sublanguage-support.
 */
class Init extends Base implements ThirdPartySupport_Base {

	/**
	 * Name of this plugin.
	 *
	 * @var string
	 */
	protected string $name = 'sublanguage';

	/**
	 * Title of this plugin.
	 *
	 * @var string
	 */
	protected string $title = 'Sublanguage';

	/**
	 * Instance of this object.
	 *
	 * @var ?Init
	 */
	private static ?Init $instance = null;

	/**
	 * Constructor for Init-Handler.
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
	public static function get_instance(): Init {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// add hooks.
		add_action( 'admin_init', array( $this, 'wp_init' ) );

		// disable transients on sublanguage-deactivation.
		add_action( 'deactivate_sublanguage/sublanguage.php', array( $this, 'foreign_deactivate' ) );
	}

	/**
	 * Run on plugin-installation.
	 *
	 * @return void
	 */
	public function install(): void {}

	/**
	 * Run on each request to add our supported languages.
	 *
	 * @return void
	 */
	public function wp_init(): void {
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $language ) {
			$query   = array(
				'post_type'   => 'language',
				'title'       => $language['label'],
				'post_status' => array( 'any', 'trash' ),
				'fields'      => 'ids',
			);
			$results = new WP_Query( $query );
			if ( 0 === $results->post_count ) {
				$array = array(
					'post_type'    => 'language',
					'post_title'   => $language['label'],
					'post_content' => $language_code,
					'post_status'  => 'publish',
				);
				wp_insert_post( $array );
			}
		}
	}

	/**
	 * Run on uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {}

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public function deactivation(): void {}

	/**
	 * Run on deactivation of sublanguage.
	 *
	 * @return void
	 */
	public function foreign_deactivate(): void {
		// get transient objects-object.
		$transients_obj = Transients::get_instance();

		// get transient-object for this plugin.
		$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_plugin_' . $this->get_name() );

		// delete it.
		$transient_obj->delete();
	}

	/**
	 * Return list of active languages this plugin is using atm.
	 *
	 * @return array<string,string>
	 */
	public function get_active_languages(): array {
		// define return array-list.
		$languages = array();

		// query the actual languages used by sublanguage.
		$query   = array(
			'post_type'   => 'language',
			'post_status' => 'publish',
		);
		$results = new WP_Query( $query );

		// loop through them.
		foreach ( $results->get_posts() as $language ) {
			// bail if object is not WP_Post.
			if ( ! $language instanceof WP_Post ) {
				continue;
			}

			// add the language.
			$languages[ $language->post_content ] = '1';
		}

		// return resulting list.
		return $languages;
	}

	/**
	 * We do not add any scripts for sublanguage.
	 *
	 * @return void
	 */
	public function get_simplifications_scripts(): void {}

	/**
	 * Additional cli functions we do not use for this plugin.
	 *
	 * @return void
	 */
	public function cli(): void {}
}
