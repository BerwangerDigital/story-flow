<?php
/**
 * Plugin Name:     Story Flow
 * Plugin URI:      https://berwanger.digital/plugins/story-flow/
 * Description:     A powerful tool to streamline the creation and management of pitch suggestions for news publishers and blogs to generate a news repository.
 * Author:          Marcelo Berwanger
 * Author URI:      https://berwanger.digital
 * Text Domain:     story-flow
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         StoryFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'SF_VERSION', '0.1.0' );

defined( 'SF__PLUGIN_DIRPATH' ) || define( 'SF__PLUGIN_DIRPATH', __DIR__ );
defined( 'SF__PLUGIN_URL' ) || define( 'SF__PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Constant definitions required for operation
require_once SF__PLUGIN_DIRPATH . '/constants/loader.php';

// Autoloaders
require_once SF__PLUGIN_DIRPATH . '/utilities/autoload.php';

// Composer
if ( file_exists( SF__PLUGIN_DIRPATH . '/vendor/autoload.php' ) ) {
    require_once SF__PLUGIN_DIRPATH . '/vendor/autoload.php';
}

// Utilities
require_once SF__PLUGIN_DIRPATH . '/utilities/functions.php';

// Initialization
new \StoryFlow\Core\SF_Activation( __FILE__ );
