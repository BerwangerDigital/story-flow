<?php
/**
 * Managing database migrations in WordPress.
 *
 * @package StoryFlow
 */

namespace StoryFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'SF_Core' ) ) {
	class SF_Core {
		/**
		 * Get the default capability to manage everything for  Story Flow.
		 *
		 * @return string
		 */
		public static function get_capability() {

			/**
			 * Filters the default capability to manage everything for Story Flow.
			 *
			 * @param string $capability The default capability to manage everything for Story Flow.
			 */
			return apply_filters( 'story_flow_core_get_capability_manage_options', 'manage_options' );
		}
	}
}
