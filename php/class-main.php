<?php
/**
 * Bootstraps the Meta.
 *
 * @package MetaShortcodeRcp
 */

namespace MetaShortcodeRcp;

use MetaShortcodeRcp\Profile;
use MetaShortcodeRcp\RCP;

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
