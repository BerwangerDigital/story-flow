<?php

namespace StoryFlow\Admin\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class Pitch_Form_Page {

    /**
     * The database table name for pitch suggestions.
     *
     * @var string
     */
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . SF_TABLE_PITCH_SUGGESTIONS;
    }

    public function display() {
        $pitch_id = isset($_GET['pitch_id']) ? intval($_GET['pitch_id']) : null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pitch'])) {
            $this->handle_form_submission($pitch_id);
        }

        $this->render_form($pitch_id);
    }

    private function handle_form_submission($pitch_id) {
		$errors = [];

        // Validar e Sanitizar Dados
        $category = sf_retrieve($_POST, 'category', false, 'sanitize_text_field');
        $topic = sf_retrieve($_POST, 'topic', false, 'sanitize_text_field');
        $main_seo_keyword = sf_retrieve($_POST, 'main_seo_keyword', false, 'sanitize_text_field');
        $suggested_pitch = sf_retrieve($_POST, 'suggested_pitch', false, 'sanitize_textarea_field');

        // Validar Campos Obrigatórios
        if (empty($category) || empty($topic) || empty($main_seo_keyword) || empty($suggested_pitch)) {
            add_settings_error('pitch_form', 'pitch_error', __('Todos os campos são obrigatórios.', SF_TEXTDOMAIN), 'error');
            return;
        }

        global $wpdb;

        if ($pitch_id) {
            // Atualizar pitch existente
            $current_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$this->table_name} WHERE id = %d", $pitch_id));

            if ($current_status !== 'pending') {
                add_settings_error('pitch_form', 'pitch_error', __('Somente assuntos com status "Pendente" podem ser editados.', SF_TEXTDOMAIN), 'error');
                return;
            }

            $result = $wpdb->update(
                $this->table_name,
                [
                    'category' => $category,
                    'topic' => $topic,
                    'main_seo_keyword' => $main_seo_keyword,
                    'suggested_pitch' => $suggested_pitch,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $pitch_id],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );

			$message = "Assunto atualizado com sucesso.";
        } else {
            // Inserir novo pitch
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'category' => $category,
                    'topic' => $topic,
                    'main_seo_keyword' => $main_seo_keyword,
                    'suggested_pitch' => $suggested_pitch,
                    'origin' => 'manual',
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );

			$message = "Assunto adicionado com sucesso.";
        }

		if (!$result) {
			$errors[] = "Um erro ocorreu durante a gravação dos dados no banco de dados.";
		}

		$_SESSION['pitch_results'] = [
			'status' => empty($errors) ? 'success' : 'error',
			'message' => $message,
			'errors' => $errors,
		];
    }

    private function render_form($pitch_id) {

		$pitch_results = false;

		if (isset($_SESSION['pitch_results'])) {
			$pitch_results = sf_retrieve($_SESSION, 'pitch_results', false);
			unset($_SESSION['pitch_results']);
		}

        global $wpdb;
        $pitch = null;

        if ($pitch_id) {
            $pitch = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $pitch_id), ARRAY_A);

            if (!$pitch) {
                wp_die(__('Assunto não encontrato.', SF_TEXTDOMAIN));
            }
        }

		if ($pitch_results): ?>
			<div class="<?php echo esc_attr($pitch_results['status'] === 'success' ? 'updated' : 'error'); ?>">
				<p><?php echo esc_html($pitch_results['message']); ?></p>
			</div>
        <?php endif; ?>

        <form method="post">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="category"><?php esc_html_e('Categoria', SF_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" name="category" id="category" class="regular-text" value="<?php echo esc_attr($pitch['category'] ?? ''); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="topic"><?php esc_html_e('Tópico', SF_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" name="topic" id="topic" class="regular-text" value="<?php echo esc_attr($pitch['topic'] ?? ''); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="main_seo_keyword"><?php esc_html_e('Palavra-chave SEO', SF_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" name="main_seo_keyword" id="main_seo_keyword" class="regular-text" value="<?php echo esc_attr($pitch['main_seo_keyword'] ?? ''); ?>" required>
                        </td>
                    </tr>
                    <tr class="form-field">
                        <th scope="row">
                            <label for="suggested_pitch"><?php esc_html_e('Assunto', SF_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <textarea name="suggested_pitch" id="suggested_pitch" rows="5" class="large-text" required><?php echo esc_textarea($pitch['suggested_pitch'] ?? ''); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Escreva uma sugestão de assunto para geração de uma nova matéria utilizando IA.', SF_TEXTDOMAIN); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button($pitch_id ? __('Atualizar', SF_TEXTDOMAIN) : __('Adicionar', SF_TEXTDOMAIN), 'primary', 'submit_pitch'); ?>
        </form>

        <?php
    }
}
