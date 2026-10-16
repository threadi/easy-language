<?php
/**
 * File for handling Zion Builder as page builder.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\Plugin\Helper;

/**
 * Object to handle Zion Builder as page builder.
 *
 * Adds a language switcher element to Zion Builder.
 */
class ZionBuilder extends PageBuilder_Base {
	/**
	 * Instance of this object.
	 *
	 * @var ?ZionBuilder
	 */
	private static ?ZionBuilder $instance = null;

	/**
	 * Constructor for this object.
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
	public static function get_instance(): ZionBuilder {
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
		add_action( 'zionbuilder/elements_manager/register_elements', array( $this, 'register_element' ) );
	}

	/**
	 * Register our language switcher element in Zion Builder.
	 *
	 * @param object $elements_manager The elements manager of Zion Builder.
	 *
	 * @return void
	 */
	public function register_element( object $elements_manager ): void {
		// bail if the manager or the base class is not available.
		if ( ! method_exists( $elements_manager, 'register_element' ) || ! class_exists( '\ZionBuilder\Elements\Element' ) ) {
			return;
		}

		// register the element.
		$elements_manager->register_element( new ZionBuilder\Switcher_Element() );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'zionbuilder/zionbuilder.php' );
	}
}
