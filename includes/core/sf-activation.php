<?php
/**
 * Managing database migrations in WordPress.
 *
 * @package StoryFlow
 */

namespace StoryFlow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use \StoryFlow\Admin\SF_Menu_Area;
use \StoryFlow\Database\DB_Migrations;

if ( ! class_exists( 'SF_Activation' ) ) {
	class SF_Activation
	{
		public function __construct( $file ) {
			# Activation and deactivation
			register_activation_hook( $file, [ $this, 'activate' ] );
			register_deactivation_hook( $file, [ $this, 'deactivate' ] );

			// Initialize admin area.
			( new SF_Menu_Area() )->init();
		}

		public function activate() {
			global $wpdb;

			$migrations = new DB_Migrations();

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
					INDEX idx_sf_pitch_category (category), -- Índice completo na categoria
					INDEX idx_sf_pitch_topic (topic), -- Índice completo no tópico
					INDEX idx_sf_pitch_category_topic (category, topic), -- Índice composto em categoria e tópico
					FULLTEXT INDEX idx_sf_pitch_suggested_pitch (suggested_pitch), -- Índice FULLTEXT para busca no campo suggested_pitch
					UNIQUE idx_sf_unique_pitch (category, topic, main_seo_keyword, suggested_pitch(255)) -- Índice único mantendo prefixo no suggested_pitch
				) " . $migrations->get_charset_collate() . ";"
			);

			$migrations->register_table(
				SF__TABLE_PROMPTS,
				"CREATE TABLE {$wpdb->prefix}" . SF__TABLE_PROMPTS . " (
					id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					category VARCHAR(255) NOT NULL,
					topic VARCHAR(255) DEFAULT NULL,
					prompt TEXT NOT NULL,
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					INDEX idx_sf_prompt_category (category),
					INDEX idx_sf_prompt_topic (topic),
					FULLTEXT INDEX idx_sf_prompt_prompt (prompt),
					UNIQUE idx_sf_unique_prompt (category, topic)
				) " . $migrations->get_charset_collate() . ";"
			);

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

			// Run the creation of all registered tables
			$migrations->migrate();
		}

		public function deactivate() {
			//error_log(print_r('deactivate executed',true));
		}
	}
}
