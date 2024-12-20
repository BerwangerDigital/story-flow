<?php

namespace StoryFlow\Admin\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WP_List_Table;

class Pitch_List_Table extends WP_List_Table {

	// define $table_data property
	private $table_data;

	private $table_name = 'sf_pitch_suggetion';

	private $wp_date_format;

	private $wp_time_format;

	private const ALLOWED_ORDERBY_COLUMNS = ['category', 'topic', 'main_seo_keyword', 'status', 'created_at'];

	/**
    * Constructor, we override the parent to pass our own arguments
    * We usually focus on three parameters: singular and plural labels, as well as whether the class supports AJAX.
    */
    function __construct() {
		$this->wp_date_format = get_option('date_format');
		$this->wp_time_format = get_option('time_format');

		parent::__construct( [
			'singular'	=> 'sf_list_story_suggestion', // Singular label
			'plural'	=> 'sf_list_story_suggestions', // Plural label, also this well be one of the table css class
			'ajax'		=> false // We won't support Ajax for this table
		] );
	}

	/**
	 * Add extra markup in the toolbars before or after the list
	 * @param string $which, helps you decide if you add the markup after (bottom) or before (top) the list
	 */
	function extra_tablenav($which) {
		$this->table_data = $this->get_table_data();

		if ($which === 'top') {
			echo '<div class="alignleft actions bulkactions">';
			$this->render_filters();
			echo '<button type="submit" class="button">' . esc_html__('Filter', 'story-flow') . '</button>';
			echo '</div>';
		}
    }

	/**
	 * Define the columns that are going to be used in the table
	 * @return array $columns, the array of columns to use with the table
	 */
	function get_columns() {
		return $columns = [
			'category'			=> 'Category',
			'topic'				=> 'Topic',
			'main_seo_keyword'	=> 'SEO Keyword',
			'suggested_pitch'	=> 'Pitch',
			'status'			=> 'Status',
			'created_at'		=> 'Date',
		];
	}

	/**
	 * Decide which columns to activate the sorting functionality on
	 * @return array $sortable, the array of columns that can be sorted by the user
	 */
	public function get_sortable_columns() {
		return $sortable = [
			'category'			=> ['category', false],
			'main_seo_keyword'	=> ['main_seo_keyword',false],
			'topic'				=> ['topic',true],
			'status'			=> ['status',false]
		];
	}

	// Get table data
	private function get_table_data($per_page = 10, $current_page = 1, $orderby = 'created_at', $order = 'ASC') {
		global $wpdb;

		$table = $wpdb->prefix . $this->table_name;

		// Filters
		$pitch_status       = sf_retrieve($_GET, 'pitch_status', 'all', 'sanitize_text_field');
        $category_filter	= sf_retrieve($_POST, 'cat-filter', '', 'sanitize_text_field');
        $topic_filter		= sf_retrieve($_POST, 'topic-filter', '', 'sanitize_text_field');
		$search				= sf_retrieve($_POST, 's', '', 'sanitize_text_field');

    	// Validar parâmetros de ordenação
		$orderby = in_array($orderby, self::ALLOWED_ORDERBY_COLUMNS) ? $orderby : 'created_at';
		$order   = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
    	$order   = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

		if (empty($pitch_status)) {
			$pitch_status = sf_retrieve($_POST, 'pitch_status', 'all', 'sanitize_text_field');
		}

		// Base da query
		$query = "SELECT * FROM {$table} WHERE 1=1";

		$filters = [
			'category'  => $category_filter,
			'topic'     => $topic_filter,
			'suggested_pitch LIKE' => !empty($search) ? '%' . $wpdb->esc_like($search) . '%' : null,
		];

		// Adicionar filtro de status apenas se necessário
		if ($pitch_status !== 'all') {
			$filters['status'] = $pitch_status;
		}

		$query .= $this->build_filters($filters);

		$offset = ($current_page - 1) * $per_page;
		$query .= " ORDER BY {$orderby} {$order}";
		$query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);

