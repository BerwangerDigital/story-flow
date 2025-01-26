<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

spl_autoload_register( function ( $class ) {

	list( $plugin_space ) = explode( '\\', $class );
	if ( $plugin_space !== 'StoryFlow' ) {
		return;
	}

	// Get the relative class name.
	$fileparts = substr( $class, strlen( $plugin_space ) + 1 );

	$filename = str_replace( '\\', DIRECTORY_SEPARATOR, strtolower( preg_replace( '/([a-z])([A-Z])|_/', '$1-$2', $fileparts ) ) );

	$filepath = wp_normalize_path( SF_PLUGIN_DIRPATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $filename . '.php' );

	// If the file exists, require it.
	if ( is_readable( $filepath ) ) {
		require_once $filepath;
	}
}, true, true );
