<?php
/**
 * Singletone abscract class.
 *
 * @package UserListingRCP
 */

namespace UserListingRCP;

/**
 * Class Singletone
 *
 * @package UserListingRCP
 */
abstract class Singletone {

	/**
	 * Initiator
	 */
	final public static function get_instance() {
		static $instances = array();

		$called_class = get_called_class();

		if ( ! isset( $instances[ $called_class ] ) ) {
			$instances[ $called_class ] = new $called_class();
		}

		return $instances[ $called_class ];
	}

	/**
	 * Constructor
	 */
	protected function __construct() {
	}

}
