<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'version.php';
require_once 'database.php';
require_once 'language.php';

defined('CONFIGURATION__OPENAI__APIKEY') || define('CONFIGURATION__OPENAI__APIKEY', '');
