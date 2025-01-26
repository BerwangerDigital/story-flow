<?php

namespace StoryFlow\Admin\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WP_List_Table;
use StoryFlow\Queue\Pitch_Queue_Manager;

/**
 * Class Pitch_List_Table
 *
 * Handles the custom list table for managing story pitch suggestions.
 */
class Pitch_List_Table extends WP_List_Table {

	/**
     * Table data for the list table.
     *
     * @var array
     */
	private $table_data;

	/**
     * The database table name for pitch suggestions.
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
     * Allowed columns for ordering the list table.
     *
     * @const array
     */
	private const ALLOWED_ORDERBY_COLUMNS = ['category', 'topic', 'main_seo_keyword', 'status', 'created_at'];

	/**
     * Valid statuses for pitch suggestions.
     *
     * @const array
     */
	private const VALID_STATUSES = ['pending', 'approved', 'refused', 'generated'];

    /**
     * Constructor for the Pitch_List_Table class.
     *
     * Sets up the list table, processes actions, and initializes required properties.
     */
    function __construct() {
		global $wpdb;

		// Table name
		$this->table_name = $wpdb->prefix . SF_TABLE_PITCH_SUGGESTIONS;

		$this->wp_date_format = get_option('date_format');
		$this->wp_time_format = get_option('time_format');

		parent::__construct( [
			'singular'	=> 'sf_list_story_suggestion', // Singular label
			'plural'	=> 'sf_list_story_suggestions', // Plural label, also this well be one of the table css class
			'ajax'		=> false // We won't support Ajax for this table
		] );

        // Process actions (delete, change status, etc.)
        $this->process_actions();
	}

    /**
     * Process actions for delete and status changes.
     */
    private function process_actions() {
        $action = sf_retrieve($_GET, 'action', '', 'sanitize_text_field');

        if ($action === 'delete') {
            $this->process_delete_action();

			echo '<div id="message" class="notice is-dismissible updated"><p>' . esc_html__('Assunto excluído com sucesso.', SF_TEXTDOMAIN) . '</p></div>';
        } elseif ($action === 'change_status') {
            $this->process_change_status();
        }
    }

    /**
     * Process the "Delete" action for a pitch suggestion.
     */
    private function process_delete_action() {
        if (empty($_GET['post']) || empty($_GET['nonce'])) {
            return;
        }

        $post_id = absint($_GET['post']);
        $nonce = $_GET['nonce'];

        // Verify nonce
        if (!wp_verify_nonce($nonce, 'delete_pitch_' . $post_id)) {
            wp_die(__('Nonce inválido. Ação não permitida.', SF_TEXTDOMAIN));
        }

        global $wpdb;

        // Delete the record
        $deleted = $wpdb->delete(
            $this->table_name,
            ['id' => $post_id],
            ['%d']
        );

        if (false === $deleted) {
            wp_die(__('Houve uma falha ao tentar apagar o registro.', SF_TEXTDOMAIN));
        }

        // Add a success message
        add_action('admin_notices', function () {
            echo '<div id="message" class="notice notice-success is-dismissible"><p>' . esc_html__('Assunto excluído com sucesso.', SF_TEXTDOMAIN) . '</p></div>';
        });
    }

    /**
     * Add extra markup in the toolbar before or after the list.
     *
     * @param string $which Determines the position of the toolbar (top or bottom).
     */
	function extra_tablenav($which) {
		$this->table_data = $this->get_table_data();

		if ($which === 'top') {
			echo '<div class="alignleft actions bulkactions">';
			$this->render_filters();
			echo '<button type="submit" class="button">' . esc_html__('Filtrar', SF_TEXTDOMAIN) . '</button>';
			echo '</div>';
		}
    }

    /**
     * Define the columns to be displayed in the list table.
     *
     * @return array The array of column names.
     */
	function get_columns() {
		return $columns = [
			'suggested_pitch'	=> __('Assunto', SF_TEXTDOMAIN),
			'category'			=> __('Categoria', SF_TEXTDOMAIN),
			'topic'				=> __('Tópico', SF_TEXTDOMAIN),
			'main_seo_keyword'	=> __('Palavra-chave SEO', SF_TEXTDOMAIN),
			'status'			=> __('Status', SF_TEXTDOMAIN),
			'created_at'		=> __('Data', SF_TEXTDOMAIN),
		];
	}

