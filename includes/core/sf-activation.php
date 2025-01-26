<?php
/**
 * Managing database migrations and scheduled tasks in WordPress.
 *
 * @package StoryFlow
 */

namespace StoryFlow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use \StoryFlow\Admin\SF_Menu_Area;
use \StoryFlow\Database\DB_Migrations;
use \StoryFlow\Queue\Queue_Processor;
use \StoryFlow\Queue\Pitch_Queue_Manager;

if ( ! class_exists( 'SF_Activation' ) ) {
	class SF_Activation {
		public function __construct( $file ) {
			// Activation and deactivation
			register_activation_hook( $file, [ $this, 'activate' ] );
			register_deactivation_hook( $file, [ $this, 'deactivate' ] );

			// Initialize admin area.
			( new SF_Menu_Area() )->init();

			// Hook for processing the queue
			add_action( 'sf_process_queue_event', [ $this, 'process_queue' ] );

			// Hook for checking `approved` status
			add_action( 'sf_check_approved_event', [ $this, 'check_approved_and_enqueue' ] );
		}

		/**
		 * Runs on plugin activation.
		 */
		public function activate() {
			global $wpdb;

			$migrations = new DB_Migrations();

			// Register Pitch Suggestions Table.
			$migrations->register_table(
				SF_TABLE_PITCH_SUGGESTIONS,
				"CREATE TABLE {$wpdb->prefix}" . SF_TABLE_PITCH_SUGGESTIONS . " (
					id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					category VARCHAR(255),
					topic VARCHAR(255),
					main_seo_keyword VARCHAR(255),
					suggested_pitch TEXT,
					origin ENUM('manual', 'automattic') DEFAULT 'manual',
					status ENUM('pending', 'approved', 'refused', 'processing', 'generated', 'published') DEFAULT 'pending',
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					INDEX idx_sfps_ca_s (created_at, status),
					INDEX idx_sfps_ua_s (updated_at, status),
					INDEX idx_sfps_c (category),
					INDEX idx_sfps_t (topic),
					INDEX idx_sfps_c_t (category, topic),
					FULLTEXT INDEX idx_sfps_sp (suggested_pitch)
				) " . $migrations->get_charset_collate() . ";"
			);

			// Register Prompts Table
			$migrations->register_table(
				SF_TABLE_PROMPTS,
				"CREATE TABLE {$wpdb->prefix}" . SF_TABLE_PROMPTS . " (
					id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					category VARCHAR(255) DEFAULT NULL,
					topic VARCHAR(255) DEFAULT NULL,
					prompt TEXT NOT NULL,
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					INDEX idx_sfp_c (category),
					INDEX idx_sfp_t (topic),
					FULLTEXT INDEX idx_sfp_p (prompt),
					UNIQUE idx_sfp_unique_c_t (category, topic)
				) " . $migrations->get_charset_collate() . ";"
			);

			// Register Queue Table.
			$migrations->register_table(
				SF_TABLE_QUEUE,
				"CREATE TABLE {$wpdb->prefix}" . SF_TABLE_QUEUE . " (
					id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					pitch_id BIGINT(20) UNSIGNED NOT NULL,
					status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
				) " . $migrations->get_charset_collate() . ";"
			);

			// Run the creation of all registered tables
			$migrations->migrate();

			// Schedule the cron events
			if ( ! wp_next_scheduled( 'sf_process_queue_event' ) ) {
				wp_schedule_event( time(), 'sf_five_minutes', 'sf_process_queue_event' );
			}

			if ( ! wp_next_scheduled( 'sf_check_approved_event' ) ) {
				wp_schedule_event( time(), 'sf_one_minute', 'sf_check_approved_event' );
			}
		}

		/**
		 * Runs on plugin deactivation.
		 */
		public function deactivate() {
			// Unschedule the cron events
			foreach ( [ 'sf_process_queue_event', 'sf_check_approved_event' ] as $event ) {
				$timestamp = wp_next_scheduled( $event );
				if ( $timestamp ) {
					wp_unschedule_event( $timestamp, $event );
				}
			}
		}

		/**
		 * Processes the queue on the scheduled event.
		 */
		public function process_queue() {
			$processor = new Queue_Processor();
			$processor->process_queue();
		}

		/**
		 * Checks for `approved` status and enqueues them.
		 */
		public function check_approved_and_enqueue() {
			$queue_manager = new Pitch_Queue_Manager();
			$queue_manager->check_approved_and_enqueue();
		}
	}
}

/**
 * Add custom intervals for cron.
 */
add_filter( 'cron_schedules', function ( $schedules ) {
	$schedules['sf_five_minutes'] = [
		'interval' => 5 * 60, // 5 minutes in seconds
		'display'  => __( 'Every Five Minutes', SF_TEXTDOMAIN )
	];

	$schedules['sf_one_minute'] = [
		'interval' => 60, // 1 minute in seconds
		'display'  => __( 'Every Minute', SF_TEXTDOMAIN )
	];

	return $schedules;
});
