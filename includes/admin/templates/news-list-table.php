<?php

namespace StoryFlow\Admin\Templates;

if (!defined('ABSPATH')) {
    exit;
}

use WP_List_Table;

class News_List_Table extends WP_List_Table {

    private $table_data;
    private $table_name;

    private const ALLOWED_ORDERBY_COLUMNS = ['post_title', 'post_date'];

    public function __construct() {
        global $wpdb;

        $this->table_name = $wpdb->prefix . 'posts';

        parent::__construct([
            'singular' => 'sf_generated_news',
            'plural'   => 'sf_generated_news_list',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'post_title' => __('Título', SF_TEXTDOMAIN),
            'pillar'     => __('Pilar', SF_TEXTDOMAIN),
            'post_date'  => __('Data', SF_TEXTDOMAIN),
        ];
    }

    public function get_sortable_columns() {
        return [
            'post_title' => ['post_title', true],
            'post_date'  => ['post_date', true],
        ];
    }

    /**
     * Render the filters for quick access to pillars above the table.
     */
    public function extra_tablenav($which) {
        if ($which === 'top') {
            $current_pillar = isset($_GET['pillar']) ? sanitize_text_field($_GET['pillar']) : '';
            $pillars = $this->get_fixed_pillar_values();

            echo '<div class="alignleft actions">';
            echo '<a href="' . esc_url(remove_query_arg('pillar')) . '" class="' . ($current_pillar === '' ? 'current' : '') . '">' . __('Todos conteúdos', SF_TEXTDOMAIN) . ' (' . $this->get_pillar_total() . ')</a>';

            foreach ($pillars as $slug => $label) {
                $url = add_query_arg('pillar', urlencode($slug));
                $class = ($current_pillar === $slug) ? 'current' : '';
                echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . ' (' . $this->get_pillar_total($slug) . ')</a>';
            }

            echo '</div>';
        }
    }

    private function get_table_data($per_page = 10, $current_page = 1, $orderby = 'post_date', $order = 'DESC') {
        global $wpdb;

        $orderby = in_array($orderby, self::ALLOWED_ORDERBY_COLUMNS) ? $orderby : 'post_date';
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $pillar_filter = isset($_GET['pillar']) ? sanitize_text_field($_GET['pillar']) : '';

        $query = "
            SELECT p.*, pm_pillar.meta_value AS pillar
            FROM {$this->table_name} p
            INNER JOIN {$wpdb->prefix}postmeta pm
                ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->prefix}postmeta pm_pillar
                ON p.ID = pm_pillar.post_id AND pm_pillar.meta_key = '_pillar'
            WHERE
                p.post_type = 'post'
                AND (p.post_status = 'draft' OR p.post_status = 'publish')
                AND pm.meta_key = '_generated_by_ai'
                AND pm.meta_value = 'true'
        ";

        if ($pillar_filter) {
            $query .= $wpdb->prepare(" AND pm_pillar.meta_value = %s", $pillar_filter);
        }

        $offset = ($current_page - 1) * $per_page;
        $query .= " ORDER BY {$orderby} {$order}";
        $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);

        return $wpdb->get_results($query, ARRAY_A);
    }

    private function get_total_items() {
        global $wpdb;

        $pillar_filter = isset($_GET['pillar']) ? sanitize_text_field($_GET['pillar']) : '';

        $query = "
            SELECT COUNT(*)
            FROM {$this->table_name} p
            INNER JOIN {$wpdb->prefix}postmeta pm
                ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->prefix}postmeta pm_pillar
                ON p.ID = pm_pillar.post_id AND pm_pillar.meta_key = '_pillar'
            WHERE
                p.post_type = 'post'
                AND (p.post_status = 'draft' OR p.post_status = 'publish')
                AND pm.meta_key = '_generated_by_ai'
                AND pm.meta_value = 'true'
        ";

        if ($pillar_filter) {
            $query .= $wpdb->prepare(" AND pm_pillar.meta_value = %s", $pillar_filter);
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Get total count of items for a specific pillar.
     *
     * @param string|null $pillar_slug The slug of the pillar to filter by.
     * @return int The total count of items.
     */
    private function get_pillar_total($pillar_slug = null) {
        global $wpdb;

        $query = "
            SELECT COUNT(*)
            FROM {$this->table_name} p
            INNER JOIN {$wpdb->prefix}postmeta pm
                ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->prefix}postmeta pm_pillar
                ON p.ID = pm_pillar.post_id AND pm_pillar.meta_key = '_pillar'
            WHERE
                p.post_type = 'post'
                AND (p.post_status = 'draft' OR p.post_status = 'publish')
                AND pm.meta_key = '_generated_by_ai'
                AND pm.meta_value = 'true'
        ";

        if ($pillar_slug) {
            $query .= $wpdb->prepare(" AND pm_pillar.meta_value = %s", $pillar_slug);
        }

        return (int) $wpdb->get_var($query);
    }

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
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $this->items = $this->table_data;
    }

    public function column_post_title($item) {
        $edit_link = get_edit_post_link($item['ID']);
        return sprintf('<a href="%s"><strong>%s</strong></a>', esc_url($edit_link), esc_html($item['post_title']));
    }

    public function column_pillar($item) {
        $pillars = $this->get_fixed_pillar_values();
        $pillar_slug = $item['pillar'] ?? '';
        return isset($pillars[$pillar_slug]) ? esc_html($pillars[$pillar_slug]) : __('Desconhecido', SF_TEXTDOMAIN);
    }

    public function column_post_date($item) {
        return esc_html(date('Y-m-d H:i:s', strtotime($item['post_date'])));
    }

    private function get_fixed_pillar_values() {
        return [
            'sport' => __('Conteúdo Esporte', SF_TEXTDOMAIN),
            'strategic-content' => __('Conteúdo Estratégico', SF_TEXTDOMAIN),
            'partner-content' => __('Conteúdo Parceiros', SF_TEXTDOMAIN),
            'proprietary-content' => __('Conteúdo Proprietário', SF_TEXTDOMAIN),
        ];
    }
}
