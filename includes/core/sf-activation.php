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

if ( ! class_exists( 'SF_Activation' ) ) {
	class SF_Activation
	{
		public function __construct( $file ) {
			# Activation and deactivation
			register_activation_hook( $file, [ $this, 'activate' ] );
			register_deactivation_hook( $file, [ $this, 'deactivate' ] );

			// Initialize admin area.
			( new SF_Menu_Area() )->init();

            // Hook for processing the queue
            add_action('sf_process_queue_event', [$this, 'process_queue']);
		}

        /**
         * Runs on plugin activation.
         */
		public function activate() {
			global $wpdb;

			$migrations = new DB_Migrations();

			// Register Pitch Suggestions Table.
			$migrations->register_table(
				SF__TABLE_PITCH_SUGGESTIONS,
				"CREATE TABLE {$wpdb->prefix}" . SF__TABLE_PITCH_SUGGESTIONS . " (
					id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					category VARCHAR(255),
					topic VARCHAR(255),
					main_seo_keyword VARCHAR(255),
					suggested_pitch TEXT,
					origin ENUM('manual', 'automattic') DEFAULT 'manual',
					status ENUM('pending', 'assign', 'refused', 'processing', 'generated', 'published') DEFAULT 'pending',
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					INDEX idx_sf_created_status (created_at, status),
					INDEX idx_sf_updated_status (updated_at, status),
					INDEX idx_sf_pitch_category (category),
					INDEX idx_sf_pitch_topic (topic),
					INDEX idx_sf_pitch_category_topic (category, topic),
					FULLTEXT INDEX idx_sf_pitch_suggested_pitch (suggested_pitch),
					UNIQUE idx_sf_unique_pitch (category, topic, main_seo_keyword, suggested_pitch(255))
				) " . $migrations->get_charset_collate() . ";"
			);

			// Register Prompts Table
			$migrations->register_table(
				SF__TABLE_PROMPTS,
				"CREATE TABLE {$wpdb->prefix}" . SF__TABLE_PROMPTS . " (
					id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					pillar ENUM('sport', 'strategic-content', 'partner-content', 'proprietary-content') DEFAULT NULL,
					category VARCHAR(255) NOT NULL,
					topic VARCHAR(255) DEFAULT NULL,
					prompt TEXT NOT NULL,
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					INDEX idx_sf_prompt_category (category),
					INDEX idx_sf_prompt_topic (topic),
					FULLTEXT INDEX idx_sf_prompt_prompt (prompt),
					UNIQUE idx_sf_unique_pillar_category_topic (pillar, category, topic)
				) " . $migrations->get_charset_collate() . ";"
			);

			// Register Queue Table.
			$migrations->register_table(
				SF__TABLE_QUEUE,
				"CREATE TABLE {$wpdb->prefix}" . SF__TABLE_QUEUE . " (
					id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					pitch_id BIGINT(20) UNSIGNED NOT NULL,
					status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
				) " . $migrations->get_charset_collate() . ";"
			);

			// Register Generated Content Table
			$migrations->register_table(
				SF__TABLE_GENERATED_CONTENT,
				"CREATE TABLE {$wpdb->prefix}" . SF__TABLE_GENERATED_CONTENT . " (
					id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					content LONGTEXT NOT NULL,
					status ENUM('pending', 'approved', 'refused', 'discarded') DEFAULT 'pending',
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					validated_at DATETIME NULL,
					generated_by BIGINT(20) UNSIGNED NULL,
					metadata LONGTEXT NULL,
					INDEX idx_sf_generated_status (status),
					INDEX idx_sf_generated_created (created_at),
					INDEX idx_sf_generated_validated (validated_at)
				) " . $migrations->get_charset_collate() . ";"
			);

			// Run the creation of all registered tables
			$migrations->migrate();

            // Schedule the cron event for processing the queue
            if (!wp_next_scheduled('sf_process_queue_event')) {
                wp_schedule_event(time(), 'sf_five_minutes', 'sf_process_queue_event');
            }
		}

        /**
         * Runs on plugin deactivation.
         */
        public function deactivate() {
            // Unschedule the cron event
            $timestamp = wp_next_scheduled('sf_process_queue_event');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'sf_process_queue_event');
            }
        }

        /**
         * Processes the queue on the scheduled event.
         */
        public function process_queue() {
            $processor = new Queue_Processor();
            $processor->process_queue();
        }
    }
}

/**
 * Add custom interval for 15 minutes.
 */
add_filter('cron_schedules', function ($schedules) {
    $schedules['sf_five_minutes'] = [
        'interval' => 5 * 60, // 15 minutes in seconds
        'display'  => __('Every Five Minutes', 'story-flow')
    ];
    return $schedules;
});
