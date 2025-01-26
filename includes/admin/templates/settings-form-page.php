<?php

namespace StoryFlow\Admin\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class Settings_Form_Page {

    public function display() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_settings'])) {
            $this->handle_form_submission();
        }

        $this->render_form();
    }

    private function render_form() {
        // Recupera os valores salvos das opções
        $default_prompt = get_option('story_flow_default_prompt', '');
        $system_prompt = get_option('story_flow_system_prompt', '');
        $default_author = get_option('story_flow_default_author', '');

        // Lista de todos os usuários ativos para o campo de seleção
        $users = get_users(['fields' => ['ID', 'display_name']]);

        ?>
        <form method="post">
            <table class="form-table">
                <tr class="form-field">
                    <th scope="row">
                        <label for="default_prompt"><?php echo esc_html__('Prompt Padrão', SF_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <textarea name="default_prompt" id="default_prompt" rows="5" cols="50" class="large-text"><?php echo esc_textarea($default_prompt); ?></textarea>
                        <p class="description">Informe como você gostaria que a IA respondesse quando não houver regras configuradas.</p>
                    </td>
                </tr>
                <tr class="form-field">
                    <th scope="row">
                        <label for="system_prompt"><?php echo esc_html__('Instruções personalizadas', SF_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <textarea name="system_prompt" id="system_prompt" rows="5" cols="50" class="large-text"><?php echo esc_textarea($system_prompt); ?></textarea>
                        <p class="description">Defina diretrizes básicas de como a IA deve responder.</p>
                    </td>
                </tr>
                <tr class="form-field">
                    <th scope="row">
                        <label for="default_author"><?php echo esc_html__('Autor padrão', SF_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <select name="default_author" id="default_author" class="regular-text">
                            <option value="">— <?php echo esc_html__('Select a User', SF_TEXTDOMAIN); ?> —</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($default_author, $user->ID); ?>>
                                    <?php echo esc_html($user->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Selecione o usuário padrão para adicionar aos posts gerados.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="submit_settings" class="button-primary">
                    <?php echo esc_html__('Save Changes', SF_TEXTDOMAIN); ?>
                </button>
            </p>
        </form>
        <?php
    }

    private function handle_form_submission() {
        // Sanitiza e salva as configurações no banco de dados com autoload ativado
        if (isset($_POST['default_prompt'])) {
            update_option('story_flow_default_prompt', sanitize_textarea_field($_POST['default_prompt']), true);
        }
        if (isset($_POST['system_prompt'])) {
            update_option('story_flow_system_prompt', sanitize_textarea_field($_POST['system_prompt']), true);
        }
        if (isset($_POST['default_author'])) {
            update_option('story_flow_default_author', sanitize_text_field($_POST['default_author']), true);
        }

        // Exibe uma mensagem de sucesso
        add_settings_error('story_flow_settings', 'settings_updated', __('Configurações salvas com sucesso.', SF_TEXTDOMAIN), 'updated');
        settings_errors('story_flow_settings');
    }
}
