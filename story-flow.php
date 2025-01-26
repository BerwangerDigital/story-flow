<?php
/**
 * Plugin Name:     Story Flow
 * Plugin URI:      https://berwanger.digital/plugins/story-flow/
 * Description:     A powerful tool to streamline the creation and management of pitch suggestions for news publishers and blogs to generate a news repository.
 * Author:          Marcelo Berwanger
 * Author URI:      https://berwanger.digital
 * Text Domain:     story-flow
 * Domain Path:     /languages
 * Requires PHP:    7.4
 * Version:         1.0.0
 *
 * @package         StoryFlow
 */

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'SF_PLUGIN_DIRPATH', __DIR__ );
define( 'SF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Constant definitions required for operation
require_once SF_PLUGIN_DIRPATH . '/constants/loader.php';

// Load text domain
add_action(
	'init',
	static function () {
		load_plugin_textdomain( SF_TEXTDOMAIN, false, SF_DOMAIN_PATH );
	}
);

// Autoloaders
require_once SF_PLUGIN_DIRPATH . '/utilities/autoload.php';

// Composer
if ( file_exists( SF_PLUGIN_DIRPATH . '/vendor/autoload.php' ) ) {
    require_once SF_PLUGIN_DIRPATH . '/vendor/autoload.php';
}

// Utilities
require_once SF_PLUGIN_DIRPATH . '/utilities/functions.php';

// Activate the plugin
add_action(
	'plugins_loaded',
	static function () {
		// Initialization
		new \StoryFlow\Core\SF_Activation( __FILE__ );
	}
);
