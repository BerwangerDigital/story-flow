<?php

namespace StoryFlow\Admin\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class Prompt_Form_Page {

	/**
     * The database table name for promts.
     *
     * @var string
     */
    private $table_name;

	/**
     * The database table name for pitch suggestions.
     *
     * @var string
     */
	private $pitch_table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sf_prompts';
		$this->pitch_table_name = $wpdb->prefix . SF__TABLE_PITCH_SUGGESTIONS;
    }

    public function display() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_prompt'])) {
            $this->handle_form_submission();
        }

        $this->render_form();
    }

    private function handle_form_submission() {
		// Validar e Sanitizar Dados
		$category = sf_retrieve($_POST, 'category', false, 'sanitize_text_field');
		$topic = sf_retrieve($_POST, 'topic', false, 'sanitize_text_field');
		$pillar = sf_retrieve($_POST, 'pillar', false, 'sanitize_text_field');
		$prompt = sf_retrieve($_POST, 'prompt', false, 'sanitize_textarea_field');

        // Validar Campos Obrigatórios
        if (empty($pillar) || empty($prompt)) {
            add_settings_error('prompt_form', 'prompt_error', __('The fields Pillar and Prompt are required.', 'story-flow'), 'error');
            return;
        }

        // Inserir no Banco de Dados
        global $wpdb;
        $result = $wpdb->insert(
            $this->table_name,
            [
                'category' => $category,
                'topic' => $topic,
                'pillar' => $pillar,
                'prompt' => $prompt,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result) {
            wp_redirect(add_query_arg(['page' => 'prompt_list', 'message' => 'success'], admin_url('admin.php')));
            exit;
        } else {
            add_settings_error('prompt_form', 'prompt_error', __('An error occurred while saving the prompt.', 'story-flow'), 'error');
        }
    }

    private function render_form() {
        // Recuperar Categorias, Tópicos e Pilares
        global $wpdb;
        $categories = $wpdb->get_col("SELECT DISTINCT category FROM $this->pitch_table_name WHERE category IS NOT NULL ORDER BY category ASC");
        $topics = $wpdb->get_col("SELECT DISTINCT topic FROM $this->pitch_table_name WHERE topic IS NOT NULL ORDER BY topic ASC");
        $pillars = [
			'sport' => 'Esporte',
			'strategic-content' => 'Estratégico',
			'partner-content' => 'Parceiros',
			'proprietary-content' => 'Proprietário'
		];

        //settings_errors('prompt_form');
        ?>

            <form method="post">
                <table class="form-table" role="presentation">
					<tbody>
						<tr class="form-field">
							<th scope="row">
								<label for="pillar"><?php esc_html_e('Pillar', 'story-flow'); ?></label>
							</th>
							<td>
							<?php
								$html_options = sprintf(
									'<option value="">%s</option>',
									esc_html__('Select a Pillar', 'story-flow')
								);

								$pillar = '';

								foreach ($pillars as $key => $value) {
										$html_options .= sprintf(
											'<option value="%1$s" %3$s>%2$s</option>',
											esc_attr( $key ),
											esc_html( $value ),
											selected( $pillar, esc_attr( $key ), false )
										);
								}

								echo '<select name="pillar" id="pillar" required>' . $html_options . '</select>';

							?>
							</td>
						</tr>
						<tr class="form-field">
							<th scope="row">
								<label for="category"><?php esc_html_e('Category', 'story-flow'); ?></label>
							</th>
							<td>
								<select name="category" id="category">
									<option value=""><?php esc_html_e('Select a Category', 'story-flow'); ?></option>
									<?php foreach ($categories as $category) : ?>
										<option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr class="form-field">
							<th scope="row">
								<label for="topic"><?php esc_html_e('Topic', 'story-flow'); ?></label>
							</th>
							<td>
								<select name="topic" id="topic">
									<option value=""><?php esc_html_e('Select a Topic', 'story-flow'); ?></option>
									<?php foreach ($topics as $topic) : ?>
										<option value="<?php echo esc_attr($topic); ?>"><?php echo esc_html($topic); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr class="form-field">
							<th scope="row">
								<label for="prompt"><?php esc_html_e('Prompt', 'story-flow'); ?></label>
							</th>
							<td>
								<textarea name="prompt" id="prompt" rows="5" class="large-text" required></textarea>
								<p class="description">
									<?php esc_html_e('You can use tags like {pitch}, {topic}, {keywords}.', 'story-flow'); ?>
								</p>
							</td>
						</tr>
					</tbody>
                </table>
                <?php submit_button(__('Add Prompt', 'story-flow'), 'primary', 'submit_prompt'); ?>
            </form>

        <?php
    }
}
