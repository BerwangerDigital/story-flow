<?php

namespace StoryFlow\Admin\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class Prompt_Add_Page {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sf_prompts';
    }

    public function display() {
        // if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_prompt'])) {
        //     $this->handle_form_submission();
        // }

        $this->render_form();
    }

    private function handle_form_submission() {
        // Validar e Sanitizar Dados
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';

        // Validar Campos Obrigatórios
        if (empty($category) || empty($topic) || empty($prompt)) {
            add_settings_error('prompt_form', 'prompt_error', __('All fields are required.', 'story-flow'), 'error');
            return;
        }

        // Inserir no Banco de Dados
        global $wpdb;
        $result = $wpdb->insert(
            $this->table_name,
            [
                'category' => $category,
                'topic' => $topic,
                'prompt' => $prompt,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        if ($result) {
            add_settings_error('prompt_form', 'prompt_success', __('Prompt added successfully!', 'story-flow'), 'updated');
        } else {
            add_settings_error('prompt_form', 'prompt_error', __('An error occurred while saving the prompt.', 'story-flow'), 'error');
        }
    }

    private function render_form() {
        // Recuperar Categorias e Tópicos Existentes
        global $wpdb;
        $categories = $wpdb->get_col("SELECT DISTINCT category FROM {$this->table_name} WHERE category IS NOT NULL ORDER BY category ASC");
        $topics = $wpdb->get_col("SELECT DISTINCT topic FROM {$this->table_name} WHERE topic IS NOT NULL ORDER BY topic ASC");

        //settings_errors('prompt_form');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Add New Prompt', 'story-flow'); ?></h1>
            <form method="post">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="category"><?php esc_html_e('Category', 'story-flow'); ?></label>
                        </th>
                        <td>
                            <select name="category" id="category" required>
                                <option value=""><?php esc_html_e('Select a Category', 'story-flow'); ?></option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="topic"><?php esc_html_e('Topic', 'story-flow'); ?></label>
                        </th>
                        <td>
                            <select name="topic" id="topic" required>
                                <option value=""><?php esc_html_e('Select a Topic', 'story-flow'); ?></option>
                                <?php foreach ($topics as $topic) : ?>
                                    <option value="<?php echo esc_attr($topic); ?>"><?php echo esc_html($topic); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="prompt"><?php esc_html_e('Prompt', 'story-flow'); ?></label>
                        </th>
                        <td>
                            <textarea name="prompt" id="prompt" rows="5" class="large-text" required></textarea>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Add Prompt', 'story-flow'), 'primary', 'submit_prompt'); ?>
            </form>
        </div>
        <?php
    }
}
