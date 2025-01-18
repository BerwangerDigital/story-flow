<?php

namespace StoryFlow\Admin\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WP_List_Table;

/**
 * Class Prompt_List_Table
 *
 * Handles the custom list table for managing AI-generated prompts.
 */
class Prompt_List_Table extends WP_List_Table {

    /**
     * Table data for the list table.
     *
     * @var array
     */
    private $table_data;

    /**
     * The database table name for prompts.
     *
     * @var string
     */
    private $table_name;

    /**
     * WordPress date format.
     *
     * @var string
     */
    private $wp_date_format;

    /**
     * WordPress time format.
     *
     * @var string
     */
    private $wp_time_format;

    /**
     * Constructor for the Prompt_List_Table class.
     *
     * Sets up the list table and initializes required properties.
     */
    public function __construct() {
        global $wpdb;

        // Table name
        $this->table_name = $wpdb->prefix . 'sf_prompts';

        $this->wp_date_format = get_option('date_format');
        $this->wp_time_format = get_option('time_format');

        parent::__construct([
            'singular' => 'prompt',  // Singular label
            'plural'   => 'prompts', // Plural label
            'ajax'     => false      // No support for AJAX
        ]);

        $this->handle_actions();
    }

    /**
     * Handle actions like delete.
     */
    private function handle_actions() {

		$action		= sf_retrieve($_GET, 'action', false, 'sanitize_text_field');
		$prompt_id	= sf_retrieve($_GET, 'prompt_id', false, 'intval');
		$nonce		= sf_retrieve($_REQUEST, '_wpnonce', false, 'sanitize_text_field');

        if (isset($action) && $action === 'delete' && isset($prompt_id)) {
            if (
                wp_verify_nonce(
                    $nonce,
                    'delete_prompt_' . $prompt_id
                )
            ) {
                $this->delete_prompt((int) $prompt_id);
            }
        }
    }

    /**
     * Delete a prompt by its ID.
     *
     * @param int $prompt_id The ID of the prompt to delete.
     */
    private function delete_prompt($prompt_id) {
        global $wpdb;

        if ($prompt_id > 0) {
            $wpdb->delete($this->table_name, ['id' => $prompt_id], ['%d']);
			wp_redirect(add_query_arg('deleted', '1', admin_url('admin.php?page=story-flow-prompts')));
            exit;
        }
    }

	public function display_notices() {
		$deleted		= sf_retrieve($_GET, 'deleted', 0, 'sanitize_text_field');

		if (isset($deleted) && $deleted === '1') {
			echo '<div class="notice notice-success is-dismissible">
				<p>' . esc_html__('Prompt deleted successfully.', 'story-flow') . '</p>
			</div>';
		}
	}

    /**
     * Define the columns to be displayed in the list table.
     *
     * @return array The array of column names.
     */
    public function get_columns() {
        return [
            'prompt'   => __('AI Prompt', 'story-flow'),
            'pillar'   => __('Pillar', 'story-flow'),
            'category' => __('Category', 'story-flow'),
            'topic'    => __('Topic', 'story-flow'),
        ];
    }

    /**
     * Define which columns are sortable in the list table.
     *
     * @return array The array of sortable columns.
     */
    public function get_sortable_columns() {
        return [
            'pillar'   => ['pillar', true],
            'category' => ['category', true],
            'topic'    => ['topic', true],
        ];
    }

    /**
     * Prepare the items for the table, including sorting, pagination, and filtering.
     */
    public function prepare_items() {
        $this->table_data = $this->get_table_data();

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $primary = 'id';

        $this->_column_headers = [$columns, $hidden, $sortable, $primary];

        $per_page = $this->get_items_per_page('elements_per_page', 10);
        $current_page = $this->get_pagenum();
        $total_items = count($this->table_data);

        // Pagination
        $this->table_data = array_slice($this->table_data, ($current_page - 1) * $per_page, $per_page);
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $this->items = $this->table_data;
    }

