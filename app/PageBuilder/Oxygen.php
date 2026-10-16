<?php
/**
 * File for handling Oxygen (classic) as page builder.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\Parser\Oxygen as Oxygen_Parser;

/**
 * Object to handle Oxygen (classic) as page builder.
 *
 * Adds a language switcher element to Oxygen.
 */
class Oxygen extends PageBuilder_Base {
	/**
	 * Instance of this object.
	 *
	 * @var ?Oxygen
	 */
	private static ?Oxygen $instance = null;

	/**
	 * The registered element.
	 *
	 * @var object|null
	 */
	private ?object $element = null;

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
	 * The element is registered on "init" (and not on "plugins_loaded"), so its labels could be translated.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_element' ), 5 );
	}

	/**
	 * Register our language switcher element in Oxygen.
	 *
	 * @return void
	 */
	public function register_element(): void {
		// bail if Oxygen is not active or the elements API is not available.
		if ( ! $this->is_active() || ! class_exists( 'OxyEl' ) ) {
			return;
		}

		// register the element (Oxygen registers it in the constructor).
		$this->element = new Oxygen\Switcher_Element();
	}

	/**
	 * Return the registered element or null, if it is not registered.
	 *
	 * @return object|null
	 */
	public function get_element(): ?object {
		return $this->element;
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Oxygen_Parser::get_instance()->is_active();
	}
}
