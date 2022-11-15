<?php
/**
 * Bootstraps the Meta.
 *
 * @package UserListingRCP
 */

namespace UserListingRCP;

use UserListingRCP\Profile;
use UserListingRCP\RCP;

/**
 * Main bootstrap file.
 */
class Main extends Singletone {

	/**
	 * Initiate the resources.
	 */
	public function init() {
		Profile::get_instance()->init();
		RCP::get_instance()->init();
	}

}
