<?php
/**
 * This file contains the handling of transients for this plugin in wp-admin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Initialize the transients-object.
 */
class Transients {
	/**
	 * Instance of actual object.
	 *
	 * @var Transients|null
	 */
	private static ?Transients $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Transients
	 */
	public static function get_instance(): Transients {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Adds a single transient.
	 *
	 * @return Transient
	 */
	public function add(): Transient {
		// create new object and return it directly.
		return new Transient();
	}

	/**
	 * Get all known transients as objects.
	 *
	 * @return array<string,Transient>
	 */
	public function get_transients(): array {
		$transients = array();

		// get list of our own transients from DB as array.
		$transients_from_db = get_option( EASY_LANGUAGE_TRANSIENT_LIST, array() );
		if ( ! is_array( $transients_from_db ) ) {
			$transients_from_db = array();
		}

		// loop through the list and create the corresponding transient-objects.
		foreach ( $transients_from_db as $transient ) {
			// create the object from setting.
			$transient_obj = new Transient( $transient );

			// add object to list.
			$transients[ $transient ] = $transient_obj;
		}

		// return the resulting list as array.
		return $transients;
	}

	/**
	 * Add new transient to list of our plugin-specific transients.
	 *
	 * @param Transient $transient_obj The transient-object to add.
	 *
	 * @return void
	 */
	public function add_transient( Transient $transient_obj ): void {
		// get actual known transients as array.
		$transients = $this->get_transients();

		// bail if transient is already on list.
		if ( ! empty( $transients[ $transient_obj->get_name() ] ) ) {
			return;
		}

		// add the new one to the list.
		$transients[ $transient_obj->get_message() ] = $transient_obj;

		// transform list to simple array for options-table.
		$transients_in_db = array();
		foreach ( $transients as $transient ) {
			$transients_in_db[] = $transient->get_name();
		}

		// update the transients-list in db.
		update_option( EASY_LANGUAGE_TRANSIENT_LIST, $transients_in_db );
	}

	/**
	 * Delete single transient from our own list.
	 *
	 * @param Transient $transient_to_delete_obj The transient-object to delete.
	 *
	 * @return void
	 */
	public function delete_transient( Transient $transient_to_delete_obj ): void {
		// get actual known transients as array.
		$transients = $this->get_transients();

		// bail if transient is not in our list.
		if ( empty( $transients[ $transient_to_delete_obj->get_name() ] ) ) {
			return;
		}

		// remove it from the list.
		unset( $transients[ $transient_to_delete_obj->get_name() ] );

		// transform list to simple array for options-table.
		$transients_in_db = array();
		foreach ( $transients as $transient ) {
			$transients_in_db[] = $transient->get_name();
		}
		update_option( EASY_LANGUAGE_TRANSIENT_LIST, $transients_in_db );
	}

	/**
	 * Check all known transients.
	 *
	 * @return void
	 */
	public function check_transients(): void {
		$transients = $this->get_transients();

		/**
		 * Filter the list of transients.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $transients List of transients.
		 */
		foreach ( apply_filters( 'easy_language_get_transients_for_display', $transients ) as $transient_obj ) {
			if ( $transient_obj->is_set() ) {
				$transient_obj->display();
			}
		}
	}

	/**
	 * Return a specific transient by its internal name.
	 *
	 * @param string $transient The transient-name we search.
	 *
	 * @return Transient
	 */
	public function get_transient_by_name( string $transient ): Transient {
		return new Transient( $transient );
	}
}