    /**
     * Retrieve the data for the list table, applying filters and searches.
     *
     * @return array The table data.
     */
    private function get_table_data() {
        global $wpdb;

        $filters = [
            'pillar'   => sf_retrieve($_POST, 'pillar-filter', '', 'sanitize_text_field'),
            'category' => sf_retrieve($_POST, 'cat-filter', '', 'sanitize_text_field'),
            'topic'    => sf_retrieve($_POST, 'topic-filter', '', 'sanitize_text_field'),
            'search'   => sf_retrieve($_POST, 's', '', 'sanitize_text_field'),
        ];

        $orderby = sf_retrieve($_GET, 'orderby', 'id', 'sanitize_text_field');
        $order = sf_retrieve($_GET, 'order', 'ASC', 'sanitize_text_field');

        $valid_orderby = ['pillar', 'category', 'topic', 'id'];
        $orderby = in_array($orderby, $valid_orderby, true) ? $orderby : 'id';
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $query = "SELECT * FROM {$this->table_name} WHERE 1=1";

        if (!empty($filters['pillar'])) {
            $query .= $wpdb->prepare(" AND pillar = %s", $filters['pillar']);
        }

        if (!empty($filters['category'])) {
            $query .= $wpdb->prepare(" AND category = %s", $filters['category']);
        }

        if (!empty($filters['topic'])) {
            $query .= $wpdb->prepare(" AND topic = %s", $filters['topic']);
        }

        if (!empty($filters['search'])) {
            $query .= $wpdb->prepare(" AND prompt LIKE %s", '%' . $wpdb->esc_like($filters['search']) . '%');
        }

        $query .= " ORDER BY $orderby $order";

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Add extra markup in the toolbars before or after the list.
     *
     * @param string $which The position of the toolbar (top or bottom).
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        global $wpdb;

        $categories = $wpdb->get_col("SELECT DISTINCT category FROM {$this->table_name} WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
        $topics = $wpdb->get_col("SELECT DISTINCT topic FROM {$this->table_name} WHERE topic IS NOT NULL AND topic != '' ORDER BY topic ASC");

        $selected_pillar = sf_retrieve($_POST, 'pillar-filter', '', 'sanitize_text_field');
        $selected_category = sf_retrieve($_POST, 'cat-filter', '', 'sanitize_text_field');
        $selected_topic = sf_retrieve($_POST, 'topic-filter', '', 'sanitize_text_field');

        echo '<div class="alignleft actions bulkactions">';

        // Pillar filter
        echo '<select name="pillar-filter">';
        echo '<option value="">' . esc_html__('Filter by Pillar', 'story-flow') . '</option>';
        $pillars = [
            'sport' => __('Esporte', 'story-flow'),
            'strategic-content' => __('Conteúdo Estratégico', 'story-flow'),
            'partner-content' => __('Parceiros', 'story-flow'),
            'proprietary-content' => __('Conteúdo Proprietário', 'story-flow'),
        ];
        foreach ($pillars as $key => $label) {
            $selected = selected($selected_pillar, $key, false);
            echo "<option value='" . esc_attr($key) . "' {$selected}>" . esc_html($label) . "</option>";
        }
        echo '</select>';

        // Category filter
        if (!empty($categories)) {
            echo '<select name="cat-filter">';
            echo '<option value="">' . esc_html__('Filter by Category', 'story-flow') . '</option>';
            foreach ($categories as $category) {
                $selected = selected($selected_category, $category, false);
                echo "<option value='" . esc_attr($category) . "' {$selected}>" . esc_html($category) . "</option>";
            }
            echo '</select>';
        }

        // Topic filter
        if (!empty($topics)) {
            echo '<select name="topic-filter">';
            echo '<option value="">' . esc_html__('Filter by Topic', 'story-flow') . '</option>';
            foreach ($topics as $topic) {
                $selected = selected($selected_topic, $topic, false);
                echo "<option value='" . esc_attr($topic) . "' {$selected}>" . esc_html($topic) . "</option>";
            }
            echo '</select>';
        }

        if (!empty($categories) || !empty($topics)) {
            echo '<button type="submit" class="button">' . esc_html__('Filter', 'story-flow') . '</button>';
        }

        echo '</div>';
    }

    /**
     * Render the default columns.
     *
     * @param array  $item The current item.
     * @param string $column_name The name of the column.
     * @return string The formatted column value.
     */
    public function column_default($item, $column_name) {
        if ($column_name === 'pillar') {
            return $this->format_pillar($item['pillar']);
        }

        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
    }

    /**
     * Render the "Prompt" column with actions.
     *
     * @param array $item The current item.
     * @return string The formatted column with actions.
     */
    public function column_prompt($item) {
        $output = '<strong>' . esc_html($item['prompt']) . '</strong>';
        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=story-flow-prompts&action=edit&prompt_id=' . $item['id'])),
                __('Edit', 'story-flow')
            ),
            'delete' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(
                    wp_nonce_url(
                        admin_url('admin.php?page=story-flow-prompts&action=delete&prompt_id=' . $item['id']),
                        'delete_prompt_' . $item['id']
                    )
                ),
                __('Delete', 'story-flow')
            ),
        ];

        return $output . $this->row_actions($actions);
    }

    /**
     * Format the pillar column with user-friendly labels.
     *
     * @param string $pillar The pillar value from the database.
     * @return string The formatted pillar label.
     */
    private function format_pillar($pillar) {
        $pillars = [
            'sport' => __('Esporte', 'story-flow'),
            'strategic-content' => __('Conteúdo Estratégico', 'story-flow'),
            'partner-content' => __('Parceiros', 'story-flow'),
            'proprietary-content' => __('Conteúdo Proprietário', 'story-flow'),
        ];

        return isset($pillars[$pillar]) ? $pillars[$pillar] : __('Unknown', 'story-flow');
    }
}