   /**
     * Define which columns are sortable in the list table.
     *
     * @return array The array of sortable columns.
     */
	public function get_sortable_columns() {
		return $sortable = [
			'category'			=> ['category', false],
			'main_seo_keyword'	=> ['main_seo_keyword',false],
			'topic'				=> ['topic',false],
			'status'			=> ['status',false]
		];
	}

    /**
     * Retrieve table data for the list table.
     *
     * @param int $per_page    Number of items per page.
     * @param int $current_page The current page number.
     * @param string $orderby  Column to order by.
     * @param string $order    Sorting order (ASC or DESC).
     * @return array The table data.
     */
	private function get_table_data($per_page = 10, $current_page = 1, $orderby = 'id', $order = 'DESC') {
		global $wpdb;

		$pitch_status = sf_retrieve($_GET, 'pitch_status', 'all', 'sanitize_text_field');
		$category_filter = sf_retrieve($_POST, 'cat-filter', '', 'sanitize_text_field');
		$topic_filter = sf_retrieve($_POST, 'topic-filter', '', 'sanitize_text_field');
		$search = sf_retrieve($_POST, 's', '', 'sanitize_text_field');

		// Validate orderby column
		$orderby = in_array($orderby, self::ALLOWED_ORDERBY_COLUMNS) ? $orderby : 'id';
		$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

		if (empty($pitch_status)) {
			$pitch_status = sf_retrieve($_POST, 'pitch_status', 'all', 'sanitize_text_field');
		}

		$query = "SELECT * FROM {$this->table_name} WHERE 1=1";

		$filters = [
			'category' => $category_filter,
			'topic' => $topic_filter,
			'suggested_pitch LIKE' => !empty($search) ? '%' . $wpdb->esc_like($search) . '%' : null,
		];

		if ($pitch_status !== 'all') {
			$filters['status'] = $pitch_status;
		}

		$query .= $this->build_filters($filters);

		$offset = ($current_page - 1) * $per_page;
		$query .= " ORDER BY {$orderby} {$order}";
		$query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);

