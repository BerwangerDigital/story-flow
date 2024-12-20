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
	function extra_tablenav($which)
    {
		$this->table_data = $this->get_table_data();

        if ($which === "top") {
            ?>
            <div class="alignleft actions bulkactions">
                    <?php

                    global $wpdb;

                    $table_name = $wpdb->prefix . $this->table_name;

                    // Obter categorias únicas
                    $cats = $wpdb->get_results("SELECT DISTINCT category FROM {$table_name} WHERE category IS NOT NULL ORDER BY category ASC", ARRAY_A);

                    if ($cats) {

						$selected_cat = sf_retrieve($_POST, 'cat-filter', false);

                        ?>
                        <select name="cat-filter">
                            <option value=""><?php echo esc_html__('Filter by Category', 'story-flow'); ?></option>
                            <?php
                            foreach ($cats as $cat) {
                                $selected = ($selected_cat !== false && $selected_cat === $cat['category']) ? 'selected="selected"' : '';
								printf('<option value="%s" %s>%s</option>', esc_attr($cat['category']), esc_attr($selected), esc_html($cat['category']));
                            }
                            ?>
                        </select>
                        <?php
                    }

                    // Obter tópicos únicos
                    $topics = $wpdb->get_results("SELECT DISTINCT topic FROM {$table_name} WHERE topic IS NOT NULL ORDER BY topic ASC", ARRAY_A);

                    if ($topics) {

						$selected_topic = sf_retrieve($_POST, 'topic-filter', false);

                        ?>
                        <select name="topic-filter">
                            <option value=""><?php echo esc_html__('Filter by Topic', 'story-flow'); ?></option>
                            <?php
                            foreach ($topics as $topic) {
								$selected = ($selected_topic !== false && $selected_topic === $topic['topic']) ? 'selected="selected"' : '';
								printf('<option value="%s" %s>%s</option>', esc_attr($topic['topic']), esc_attr($selected), esc_html($topic['topic']));
                            }
						?>
                        </select>
                        <?php
                    }

					if (($topics) || ($cats)) {
                    	echo '<button type="submit" class="button">' . esc_html__('Filter', 'story-flow') . '</button>';
					}
					?>
            </div>
            <?php
        }
    }

	/**
	 * Define the columns that are going to be used in the table
	 * @return array $columns, the array of columns to use with the table
	 */
	function get_columns() {
		return $columns = [
			// 'cb'				=> '<input type="checkbox" />',
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
	private function get_table_data() {
		global $wpdb;

		$table = $wpdb->prefix . 'sf_pitch_suggetion';

		// Obter o filtro de status, se fornecido
		$pitch_status = sf_retrieve($_GET, 'pitch_status', '');

		// Filters
        $category_filter	= sf_retrieve($_POST, 'cat-filter', '', 'sanitize_text_field');
        $topic_filter		= sf_retrieve($_POST, 'topic-filter', '', 'sanitize_text_field');
		$search				= sf_retrieve($_POST, 's', '', 'sanitize_text_field');

		if (empty($pitch_status)) {
			$pitch_status = sf_retrieve($_POST, 'pitch_status', '');
		}

		// Base da query
		$query = "SELECT * FROM {$table} WHERE 1=1";

		// Adicionar filtro, se necessário
		if (!empty($pitch_status) && $pitch_status !== 'all') {
			$query .= $wpdb->prepare(" AND status = %s", $pitch_status);
		}

        // Adicionar filtro de categoria
        if (!empty($category_filter)) {
            $query .= $wpdb->prepare(" AND category = %s", $category_filter);
        }

		// Adicionar filtro de tópico
		if (!empty($topic_filter)) {
			$query .= $wpdb->prepare(" AND topic = %s", $topic_filter);
		}

		// Adicionar busca no campo suggested_pitch
		if (!empty($search)) {
			$query .= $wpdb->prepare(" AND suggested_pitch LIKE %s", '%' . $wpdb->esc_like($search) . '%');
		}

		// Retornar os resultados da tabela
		return $wpdb->get_results($query, ARRAY_A);
	}

	// Sorting function
	function usort_reorder($a, $b)
	{
		// If no sort, default to user_login
		$orderby = sf_retrieve($_GET, 'orderby', 'created_at');

		// If no order, default to asc
		$order = sf_retrieve($_GET, 'order', 'asc');

		// Determine sort order
		$result = strcmp($a[$orderby], $b[$orderby]);

		// Send final sort direction to usort
		return ($order === 'asc') ? $result : -$result;
	}


	/**
	 * Prepare the table with different parameters, pagination, columns and table elements
	 */
	function prepare_items() {

		$this->table_data = $this->get_table_data();

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
		$primary  = 'id';
        $this->_column_headers = array($columns, $hidden, $sortable, $primary);

        usort($this->table_data, array(&$this, 'usort_reorder'));

		/* pagination */
		$per_page = $this->get_items_per_page('elements_per_page', 10);;
		$current_page = $this->get_pagenum();
		$total_items = count($this->table_data);

		$this->table_data = array_slice($this->table_data, (($current_page - 1) * $per_page), $per_page);

		$this->set_pagination_args(array(
				'total_items' => $total_items, // total number of items
				'per_page'    => $per_page, // items to show on a page
				'total_pages' => ceil( $total_items / $per_page ) // use ceil to round up
		));

        $this->items = $this->table_data;

 	}

	protected function get_views() {
		global $wpdb;

		// Nome da tabela
		$table_name = $wpdb->prefix . 'sf_pitch_suggetion';

		// Contar os registros por status
		$status_counts = $wpdb->get_results("
			SELECT status, COUNT(*) as count
			FROM {$table_name}
			GROUP BY status
		", OBJECT_K);

		// Garantir que os valores inexistentes retornem 0
		$status_totals = [
			'all'        => array_sum(wp_list_pluck($status_counts, 'count')),
			'pending'    => isset($status_counts['pending']) ? $status_counts['pending']->count : 0,
			'assign'     => isset($status_counts['assign']) ? $status_counts['assign']->count : 0,
			'refused'    => isset($status_counts['refused']) ? $status_counts['refused']->count : 0,
			'generated'  => isset($status_counts['generated']) ? $status_counts['generated']->count : 0,
		];

		$current_page = 'story-flow-pitchs';
		$base_url = admin_url('admin.php');

		// Obter o pitch_status da URL
		$current_status = isset($_GET['pitch_status']) ? sanitize_text_field($_GET['pitch_status']) : 'all';

		// Gerar os links de status
		$status_links = [
			"all"       => $current_status === 'all'
				? sprintf('%s (%d)', __('All', 'story-flow'), $status_totals['all'])
				: sprintf(
					'<a href="%s">%s (%d)</a>',
					esc_url(add_query_arg(['page' => $current_page, 'pitch_status' => 'all'], $base_url)),
					__('All', 'story-flow'),
					$status_totals['all']
				),
			"pending"   => $current_status === 'pending'
				? sprintf('%s (%d)', __('Pending', 'story-flow'), $status_totals['pending'])
				: sprintf(
					'<a href="%s">%s (%d)</a>',
					esc_url(add_query_arg(['page' => $current_page, 'pitch_status' => 'pending'], $base_url)),
					__('Pending', 'story-flow'),
					$status_totals['pending']
				),
			"assign"    => $current_status === 'assign'
				? sprintf('%s (%d)', __('Assign', 'story-flow'), $status_totals['assign'])
				: sprintf(
					'<a href="%s">%s (%d)</a>',
					esc_url(add_query_arg(['page' => $current_page, 'pitch_status' => 'assign'], $base_url)),
					__('Assign', 'story-flow'),
					$status_totals['assign']
				),
			"refused"   => $current_status === 'refused'
				? sprintf('%s (%d)', __('Refused', 'story-flow'), $status_totals['refused'])
				: sprintf(
					'<a href="%s">%s (%d)</a>',
					esc_url(add_query_arg(['page' => $current_page, 'pitch_status' => 'refused'], $base_url)),
					__('Refused', 'story-flow'),
					$status_totals['refused']
				),
			"generated" => $current_status === 'generated'
				? sprintf('%s (%d)', __('News generated', 'story-flow'), $status_totals['generated'])
				: sprintf(
					'<a href="%s">%s (%d)</a>',
					esc_url(add_query_arg(['page' => $current_page, 'pitch_status' => 'generated'], $base_url)),
					__('News generated', 'story-flow'),
					$status_totals['generated']
				),
		];

		return $status_links;
	}

	function column_default($item, $column_name)
	{
		switch ($column_name) {
			case 'created_at':

				$timestamp = strtotime($item[$column_name]);

				$date = date_i18n($this->wp_date_format, $timestamp);
				$time = date_i18n($this->wp_time_format, $timestamp);

				return $date . ' ' . $time;
			case 'category':
			case 'topic':
			case 'main_seo_keyword':
			case 'status':
				return ucfirst($item[$column_name]);
			default:
				return $item[$column_name];
		}
	}

	public function column_suggested_pitch( $item ) {
        $edit_link	= admin_url( 'post.php?action=edit&amp;post=' .  $item['id']  );
        $view_link	= get_permalink( $item['id'] );
        $output		= '';
		$actions	= [];

		if (('generated' === $item['status']) || ('published' === $item['status'])|| ('drafted' === $item['status'])) {
        	$output .= esc_html(  $item['suggested_pitch']   );

			return $output;
		}

		$output .= '<strong><a href="' . esc_url( $edit_link ) . '" class="row-title">' . esc_html(  $item['suggested_pitch']   ) . '</a></strong>';

		// Get actions.
		$actions = array(
			'edit'   => '<a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit', 'story-flow' ) . '</a>',
			'assign'   => '<a href="' . esc_url( $view_link ) . '">' . esc_html__( 'Assign', 'story-flow' ) . '</a>',
			'refused'  => '<a href="' . esc_url( $view_link ) . '">' . esc_html__( 'Refused', 'story-flow' ) . '</a>',
		);

		$row_actions = array();

		foreach ( $actions as $action => $link ) {
			$row_actions[] = '<span class="' . esc_attr( $action ) . '">' . $link . '</span>';
		}

		$output .= '<div class="row-actions">' . implode( ' | ', $row_actions ) . '</div>';

		return $output;
    }
}
