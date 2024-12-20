<?php
/**
 * Database migrations.
 *
 * @package StoryFlow
 */

namespace StoryFlow\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'DB_Migrations' ) ) {
	class DB_Migrations {

		private $tables = [];
        private $charset_collate;

		public function __construct() {
            global $wpdb;
            $this->charset_collate = $wpdb->get_charset_collate();
		}

		/**
		 * Getter for charset_collate.
		 *
		 * @return string
		 */
		public function get_charset_collate() {
			return $this->charset_collate;
		}

		/**
		 * Registers a new table and its schema.
		 *
		 * @param string $table_name Table name (without prefix).
		 * @param string $schema SQL schema for the table.
		 */
        public function register_table( $table_name, $schema ) {
            global $wpdb;

            $prefixed_table_name = $wpdb->prefix . $table_name;
            $this->tables[ $prefixed_table_name ] = $schema;
        }

		/**
		 * Checks if a table exists in the database.
		 *
		 * @param string $table_name Full table name (with prefix).
		 * @return bool
		 */
        private function table_exists( $table_name ) {
            global $wpdb;

            $query = $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            );

            return $wpdb->get_var( $query ) === $table_name;
        }

		/**
         * Creates all registered tables if they do not already exist.
         */
        public function migrate() {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            foreach ( $this->tables as $table_name => $schema ) {
                if ( ! $this->table_exists( $table_name ) ) {
                    dbDelta( $schema );
                }
            }
        }
	}
}
