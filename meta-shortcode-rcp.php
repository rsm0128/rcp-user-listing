<?php
/**
 * Plugin Name: MetaShortcode Restrict Content Pro Addon
 * Plugin URI: https://github.com/rsm0128/meta-shortcode-rcp
 * Description: This plugin is to render meta value on frontend
 * Version: 1.1
 * Author: rsm0128
 * Author URI: https://github.com/rsm0128/
 * Text Domain: msrcp
 *
 * @package MetaShortcodeRcp
 */

define( 'MSRCP_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'MSRCP_DIR_PATH', plugin_dir_path( __FILE__ ) );

require_once __DIR__ . '/php/class-singletone.php';
require_once __DIR__ . '/php/class-main.php';
require_once __DIR__ . '/php/class-profile.php';
require_once __DIR__ . '/php/class-rcp.php';

\MetaShortcodeRcp\Main::get_instance()->init();
