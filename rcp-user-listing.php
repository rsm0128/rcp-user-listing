<?php
/**
 * Plugin Name: Restrict Content Pro User Listing Addon
 * Plugin URI: https://github.com/rsm0128/rcp-user-listing
 * Description: This plugin provides user listing with restrict content pro support.
 * Version: 1.2
 * Author: rsm0128
 * Author URI: https://github.com/rsm0128/
 * Text Domain: msrcp
 *
 * @package UserListingRCP
 */

define( 'MSRCP_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'MSRCP_DIR_PATH', plugin_dir_path( __FILE__ ) );

require_once __DIR__ . '/php/class-singletone.php';
require_once __DIR__ . '/php/class-main.php';
require_once __DIR__ . '/php/class-profile.php';
require_once __DIR__ . '/php/class-rcp.php';

\UserListingRCP\Main::get_instance()->init();
