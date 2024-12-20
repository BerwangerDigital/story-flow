<?php

namespace StoryFlow\Admin\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WP_List_Table;

class Prompt_List_Table extends WP_List_Table {

	// define $table_data property
	private $table_data;

	private $table_name = 'sf_prompts';

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
            'singular' => 'prompt',
            'plural'   => 'prompts',
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
            'prompt'    => __('Prompt', 'story-flow'),
			'category'  => __('Category', 'story-flow'),
            'topic'     => __('Topic', 'story-flow'),
		];
	}

	/**
	 * Decide which columns to activate the sorting functionality on
	 * @return array $sortable, the array of columns that can be sorted by the user
	 */
	public function get_sortable_columns() {
		return $sortable = [
			'category'			=> ['category', false],
			'topic'				=> ['topic',true],
		];
	}

	// Get table data
	private function get_table_data() {
		global $wpdb;

		$table = $wpdb->prefix . $this->table_name;


		// Filters
        $category_filter	= sf_retrieve($_POST, 'cat-filter', '', 'sanitize_text_field');
        $topic_filter		= sf_retrieve($_POST, 'topic-filter', '', 'sanitize_text_field');
		$search				= sf_retrieve($_POST, 's', '', 'sanitize_text_field');

		if (empty($pitch_status)) {
			$pitch_status = sf_retrieve($_POST, 'pitch_status', '');
		}

		// Base da query
		$query = "SELECT * FROM {$table} WHERE 1=1";

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
			$query .= $wpdb->prepare(" AND prompt LIKE %s", '%' . $wpdb->esc_like($search) . '%');
		}

		// Retornar os resultados da tabela
		return $wpdb->get_results($query, ARRAY_A);
	}

	// Sorting function
	function usort_reorder($a, $b)
	{
		// If no sort, default to user_login
		$orderby = sf_retrieve($_GET, 'orderby', 'id');

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

	function column_default($item, $column_name)
	{
		switch ($column_name) {
			case 'category':
			case 'topic':
			default:
				return $item[$column_name];
		}
	}

	public function column_prompt( $item ) {
        $edit_link	= admin_url( 'post.php?action=edit&amp;post=' .  $item['id']  );
        $view_link	= get_permalink( $item['id'] );
        $output		= '';
		$actions	= [];

		$output .= '<strong><a href="' . esc_url( $edit_link ) . '" class="row-title">' . esc_html(  $item['prompt']   ) . '</a></strong>';

		// Get actions.
		$actions = array(
			'edit'   => '<a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit', 'story-flow' ) . '</a>',
		);

		$row_actions = array();

		foreach ( $actions as $action => $link ) {
			$row_actions[] = '<span class="' . esc_attr( $action ) . '">' . $link . '</span>';
		}

		$output .= '<div class="row-actions">' . implode( ' | ', $row_actions ) . '</div>';

		return $output;
    }
}
