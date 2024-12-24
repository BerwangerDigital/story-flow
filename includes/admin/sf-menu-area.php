<?php
/**
 * Database migrations.
 *
 * @package StoryFlow
 */

namespace StoryFlow\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use StoryFlow\SF_Core;
use StoryFlow\Admin\Templates\Pitch_List_Table;
use StoryFlow\Admin\Templates\Prompt_List_Table;
use StoryFlow\Admin\Templates\News_List_Table;
use StoryFlow\Admin\Templates\Prompt_Add_Page;
use StoryFlow\Admin\Templates\Settings_Form_Page;

if ( ! class_exists( 'SF_Menu_Area' ) ) {
	class SF_Menu_Area {

		/**
		 * Slug of the admin area page.
		 *
		 * @var string
		 */
		const SLUG = 'story-flow';

		/**
		 * List of official registered pages.
		 *
		 * @since 1.5.0
		 *
		 * @var array
		 */
		public static $admin_pages_registered = [ 'pitchs', 'news', 'prompts', 'settings' ];

		public function init() {
			// Add the options page.
			add_action( 'admin_menu', [ $this, 'add_admin_menu_items' ] );

			// Outputs the plugin admin header.
			add_action( 'in_admin_header', [ $this, 'display_admin_header' ], 100 );

			// Outputs the plugin promotional admin footer.
			add_action( 'in_admin_footer', [ $this, 'display_admin_footer' ] );
		}

		/**
		 * Add admin area menu item.
		 */
		public function add_admin_menu_items() {
			add_menu_page(
				esc_html__('Story Flow', 'story-flow'),
				esc_html__('Story Flow', 'story-flow'),
				SF_Core::get_capability(),
				self::SLUG,
				[ $this, 'display' ],
				'dashicons-list-view',
				$this->get_menu_item_position()
			);

			// Dynamically defined submenus
			foreach (self::$admin_pages_registered as $slug) {
				add_submenu_page(
					self::SLUG,
					esc_html__($this->get_page_title($slug), 'story-flow'),
					esc_html__($this->get_page_title($slug), 'story-flow'),
					SF_Core::get_capability(),
					self::SLUG . '-' . $slug,
					[ $this, 'display' ]
				);
			}

			// Remove duplicate submenu pointing to the main menu.
			remove_submenu_page(self::SLUG, self::SLUG);
		}

		/**
		 * Get menu item position.
		 *
		 * @return int
		 */
		public function get_menu_item_position() {

			/**
			 * Filters menu item position.
			 *
			 * @param int $position Position number.
			 */
			return apply_filters( 'story_flow_admin_get_menu_item_position', 98 );
		}

		/**
		 * Outputs the plugin admin header.
		 */
		public function display_admin_header() {

			// Bail if we're not on a plugin page.
			if ( ! $this->is_admin_page() ) {
				return;
			}

			do_action( 'story_flow_admin_header_before' );
		}

		/**
		 * Oubputs the plugin admin footer
		 */
		public function display_admin_footer() {

			// Bail if we're not on a plugin page.
			if ( ! $this->is_admin_page() ) {
				return;
			}

			do_action( 'story_flow_admin_footer_before' );
		}

		/**
	 	 * Display content of the admin area page.
	 	 */
		public function display() {

			if (!$this->is_admin_page()) return;

			$page_slug = str_replace(self::SLUG . '-', '', sf_retrieve($_GET, 'page', false, 'sanitize_key'));
			$page_handler = $this->load_page_handler($page_slug);

			$page	= sf_retrieve($_GET, 'page', false, 'sanitize_key' );
			$action = sf_retrieve($_GET, 'action', false, 'sanitize_key' );

			echo '<div class="wrap story-flow__container">';

			$title = $this->get_title_admin_page(str_replace(self::SLUG . '-', '', $page), $action);
			printf("<h1 class='wp-heading-inline'>%s</h1>", esc_html__($title, 'story-flow'));

			switch ( $page_slug ) {
				case 'pitchs':
					?>
					<a href="#" class="page-title-action">Add New Suggestion</a>
					<a href="#" class="page-title-action">Import CSV</a>
					<hr class="wp-header-end">

					<form method="post">
					<?php

					//$wp_list_table = new Pitch_List_Table();
					$page_handler->views();
					$page_handler->prepare_items();
					$page_handler->search_box(esc_html__('Search Suggestions', 'story-flow'), 'pitch-suggestion-form');
					$page_handler->display();

					?>
					</form>
					<?php
					break;
				case 'settings':
					echo '<hr class="wp-header-end">';
					echo '<form method="post">';

					$page_handler->display();

					echo '</form>';
					break;
				case 'prompts':

					if (!$action) {
						printf('<a href="%s" class="page-title-action">%s</a>', esc_url(admin_url('admin.php?page=' . self::SLUG . '-prompts&action=add-form')), esc_html__('Add New Prompt', 'story-flow'));

						echo '<hr class="wp-header-end">';
						echo '<form method="post">';

						$page_handler->prepare_items();
						$page_handler->search_box(__('Search Prompts', 'story-flow'), 'prompt-search');
						$page_handler->display();

						echo '</form>';
					} else {
						$page_handler->display();
					}

					break;
				case 'news':
					echo '<hr class="wp-header-end">';
					echo '<form method="post">';

					$page_handler->prepare_items();
					$page_handler->search_box(__('Search Prompts', 'story-flow'), 'news-search');
					$page_handler->display();

					echo '</form>';
					break;
			}
			?>
			</div>
			<?php
		}

		/**
		 * Check whether we are on an admin page of story flow.
		 *
		 * @since 1.0.0
		 * @since 1.5.0 Added support for new pages.
		 *
		 * @param array|string $slug ID(s) of a plugin page. Possible values: 'general', 'logs', 'about' or array of them.
		 *
		 * @return bool
		 */
		public function is_admin_page($slug = []) {
			$page = sf_retrieve($_GET, 'page', false, 'sanitize_key');
			if (is_string($slug)) {
				$slug = [self::SLUG . '-' . sanitize_key($slug)];
			} elseif (empty($slug)) {
				$slug = array_map(function($item) {
					return self::SLUG . '-' . $item;
				}, self::$admin_pages_registered);
			}
			return is_admin() && in_array($page, $slug, true);
		}

		public function get_title_admin_page($slug) {
			$titles = [
				'pitchs'   => 'Pitch Suggestions',
				'news'     => 'News Repository',
				'prompts'  => 'Manage Prompts',
				'settings' => 'Settings',
			];

			return $titles[$slug] ?? esc_html__('Story Flow', 'story-flow');
		}

		private function get_page_title($slug) {
			$titles = [
				'pitchs'   => __('Pitch Suggestions', 'story-flow'),
				'news'     => __('News Repository', 'story-flow'),
				'prompts'  => __('Manage Prompts', 'story-flow'),
				'settings' => __('Settings', 'story-flow'),
			];

			return $titles[$slug] ?? __('Unknown', 'story-flow');
		}

		private function load_page_handler($page_slug) {
			$page_handlers = [
				'pitchs'   => Pitch_List_Table::class,
				'news'     => News_List_Table::class,
				'prompts'  => Prompt_List_Table::class,
				'settings' => Settings_Form_Page::class,
			];

			return isset($page_handlers[$page_slug]) ? new $page_handlers[$page_slug]() : null;
		}
	}
}
