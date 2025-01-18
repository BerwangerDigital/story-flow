<?php

namespace StoryFlow\Admin\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WP_List_Table;

/**
 * Class News_List_Table
 *
 * Handles the custom list table for managing AI-generated content for review.
 */
class News_List_Table extends WP_List_Table {

    private $table_data;
    private $table_name;
    // private $wp_date_format;
    // private $wp_time_format;

    private const ALLOWED_ORDERBY_COLUMNS = ['post_title', 'post_date', 'post_modified'];
    // // private const VALID_STATUSES = ['pending', 'approved', 'refused'];

    public function __construct() {
        global $wpdb;

        $this->table_name = $wpdb->prefix . 'posts';
    //     $this->wp_date_format = get_option('date_format');
    //     $this->wp_time_format = get_option('time_format');

        parent::__construct([
            'singular' => 'sf_generated_news',
            'plural'   => 'sf_generated_news_list',
            'ajax'     => false,
        ]);

    //     $this->process_actions();
    }

    // private function process_actions() {
    //     $action = sf_retrieve($_GET, 'action', '', 'sanitize_text_field');

    //     if ($action === 'delete') {
    //         $this->process_delete_action();
    //     } elseif ($action === 'change_status') {
    //         $this->process_change_status();
    //     }
    // }

    // private function process_delete_action() {
    //     if (empty($_GET['post']) || empty($_GET['nonce'])) {
    //         return;
    //     }

    //     $post_id = absint($_GET['post']);
    //     $nonce = $_GET['nonce'];

    //     if (!wp_verify_nonce($nonce, 'delete_generated_content_' . $post_id)) {
    //         wp_die(__('Invalid nonce. Action not allowed.', 'story-flow'));
    //     }

    //     global $wpdb;

    //     $deleted = $wpdb->delete(
    //         $this->table_name,
    //         ['ID' => $post_id],
    //         ['%d']
    //     );

    //     if (false === $deleted) {
    //         wp_die(__('Failed to delete the item.', 'story-flow'));
    //     }

    //     add_action('admin_notices', function () {
    //         echo '<div id="message" class="notice notice-success is-dismissible"><p>' . esc_html__('Item deleted successfully.', 'story-flow') . '</p></div>';
    //     });
    // }

    public function get_columns() {
        return [
            'post_title'         => __('Title', 'story-flow'),
            'post_date'    => __('Date', 'story-flow'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'post_title'         => ['post_title', true],
            'post_date'    => ['post_date', true],
        ];
    }

	private function get_table_data($per_page = 10, $current_page = 1, $orderby = 'post_date', $order = 'DESC') {
		global $wpdb;

		$orderby = in_array($orderby, self::ALLOWED_ORDERBY_COLUMNS) ? $orderby : 'post_date';
		$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

		$query = "SELECT * FROM {$this->table_name} WHERE (post_status = 'draft' OR post_status = 'publish') AND post_type = 'post'";

		$offset = ($current_page - 1) * $per_page;
		$query .= " ORDER BY {$orderby} {$order}";
		$query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);

		return $wpdb->get_results($query, ARRAY_A);
	}

    // private function get_total_items() {
    //     global $wpdb;

    //     $status_filter = sf_retrieve($_GET, 'status', '', 'sanitize_text_field');
    //     $search = sf_retrieve($_POST, 's', '', 'sanitize_text_field');

    //     $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1";

    //     if (!empty($status_filter)) {
    //         $query .= $wpdb->prepare(" AND status = %s", $status_filter);
    //     }

    //     if (!empty($search)) {
    //         $query .= $wpdb->prepare(" AND title LIKE %s", '%' . $wpdb->esc_like($search) . '%');
    //     }

        // return (int) $wpdb->get_var($query);
    // }

    public function prepare_items() {
        $per_page = $this->get_items_per_page('sf_generated_news_per_page', 10);
        $current_page = $this->get_pagenum();

        $orderby = sf_retrieve($_GET, 'orderby', 'post_date', 'sanitize_text_field');
        $order = sf_retrieve($_GET, 'order', 'desc', 'sanitize_text_field');

        $this->table_data = $this->get_table_data($per_page, $current_page, $orderby, $order);

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $total_items = $this->get_total_items();

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $this->items = $this->table_data;
    }

    public function column_post_title($item) {
		$edit_link = get_edit_post_link($item['ID']);
		$publish_nonce = wp_create_nonce('publish_generated_content_' . $item['ID']);
		$publish_link = admin_url('edit.php?page=news-repository&action=publish&post=' . $item['ID'] . '&nonce=' . $publish_nonce);

		$delete_nonce = wp_create_nonce('delete_generated_content_' . $item['ID']);
		$delete_link = admin_url('admin.php?page=news-repository&action=delete&post=' . $item['ID'] . '&nonce=' . $delete_nonce);

		$actions = [
			'edit'    => sprintf('<a href="%s">%s</a>', esc_url($edit_link), __('Edit', 'story-flow')),
			'publish' => sprintf('<a href="%s">%s</a>', esc_url($publish_link), __('Publish', 'story-flow')),
			'delete'  => sprintf('<a href="%s" class="delete">%s</a>', esc_url($delete_link), __('Delete', 'story-flow')),
		];

		return sprintf('<a href="%s"><strong>%s</strong></a> %s',  esc_url($edit_link), esc_html($item['post_title']), $this->row_actions($actions));
	}
}
