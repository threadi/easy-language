<?php
/**
 * File to handle support for the page builder Oxygen 6.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// deny direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\PageBuilder\Breakdance\Switcher_Element;

/**
 * Object to handle support for the page builder Oxygen 6.
 *
 * Breakdance and Oxygen 6 use the same engine and the same element: see Switcher_Element.
 */
class Oxygen extends PageBuilder_Base {
	/**
	 * Instance of this object.
	 *
	 * @var ?Oxygen
	 */
	private static ?Oxygen $instance = null;

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
	public static function get_instance(): Oxygen {
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
		// register our elements before the page builder loads the elements (with priority 10).
		add_action( 'breakdance_loaded', array( $this, 'register_elements' ), 8 );
	}

	/**
	 * Register our elements.
	 *
	 * @return void
	 */
	public function register_elements(): void {
		// bail if Oxygen 6 is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		// register the location of our elements.
		Switcher_Element::get_instance()->register_save_location();
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * Breakdance and Oxygen 6 set the constant BREAKDANCE_MODE.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return defined( 'BREAKDANCE_MODE' ) && 'oxygen' === constant( 'BREAKDANCE_MODE' );
	}
}
