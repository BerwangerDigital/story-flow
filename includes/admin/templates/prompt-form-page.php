<?php

namespace StoryFlow\Admin\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class Prompt_Form_Page {

    private $table_name;
    private $pitch_table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sf_prompts';
        $this->pitch_table_name = $wpdb->prefix . SF_TABLE_PITCH_SUGGESTIONS;
    }

    public function display() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $prompt_id = isset($_GET['prompt_id']) ? intval($_GET['prompt_id']) : 0;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_prompt'])) {
            $this->handle_form_submission();
        }

        if ($action === 'update-form' && $prompt_id > 0) {
            $this->render_form($prompt_id);
        } else {
            $this->render_form();
        }
    }

    private function handle_form_submission() {
        global $wpdb;

		$errors = [];

        $id = sf_retrieve($_POST, 'id', false, 'intval');
        $category = sf_retrieve($_POST, 'category', false, 'sanitize_text_field');
        $topic = sf_retrieve($_POST, 'topic', false, 'sanitize_text_field');
        $pillar = sf_retrieve($_POST, 'pillar', false, 'sanitize_text_field');
        $prompt = sf_retrieve($_POST, 'prompt', false, 'sanitize_textarea_field');

        if (empty($pillar) || empty($prompt)) {
            add_settings_error('prompt_form', 'prompt_error', __('Os campos Pilar e Prompt são de preenchimento obrigatório.', SF_TEXTDOMAIN), 'error');
            return;
        }

        if ($id) {
            $result = $wpdb->update(
                $this->table_name,
                [
                    'category' => $category,
                    'topic' => $topic,
                    'pillar' => $pillar,
                    'prompt' => $prompt,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $id],
                ['%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            if ($result === false) {
				$errors[] = "Um erro ocorreu durante a atualização.";
            }

			$message = "Nova Regra de Prompt atualizada com sucesso.";
        } else {

			$values = [
				'pillar' => $pillar,
				'prompt' => $prompt,
				'created_at' => current_time('mysql'),
				'updated_at' => current_time('mysql'),
			];

			if (!empty($category)) {
				$values['category'] = $category;
			}

			if (!empty($topic)) {
				$values['topic'] = $topic;
			}

            $result = $wpdb->insert(
                $this->table_name,
				$values,
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );

            if (!$result) {
				$errors[] = "Um erro ocorreu durante a gravação dos dados no banco de dados.";
            }

			$message = "Nova Regra de Prompt adicionada com sucesso.";
        }

		$_SESSION['prompt_results'] = [
			'status' => empty($errors) ? 'success' : 'error',
			'message' => $message,
			'errors' => $errors,
		];
    }

    private function render_form($prompt_id = 0) {
        global $wpdb;

		$prompt_results = sf_retrieve($_SESSION, 'prompt_results', false);
		unset($_SESSION['prompt_results']);

        $pillars = [
            'sport' => 'Conteúdo Esporte',
            'strategic-content' => 'Conteúdo Estratégico',
            'partner-content' => 'Conteúdo Parceiros',
            'proprietary-content' => 'Conteúdo Proprietário'
        ];

        $existing_prompt = $prompt_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $prompt_id), ARRAY_A) : null;

        $pillar = $existing_prompt['pillar'] ?? '';
        $category = $existing_prompt['category'] ?? '';
        $topic = $existing_prompt['topic'] ?? '';
        $prompt = $existing_prompt['prompt'] ?? '';

		if ($prompt_results): ?>
			<div class="<?php echo esc_attr($prompt_results['status'] === 'success' ? 'updated' : 'error'); ?>">
				<p><?php echo esc_html($prompt_results['message']); ?></p>
			</div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="id" value="<?php echo esc_attr($prompt_id); ?>">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr class="form-field">
                        <th scope="row">
                            <label for="pillar"><?php esc_html_e('Pilar', SF_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <select name="pillar" id="pillar" required>
                                <option value=""><?php esc_html_e('Selecione o Pilar', SF_TEXTDOMAIN); ?></option>
                                <?php foreach ($pillars as $key => $value) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($pillar, $key); ?>><?php echo esc_html($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr class="form-field">
                        <th scope="row">
                            <label for="category"><?php esc_html_e('Categoria', SF_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" name="category" id="category" value="<?php echo esc_attr($category); ?>" placeholder="<?php esc_attr_e('Optional', SF_TEXTDOMAIN); ?>">
                        </td>
                    </tr>
                    <tr class="form-field">
                        <th scope="row">
                            <label for="topic"><?php esc_html_e('Tópico', SF_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" name="topic" id="topic" value="<?php echo esc_attr($topic); ?>" placeholder="<?php esc_attr_e('Optional', SF_TEXTDOMAIN); ?>">
                        </td>
                    </tr>
                    <tr class="form-field">
                        <th scope="row">
                            <label for="prompt"><?php esc_html_e('Prompt', SF_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <textarea name="prompt" id="prompt" rows="5" class="large-text" required><?php echo esc_textarea($prompt); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Você pode usar tags dinâmicas como {pitch} para adicionar o assunto, {topic} para o tópico e {keywords} para Palavra-chave SEO.', SF_TEXTDOMAIN); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php
            if ($prompt_id > 0) {
                submit_button(__('Atualizar', SF_TEXTDOMAIN), 'primary', 'submit_prompt');
            } else {
                submit_button(__('Adicionar', SF_TEXTDOMAIN), 'primary', 'submit_prompt');
            }
            ?>
        </form>

        <?php
    }
}