		return $wpdb->get_results($query, ARRAY_A);
	}

	/**
	 * Prepare the table with different parameters, pagination, columns and table elements
	 */
	public function prepare_items() {
		$per_page = $this->get_items_per_page('elements_per_page', 10);
		$current_page = $this->get_pagenum();

		// Define valores padrão para orderby e order
		$orderby = sf_retrieve($_GET, 'orderby', 'created_at', 'sanitize_text_field');
		$order   = sf_retrieve($_GET, 'order', 'desc', 'sanitize_text_field');

		$this->table_data = $this->get_table_data($per_page, $current_page, $orderby, $order);

		$columns = $this->get_columns();
		$hidden = [];
		$sortable = $this->get_sortable_columns();
		$primary  = 'id';
		$this->_column_headers = [$columns, $hidden, $sortable, $primary];

		$total_items = $this->get_total_items();

		$this->set_pagination_args([
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil($total_items / $per_page),
		]);

		$this->items = $this->table_data;
	}

    /**
     * Count the total number of items in the database for pagination.
     *
     * @return int Total number of items.
     */
	private function get_total_items() {
		global $wpdb;

		$pitch_status = sf_retrieve($_GET, 'pitch_status', 'all', 'sanitize_text_field');

		$filters = [
			'category'             => sf_retrieve($_POST, 'cat-filter', '', 'sanitize_text_field'),
			'topic'                => sf_retrieve($_POST, 'topic-filter', '', 'sanitize_text_field'),
			'suggested_pitch LIKE' => !empty($search) ? '%' . $wpdb->esc_like($search) . '%' : null,
		];

		if ($pitch_status !== 'all') {
			$filters['status'] = $pitch_status;
		}

		$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1";
		$query .= $this->build_filters($filters);

		return (int) $wpdb->get_var($query);
	}

	/**
     * Render the views (filters by status) at the top of the list table.
     *
     * @return array The views for the table.
     */
	protected function get_views() {
		global $wpdb;

		$status_counts = $wpdb->get_results("
        	SELECT status, COUNT(*) as count
        	FROM {$this->table_name}
        	GROUP BY status
    	", OBJECT_K);

		$status_totals = [
			'all'        => array_sum(wp_list_pluck($status_counts, 'count')),
			'pending'    => $status_counts['pending']->count ?? 0,
			'approved'     => $status_counts['approved']->count ?? 0,
			'refused'    => $status_counts['refused']->count ?? 0,
			'generated'  => $status_counts['generated']->count ?? 0,
		];

		$current_status = sf_retrieve($_GET, 'pitch_status', 'all', 'sanitize_text_field');

		$views = [];

		echo '<ul class="subsubsub">';

		foreach ($status_totals as $status => $count) {
			$views[$status] = $this->build_status_view($status, $count, $current_status);
		}

		echo '</ul>';

		return $views;
	}

	/**
     * Default column output if a specific column handler is not defined.
     *
     * @param array  $item        The current item.
     * @param string $column_name The name of the column.
     * @return string The formatted output for the column.
     */
	function column_default($item, $column_name) {
		if (in_array($column_name, ['category', 'topic', 'main_seo_keyword'], true)) {
			return ucfirst($item[$column_name] ?? '');
		}

		return $item[$column_name];
	}

	/**
     * Format the "Status" column.
     *
     * @param array $item The current item.
     * @return string The formatted status.
     */
	function column_status($item) {

		$label_status = [
			'pending' => __('Pendente', SF_TEXTDOMAIN),
			'processing' => __('Em Processamento', SF_TEXTDOMAIN),
			'approved' => __('Aprovado', SF_TEXTDOMAIN),
			'refused' => __('Recusado', SF_TEXTDOMAIN),
			'generated' => __('Finalizado', SF_TEXTDOMAIN),
		];

		return $label_status[$item['status']];
	}

	/**
     * Render the "Suggested Pitch" column with actions.
     *
     * @param array $item The current item.
     * @return string The formatted column with actions.
     */
	public function column_suggested_pitch($item) {
		$output = '';
		$actions = [];

		// Check if the pitch is editable based on its status.
		if (('pending' === $item['status']) || ('refused' === $item['status'])) {
			// Create the URL for the pitch form.
			$edit_link = admin_url('admin.php?page=story-flow-pitchs&action=update-form&pitch_id=' . $item['id']);
			$output .= '<strong><a href="' . esc_url($edit_link) . '" class="row-title">' . esc_html($item['suggested_pitch']) . '</a></strong>';
		} else {
			$output .= '<strong>' . esc_html($item['suggested_pitch']) . '</strong>';
		}

		// Define actions based on status.
		$nonce = wp_create_nonce('change_pitch_status_' . $item['id']);
		$change_status_link = admin_url('admin.php?page=story-flow-pitchs&action=change_status&post=' . $item['id'] . '&status=');

		switch ($item['status']) {
			case 'pending':
				$actions = [
					'approved' => sprintf(
						'<a href="%s&nonce=%s">%s</a>',
						esc_url($change_status_link . 'approved'),
						esc_attr($nonce),
						esc_html__('Aprovar', SF_TEXTDOMAIN)
					),
					// 'generated' => sprintf(
					// 	'<a href="%s&nonce=%s">%s</a>',
					// 	esc_url($change_status_link . 'generated'),
					// 	esc_attr($nonce),
					// 	esc_html__('Generate Now', SF_TEXTDOMAIN)
					// ),
					'refused' => sprintf(
						'<a href="%s&nonce=%s">%s</a>',
						esc_url($change_status_link . 'refused'),
						esc_attr($nonce),
						esc_html__('Recusar', SF_TEXTDOMAIN)
					),
				];
				break;
			case 'approved':
				$actions = [
					// 'generated' => sprintf(
					// 	'<a href="%s&nonce=%s">%s</a>',
					// 	esc_url($change_status_link . 'generated'),
					// 	esc_attr($nonce),
					// 	esc_html__('Gerar Agora', SF_TEXTDOMAIN)
					// ),
					'refused' => sprintf(
						'<a href="%s&nonce=%s">%s</a>',
						esc_url($change_status_link . 'refused'),
						esc_attr($nonce),
						esc_html__('Recusar', SF_TEXTDOMAIN)
					),
				];
				break;
			case 'refused':
				$actions = [
					'pending' => sprintf(
						'<a href="%s&nonce=%s">%s</a>',
						esc_url($change_status_link . 'pending'),
						esc_attr($nonce),
						esc_html__('Recuperar', SF_TEXTDOMAIN)
					),
				];
				break;
			case 'generated':
				$actions = [];
				break;
		}

		// Add "Delete" action for "Pending" or "Refused" statuses.
		if (in_array($item['status'], ['pending', 'refused'], true)) {
			$delete_nonce = wp_create_nonce('delete_pitch_' . $item['id']);
			$delete_link = admin_url('admin.php?page=story-flow-pitchs&action=delete&post=' . $item['id'] . '&nonce=' . $delete_nonce);
			$actions['delete'] = sprintf(
				'<a href="%s" class="delete">%s</a>',
				esc_url($delete_link),
				esc_html__('Apagar', SF_TEXTDOMAIN)
			);
		}

		if (!empty($actions)) {
			$row_actions = [];
			foreach ($actions as $action => $link) {
				$row_actions[] = sprintf('<span class="%s">%s</span>', esc_attr($action), $link);
			}
			$output .= '<div class="row-actions">' . implode(' | ', $row_actions) . '</div>';
		}

		return $output;
	}

	/**
     * Format the "Created At" column with formatted timestamps.
     *
     * @param array $item The current item.
     * @return string The formatted date and time.
     */
	function column_created_at($item) {
		$output = '';

		if (!empty($item['updated_at']) && ($item['updated_at'] != $item['created_at'])) {
			$timestamp = strtotime($item['updated_at']);
			$output .= esc_html__('Atualizado em', SF_TEXTDOMAIN) . '<br>';
		} else {
			$timestamp = strtotime($item['updated_at']);
			$output .= esc_html__('Criado em', SF_TEXTDOMAIN) . '<br>';
		}

		$date = date_i18n($this->wp_date_format, $timestamp);
		$time = date_i18n($this->wp_time_format, $timestamp);

		$output .= sprintf('%s %s', $date, $time);

		return $output;
	}

	/**
     * Render the filters above the table for filtering data.
     */
	private function render_filters() {
		// Retrieve current filter values.
		$search = sf_retrieve($_POST, 's', '', 'sanitize_text_field');
		$selected_category = sf_retrieve($_POST, 'cat-filter', '', 'sanitize_text_field');
		$selected_topic = sf_retrieve($_POST, 'topic-filter', '', 'sanitize_text_field');

		// Define filter configurations.
		$filter_configurations = [
			[
				'name' => 'category',
				'key' => 'cat-filter',
				'label' => __('Todos por Categoria', SF_TEXTDOMAIN),
				'options' => $this->get_unique_values('category'),
				'selected' => $selected_category,
			],
			[
				'name' => 'topic',
				'key' => 'topic-filter',
				'label' => __('Todos por Tópico', SF_TEXTDOMAIN),
				'options' => $this->get_unique_values('topic'),
				'selected' => $selected_topic,
			],
		];

		// Render each filter dropdown.
		foreach ($filter_configurations as $config) {
			$this->render_filter_dropdown($config['key'], $config['label'], $config['options'], $config['selected']);
		}
	}

	/**
	 * Render a dropdown filter with given options.
	 *
	 * @param string $name   The name of the filter input field.
	 * @param string $label  The label for the dropdown.
	 * @param array  $options The list of options for the dropdown.
	 * @param string $selected The selected value.
	 */
	private function render_filter_dropdown($name, $label, $options, $selected) {
		if ($options) {
			echo '<select name="' . esc_attr($name) . '">';
			echo '<option value="">' . esc_html($label) . '</option>';
			foreach ($options as $value => $label) {
				$is_selected = $selected === $value ? 'selected="selected"' : '';
				printf('<option value="%s" %s>%s</option>', esc_attr($value), esc_attr($is_selected), esc_html($label));
			}
			echo '</select>';
		}
	}

	/**
     * Generate a link for views with counts and labels.
     *
     * @param string $status        The status of the items (e.g., pending, approved).
     * @param string $label         The label for the status.
     * @param string $current_status The currently selected status.
     * @param int    $count         The count of items for this status.
     *
     * @return string The generated HTML link for the status view.
     */
	private function get_view_link($status, $label, $current_status, $count) {
		$url = add_query_arg(['page' => 'story-flow-pitchs', 'pitch_status' => $status], admin_url('admin.php'));
		if ($current_status === $status) {
			return sprintf('%s (%d)', esc_html($label), $count);
		}

		return sprintf('<a href="%s">%s (%d)</a>', esc_url($url), esc_html($label), $count);
	}

	/**
     * Build a WHERE clause for SQL queries based on the filters provided.
     *
     * @param array $filters Associative array of filters with column names as keys.
     *
     * @return string The SQL WHERE clause.
     */
	private function build_filters($filters) {
		global $wpdb;

		$where_clauses = [];
		foreach ($filters as $column => $value) {
			if ($value !== null && $value !== '') {
				if (stripos($column, 'LIKE') !== false) {
					$column = str_replace(' LIKE', '', $column);
					$where_clauses[] = $wpdb->prepare("{$column} LIKE %s", $value);
				} else {
					$where_clauses[] = $wpdb->prepare("{$column} = %s", $value);
				}
			}
		}

		return !empty($where_clauses) ? ' AND ' . implode(' AND ', $where_clauses) : '';
	}

	/**
     * Retrieve unique values for a specific column based on filters.
     *
     * @param string $column  The column name to retrieve unique values for.
     * @param array  $filters The filters to apply to the query.
     *
     * @return array List of unique values for the column.
     */
	private function get_unique_values($column, $filters = []) {
		global $wpdb;

		$query = "SELECT DISTINCT {$column} FROM {$this->table_name} WHERE 1=1";
		$query .= $this->build_filters($filters);
		$query .= " ORDER BY {$column} ASC";

		return $wpdb->get_col($query);
	}

	/**
     * Build a view link for a specific status with a count.
     *
     * @param string $status         The status identifier.
     * @param int    $count          The count of items for this status.
     * @param string $current_status The currently selected status.
     *
     * @return string The HTML for the view link.
     */
	private function build_status_view($status, $count, $current_status) {

		$label_status = [
			'all' => __('Todos', SF_TEXTDOMAIN),
			'pending' => __('Pendentes', SF_TEXTDOMAIN),
			'approved' => __('Aprovados', SF_TEXTDOMAIN),
			'refused' => __('Recusados', SF_TEXTDOMAIN),
			'generated' => __('Finalizados', SF_TEXTDOMAIN),
		];

		$label = $label_status[$status];
		$url = add_query_arg(['page' => 'story-flow-pitchs', 'pitch_status' => $status], admin_url('admin.php'));

		return $current_status === $status
			? sprintf('<li><a href="%s" class="current">%s <span class="count">(%d)</span></a>', esc_url($url), esc_html($label), $count)
			: sprintf('<li><a href="%s">%s <span class="count">(%d)</span></a></li>', esc_url($url), esc_html($label), $count);
	}

	/**
	 * Processes the "Change Status" action for a record.
	 *
	 * This method verifies the request, validates the parameters,
	 * updates the status of the given record in the database, and,
	 * if applicable, queues the record for further processing.
	 *
	 * @return void
	 */
	private function process_change_status() {

		$action = sf_retrieve($_GET, 'action', '', 'sanitize_text_field');

		if ($action !== 'change_status') {
			return;
		}

		if (empty($_GET['post']) || empty($_GET['status']) || empty($_GET['nonce'])) {
			error_log('Parâmetros ausentes ao tentar mudar status.');
			return;
		}

		$post_id = absint($_GET['post']);
		$new_status = sanitize_text_field($_GET['status']);
		$nonce = $_GET['nonce'];

		if (!wp_verify_nonce($nonce, 'change_pitch_status_' . $post_id)) {
			wp_die(__('Nonce inválido. Ação não permitida.', SF_TEXTDOMAIN));
		}

		$valid_statuses = ['pending', 'approved', 'refused', 'processing', 'generated'];
		if (!in_array($new_status, $valid_statuses, true)) {
			wp_die(__('Status inválido.', SF_TEXTDOMAIN));
		}

		global $wpdb;

		$updated = $wpdb->update(
			$this->table_name,
			['status' => $new_status, 'updated_at' => current_time('mysql')],
			['id' => $post_id],
			['%s', '%s'],
			['%d']
		);

		if (false === $updated) {
			wp_die(__('Falhou ao atualizar o status.', SF_TEXTDOMAIN));
		}

		// If the new status is "generated", add the record to the processing queue
        $queue_manager = new Pitch_Queue_Manager();

        if ('approved' === $new_status) {
            $queue_manager->add_to_queue($post_id);
        } elseif ('generated' === $new_status) {
            $queue_manager->add_to_queue_with_priority($post_id);
        }

		// Display a success message in the admin interface
		$this->display_success_message();
	}

	private function display_success_message() {
		echo '<div id="message" class="notice is-dismissible updated"><p>' . esc_html__('Status atualizado com sucesso.', SF_TEXTDOMAIN) . '</p></div>';
	}
}