		// Retornar os resultados da tabela
		return $wpdb->get_results($query, ARRAY_A);
	}

	/**
	 * Prepare the table with different parameters, pagination, columns and table elements
	 */
	function prepare_items() {

		// Definir parâmetros de paginação
		$per_page = $this->get_items_per_page('elements_per_page', 10);
		$current_page = $this->get_pagenum();

		// Obter parâmetros de ordenação
		$orderby = sf_retrieve($_GET, 'orderby', 'created_at', 'sanitize_text_field');
		$order   = sf_retrieve($_GET, 'order', 'asc', 'sanitize_text_field');

		// Obter dados da tabela com paginação e ordenação
		$this->table_data = $this->get_table_data($per_page, $current_page, $orderby, $order);

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
		$primary  = 'id';
        $this->_column_headers = array($columns, $hidden, $sortable, $primary);

		// Contar o total de itens (sem paginação)
		$total_items = $this->get_total_items();

		$this->set_pagination_args(array(
				'total_items' => $total_items, // total number of items
				'per_page'    => $per_page, // items to show on a page
				'total_pages' => ceil( $total_items / $per_page ) // use ceil to round up
		));

        $this->items = $this->table_data;
 	}

	/**
	 * Contar o número total de itens na tabela para paginação.
	 *
	 * @return int Total de itens.
	 */
	private function get_total_items() {
		global $wpdb;

		$table = $wpdb->prefix . $this->table_name;

		$pitch_status = sf_retrieve($_GET, 'pitch_status', 'all', 'sanitize_text_field');

		$filters = [
			'category'             => sf_retrieve($_POST, 'cat-filter', '', 'sanitize_text_field'),
			'topic'                => sf_retrieve($_POST, 'topic-filter', '', 'sanitize_text_field'),
			'suggested_pitch LIKE' => !empty($search) ? '%' . $wpdb->esc_like($search) . '%' : null,
		];

		// Adicionar filtro de status apenas se necessário
		if ($pitch_status !== 'all') {
			$filters['status'] = $pitch_status;
		}

		$query = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
		$query .= $this->build_filters($filters);

		return (int) $wpdb->get_var($query);
	}

	protected function get_views() {
		global $wpdb;

		// Nome da tabela
		$table_name = $wpdb->prefix . $this->table_name;

		// Contar os registros por status
		$status_counts = $wpdb->get_results("
        	SELECT status, COUNT(*) as count
        	FROM {$table_name}
        	GROUP BY status
    	", OBJECT_K);

		// Garantir que os valores inexistentes retornem 0
		$status_totals = [
			'all'        => array_sum(wp_list_pluck($status_counts, 'count')),
			'pending'    => $status_counts['pending']->count ?? 0,
			'assign'     => $status_counts['assign']->count ?? 0,
			'refused'    => $status_counts['refused']->count ?? 0,
			'generated'  => $status_counts['generated']->count ?? 0,
		];

		$current_status = sf_retrieve($_GET, 'pitch_status', 'all', 'sanitize_text_field');

		$views = [];

		foreach ($status_totals as $status => $count) {
			$views[$status] = $this->build_status_view($status, $count, $current_status);
		}

		return $views;
	}

	function column_default($item, $column_name) {
		if (in_array($column_name, ['category', 'topic', 'main_seo_keyword'], true)) {
			return ucfirst($item[$column_name]);
		}

		return $item[$column_name];
	}

	function column_status($item) {
		return sprintf('<span style="font-size:24px;font-weight:600;">%s</span>', ucfirst($item['status']));
	}

	public function column_suggested_pitch($item) {
		$output = '';
		$actions = [];

		$change_status_link = admin_url('admin.php?page=story-flow-pitchs&action=change_status&post=' . $item['id'] . '&status=');

		// Adicionar o título do pitch
		if ('pending' === $item['status']) {
			// Exibir título com link de edição
			$edit_link = admin_url('post.php?action=edit&post=' . $item['id']);
			$output .= '<strong><a href="' . esc_url($edit_link) . '" class="row-title">' . esc_html($item['suggested_pitch']) . '</a></strong>';
		} else {
			// Exibir título como texto simples
			$output .= '<strong>' . esc_html($item['suggested_pitch']) . '</strong>';
		}

		// Determinar ações com base no status atual
		switch ($item['status']) {
			case 'pending':
				$actions = [
					'assign' => sprintf(
						'<a href="%s">%s</a>',
						esc_url($change_status_link . 'assign'),
						esc_html__('Assign', 'story-flow')
					),
					'refused' => sprintf(
						'<a href="%s">%s</a>',
						esc_url($change_status_link . 'refused'),
						esc_html__('Refuse', 'story-flow')
					),
				];
				break;

			case 'assign':
				$actions = [
					'generated' => sprintf(
						'<a href="%s">%s</a>',
						esc_url($change_status_link . 'generated'),
						esc_html__('Generate', 'story-flow')
					),
					'refused' => sprintf(
						'<a href="%s">%s</a>',
						esc_url($change_status_link . 'refused'),
						esc_html__('Refuse', 'story-flow')
					),
				];
				break;

			case 'refused':
				$actions = [
					'pending' => sprintf(
						'<a href="%s">%s</a>',
						esc_url($change_status_link . 'pending'),
						esc_html__('Reopen', 'story-flow')
					),
				];
				break;

			case 'generated':
				// Nenhuma ação disponível para status "generated"
				$actions = [];
				break;
		}

		// Renderizar as ações
		if (!empty($actions)) {
			$row_actions = [];
			foreach ($actions as $action => $link) {
				$row_actions[] = sprintf('<span class="%s">%s</span>', esc_attr($action), $link);
			}
			$output .= '<div class="row-actions">' . implode(' | ', $row_actions) . '</div>';
		}

		return $output;
	}

	function column_created_at($item) {
		$output = '';

		if (!empty($item['updated_at']) && ($item['updated_at'] != $item['created_at'])) {
			$timestamp = strtotime($item['updated_at']);
			$output .= esc_html__('Updated at', 'story-flow') . '<br>';
		} else {
			$timestamp = strtotime($item['created_at']);
			$output .= esc_html__('Created at', 'story-flow') . '<br>';
		}

		$date = date_i18n($this->wp_date_format, $timestamp);
		$time = date_i18n($this->wp_time_format, $timestamp);

		$output .= sprintf('%s %s', $date, $time);

		return $output;
	}

	private function render_filters() {
		// Obter os filtros aplicados atualmente
		$pitch_status = sf_retrieve($_GET, 'pitch_status', 'all', 'sanitize_text_field');
		$search = sf_retrieve($_POST, 's', '', 'sanitize_text_field');
		$selected_category = sf_retrieve($_POST, 'cat-filter', '', 'sanitize_text_field');
		$selected_topic = sf_retrieve($_POST, 'topic-filter', '', 'sanitize_text_field');

		$applied_filters = [
			'status'               => $pitch_status !== 'all' ? $pitch_status : null,
			'suggested_pitch LIKE' => !empty($search) ? '%' . esc_sql($search) . '%' : null,
			'category'             => $selected_category, // Aplicar o filtro de categoria no tópico
			'topic'                => $selected_topic, // Aplicar o filtro de tópico na categoria
		];

		// Filtrar categorias com base nos resultados atuais
		$categories = $this->get_unique_values('category', $applied_filters);
		$this->render_filter_dropdown('cat-filter', __('All by Category', 'story-flow'), $categories, $selected_category);

		// Filtrar tópicos com base nos resultados atuais
		$topics = $this->get_unique_values('topic', $applied_filters);
		$this->render_filter_dropdown('topic-filter', __('All by Topic', 'story-flow'), $topics, $selected_topic);
	}

	private function render_filter_dropdown($name, $label, $options, $selected) {
		if ($options) {
			echo '<select name="' . esc_attr($name) . '">';
			echo '<option value="">' . esc_html($label) . '</option>';
			foreach ($options as $option) {
				$is_selected = $selected === $option ? 'selected="selected"' : '';
				printf('<option value="%s" %s>%s</option>', esc_attr($option), esc_attr($is_selected), esc_html($option));
			}
			echo '</select>';
		}
	}

	private function get_view_link($status, $label, $current_status, $count) {
		$url = add_query_arg(['page' => 'story-flow-pitchs', 'pitch_status' => $status], admin_url('admin.php'));
		if ($current_status === $status) {
			return sprintf('%s (%d)', esc_html($label), $count);
		}

		return sprintf('<a href="%s">%s (%d)</a>', esc_url($url), esc_html($label), $count);
	}

	private function build_filters($filters) {
		global $wpdb;

		$where_clauses = [];
		foreach ($filters as $column => $value) {
			if ($value !== null && $value !== '') {
				if (stripos($column, 'LIKE') !== false) {
					// Remove " LIKE" do final da coluna para preparar o nome
					$column = str_replace(' LIKE', '', $column);
					$where_clauses[] = $wpdb->prepare("{$column} LIKE %s", $value);
				} else {
					$where_clauses[] = $wpdb->prepare("{$column} = %s", $value);
				}
			}
		}

		return !empty($where_clauses) ? ' AND ' . implode(' AND ', $where_clauses) : '';
	}

	private function get_unique_values($column, $filters = []) {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->table_name;

		// Construir a query para extrair valores únicos com base nos filtros aplicados
		$query = "SELECT DISTINCT {$column} FROM {$table_name} WHERE 1=1";

		// Adicionar os filtros aplicados
		$query .= $this->build_filters($filters);

		$query .= " ORDER BY {$column} ASC";

		return $wpdb->get_col($query);
	}

	private function build_status_view($status, $count, $current_status) {
		$label = ucfirst($status);
		$url = add_query_arg(['page' => 'story-flow-pitchs', 'pitch_status' => $status], admin_url('admin.php'));

		return $current_status === $status
			? sprintf('%s (%d)', esc_html($label), $count)
			: sprintf('<a href="%s">%s (%d)</a>', esc_url($url), esc_html($label), $count);
	}
}
