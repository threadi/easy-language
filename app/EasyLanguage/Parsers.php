<?php
/**
 * File for our own parser-support.
 *
 * This file handles which parsers are supported to get and simplify content in supported page-builders.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object for handle support for different parsers.
 */
class Parsers {

	/**
	 * Instance of this object.
	 *
	 * @var ?Parsers
	 */
	private static ?Parsers $instance = null;

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
	public static function get_instance(): Parsers {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize parsers-support for our own plugin.
	 *
	 * @return void
	 */
	public function init(): void {}

	/**
	 * Return the list of all parsers as objects.
	 *
	 * @return array<int,Parser_Base>
	 */
	public function get_parsers_as_objects(): array {
		// create the list.
		$list = array();

		foreach ( $this->get_parsers() as $class_name ) {
			// create the classname.
			$classname = $class_name . '::get_instance';

			// bail if the classname is not callable.
			if ( ! is_callable( $classname ) ) {
				continue;
			}

			// get the object.
			$obj = $classname();

			// bail if the object is not the handler base.
			if ( ! $obj instanceof Parser_Base ) {
				continue;
			}

			// add the object to the list.
			$list[] = $obj;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the list of page builder class names we support.
	 *
	 * @return array<int,string>
	 */
	private function get_parsers(): array {
		// create the list.
		$list = array(
			'\easyLanguage\Parser\Avada',
			'\easyLanguage\Parser\Avia',
			'\easyLanguage\Parser\BeaverBuilder',
			'\easyLanguage\Parser\BoldBuilder',
			'\easyLanguage\Parser\Breakdance',
			'\easyLanguage\Parser\Brizy',
			'\easyLanguage\Parser\Divi',
			'\easyLanguage\Parser\Elementor',
			'\easyLanguage\Parser\Gutenberg',
			'\easyLanguage\Parser\Kubio',
			'\easyLanguage\Parser\Salients_WpBakery',
			'\easyLanguage\Parser\SeedProd',
			'\easyLanguage\Parser\SiteOrigin',
			'\easyLanguage\Parser\Themify',
			'\easyLanguage\Parser\Undetected',
			'\easyLanguage\Parser\VisualComposer',
			'\easyLanguage\Parser\WpBakery',
		);

		/**
		 * Filter the list of supported parsers.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array<int,string> $list The list of supported parsers.
		 */
		return apply_filters( 'easy_language_parsers_list', $list );
	}
}
